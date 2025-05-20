<?php


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Conditional_Woo_Gravatars {

    /**
     * Constructor - setup hooks
     */
    public function __construct() {
        // Filter the get_avatar output
        add_filter('get_avatar', array($this, 'filter_woocommerce_gravatar'), 10, 6);
    }

    /**
     * Check if a custom Gravatar exists for this email
     * 
     * @param string $email The email to check
     * @return bool True if a custom Gravatar exists
     */
    private function has_gravatar($email) {
        if (empty($email)) {
            return false;
        }

        // Check cache first
        $cache_key = 'cwg_has_gravatar_' . md5($email);
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result === 'yes';
        }

        // Make a HEAD request to Gravatar
        $hash = md5(strtolower(trim($email)));
        $uri = 'https://www.gravatar.com/avatar/' . $hash . '?d=404';

        $response = wp_remote_head($uri);
        $has_valid_avatar = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 120;

        // Cache result for 24 hours
        set_transient($cache_key, $has_valid_avatar ? 'yes' : 'no', DAY_IN_SECONDS);

        return $has_valid_avatar;
    }

    /**
     * Filter the get_avatar function for WooCommerce reviews
     * Completely removes the <img> tag if user has no custom Gravatar
     * Modifies Gravatar size to 200px
     * 
     * @param string $avatar Avatar HTML
     * @param mixed $id_or_email User ID, email or comment object
     * @param int $size Avatar size
     * @param string $default Default avatar URL
     * @param string $alt Alt text
     * @param array $args Avatar args
     * @return string Filtered avatar HTML
     */
    public function filter_woocommerce_gravatar($avatar, $id_or_email, $size, $default, $alt, $args = array()) {
        if (!function_exists('is_product') || !is_product()) {
            return $avatar;
        }
    
        $email = '';
        if (is_object($id_or_email) && isset($id_or_email->comment_ID)) {
            $comment = $id_or_email;
            $email = $comment->comment_author_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $email = $id_or_email;
        } elseif (is_numeric($id_or_email)) {
            $user = get_userdata($id_or_email);
            if ($user) {
                $email = $user->user_email;
            }
        }
    
        if (empty($email) || !$this->has_gravatar($email)) {
            return '';
        }
    
        // Target sizes with filter
        $base_size = apply_filters('wcri_gravatar_base_size', 120);
        $retina_size = $base_size * 2;
    
        // Extract src
        if (preg_match('/src=["\']([^"\']+)["\']/', $avatar, $src_match)) {
            $src_url = $src_match[1];
            $src_url = preg_replace('/s=\d+/', 's=' . $base_size, $src_url);
            $avatar = preg_replace('/src=["\'][^"\']+["\']/', 'src="' . esc_url($src_url) . '"', $avatar);
        }
    
        // Generate srcset with retina
        $srcset_url = preg_replace('/s=\d+/', 's=' . $retina_size, $src_url);
        $avatar = preg_replace(
            '/srcset=["\'][^"\']*["\']/',
            'srcset="' . esc_url($srcset_url) . ' 2x"',
            $avatar
        );
    
        // Replace width and height
        $avatar = preg_replace('/(width|height)=["\']\d+["\']/', '$1="' . $base_size . '"', $avatar);
    
        // Optional: update class name
        $avatar = preg_replace('/avatar-\d+/', 'avatar-' . $base_size, $avatar);
    
        return $avatar;
    }
    

}

/**
 * Filters whether to enable the conditional Gravatar functionality.
 *
 * @since 1.0.2
 * @param bool $enabled Whether the Gravatar functionality is enabled. Default true.
 */
if (apply_filters('wcri_enable_conditional_gravatars', true)) {
    new Conditional_Woo_Gravatars();
}
