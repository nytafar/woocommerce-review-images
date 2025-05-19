<?php
/**
 * Plugin Name: WooCommerce Review Images
 * Description: Allows users to upload an image with their product review and displays it on the frontend.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: Your Website
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-review-images
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: (latest version you know)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Review_Images' ) ) {

    class WC_Review_Images {

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Initialize the plugin.
         */
        private function __construct() {
            // Define constants
            define( 'WCRI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            define( 'WCRI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

            // Add hooks
            add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

        /**
         * Return an instance of this class.
         *
         * @return object A single instance of this class.
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Initialize plugin functionality.
         */
        public function init() {
            // Check if WooCommerce is active
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
                return;
            }

            // Hook to add the upload field
            add_action( 'comment_form_default_fields', array( $this, 'add_review_image_upload_field' ) );
            // Ensure the form can handle file uploads & add nonce
            add_action( 'comment_form_after_fields', array( $this, 'ensure_form_enctype_and_nonce' ) );


            // Handle image upload and save meta
            add_action( 'preprocess_comment', array( $this, 'handle_review_image_upload' ) );
            add_action( 'comment_post', array( $this, 'save_review_image_meta' ), 10, 2 );

            // Display image in review
            add_action( 'woocommerce_review_after_comment_text', array( $this, 'display_review_image' ) );
            
            // Enqueue scripts and styles (optional, if needed later)
            // add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        }

        /**
         * Display a notice if WooCommerce is not active.
         */
        public function woocommerce_missing_notice() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'WooCommerce Review Images requires WooCommerce to be installed and active.', 'woocommerce-review-images' ); ?></p>
            </div>
            <?php
        }

        /**
         * Add the image upload field to the review form.
         *
         * @param array $fields The default comment form fields.
         * @return array Modified comment form fields.
         */
        public function add_review_image_upload_field( $fields ) {
            // Check if it's a product review form
            // Note: 'comment_form_default_fields' is general. We'll ensure it's a product review form contextually.
            // For a more targeted approach, 'woocommerce_product_review_comment_form_args' could be used,
            // but it's harder to just add a simple field without redoing the whole comment_field.
            // This hook is simpler for adding a field among 'author', 'email', 'url'.
            // We'll add it after 'comment_notes_after' if this hook doesn't place it well.

            // A better hook for product reviews is 'comment_form_logged_in_after' and 'comment_form_after_fields' (for not logged in)
            // OR 'woocommerce_review_order_before_submit'
            // For simplicity, let's use 'comment_form_after_fields' which is generally reliable for adding before submit button.
            // The `ensure_form_enctype_and_nonce` function will use this hook too.

            // The initial thought of using 'comment_form_default_fields' might not be ideal for placement.
            // Let's refine this in the next step if needed. For now, we'll rely on 'comment_form_after_fields'
            // for both nonce and potentially the field itself if placement becomes an issue.
            //
            // A common place is inside the 'comment_field'.
            // We can also hook `comment_form_defaults` to modify `$args['comment_field']`.
            return $fields; // We will add the field using a more direct action hook.
        }
        
        /**
         * Ensure the comment form has 'multipart/form-data' enctype and add a nonce.
         * Also adds the file input field here for better placement.
         */
        public function ensure_form_enctype_and_nonce() {
            global $post;
            // Only on single product pages
            if ( is_product() && comments_open( $post->ID ) ) {
                // Add enctype to form using JavaScript (simplest cross-theme way if no direct PHP hook to modify <form> tag itself)
                // WordPress doesn't have a clean hook to add attributes directly to the <form> tag generated by comment_form().
                echo "<script>jQuery(document).ready(function($){ $('#commentform').attr('enctype', 'multipart/form-data'); });</script>";

                // Add the upload field
                echo '<p class="comment-form-image-upload">';
                echo '<label for="review_image">' . esc_html__( 'Upload Image (optional)', 'woocommerce-review-images' ) . '</label>';
                echo '<input type="file" id="review_image" name="review_image" accept="image/*" />';
                echo '</p>';

                // Add nonce field
                wp_nonce_field( 'wcri_review_image_upload', 'wcri_review_image_nonce' );
            }
        }


        /**
         * Handle the image upload.
         *
         * @param array $commentdata Comment data.
         * @return array Comment data, possibly with an error if upload failed.
         */
        public function handle_review_image_upload( $commentdata ) {
            // Only process for product reviews
            if ( isset( $_POST['comment_post_ID'] ) && 'product' === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) {
                
                // Verify nonce
                if ( ! isset( $_POST['wcri_review_image_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcri_review_image_nonce'] ) ), 'wcri_review_image_upload' ) ) {
                    // Optionally add an error or just ignore if nonce fails
                    return $commentdata;
                }

                if ( isset( $_FILES['review_image'] ) && ! empty( $_FILES['review_image']['name'] ) ) {
                    if ( $_FILES['review_image']['error'] !== UPLOAD_ERR_OK ) {
                        // Handle upload error, e.g., by setting a transient to show user or logging
                        // For simplicity, we'll just return $commentdata here.
                        // A more robust solution would add a `wp_die` or filter `pre_comment_approved`.
                        return $commentdata;
                    }

                    // Check file type and size (simplified for now)
                    $allowed_mime_types = array( 'image/jpeg', 'image/png', 'image/gif' );
                    $file_mime_type = mime_content_type( $_FILES['review_image']['tmp_name'] );
                    if ( ! in_array( $file_mime_type, $allowed_mime_types ) ) {
                        // Handle invalid file type
                        return $commentdata;
                    }

                    // Max file size (e.g., 2MB)
                    if ( $_FILES['review_image']['size'] > 2 * 1024 * 1024 ) {
                        // Handle file too large
                        return $commentdata;
                    }
                    
                    // WordPress upload overrides
                    $upload_overrides = array( 'test_form' => false );
                    
                    // Handle the upload
                    // Note: `wp_handle_upload` is lower level. `media_handle_upload` is preferred as it creates an attachment.
                    // `media_handle_upload` requires the post ID to associate the attachment.
                    // We don't have comment ID yet in `preprocess_comment`.
                    // So, we'll upload it and store the file path, then create attachment in `save_review_image_meta`.

                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );

                    // To use media_handle_upload, we need a post ID. Let's use the product ID.
                    $product_id = absint( $_POST['comment_post_ID'] );
                    
                    // `media_handle_upload` needs the first argument to be the key in $_FILES.
                    $attachment_id = media_handle_upload( 'review_image', $product_id );

                    if ( is_wp_error( $attachment_id ) ) {
                        // Handle failed upload to media library
                        // e.g., error_log( 'Failed to upload review image: ' . $attachment_id->get_error_message() );
                    } else {
                        // Store the attachment ID temporarily to be saved with the comment meta later
                        // We need a way to pass this to the 'comment_post' hook.
                        // A static variable or a transient could work. A static variable is simpler for this scope.
                        self::$uploaded_image_attachment_id = $attachment_id;
                    }
                }
            }
            return $commentdata;
        }
        
        // Temporary storage for attachment ID between preprocess_comment and comment_post
        private static $uploaded_image_attachment_id = null;

        /**
         * Save the review image attachment ID as comment meta.
         *
         * @param int   $comment_id The ID of the comment being posted.
         * @param mixed $comment_approved Approval status.
         */
        public function save_review_image_meta( $comment_id, $comment_approved ) {
            if ( $comment_approved && self::$uploaded_image_attachment_id !== null ) {
                update_comment_meta( $comment_id, '_review_image_id', self::$uploaded_image_attachment_id );
                // Reset static variable
                self::$uploaded_image_attachment_id = null; 
            }
        }

        /**
         * Display the uploaded image in the review.
         *
         * @param object $comment The comment object.
         */
        public function display_review_image( $comment ) {
            $image_id = get_comment_meta( $comment->comment_ID, '_review_image_id', true );
            if ( $image_id ) {
                // Display the image - 'thumbnail', 'medium', 'large', or custom size
                $image_html = wp_get_attachment_image( $image_id, 'medium' ); 
                if ( $image_html ) {
                    echo '<div class="review-image-attachment" style="margin-top: 10px;">' . $image_html . '</div>';
                }
            }
        }
        
        /**
         * Enqueue scripts and styles. (Placeholder)
         */
        // public function enqueue_scripts() {
            // wp_enqueue_style( 'wcri-style', WCRI_PLUGIN_URL . 'assets/css/style.css', array(), '1.0.0' );
            // wp_enqueue_script( 'wcri-script', WCRI_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), '1.0.0', true );
        // }

    } // END class WC_Review_Images

    // Instantiate the plugin class.
    WC_Review_Images::get_instance();

} // END if ( ! class_exists( 'WC_Review_Images' ) )
?>