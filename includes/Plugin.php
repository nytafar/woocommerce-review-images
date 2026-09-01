<?php
/**
 * Boot wiring.
 *
 * One file registers every module, suite convention. Each module keeps the
 * enable-filter gate it had before the Kaupang conversion; the gates run at
 * plugin-load time, so only an mu-plugin or an earlier-loading plugin can flip
 * them — a theme cannot. That timing is inherited, not new.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages;

defined('ABSPATH') || exit;

final class Plugin
{
    public static function boot(): void
    {
        add_action('plugins_loaded', [self::class, 'loadTextdomain']);

        if (apply_filters('kaupang/review-images/enable_review_images', true)) {
            Reviews\Images::instance();
        }

        if (apply_filters('kaupang/review-images/enable_avatar_upload', true)) {
            Avatars\Upload::instance();
        }

        if (apply_filters('kaupang/review-images/enable_avatar_display', true)) {
            Avatars\Display::instance();
        }

        if (apply_filters('kaupang/review-images/enable_conditional_gravatars', true)) {
            Avatars\Gravatar::instance();
        }

        Meta\Renderer::boot();
    }

    public static function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'kaupang-review-images',
            false,
            dirname(plugin_basename(KAUPANG_REVIEW_IMAGES_FILE)) . '/languages'
        );

        // Fallback: explicitly load from bundled languages if not loaded yet.
        if (is_textdomain_loaded('kaupang-review-images')) {
            return;
        }

        $dir    = KAUPANG_REVIEW_IMAGES_DIR . 'languages/';
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        $candidates = ['kaupang-review-images-' . $locale . '.mo'];

        // Also try common Norwegian variants.
        foreach (['nb_NO', 'nb', 'no_NO', 'no'] as $variant) {
            if ($variant !== $locale) {
                $candidates[] = 'kaupang-review-images-' . $variant . '.mo';
            }
        }

        foreach ($candidates as $file) {
            if (file_exists($dir . $file)) {
                load_textdomain('kaupang-review-images', $dir . $file);
                if (is_textdomain_loaded('kaupang-review-images')) {
                    break;
                }
            }
        }
    }
}
