# WooCommerce Review Images

[![WooCommerce Review Images](https://img.shields.io/badge/WooCommerce-Review%20Images-7f54b3.svg)](https://wordpress.org/plugins/woocommerce-review-images/)
[![Version 1.2.0](https://img.shields.io/badge/Version-1.2.0-brightgreen.svg)](https://github.com/nytafar/woocommerce-review-images/releases)
[![WooCommerce 5.0+](https://img.shields.io/badge/WooCommerce-5.0+-a46497.svg)](https://woocommerce.com/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-8892BF.svg)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

Enhance your WooCommerce product reviews by allowing customers to upload images with their reviews. This plugin provides a seamless way to collect and display user-generated content, helping build trust and engagement on your e-commerce site.

![WooCommerce Review Images](assets/screenshot-1.png)

## Features

- **Custom Avatar Uploads**: Allow customers to upload their profile photo/avatar with reviews (takes precedence over Gravatar)
- **Review Image Uploads**: Allow customers to upload images with product reviews
- **Admin Interface**: Manage review images and avatars from WordPress admin
- **Optimized Gravatar Display**: Conditional Gravatar loading for review authors
- **Mobile-Friendly**: Responsive image upload interface
- **Configurable Settings**: Image size and quality settings
- **Multiple Formats**: Support for JPEG, PNG, GIF, and WebP
- **Secure File Handling**: Secure file upload with validation
- **GDPR Compliant**: Stores user consent for uploads

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
2. **Upload Profile Photo** (optional): Choose a file to upload your avatar/profile photo
3. **Upload Review Image** (optional): Choose a file to upload an image related to the product
4. Submit your review
5. Your custom avatar and review image will appear with your review

### For Administrators

1. Go to **Comments** in WordPress admin
2. Locate the review with an image or avatar
3. Both the avatar and review image will be visible in the comment list and edit screen
4. You can manage or delete images as needed from the media library

## Available Filters

### Avatar Upload Filters

#### `wcri_enable_avatar_upload`
Toggle the avatar upload functionality.

```php
// Disable avatar upload functionality
add_filter('wcri_enable_avatar_upload', '__return_false');

// Or conditionally enable
add_filter('wcri_enable_avatar_upload', function($enabled) {
    return is_user_logged_in(); // Only allow logged-in users to upload avatars
});
```

#### `wcri_avatar_upload_field_label_text`
Customize the avatar upload field label text.

```php
add_filter('wcri_avatar_upload_field_label_text', function($default_text, $product) {
    return __('Upload your photo (optional)', 'your-text-domain');
}, 10, 2);
```

#### `wcri_avatar_base_size`
Set the base size for custom avatars in pixels.

```php
// Set base avatar size to 96px (will serve 96px and 192px for retina)
add_filter('wcri_avatar_base_size', function() {
    return 96; // Default is 120px
});
```

#### `wcri_custom_avatar_html`
Filter the custom avatar HTML output.

```php
add_filter('wcri_custom_avatar_html', function($html, $comment_id, $avatar_id, $size) {
    // Modify avatar HTML as needed
    return $html;
}, 10, 4);
```

#### `wcri_display_avatar_in_meta`
Control whether to display avatar in review meta section.

```php
// Hide avatar in review meta
add_filter('wcri_display_avatar_in_meta', '__return_false');
```

### Review Image Filters

#### 1. `wcri_enable_review_images`
Toggle the entire review images functionality.

```php
// Disable review images functionality
add_filter('wcri_enable_review_images', '__return_false');

// Or conditionally enable
add_filter('wcri_enable_review_images', function($enabled) {
    return is_product() && !is_user_logged_in(); // Only for guests on product pages
});
```

#### 2. `wcri_upload_field_label_text`
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

#### 3. `wcri_enable_conditional_gravatars`
Optimize Gravatar loading by only loading Gravatars for users who have custom avatars.

```php
// Disable conditional Gravatar loading (show all Gravatars, even default ones)
add_filter('wcri_enable_conditional_gravatars', '__return_false');

// Or conditionally enable based on user role
add_filter('wcri_enable_conditional_gravatars', function() {
    return current_user_can('edit_products'); // Only optimize for non-editors
});
```

#### 4. `wcri_gravatar_base_size`
Set the base width for Gravatars in pixels. The plugin will automatically generate both standard and retina (2x) versions.

```php
// Set base Gravatar size to 96px (will serve 96px and 192px for retina)
add_filter('wcri_gravatar_base_size', function() {
    return 96; // Default is 200px (serves 200px and 400px)
});
```

#### 5. Review Meta Customization Hooks
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

### 1.2.0 - 2025-10-27
- **NEW**: Added custom avatar upload functionality for review authors
- **NEW**: Custom avatars take precedence over Gravatar images
- **NEW**: Extended conditional display logic to custom avatars
- **NEW**: Separate modular classes for avatar upload and display
- **IMPROVED**: Better code organization with dedicated includes directory
- **IMPROVED**: Enhanced extensibility with new filters and hooks
- Added `wcri_enable_avatar_upload` filter
- Added `wcri_avatar_upload_field_label_text` filter
- Added `wcri_avatar_base_size` filter
- Added `wcri_custom_avatar_html` filter
- Added `wcri_display_avatar_in_meta` filter

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
