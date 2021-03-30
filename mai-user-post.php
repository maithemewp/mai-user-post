<?php

/**
 * Plugin Name:     Mai User Post
 * Plugin URI:      https://bizbudding.com
 * Description:     Connect a user to a post type for front end display via Mai Post Grid.
 * Version:         0.1.0
 *
 * Author:          BizBudding, Mike Hemberger
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Mai_User_Post_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_User_Post_Plugin {

	/**
	 * @var   Mai_User_Post_Plugin The one true Mai_User_Post_Plugin
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_User_Post_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_User_Post_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_User_Post_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_User_Post_Plugin::includes() Include the required files.
	 * @uses    Mai_User_Post_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_User_Post_Plugin()
	 * @return  object | Mai_User_Post_Plugin The one true Mai_User_Post_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup.
			self::$instance = new Mai_User_Post_Plugin;
			// Methods.
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'textdomain' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'textdomain' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'MAI_USER_POST_VERSION' ) ) {
			define( 'MAI_USER_POST_VERSION', '0.1.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_USER_POST_PLUGIN_DIR' ) ) {
			define( 'MAI_USER_POST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Classes Path.
		// if ( ! defined( 'MAI_USER_POST_CLASSES_DIR' ) ) {
		// 	define( 'MAI_USER_POST_CLASSES_DIR', MAI_USER_POST_PLUGIN_DIR . 'classes/' );
		// }

		// Plugin Includes Path.
		if ( ! defined( 'MAI_USER_POST_INCLUDES_DIR' ) ) {
			define( 'MAI_USER_POST_INCLUDES_DIR', MAI_USER_POST_PLUGIN_DIR . 'includes/' );
		}

		// Plugin Folder URL.
		if ( ! defined( 'MAI_USER_POST_PLUGIN_URL' ) ) {
			define( 'MAI_USER_POST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'MAI_USER_POST_PLUGIN_FILE' ) ) {
			define( 'MAI_USER_POST_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'MAI_USER_POST_BASENAME' ) ) {
			define( 'MAI_USER_POST_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
		}

	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function includes() {
		// Include vendor libraries.
		// require_once __DIR__ . '/vendor/autoload.php';
		// Classes.
		// foreach ( glob( MAI_USER_POST_CLASSES_DIR . '*.php' ) as $file ) { include $file; }
		// Includes.
		foreach ( glob( MAI_USER_POST_INCLUDES_DIR . '*.php' ) as $file ) { include $file; }

	}

	/**
	 * Run the hooks.
	 *
	 * @since   0.1.0
	 * @return  void
	 */
	public function hooks() {

		add_action( 'admin_init',          [ $this, 'updater' ] );
		add_action( 'init',                [ $this, 'register_content_types' ] );
		// add_action( 'plugins_loaded',      [ $this, 'setup' ] );
		add_filter( 'mai_grid_post_types', [ $this, 'grid_post_types' ] );

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @since 0.1.0
	 *
	 * @uses https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return void
	 */
	public function updater() {
		// Bail if current user cannot manage plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'Puc_v4_Factory' ) ) {
			return;
		}

		// // Setup the updater.
		// $updater = Puc_v4_Factory::buildUpdateChecker( 'https://github.com/bizbudding/starter-plugin/', __FILE__, 'textdomain' );

		// // Maybe set github api token.
		// if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		// 	$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		// }

		// // Add icons for Dashboard > Updates screen.
		// if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
		// 	$updater->addResultFilter(
		// 		function ( $info ) use ( $icons ) {
		// 			$info->icons = $icons;
		// 			return $info;
		// 		}
		// 	);
		// }
	}

	/**
	 * Register content types.
	 *
	 * @return  void
	 */
	public function register_content_types() {

		/***********************
		 *  Custom Post Types  *
		 ***********************/

		register_post_type( 'mai_user', apply_filters( 'mai_user_post_type_args',
			[
				'exclude_from_search' => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'labels'              => [
					'name'               => _x( 'User Posts', 'User general name'        , 'mai-user-post' ),
					'singular_name'      => _x( 'User Post' , 'User singular name'       , 'mai-user-post' ),
					'menu_name'          => _x( 'User Posts', 'User admin menu'          , 'mai-user-post' ),
					'name_admin_bar'     => _x( 'User Post' , 'User add new on admin bar', 'mai-user-post' ),
					'add_new'            => _x( 'Add New'  , 'User add new'              , 'mai-user-post' ),
					'add_new_item'       => __( 'Add New User'                           , 'mai-user-post' ),
					'new_item'           => __( 'New User'                               , 'mai-user-post' ),
					'edit_item'          => __( 'Edit User'                              , 'mai-user-post' ),
					'view_item'          => __( 'View User'                              , 'mai-user-post' ),
					'all_items'          => __( 'All User Posts'                         , 'mai-user-post' ),
					'search_items'       => __( 'Search User Posts'                      , 'mai-user-post' ),
					'parent_item_colon'  => __( 'Parent User Posts:'                     , 'mai-user-post' ),
					'not_found'          => __( 'No User Posts found.'                   , 'mai-user-post' ),
					'not_found_in_trash' => __( 'No User Posts found in Trash.'          , 'mai-user-post' )
				],
				'menu_icon'          => 'dashicons-admin-users',
				'public'             => false,
				'publicly_queryable' => false,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				// 'show_in_rest'       => true,
				'show_ui'            => true,
				'rewrite'            => false,
				// 'supports'           => [ 'title', 'editor', 'author', 'page-attributes', 'thumbnail' ],
				'supports'           => [ 'title', 'editor', 'page-attributes', 'thumbnail' ],
			]
		) );
	}

	/**
	 * Setup Mai_User_Post class.
	 *
	 * @return object
	 */
	// function setup() {
	// 	return new Mai_User_Post;
	// }

	/**
	 * Add 'mai_user' as an available post type for Mai Post Grid.
	 *
	 * @param array $post_types The existing post types.
	 *
	 * @return array
	 */
	function grid_post_types( $post_types ) {
		$post_types[] = 'mai_user';
		return $post_types;
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate() {
		$this->register_content_types();
		flush_rewrite_rules();
	}
}

/**
 * The main function for that returns Mai_User_Post_Plugin
 *
 * The main function responsible for returning the one true Mai_User_Post_Plugin
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Mai_User_Post_Plugin(); ?>
 *
 * @since 0.1.0
 *
 * @return object|Mai_User_Post_Plugin The one true Mai_User_Post_Plugin Instance.
 */
function Mai_User_Post_Plugin() {
	return Mai_User_Post_Plugin::instance();
}

// Get Mai_User_Post_Plugin Running.
Mai_User_Post_Plugin();
