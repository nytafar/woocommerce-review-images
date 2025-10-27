<?php
/**
 * Plugin Name: WooCommerce Review Images
 * Plugin URI: https://github.com/nytafar/woocommerce-review-images
 * Description: Enhance WooCommerce product reviews by allowing customers to upload images with their reviews. Includes custom avatar uploads, Gravatar optimization, and admin management tools.
 * Version: 1.2.0
 * Author: Lasse Jellum
 * Author URI: https://jellum.net
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-review-images
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_Review_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include existing functionality modules
include_once( plugin_dir_path( __FILE__ ) . 'existing-gravatar.php' );
include_once( plugin_dir_path( __FILE__ ) . 'custom-review-meta.php' );

// Include new avatar upload functionality
include_once( plugin_dir_path( __FILE__ ) . 'includes/class-wcri-avatar-upload.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/class-wcri-avatar-display.php' );

// Load text domain on plugins_loaded (standard WordPress hook)
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'woocommerce-review-images', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    // Debug: Check if translations are loaded (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_action('wp_footer', function() {
            $locale = get_locale();
            $loaded = is_textdomain_loaded('woocommerce-review-images');
            $test_translation = __('Choose Photo', 'woocommerce-review-images');
            echo "<!-- WCRI Debug - Locale: $locale, Textdomain loaded: " . ($loaded ? 'Yes' : 'No') . ", Test translation: $test_translation -->";
        });
    }
} );

/**
 * Filters whether to enable the review images functionality.
 *
 * @since 1.0.2
 * @param bool $enabled Whether the review images functionality is enabled. Default true.
 */
if ( ! class_exists( 'WC_Review_Images' ) && apply_filters( 'wcri_enable_review_images', true ) ) {
    class WC_Review_Images {
        protected static $instance = null;
        private static $uploaded_image_attachment_id = null;

        // Constants for configuration
        const META_KEY_IMAGE_ID = '_review_image_id';
        const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024; // 2MB
        const ALLOWED_MIME_TYPES = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif'          => 'image/gif',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        );

        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

        public function init() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
                return;
            }

            add_action( 'comment_form_logged_in_after', array( $this, 'display_upload_field_and_nonce' ) );
            add_action( 'comment_form_after_fields', array( $this, 'display_upload_field_and_nonce' ) );    
            add_action( 'wp_footer', array( $this, 'ensure_form_enctype_script' ), 99 );
            add_action( 'preprocess_comment', array( $this, 'handle_image_upload' ) );
            add_action( 'comment_post', array( $this, 'save_image_meta' ), 10, 2 );
            add_action( 'woocommerce_review_before', array( $this, 'display_review_image' ) );

            if ( is_admin() ) {
                add_filter( 'manage_edit-comments_columns', array( $this, 'add_review_image_admin_column_header' ) );
                add_action( 'manage_comments_custom_column', array( $this, 'display_review_image_admin_column_content' ), 10, 2 );
                add_action( 'add_meta_boxes_comment', array( $this, 'add_review_image_meta_box' ) );
            }
        }

        public function woocommerce_missing_notice() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'WooCommerce Review Images plugin requires WooCommerce to be installed and active.', 'woocommerce-review-images' ); ?></p>
            </div>
            <?php
        }

        public function display_upload_field_and_nonce() {
            if ( ! is_product() || ! comments_open() ) return;
            static $field_displayed = false;
            if ( $field_displayed ) return;

            // Check if avatar upload is enabled
            $avatar_enabled = apply_filters('wcri_enable_avatar_upload', true);
            
            // Get filterable labels
            $avatar_label = apply_filters('wcri_avatar_upload_field_label_text', __('Your Photo', 'woocommerce-review-images'), get_post());
            $review_label = apply_filters('wcri_upload_field_label_text', __('Product Image', 'woocommerce-review-images'), get_post());
            
            ?>
            <style>
                .wcri-upload-container {
                    display: flex;
                    gap: 20px;
                    margin: 15px 0;
                    flex-wrap: wrap;
                }
                .wcri-upload-field {
                    flex: 1;
                    min-width: 250px;
                    padding: 20px;
                    border: 2px dashed #ddd;
                    border-radius: 8px;
                    background: #fafafa;
                    transition: all 0.3s ease;
                    position: relative;
                }
                .wcri-upload-field:hover {
                    border-color: #999;
                    background: #f5f5f5;
                }
                .wcri-upload-field label {
                    display: block;
                    font-weight: 600;
                    font-size: 15px;
                    margin-bottom: 10px;
                    color: #333;
                }
                .wcri-upload-field input[type="file"] {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    border: 0;
                }
                .wcri-upload-button {
                    display: block;
                    width: 100%;
                    padding: 12px;
                    background: #f0f0f1;
                    border: 1px dashed #8c8f94;
                    border-radius: 4px;
                    color: #2271b1;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .wcri-upload-button:hover {
                    background: #e0e0e0;
                    border-color: #2271b1;
                }
                .wcri-preview-container {
                    margin-top: 10px;
                    display: none;
                    text-align: center;
                }
                .wcri-preview-image {
                    max-width: 100%;
                    max-height: 200px;
                    border-radius: 4px;
                    margin-top: 10px;
                    display: block;
                }
                .wcri-remove-image {
                    display: inline-block;
                    margin-top: 5px;
                    color: #d63638;
                    text-decoration: none;
                    font-size: 12px;
                }
                .wcri-remove-image:hover {
                    text-decoration: underline;
                }
                .wcri-upload-hint {
                    font-size: 12px;
                    color: #666;
                    margin: 10px 0 5px;
                    font-style: italic;
                    display: block;
                }
                @media (max-width: 768px) {
                    .wcri-upload-container {
                        flex-direction: column;
                    }
                    .wcri-upload-field {
                        min-width: 100%;
                    }
                }
            </style>
            <div class="wcri-upload-container">
                <?php if ($avatar_enabled) : ?>
                <div class="wcri-upload-field comment-form-avatar-upload">
                    <label for="wcri_avatar_upload">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <?php echo esc_html($avatar_label); ?>
                    </label>
                    <input type="file" id="wcri_avatar_upload" name="wcri_avatar_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
                    <label for="wcri_avatar_upload" class="wcri-upload-button">
                        <?php esc_html_e('Choose Photo', 'woocommerce-review-images'); ?>
                    </label>
                    <div class="wcri-upload-hint"><?php esc_html_e('Upload your profile picture', 'woocommerce-review-images'); ?></div>
                    <div class="wcri-preview-container" id="wcri_avatar_preview">
                        <img class="wcri-preview-image" alt="<?php esc_attr_e('Avatar preview', 'woocommerce-review-images'); ?>" />
                        <a href="#" class="wcri-remove-image" data-target="wcri_avatar_upload"><?php esc_html_e('Remove', 'woocommerce-review-images'); ?></a>
                    </div>
                    <?php wp_nonce_field('wcri_avatar_upload_action', 'wcri_avatar_upload_nonce', false); ?>
                </div>
                <?php endif; ?>
                
                <div class="wcri-upload-field comment-form-image-upload">
                    <label for="wcri_review_image_upload">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <?php echo esc_html($review_label); ?>
                    </label>
                    <input type="file" id="wcri_review_image_upload" name="wcri_review_image_upload" accept="image/jpeg,image/png,image/gif,image/webp" />
                    <label for="wcri_review_image_upload" class="wcri-upload-button">
                        <?php esc_html_e('Choose Image', 'woocommerce-review-images'); ?>
                    </label>
                    <div class="wcri-upload-hint"><?php esc_html_e('Share a photo of the product', 'woocommerce-review-images'); ?></div>
                    <div class="wcri-preview-container" id="wcri_review_image_preview">
                        <img class="wcri-preview-image" alt="<?php esc_attr_e('Review image preview', 'woocommerce-review-images'); ?>" />
                        <a href="#" class="wcri-remove-image" data-target="wcri_review_image_upload"><?php esc_html_e('Remove', 'woocommerce-review-images'); ?></a>
                    </div>
                    <?php wp_nonce_field('wcri_image_upload_action', 'wcri_image_upload_nonce', false); ?>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Translated strings
                const wcriStrings = {
                    changePhoto: '<?php echo esc_js(__('Change Photo', 'woocommerce-review-images')); ?>',
                    changeImage: '<?php echo esc_js(__('Change Image', 'woocommerce-review-images')); ?>',
                    choosePhoto: '<?php echo esc_js(__('Choose Photo', 'woocommerce-review-images')); ?>',
                    chooseImage: '<?php echo esc_js(__('Choose Image', 'woocommerce-review-images')); ?>'
                };
                
                // Handle file input changes for preview
                $('.wcri-upload-field input[type="file"]').on('change', function(e) {
                    const file = e.target.files[0];
                    const $field = $(this).closest('.wcri-upload-field');
                    const $preview = $field.find('.wcri-preview-container');
                    const $previewImg = $preview.find('.wcri-preview-image');
                    const $button = $field.find('.wcri-upload-button');
                    const isAvatar = $field.hasClass('comment-form-avatar-upload');
                    
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            $previewImg.attr('src', e.target.result);
                            $preview.show();
                            if (isAvatar) {
                                $button.text(wcriStrings.changePhoto);
                            } else {
                                $button.text(wcriStrings.changeImage);
                            }
                        };
                        reader.readAsDataURL(file);
                    } else {
                        $preview.hide();
                        if (isAvatar) {
                            $button.text(wcriStrings.choosePhoto);
                        } else {
                            $button.text(wcriStrings.chooseImage);
                        }
                    }
                });
                
                // Handle remove image clicks
                $('.wcri-remove-image').on('click', function(e) {
                    e.preventDefault();
                    const $field = $(this).closest('.wcri-upload-field');
                    const $input = $field.find('input[type="file"]');
                    const $preview = $field.find('.wcri-preview-container');
                    const $button = $field.find('.wcri-upload-button');
                    const isAvatar = $field.hasClass('comment-form-avatar-upload');
                    
                    $input.val('');
                    $preview.hide();
                    if (isAvatar) {
                        $button.text(wcriStrings.choosePhoto);
                    } else {
                        $button.text(wcriStrings.chooseImage);
                    }
                });
            });
            </script>
            <?php
            $field_displayed = true;
        }
        
        public function ensure_form_enctype_script() {
            if ( is_product() && comments_open() ) {
                echo "<script type='text/javascript'>
                    jQuery(document).ready(function($) {
                        var commentForm = $('#commentform');
                        if (commentForm.length) {
                            commentForm.attr('enctype', 'multipart/form-data');
                        }
                    });
                </script>";
            }
        }

        public function handle_image_upload( $commentdata ) {
            if ( !isset($_POST['comment_post_ID']) || 'product' !== get_post_type( absint( $_POST['comment_post_ID'] ) ) ) return $commentdata;
            if ( !isset($_POST['wcri_image_upload_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcri_image_upload_nonce'])), 'wcri_image_upload_action')) return $commentdata;

            if ( isset( $_FILES['wcri_review_image_upload'] ) && !empty( $_FILES['wcri_review_image_upload']['name'] ) && $_FILES['wcri_review_image_upload']['error'] == UPLOAD_ERR_OK ) {
                
                $file = $_FILES['wcri_review_image_upload'];

                if ( $file['size'] > self::MAX_FILE_SIZE_BYTES ) {
                    error_log( 'WCRI Error: Uploaded file exceeds max size limit. File: ' . sanitize_file_name($file['name']) );
                    return $commentdata; 
                }

                $file_type = wp_check_filetype( $file['name'], self::ALLOWED_MIME_TYPES );
                if ( ! $file_type['ext'] || ! $file_type['type'] ) {
                    error_log( 'WCRI Error: Uploaded file type is not allowed. File: ' . sanitize_file_name($file['name']) . ' Detected type: ' . sanitize_mime_type($file['type']) );
                    return $commentdata; 
                }

                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                
                $attachment_id = media_handle_upload( 'wcri_review_image_upload', absint( $_POST['comment_post_ID'] ) );

                if ( is_wp_error( $attachment_id ) ) {
                    error_log( 'WCRI Error: media_handle_upload failed. Message: ' . $attachment_id->get_error_message() . ' File: ' . sanitize_file_name($file['name']) );
                } else {
                    self::$uploaded_image_attachment_id = $attachment_id;
                }
            }
            return $commentdata;
        }

        public function save_image_meta( $comment_id, $comment_approved ) {
            if ( $comment_approved && self::$uploaded_image_attachment_id !== null ) {
                update_comment_meta( $comment_id, self::META_KEY_IMAGE_ID, self::$uploaded_image_attachment_id );
                self::$uploaded_image_attachment_id = null;
            }
        }

        public function display_review_image( $comment ) {
            $image_id = get_comment_meta( $comment->comment_ID, self::META_KEY_IMAGE_ID, true );
            if ( $image_id ) {
                $image_html = wp_get_attachment_image( $image_id, 'medium', false, array('style' => 'height:auto;width:100%;;') );
                if ( $image_html ) {
                    echo $image_html;
                }
            }
        }

        // Admin columns for comments
        public function add_review_image_admin_column_header( $columns ) {
            $new_columns = array();
            $insert_before = 'date'; 
            if (!isset($columns[$insert_before])) {
                 $insert_before = 'response';
            }

            foreach ( $columns as $key => $title ) {
                if ( $key == $insert_before ) {
                    $new_columns['review_image'] = __( 'Image', 'woocommerce-review-images' );
                }
                $new_columns[$key] = $title;
            }
            if ( !isset( $new_columns['review_image'] ) ) {
                $new_columns['review_image'] = __( 'Image', 'woocommerce-review-images' );
            }
            return $new_columns;
        }

        public function display_review_image_admin_column_content( $column_name, $comment_id ) {
            if ( 'review_image' === $column_name ) {
                $image_id = get_comment_meta( $comment_id, self::META_KEY_IMAGE_ID, true );
                if ( $image_id ) {
                    $image_html = wp_get_attachment_image( $image_id, array(80, 80), true, array( 'style' => 'max-width:80px; height:auto; display:block; margin:auto;' ) );
                    if ( $image_html ) {
                        echo $image_html;
                    } else {
                        echo esc_html__( 'N/A', 'woocommerce-review-images' );
                    }
                } else {
                    echo esc_html__( 'N/A', 'woocommerce-review-images' );
                }
            }
        }

        // Meta box for comment edit screen
        public function add_review_image_meta_box() {
            global $comment;
            if ( $comment && 'product' === get_post_type( $comment->comment_post_ID ) ) {
                add_meta_box(
                    'wcri_review_image_meta_box',
                    __( 'Review Image', 'woocommerce-review-images' ),
                    array( $this, 'render_review_image_meta_box' ),
                    'comment', 
                    'normal',
                    'high'
                );
            }
        }

        public function render_review_image_meta_box( $comment ) {
            $image_id = get_comment_meta( $comment->comment_ID, self::META_KEY_IMAGE_ID, true );
            if ( $image_id ) {
                $image_html = wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:100%; height:auto;' ) );
                if ( $image_html ) {
                    echo $image_html;
                } else {
                    echo '<p>' . esc_html__( 'Image data found, but the image could not be displayed. It might have been deleted from the media library.', 'woocommerce-review-images' ) . '</p>';
                }
            } else {
                echo '<p>' . esc_html__( 'No image was uploaded with this review.', 'woocommerce-review-images' ) . '</p>';
            }
        }

    } 

    WC_Review_Images::get_instance();
}