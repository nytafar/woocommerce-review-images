<?php
/**
 * Plugin Name: WooCommerce Review Images (Simplified)
 * Description: Allows a single image upload with product reviews.
 * Version: 0.6.0
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

if ( ! class_exists( 'WC_Review_Images_Simplified' ) ) {
    class WC_Review_Images_Simplified {
        protected static $instance = null;
        private static $uploaded_image_attachment_id = null;

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

            // Display upload field and nonce
            add_action( 'comment_form_logged_in_after', array( $this, 'display_upload_field_and_nonce' ) ); // For logged-in users
            add_action( 'comment_form_after_fields', array( $this, 'display_upload_field_and_nonce' ) );    // For logged-out users (after name, email, url fields)
            
            // Ensure form has enctype for file uploads
            add_action( 'wp_footer', array( $this, 'ensure_form_enctype_script' ), 99 );

            // Handle image upload during comment processing
            add_action( 'preprocess_comment', array( $this, 'handle_image_upload' ) );

            // Save image meta data after comment is saved
            add_action( 'comment_post', array( $this, 'save_image_meta' ), 10, 2 );

            // Display the image in the review on the front-end
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
            if ( ! is_product() || ! comments_open() ) {
                return;
            }
            // Prevent duplicate output if both hooks fire in a way that would cause it
            static $field_displayed = false;
            if ( $field_displayed ) {
                return;
            }

            echo '<p class="comment-form-image-upload">';
            echo '<label for="review_image_upload">' . esc_html__( 'Upload an image (optional)', 'woocommerce-review-images' ) . '</label>';
            echo '<input type="file" id="review_image_upload" name="review_image_upload" accept="image/*" />';
            echo '</p>';
            wp_nonce_field( 'wcri_image_upload_action', 'wcri_image_upload_nonce' );
            $field_displayed = true;
        }
        
        public function ensure_form_enctype_script() {
            if ( is_product() && comments_open() ) {
                // This JavaScript ensures the comment form can handle file uploads.
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
            // Only process for product reviews and if nonce is valid
            if ( !isset($_POST['comment_post_ID']) || 'product' !== get_post_type( absint( $_POST['comment_post_ID'] ) ) ) return $commentdata;
            if ( !isset($_POST['wcri_image_upload_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcri_image_upload_nonce'])), 'wcri_image_upload_action')) return $commentdata;

            if ( isset( $_FILES['review_image_upload'] ) && !empty( $_FILES['review_image_upload']['name'] ) && $_FILES['review_image_upload']['error'] == UPLOAD_ERR_OK ) {
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                
                $attachment_id = media_handle_upload( 'review_image_upload', absint( $_POST['comment_post_ID'] ) );

                if ( is_wp_error( $attachment_id ) ) {
                    // error_log( 'WCRI Error: Failed to upload image. ' . $attachment_id->get_error_message() );
                } else {
                    self::$uploaded_image_attachment_id = $attachment_id;
                }
            }
            return $commentdata;
        }

        public function save_image_meta( $comment_id, $comment_approved ) {
            if ( $comment_approved && self::$uploaded_image_attachment_id !== null ) {
                update_comment_meta( $comment_id, '_review_image_id', self::$uploaded_image_attachment_id );
                self::$uploaded_image_attachment_id = null;
            }
        }

        public function display_review_image( $comment ) {
            $image_id = get_comment_meta( $comment->comment_ID, '_review_image_id', true );
            if ( $image_id ) {
                $image_html = wp_get_attachment_image( $image_id, 'medium', false, array('style' => 'margin-top:10px;max-width:100%;height:auto;') );
                if ( $image_html ) {
                    echo $image_html;
                }
            }
        }
    } // END class WC_Review_Images_Simplified

    WC_Review_Images_Simplified::get_instance();
}