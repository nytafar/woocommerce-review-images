<?php
/**
 * Avatar Display Handler
 * 
 * Extends the existing Gravatar functionality to display custom uploaded avatars.
 * Custom avatars take precedence over Gravatar images.
 * 
 * @package WooCommerce_Review_Images
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Review_Images_Avatar_Display
 * 
 * Manages avatar display with priority: Custom Upload > Gravatar > Hidden
 */
class WC_Review_Images_Avatar_Display {

    /**
     * Singleton instance
     * @var WC_Review_Images_Avatar_Display|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     * 
     * @return WC_Review_Images_Avatar_Display
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

        // Hook into get_avatar with high priority to override Gravatar
        add_filter('get_avatar', array($this, 'display_custom_avatar'), 5, 6);
        
        // Add custom avatar display in review meta using existing hooks
        add_action('woocommerce_review_meta_after_author', array($this, 'display_avatar_in_review_meta'), 10, 1);
    }

    /**
     * Display custom uploaded avatar if available, otherwise use Gravatar logic
     * 
     * @param string $avatar Avatar HTML
     * @param mixed $id_or_email User ID, email or comment object
     * @param int $size Avatar size
     * @param string $default Default avatar URL
     * @param string $alt Alt text
     * @param array $args Avatar args
     * @return string Filtered avatar HTML
     */
    public function display_custom_avatar($avatar, $id_or_email, $size, $default, $alt, $args = array()) {
        // Only modify avatars on product pages
        if (!function_exists('is_product') || !is_product()) {
            return $avatar;
        }

        $comment = null;
        
        // Extract comment object from various input types
        if (is_object($id_or_email) && isset($id_or_email->comment_ID)) {
            $comment = $id_or_email;
        } elseif (is_numeric($id_or_email) && function_exists('get_comment')) {
            // Check if this is a comment ID by trying to fetch it
            $possible_comment = get_comment($id_or_email);
            if ($possible_comment && isset($possible_comment->comment_ID)) {
                $comment = $possible_comment;
            }
        }

        // If we have a comment, check for custom avatar
        if ($comment && isset($comment->comment_ID)) {
            // Check if custom avatar exists
            if (class_exists('WC_Review_Images_Avatar_Upload') && 
                WC_Review_Images_Avatar_Upload::has_custom_avatar($comment->comment_ID)) {
                
                return $this->get_custom_avatar_html($comment->comment_ID, $size, $alt);
            }
        }

        // If no custom avatar, check if we should hide gravatar when there's no custom gravatar
        if ($comment && apply_filters('wcri_enable_conditional_gravatars', true)) {
            $email = $comment->comment_author_email;
            
            if (!empty($email) && is_email($email)) {
                // Use the existing has_gravatar check from Conditional_Woo_Gravatars
                if (class_exists('Conditional_Woo_Gravatars')) {
                    // Check if user has actual Gravatar
                    if (!$this->has_gravatar($email)) {
                        return ''; // Hide if no Gravatar and no custom avatar
                    }
                }
            }
        }

        // Return original avatar (Gravatar or default)
        return $avatar;
    }

    /**
     * Check if a custom Gravatar exists for this email
     * This mirrors the functionality from Conditional_Woo_Gravatars
     * 
     * @param string $email The email to check
     * @return bool True if a custom Gravatar exists
     */
    private function has_gravatar($email) {
        if (empty($email)) {
            return false;
        }

        // Check cache first
        $cache_key = 'wcri_has_gravatar_' . md5($email);
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result === 'yes';
        }

        // Make a HEAD request to Gravatar
        $hash = md5(strtolower(trim($email)));
        $uri = 'https://www.gravatar.com/avatar/' . $hash . '?d=404';

        $response = wp_remote_head($uri);
        $has_valid_avatar = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;

        // Cache result for 24 hours
        set_transient($cache_key, $has_valid_avatar ? 'yes' : 'no', DAY_IN_SECONDS);

        return $has_valid_avatar;
    }

    /**
     * Generate HTML for custom avatar
     * 
     * @param int $comment_id Comment ID
     * @param int $size Avatar size
     * @param string $alt Alt text
     * @return string Avatar HTML
     */
    private function get_custom_avatar_html($comment_id, $size, $alt) {
        if (!class_exists('WC_Review_Images_Avatar_Upload')) {
            return '';
        }

        $avatar_id = WC_Review_Images_Avatar_Upload::get_comment_avatar_id($comment_id);
        if (!$avatar_id) {
            return '';
        }

        // Get avatar base size with filter
        $base_size = apply_filters('wcri_avatar_base_size', 120);
        $retina_size = $base_size * 2;

        // Get image URLs
        $avatar_url = wp_get_attachment_image_url($avatar_id, array($base_size, $base_size));
        $avatar_url_retina = wp_get_attachment_image_url($avatar_id, array($retina_size, $retina_size));

        if (!$avatar_url) {
            return '';
        }

        // Build HTML with retina support
        $html = sprintf(
            '<img alt="%s" src="%s" srcset="%s 2x" class="avatar avatar-custom wcri-custom-avatar" height="%d" width="%d" loading="lazy" decoding="async" />',
            esc_attr($alt),
            esc_url($avatar_url),
            esc_url($avatar_url_retina),
            absint($base_size),
            absint($base_size)
        );

        /**
         * Filter the custom avatar HTML
         * 
         * @since 1.2.0
         * @param string $html Avatar HTML
         * @param int $comment_id Comment ID
         * @param int $avatar_id Avatar attachment ID
         * @param int $size Avatar size
         */
        return apply_filters('wcri_custom_avatar_html', $html, $comment_id, $avatar_id, $size);
    }

    /**
     * Display avatar in review meta section
     * Uses the existing hook from custom-review-meta.php
     * 
     * @param WP_Comment $comment Comment object
     */
    public function display_avatar_in_review_meta($comment) {
        if (!isset($comment->comment_ID)) {
            return;
        }

        /**
         * Filter whether to display avatar in review meta
         * 
         * @since 1.2.0
         * @param bool $display Whether to display avatar. Default true.
         * @param WP_Comment $comment Comment object
         */
        if (!apply_filters('wcri_display_avatar_in_meta', true, $comment)) {
            return;
        }

        // Get avatar HTML (will use custom avatar if available, or Gravatar)
        $avatar_html = get_avatar($comment, 48);
        
        if (!empty($avatar_html)) {
            echo '<span class="wcri-review-avatar">' . $avatar_html . '</span>';
        }
    }

    /**
     * Get avatar URL for a comment (helper method)
     * 
     * @param int|WP_Comment $comment Comment ID or object
     * @param string|array $size Image size
     * @return string|false Avatar URL or false
     */
    public static function get_avatar_url($comment, $size = 'thumbnail') {
        $comment_id = is_object($comment) ? $comment->comment_ID : absint($comment);
        
        // Check for custom avatar first
        if (class_exists('WC_Review_Images_Avatar_Upload')) {
            $custom_url = WC_Review_Images_Avatar_Upload::get_comment_avatar_url($comment_id, $size);
            if ($custom_url) {
                return $custom_url;
            }
        }

        // Fallback to Gravatar
        $comment_obj = is_object($comment) ? $comment : get_comment($comment_id);
        if ($comment_obj && !empty($comment_obj->comment_author_email)) {
            $hash = md5(strtolower(trim($comment_obj->comment_author_email)));
            $size_param = is_numeric($size) ? $size : 96;
            return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size_param . '&d=mp&r=g';
        }

        return false;
    }
}

// Initialize the avatar display handler
if (apply_filters('wcri_enable_avatar_display', true)) {
    WC_Review_Images_Avatar_Display::get_instance();
}
