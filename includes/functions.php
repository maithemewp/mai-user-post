<?php

/**
 * Run the hooks.
 *
 * @since 0.1.0
 *
 * @return
 */
add_action( 'user_register',        'maiup_sync_user_post', 99 );
add_action( 'profile_update',       'maiup_sync_user_post', 99 );
add_action( 'wp_after_insert_post', 'maiup_sync_post_user', 99, 4 );
add_action( 'delete_user',          'maiup_delete_user_post' );

/**
 * Gets meta keys to sync.
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
 * Gets ACF meta keys to sync.
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
 * Gets mapped keys to sync.
 * Useful when keys are different from user to post meta.
 * Array of user key => post key.
 *
 * [
 *   'user_image_id' => '_thumbnail_id',
 * ]
 *
 * @since 0.1.0
 *
 * @return array
 */
function maiup_get_mapped_keys() {
	static $mapped_keys = null;

	if ( ! is_null( $mapped_keys ) ) {
		return $mapped_keys;
	}

	$mapped_keys = apply_filters( 'maiup_mapped_keys', [] );

	return (array) $mapped_keys;
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
		$user_meta = get_userdata( $user_id );
		$has_role  = (bool) array_intersect( $user_meta->roles, $user_roles );

		if ( ! $has_role ) {
			return;
		}
	}

	$should_sync = apply_filters( 'maiup_should_sync', true, $user_id );

	if ( ! $should_sync ) {
		return;
	}

	$post_id = 0;
	$post    = maiup_get_user_post( $user_id );

	if ( $post ) {
		$post_id = $post->ID;
	} else {
		$post_id = maiup_create_user_post( $user_id );

		if ( $post_id ) {
			$post = get_post( $post_id );
		}
	}

	if ( ! ( $post_id && $post ) ) {
		return;
	}

	$user = get_user_by( 'id', $user_id );

	remove_action( 'wp_after_insert_post', 'maiup_sync_post_user', 99, 4 );

	$post_args = [
		'ID'           => $post_id,
		'post_title'   => $user->display_name,
		'post_excerpt' => $user->description,
	];

	$content = get_user_meta( $user_id, 'post_content', true );

	if ( $content && ( $post->post_content !== $content ) ) {
		$post_args['post_content'] = $content;
	}

	wp_update_post( $post_args );

	add_action( 'wp_after_insert_post', 'maiup_sync_post_user', 99, 4 );

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

	$mapped_keys = maiup_get_mapped_keys();

	if ( $mapped_keys ) {
		foreach ( $mapped_keys as $user_key => $post_key ) {
			$user_meta = get_user_meta( $user_id, $user_key, true );
			$post_meta = get_post_meta( $post_id, $post_key, true );

			if ( $user_meta === $post_meta ) {
				continue;
			}

			update_post_meta( $post_id, $post_key, $user_meta );
		}
	}

	do_action( 'maiup_user_updated', $user_id, $post_id );
}

/**
 * Syncs user post to the user
 * when a user post is updated.
 *
 * @since 0.1.0
 *
 * @param int          $post_id     Post ID.
 * @param WP_Post      $post        Post object.
 * @param bool         $update      Whether this is an existing post being updated.
 * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior
 *                                  to the update for updated posts.
 *
 * @return void
 */
function maiup_sync_post_user( $post_id, $post, $update, $post_before ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! $update ) {
		return;
	}

	$user_id = get_post_meta( $post_id, 'mai_user_id', true );

	if ( ! $user_id ) {
		return;
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return;
	}

	$user_roles = maiup_get_user_roles();

	if ( $user_roles ) {
		$user_meta = get_userdata( $user_id );
		$has_role  = (bool) array_intersect( $user_meta->roles, $user_roles );

		if ( ! $has_role ) {
			return;
		}
	}

	$should_sync = apply_filters( 'maiup_should_sync', true, $user_id );

	if ( ! $should_sync ) {
		return;
	}

	$post = get_post( $post_id );

	remove_action( 'user_register', 'maiup_sync_user_post', 99 );
	remove_action( 'profile_update', 'maiup_sync_user_post', 99 );

	wp_update_user(
		[
			'ID'           => $user_id,
			'user_url'     => get_post_meta( $post_id, 'user_url', true ),
			'display_name' => $post->post_title,
			'description'  => $post->post_excerpt,
		]
	);

	add_action( 'user_register', 'maiup_sync_user_post', 99 );
	add_action( 'profile_update', 'maiup_sync_user_post', 99 );

	$content = get_user_meta( $user_id, 'post_content', true );

	if ( $post->post_content && ( $post->post_content !== $content ) ) {
		update_user_meta( $user_id, 'post_content', $post->post_content );
	}

	$meta_keys = maiup_get_meta_keys();

	if ( $meta_keys ) {
		foreach ( $meta_keys as $key ) {
			$post_meta = get_post_meta( $post_id, $key, true );
			$user_meta = get_user_meta( $user_id, $key, true );

			if ( $post_meta === $user_meta ) {
				continue;
			}

			update_user_meta( $user_id, $key, $post_meta );
		}
	}

	$acf_keys = maiup_get_acf_keys();

	if ( $acf_keys && function_exists( 'get_field' ) && function_exists( 'update_field' ) ) {
		foreach ( $acf_keys as $key ) {
			$post_meta = get_field( $key, $post_id );
			$user_meta = get_field( $key, 'user_' .  $user_id );

			if ( $post_meta === $user_meta ) {
				continue;
			}

			update_field( $key, $post_meta, 'user_' .  $user_id );
		}
	}

	$mapped_keys = maiup_get_mapped_keys();

	if ( $mapped_keys ) {
		foreach ( $mapped_keys as $user_key => $post_key ) {
			$post_meta = get_post_meta( $post_id, $post_key, true );
			$user_meta = get_user_meta( $user_id, $user_key, true );

			if ( $post_meta === $user_meta ) {
				continue;
			}

			update_user_meta( $user_id, $user_key, $post_meta );
		}
	}

	do_action( 'maiup_user_post_updated', $user_id, $post_id );
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

		$args = [
			'post_type'    => 'mai_user',
			'post_author'  => $user_id,
			'post_excerpt' => $user->description,
			'post_status'  => 'publish',
			'post_title'   => $user->display_name,
			'meta_input'   => [
				'mai_user_id' => absint( $user_id ),
			],
		];

		$args = apply_filters( 'maiup_user_post_args', $args, $user_id );

		$post_id = wp_insert_post( $args );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			do_action( 'maiup_user_post_created', $user_id, $post_id );

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
	$post  = null;
	$query = new WP_Query(
		[
			'post_type'              => 'mai_user',
			'meta_key'               => 'mai_user_id',
			'meta_value'             => $user_id,
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]
	);
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) : $query->the_post();
			$post = get_post( get_the_ID() );
			break;
		endwhile;
	}
	wp_reset_postdata();
	return $post;
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
