# WooCommerce Review Images

[![WooCommerce Review Images](https://img.shields.io/badge/WooCommerce-Review%20Images-7f54b3.svg)](https://wordpress.org/plugins/woocommerce-review-images/)
[![Version 1.1.1](https://img.shields.io/badge/Version-1.1.1-brightgreen.svg)](https://github.com/nytafar/woocommerce-review-images/releases)
[![WooCommerce 5.0+](https://img.shields.io/badge/WooCommerce-5.0+-a46497.svg)](https://woocommerce.com/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-8892BF.svg)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

Enhance your WooCommerce product reviews by allowing customers to upload images with their reviews. This plugin provides a seamless way to collect and display user-generated content, helping build trust and engagement on your e-commerce site.

![WooCommerce Review Images](assets/screenshot-1.png)

## Features

- Allow customers to upload images with product reviews
- Admin interface to manage review images
- Optimized Gravatar display for review authors
- Mobile-friendly image upload interface
- Configurable image size and quality settings
- Support for multiple image formats (JPEG, PNG, GIF)
- Secure file upload handling
- GDPR compliant (stores user consent for uploads)

## Requirements

- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `woocommerce-review-images` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The image upload field will automatically appear in the product review form

## Configuration

### Image Upload Settings

The plugin uses the following default settings:

- Maximum upload size: 2MB
- Allowed file types: JPEG, PNG, GIF
- Image upload is optional

### Gravatar Settings

Control how Gravatars are displayed in reviews:

```php
// Change Gravatar size (default: 200px)
add_filter('wcri_gravatar_base_size', function() {
    return 250; // Set your preferred size in pixels
});

// Disable Gravatar functionality
add_filter('wcri_enable_conditional_gravatars', '__return_false');
```

## Usage

### For Customers

1. Write a product review as usual
2. Click "Choose File" to upload an image
3. Submit your review
4. The image will appear alongside your review

### For Administrators

1. Go to **Comments** in WordPress admin
2. Locate the review with an image
3. The image will be visible in the comment list and edit screen
4. You can delete the image if needed

## Available Filters

### 1. `wcri_enable_review_images`
Toggle the entire review images functionality.

```php
// Disable review images functionality
add_filter('wcri_enable_review_images', '__return_false');

// Or conditionally enable
add_filter('wcri_enable_review_images', function($enabled) {
    return is_product() && !is_user_logged_in(); // Only for guests on product pages
});
```

### 2. `wcri_upload_field_label_text`
Customize the upload field label text.

```php
add_filter('wcri_upload_field_label_text', function($default_text, $product) {
    // $product is the current product post object
    return sprintf(
        __('Upload an image of your %s (max 5MB, JPG, PNG, GIF)', 'woocommerce-review-images'),
        $product->get_name()
    );
}, 10, 2);
```

### 3. `wcri_enable_conditional_gravatars`
Optimize Gravatar loading by only loading Gravatars for users who have custom avatars.

```php
// Disable conditional Gravatar loading (show all Gravatars, even default ones)
add_filter('wcri_enable_conditional_gravatars', '__return_false');

// Or conditionally enable based on user role
add_filter('wcri_enable_conditional_gravatars', function() {
    return current_user_can('edit_products'); // Only optimize for non-editors
});
```

### 4. `wcri_gravatar_base_size`
Set the base width for Gravatars in pixels. The plugin will automatically generate both standard and retina (2x) versions.

```php
// Set base Gravatar size to 96px (will serve 96px and 192px for retina)
add_filter('wcri_gravatar_base_size', function() {
    return 96; // Default is 200px (serves 200px and 400px)
});
```

### 5. Review Meta Customization Hooks
The following hooks allow for granular customization of the review meta output:

| Hook Name | Description |
|-----------|-------------|
| `woocommerce_review_meta_start` | Fires at the very beginning of the meta block. Useful for wrapping markup or adding icons. |
| `woocommerce_review_meta_author` | Fires before or around the author name. Useful if you want to prepend or wrap the name. |
| `woocommerce_review_meta_after_author` | Fires immediately after the author name, before the verified badge. Great for adding avatars or separators. |
| `woocommerce_review_meta_after_verified` | Fires after the verified badge and before the date. |
| `woocommerce_review_meta_end` | Fires at the very end of the meta block, just before closing `</p>`. Useful for appending icons or badges. |

Example usage:
```php
// Add a custom icon before the author name
add_action('woocommerce_review_meta_author', function() {
    echo '<span class="review-author-icon">👤</span> ';
});
```

## Changelog

### 1.1.1 - 2025-05-22
- Fixed critical bug in conditional Gravatar display
- Improved Gravatar detection and caching mechanism
- Fixed HTTP status code validation for Gravatar existence check
- Enhanced error handling for Gravatar display

### 1.1.0 - 2025-05-20
- Added granular hooks for customizing review meta output
- New hooks: `woocommerce_review_meta_start`, `woocommerce_review_meta_author`, 
  `woocommerce_review_meta_after_author`, `woocommerce_review_meta_after_verified`,
  and `woocommerce_review_meta_end`
- Improved customization options for review display

### 1.0.3 - 2025-05-20
- Added ability to completely disable review images functionality via filter
- Improved documentation for all available filters
- Reorganized filter documentation for better readability

### 1.0.2 - 2025-05-19
- Added configurable Gravatar size via filter
- Added option to completely disable Gravatar functionality
- Improved Gravatar handling and documentation
- Updated WordPress and WooCommerce version requirements
- Added proper documentation and filters

### 1.0.1 - 2025-05-19
- Added conditional Gravatar loading (only shows for users with custom Gravatars)
- Fixed image display in admin comments list
- Added basic security checks
- Improved code documentation

### 1.0.0 - 2025-05-18
- Initial release
- Basic image upload functionality
- Admin interface for managing review images

## Upgrade Notice

### 1.0.2
This update includes important security improvements and new features. Please test in a staging environment before updating production.

## Support

For support, please [open an issue](https://github.com/nytafar/woocommerce-review-images/issues) on GitHub.

## License

GPL-2.0+

## Credits

Created by [Lasse Jellum](https://jellum.net)
