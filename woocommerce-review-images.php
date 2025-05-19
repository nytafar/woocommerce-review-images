<?php
/**
 * Plugin Name: WooCommerce Review Images
 * Description: Allows a single image upload with product reviews.
 * Version: 0.9.0
 * Author: Cascade AI & User
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-review-images
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Review_Images' ) ) {
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
            add_action( 'woocommerce_review_after_comment_text', array( $this, 'display_review_image' ) );
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

            $default_label_text = __( 'Upload an image (optional, max 2MB, JPG, PNG, GIF)', 'woocommerce-review-images' );
            
            /**
             * Filters the label text for the review image upload field.
             *
             * @since 0.8.0
             *
             * @param string   $default_label_text The default translatable label text.
             * @param WP_Post|null $product            The current product post object (null if not on a product page, though this function checks for is_product()).
             */
            $label_text = apply_filters( 'wcri_upload_field_label_text', $default_label_text, get_post() );

            echo '<p class="comment-form-image-upload">';
            echo '<label for="wcri_review_image_upload">' . esc_html( $label_text ) . '</label>';
            echo '<input type="file" id="wcri_review_image_upload" name="wcri_review_image_upload" accept="image/jpeg,image/png,image/gif" />';
            echo '</p>';
            wp_nonce_field( 'wcri_image_upload_action', 'wcri_image_upload_nonce' );
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
                $image_html = wp_get_attachment_image( $image_id, 'medium', false, array('style' => 'margin-top:10px;max-width:100%;height:auto;') );
                if ( $image_html ) {
                    echo $image_html;
                }
            }
        }
    } 

    WC_Review_Images::get_instance();
}