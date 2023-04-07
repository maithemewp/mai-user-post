<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Renovator profile edit class.
 */
class Mai_User_Post_Edit_Form {
	protected $post_id;
	protected $listener;
	protected $keys;

	/**
	 * Constructs the class.
	 *
	 * @return void
	 */
	function __construct( $post_id = 0 ) {
		$this->post_id = (int) $post_id;

		if ( ! $this->post_id ) {
			return;
		}

		$this->listener = new Mai_User_Post_Edit_Listener();
		$this->keys     = maitowne_get_agent_keys();
		$this->hooks();
	}

	/**
	 * Display the form.
	 *
	 * @param string $redirect The redirect url/value.
	 *
	 * @return void
	 */
	function render( $redirect = '' ) {
		printf( '<h2>%s %s - <a href="%s">%s</a></h2>', __( 'Edit', 'maitowne' ), get_the_title( $this->post_id ), get_permalink( $this->post_id ), __( 'View', 'mai-user-post' ) );

		$args = [
			'fields'             => array_keys( $this->keys ),
			'post_id'            => $this->post_id,
			'submit_value'       => __( 'Save Changes', 'maitowne' ),
			'updated_message'    => __( 'Changes saved successfully.', 'maitowne' ),
			'html_submit_button' => '<input type="submit" class="button" value="%s" />',
		];

		if ( $redirect ) {
			$args['return'] = esc_url( $redirect );
		}

		acf_form( $args );
	}

	/**
	 * Runs hooks.
	 *
	 * @return void
	 */
	function hooks() {
		// Header stuff.
		add_action( 'get_header',         [ $this, 'get_form_head' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_enqueue_scripts', 'wp_enqueue_media' ); // Allows WP uploader.
		// Translate.
		add_filter( 'gettext',            [ $this, 'translate_text' ], 10, 3 );

		// Bail if not editing an existing agent post.
		if ( ! $this->post_id ) {
			return;
		}

		// Field filters.
		add_filter( 'acf/load_value/name=_thumbnail_id', [ $this, 'load_thumbnail' ], 10, 3 );
		add_filter( 'acf/load_value/name=post_title',    [ $this, 'load_title' ], 10, 3 );
		add_filter( 'acf/load_value/name=post_excerpt',  [ $this, 'load_excerpt' ], 10, 3 );
		add_filter( 'acf/load_value/name=post_content',  [ $this, 'load_content' ], 10, 3 );
	}

	/**
	 * Gets header code.
	 *
	 * @return void
	 */
	function get_form_head() {
		acf_form_head();
	}

	/**
	 * Enqueues CSS files.
	 *
	 * @return void
	 */
	function enqueue() {
		maitowne_enqueue_css( 'maitowne-agent-edit', '/assets/css/agent-edit.css', MAITOWNE_ENGINE_VERSION );
	}

	/**
	 * Translates text strings.
	 * Removes "No image selected" text from Gallery/Image fields.
	 *
	 * @param   string $translated_text
	 * @param   string $text
	 * @param   string $domain
	 *
	 * @return  string
	 */
	function translate_text( $translated_text, $text, $domain ) {
		if ( 'acf' !== $domain ) {
			return $translated_text;
		}

		switch ( $translated_text ) {
			case 'No image selected':
				$translated_text = '';
			break;
		}

		return $translated_text;
	}

	/**
	 * Sets featured image field to actual featured image.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID where the value is saved.
	 * @param array      $field   The field array containing all settings.
	 *
	 * @return string
	 */
	function load_thumbnail( $value, $post_id, $field ) {
		$image_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );
		return maitowne_get_avatar_fallback() !== $image_id ? $image_id : 0;
	}

	/**
	 * Sets post title field to actual post title.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID where the value is saved.
	 * @param array      $field   The field array containing all settings.
	 *
	 * @return string
	 */
	function load_title( $value, $post_id, $field ) {
		return (string) get_post_field( 'post_title', get_post( $post_id ) );
	}

	/**
	 * Sets post excerpt field to actual post excerpt.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID where the value is saved.
	 * @param array      $field   The field array containing all settings.
	 *
	 * @return string
	 */
	function load_excerpt( $value, $post_id, $field ) {
		return (string) get_post_field( 'post_excerpt', get_post( $post_id ) );
	}

	/**
	 * Sets post content field to actual post content.
	 *
	 * @param mixed      $value   The field value.
	 * @param int|string $post_id The post ID where the value is saved.
	 * @param array      $field   The field array containing all settings.
	 *
	 * @return string
	 */
	function load_content( $value, $post_id, $field ) {
		return (string) get_post_field( 'post_content', get_post( $post_id ) );
	}
}
