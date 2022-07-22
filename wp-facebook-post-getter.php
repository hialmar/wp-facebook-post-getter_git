<?php
/**
 * Plugin Name: WP Facebook Post Getter
 * Plugin URI:  https://torguet.net
 * Description: Import Facebook Posts With a Single Click.
 * Author:      Patrice Torguet
 * Author URI:  https://torguet.net
 * Version:     1.0.0
 * License:     GPL
 * Text Domain: wp-facebook-post-getter
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------
// Define Plugin Folders Path
// ---------------------------------------------------------
define( "WPFPG_PLUGIN_PATH", plugin_dir_path( __FILE__ ) );
define( "WPFPG_PLUGIN_URL", plugin_dir_url( __FILE__ ) );

require_once WPFPG_PLUGIN_PATH . 'includes/settings.php';

/**
 * Main Class
 */
class WP_FACEBOOK_POST_GETTER
{
    /**
     * Load Plugin Files & Add Hooks
     */
    public function __construct()
    {
    	add_action( 'init', array( $this, 'init' ) );

    	add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    /**
    * 
    */
    public function init()
    {
    	add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
    }

    /**
    * Add Plugin Settings page to wp menu dashboard
    */
    public function add_menu_page()
    {
		add_menu_page( 'Facebook Import', 'Facebook Import', 'manage_options' , 'wp-facebook-post-getter', array( $this, 'wp_faceook_post_getter' ), 'dashicons-menu' );
    }

    /**
    * Facebook Post Creating Form
    */
    public function wp_faceook_post_getter()
    {
        // get the custom post types
		$post_types = $this->get_custom_post_types();

        // manage the form data if it got posted
        manage_post($created_posts,$wpfpg_message, $wpfpg_show_message);

        $pluginlog = WPFPG_PLUGIN_URL.'/includes/debug.log';

        // the form
?>

		<div class="wrap">
			<h2>Get Facebook Posts</h2>
			<?php if ( isset( $wpfpg_show_message ) ) : ?>
				<div class="notice notice-<?php echo $wpfpg_show_message; ?> is-dismissible posts_create_success_failure_message">
					<p><?php echo $wpfpg_message; ?></p>
				</div>
				<?php $i = 0; ?>
				<div class="notice notice-success is-dismissible" style="padding-right: 10px;">
					<h2>Created Posts :</h2>
					<div class="container-fluid">
						<?php foreach ( $created_posts as $id ) : $i++; ?>
							<div class="row" style="border-bottom: 1px solid lightgrey;">
							  	<p class="col-md-11" style="line-height: 28px;"><?php echo esc_html( get_the_title( $id ) ); ?></p>
							  		
							  	<p class="col-md-1" style="text-align: right;"><a href="<?php echo get_the_permalink($id); ?>" class='button' style="margin-right: 10px;">View</a><a href="<?php echo get_edit_post_link( $id ); ?>" class='button'>Edit</a></p>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

			<?php endif; ?>

			<form action="" method="post">
	    		<div class="form-group">
		    		<p class="col-form-label facebook_key">Facebook key</p>
					<input type=text class="form-control" name="wpfpg_facebook_key" id="wpfpg_facebook_key">
				</div>
                <div class="form-group">
                    <p class="col-form-label facebook_key">Start Date: </p>
                    <input type=text class="form-control" name="wpfpg_date_since" id="wpfpg_datepicker_since" value="01 June 2022">
                    <p class="col-form-label facebook_key">Stop Date: </p>
                    <input type=text class="form-control" name="wpfpg_date_until" id="wpfpg_datepicker_until" value="30 June 2022">
                    <p class="col-form-label facebook_key">Max Number of Posts:</p>
                    <input type=text class="form-control" name="wpfpg_max_posts" id="wpfpg_max_posts" value="20">
                    <p class="col-form-label facebook_key">Filter:</p>
                    <input type=text class="form-control" name="wpfpg_filter" id="wpfpg_filter" value="coronavirus">
                </div>

				<div class="form-group">
					<div class="row">
						<div class="col">
		    				<p class="col-form-label insert_note">Post Type</p>
							<select name="wpfpg_new_post_type" id="wpfpg_new_post_type" class="form-control">
								<?php foreach ( $post_types as $post_type ) : ?>
									<option value="<?php echo $post_type; ?>"><?php echo ucfirst( $post_type ) ?></option>
								<?php endforeach ?>
							</select>
						</div>
						<div class="col">
			    			<p class="col-form-label insert_note">Post Status</p>
					      	<select name="wpfpg_new_post_status" id="wpfpg_new_post_status" class="form-control">
					      		<!-- <option value="" disabled selected style="display:none"></option> -->
					      		<option value="publish">Publish</option>
					      		<option value="draft">Draft</option>
					      		<option value="pending">Pending</option>
					      		<option value="private">Private</option>
					      	</select>
					    </div>

					    <div class="col">
			    			<p class="col-form-label insert_note">Post Author</p>
					      	<select name="wpfpg_new_post_author" id="wpfpg_new_post_author" class="form-control">
					      		<!-- <option value="" disabled selected style="display:none"></option> -->
					      		<?php

					      			$users = get_users( 'who=authors' );
					      			
					      			foreach ( $users as $user ) {
									   
									   echo '<option value="'.$user->ID.'">'.ucwords( str_replace( "_", " ", $user->user_login ) ).'</option>';
									}
					      		?>
					      	</select>
					    </div>

					    <div class="col">
			    			<p class="col-form-label insert_note">Post Category (<small>Posts Only)</small></p>
					      	<select name="wpfpg_new_post_category[]" id="wpfpg_new_post_category" class="form-control" multiple>
					      		<?php
					      			foreach ( get_categories() as $category ) {
									   
									   echo '<option value="'.$category->term_id.'">'.ucwords( str_replace( "_", " ", $category->cat_name ) ).'</option>';
									}
					      		?>
					      	</select>
					    </div>
		  			</div>
				</div>
				<?php wp_nonce_field( 'wpfpg_create_posts', 'wpfpg_nonce' ); ?>

				<button type="submit" class="button" name="wpfpg_submit_for_create_posts" id="wpfpg_submit_for_create_posts">Get Posts</button>
			</form>
		</div>

        <a href="<?php echo "$pluginlog"; ?>">log file</a>

	<?php }

    /**
    * Enqueue JS & CSS Files
    */
    public function admin_enqueue_scripts()
    {
		wp_enqueue_style ( 'wpfpg_bootstrap_css', WPFPG_PLUGIN_URL . '/assets/css/bootstrap.css', false );
		
		wp_enqueue_style ( 'wpfpgselect2_css',    WPFPG_PLUGIN_URL . 'assets/css/select2.min.css', true );
		
		wp_enqueue_style ( 'wpfpg_style_css', 	  WPFPG_PLUGIN_URL . '/assets/css/style.css', false );
		
		wp_enqueue_script( 'wpfpgselect2_js', 	  WPFPG_PLUGIN_URL . 'assets/js/select2.min.js', true );
		
		wp_enqueue_script( 'wpfpg_popper_js',     WPFPG_PLUGIN_URL . '/assets/js/popper.min.js', true );
		
		wp_enqueue_script( 'wpfpg_bootstrap_js',  WPFPG_PLUGIN_URL . '/assets/js/bootstrap.min.js', array( 'jquery', 'wpfpg_popper_js' ), true );
		
		wp_enqueue_script( 'wpfpg_linedtextarea', WPFPG_PLUGIN_URL . '/assets/js/linedtextarea.js', array( 'jquery' ), true );
		
		wp_enqueue_script( 'wpfpg_script', 		  WPFPG_PLUGIN_URL . '/assets/js/script.js', array( 'jquery' ), true );

        // Load the datepicker script (pre-registered in WordPress).
        wp_enqueue_script( 'jquery-ui-datepicker' );

        // You need styling for the datepicker. For simplicity I've linked to the jQuery UI CSS on a CDN.
        wp_register_style( 'jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css' );
        wp_enqueue_style( 'jquery-ui' );
    }

	/**
    * Get All Custom Post Types Registered By Others
    */
	function get_custom_post_types()
	{
		$all_post_types = array( 'post', 'page' );

		$args = array(
	       'public'   => true,
	       '_builtin' => false,
	    );

	    $output = 'names'; // names or objects, note names is the default
	    
	    $operator = 'and'; // 'and' or 'or'

	    $post_types = get_post_types( $args, $output, $operator );

	    if ( ! empty( $post_types ) )
	    {
	    	$all_post_types = array_merge( $all_post_types, $post_types );
	    }
	    
	    return $all_post_types;
	}
}

$WP_FACEBOOK_POST_GETTER = new WP_FACEBOOK_POST_GETTER();
