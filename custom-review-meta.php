<?php
/**
 * Plugin Module: Custom WooCommerce Review Meta Renderer with Hook Points
 * Description: Replaces default review meta output and provides hooks for extensibility.
 */


// Make sure we only unhook and rehook once
add_action('init', 'custom_override_review_meta_output', 20);
function custom_override_review_meta_output() {
    // Ensure WooCommerce's function exists before trying to remove
    if (has_action('woocommerce_review_meta', 'woocommerce_review_display_meta')) {
        remove_action('woocommerce_review_meta', 'woocommerce_review_display_meta', 10);
    }

    // Avoid duplicate registration
    if (!has_action('woocommerce_review_meta', 'custom_woocommerce_review_display_meta')) {
        add_action('woocommerce_review_meta', 'custom_woocommerce_review_display_meta', 10);
    }
}

/**
 * Custom review meta output with hookable structure.
 *
 * @param WP_Comment $comment Comment object
 */
function custom_woocommerce_review_display_meta($comment) {
    $author    = get_comment_author($comment);
    $date      = esc_html(get_comment_date(wc_date_format(), $comment));
    $datetime  = esc_attr(get_comment_date('c', $comment));
    $verified  = wc_review_is_from_verified_owner($comment->comment_ID);

    echo '<p class="meta">';

    /**
     * Hook: woocommerce_review_meta_start
     * Before any meta output begins (opening <p>)
     */
    do_action('woocommerce_review_meta_start', $comment);

    /**
     * Hook: woocommerce_review_meta_author
     * Before or around the author name
     */
    do_action('woocommerce_review_meta_author', $comment);

    echo '<strong class="woocommerce-review__author">' . esc_html($author) . '</strong>';

    /**
     * Hook: woocommerce_review_meta_after_author
     * Right after author name (before verified)
     */
    do_action('woocommerce_review_meta_after_author', $comment);

    if ($verified) {
        echo ' <em class="woocommerce-review__verified verified">' . esc_html__('(verified owner)', 'woocommerce') . '</em>';
    }

    /**
     * Hook: woocommerce_review_meta_after_verified
     * After verified badge, before date
     */
    do_action('woocommerce_review_meta_after_verified', $comment);

    echo ' <span class="woocommerce-review__dash">–</span> ';
    echo '<time class="woocommerce-review__published-date" datetime="' . $datetime . '">' . $date . '</time>';

    /**
     * Hook: woocommerce_review_meta_end
     * After all meta output (before closing </p>)
     */
    do_action('woocommerce_review_meta_end', $comment);

    echo '</p>';
}
