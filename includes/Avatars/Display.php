<?php
/**
 * Avatar display.
 *
 * Precedence: custom upload > real Gravatar > nothing.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Avatars;

use Kaupang\ReviewImages\Support\Comments;

defined('ABSPATH') || exit;

final class Display
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
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Priority 5 — must run before Gravatar's resize filter at 10.
        add_filter('get_avatar', [$this, 'displayCustomAvatar'], 5, 6);
    }

    /**
     * @param string                    $avatar
     * @param mixed                     $idOrEmail
     * @param int                       $size
     * @param string                    $default
     * @param string                    $alt
     * @param array<string, mixed>      $args
     * @return string
     */
    public function displayCustomAvatar($avatar, $idOrEmail, $size, $default, $alt, $args = [])
    {
        // ponytail: admin keeps WordPress's own avatars. getCustomAvatarHtml
        // pins width/height to avatar_base_size (120) for retina crispness on
        // the frontend, which would land 120px images in the 32px comment-list
        // column. Honour $size here instead if admin avatars are ever wanted.
        if (is_admin()) {
            return $avatar;
        }

        $comment = Comments::productReview($idOrEmail);
        if (!$comment) {
            return $avatar;
        }

        if (Upload::hasCustomAvatar((int) $comment->comment_ID)) {
            return self::getCustomAvatarHtml((int) $comment->comment_ID, $size, $alt);
        }

        // No custom avatar: hide the default silhouette when the author has no
        // real Gravatar either.
        if (apply_filters('kaupang/review-images/enable_conditional_gravatars', true)) {
            $email = $comment->comment_author_email;

            if (!empty($email) && is_email($email) && !Gravatar::hasGravatar($email)) {
                return '';
            }
        }

        return $avatar;
    }

    /**
     * @param int|string $size
     */
    public static function getCustomAvatarHtml(int $commentId, $size, string $alt): string
    {
        $avatarId = Upload::getCommentAvatarId($commentId);
        if (!$avatarId) {
            return '';
        }

        $baseSize   = (int) apply_filters('kaupang/review-images/avatar_base_size', 120);
        $retinaSize = $baseSize * 2;

        $url = wp_get_attachment_image_url($avatarId, [$baseSize, $baseSize]);
        if (!$url) {
            return '';
        }

        $retinaUrl = wp_get_attachment_image_url($avatarId, [$retinaSize, $retinaSize]);

        $html = sprintf(
            '<img alt="%s" src="%s" srcset="%s 2x" class="avatar avatar-custom wcri-custom-avatar" height="%d" width="%d" loading="lazy" decoding="async" />',
            esc_attr($alt),
            esc_url($url),
            esc_url((string) $retinaUrl),
            $baseSize,
            $baseSize
        );

        /**
         * Filter the custom avatar HTML.
         *
         * @param string $html      Avatar HTML.
         * @param int    $commentId Comment ID.
         * @param int    $avatarId  Avatar attachment ID.
         * @param int|string $size  Requested size.
         */
        return apply_filters('kaupang/review-images/custom_avatar_html', $html, $commentId, $avatarId, $size);
    }

    /**
     * Custom upload first, Gravatar as fallback.
     *
     * @param int|\WP_Comment        $comment
     * @param string|array<int, int> $size
     * @return string|false
     */
    public static function getAvatarUrl($comment, $size = 'thumbnail')
    {
        $commentId = is_object($comment) ? (int) $comment->comment_ID : absint($comment);

        $customUrl = Upload::getCommentAvatarUrl($commentId, $size);
        if ($customUrl) {
            return $customUrl;
        }

        $commentObj = is_object($comment) ? $comment : get_comment($commentId);
        if ($commentObj && !empty($commentObj->comment_author_email)) {
            $hash = md5(strtolower(trim($commentObj->comment_author_email)));

            return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . (is_numeric($size) ? (int) $size : 96) . '&d=mp&r=g';
        }

        return false;
    }
}
