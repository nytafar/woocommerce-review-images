<?php
/**
 * Seed one fully-populated product review to develop and check against.
 *
 * Staging carries 143 reviews but zero _review_image_id and zero
 * _review_author_avatar_id, so half this plugin has nothing to render.
 *
 *   sudo -u myrvann wp --path=/var/www/staging.myrvann.no/htdocs \
 *     eval-file wp-content/plugins/kaupang-review-images/bin/seed-review-fixtures.php
 *   ... eval-file .../seed-review-fixtures.php clean
 *
 * Idempotent: a second create reuses the existing fixture rather than piling up
 * duplicates. Everything it makes is tagged with KRI_FIXTURE_META so clean can
 * find it again, review and attachments alike.
 *
 * Loaded as a library by check-review-block.php, which defines
 * KRI_FIXTURES_LIB first to suppress the run-directly block at the bottom.
 *
 * @package KaupangReviewImages
 */

// No declare(strict_types=1): `wp eval-file` evals the source, and a declare
// must be the first statement of a script.

const KRI_FIXTURE_META  = '_kri_fixture';
const KRI_FIXTURE_EMAIL = 'fixture@kaupang-review-images.invalid';

/**
 * A real image, not a 1x1: the avatar chain asks for [120,120] and a 2x srcset,
 * which only means something if intermediate sizes actually get generated.
 */
function kri_fixture_attachment(string $filename, int $size, array $rgb): int
{
    if (!function_exists('imagecreatetruecolor')) {
        kri_fixture_fail('GD is not available; cannot generate fixture images.');
    }

    $image = imagecreatetruecolor($size, $size);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    // A diagonal, so a human can tell a fixture from real media at a glance.
    imageline($image, 0, 0, $size, $size, imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $upload = wp_upload_bits($filename, null, $bytes);
    if (!empty($upload['error'])) {
        kri_fixture_fail('wp_upload_bits failed: ' . $upload['error']);
    }

    $attachmentId = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title'     => sanitize_file_name($filename),
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (is_wp_error($attachmentId) || !$attachmentId) {
        kri_fixture_fail('wp_insert_attachment failed.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata(
        $attachmentId,
        wp_generate_attachment_metadata($attachmentId, $upload['file'])
    );

    update_post_meta($attachmentId, KRI_FIXTURE_META, 1);

    return (int) $attachmentId;
}

/**
 * @return array{comment_id:int, product_id:int, image_id:int, avatar_id:int}
 */
function kri_fixture_create(): array
{
    $existing = get_comments([
        'meta_key' => KRI_FIXTURE_META,
        'number'   => 1,
        'status'   => 'all',
        'type'     => 'review',
    ]);

    if ($existing) {
        $comment = $existing[0];

        return [
            'comment_id' => (int) $comment->comment_ID,
            'product_id' => (int) $comment->comment_post_ID,
            'image_id'   => (int) get_comment_meta((int) $comment->comment_ID, '_review_image_id', true),
            'avatar_id'  => (int) get_comment_meta((int) $comment->comment_ID, '_review_author_avatar_id', true),
        ];
    }

    $products = wc_get_products(['limit' => 1, 'status' => 'publish', 'return' => 'ids']);
    if (!$products) {
        kri_fixture_fail('No published product to attach a review to.');
    }
    $productId = (int) $products[0];

    $commentId = wp_insert_comment([
        'comment_post_ID'      => $productId,
        'comment_author'       => 'Kaupang Fixture',
        'comment_author_email' => KRI_FIXTURE_EMAIL,
        'comment_content'      => 'Fixture review. Created by bin/seed-review-fixtures.php; safe to delete.',
        'comment_type'         => 'review',
        'comment_approved'     => 1,
    ]);

    if (!$commentId) {
        kri_fixture_fail('wp_insert_comment failed.');
    }
    $commentId = (int) $commentId;

    $imageId  = kri_fixture_attachment('kri-fixture-review-image.png', 800, [34, 85, 51]);
    $avatarId = kri_fixture_attachment('kri-fixture-avatar.png', 400, [85, 34, 51]);

    update_comment_meta($commentId, KRI_FIXTURE_META, 1);
    update_comment_meta($commentId, 'rating', 5);
    update_comment_meta($commentId, 'verified', 1);
    update_comment_meta($commentId, '_review_image_id', $imageId);
    update_comment_meta($commentId, '_review_author_avatar_id', $avatarId);

    return [
        'comment_id' => $commentId,
        'product_id' => $productId,
        'image_id'   => $imageId,
        'avatar_id'  => $avatarId,
    ];
}

function kri_fixture_delete(): int
{
    $removed = 0;

    foreach (get_comments(['meta_key' => KRI_FIXTURE_META, 'status' => 'all', 'type' => 'review']) as $comment) {
        wp_delete_comment((int) $comment->comment_ID, true);
        $removed++;
    }

    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_key'       => KRI_FIXTURE_META,
        'fields'         => 'ids',
    ]);

    foreach ($attachments as $attachmentId) {
        wp_delete_attachment((int) $attachmentId, true);
        $removed++;
    }

    delete_transient('kaupang_review_images_has_gravatar_' . md5(KRI_FIXTURE_EMAIL));

    return $removed;
}

function kri_fixture_fail(string $message): void
{
    fwrite(STDERR, 'seed-review-fixtures: ' . $message . PHP_EOL);
    exit(1);
}

if (!defined('KRI_FIXTURES_LIB')) {
    $action = $args[0] ?? 'create';

    if ($action === 'clean') {
        printf("removed %d fixture objects\n", kri_fixture_delete());
    } else {
        $fixture = kri_fixture_create();
        printf(
            "review %d on product %d (image %d, avatar %d)\n",
            $fixture['comment_id'],
            $fixture['product_id'],
            $fixture['image_id'],
            $fixture['avatar_id']
        );
    }
}
