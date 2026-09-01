# Kaupang Review Images

[![Version 2.0.0](https://img.shields.io/badge/Version-2.0.0-brightgreen.svg)](https://github.com/nytafar/kaupang-review-images/releases)
[![WooCommerce 9.0+](https://img.shields.io/badge/WooCommerce-9.0+-a46497.svg)](https://woocommerce.com/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-8892BF.svg)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

Customers attach a product photo and a profile picture to their WooCommerce review; both are
stored as ordinary media-library attachments referenced from comment meta. Conditional Gravatar
loading keeps default mystery-person avatars off the page. A **Curated Review** block lets an
editor place one hand-picked review anywhere on the site.

Part of the Kaupang suite: `Kaupang\ReviewImages\`, `KAUPANG_REVIEW_IMAGES_*`, Composer-less
PSR-4, slash-namespaced `kaupang/review-images/*` seams.

## Features

- **Review image uploads** — one photo per review, 2 MB ceiling
- **Custom avatar uploads** — takes precedence over Gravatar, 1 MB ceiling
- **Conditional Gravatar loading** — a review author with no real Gravatar and no upload gets
  *no* `<img>` at all, rather than the default silhouette
- **Retina avatars** — base size plus a `2x` `srcset`, for both custom uploads and real Gravatars
- **Curated Review block** — place one specific review anywhere; the theme owns presentation
- **Admin surface** — an "Image" column on the Comments screen plus review-image and avatar meta
  boxes on the comment-edit screen
- **JPEG, PNG, GIF and WebP**, nonce- and MIME-guarded on upload
- **HPOS-compatible** (declared; the plugin never touches order data)

## Requirements

- WordPress 6.7 or higher
- WooCommerce 9.0 or higher (tested up to 11.0)
- PHP 8.1 or higher

There are no settings, no options, no admin menu, no REST routes and no cron. Everything is
configured through the filters below.

## Installation

1. Upload the `kaupang-review-images` folder to `/wp-content/plugins/`
2. Activate through the Plugins menu
3. The upload fields appear in the product review form automatically

`block/build/` is committed, so deploys are copy-only — no `node` on the server. See
[Building](#building) if you change the editor JS.

## Usage

### For customers

1. Write a product review as usual
2. **Your Photo** (optional) — upload an avatar
3. **Product Image** (optional) — upload a photo of the product
4. Submit. Both appear with the review once it is approved.

### For administrators

Go to **Comments**. The "Image" column shows the review photo; opening a review shows the
"Review Image" and "Review Author Avatar" meta boxes. Uploads are ordinary attachments, so they
are managed and deleted from the Media Library like anything else.

### For editors — the Curated Review block

Editorial, not a feed. An editor picks *one* review and places it: a testimonial on the home
page, a callout in a blog post, a trust signal on a landing page. The same review can appear in
three places looking completely different, because the block decides only **which data elements
are present** and the theme decides how they look.

Insert **Curated Review** (Widgets category), pick a product in the sidebar, then one of its
reviews. On a product page the product is pre-filled — an editor convenience, not a different
default.

The review picker reads `GET /wc/v3/products/reviews`, whose permission is `moderate_comments`.
Administrator, Editor and Shop manager pass; Author and Contributor get a 403 and the picker says
so. The frontend renders for everyone — only *choosing* is gated.

Known cap: the picker requests `per_page=100`, so a product with more than 100 approved reviews
truncates the list.

## The block

### Identity

| | |
|---|---|
| Block name | `kaupang-review-images/curated-review` |
| Render callback | `Kaupang\ReviewImages\Block\CuratedReview::render()` |
| Metadata | `block/block.json` |
| Editor source | `block/src/index.js` → `block/build/` (committed) |
| Kill switch | `kaupang/review-images/enable_block` |

The PHP render callback is the single source of truth for the markup. The editor previews it
through `<ServerSideRender />`, so there is no JS-side copy that can drift.

### It ships zero CSS

No `style`, no `editorStyle` in `block.json`; no inline styles; no `style` attributes on anything
the block authors. The theme owns presentation entirely — `myrvann/scss/plugins/_kaupang-review.scss`
is where it lands on this site. The one inline style you will see inside the rating is
WooCommerce's own `width:X%` star encoding from `wc_get_star_rating_html()`, which is data, not
presentation.

### Attributes

All eleven, with the defaults from `block/block.json`:

| Attribute | Type | Default | | Attribute | Type | Default |
|---|---|---|---|---|---|---|
| `reviewId` | number | `0` | | `showAuthor` | boolean | `true` |
| `productId` | number | `0` | | `showAvatar` | boolean | `true` |
| `showBody` | boolean | `true` | | `showVerified` | boolean | **`false`** |
| `showReviewImages` | boolean | **`false`** | | `showDate` | boolean | `true` |
| `showRating` | boolean | **`false`** | | `showProductName` | boolean | `true` |
| | | | | `showProductImage` | boolean | **`false`** |

Defaults are uniform — they do not vary by context. The render callback reads them back off the
block registry, so `block.json` is the only place they are defined.

### Class contract

`kaupang-review__*`, BEM, vendor spelled out. Document order is fixed — body, images, rating,
attribution, product. Toggles decide *presence*, never position; CSS `order`/grid makes visual
position free. This is the reading order for screen readers and text extraction.

```html
<article class="kaupang-review" data-review-id="482" data-rating="5" data-verified="1" data-has-images="1">
  <blockquote class="kaupang-review__body" cite="https://…/produkt/kakaonibs/">
    <p>…</p>
  </blockquote>

  <figure class="kaupang-review__images">
    <img class="kaupang-review__image" …>
  </figure>

  <p class="kaupang-review__rating">
    <span class="star-rating" role="img" aria-label="Rated 5 out of 5">…</span>
  </p>

  <footer class="kaupang-review__author">
    <img class="kaupang-review__avatar avatar …" …>
    <cite class="kaupang-review__author-name">Kari N.</cite>
    <em class="kaupang-review__verified">(verified owner)</em>
    <time class="kaupang-review__date" datetime="2026-04-12T09:31:00+02:00">12. april 2026</time>
  </footer>

  <div class="kaupang-review__product">
    <a class="kaupang-review__product-link" href="…">
      <img class="kaupang-review__product-image" …>
      <span class="kaupang-review__product-name">…</span>
    </a>
  </div>
</article>
```

Notes on what is actually emitted:

- The root `<article>` carries `get_block_wrapper_attributes()`, so editor-set classes (align,
  spacing, `wp-block-*`) merge with `kaupang-review`.
- `star-rating` is WooCommerce's own class, around `wc_get_star_rating_html()`. It is
  deliberately **not** `wc_get_rating_html()` — the theme filters that one to an empty string
  (`myrvann/inc/woocommerce.php:18`) to kill loop ratings. `role="img"` + `aria-label` sit on the
  span, as in WooCommerce's own review-rating template; there is no second `aria-label` on the
  wrapping `<p>` competing with it.
- The avatar comes back from `get_avatar()`, which runs the full custom-upload → real-Gravatar →
  nothing chain, so it carries the chain's own classes (`avatar avatar-custom wcri-custom-avatar`
  or `wcri-gravatar`) in addition to `kaupang-review__avatar`. When the chain returns nothing, no
  `<img>` is emitted.
- `kaupang-review__product` and its `<a>` only render when the product still exists.
- `(verified owner)` and `Rated %s out of 5` use the `woocommerce` text domain on purpose: they
  are verbatim WooCommerce strings and arrive already translated.

### State attributes

`data-review-id`, `data-rating`, `data-verified`, `data-has-images` render on the root
**regardless of the corresponding `show*` toggle** — a hidden rating still exposes
`data-rating`. The theme can hang the cascade on state it cannot see.

`data-verified` and `data-has-images` are `"1"`/`"0"`. `data-rating` is the raw integer, `"0"`
when the review has no rating.

### Empty state

Missing means any of: `reviewId` 0, comment deleted, unapproved, spam, trashed, or not a review
on a product.

- **Frontend:** the empty string. An empty wrapper is itself a style decision and this block
  ships none.
- **Editor:** a plain `<p class="kaupang-review__notice">` saying why — detected via
  `REST_REQUEST`, because `ServerSideRender` comes through `/wp/v2/block-renderer/`.

### No structured data

No schema.org microdata, no JSON-LD, deliberately. Designation is carried by HTML semantics
instead: `<article>` around a `<blockquote cite>`, `<cite>` for the reviewer, `<time datetime>`
for the date, the rating as real text behind an `aria-label`. The reasoning and its primary
sources are recorded in `docs/suite-seams/kaupang-review-images.md` — read that before adding
schema back.

## Filters

Every filter this plugin defines, with the default it actually applies. All are slash-namespaced
`kaupang/review-images/*`.

| Filter | Args | Default | Applied in |
|---|---|---|---|
| `kaupang/review-images/enable_review_images` | `bool $enabled` | `true` | `includes/Plugin.php` |
| `kaupang/review-images/enable_avatar_upload` | `bool $enabled` | `true` | `includes/Plugin.php`, `includes/Reviews/Images.php` |
| `kaupang/review-images/enable_avatar_display` | `bool $enabled` | `true` | `includes/Plugin.php` |
| `kaupang/review-images/enable_conditional_gravatars` | `bool $enabled` | `true` | `includes/Plugin.php`, `includes/Avatars/Display.php`, `includes/Avatars/Gravatar.php` |
| `kaupang/review-images/enable_block` | `bool $enabled` | `true` | `includes/Block/CuratedReview.php` |
| `kaupang/review-images/enqueue_styles` | `bool $enabled` | `true` | `includes/Reviews/Images.php` |
| `kaupang/review-images/upload_field_label` | `string $label`, `WP_Post $product` | `'Product Image'` | `includes/Reviews/Images.php` |
| `kaupang/review-images/avatar_upload_field_label` | `string $label`, `WP_Post $product` | `'Your Photo'` | `includes/Reviews/Images.php` |
| `kaupang/review-images/avatar_base_size` | `int $px` | `120` | `includes/Avatars/Display.php`, `includes/Block/CuratedReview.php` |
| `kaupang/review-images/gravatar_base_size` | `int $px` | `120` | `includes/Avatars/Gravatar.php` |
| `kaupang/review-images/custom_avatar_html` | `string $html`, `int $commentId`, `int $avatarId`, `int\|string $size` | the built `<img>` | `includes/Avatars/Display.php` |

### When the gates are evaluated

The four `enable_*` module gates in `Plugin::boot()` run at **plugin-load time**, so only an
mu-plugin or an earlier-loading plugin can flip them — a theme's `functions.php` runs too late.
That timing is inherited from before the Kaupang conversion, not new.

`enable_block` is different: it is applied inside the `init` callback, so a theme *can* flip it.
`enable_avatar_upload` (in the form renderer), `enable_conditional_gravatars` (in the two
`get_avatar` filters) and `enqueue_styles` are all re-evaluated at render time and are themeable
too.

### Examples

```php
// Take the block out of the inserter entirely.
add_filter('kaupang/review-images/enable_block', '__return_false');

// Serve 96px avatars (and 192px for retina).
add_filter('kaupang/review-images/avatar_base_size', fn() => 96);

// Same for real Gravatars.
add_filter('kaupang/review-images/gravatar_base_size', fn() => 96);

// Show default Gravatars again, silhouettes and all.
add_filter('kaupang/review-images/enable_conditional_gravatars', '__return_false');

// Theme owns the upload-form CSS.
add_filter('kaupang/review-images/enqueue_styles', '__return_false');

// Product-specific field label.
add_filter('kaupang/review-images/upload_field_label', function ($label, $product) {
    return sprintf(__('Upload a photo of your %s', 'my-theme'), get_the_title($product));
}, 10, 2);

// Wrap the custom avatar.
add_filter('kaupang/review-images/custom_avatar_html', function ($html, $commentId, $avatarId, $size) {
    return '<span class="my-avatar-ring">' . $html . '</span>';
}, 10, 4);
```

## PHP API

Public statics worth knowing about; everything else is internal.

| Call | Returns |
|---|---|
| `Reviews\Images::getImageIds(int $commentId)` | `int[]` — attachment ids of the review's images. Zero or one element today; an array from day one so multi-image upload later is a storage change only. |
| `Avatars\Upload::getCommentAvatarId(int $commentId)` | `int\|false` |
| `Avatars\Upload::hasCustomAvatar(int\|WP_Comment $comment)` | `bool` |
| `Avatars\Upload::getCommentAvatarUrl(int $commentId, $size)` | `string\|false` |
| `Avatars\Display::getCustomAvatarHtml(int $commentId, $size, string $alt)` | `string` — the custom upload only, *not* the Gravatar fallback. Use `get_avatar()` if you want the whole chain. |
| `Avatars\Display::getAvatarUrl($comment, $size)` | `string\|false` — custom upload, else a Gravatar URL. |
| `Avatars\Gravatar::hasGravatar(string $email)` | `bool` — 24h-transient-cached `HEAD` probe. |
| `Support\Comments::asProductReview(WP_Comment $comment)` | `?WP_Comment` — the "is this a product review" gate. |

## Data storage

No custom tables. Comment meta plus ordinary attachments plus one transient.

| Key | Holds |
|---|---|
| `_review_image_id` (comment meta) | attachment id of the review image |
| `_review_author_avatar_id` (comment meta) | attachment id of the custom avatar |
| `kaupang_review_images_has_gravatar_{md5(email)}` (transient, 24h) | `'yes'`/`'no'` |

Uploads are parented to the product via `media_handle_upload()`; avatar attachments also get
`_wp_attachment_image_alt`. Meta is written in `comment_post` **only if the comment is approved**.

## Building

The editor JS is the only thing that needs a build. `block/build/` is **committed** so deploys
stay copy-only.

```bash
npm ci
npm run build      # or: npm start  for a watch build
```

Known quirk on this server: `npm` can leave the symlinks in `node_modules/.bin` without the
executable bit, and the first `npm run build` then dies with `wp-scripts: Permission denied`.
Fix after a fresh install:

```bash
find node_modules/.bin -maxdepth 1 -type l -exec chmod +x {} \;
```

`node_modules/` and `languages/*.l10n.php` are gitignored; the latter is a cache
performant-translations regenerates from the `.mo` on every load.

## Verification

Half this plugin had nothing to render against — staging carries 143 reviews and zero
`_review_image_id`, zero `_review_author_avatar_id` — so there are two PHP-native scripts in
`bin/`. Run them as the site user, never as root.

```bash
# Seed one approved review with a rating, the verified flag, a review image and a custom avatar.
# Idempotent; `clean` removes the review and both attachments again.
sudo -u myrvann wp --path=/var/www/staging.myrvann.no/htdocs \
  eval-file wp-content/plugins/kaupang-review-images/bin/seed-review-fixtures.php
sudo -u myrvann wp --path=/var/www/staging.myrvann.no/htdocs \
  eval-file wp-content/plugins/kaupang-review-images/bin/seed-review-fixtures.php clean

# The runnable check: seeds its own fixture, asserts 23 things, trashes it in a finally
# block, and exits non-zero on failure.
sudo -u myrvann wp --path=/var/www/staging.myrvann.no/htdocs \
  eval-file wp-content/plugins/kaupang-review-images/bin/check-review-block.php
```

No PHPUnit, no fixtures framework — the smallest thing that goes red if the block, the
`getImageIds()` accessor or the avatar chain breaks. The Gravatar half is made deterministic by
pre-seeding the transient `Gravatar::hasGravatar()` caches into, so it needs no network.

Neither script declares `strict_types`: `wp eval-file` evals the source, and a `declare` must be
the first statement of a script. Both say so in a comment — do not "fix" it back.

## Changelog

### 2.0.0 - 2026-09-01

Two breaking changes — the rename and the hook removal — hence the major bump.

- **BREAKING**: renamed `woocommerce-review-images` → `kaupang-review-images`. Folder, entry
  file, text domain, GitHub repo. Every `wcri_*` filter is now `kaupang/review-images/*`.
- **BREAKING**: the five `woocommerce_review_meta_{start,author,after_author,after_verified,end}`
  actions **no longer exist**. `custom-review-meta.php` replaced WooCommerce's
  `woocommerce_review_display_meta` renderer wholesale just to emit them, and the only consumer
  anywhere was the myrvann theme moving a gravatar. WooCommerce's own renderer runs again and the
  avatar returns to `woocommerce_review_before`. They are not renamed, deprecated or shimmed:
  no third party fires or consumes them, and impersonating a core hook only misleads whoever
  greps WooCommerce for it. `kaupang/review-images/display_avatar_in_meta` and
  `WC_Review_Images_Avatar_Display::display_avatar_in_review_meta()` go with them.
- **NEW**: the **Curated Review** block (`kaupang-review-images/curated-review`) — one
  hand-picked review, nine toggles, the `kaupang-review__*` class contract, zero CSS.
  Kill switch: `kaupang/review-images/enable_block`.
- **FIXED**: a review whose author has **no email address** rendered the default mystery-person.
  The conditional-Gravatar branch only suppressed when an address was present and had no
  Gravatar — but no address means no Gravatar can exist, so that was the case most in need of
  suppressing.
- **FIXED**: the avatar gate asked the wrong question. Both `get_avatar` filters early-returned
  unless `is_product()`, so the custom-upload → real-Gravatar → nothing chain never ran off a
  product page — exactly where the default silhouette this plugin exists to suppress would show.
  The gate now tests whether the *comment* is a product review (`Support\Comments`). A latent bug
  went with it: a numeric `$id_or_email` in `get_avatar()` is a *user* id, and the old code read
  it as a comment id.
- **CHANGED**: Composer-less PSR-4 (`Kaupang\ReviewImages\` → `includes/`), `strict_types=1`,
  `KAUPANG_REVIEW_IMAGES_{VERSION,FILE,DIR,URL}`, boot wiring in `includes/Plugin.php`.
- **CHANGED**: requirements raised to PHP 8.1 / WordPress 6.7 / WooCommerce 9.0.
- **NEW**: `Reviews\Images::getImageIds()` accessor; every `_review_image_id` call site routes
  through it and loop, so multi-image support is a storage change away.
- **NEW**: `Support\Media::ingest()` — one shared size → MIME → `media_handle_upload()` path
  replacing the two near-identical upload handlers, and one copy of the MIME allowlist.
- **CHANGED**: asset versions come from `filemtime()` instead of a frozen `'1.2.1'` literal that
  had already drifted from the header.
- **REMOVED**: the ~30-line bespoke `.mo` candidate-probing loader, the dead
  `display_avatar_upload_field()`, and the `WP_DEBUG` footer probe.
- **DOCS**: two defaults were documented wrongly before this release —
  `display_avatar_in_meta` was documented `true` but was always `false` (moot: it is deleted),
  and `gravatar_base_size` was documented `200` but has always been `120`.

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

> Changelog entries below 2.0.0 are history. The hooks and `wcri_*` filter names they mention no
> longer exist — the current API is the filter table above.

## Upgrade notice

### 2.0.0
The plugin folder, text domain and every filter name changed, and the five
`woocommerce_review_meta_*` actions were deleted. If anything in your theme hooks them — the
myrvann child theme did — that code must move to `woocommerce_review_before` in the same
deploy window, or the byline avatar silently disappears.

## Support

For support, please [open an issue](https://github.com/nytafar/kaupang-review-images/issues) on GitHub.

## License

GPL-2.0-or-later

## Credits

Created by [Lasse Jellum](https://jellum.net)
