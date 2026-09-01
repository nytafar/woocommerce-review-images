<?php
/**
 * Runnable check for the Curated Review block. Exits non-zero on failure.
 *
 *   sudo -u myrvann wp --path=/var/www/staging.myrvann.no/htdocs \
 *     eval-file wp-content/plugins/kaupang-review-images/bin/check-review-block.php
 *
 * Seeds its own fixture, asserts, and trashes it again -- including when an
 * assertion fails, so a red run does not leave a review behind. No PHPUnit, no
 * fixtures framework: this is the smallest thing that goes red if the block,
 * the accessor or the avatar chain breaks.
 *
 * The Gravatar half of the avatar chain is made deterministic by pre-seeding
 * the transient Gravatar::hasGravatar() caches into, so the check never depends
 * on a network round trip to gravatar.com.
 *
 * @package KaupangReviewImages
 */

// No declare(strict_types=1): `wp eval-file` evals the source, and a declare
// must be the first statement of a script.

// wp-fail2ban's syslog writer reads $_SERVER['HTTP_HOST'] unguarded and fatals
// under WP-CLI without it. Not our bug, but it takes the whole check down.
// ponytail: drop this line if wp-fail2ban ever guards its own getHost().
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? (string) wp_parse_url(home_url(), PHP_URL_HOST);

define('KRI_FIXTURES_LIB', true);
require_once __DIR__ . '/seed-review-fixtures.php';

use Kaupang\ReviewImages\Block\CuratedReview;
use Kaupang\ReviewImages\Reviews\Images;

// $GLOBALS, not `global`: `wp eval-file` evals this inside a method, so the
// top-level scope here is function scope and `global` would bind elsewhere.
// That silently made every failure invisible and the exit code always 0.
$GLOBALS['kri_failures'] = [];
$GLOBALS['kri_checks']   = 0;

function kri_check(string $name, bool $ok, string $detail = ''): void
{
    $GLOBALS['kri_checks']++;

    if ($ok) {
        echo "  ok   $name\n";

        return;
    }

    echo "  FAIL $name" . ($detail !== '' ? " -- $detail" : '') . "\n";
    $GLOBALS['kri_failures'][] = $name;
}

function kri_gravatar_cache(string $email, bool $exists): void
{
    set_transient(
        'kaupang_review_images_has_gravatar_' . md5($email),
        $exists ? 'yes' : 'no',
        HOUR_IN_SECONDS
    );
}

/** Every toggle on, so nothing is hidden from the assertions. */
function kri_all_on(int $reviewId): array
{
    return [
        'reviewId'         => $reviewId,
        'showBody'         => true,
        'showReviewImages' => true,
        'showRating'       => true,
        'showAuthor'       => true,
        'showAvatar'       => true,
        'showVerified'     => true,
        'showDate'         => true,
        'showProductName'  => true,
        'showProductImage' => true,
    ];
}

function kri_all_off(int $reviewId): array
{
    return array_merge(kri_all_on($reviewId), array_fill_keys([
        'showBody',
        'showReviewImages',
        'showRating',
        'showAuthor',
        'showAvatar',
        'showVerified',
        'showDate',
        'showProductName',
        'showProductImage',
    ], false));
}

$fixture = kri_fixture_create();
$id      = $fixture['comment_id'];

try {
    echo "fixture: review {$id} on product {$fixture['product_id']}\n";

    // --- accessor -----------------------------------------------------------
    kri_check(
        'accessor returns [id] for a review with an image',
        Images::getImageIds($id) === [$fixture['image_id']],
        wp_json_encode(Images::getImageIds($id))
    );

    $bare = wp_insert_comment([
        'comment_post_ID'  => $fixture['product_id'],
        'comment_author'       => 'Kaupang Fixture (no image)',
        'comment_author_email' => 'no-avatar@kaupang-review-images.invalid',
        'comment_type'         => 'review',
        'comment_approved' => 1,
        'comment_content'  => 'Fixture without an image.',
    ]);
    $bare = (int) $bare;
    update_comment_meta($bare, KRI_FIXTURE_META, 1);

    kri_check('accessor returns [] for a review with no image', Images::getImageIds($bare) === []);

    // --- render: happy path -------------------------------------------------
    kri_gravatar_cache(KRI_FIXTURE_EMAIL, true);
    $html = CuratedReview::render(kri_all_on($id));

    // Assert against the OPENING <article> TAG, not the whole document: a
    // substring search for class="kaupang-review passes on any child element,
    // and state attributes would pass wherever they sat.
    preg_match('#^<article\b[^>]*>#', $html, $rootMatch);
    $root = $rootMatch[0] ?? '';

    kri_check('root element is <article> with the block class', (bool) preg_match('#\bclass="[^"]*\bkaupang-review\b#', $root), $root);
    kri_check('root carries data-review-id', strpos($root, 'data-review-id="' . $id . '"') !== false, $root);
    kri_check('root carries data-rating', strpos($root, 'data-rating="5"') !== false, $root);
    kri_check('root carries data-verified', strpos($root, 'data-verified="1"') !== false, $root);
    kri_check('root carries data-has-images', strpos($root, 'data-has-images="1"') !== false, $root);
    kri_check('review image renders', strpos($html, 'kaupang-review__image') !== false);
    kri_check('rating renders WooCommerce star markup', strpos($html, 'class="star-rating"') !== false);

    // Document order is part of the contract: body, images, rating, author,
    // product. Toggles decide presence, never position.
    $order = [];
    foreach (['kaupang-review__body', 'kaupang-review__images', 'kaupang-review__rating', 'kaupang-review__author', 'kaupang-review__product'] as $class) {
        $order[$class] = strpos($html, $class);
    }
    // strpos() returns false for an absent element, and false sorts before every
    // integer -- so a missing section used to make this assertion vacuously
    // true. Every section must be present AND in order.
    $missing   = array_keys($order, false, true);
    $positions = array_values($order);
    $sorted    = $positions;
    sort($sorted);
    kri_check(
        'document order is body, images, rating, author, product',
        !$missing && $positions === $sorted,
        $missing ? 'missing: ' . implode(', ', $missing) : wp_json_encode($order)
    );

    // --- read more ----------------------------------------------------------
    $plain = CuratedReview::render(kri_all_on($id));
    kri_check('no expand button unless asked for', strpos($plain, 'kaupang-review__more') === false);
    kri_check('no data-expandable unless asked for', strpos($plain, 'data-expandable') === false);

    $expand = CuratedReview::render(array_merge(kri_all_on($id), ['expandable' => true]));
    kri_check('expandable marks the root', strpos($expand, 'data-expandable') !== false);
    kri_check('expand button ships hidden', (bool) preg_match('#<button[^>]*kaupang-review__more[^>]*\bhidden\b#', $expand), $expand);
    kri_check('expand button starts collapsed', strpos($expand, 'aria-expanded="false"') !== false);

    // aria-controls must point at THIS instance's body, and two instances on one
    // page must not collide -- the same review can legitimately appear twice.
    preg_match('#<blockquote class="kaupang-review__body" id="([^"]+)"#', $expand, $bodyIdMatch);
    preg_match('#<button[^>]*aria-controls="([^"]+)"#', $expand, $controlsMatch);
    kri_check(
        'aria-controls points at this instance\'s body',
        !empty($bodyIdMatch[1]) && ($bodyIdMatch[1] ?? null) === ($controlsMatch[1] ?? null),
        wp_json_encode([$bodyIdMatch[1] ?? null, $controlsMatch[1] ?? null])
    );

    $second = CuratedReview::render(array_merge(kri_all_on($id), ['expandable' => true]));
    preg_match('#<blockquote class="kaupang-review__body" id="([^"]+)"#', $second, $secondIdMatch);
    kri_check(
        'a second instance of the same review gets its own id',
        !empty($secondIdMatch[1]) && $secondIdMatch[1] !== ($bodyIdMatch[1] ?? null),
        wp_json_encode([$bodyIdMatch[1] ?? null, $secondIdMatch[1] ?? null])
    );

    kri_check(
        'both label states travel with the button',
        strpos($expand, 'data-label-more=') !== false && strpos($expand, 'data-label-less=') !== false
    );

    kri_check(
        'expandable is inert when the body is hidden',
        strpos(CuratedReview::render(array_merge(kri_all_on($id), ['expandable' => true, 'showBody' => false])), 'kaupang-review__more') === false
    );

    // --- render: empty states ----------------------------------------------
    kri_check('deleted id renders empty on the frontend', CuratedReview::render(['reviewId' => 999999999]) === '');
    kri_check('id 0 renders empty on the frontend', CuratedReview::render(['reviewId' => 0]) === '');

    // Created unapproved rather than transitioned: wp_set_comment_status() fires
    // the post-author notification chain, which is what drags wp-fail2ban in.
    $unapproved = (int) wp_insert_comment([
        'comment_post_ID'  => $fixture['product_id'],
        'comment_author'   => 'Kaupang Fixture (unapproved)',
        'comment_type'     => 'review',
        'comment_approved' => 0,
        'comment_content'  => 'Fixture awaiting moderation.',
    ]);
    update_comment_meta($unapproved, KRI_FIXTURE_META, 1);
    kri_check('unapproved review renders empty', CuratedReview::render(kri_all_on($unapproved)) === '');

    // --- render: all toggles off -------------------------------------------
    $off = CuratedReview::render(kri_all_off($id));
    preg_match('#^<article\b[^>]*>#', $off, $offRoot);
    kri_check('all toggles off keeps the state attributes', strpos($offRoot[0] ?? '', 'data-rating="5"') !== false, $off);
    kri_check(
        'all toggles off emits no children',
        (bool) preg_match('#^<article [^>]*></article>$#', trim($off)),
        trim($off)
    );

    // --- no structured data, no inline style we authored ---------------------
    kri_check('no itemprop', stripos($html, 'itemprop') === false);
    kri_check('no itemscope', stripos($html, 'itemscope') === false);
    kri_check('no JSON-LD', stripos($html, 'application/ld') === false);

    // WooCommerce's own star span carries style="width:X%" -- that is its data
    // encoding of the rating, not markup this block authored. Everything
    // outside it must be style-free.
    $outsideStars = (string) preg_replace('#<span class="star-rating".*?</span></span>#s', '', $html);
    kri_check('no inline style outside WooCommerce\'s star span', stripos($outsideStars, 'style=') === false, $outsideStars);

    // --- avatar chain -------------------------------------------------------
    // Upload must beat a real Gravatar.
    kri_gravatar_cache(KRI_FIXTURE_EMAIL, true);
    $withUpload = CuratedReview::render(kri_all_on($id));
    $avatarUrl  = (string) wp_get_attachment_image_url($fixture['avatar_id'], [120, 120]);
    kri_check('custom upload wins over a real Gravatar', $avatarUrl !== '' && strpos($withUpload, $avatarUrl) !== false);
    kri_check('avatar carries the block class', strpos($withUpload, 'kaupang-review__avatar') !== false);

    // Neither an upload nor a Gravatar must produce no <img> at all.
    $noAvatar = array_merge(kri_all_on($bare), ['showReviewImages' => false, 'showProductImage' => false]);
    kri_gravatar_cache((string) get_comment($bare)->comment_author_email, false);
    $bareHtml = CuratedReview::render($noAvatar);
    kri_check('no upload and no Gravatar renders no <img>', stripos($bareHtml, '<img') === false, $bareHtml);

    // A review with no author address at all: no Gravatar can exist, so the
    // default silhouette must not survive either.
    $anon = (int) wp_insert_comment([
        'comment_post_ID'  => $fixture['product_id'],
        'comment_author'   => 'Kaupang Fixture (no email)',
        'comment_type'     => 'review',
        'comment_approved' => 1,
        'comment_content'  => 'Fixture with no author email.',
    ]);
    update_comment_meta($anon, KRI_FIXTURE_META, 1);
    $anonHtml = CuratedReview::render(array_merge(kri_all_on($anon), ['showReviewImages' => false, 'showProductImage' => false]));
    kri_check('review with no author email renders no <img>', stripos($anonHtml, '<img') === false, $anonHtml);
} finally {
    kri_fixture_delete();
    echo "fixture removed\n";
}

printf("\n%d checks, %d failed\n", $GLOBALS['kri_checks'], count($GLOBALS['kri_failures']));

if ($GLOBALS['kri_failures']) {
    foreach ($GLOBALS['kri_failures'] as $name) {
        echo "  - $name\n";
    }
    exit(1);
}

exit(0);
