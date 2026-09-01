<?php
/**
 * Avatar upload and storage.
 *
 * An alternative to Gravatar that takes precedence in display.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Avatars;

defined('ABSPATH') || exit;

final class Upload
{
    public const META_KEY_AVATAR_ID = '_review_author_avatar_id';

    public const MAX_AVATAR_SIZE_BYTES = 1 * 1024 * 1024; // 1MB

    public const ALLOWED_AVATAR_MIME_TYPES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif'          => 'image/gif',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    ];

    private static ?self $instance = null;

    private static ?int $uploadedAvatarAttachmentId = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // The upload field itself is rendered by Reviews\Images, in a unified UI
        // alongside the review-image field.
        add_action('preprocess_comment', [$this, 'handleAvatarUpload']);
        add_action('comment_post', [$this, 'saveAvatarMeta'], 10, 2);

        if (is_admin()) {
            add_action('add_meta_boxes_comment', [$this, 'addAvatarMetaBox']);
        }
    }

    /**
     * @param array<string, mixed> $commentdata
     * @return array<string, mixed>
     */
    public function handleAvatarUpload($commentdata)
    {
        if (!isset($_POST['comment_post_ID']) || get_post_type(absint($_POST['comment_post_ID'])) !== 'product') {
            return $commentdata;
        }

        if (
            !isset($_POST['wcri_avatar_upload_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcri_avatar_upload_nonce'])), 'wcri_avatar_upload_action')
        ) {
            return $commentdata;
        }

        if (
            !isset($_FILES['wcri_avatar_upload'])
            || empty($_FILES['wcri_avatar_upload']['name'])
            || $_FILES['wcri_avatar_upload']['error'] !== UPLOAD_ERR_OK
        ) {
            return $commentdata;
        }

        $file = $_FILES['wcri_avatar_upload'];

        if ($file['size'] > self::MAX_AVATAR_SIZE_BYTES) {
            error_log('KaupangReviewImages avatar: uploaded file exceeds max size limit. File: ' . sanitize_file_name($file['name']));

            return $commentdata;
        }

        $fileType = wp_check_filetype($file['name'], self::ALLOWED_AVATAR_MIME_TYPES);
        if (!$fileType['ext'] || !$fileType['type']) {
            error_log('KaupangReviewImages avatar: uploaded file type is not allowed. File: ' . sanitize_file_name($file['name']));

            return $commentdata;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachmentId = media_handle_upload('wcri_avatar_upload', absint($_POST['comment_post_ID']));

        if (is_wp_error($attachmentId)) {
            error_log('KaupangReviewImages avatar: media_handle_upload failed. Message: ' . $attachmentId->get_error_message());

            return $commentdata;
        }

        self::$uploadedAvatarAttachmentId = $attachmentId;

        update_post_meta($attachmentId, '_wp_attachment_image_alt', sprintf(
            /* translators: %s: review author name. */
            __('Avatar for %s', 'kaupang-review-images'),
            $commentdata['comment_author']
        ));

        return $commentdata;
    }

    /**
     * @param int        $commentId
     * @param int|string $commentApproved
     */
    public function saveAvatarMeta($commentId, $commentApproved): void
    {
        if ($commentApproved && self::$uploadedAvatarAttachmentId !== null) {
            update_comment_meta($commentId, self::META_KEY_AVATAR_ID, self::$uploadedAvatarAttachmentId);
            self::$uploadedAvatarAttachmentId = null;
        }
    }

    /**
     * @return int|false Avatar attachment ID, or false when there is none.
     */
    public static function getCommentAvatarId(int $commentId)
    {
        $avatarId = get_comment_meta($commentId, self::META_KEY_AVATAR_ID, true);

        return $avatarId ? absint($avatarId) : false;
    }

    /**
     * @param int|\WP_Comment $comment
     */
    public static function hasCustomAvatar($comment): bool
    {
        $commentId = is_object($comment) ? (int) $comment->comment_ID : absint($comment);

        return (bool) self::getCommentAvatarId($commentId);
    }

    /**
     * @param string|array<int, int> $size
     * @return string|false
     */
    public static function getCommentAvatarUrl(int $commentId, $size = 'thumbnail')
    {
        $avatarId = self::getCommentAvatarId($commentId);
        if (!$avatarId) {
            return false;
        }

        return wp_get_attachment_image_url($avatarId, $size) ?: false;
    }

    public function addAvatarMetaBox(): void
    {
        global $comment;

        if ($comment && get_post_type($comment->comment_post_ID) === 'product') {
            add_meta_box(
                'kaupang_review_avatar_meta_box',
                __('Review Author Avatar', 'kaupang-review-images'),
                [$this, 'renderAvatarMetaBox'],
                'comment',
                'normal',
                'high'
            );
        }
    }

    public function renderAvatarMetaBox(\WP_Comment $comment): void
    {
        $avatarId = self::getCommentAvatarId((int) $comment->comment_ID);

        if (!$avatarId) {
            echo '<p>' . esc_html__('No custom avatar was uploaded with this review.', 'kaupang-review-images') . '</p>';

            return;
        }

        $html = wp_get_attachment_image($avatarId, 'thumbnail', false, [
            'style' => 'max-width:150px; height:auto; border-radius: 50%;',
        ]);

        if ($html) {
            echo '<div style="text-align:center;">' . $html . '</div>';
            echo '<p style="text-align:center;"><small>' . esc_html__('This custom avatar takes precedence over Gravatar.', 'kaupang-review-images') . '</small></p>';
        } else {
            echo '<p>' . esc_html__('Avatar data found, but the image could not be displayed. It might have been deleted from the media library.', 'kaupang-review-images') . '</p>';
        }
    }
}
