# Mai User Post
A developer plugin to connect and sync a user to an individual custom post type entry.

This plugin requires PHP filters in order to use. See below.

## Enable user roles
`maiup_user_roles`

In order for any user and user entry to be created and/or synced you need to enable a user role or roles via the following filter.

```
/**
 * Enable user roles.
 *
 * @param array $roles
 *
 * @return array
 */
add_filter( 'maiup_user_roles', function( $roles ) {
	return [ 'author', 'editor' ];
});
```

## Sync data
By default, Mai User Post creates and syncs the following data both ways.

| User                |    Post |
|---------------------|--------:|
| Display Name        |   Title |
| Biography           | Excerpt |
| `post_content` meta | Content |

```
// Filter args.
$args = apply_filters( 'maiup_user_post_args', $args, $user_id );
```

## Add sync data
`maiup_meta_keys`

Add default meta keys to be synced between users and the user post.

The following example syncs WooCommerce billing address/info to the user post.

```
/**
 * Enables meta keys to sync from user to post meta.
 *
 * @param array $meta_keys The meta key names.
 *
 * @return array
 */
add_filter( 'maiup_meta_keys', function( $meta_keys ) {
	$meta_keys = array_merge( $meta_keys, [
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_phone',
		'billing_email',
	] );

	return $meta_keys;
});
```

`maiup_acf_keys`

Add ACF meta keys to sync from user to post meta. Uses `get_field` and `update_field`, which is much easier for complex fields like repeaters.

```
/**
 * Enables ACF meta keys to sync from user to post meta.
 *
 * @uses Advanced Custom Fields.
 *
 * @param array $meta_keys The meta key names.
 *
 * @return array
 */
add_filter( 'maiup_acf_keys', function( $meta_keys ) {
	$meta_keys[] = 'location';       // Google Map field name.
	$meta_keys[] = 'some_repeater'; // Repeater field name.
	return $meta_keys;
});
```

`maiup_mapped_keys`

Enables different meta keys to sync from user to post meta. Useful when keys are different from user to post meta.
Returns an array of `'user_key_name' => 'post_key_name'`.

The following example syncs an image field that stores the image ID with the post featured image.

```
/**
 * Enables different meta keys to sync from user to post meta.
 *
 * @param array $meta_keys Array of `'user_key_name' => 'post_key_name'`.
 *
 * @return array
 */
add_filter( 'maiup_mapped_keys', function( $meta_keys ) {
	$meta_keys[ 'featured_image' ] = '_thumbnail_id';
	return $meta_keys;
});
```

## Change default sync data
`maiup_user_post_args`

Change the post data just before `wp_update_post`. Useful to save different data as the post title.

The following example uses WooCommerce `billing_company` as the user post title.

```
/**
 * Uses user data as user post data.
 *
 * @param array $args    The post args.
 * @param int   $user_id The user ID.
 *
 * @return array
 */
add_filter( 'maiup_user_post_args', function( $args, $user_id ) {
	$company = get_user_meta( $user_id, 'billing_company', true );
	if ( $company ) {
		$args['post_title'] = $company;
	}
	return $args;
});
```

## Post Type Args
**Basic post type filters**

```
$plural   = apply_filters( 'maiup_post_type_plural', __( 'User Posts', 'mai-user-post' ) );
$singular = apply_filters( 'maiup_post_type_singular', __( 'User Post', 'mai-user-post' ) );
$base     = apply_filters( 'maiup_post_type_base', 'users' );
```

```
/**
 * Change Mai User Post type plural label.
 *
 * @param string $label
 *
 * @return string
 */
add_filter( 'maiup_post_type_plural', function( $label ) {
	return __( 'Stores', 'textdomain' );
});
```

```
/**
 * Change Mai User Post type singular label.
 *
 * @param string $label
 *
 * @return string
 */
add_filter( 'maiup_post_type_singular', function( $label ) {
	return __( 'Store', 'textdomain' );
});
```

```
/**
 * Change Mai User Post type base url.
 *
 * @param string $base
 *
 * @return string
 */
add_filter( 'maiup_post_type_base', function( $base ) {
	return 'stores';
});
```

**Full post type args filter**
`mai_user_post_type_args`

```
$args = apply_filters( 'mai_user_post_type_args', $args );
```
