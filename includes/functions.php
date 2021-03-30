<?php

/**
 * Run the hooks.
 *
 * @since 0.1.0
 *
 * @return
 */
add_action( 'user_register',  'maiup_sync_user_post', 99 );
add_action( 'profile_update', 'maiup_sync_user_post', 99 );
add_action( 'delete_user',    'maiup_delete_user_post' );

/**
 * Get meta keys to sync.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maiup_get_meta_keys() {
	static $meta_keys = null;

	if ( ! is_null( $meta_keys ) ) {
		return $meta_keys;
	}

	$meta_keys = apply_filters( 'maiup_meta_keys', [] );

	return (array) $meta_keys;
}

/**
 * Gets ACF keys to sync.
 *
 * @since 0.1.0
 *
 * @return array
 */
function maiup_get_acf_keys() {
	static $acf_keys = null;

	if ( ! is_null( $acf_keys ) ) {
		return $acf_keys;
	}

	$acf_keys = apply_filters( 'maiup_acf_keys', [] );

	return (array) $acf_keys;
}

/**
 * Syncs user data to the user post
 * when a user is registered or profile is updated.
 *
 * @since 0.1.0
 *
 * @param int $user_id The user ID.
 *
 * @return void
 */
function maiup_sync_user_post( $user_id ) {
	$user_roles = maiup_get_user_roles();

	if ( $user_roles ) {
		$user_meta  = get_userdata( $user_id );
		$has_role   = (bool) array_intersect( $user_meta->roles, $user_roles );

		if ( ! $has_role ) {
			return;
		}
	}

	$should_sync = apply_filters( 'maiup_should_sync', true, $user_id );

	if ( ! $should_sync ) {
		return;
	}

	$post = maiup_get_user_post( $user_id );

	if ( $post ) {
		$post_id = $post->ID;
	} else {
		$post_id = maiup_create_user_post( $user_id );

		if ( ! $post_id ) {
			return;
		}
	}

	$user = get_user_by( 'id', $user_id );

	wp_update_post(
		[
			'ID'           => $post_id,
			'post_title'   => $user->display_name,
			'post_content' => $user->description,
		]
	);

	$meta_keys = maiup_get_meta_keys();

	if ( $meta_keys ) {
		foreach ( $meta_keys as $key ) {
			$user_meta = get_user_meta( $user_id, $key, true );
			$post_meta = get_post_meta( $post_id, $key, true );

			if ( $user_meta === $post_meta ) {
				continue;
			}

			update_post_meta( $post_id, $key, $user_meta );
		}
	}

	$acf_keys = maiup_get_acf_keys();

	if ( $acf_keys && function_exists( 'get_field' ) && function_exists( 'update_field' ) ) {
		foreach ( $acf_keys as $key ) {
			$user_meta = get_field( $key, 'user_' .  $user_id );
			$post_meta = get_field( $key, $post_id );

			if ( $user_meta === $post_meta ) {
				continue;
			}

			update_field( $key, $user_meta, $post_id );
		}
	}

	do_action( 'maiup_user_updated', $user_id, $post_id );
}


/**
 * Creates a user post if it doesn't exist.
 *
 * @since 0.1.0
 *
 * @param int $user_id The user ID.
 *
 * @return int|false
 */
function maiup_create_user_post( $user_id ) {
	$post = maiup_get_user_post( $user_id );

	if ( $post ) {
		return $post->ID;
	}

	$user = get_user_by( 'id', $user_id );

	if ( $user ) {

		$args = apply_filters( 'maiup_user_post_args', [
			'post_type'    => 'mai_user',
			'post_author'  => $user_id,
			'post_content' => $user->description,
			'post_name'    => maiup_get_user_slug( $user_id ),
			'post_status'  => 'publish',
			'post_title'   => $user->display_name,
		] );

		$post_id = wp_insert_post( $args );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			return $post_id;
		}
	}

	return false;
}

/**
 * Deletes a user post when a user is deleted.
 *
 * @param int $user_id The user ID.
 *
 * @return void
 */
function maiup_delete_user_post( $user_id ) {
	$post = maiup_get_user_post( $user_id );

	if ( $post ) {
		wp_delete_post( $post->ID, true );
	}
}


/**
 * Gets a user post by user ID.
 *
 * @since 0.1.0
 *
 * @param int $user_id The user ID.
 *
 * @return WP_User|null
 */
function maiup_get_user_post( $user_id ) {
	return get_page_by_path( maiup_get_user_slug( $user_id ), OBJECT, 'mai_user' );
}

/**
 * Gets a user slug by user ID.
 *
 * @since 0.1.0
 *
 * @param int $user_id The user ID.
 *
 * @return string
 */
function maiup_get_user_slug( $user_id ) {
	return sprintf( 'user-%s', $user_id );
}

/**
 * Gets the roles to sync.
 *
 * @since 0.1.0
 *
 * @return string
 */
function maiup_get_user_roles() {
	static $user_roles = null;

	if ( ! is_null( $user_roles ) ) {
		return $user_roles;
	}

	$user_roles = (array) apply_filters( 'maiup_user_roles', [] );

	return $user_roles;
}
