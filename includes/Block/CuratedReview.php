<?php
/**
 * The Curated Review block.
 *
 * A presentation surface for one hand-picked product review. Ships zero CSS:
 * the toggles decide WHAT renders, the theme decides HOW it looks.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Block;

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
        // Filled in by #11.
        return '';
    }
}
