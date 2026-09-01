<?php
/**
 * Plugin Name: Kaupang Review Images
 * Plugin URI:  https://github.com/nytafar/kaupang-review-images
 * Description: Review image and avatar uploads for WooCommerce product reviews — customers attach a product photo and a profile picture to their review, stored as ordinary media attachments; conditional Gravatar loading keeps default avatars off the page. Part of the Kaupang suite.
 * Version:     1.2.1
 * Author:      Lasse Jellum
 * Author URI:  https://jellum.net
 * Text Domain: kaupang-review-images
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.7
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 11.0
 * License: GPL-2.0-or-later
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KAUPANG_REVIEW_IMAGES_VERSION', '1.2.1');
define('KAUPANG_REVIEW_IMAGES_FILE', __FILE__);
define('KAUPANG_REVIEW_IMAGES_DIR', plugin_dir_path(__FILE__));
define('KAUPANG_REVIEW_IMAGES_URL', plugin_dir_url(__FILE__));

// PSR-4 autoloader (Composer-less), suite convention.
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'Kaupang\\ReviewImages\\') !== 0) {
        return;
    }
    $relative = substr($class, strlen('Kaupang\\ReviewImages\\'));
    $path     = KAUPANG_REVIEW_IMAGES_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

// Declare HPOS (custom order tables) compatibility. This plugin only works with
// product reviews and avatars; it never reads or writes order data, so it is
// safe under both legacy CPT and HPOS storage.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

\Kaupang\ReviewImages\Plugin::boot();
