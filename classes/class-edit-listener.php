<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Renovator profile edit class.
 */
class Mai_User_Post_Edit_Listener {
	/**
	 * Constructs the class.
	 *
	 * @return void
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Runs hooks.
	 *
	 * @return void
	 */
	function hooks() {
		// Form listener.
		add_filter( 'acf/pre_save_post', [ $this, 'save_post' ] );
	}

	/**
	 * Saves post data to post.
	 *
	 * @param mixed $post_id The post ID.
	 *
	 * @return mixed
	 */
	function save_post( $post_id ) {
		$data = isset( $_POST['acf'] ) && ! empty( $_POST['acf'] ) ? $_POST['acf'] : [];

		// Bail if no data.
		if ( ! array_values( $data ) ) {
			return $post_id;
		}

		// Bail if no post ID.
		if ( ! $post_id ) {
			return $post_id;
		}

		$update = false;
		$args   = [
			'ID' => $post_id,
		];

		// Set vars.
		$keys   = maitowne_get_agent_keys();
		$update = [
			'post_title',
			'post_excerpt',
			'post_content',
		];

		foreach ( $update as $key ) {
			if ( ! isset( $keys[ $key ] ) || ! isset( $data[ $keys[ $key ] ] ) ) {
				continue;
			}

			// Add to post args.
			$args[ $key ] = wp_kses_post( $data[ $keys[ $key ] ] );

			// Unset key so it doesn't save to meta.
			unset( $data[ $keys[ $key ] ] );

			// We need to update.
			$update = true;
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			$args['post_status'] =  'publish';

			// We need to update.
			$update = true;
		}

		// Bail if not updat
		if ( ! $update ) {
			return $post_id;
		}

		wp_update_post( $args );

		return $post_id;
	}
}