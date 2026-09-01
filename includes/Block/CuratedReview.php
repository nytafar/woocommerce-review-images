<?php
/**
 * The Curated Review block.
 *
 * A presentation surface for one hand-picked product review. Ships zero CSS:
 * the toggles decide WHAT renders, the theme decides HOW it looks. This render
 * callback is the single source of truth for the markup -- the editor previews
 * it through ServerSideRender, so there is no JS-side copy to drift.
 *
 * No schema.org, no JSON-LD, deliberately. The reasoning and its primary
 * sources are in issue #2 section J; do not add them back without reading it.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Block;

use Kaupang\ReviewImages\Reviews\Images;
use Kaupang\ReviewImages\Support\Comments;

defined('ABSPATH') || exit;

final class CuratedReview
{
    public const BLOCK_NAME = 'kaupang-review-images/curated-review';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
    }

    public static function registerBlock(): void
    {
        /**
         * Kill switch for the whole block.
         *
         * __return_false removes it from the inserter entirely.
         *
         * @param bool $enabled
         */
        if (!apply_filters('kaupang/review-images/enable_block', true)) {
            return;
        }

        $type = register_block_type(
            KAUPANG_REVIEW_IMAGES_DIR . 'block',
            ['render_callback' => [self::class, 'render']]
        );

        /**
         * Drop the block's own stylesheet.
         *
         * The two style variations ship structure only -- reading order, a
         * round avatar, a photo that stays inside its column -- and no chrome.
         * A theme that would rather own even that returns false here and styles
         * .is-style-quote / .is-style-compact itself. The variations stay
         * registered either way, so the editor still offers them.
         *
         * @param bool $enqueue
         */
        if ($type && !apply_filters('kaupang/review-images/enqueue_block_styles', true)) {
            $type->style_handles = [];
        }

        // Editor strings ship in the plugin's own .mo files.
        if ($type && !empty($type->editor_script_handles)) {
            foreach ($type->editor_script_handles as $handle) {
                wp_set_script_translations(
                    $handle,
                    'kaupang-review-images',
                    KAUPANG_REVIEW_IMAGES_DIR . 'languages'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function render($attributes = []): string
    {
        $a = array_merge(self::defaults(), (array) $attributes);

        $comment = self::resolve((int) $a['reviewId']);
        if (!$comment) {
            return self::emptyState((int) $a['reviewId']);
        }

        $commentId = (int) $comment->comment_ID;
        $productId = (int) $comment->comment_post_ID;
        $product   = wc_get_product($productId);
        $permalink = $product ? (string) get_permalink($productId) : '';
        $rating    = (int) get_comment_meta($commentId, 'rating', true);
        $verified  = (bool) wc_review_is_from_verified_owner($commentId);
        $imageIds  = Images::getImageIds($commentId);

        // Document order is fixed: body, images, rating, attribution, product.
        // Toggles decide presence, never position -- CSS order/grid makes visual
        // position free, and this is the reading order for screen readers and
        // text extraction.
        $out = '';

        $expandable = $a['showBody'] && $a['expandable'];

        if ($a['showBody']) {
            $cite = $permalink ? ' cite="' . esc_url($permalink) . '"' : '';
            // Unique per instance, not per review: the same review can legitimately
            // appear more than once on a page, and duplicate ids would point every
            // button at the first one.
            $bodyId = $expandable ? wp_unique_id('kaupang-review-body-') : '';
            $id     = $bodyId ? ' id="' . esc_attr($bodyId) . '"' : '';

            $out .= '<blockquote class="kaupang-review__body"' . $id . $cite . '>'
                . wp_kses_post(apply_filters('comment_text', get_comment_text($comment), $comment))
                . '</blockquote>';

            if ($expandable) {
                $more = __('Read more', 'kaupang-review-images');
                $less = __('Show less', 'kaupang-review-images');

                // Rendered hidden, and block/view.js unhides it only once it has
                // confirmed the text is actually clamped. Without JS the reader
                // gets the full review and no button, rather than a truncated
                // review and no way to reach the rest.
                $out .= sprintf(
                    '<button type="button" class="kaupang-review__more" aria-expanded="false" aria-controls="%s" data-label-more="%s" data-label-less="%s" hidden>%s</button>',
                    esc_attr($bodyId),
                    esc_attr($more),
                    esc_attr($less),
                    esc_html($more)
                );
            }
        }

        if ($a['showReviewImages'] && $imageIds) {
            $images = '';
            foreach ($imageIds as $imageId) {
                $images .= wp_get_attachment_image($imageId, 'large', false, [
                    'class'    => 'kaupang-review__image',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ]);
            }

            if ($images !== '') {
                $out .= '<figure class="kaupang-review__images">' . $images . '</figure>';
            }
        }

        if ($a['showRating'] && $rating > 0) {
            /* translators: %s: rating out of 5. WooCommerce's own string, reused for its translations. */
            $label = sprintf(__('Rated %s out of 5', 'woocommerce'), $rating);

            $out .= '<p class="kaupang-review__rating">'
                . '<span class="star-rating" role="img" aria-label="' . esc_attr($label) . '">'
                . wc_get_star_rating_html($rating)
                . '</span></p>';
        }

        $footer = '';

        if ($a['showAvatar']) {
            $footer .= self::avatar($comment);
        }

        if ($a['showAuthor']) {
            $footer .= '<cite class="kaupang-review__author-name">'
                . esc_html(get_comment_author($comment)) . '</cite>';
        }

        if ($a['showVerified'] && $verified) {
            $footer .= '<em class="kaupang-review__verified">'
                . esc_html__('(verified owner)', 'woocommerce') . '</em>';
        }

        if ($a['showDate']) {
            $footer .= '<time class="kaupang-review__date" datetime="'
                . esc_attr(get_comment_date('c', $comment)) . '">'
                . esc_html(get_comment_date(wc_date_format(), $comment)) . '</time>';
        }

        if ($footer !== '') {
            $out .= '<footer class="kaupang-review__author">' . $footer . '</footer>';
        }

        if ($product && ($a['showProductName'] || $a['showProductImage'])) {
            $inner = '';

            if ($a['showProductImage']) {
                $inner .= (string) get_the_post_thumbnail($productId, 'woocommerce_thumbnail', [
                    'class'    => 'kaupang-review__product-image',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                ]);
            }

            if ($a['showProductName']) {
                $inner .= '<span class="kaupang-review__product-name">'
                    . esc_html($product->get_name()) . '</span>';
            }

            if ($inner !== '') {
                $out .= '<div class="kaupang-review__product">'
                    . '<a class="kaupang-review__product-link" href="' . esc_url($permalink) . '">'
                    . $inner . '</a></div>';
            }
        }

        // State attributes render regardless of their show* toggle: a hidden
        // rating still exposes data-rating, so the theme can hang the cascade
        // on state it cannot see.
        $wrapperAttributes = [
            'class'           => 'kaupang-review',
            'data-review-id'  => (string) $commentId,
            'data-rating'     => (string) $rating,
            'data-verified'   => $verified ? '1' : '0',
            'data-has-images' => $imageIds ? '1' : '0',
        ];

        // Presence, not a value: the CSS keys the clamp on [data-expandable],
        // and block/view.js only looks at roots that carry it.
        if ($expandable) {
            $wrapperAttributes['data-expandable'] = '';
        }

        $wrapper = get_block_wrapper_attributes($wrapperAttributes);

        return '<article ' . $wrapper . '>' . $out . '</article>';
    }

    /**
     * The avatar chain, not a copy of it.
     *
     * get_avatar() runs Avatars\Display at priority 5 (custom upload, via the
     * now-public getCustomAvatarHtml) and Avatars\Gravatar at 10 (real Gravatar,
     * resized). Either can return '' -- that is the point: a reviewer with
     * neither gets no <img> rather than the default silhouette. Calling
     * getCustomAvatarHtml() directly, as #11 suggested, would have skipped the
     * Gravatar half of the chain.
     */
    private static function avatar(\WP_Comment $comment): string
    {
        $size = (int) apply_filters('kaupang/review-images/avatar_base_size', 120);

        /* translators: %s: review author name. */
        $alt = sprintf(__('Avatar for %s', 'kaupang-review-images'), get_comment_author($comment));

        $avatar = get_avatar($comment, $size, '', $alt);
        if (!is_string($avatar) || $avatar === '') {
            return '';
        }

        // ponytail: the chain hands back finished <img> HTML in either quote
        // style, so the block's own class goes on with one targeted insert
        // rather than a second markup builder. Give getCustomAvatarHtml() a
        // class argument if a third caller ever needs one.
        return (string) preg_replace('/\bclass=([\'"])/', 'class=$1kaupang-review__avatar ', $avatar, 1);
    }

    /**
     * Missing means: id 0, deleted, unapproved, spam, trashed, or not a
     * product review.
     */
    private static function resolve(int $reviewId): ?\WP_Comment
    {
        if ($reviewId <= 0) {
            return null;
        }

        $comment = get_comment($reviewId);
        if (!$comment) {
            return null;
        }

        // Covers unapproved, spam and trash in one call.
        if (wp_get_comment_status($comment) !== 'approved') {
            return null;
        }

        return Comments::asProductReview($comment);
    }

    /**
     * Frontend gets nothing at all -- an empty wrapper is itself a style
     * decision, and this block ships none. The editor gets told why, detected
     * via REST_REQUEST because ServerSideRender comes through
     * /wp/v2/block-renderer/.
     */
    private static function emptyState(int $reviewId): string
    {
        // REST_REQUEST alone is not "the editor". A public
        // GET /wp-json/wp/v2/pages/{id} renders this block into
        // content.rendered too, so the notice would leak to every headless
        // consumer. Only someone who could act on it should ever see it.
        if (!defined('REST_REQUEST') || !REST_REQUEST || !current_user_can('edit_posts')) {
            return '';
        }

        $message = $reviewId <= 0
            ? __('Pick a product and a review in the block settings.', 'kaupang-review-images')
            : __('That review is not available any more — it may have been deleted, unapproved, or marked as spam.', 'kaupang-review-images');

        return '<p class="kaupang-review__notice">' . esc_html($message) . '</p>';
    }

    /**
     * Defaults come from block.json, so there is one place to change them.
     *
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        $type = \WP_Block_Type_Registry::get_instance()->get_registered(self::BLOCK_NAME);

        if ($type && is_array($type->attributes)) {
            $attributes = $type->attributes;
        } else {
            // Called before init, or with the block gated off. Read the same
            // block.json the registry would have: returning [] here silently
            // treated every show* as false and rendered a bare <article>.
            $metadata   = wp_json_file_decode(KAUPANG_REVIEW_IMAGES_DIR . 'block/block.json', ['associative' => true]);
            $attributes = is_array($metadata) && isset($metadata['attributes']) && is_array($metadata['attributes'])
                ? $metadata['attributes']
                : [];
        }

        $defaults = [];
        foreach ($attributes as $name => $schema) {
            if (is_array($schema) && array_key_exists('default', $schema)) {
                $defaults[$name] = $schema['default'];
            }
        }

        return $defaults;
    }
}
