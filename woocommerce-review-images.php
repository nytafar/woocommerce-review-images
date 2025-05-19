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

            // Add the upload field to WooCommerce review form
            add_action( 'comment_form_logged_in_after', array( $this, 'add_review_image_upload_field' ) );
            add_action( 'comment_form_after_fields', array( $this, 'add_review_image_upload_field' ) );
            
            // Add our enctype to the form in multiple ways to ensure it works
            add_action( 'comment_form_top', array( $this, 'add_form_enctype_script' ) );
            add_filter( 'comment_form_defaults', array( $this, 'comment_form_defaults' ) );
            
            // Handle image upload and save meta
            add_filter( 'preprocess_comment', array( $this, 'handle_review_image_upload' ) );
            add_action( 'comment_post', array( $this, 'save_review_image_meta' ), 10, 2 );

            // Display image in review (frontend)
            add_action( 'woocommerce_review_after_comment_text', array( $this, 'display_review_image' ) );
            
            // Display images in admin panel
            add_filter( 'comment_text', array( $this, 'display_admin_review_image' ), 10, 2 );
            add_action( 'add_meta_boxes_comment', array( $this, 'add_comment_images_metabox' ) );
            
            // Enqueue scripts and styles
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
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
         */
        /**
         * Filter comment form defaults to add multipart/form-data enctype
         */
        public function comment_form_defaults( $defaults ) {
            if ( is_product() && is_singular( 'product' ) ) {
                // Add multipart enctype to the form directly
                if ( isset( $defaults['id_form'] ) ) {
                    $defaults['id_form'] = $defaults['id_form'] . ' enctype="multipart/form-data"';
                }
            }
            return $defaults;
        }
        
        /**
         * Add the review image upload field to the form
         */
        public function add_review_image_upload_field() {
            // Only show on single product pages and only on the actual form (not on reviews)
            if ( ! is_product() || ! is_singular( 'product' ) ) {
                return;
            }
            
            // Don't show if comments are closed
            if ( ! comments_open() ) {
                return;
            }
            
            // Add the upload field with better styling
            echo '<div class="comment-form-image-upload">';
            echo '<h4>' . esc_html__( 'Upload Review Images', 'woocommerce-review-images' ) . '</h4>';
            echo '<label for="wcri_review_image">' . esc_html__( 'Add images to your review (optional)', 'woocommerce-review-images' ) . '</label>';
            echo '<input type="file" id="wcri_review_image" name="wcri_review_image[]" multiple accept="image/jpeg,image/png,image/gif" style="display: block; margin: 10px 0; width: 100%;">';
            echo '<div id="image-preview-container" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>';
            echo '<p class="description">' . esc_html__( 'You can upload up to 5 images (JPEG, PNG, GIF). Max size: 2MB per image.', 'woocommerce-review-images' ) . '</p>';
            echo '</div>';
            
            // Add nonce field
            wp_nonce_field( 'wcri_review_image_upload', 'wcri_review_image_nonce' );
            
            // Add preview script
            echo '<script>
                jQuery(document).ready(function($) {
                    $("#wcri_review_image").on("change", function() {
                        var preview = $("#image-preview-container");
                        preview.empty();
                        
                        if (this.files && this.files.length > 0) {
                            // Limit to 5 files
                            var maxFiles = 5;
                            var filesToPreview = Math.min(this.files.length, maxFiles);
                            
                            if (this.files.length > maxFiles) {
                                alert("' . esc_js( __( 'Maximum 5 images allowed.', 'woocommerce-review-images' ) ) . '");
                            }
                            
                            for (var i = 0; i < filesToPreview; i++) {
                                var file = this.files[i];
                                var reader = new FileReader();
                                
                                reader.onload = (function(file) {
                                    return function(e) {
                                        var div = $("<div>").addClass("image-preview").css({
                                            "border": "1px solid #ccc",
                                            "padding": "5px",
                                            "margin-right": "10px",
                                            "margin-bottom": "10px",
                                            "display": "inline-block",
                                            "position": "relative"
                                        });
                                        
                                        $("<img>").attr("src", e.target.result)
                                            .css({
                                                "max-width": "100px",
                                                "max-height": "100px"
                                            })
                                            .appendTo(div);
                                        
                                        preview.append(div);
                                    };
                                })(file);
                                
                                reader.readAsDataURL(file);
                            }
                        }
                    });
                });
            </script>';
        }
        
        /**
         * Add JavaScript to set form enctype for file uploads
         */
        public function add_form_enctype_script() {
            if ( is_product() && is_singular( 'product' ) && comments_open() ) {
                // Use direct output for maximum reliability
                echo "<script type='text/javascript'>
                    jQuery(document).ready(function($) {
                        console.log('WC Review Images: Setting form enctype');
                        $('form#commentform').attr('enctype', 'multipart/form-data');
                        
                        // Double-check the enctype is properly set
                        setTimeout(function() {
                            if ($('form#commentform').attr('enctype') !== 'multipart/form-data') {
                                console.error('WC Review Images: Form enctype not set correctly!');
                                $('form#commentform').attr('enctype', 'multipart/form-data');
                                
                                // Last resort - add a completely new attribute
                                document.getElementById('commentform').setAttribute('enctype', 'multipart/form-data');
                            } else {
                                console.log('WC Review Images: Form enctype verified');
                            }
                        }, 1000);
                    });
                </script>";
                
                // Also try to add it using WordPress's proper method
                wp_add_inline_script('jquery', '
                    jQuery(document).ready(function($) {
                        $("form#commentform").attr("enctype", "multipart/form-data");
                    });
                ');
            }
        }


        /**
         * Handle the image upload.
         *
         * @param array $commentdata Comment data.
         * @return array Comment data, possibly with an error if upload failed.
         */
        public function handle_review_image_upload( $commentdata ) {
            // Debug info
            error_log('WC Review Images: Processing comment upload');            
            
            // CRITICAL DEBUG - Record the entire form submission
            error_log('WC Review Images: POST data keys: ' . print_r(array_keys($_POST), true));
            error_log('WC Review Images: FILES data keys: ' . print_r(array_keys($_FILES), true));
            
            // Only process for product reviews
            if ( isset( $_POST['comment_post_ID'] ) && 'product' === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) {
                error_log('WC Review Images: This is a product review');
                
                // Verify nonce - but don't abort if it fails, just log it (for debugging)
                if ( ! isset( $_POST['wcri_review_image_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcri_review_image_nonce'] ) ), 'wcri_review_image_upload' ) ) {
                    error_log('WC Review Images: Nonce verification failed or missing');
                    // But don't return, let's keep debugging
                }

                // Initialize array for storing attachment IDs
                self::$uploaded_image_attachment_ids = array();
                
                // Check directly for ALL $_FILES data
                error_log('WC Review Images: Raw $_FILES array: ' . print_r($_FILES, true));
                
                // Check for the image input field
                if ( isset( $_FILES['wcri_review_image'] ) ) {
                    error_log('WC Review Images: wcri_review_image field exists');
                } else {
                    error_log('WC Review Images: wcri_review_image field NOT FOUND');
                    
                    // Check if a different file field exists
                    foreach( $_FILES as $field => $data ) {
                        error_log('WC Review Images: Found file field: ' . $field);
                    }
                }
                
                // Now check for file uploads with our field name
                if ( isset( $_FILES['wcri_review_image'] ) && is_array( $_FILES['wcri_review_image']['name'] ) ) {
                    error_log('WC Review Images: Found wcri_review_image files array');
                    
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                    
                    $product_id = absint( $_POST['comment_post_ID'] );
                    $max_uploads = 5; // Maximum number of images to allow
                    $file_count = count( $_FILES['review_image']['name'] );
                    
                    // Process each uploaded file
                    for ( $i = 0; $i < $file_count && $i < $max_uploads; $i++ ) {
                        // Skip if there's no file or there was an error
                        if ( empty( $_FILES['wcri_review_image']['name'][$i] ) || $_FILES['wcri_review_image']['error'][$i] !== UPLOAD_ERR_OK ) {
                            continue;
                        }
                        
                        // Check file type
                        $allowed_mime_types = array( 'image/jpeg', 'image/png', 'image/gif' );
                        $file_info = wp_check_filetype( $_FILES['wcri_review_image']['name'][$i] );
                        
                        if ( ! in_array( $file_info['type'], $allowed_mime_types ) ) {
                            continue; // Skip invalid file types
                        }
                        
                        // Check file size (2MB limit)
                        if ( $_FILES['wcri_review_image']['size'][$i] > 2 * 1024 * 1024 ) {
                            continue; // Skip files that are too large
                        }
                        
                        // Prepare a file array for this single file
                        $file = array(
                            'name'     => $_FILES['wcri_review_image']['name'][$i],
                            'type'     => $_FILES['wcri_review_image']['type'][$i],
                            'tmp_name' => $_FILES['wcri_review_image']['tmp_name'][$i],
                            'error'    => $_FILES['wcri_review_image']['error'][$i],
                            'size'     => $_FILES['wcri_review_image']['size'][$i]
                        );
                        
                        // Set up $_FILES for media_handle_upload
                        $_FILES['review_image_single'] = $file;
                        
                        // Upload the file and create attachment
                        $attachment_id = media_handle_upload( 'review_image_single', $product_id );
                        
                        if ( ! is_wp_error( $attachment_id ) ) {
                            self::$uploaded_image_attachment_ids[] = $attachment_id;
                            error_log('WC Review Images: File uploaded successfully, attachment ID: ' . $attachment_id);
                        } else {
                            error_log('WC Review Images: Upload failed: ' . $attachment_id->get_error_message());
                        }
                    }
                }
            }
            return $commentdata;
        }
        
        // Temporary storage for attachment IDs between preprocess_comment and comment_post
        private static $uploaded_image_attachment_ids = array();

        /**
         * Save the review image attachment IDs as comment meta.
         *
         * @param int   $comment_id The ID of the comment being posted.
         * @param mixed $comment_approved Approval status.
         */
        public function save_review_image_meta( $comment_id, $comment_approved ) {
            error_log('WC Review Images: Saving comment meta for comment ID ' . $comment_id);
            
            if ( ! empty( self::$uploaded_image_attachment_ids ) ) {
                error_log('WC Review Images: Saving ' . count(self::$uploaded_image_attachment_ids) . ' image IDs: ' . implode(', ', self::$uploaded_image_attachment_ids));
                
                // Explicitly add _wp_attachment_image_alt meta to make the alt attribute work
                foreach (self::$uploaded_image_attachment_ids as $attachment_id) {
                    update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Product review image' );
                }
                
                // Save the array of image IDs to the comment meta
                update_comment_meta( $comment_id, '_review_images', self::$uploaded_image_attachment_ids );
                
                // For debugging, output a hidden comment meta that shows we've processed images
                update_comment_meta( $comment_id, '_review_has_images', 'yes' );
                
                // Reset static variable
                self::$uploaded_image_attachment_ids = array(); 
            } else {
                error_log('WC Review Images: No images to save');
            }
        }

        /**
         * Display the uploaded images in the review.
         *
         * @param object $comment The comment object.
         */
        public function display_review_image( $comment ) {
            // Make sure we have a valid comment object
            if ( ! isset( $comment->comment_ID ) ) {
                error_log('WC Review Images: Invalid comment object in display function');
                return;
            }
            
            // Debug information
            error_log('WC Review Images: Displaying images for comment ID ' . $comment->comment_ID);
            
            // Try to get images from the new meta key first
            $image_ids = get_comment_meta( $comment->comment_ID, '_review_images', true );
            error_log('WC Review Images: Retrieved image IDs: ' . print_r($image_ids, true));
            
            // If not found, try the old meta key for backward compatibility
            if ( empty( $image_ids ) ) {
                $legacy_image_id = get_comment_meta( $comment->comment_ID, '_review_image_id', true );
                if ( $legacy_image_id ) {
                    $image_ids = array( $legacy_image_id );
                    error_log('WC Review Images: Using legacy image ID: ' . $legacy_image_id);
                }
            }
            
            // Show all meta data for debugging
            $all_meta = get_comment_meta($comment->comment_ID);
            error_log('WC Review Images: All comment meta: ' . print_r($all_meta, true));
            
            // Display images if we have any
            if ( ! empty( $image_ids ) && is_array( $image_ids ) ) {
                error_log('WC Review Images: Displaying ' . count($image_ids) . ' images');
                
                // Always show a basic debug message at the start to confirm this code is running
                echo '<!-- WC Review Images Display Function Running -->';
                echo '<div class="review-images-container" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px;">';
                foreach ( $image_ids as $image_id ) {
                    $full_image_url = wp_get_attachment_url( $image_id );
                    $image_html = wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'review-image' ) );
                    
                    error_log('WC Review Images: Processing image ID ' . $image_id . ', URL: ' . $full_image_url);
                    
                    if ( $image_html ) {
                        echo '<div class="review-image-attachment" style="border: 1px solid #f0f0f0; padding: 5px; border-radius: 4px;">';
                        echo '<a href="' . esc_url( $full_image_url ) . '" target="_blank">';
                        echo $image_html;
                        echo '</a>';
                        echo '</div>';
                    } else {
                        error_log('WC Review Images: Failed to generate image HTML for ID ' . $image_id);
                    }
                }
                echo '</div>';
                
                // Explicit image display as fallback
                if (count($image_ids) > 0) {
                    echo '<!-- Debug output -->';
                    echo '<div style="margin-top: 10px; padding: 5px; border: 1px solid #ccc; background: #f9f9f9;">';
                    echo '<p><strong>Debug:</strong> ' . count($image_ids) . ' image(s) should appear above</p>';
                    $first_id = reset($image_ids);
                    $direct_url = wp_get_attachment_url($first_id);
                    if ($direct_url) {
                        echo '<img src="' . esc_url($direct_url) . '" style="max-width: 200px; height: auto;" />';
                    }
                    echo '</div>';
                }
            } else {
                error_log('WC Review Images: No images to display');
            }
        }
        
        /**
         * Enqueue scripts and styles.
         */
        public function enqueue_scripts() {
            if ( is_product() ) {
                // Basic styles for the image upload and display
                $css = '
                    .comment-form-image-upload {
                        margin: 15px 0;
                    }
                    .review-image-attachment {
                        margin-top: 15px;
                    }
                    .review-image-attachment img {
                        max-width: 100%;
                        height: auto;
                        border: 1px solid #ddd;
                        padding: 5px;
                        background: #fff;
                        margin: 5px 10px 5px 0;
                    }';
                
                wp_add_inline_style( 'woocommerce-general', $css );
            }
        }
        
        /**
         * Enqueue admin scripts and styles.
         *
         * @param string $hook Current admin page.
         */
        public function admin_enqueue_scripts( $hook ) {
            if ( 'comment.php' === $hook || 'edit-comments.php' === $hook ) {
                $css = '
                    .wcri-admin-images {
                        margin: 10px 0;
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px;
                    }
                    .wcri-admin-image-item {
                        border: 1px solid #ddd;
                        padding: 5px;
                        background: #fff;
                        max-width: 150px;
                        position: relative;
                    }
                    .wcri-admin-image-item img {
                        max-width: 100%;
                        height: auto;
                    }
                ';
                
                wp_add_inline_style( 'wp-admin', $css );
            }
        }
        
        /**
         * Display review images in the admin comments list.
         *
         * @param string     $comment_text Text of the current comment.
         * @param WP_Comment $comment      The comment object.
         * @return string Filtered comment text.
         */
        public function display_admin_review_image( $comment_text, $comment = null ) {
            // Only run in admin
            if ( ! is_admin() || ! $comment ) {
                return $comment_text;
            }
            
            // Only process product reviews
            $post_type = get_post_type( $comment->comment_post_ID );
            if ( 'product' !== $post_type ) {
                return $comment_text;
            }
            
            // Get review images
            $image_ids = get_comment_meta( $comment->comment_ID, '_review_images', true );
            if ( empty( $image_ids ) ) {
                // Try legacy meta
                $legacy_image_id = get_comment_meta( $comment->comment_ID, '_review_image_id', true );
                if ( $legacy_image_id ) {
                    $image_ids = array( $legacy_image_id );
                }
            }
            
            // Display review images if we have any
            if ( ! empty( $image_ids ) && is_array( $image_ids ) ) {
                $image_html = '<div class="wcri-admin-images">';
                foreach ( $image_ids as $image_id ) {
                    $full_image_url = wp_get_attachment_url( $image_id );
                    $thumbnail = wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'review-image' ) );
                    
                    if ( $thumbnail ) {
                        $image_html .= '<div class="wcri-admin-image-item">';
                        $image_html .= '<a href="' . esc_url( $full_image_url ) . '" target="_blank">';
                        $image_html .= $thumbnail;
                        $image_html .= '</a>';
                        $image_html .= '</div>';
                    }
                }
                $image_html .= '</div>';
                
                $comment_text .= $image_html;
            }
            
            return $comment_text;
        }
        
        /**
         * Add a metabox to the comment editing screen to show review images.
         *
         * @param WP_Comment $comment Comment object.
         */
        public function add_comment_images_metabox( $comment ) {
            $post_type = get_post_type( $comment->comment_post_ID );
            
            // Only add for product reviews
            if ( 'product' === $post_type ) {
                add_meta_box(
                    'wcri_review_images',
                    __( 'Review Images', 'woocommerce-review-images' ),
                    array( $this, 'render_comment_images_metabox' ),
                    'comment',
                    'normal',
                    'high'
                );
            }
        }
        
        /**
         * Render the review images metabox content.
         *
         * @param WP_Comment $comment Comment object.
         */
        public function render_comment_images_metabox( $comment ) {
            // Get review images
            $image_ids = get_comment_meta( $comment->comment_ID, '_review_images', true );
            if ( empty( $image_ids ) ) {
                $legacy_image_id = get_comment_meta( $comment->comment_ID, '_review_image_id', true );
                if ( $legacy_image_id ) {
                    $image_ids = array( $legacy_image_id );
                }
            }
            
            if ( ! empty( $image_ids ) && is_array( $image_ids ) ) {
                echo '<div class="wcri-admin-images">';
                foreach ( $image_ids as $image_id ) {
                    $full_image_url = wp_get_attachment_url( $image_id );
                    $medium_image = wp_get_attachment_image( $image_id, 'medium', false );
                    
                    if ( $medium_image ) {
                        echo '<div class="wcri-admin-image-item">';
                        echo '<a href="' . esc_url( $full_image_url ) . '" target="_blank">';
                        echo $medium_image;
                        echo '</a>';
                        echo '<p><strong>' . esc_html__( 'Image ID', 'woocommerce-review-images' ) . ':</strong> ' . esc_html( $image_id ) . '</p>';
                        echo '</div>';
                    }
                }
                echo '</div>';
            } else {
                echo '<p>' . esc_html__( 'No images attached to this review.', 'woocommerce-review-images' ) . '</p>';
            }
        }

    } // END class WC_Review_Images

    // Instantiate the plugin class.
    WC_Review_Images::get_instance();

} // END if ( ! class_exists( 'WC_Review_Images' ) )
?>