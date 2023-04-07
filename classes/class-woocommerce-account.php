<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * WooCommerce Account.
 */
class Mai_User_Post_WooCommerce_Account {
	protected $endpoint;
	protected $menu;
	protected $post_id;
	protected $user_id;
	protected $form;

	/**
	 * Constructs the class.
	 *
	 * @return void
	 */
	function __construct() {
		$this->endpoint = sanitize_title_with_dashes( sprintf( 'maiup-%s', maiup_get_option( 'singular' ) ) );
		$this->menu     = esc_html( maiup_get_option( 'woocommerce_menu' ) );
		$this->menu     = $this->menu ?: maiup_get_option( 'singular' ) . ' ' . __( 'Details', 'mai-user-post' );
		$this->form     = null;

		$this->hooks();
	}

	/**
	 * Runs hooks.
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'init',                                           [ $this, 'add_endpoint' ] );
		add_action( 'template_redirect',                              [ $this, 'setup_form' ] );
		add_filter( 'woocommerce_get_query_vars',                     [ $this, 'add_query_vars' ], 0 );
		add_filter( 'woocommerce_account_menu_items',                 [ $this, 'add_link' ] );
		add_action( "woocommerce_account_{$this->endpoint}_endpoint", [ $this, 'add_content' ] );
		// add_action( 'woocommerce_account_dashboard',                  [ $this, 'add_dashboard_button' ] );
	}

	/**
	 * Registers permalink endpoint.
	 *
	 * @return void
	 */
	function add_endpoint() {
		add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Sets up form object.
	 * This needs to run early because MaiTowne_Agent_Edit_Form enqueues styles.
	 *
	 * @return void
	 */
	function setup_form() {
		if ( ! is_wc_endpoint_url( $this->endpoint ) ) {
			return;
		}

		// Redirect if no access.
		if ( ! $this->has_access() ) {
			wp_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
			exit();
		}

		$post          = maiup_get_user_post();
		$this->post_id = $post ? $post->ID : 0;
		$this->user_id = get_current_user_id();

		if ( ! maiup_has_role( $this->user_id ) ) {
			$user = get_user_by( 'ID', $this->user_id );
			$user->add_role( 'agent' );
		}

		if ( ! $this->post_id ) {
			$this->post_id = maiup_create_user_post( $this->user_id,
				[
					'post_status' => 'draft',
				]
			);
		}

		if ( ! $this->post_id ) {
			return;
		}

		$this->form = new Mai_User_Post_Edit_Form( $this->post_id );
	}


	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[ $this->endpoint ] = $this->endpoint;

		return $vars;
	}

	/**
	 * Adds new menu link.
	 *
	 * @param array $menu_links
	 *
	 * @return array
	 */
	function add_link( $menu_links ) {
		if ( ! $this->has_access() ) {
			return $menu_links;
		}

		// Insert after Dashboard.
		$menu_links = $this->insert_after( $menu_links, 'dashboard', [ $this->endpoint => $this->menu ] );

		return $menu_links;
	}

	/**
	 * Adds menu item content.
	 *
	 * @return void
	 */
	function add_content() {
		$this->form->render();
	}

	/**
	 * Insert a value or key/value pair after a specific key in an array.
	 * If key doesn't exist, value is appended to the end of the array.
	 *
	 * @param array  $array
	 * @param string $key
	 * @param array  $new
	 *
	 * @return array
	 */
	function insert_after( array $array, $key, array $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys );
		$pos   = false === $index ? count( $array ) : $index + 1;

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}

	/**
	 * If current user has access to the menu item and content.
	 *
	 * @return bool
	 */
	function has_access() {
		$has_access = maiup_has_role();

		if ( ! $has_access && is_user_logged_in() ) {
			$user_id     = get_current_user_id();
			$product_ids = maitowne_post_get_product_ids();

			foreach ( $product_ids as $product_id ) {
				if ( ! wcs_user_has_subscription( $user_id, $product_id, 'active' ) ) {
					continue;
				}

				$has_access = true;
				break;
			}
		}

		return $has_access;
	}
}
