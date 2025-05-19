# WooCommerce Review Images

[![WooCommerce Review Images](https://img.shields.io/badge/WooCommerce-Review%20Images-7f54b3.svg)](https://wordpress.org/plugins/woocommerce-review-images/)
[![Version 1.0.2](https://img.shields.io/badge/Version-1.0.2-brightgreen.svg)](https://github.com/nytafar/woocommerce-review-images/releases)
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

You can configure the following settings in your WordPress admin under **WooCommerce > Settings > Products > Review Images**:

- Maximum upload size (default: 2MB)
- Allowed file types
- Image dimensions
- Required/Optional image upload

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

## Filters and Actions

### Filters

- `wcri_gravatar_base_size` - Modify the Gravatar size (default: 200)
- `wcri_enable_conditional_gravatars` - Toggle Gravatar functionality (default: true)
- `wcri_max_upload_size` - Change maximum upload size in bytes (default: 2097152)
- `wcri_allowed_mime_types` - Modify allowed file types

### Actions

- `wcri_before_review_image_upload` - Fires before image upload
- `wcri_after_review_image_upload` - Fires after successful image upload
- `wcri_before_review_image_display` - Fires before displaying review image

## Changelog

### 1.0.2 - 2025-05-19
- Added configurable Gravatar size via filter
- Added option to disable Gravatar functionality
- Improved image upload security
- Updated WordPress and WooCommerce version requirements
- Added proper documentation and filters

### 1.0.1 - 2025-05-19
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
