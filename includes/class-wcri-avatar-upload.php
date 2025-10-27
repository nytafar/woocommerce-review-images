<?php
/**
 * Avatar Upload Handler
 * 
 * Handles the upload and storage of user avatars for product reviews.
 * This provides an alternative to Gravatar that takes precedence in display.
 * 
 * @package WooCommerce_Review_Images
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Review_Images_Avatar_Upload
 * 
 * Manages avatar uploads for review authors.
 */
class WC_Review_Images_Avatar_Upload {

    /**
     * Meta key for storing avatar attachment ID
     */
    const META_KEY_AVATAR_ID = '_review_author_avatar_id';

    /**
     * Maximum file size for avatars (1MB)
     */
    const MAX_AVATAR_SIZE_BYTES = 1 * 1024 * 1024;

    /**
     * Allowed MIME types for avatar uploads
     */
    const ALLOWED_AVATAR_MIME_TYPES = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif'          => 'image/gif',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    );

    /**
     * Stores the uploaded avatar attachment ID temporarily
     * @var int|null
     */
    private static $uploaded_avatar_attachment_id = null;

    /**
     * Singleton instance
     * @var WC_Review_Images_Avatar_Upload|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     * 
     * @return WC_Review_Images_Avatar_Upload
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - setup hooks
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize hooks
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Note: Avatar upload field is now displayed by the main WC_Review_Images class
        // in a unified UI alongside the review image upload field

        // Handle avatar upload during comment processing
        add_action('preprocess_comment', array($this, 'handle_avatar_upload'));
        add_action('comment_post', array($this, 'save_avatar_meta'), 10, 2);

        // Admin display
        if (is_admin()) {
            add_action('add_meta_boxes_comment', array($this, 'add_avatar_meta_box'));
        }
    }

    /**
     * Display avatar upload field in review form
     */
    public function display_avatar_upload_field() {
        if (!is_product() || !comments_open()) {
            return;
        }

        static $avatar_field_displayed = false;
        if ($avatar_field_displayed) {
            return;
        }

        /**
         * Filter to enable/disable avatar upload functionality
         * 
         * @since 1.2.0
         * @param bool $enabled Whether avatar upload is enabled. Default true.
         */
        if (!apply_filters('wcri_enable_avatar_upload', true)) {
            return;
        }

        $default_label_text = __('Upload your profile photo (optional, max 1MB, JPG, PNG, GIF)', 'woocommerce-review-images');
        
        /**
         * Filter the avatar upload field label text
         * 
         * @since 1.2.0
         * @param string $default_label_text The default label text
         * @param WP_Post|null $product The current product
         */
        $label_text = apply_filters('wcri_avatar_upload_field_label_text', $default_label_text, get_post());

        echo '<p class="comment-form-avatar-upload">';
        echo '<label for="wcri_avatar_upload">' . esc_html($label_text) . '</label>';
        echo '<input type="file" id="wcri_avatar_upload" name="wcri_avatar_upload" accept="image/jpeg,image/png,image/gif,image/webp" />';
        echo '</p>';
        
        wp_nonce_field('wcri_avatar_upload_action', 'wcri_avatar_upload_nonce');
        
        $avatar_field_displayed = true;
    }

    /**
     * Handle avatar upload during comment processing
     * 
     * @param array $commentdata Comment data array
     * @return array Modified comment data
     */
    public function handle_avatar_upload($commentdata) {
        // Only process for product reviews
        if (!isset($_POST['comment_post_ID']) || 'product' !== get_post_type(absint($_POST['comment_post_ID']))) {
            return $commentdata;
        }

        // Verify nonce
        if (!isset($_POST['wcri_avatar_upload_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcri_avatar_upload_nonce'])), 'wcri_avatar_upload_action')) {
            return $commentdata;
        }

        // Check if avatar file was uploaded
        if (!isset($_FILES['wcri_avatar_upload']) || 
            empty($_FILES['wcri_avatar_upload']['name']) || 
            $_FILES['wcri_avatar_upload']['error'] !== UPLOAD_ERR_OK) {
            return $commentdata;
        }

        $file = $_FILES['wcri_avatar_upload'];

        // Validate file size
        if ($file['size'] > self::MAX_AVATAR_SIZE_BYTES) {
            error_log('WCRI Avatar Error: Uploaded file exceeds max size limit. File: ' . sanitize_file_name($file['name']));
            return $commentdata;
        }

        // Validate file type
        $file_type = wp_check_filetype($file['name'], self::ALLOWED_AVATAR_MIME_TYPES);
        if (!$file_type['ext'] || !$file_type['type']) {
            error_log('WCRI Avatar Error: Uploaded file type is not allowed. File: ' . sanitize_file_name($file['name']));
            return $commentdata;
        }

        // Load WordPress upload handlers
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload the file
        $attachment_id = media_handle_upload('wcri_avatar_upload', absint($_POST['comment_post_ID']));

        if (is_wp_error($attachment_id)) {
            error_log('WCRI Avatar Error: media_handle_upload failed. Message: ' . $attachment_id->get_error_message());
        } else {
            // Store attachment ID temporarily
            self::$uploaded_avatar_attachment_id = $attachment_id;
            
            // Set alt text for accessibility
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sprintf(
                __('Avatar for %s', 'woocommerce-review-images'),
                $commentdata['comment_author']
            ));
        }

        return $commentdata;
    }

    /**
     * Save avatar attachment ID to comment meta
     * 
     * @param int $comment_id Comment ID
     * @param int|string $comment_approved Comment approval status
     */
    public function save_avatar_meta($comment_id, $comment_approved) {
        if ($comment_approved && self::$uploaded_avatar_attachment_id !== null) {
            update_comment_meta($comment_id, self::META_KEY_AVATAR_ID, self::$uploaded_avatar_attachment_id);
            self::$uploaded_avatar_attachment_id = null;
        }
    }

    /**
     * Get avatar attachment ID for a comment
     * 
     * @param int $comment_id Comment ID
     * @return int|false Avatar attachment ID or false if not found
     */
    public static function get_comment_avatar_id($comment_id) {
        $avatar_id = get_comment_meta($comment_id, self::META_KEY_AVATAR_ID, true);
        return $avatar_id ? absint($avatar_id) : false;
    }

    /**
     * Check if comment has a custom avatar
     * 
     * @param int|WP_Comment $comment Comment ID or object
     * @return bool True if comment has custom avatar
     */
    public static function has_custom_avatar($comment) {
        $comment_id = is_object($comment) ? $comment->comment_ID : absint($comment);
        return (bool) self::get_comment_avatar_id($comment_id);
    }

    /**
     * Get avatar URL for a comment
     * 
     * @param int $comment_id Comment ID
     * @param string|array $size Image size
     * @return string|false Avatar URL or false if not found
     */
    public static function get_comment_avatar_url($comment_id, $size = 'thumbnail') {
        $avatar_id = self::get_comment_avatar_id($comment_id);
        if (!$avatar_id) {
            return false;
        }

        $image_url = wp_get_attachment_image_url($avatar_id, $size);
        return $image_url ? $image_url : false;
    }

    /**
     * Add meta box to comment edit screen for avatar
     */
    public function add_avatar_meta_box() {
        global $comment;
        
        if ($comment && 'product' === get_post_type($comment->comment_post_ID)) {
            add_meta_box(
                'wcri_review_avatar_meta_box',
                __('Review Author Avatar', 'woocommerce-review-images'),
                array($this, 'render_avatar_meta_box'),
                'comment',
                'normal',
                'high'
            );
        }
    }

    /**
     * Render avatar meta box content
     * 
     * @param WP_Comment $comment Comment object
     */
    public function render_avatar_meta_box($comment) {
        $avatar_id = self::get_comment_avatar_id($comment->comment_ID);
        
        if ($avatar_id) {
            $image_html = wp_get_attachment_image($avatar_id, 'thumbnail', false, array(
                'style' => 'max-width:150px; height:auto; border-radius: 50%;'
            ));
            
            if ($image_html) {
                echo '<div style="text-align:center;">' . $image_html . '</div>';
                echo '<p style="text-align:center;"><small>' . esc_html__('This custom avatar takes precedence over Gravatar.', 'woocommerce-review-images') . '</small></p>';
            } else {
                echo '<p>' . esc_html__('Avatar data found, but the image could not be displayed. It might have been deleted from the media library.', 'woocommerce-review-images') . '</p>';
            }
        } else {
            echo '<p>' . esc_html__('No custom avatar was uploaded with this review.', 'woocommerce-review-images') . '</p>';
        }
    }
}

// Initialize the avatar upload handler
if (apply_filters('wcri_enable_avatar_upload', true)) {
    WC_Review_Images_Avatar_Upload::get_instance();
}
