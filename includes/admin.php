<?php

add_action( 'admin_menu', 'maiup_add_sync_page' );
/**
 * Add the sync page.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiup_add_sync_page() {
	add_submenu_page(
		'edit.php?post_type=mai_user',
		__( 'Mai User Post Sync', 'mai-user-post' ),
		__( 'Bulk Sync', 'mai-user-post' ),
		'manage_options',
		'mai_user_post',
		'maiup_settings_page',
		99
	);
}

/**
 * Build the Settings page.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiup_settings_page() {
	echo '<div class="wrap">';
		printf( '<h2>%s</h2>', __( 'Mai User Post Sync', 'mai-user-post' ) );

		$notice = filter_input( INPUT_GET, 'maiup_notice', FILTER_SANITIZE_STRING );

		if ( $notice ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $notice ) );
		}

		$roles = maiup_get_user_roles();

		if ( $roles ) {
			printf( '<p>%s <strong>%s</strong></p>', __( 'The following user role(s) will be synced:', 'mai-user-post' ), implode( ', ', $roles ) );
		} else {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', __( 'There are no user roles set to be synced.', 'mai-user-post' ) );
			printf( '<p>%s</p>', __( 'To enable user roles to be synced, please use the <code>maiup_user_roles</code> filter.', 'mai-user-post' ) );
		}

		if ( $roles ) {

			$sync_url = add_query_arg(
				[
					'action' => 'mai_user_post_sync_action',
					'offset' => absint( filter_input( INPUT_GET, 'offset', FILTER_SANITIZE_NUMBER_INT ) ),
				],
				admin_url( 'admin-post.php' )
			);

			$text     = __( 'Sync Users to User Posts', 'mai-user-post' );
			$classes  = 'button button-primary';
			$continue = absint( filter_input( INPUT_GET, 'continue', FILTER_SANITIZE_NUMBER_INT ) );

			if ( $continue ) {
				$text     = __( 'Continue Syncing Users', 'mai-user-post' );
				$classes  = 'button';
				$sync_url = add_query_arg( 'continue', $continue, $sync_url );
			}

			$sync_url = wp_nonce_url( $sync_url, 'mai_user_post_sync_action', 'mai_user_post_sync_nonce' );

			printf( '<p><a class="%s" href="%s">%s</a></p>', $classes, $sync_url, $text );
		}

	echo '</div>';
}

add_action( 'admin_post_mai_user_post_sync_action', 'mai_user_post_sync_action' );
/**
 * Listener for syncing user posts.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mai_user_post_sync_action() {
	$referrer = check_admin_referer( 'mai_user_post_sync_action', 'mai_user_post_sync_nonce' );
	$nonce    = filter_input( INPUT_GET, 'mai_user_post_sync_nonce', FILTER_SANITIZE_STRING );
	$action   = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
	$offset   = absint( filter_input( INPUT_GET, 'offset', FILTER_SANITIZE_NUMBER_INT ) );

	if ( current_user_can( 'manage_options' ) && $referrer && $nonce && $action && wp_verify_nonce( $nonce, $action ) ) {

		$redirect   = admin_url( 'edit.php?post_type=mai_user&page=mai_user_post' );
		$number     = 250;
		$user_roles = maiup_get_user_roles();
		$args       = [
			'number' => $number,
			'offset' => $offset,
		];

		if ( $user_roles ) {
			$args['role__in'] = $user_roles;
		}

		$args  = apply_filters( 'maiup_user_query_args', $args );
		$users = new WP_User_Query( $args );
		$users = $users->get_results();

		if ( $users ) {
			foreach ( $users as $user ) {
				maiup_sync_user_post( $user->ID );
			}

			$notice   = sprintf( '%s %s', count( $users ), __( 'users synced. Click button to continue.', 'mai-user-post' ) );
			$redirect = add_query_arg(
				[
					'offset'       => $offset + $number,
					'continue'     => 1,
					'maiup_notice' => urlencode( $notice ),
				],
				$redirect
			);

		} else {

			$notice   = __( 'All users synced!' );
			$redirect = add_query_arg(
				[
					'offset'       => 0,
					'continue'     => 0,
					'maiup_notice' => urlencode( $notice ),
				],
				$redirect
			);
		}

		wp_safe_redirect( $redirect );
		exit;

	} else {
		wp_die(
			__( 'User posts failed to sync.', 'mai-user-post' ),
			__( 'Error', 'mai-user-post' ), [
				'link_url'  => admin_url( 'edit.php?post_type=mai_user&page=mai_user_post' ),
				'link_text' => __( 'Go back.', 'mai-user-post' ),
			]
		);
	}
}
