<?php
/**
 * Conditional Gravatar loading.
 *
 * Hides default Gravatars for review authors who have none, and resizes real
 * ones to the theme's target size with a retina srcset.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Avatars;

use Kaupang\ReviewImages\Support\Comments;

defined('ABSPATH') || exit;

final class Gravatar
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Priority 10 — must run after Display's custom-avatar filter at 5.
        add_filter('get_avatar', [$this, 'filterWoocommerceGravatar'], 10, 6);
    }

    /**
     * Does a real (non-default) Gravatar exist for this address?
     *
     * Result cached 24h. Shared by the conditional-Gravatar filter and the
     * custom-avatar display so the HEAD-request logic lives in one place.
     */
    public static function hasGravatar(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $cacheKey = 'kaupang_review_images_has_gravatar_' . md5($email);
        $cached   = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached === 'yes';
        }

        $hash     = md5(strtolower(trim($email)));
        // ponytail: 2s ceiling. This is a synchronous remote call on the first
        // render for each reviewer address, and the block can put several on
        // one page. Move it off the request entirely if that ever bites.
        $response = wp_remote_head(
            'https://www.gravatar.com/avatar/' . $hash . '?d=404',
            ['timeout' => 2]
        );
        $hasReal  = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;

        set_transient($cacheKey, $hasReal ? 'yes' : 'no', DAY_IN_SECONDS);

        return $hasReal;
    }

    /**
     * @param string               $avatar
     * @param mixed                $idOrEmail
     * @param int                  $size
     * @param string               $default
     * @param string               $alt
     * @param array<string, mixed> $args
     * @return string
     */
    public function filterWoocommerceGravatar($avatar, $idOrEmail, $size, $default, $alt, $args = [])
    {
        // See Display::displayCustomAvatar() for why admin is left alone.
        if (is_admin()) {
            return $avatar;
        }

        $comment = Comments::productReview($idOrEmail);
        if (!$comment) {
            return $avatar;
        }

        // A custom uploaded avatar already won at priority 5 -- leave it alone.
        if (is_string($avatar) && strpos($avatar, 'wcri-custom-avatar') !== false) {
            return $avatar;
        }

        $email = $comment->comment_author_email;
        if (empty($email) || !is_email($email)) {
            return $avatar;
        }

        if (!apply_filters('kaupang/review-images/enable_conditional_gravatars', true)) {
            return $avatar;
        }

        // No real Gravatar: preserve whatever came in (may be empty, may be a
        // custom upload supplied by another filter).
        if (!self::hasGravatar($email)) {
            return $avatar;
        }

        $baseSize   = (int) apply_filters('kaupang/review-images/gravatar_base_size', 120);
        $retinaSize = $baseSize * 2;

        if (!preg_match('/src=[\'"]([^\'"]+)[\'"]/', $avatar, $srcMatch)) {
            return $avatar;
        }

        $srcUrl = preg_replace('/s=\d+/', 's=' . $baseSize, $srcMatch[1]);
        $avatar = preg_replace('/src=[\'"][^\'"]+[\'"]/', 'src="' . esc_url((string) $srcUrl) . '"', $avatar);

        $srcsetUrl = preg_replace('/s=\d+/', 's=' . $retinaSize, $srcMatch[1]);
        $avatar    = preg_replace('/srcset=[\'"][^\'"]*[\'"]/', 'srcset="' . esc_url((string) $srcsetUrl) . ' 2x"', (string) $avatar);

        $avatar = preg_replace('/(width|height)=[\'"](\d+)[\'"]/', '$1="' . $baseSize . '"', (string) $avatar);

        return str_replace('class="', 'class="wcri-gravatar ', (string) $avatar);
    }
}
