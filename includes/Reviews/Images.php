<?php
/**
 * Review image upload, storage and display.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Reviews;

use Kaupang\ReviewImages\Support\Media;

defined('ABSPATH') || exit;

final class Images
{
    public const META_KEY_IMAGE_ID = '_review_image_id';

    public const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024; // 2MB

    private static ?self $instance = null;

    private static ?int $uploadedImageAttachmentId = null;

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
            add_action('admin_notices', [$this, 'woocommerceMissingNotice']);

            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        add_action('comment_form_logged_in_after', [$this, 'displayUploadFieldAndNonce']);
        add_action('comment_form_after_fields', [$this, 'displayUploadFieldAndNonce']);
        add_action('wp_footer', [$this, 'ensureFormEnctypeScript'], 99);
        add_action('preprocess_comment', [$this, 'handleImageUpload']);
        add_action('comment_post', [$this, 'saveImageMeta'], 10, 2);
        add_action('woocommerce_review_before', [$this, 'displayReviewImage']);

        if (is_admin()) {
            add_filter('manage_edit-comments_columns', [$this, 'addReviewImageAdminColumnHeader']);
            add_action('manage_comments_custom_column', [$this, 'displayReviewImageAdminColumnContent'], 10, 2);
            add_action('add_meta_boxes_comment', [$this, 'addReviewImageMetaBox']);
        }
    }

    public function enqueueAssets(): void
    {
        if (!function_exists('is_product') || !is_product() || !comments_open()) {
            return;
        }

        if (apply_filters('kaupang/review-images/enqueue_styles', true)) {
            wp_enqueue_style(
                'kaupang-review-images',
                KAUPANG_REVIEW_IMAGES_URL . 'assets/css/kaupang-review-images.css',
                [],
                Media::assetVersion('assets/css/kaupang-review-images.css')
            );
        }

        wp_enqueue_script(
            'kaupang-review-images-toggle',
            KAUPANG_REVIEW_IMAGES_URL . 'assets/js/review-form-toggle.js',
            [],
            Media::assetVersion('assets/js/review-form-toggle.js'),
            true
        );
    }

    public function woocommerceMissingNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Kaupang Review Images requires WooCommerce to be installed and active.', 'kaupang-review-images'); ?></p>
        </div>
        <?php
    }

    public function displayUploadFieldAndNonce(): void
    {
        if (!is_product() || !comments_open()) {
            return;
        }

        static $fieldDisplayed = false;
        if ($fieldDisplayed) {
            return;
        }

        $avatarEnabled = apply_filters('kaupang/review-images/enable_avatar_upload', true);

        $avatarLabel = apply_filters('kaupang/review-images/avatar_upload_field_label', __('Your Photo', 'kaupang-review-images'), get_post());
        $reviewLabel = apply_filters('kaupang/review-images/upload_field_label', __('Product Image', 'kaupang-review-images'), get_post());

        ?>
        <div class="wcri-upload-container">
            <?php if ($avatarEnabled) : ?>
            <div class="wcri-upload-field comment-form-avatar-upload">
                <label for="wcri_avatar_upload">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?php echo esc_html($avatarLabel); ?>
                </label>
                <input type="file" id="wcri_avatar_upload" name="wcri_avatar_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
                <label for="wcri_avatar_upload" class="wcri-upload-button">
                    <?php esc_html_e('Choose Photo', 'kaupang-review-images'); ?>
                </label>
                <div class="wcri-upload-hint"><?php esc_html_e('Upload your profile picture', 'kaupang-review-images'); ?></div>
                <div class="wcri-preview-container" id="wcri_avatar_preview">
                    <img class="wcri-preview-image" alt="<?php esc_attr_e('Avatar preview', 'kaupang-review-images'); ?>" />
                    <a href="#" class="wcri-remove-image" data-target="wcri_avatar_upload"><?php esc_html_e('Remove', 'kaupang-review-images'); ?></a>
                </div>
                <?php wp_nonce_field('wcri_avatar_upload_action', 'wcri_avatar_upload_nonce', false); ?>
            </div>
            <?php endif; ?>

            <div class="wcri-upload-field comment-form-image-upload">
                <label for="wcri_review_image_upload">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <?php echo esc_html($reviewLabel); ?>
                </label>
                <input type="file" id="wcri_review_image_upload" name="wcri_review_image_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
                <label for="wcri_review_image_upload" class="wcri-upload-button">
                    <?php esc_html_e('Choose Image', 'kaupang-review-images'); ?>
                </label>
                <div class="wcri-upload-hint"><?php esc_html_e('Share a photo of the product', 'kaupang-review-images'); ?></div>
                <div class="wcri-preview-container" id="wcri_review_image_preview">
                    <img class="wcri-preview-image" alt="<?php esc_attr_e('Review image preview', 'kaupang-review-images'); ?>" />
                    <a href="#" class="wcri-remove-image" data-target="wcri_review_image_upload"><?php esc_html_e('Remove', 'kaupang-review-images'); ?></a>
                </div>
                <?php wp_nonce_field('wcri_image_upload_action', 'wcri_image_upload_nonce', false); ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const wcriStrings = {
                changePhoto: '<?php echo esc_js(__('Change Photo', 'kaupang-review-images')); ?>',
                changeImage: '<?php echo esc_js(__('Change Image', 'kaupang-review-images')); ?>',
                choosePhoto: '<?php echo esc_js(__('Choose Photo', 'kaupang-review-images')); ?>',
                chooseImage: '<?php echo esc_js(__('Choose Image', 'kaupang-review-images')); ?>'
            };

            $('.wcri-upload-field input[type="file"]').on('change', function(e) {
                const file = e.target.files[0];
                const $field = $(this).closest('.wcri-upload-field');
                const $preview = $field.find('.wcri-preview-container');
                const $previewImg = $preview.find('.wcri-preview-image');
                const $button = $field.find('.wcri-upload-button');
                const isAvatar = $field.hasClass('comment-form-avatar-upload');

                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $previewImg.attr('src', e.target.result);
                        $preview.show();
                        $button.text(isAvatar ? wcriStrings.changePhoto : wcriStrings.changeImage);
                    };
                    reader.readAsDataURL(file);
                } else {
                    $preview.hide();
                    $button.text(isAvatar ? wcriStrings.choosePhoto : wcriStrings.chooseImage);
                }
            });

            $('.wcri-remove-image').on('click', function(e) {
                e.preventDefault();
                const $field = $(this).closest('.wcri-upload-field');
                const $preview = $field.find('.wcri-preview-container');
                const $button = $field.find('.wcri-upload-button');
                const isAvatar = $field.hasClass('comment-form-avatar-upload');

                $field.find('input[type="file"]').val('');
                $preview.hide();
                $button.text(isAvatar ? wcriStrings.choosePhoto : wcriStrings.chooseImage);
            });
        });
        </script>
        <?php
        $fieldDisplayed = true;
    }

    public function ensureFormEnctypeScript(): void
    {
        if (!is_product() || !comments_open()) {
            return;
        }

        echo "<script type='text/javascript'>
            jQuery(document).ready(function($) {
                var commentForm = $('#commentform');
                if (commentForm.length) {
                    commentForm.attr('enctype', 'multipart/form-data');
                }
            });
        </script>";
    }

    /**
     * @param array<string, mixed> $commentdata
     * @return array<string, mixed>
     */
    public function handleImageUpload($commentdata)
    {
        if (!isset($_POST['comment_post_ID']) || get_post_type(absint($_POST['comment_post_ID'])) !== 'product') {
            return $commentdata;
        }

        if (
            !isset($_POST['wcri_image_upload_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcri_image_upload_nonce'])), 'wcri_image_upload_action')
        ) {
            return $commentdata;
        }

        $attachmentId = Media::ingest(
            'wcri_review_image_upload',
            absint($_POST['comment_post_ID']),
            self::MAX_FILE_SIZE_BYTES
        );

        if (is_wp_error($attachmentId)) {
            if ($attachmentId->get_error_code() !== 'kaupang_review_images_no_file') {
                error_log('KaupangReviewImages: ' . $attachmentId->get_error_message());
            }

            return $commentdata;
        }

        self::$uploadedImageAttachmentId = $attachmentId;

        return $commentdata;
    }

    /**
     * @param int        $commentId
     * @param int|string $commentApproved
     */
    public function saveImageMeta($commentId, $commentApproved): void
    {
        if ($commentApproved && self::$uploadedImageAttachmentId !== null) {
            update_comment_meta($commentId, self::META_KEY_IMAGE_ID, self::$uploadedImageAttachmentId);
            self::$uploadedImageAttachmentId = null;
        }
    }

    /**
     * Attachment ids of the images on a review.
     *
     * Zero or one element today. An array from day one so that multi-image
     * upload later is a storage change only -- no block change, no theme CSS
     * change. The meta key stays _review_image_id: existing data keeps working.
     *
     * @return array<int, int>
     */
    public static function getImageIds(int $commentId): array
    {
        $imageId = get_comment_meta($commentId, self::META_KEY_IMAGE_ID, true);

        return $imageId ? [absint($imageId)] : [];
    }

    public function displayReviewImage(\WP_Comment $comment): void
    {
        $imageIds = self::getImageIds((int) $comment->comment_ID);

        foreach ($imageIds as $imageId) {
            $html = wp_get_attachment_image($imageId, 'medium', false, ['style' => 'height:auto;width:100%;']);
            if ($html) {
                echo $html;
            }
        }
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addReviewImageAdminColumnHeader($columns)
    {
        $newColumns   = [];
        $insertBefore = isset($columns['date']) ? 'date' : 'response';

        foreach ($columns as $key => $title) {
            if ($key === $insertBefore) {
                $newColumns['review_image'] = __('Image', 'kaupang-review-images');
            }
            $newColumns[$key] = $title;
        }

        if (!isset($newColumns['review_image'])) {
            $newColumns['review_image'] = __('Image', 'kaupang-review-images');
        }

        return $newColumns;
    }

    /**
     * @param string $columnName
     * @param int    $commentId
     */
    public function displayReviewImageAdminColumnContent($columnName, $commentId): void
    {
        if ($columnName !== 'review_image') {
            return;
        }

        $imageIds = self::getImageIds(absint($commentId));
        $html     = '';

        foreach ($imageIds as $imageId) {
            $html .= wp_get_attachment_image($imageId, [80, 80], true, ['style' => 'max-width:80px; height:auto; display:block; margin:auto;']);
        }

        echo $html ?: esc_html__('N/A', 'kaupang-review-images');
    }

    public function addReviewImageMetaBox(): void
    {
        global $comment;

        if ($comment && get_post_type($comment->comment_post_ID) === 'product') {
            add_meta_box(
                'kaupang_review_image_meta_box',
                __('Review Image', 'kaupang-review-images'),
                [$this, 'renderReviewImageMetaBox'],
                'comment',
                'normal',
                'high'
            );
        }
    }

    public function renderReviewImageMetaBox(\WP_Comment $comment): void
    {
        $imageIds = self::getImageIds((int) $comment->comment_ID);

        if (!$imageIds) {
            echo '<p>' . esc_html__('No image was uploaded with this review.', 'kaupang-review-images') . '</p>';

            return;
        }

        $html = '';
        foreach ($imageIds as $imageId) {
            $html .= wp_get_attachment_image($imageId, 'medium', false, ['style' => 'max-width:100%; height:auto;']);
        }

        if ($html) {
            echo $html;
        } else {
            echo '<p>' . esc_html__('Image data found, but the image could not be displayed. It might have been deleted from the media library.', 'kaupang-review-images') . '</p>';
        }
    }
}
