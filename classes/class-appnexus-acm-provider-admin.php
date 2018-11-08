<?php
/**
 * Class file for the Appnexus_ACM_Provider_Admin class.
 *
 * @file
 */

if ( ! class_exists( 'Appnexus_ACM_Provider' ) ) {
	die();
}

/**
 * Create default WordPress admin functionality to configure the plugin.
 */
class Appnexus_ACM_Provider_Admin {

	protected $option_prefix;
	protected $version;
	protected $slug;
	protected $capability;
	protected $ad_panel;
	protected $front_end;

	/**
	* Constructor which sets up admin pages
	*
	* @param string $option_prefix
	* @param string $version
	* @param string $slug
	* @param string $capability
	* @param object $ad_panel
	* @param object $front_end
	* @throws \Exception
	*/
	public function __construct( $option_prefix, $version, $slug, $capability, $ad_panel, $front_end ) {

		$this->option_prefix = $option_prefix;
		$this->version       = $version;
		$this->slug          = $slug;
		$this->capability    = $capability;
		$this->ad_panel      = $ad_panel;
		$this->front_end     = $front_end;

		//$this->mc_form_transients = $this->wordpress->mc_form_transients;

		$this->tabs = $this->get_admin_tabs();

		$this->add_actions();

	}

	/**
	* Create the action hooks to create the admin page(s)
	*
	*/
	public function add_actions() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_and_styles' ) );
			add_action( 'admin_init', array( $this, 'admin_settings_form' ) );
			add_action( 'admin_init', array( $this, 'setup_tinymce_plugin' ) );
		}

	}

	/**
	* Default display for <input> fields
	*
	* @param array $args
	*/
	public function create_admin_menu() {
		add_options_page( __( 'AppNexus Ad Settings', 'appnexus-acm-provider' ), __( 'AppNexus Ad Settings', 'appnexus-acm-provider' ), $this->capability, 'appnexus-acm-provider', array( $this, 'show_admin_page' ) );
	}

	/**
	* Admin styles. Load the CSS and/or JavaScript for the plugin's settings
	*
	* @return void
	*/
	public function admin_scripts_and_styles() {
		wp_enqueue_script( $this->slug . '-admin', plugins_url( 'assets/js/' . $this->slug . '-admin.min.js', dirname( __FILE__ ) ), array( 'jquery' ), $this->version, true );
		//wp_enqueue_style( $this->slug . '-admin', plugins_url( 'assets/css/' . $this->slug . '-admin.min.css', dirname( __FILE__ ) ), array(), $this->version, 'all' );
	}

	private function get_admin_tabs() {
		$tabs = array(
			'appnexus_acm_settings' => 'AppNexus Settings',
			'embed_ads_settings'    => 'Embed Ads Settings',
		); // this creates the tabs for the admin
		return $tabs;
	}

	/**
	* Display the admin settings page
	*
	* @return void
	*/
	public function show_admin_page() {
		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		?>
		<div class="wrap">
			<h1><?php _e( get_admin_page_title() , 'appnexus-acm-provider' ); ?></h1>

			<?php
			$tabs = $this->tabs;
			$tab  = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'appnexus_acm_settings';
			$this->render_tabs( $tabs, $tab );

			switch ( $tab ) {
				case 'appnexus_acm_settings':
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
					break;
				case 'embed_ads_settings':
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
					break;
				default:
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
					break;
			} // End switch().
			?>
		</div>
		<?php
	}

	/**
	* Render tabs for settings pages in admin
	* @param array $tabs
	* @param string $tab
	*/
	private function render_tabs( $tabs, $tab = '' ) {

		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );

		$current_tab = $tab;
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab_key => $tab_caption ) {
			$active = $current_tab === $tab_key ? ' nav-tab-active' : '';
			echo sprintf( '<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				esc_attr( $active ),
				esc_url( '?page=' . $this->slug . '&tab=' . $tab_key ),
				esc_html( $tab_caption )
			);
			//}
		}
		echo '</h2>';

		if ( isset( $get_data['tab'] ) ) {
			$tab = sanitize_key( $get_data['tab'] );
		} else {
			$tab = '';
		}
	}

	/**
	* Register items for the settings api
	* @return void
	*
	*/
	public function admin_settings_form() {

		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		$page     = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'appnexus_acm_settings';
		$section  = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'appnexus_acm_settings';

		$input_callback_default    = array( $this, 'display_input_field' );
		$textarea_callback_default = array( $this, 'display_textarea' );
		$input_checkboxes_default  = array( $this, 'display_checkboxes' );
		$input_radio_default       = array( $this, 'display_radio' );
		$input_select_default      = array( $this, 'display_select' );
		$link_default              = array( $this, 'display_link' );

		$all_field_callbacks = array(
			'text'       => $input_callback_default,
			'textarea'   => $textarea_callback_default,
			'checkboxes' => $input_checkboxes_default,
			'radio'      => $input_radio_default,
			'select'     => $input_select_default,
			'link'       => $link_default,
		);

		$this->appnexus_acm_settings( 'appnexus_acm_settings', 'appnexus_acm_settings', $all_field_callbacks );
		$this->embed_ads_settings( 'embed_ads_settings', 'embed_ads_settings', $all_field_callbacks );

	}

	/**
	* Check if the current user can edit Posts or Pages, and is using the Visual Editor
	* If so, add some filters so we can register our plugin
	*/
	public function setup_tinymce_plugin() {

		// Check if the logged in WordPress User can edit Posts or Pages
		// If not, don't register our TinyMCE plugin

		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// Check if the logged in WordPress User has the Visual Editor enabled
		// If not, don't register our TinyMCE plugin
		if ( 'true' !== get_user_option( 'rich_editing' ) ) {
			return;
		}
		// Setup some filters
		add_filter( 'mce_external_plugins', array( $this, 'add_tinymce_plugin' ) );
		add_filter( 'mce_buttons', array( $this, 'add_tinymce_toolbar_button' ) );
	}

	/**
	* Adds a TinyMCE plugin compatible JS file to the TinyMCE / Visual Editor instance
	*
	* @param array $plugin_array Array of registered TinyMCE Plugins
	* @return array Modified array of registered TinyMCE Plugins
	*/
	public function add_tinymce_plugin( $plugin_array ) {
		$plugin_array['cms_ad'] = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/tinymce-cms-ad.min.js';
		return $plugin_array;
	}

	/**
	* Adds a button to the TinyMCE / Visual Editor which the user can click
	* to insert a link with a custom CSS class.
	*
	* @param array $buttons Array of registered TinyMCE Buttons
	* @return array Modified array of registered TinyMCE Buttons
	*/
	public function add_tinymce_toolbar_button( $buttons ) {
		array_push( $buttons, '|', 'cms_ad' );
		return $buttons;
	}


	/**
	* Fields for the Appnexus Settings tab
	* This runs add_settings_section once, as well as add_settings_field and register_setting methods for each option
	*
	* @param string $page
	* @param string $section
	* @param string $input_callback
	*/
	private function appnexus_acm_settings( $page, $section, $callbacks ) {
		$tabs = $this->tabs;
		foreach ( $tabs as $key => $value ) {
			if ( $key === $page ) {
				$title = $value;
			}
		}
		add_settings_section( $page, $title, null, $page );

		$settings = array(
			'default_domain'                => array(
				'title'    => __( 'Default domain', 'appnexus-acm-provider' ),
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type' => 'text',
					'desc' => __( 'Enter the ad server domain.', 'appnexus-acm-provider' ),
				),
			),
			'use_https'                     => array(
				'title'    => __( 'Use HTTPS?', 'appnexus-acm-provider' ),
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'    => 'checkbox',
					'desc'    => 'Choose whether to use HTTPS on the domain.',
					'default' => '1',
				),
			),
			'server_path'                   => array(
				'title'    => __( 'Server path', 'appnexus-acm-provider' ),
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type' => 'text',
					'desc' => '',
				),
			),
			'ad_tag_type'                   => array(
				'title'    => __( 'Ad tag type', 'appnexus-acm-provider' ),
				'callback' => $callbacks['select'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'  => 'select',
					'desc'  => '',
					'items' => array(
						'jx'  => array(
							'text'  => 'JX',
							'value' => 'jx',
						),
						'mjx' => array(
							'text'  => 'MJX',
							'value' => 'mjx',
						),
						'nx'  => array(
							'text'  => 'NX',
							'value' => 'nx',
						),
						'sx'  => array(
							'text'  => 'SX',
							'value' => 'sx',
						),
						'dx'  => array(
							'text'  => 'DX',
							'value' => 'dx',
						),
					),
				),
			),
			'tag_list'                      => array(
				'title'    => __( 'List tags', 'appnexus-acm-provider' ),
				'callback' => $callbacks['textarea'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'desc' => 'Enter comma separated list of tags.',
				),
			),
			'show_ads_without_conditionals' => array(
				'title'    => __( 'Show ads without conditionals', 'appnexus-acm-provider' ),
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'    => 'checkbox',
					'desc'    => 'If an ad has no conditionals, show it everywhere.',
					'default' => '1',
				),
			),
		);

		$settings['lazy_load_ads'] = array(
			'title'    => __( 'Lazy load all ads?', 'appnexus-acm-provider' ),
			'callback' => $callbacks['text'],
			'page'     => $page,
			'section'  => $section,
			'args'     => array(
				'type'    => 'checkbox',
				'desc'    => 'Load each ad when the user scrolls near it, regardless of its placement. You can also choose to lazy load only embed ads.',
				'default' => '0',
			),
		);

		foreach ( $settings as $key => $attributes ) {
			$id       = $this->option_prefix . $key;
			$name     = $this->option_prefix . $key;
			$title    = $attributes['title'];
			$callback = $attributes['callback'];
			$page     = $attributes['page'];
			$section  = $attributes['section'];
			$args     = array_merge(
				$attributes['args'],
				array(
					'title'     => $title,
					'id'        => $id,
					'label_for' => $id,
					'name'      => $name,
				)
			);
			add_settings_field( $id, $title, $callback, $page, $section, $args );
			register_setting( $section, $id );
		}
	}

	/**
	* Fields for the Embed Ads Settings tab
	* This runs add_settings_section once, as well as add_settings_field and register_setting methods for each option
	*
	* @param string $page
	* @param string $section
	* @param string $input_callback
	*/
	private function embed_ads_settings( $page, $section, $callbacks ) {
		$tabs = $this->tabs;
		foreach ( $tabs as $key => $value ) {
			if ( $key === $page ) {
				$title = $value;
			}
		}
		$multiple_embeds = array(
			'overall'      => 'Embed Ad Settings',
			'multiple_on'  => 'Multiple Embeds',
			'multiple_off' => 'Single Embed',
		);
		$settings        = array();

		foreach ( $multiple_embeds as $key => $value ) {
			$section = $section . '_' . $key;
			add_settings_section( $section, $value, null, $page );

			if ( 'overall' === $key ) {
				$embed_settings = array(
					'show_in_editor'                    => array(
						'title'    => __( 'Show shortcode in editor?', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'checkbox',
							'desc'    => 'If checked, the [cms_ad] shortcode(s) will show in the post editor, so it/they can be moved around the post as needed.',
							'default' => '',
						),
					),
					'post_types'                        => array(
						'title'    => __( 'Post types to embed ads', 'appnexus-acm-provider' ),
						'callback' => $callbacks['checkboxes'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'  => 'checkboxes',
							'desc'  => 'By default this will list all post types in your installation.',
							'items' => $this->post_type_options(),
						),
					),
					'multiple_embeds'                   => array(
						'title'    => __( 'Multiple embeds per story?', 'appnexus-acm-provider' ),
						'callback' => $callbacks['radio'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'  => 'select',
							'desc'  => '',
							'items' => array(
								'yes' => array(
									'text'    => 'yes',
									'value'   => '1',
									'id'      => 'yes',
									'desc'    => '',
									'default' => '',
								),
								'no'  => array(
									'text'    => 'no',
									'value'   => '0',
									'id'      => 'no',
									'desc'    => '',
									'default' => '',
								),
							),
						),
					),
					'prevent_ads_field'                 => array(
						'title'    => __( 'Meta field to prevent ads', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'desc'    => 'Add a wp_postmeta field name used to prevent all ads.',
							'default' => '_post_prevent_appnexus_ads',
						),
					),
					'prevent_ads_field_value'           => array(
						'title'    => __( 'Meta field value to prevent ads', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'desc'    => 'Add a wp_postmeta field value used to prevent all ads.',
							'default' => 'on',
						),
					),
					'prevent_automatic_ads_field'       => array(
						'title'    => __( 'Meta field to prevent automatic ads', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'desc'    => 'Add a wp_postmeta field name used to prevent automatic ads.',
							'default' => '_post_prevent_automatic_appnexus_ads',
						),
					),
					'prevent_automatic_ads_field_value' => array(
						'title'    => __( 'Meta field value to prevent automatic ads', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'desc'    => 'Add a wp_postmeta field value used to prevent automatic ads.',
							'default' => 'on',
						),
					),
				);
				$embed_settings['lazy_load_embeds'] = array(
					'title'    => __( 'Lazy load embed ads?', 'appnexus-acm-provider' ),
					'callback' => $callbacks['text'],
					'page'     => $page,
					'section'  => $section,
					'args'     => array(
						'type'    => 'checkbox',
						'desc'    => 'If checked, the ad inserter will lazy load embed ads, even if it is not set to lazy load all the other ads.',
						'default' => '',
					),
				);
			} elseif ( 'multiple_off' === $key ) {
				$embed_settings = array(
					'auto_embed_position'      => array(
						'title'    => __( 'Auto embed position', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type' => 'text',
							'desc' => __( 'Position for the in-story ad, if it is not otherwise included.', 'appnexus-acm-provider' ),
						),
					),
					'auto_embed_top_offset'    => array(
						'title'    => __( 'Auto embed top character offset', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type' => 'text',
							'desc' => __( 'How many characters from the top of the story to put the ad.', 'appnexus-acm-provider' ),
						),
					),
					'auto_embed_bottom_offset' => array(
						'title'    => __( 'Auto embed bottom character offset', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type' => 'text',
							'desc' => __( 'How many characters from the bottom of the story to put the ad.', 'appnexus-acm-provider' ),
						),
					),
				);
			} else {
				$embed_settings = array(
					'embed_prefix'            => array(
						'title'    => __( 'Embed tag prefix', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type' => 'text',
							'desc' => 'Embed tags start with this character.',
						),
					),
					'start_tag_id'            => array(
						'title'    => __( 'First embed tag ID', 'appnexus-acm-provider' ),
						'callback' => $callbacks['select'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'  => 'select',
							'desc'  => 'Pick the tag ID that starts the embed tags. The ad inserter will start here, and continue to the maximum number of embeds.',
							'items' => $this->embed_tag_options(),
						),
					),
					'insert_every_paragraphs' => array(
						'title'    => __( 'Number of paragraphs between each insertion', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'default' => '4',
							'desc'    => __( 'The ad inserter will wait this number of paragraphs after the start of the article, insert the first ad zone, count this many more paragraphs, insert the second ad zone, and so on.', 'appnexus-acm-provider' ),
						),
					),
					'end_tag_id'              => array(
						'title'    => __( 'Last embed tag ID', 'appnexus-acm-provider' ),
						'callback' => $callbacks['select'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'  => 'select',
							'desc'  => 'Starting with ' . get_option( $this->option_prefix . 'start_tag_id', 'the first tag ID' ) . ', pick the last tag ID that could display as an embed ad. How many actually display depends on how long the post is, and how often an ad should be displayed. You can safely pick the highest applicable number.',
							'items' => $this->embed_tag_options(),
						),
					),
					'minimum_paragraph_count' => array(
						'title'    => __( 'Minimum paragraph count', 'appnexus-acm-provider' ),
						'callback' => $callbacks['text'],
						'page'     => $page,
						'section'  => $section,
						'args'     => array(
							'type'    => 'text',
							'default' => '6',
							'desc'    => __( 'This setting allows you to prevent ads from appearing on posts with fewer paragraphs than the threshold.', 'appnexus-acm-provider' ),
						),
					),
				);
			}
			foreach ( $embed_settings as $key => $attributes ) {
				$id       = $this->option_prefix . $key;
				$name     = $this->option_prefix . $key;
				$title    = $attributes['title'];
				$callback = $attributes['callback'];
				$page     = $attributes['page'];
				$section  = $attributes['section'];
				$args     = array_merge(
					$attributes['args'],
					array(
						'title'     => $title,
						'id'        => $id,
						'label_for' => $id,
						'name'      => $name,
					)
				);
				add_settings_field( $id, $title, $callback, $page, $section, $args );
				register_setting( $page, $id );
			}
		}
		$settings[ $key ] = $embed_settings;
	}

	/**
	* Get list of possible embed tags
	*
	* @return array $items
	*/
	private function embed_tag_options() {
		$items        = array();
		$list         = explode( ', ', get_option( $this->option_prefix . 'tag_list', '' ) );
		$list         = array_map( 'trim', $list );
		$embed_prefix = get_option( $this->option_prefix . 'embed_prefix', '' );
		if ( '' !== $embed_prefix ) {
			foreach ( $list as $tag ) {
				if ( strpos( $tag, $embed_prefix ) === 0 ) {
					$item    = array(
						'text'    => $tag,
						'value'   => $tag,
						'id'      => $tag,
						'desc'    => '',
						'default' => '',
					);
					$items[] = $item;
				}
			}
		}
		return $items;
	}

	/**
	* Get list of post types
	*
	* @return array $items
	*/
	private function post_type_options() {
		$types = get_post_types();
		$items = array();
		foreach ( $types as $type ) {
			$item    = array(
				'text'    => $type,
				'value'   => $type,
				'id'      => $type,
				'desc'    => '',
				'default' => '',
			);
			$items[] = $item;
		}
		return $items;
	}

	/**
	* Default display for <input> fields
	*
	* @param array $args
	*/
	public function display_input_field( $args ) {
		//error_log('args is ' . print_r($args, true));
		$type    = $args['type'];
		$id      = $args['label_for'];
		$name    = $args['name'];
		$desc    = $args['desc'];
		$checked = '';

		$class = 'regular-text';

		if ( 'checkbox' === $type ) {
			$class = 'checkbox';
		}

		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$value = esc_attr( get_option( $id, '' ) );
			if ( 'checkbox' === $type ) {
				if ( '1' === $value ) {
					$checked = 'checked ';
				}
				$value = 1;
			}
			if ( '' === $value && isset( $args['default'] ) && '' !== $args['default'] ) {
				$value = $args['default'];
			}

			echo sprintf( '<input type="%1$s" value="%2$s" name="%3$s" id="%4$s" class="%5$s"%6$s>',
				esc_attr( $type ),
				esc_attr( $value ),
				esc_attr( $name ),
				esc_attr( $id ),
				sanitize_html_class( $class . esc_html( ' code' ) ),
				esc_html( $checked )
			);
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		} else {
			echo sprintf( '<p><code>%1$s</code></p>',
				esc_html__( 'Defined in wp-config.php', 'appnexus-acm-provider' )
			);
		}
	}

	/**
	* Default display for <textarea> fields
	*
	* @param array $args
	*/
	public function display_textarea( $args ) {
		//error_log('args is ' . print_r($args, true));
		$id      = $args['label_for'];
		$name    = $args['name'];
		$desc    = $args['desc'];
		$checked = '';

		$class = 'regular-text';

		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$value = esc_attr( get_option( $id, '' ) );
			if ( '' === $value && isset( $args['default'] ) && '' !== $args['default'] ) {
				$value = $args['default'];
			}

			echo sprintf( '<textarea name="%1$s" id="%2$s" class="%3$s" rows="10">%4$s</textarea>',
				esc_attr( $name ),
				esc_attr( $id ),
				sanitize_html_class( $class . esc_html( ' code' ) ),
				esc_attr( $value )
			);
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		} else {
			echo sprintf( '<p><code>%1$s</code></p>',
				esc_html__( 'Defined in wp-config.php', 'appnexus-acm-provider' )
			);
		}
	}

	/**
	* Display for multiple checkboxes
	* Above method can handle a single checkbox as it is
	*
	* @param array $args
	*/
	public function display_checkboxes( $args ) {
		$type         = 'checkbox';
		$name         = $args['name'];
		$overall_desc = $args['desc'];
		$options      = get_option( $name, array() );
		foreach ( $args['items'] as $key => $value ) {
			$text        = $value['text'];
			$id          = $value['id'];
			$desc        = $value['desc'];
			$checked     = '';
			$field_value = isset( $value['value'] ) ? esc_attr( $value['value'] ) : esc_attr( $key );

			if ( is_array( $options ) && in_array( (string) $field_value, $options, true ) ) {
				$checked = 'checked';
			} elseif ( is_array( $options ) && empty( $options ) ) {
				if ( isset( $value['default'] ) && true === $value['default'] ) {
					$checked = 'checked';
				}
			}
			echo sprintf( '<div class="checkbox"><label><input type="%1$s" value="%2$s" name="%3$s[]" id="%4$s"%5$s>%6$s</label></div>',
				esc_attr( $type ),
				$field_value,
				esc_attr( $name ),
				esc_attr( $id ),
				esc_html( $checked ),
				esc_html( $text )
			);
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		}
		if ( '' !== $overall_desc ) {
			echo sprintf( '<p class="description">%1$s</p>',
				esc_html( $overall_desc )
			);
		}
	}

	/**
	* Display for mulitple radio buttons
	*
	* @param array $args
	*/
	public function display_radio( $args ) {
		$type = 'radio';

		$name       = $args['name'];
		$group_desc = $args['desc'];
		$options    = get_option( $name, array() );

		foreach ( $args['items'] as $key => $value ) {
			$text = $value['text'];
			$id   = $value['id'];
			$desc = $value['desc'];
			if ( isset( $value['value'] ) ) {
				$item_value = $value['value'];
			} else {
				$item_value = $key;
			}
			$checked = '';
			if ( is_array( $options ) && in_array( (string) $item_value, $options, true ) ) {
				$checked = 'checked';
			} elseif ( is_array( $options ) && empty( $options ) ) {
				if ( isset( $value['default'] ) && true === $value['default'] ) {
					$checked = 'checked';
				}
			}

			$input_name = $name;

			echo sprintf( '<div class="radio"><label><input type="%1$s" value="%2$s" name="%3$s[]" id="%4$s"%5$s>%6$s</label></div>',
				esc_attr( $type ),
				esc_attr( $item_value ),
				esc_attr( $input_name ),
				esc_attr( $id ),
				esc_html( $checked ),
				esc_html( $text )
			);
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		}

		if ( '' !== $group_desc ) {
			echo sprintf( '<p class="description">%1$s</p>',
				esc_html( $group_desc )
			);
		}

	}

	/**
	* Display for a dropdown
	*
	* @param array $args
	*/
	public function display_select( $args ) {
		$type = $args['type'];
		$id   = $args['label_for'];
		$name = $args['name'];
		$desc = $args['desc'];
		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$current_value = get_option( $name );

			echo sprintf( '<div class="select"><select id="%1$s" name="%2$s"><option value="">- Select one -</option>',
				esc_attr( $id ),
				esc_attr( $name )
			);

			foreach ( $args['items'] as $key => $value ) {
				$text     = $value['text'];
				$value    = $value['value'];
				$selected = '';
				if ( $key === $current_value || $value === $current_value ) {
					$selected = ' selected';
				}

				echo sprintf( '<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $value ),
					esc_attr( $selected ),
					esc_html( $text )
				);

			}
			echo '</select>';
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
			echo '</div>';
		} else {
			echo sprintf( '<p><code>%1$s</code></p>',
				esc_html__( 'Defined in wp-config.php', 'appnexus-acm-provider' )
			);
		}
	}

	/**
	* Default display for <a href> links
	*
	* @param array $args
	*/
	public function display_link( $args ) {
		$label = $args['label'];
		$desc  = $args['desc'];
		$url   = $args['url'];
		if ( isset( $args['link_class'] ) ) {
			echo sprintf( '<p><a class="%1$s" href="%2$s">%3$s</a></p>',
				esc_attr( $args['link_class'] ),
				esc_url( $url ),
				esc_html( $label )
			);
		} else {
			echo sprintf( '<p><a href="%1$s">%2$s</a></p>',
				esc_url( $url ),
				esc_html( $label )
			);
		}

		if ( '' !== $desc ) {
			echo sprintf( '<p class="description">%1$s</p>',
				esc_html( $desc )
			);
		}

	}

}
