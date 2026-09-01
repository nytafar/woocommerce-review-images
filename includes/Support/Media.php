<?php
/**
 * Shared media ingest.
 *
 * The review-image and avatar upload handlers were the same size -> MIME ->
 * media_handle_upload() sequence with different constants. One copy now.
 *
 * Nonce verification deliberately stays with the caller: the two handlers use
 * different nonce actions that are not derivable from the field name, and
 * threading them through here would cost more than it saves.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Support;

defined('ABSPATH') || exit;

final class Media
{
    /**
     * Both upload paths accept the same four types.
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif'          => 'image/gif',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    ];

    /**
     * Sideload one uploaded file into the media library.
     *
     * Returns a WP_Error for every rejection so the caller can log it with its
     * own prefix; nothing is logged here.
     *
     * @param string $field    Key in $_FILES.
     * @param int    $postId   Post to attach to.
     * @param int    $maxBytes Size ceiling.
     * @return int|\WP_Error Attachment ID, or the reason it was rejected.
     */
    public static function ingest(string $field, int $postId, int $maxBytes)
    {
        if (
            !isset($_FILES[$field])
            || empty($_FILES[$field]['name'])
            || $_FILES[$field]['error'] !== UPLOAD_ERR_OK
        ) {
            return new \WP_Error('kaupang_review_images_no_file', 'No usable file in $_FILES[' . $field . '].');
        }

        $file = $_FILES[$field];
        $name = sanitize_file_name((string) $file['name']);

        if ((int) $file['size'] > $maxBytes) {
            return new \WP_Error(
                'kaupang_review_images_too_large',
                sprintf('File exceeds the %d byte limit. File: %s', $maxBytes, $name)
            );
        }

        $fileType = wp_check_filetype($name, self::ALLOWED_MIME_TYPES);
        if (!$fileType['ext'] || !$fileType['type']) {
            return new \WP_Error(
                'kaupang_review_images_bad_type',
                sprintf('File type is not allowed. File: %s Detected type: %s', $name, sanitize_mime_type((string) $file['type']))
            );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachmentId = media_handle_upload($field, $postId);

        if (is_wp_error($attachmentId)) {
            return new \WP_Error(
                'kaupang_review_images_upload_failed',
                sprintf('media_handle_upload failed: %s File: %s', $attachmentId->get_error_message(), $name)
            );
        }

        return (int) $attachmentId;
    }

    /**
     * Cache-busting version for a bundled asset.
     *
     * filemtime beats the plugin version string, which drifts from the header
     * the moment someone edits CSS without bumping it.
     */
    public static function assetVersion(string $relativePath): string
    {
        $mtime = @filemtime(KAUPANG_REVIEW_IMAGES_DIR . ltrim($relativePath, '/'));

        return $mtime ? (string) $mtime : KAUPANG_REVIEW_IMAGES_VERSION;
    }
}
