---
trigger: manual
---

# WooCommerce Plugin Coding Standards – windsurf.md

## PHP Structure

- `defined( 'ABSPATH' ) || exit;` at top of all PHP files.
- Use classes; no global functions unless prefixed.
- Singleton pattern or service container recommended for plugin bootstrap.
- Autoload via PSR-4 or manual `require` in a loader file.

## Naming

- Classes: `PascalCase`, prefixed (e.g., `WCRAS_Attribute_Handler`)
- Functions: `snake_case`, prefixed (e.g., `wcras_get_attribute_labels`)
- Constants: `UPPER_SNAKE_CASE`

## Security

### Input

- `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.
- Never trust `$_POST`, `$_GET`, or `$_REQUEST` directly.

### Output

- `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, etc.
- Always escape on output, not on input.

### Nonces

- Use `wp_nonce_field()` in forms.
- Verify with `check_admin_referer()` or `check_ajax_referer()`.

## WooCommerce Integration

- Use CRUD methods: `$product->get_name()`, `$order->get_total()`, etc.
- Use WC hooks: `woocommerce_before_main_content`, etc.
- Extend product/taxonomy settings via actions/filters.

## Enqueueing

```php
add_action( 'wp_enqueue_scripts', 'wcras_enqueue_assets' );
function wcras_enqueue_assets() {
  wp_enqueue_script( 'wcras-frontend', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', ['jquery'], '1.0', true );
  wp_enqueue_style( 'wcras-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0' );
}
```

* Use `wp_localize_script()` for AJAX URL and nonce.

## AJAX

* Use `wp_ajax_{action}` / `wp_ajax_nopriv_{action}`.
* Check nonce and capabilities before processing.
* Return `wp_send_json_success()` or `wp_send_json_error()`.

## Templates

* Place in `/templates`; allow override via `wc_get_template()`.
* Use `woocommerce_locate_template()` if custom loader needed.

## Internationalization

* Text domain must match plugin slug.
* Wrap all strings in `__()`, `_e()`, `_x()`, etc.
* Load with `load_plugin_textdomain()` on `plugins_loaded`.

## Folder Structure

```
/includes
/admin
/assets
  /css
  /js
/languages
/templates
```

## Docs

* All classes and public methods must include PHPDoc.
* Use `@param`, `@return`, `@since`, `@access`.

## Misc

* Avoid logic in global scope.
* Avoid deprecated WC/WordPress functions.
* Avoid direct SQL unless necessary (then use `$wpdb->prepare()`).
