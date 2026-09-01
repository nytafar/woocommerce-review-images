<?php
/**
 * Review-meta renderer.
 *
 * Replaces WooCommerce's woocommerce_review_display_meta with a copy that
 * fires five hook points inside the byline. The myrvann theme uses
 * woocommerce_review_meta_author to reposition the gravatar.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Meta;

defined('ABSPATH') || exit;

final class Renderer
{
    public static function boot(): void
    {
        add_action('init', [self::class, 'override'], 20);
    }

    public static function override(): void
    {
        if (has_action('woocommerce_review_meta', 'woocommerce_review_display_meta')) {
            remove_action('woocommerce_review_meta', 'woocommerce_review_display_meta', 10);
        }

        if (!has_action('woocommerce_review_meta', [self::class, 'render'])) {
            add_action('woocommerce_review_meta', [self::class, 'render'], 10);
        }
    }

    public static function render(\WP_Comment $comment): void
    {
        $author   = get_comment_author($comment);
        $date     = esc_html(get_comment_date(wc_date_format(), $comment));
        $datetime = esc_attr(get_comment_date('c', $comment));
        $verified = wc_review_is_from_verified_owner($comment->comment_ID);

        echo '<p class="meta">';

        do_action('woocommerce_review_meta_start', $comment);
        do_action('woocommerce_review_meta_author', $comment);

        echo '<strong class="woocommerce-review__author">' . esc_html($author) . '</strong>';

        do_action('woocommerce_review_meta_after_author', $comment);

        if ($verified) {
            echo ' <em class="woocommerce-review__verified verified">' . esc_html__('(verified owner)', 'woocommerce') . '</em>';
        }

        do_action('woocommerce_review_meta_after_verified', $comment);

        echo ' <span class="woocommerce-review__dash">&ndash;</span> ';
        echo '<time class="woocommerce-review__published-date" datetime="' . $datetime . '">' . $date . '</time>';

        do_action('woocommerce_review_meta_end', $comment);

        echo '</p>';
    }
}
