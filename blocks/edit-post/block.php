<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The user post edit form block.
 *
 * @since TBD
 */
class Mai_User_Post_Edit_Block {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'acf/init',                                [ $this, 'register_block' ] );
		add_action( 'acf/init',                                [ $this, 'register_field_group' ] );
		add_filter( 'acf/prepare_field/key=maiup_edit_fields', [ $this, 'prepare_field_choices' ] );
		add_action( 'enqueue_block_editor_assets',             [ $this, 'enqueue_sortable' ] );
		add_action( 'wp_enqueue_scripts',                      [ $this, 'enqueue' ] );
		add_action( 'get_header',                              [ $this, 'add_form_head' ], 0 );
	}

	/**
	 * Registers block.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_block() {
		register_block_type( __DIR__ . '/block.json',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Callback function to render the block.
	 *
	 * @since TBD
	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content The block content.
	 * @param bool     $is_preview Whether or not the block is being rendered for editing preview.
	 * @param int      $post_id The current post being edited or viewed.
	 * @param WP_Block $wp_block The block instance (since WP 5.5).
	 * @param array    $context The block context array.
	 *
	 * @return void
	 */
	function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
		// Get it started.
		$html = '';
		$args = [
			'fields'      => array_filter( (array) get_field( 'fields' ) ),
			'redirect'    => (string) get_field( 'redirect' ),
			'button_text' => (string) get_field( 'button_text' ),
			'class'       => isset( $attributes['className'] ) && ! empty( $attributes['className'] ) ? $attributes['className'] : '',
			'preview'     => $is_preview,
		];

		// Bail if no fields.
		if ( ! $args['fields'] ) {
			return $html;
		}

		// Sanitize.
		$args['button_text'] = sanitize_text_field( $args['button_text'] );

		// Get single name and group data.
		$singular     = maiup_get_singular();
		$group_fields = acf_get_fields( 'maiup_field_group' );
		$group_fields = wp_list_pluck( $group_fields, 'label', 'key' );

		// If preview or in admin. Sometimes is_preview was showing false in the editor. Hmmm.
		if ( $args['preview'] ) {
			$html .= '<div class="maiup-form-preview" style="pointer-events:none;padding:36px;border:2px dashed rgba(0,0,0,0.1);">';
				$html .= sprintf( '<p style="font-size:1.25em;"><strong>%s %s</strong></p>', $singular, __( 'Edit Form', 'mai-user-post' ) );

				foreach ( $args['fields'] as $field ) {
					if ( ! isset( $group_fields[ $field ] ) ) {
						continue;
					}

					$html .= sprintf( '<p><label>%s</label><input type="text" placeholder="%s"></p>', $group_fields[ $field ], __( 'Placeholder field', 'mai-user-post' ) );
				}

				$html .= '<p><button class="button">' . $args['button_text'] . '</button></p>';

			$html .= '</div>';

			echo $html;
			return;
		}

		// Bail if not logged in.
		$user_id = get_current_user_id();

		// Bail if not a role we should sync.
		if ( ! ( $user_id && maiup_has_role( $user_id ) ) ) {
			return;
		}

		// Get user post.
		$post_id = maiup_get_user_post_id();

		// Bail if no user ID.
		if ( ! $post_id ) {
			return;
		}

		// Form args.
		$form_args = [
			'id'                => 'maiup-form',
			'post_id'           => $post_id,
			'fields'            => $args['fields'],
			'submit_value'      => $args['button_text'] ?: __( 'Update', 'mai-user-post' ),
			'updated_message'   => sprintf( __( '%s updated.', 'mai-user-post' ), $singular ),
			'uploader'          => 'basic',
			// 'uploader'          => 'wp', // Not working, needs capabilities.
			'html_after_fields' => '',
		];

		// If redirect is set, add it to the form args.
		if ( $args['redirect'] ) {
			$form_args['return'] = esc_url( $args['redirect'] );
		}

		// Add filter.
		$form_args = apply_filters( 'maiup_edit_form_args', $form_args );

		// Open wrapper.
		printf( '<div class="maiup-form%s">', $args['class'] ? ' ' . esc_attr( $args['class'] ) : '' );

			// Store form.
			ob_start();
			acf_form( $form_args );
			$form = ob_get_clean();

			// Mai Engine.
			if ( class_exists( 'Mai_Engine' ) && $form ) {
				// Set up tag processor.
				$tags = new WP_HTML_Tag_Processor( $form );

				// Loop through buttons.
				while ( $tags->next_tag( [ 'tag_name' => 'a', 'class_name' => 'acf-button' ] ) ) {
					$tags->remove_class( 'button-primary' );
					$tags->add_class( 'button-secondary' );
					$tags->add_class( 'button-small' );
				}

				// Update form.
				$form = $tags->get_updated_html();

				// Set up tag processor.
				$tags = new WP_HTML_Tag_Processor( $form );

				// Loop through buttons.
				while ( $tags->next_tag( [ 'tag_name' => 'input', 'class_name' => 'acf-button' ] ) ) {
					$tags->remove_class( 'button-primary' );
					$tags->remove_class( 'button-large' );
				}

				// Update form.
				$form = $tags->get_updated_html();
			}

			// Output form.
			echo $form;

		// Close wrapper.
		echo '</div>';
	}

	/**
	 * Add field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_field_group() {
		acf_add_local_field_group(
			[
				'title'  => __( 'Mai User Post Edit', 'mai-user-post' ),
				'key'    => 'maiup_edit_field_group',
				'fields' => [
					[
						'label'        => __( 'Redirect', 'mai-user-post' ),
						'instructions' => __( 'Redirect to this URL after submission.', 'mai-user-post' ),
						'key'          => 'maiup_post_edit_redirect',
						'name'         => 'redirect',
						'type'         => 'text',
					],
					[
						'label'         => __( 'Button Text', 'mai-user-post' ),
						'default_value' => __( 'Update', 'mai-user-post' ),
						'key'           => 'maiup_post_edit_button_text',
						'name'          => 'button_text',
						'type'          => 'text',
					],
					[
						'label'         => __( 'Form Fields', 'mai-user-post' ),
						'instructions'  => __( 'Allow editing of these fields.', 'mai-user-post' ),
						'key'           => 'maiup_edit_fields',
						'name'          => 'fields',
						'type'          => 'checkbox',
						'multiple'      => 1,
						'allow_null'    => 0,
						'ui'            => 1,
						'ajax'          => 1,
						'choices'       => [],
						'default_value' => [],
						'wrapper'       => [
							'class' => 'maiup-sortable',
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-user-post-edit',
						],
					],
				],
			]
		);
	}

	/**
	 * Make sure the field choices are in the correct order, based on existing values.
	 *
	 * @since TBD
	 *
	 * @param array $field The field data.
	 *
	 * @return array
	 */
	function prepare_field_choices( $field ) {
		// Get currently selected fields, so they are first. Combine so we can use the keys as values.
		$field['choices'] = array_combine( (array) $field['value'], (array) $field['value'] );
		// $field['choices'] = array_merge( $field['choices'], maiup_get_acf_keys() );
		// $field['choices'] = array_filter( $field['choices'] );

		// Get all register fields.
		$fields = acf_get_fields( 'maiup_field_group' );

		// Set choices.
		foreach ( $fields as $values ) {
			// Skip tabs.
			if ( 'tab' === $values['type'] ) {
				continue;
			}

			// Skip if already set.
			if ( isset( $field['choices'][ $values['key'] ] ) ) {
				continue;
			}

			// Adds as new choice or overrides existing and adds label.
			$field['choices'][ $values['key'] ] = $values['label'];
		}

		// ACF field keys.
		$keys = maiup_get_acf_keys();

		// Loop through keys and add to choices.
		foreach ( $keys as $key => $name ) {
			// Skip if already set.
			if ( isset( $field['choices'][ $key ] ) ) {
				continue;
			}

			$object                   = get_field_object( $key );
			$field['choices'][ $key ] = $object['label'];
		}

		// Remove empty choices.
		$field['choices'] = array_filter( $field['choices'] );

		// Set basic defaults.
		$field['default_value'] = [
			'maiup_image',
			'maiup_title',
			'maiup_excerpt',
			'maiup_content',
		];

		return $field;
	}

	/**
	 * Add sortable scripts and styles.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function enqueue_sortable() {
		wp_enqueue_script( 'maiup-sortable', MAI_USER_POST_PLUGIN_URL . 'assets/js/maiup-sortable.js', [ 'jquery', 'jquery-ui-sortable', 'acf-input' ], MAI_USER_POST_VERSION, true );
		wp_enqueue_style( 'maiup-sortable', MAI_USER_POST_PLUGIN_URL . 'assets/css/maiup-sortable.css', [], MAI_USER_POST_VERSION );
	}

	/**
	 * Enqueues CSS files.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function enqueue() {
		// Bail if no form.
		if ( ! $this->has_form() ) {
			return;
		}

		maiup_enqueue_css( 'maiup-form', '/assets/css/maiup-form.css', MAI_USER_POST_VERSION );
	}

	/**
	 * Processes create form submission.
	 * Adds acf_form_head().
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_form_head() {
		// Bail if no form.
		if ( ! $this->has_form() ) {
			return;
		}

		// Add form head.
		acf_form_head();
	}

	/**
	 * Checks if the current post has the form block.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	function has_form() {
		static $cache = false;

		// Bail if not logged in or not a single post.
		if ( ! ( is_user_logged_in() && is_singular() ) ) {
			return $cache;
		}

		// Check if has block.
		$cache = has_blocks() && has_block( 'acf/mai-user-post-edit' );

		return $cache;
	}
}