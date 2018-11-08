<?php
/**
 * Class file for the Appnexus_ACM_Provider_Front_End class.
 *
 * @file
 */

if ( ! class_exists( 'Appnexus_ACM_Provider' ) ) {
	die();
}

/**
 * Create front end functionality to render the ads
 */
class Appnexus_ACM_Provider_Front_End {

	protected $option_prefix;
	protected $version;
	protected $slug;
	protected $capability;
	protected $ad_code_manager;
	protected $ad_panel;
	protected $ad_tag_ids;

	/**
	* Constructor which sets up front end rendering
	*
	* @param string $option_prefix
	* @param string $version
	* @param string $slug
	* @param string $capability
	* @param object $ad_code_manager
	* @param object $ad_panel
	* @param array $ad_tag_ids
	* @throws \Exception
	*/
	public function __construct( $option_prefix, $version, $slug, $capability, $ad_code_manager, $ad_panel, $ad_tag_ids ) {

		$this->option_prefix   = $option_prefix;
		$this->version         = $version;
		$this->slug            = $slug;
		$this->capability      = $capability;
		$this->ad_code_manager = $ad_code_manager;
		$this->ad_panel        = $ad_panel;
		$this->ad_tag_ids      = $ad_tag_ids;

		$this->default_domain = trim( get_option( $this->option_prefix . 'default_domain', '' ) );
		$this->server_path    = trim( get_option( $this->option_prefix . 'server_path', '' ) );

		if ( '' !== $this->default_domain && '' !== $this->server_path ) {
			$use_https = get_option( $this->option_prefix . 'use_https', true );
			if ( '1' === $use_https ) {
				$protocol = 'https://';
			} else {
				$use_https = 'http://';
			}
			$this->default_url = $protocol . $this->default_domain . '/' . $this->server_path . '/';
		}

		$this->paragraph_end = array(
			false => '</p>',
			true  => "\n",
		);

		$this->whitelisted_script_urls = array( $this->default_domain );

		$this->random_number = mt_rand();

		$this->tag_type = get_option( $this->option_prefix . 'ad_tag_type', '' );

		$this->lazy_load_all    = get_option( $this->option_prefix . 'lazy_load_ads', '0' );
		$this->lazy_load_embeds = get_option( $this->option_prefix . 'lazy_load_embeds', '0' );

		$this->cache = false;
		if ( true === $this->cache ) {
			$this->form_transients = new Appnexus_ACM_Provider_Transient( 'appnexus_acm_transients' );
		}

		$this->add_actions();

	}

	/**
	* Create the action hooks to filter the html, render the ads, and the shortcodes
	*
	*/
	public function add_actions() {
		add_action( 'wp_loaded', array( $this, 'store_ad_response' ) );
		add_filter( 'acm_output_html', array( $this, 'filter_output_html' ), 10, 2 );
		add_filter( 'acm_display_ad_codes_without_conditionals', array( $this, 'check_conditionals' ) );
		add_filter( 'acm_conditional_args', array( $this, 'conditional_args' ), 10, 2 );

		// disperse shortcodes in the editor if the settings say to
		$show_in_editor = get_option( $this->option_prefix . 'show_in_editor', '0' );
		if ( '1' === $show_in_editor ) {
			add_filter( 'content_edit_pre', array( $this, 'insert_inline_ad_in_editor' ), 10, 2 );
		}

		// always either replace the shortcodes with ads, or if they are absent disperse ad codes throughout the content
		add_shortcode( 'cms_ad', array( $this, 'render_shortcode' ) );
		add_filter( 'the_content', array( $this, 'insert_and_render_inline_ads' ), 2000 );
		add_filter( 'the_content_feed', array( $this, 'insert_and_render_inline_ads' ), 2000 );
		add_action( 'wp_head', array( $this, 'action_wp_head' ) );

		// add javascript
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
	}

	/**
	* Enqueue JavaScript libraries for front end
	*
	*/
	public function add_scripts() {
		if ( '1' === $this->lazy_load_all || '1' === $this->lazy_load_embeds ) {
			wp_enqueue_script( 'postscribe', 'https://cdnjs.cloudflare.com/ajax/libs/postscribe/2.0.8/postscribe.min.js', array(), '1.0.0', true );
			wp_enqueue_script( 'polyfill', plugins_url( 'assets/js/intersection-observer.min.js', dirname( __FILE__ ) ), array(), '1.0.0', true );
			wp_enqueue_script( 'lozad', 'https://cdn.jsdelivr.net/npm/lozad/dist/lozad.min.js', array( 'postscribe', 'polyfill' ), '1.0.0', true );
			wp_add_inline_script( 'lozad', "
				var observer = lozad('.lozad', {
					rootMargin: '150px 0px',
				    load: function(el) {
				        postscribe(el, '<script src=' + el.getAttribute('data-src') + '><\/script>');
				    }
				});
				observer.observe();
				"
			);
		}
	}

	/**
	* This method can cache the response from the DX ads.
	* This method is apparently not usable in production yet.
	*
	*/
	public function store_ad_response() {
		$all_ads = array();

		if ( 'dx' === $this->tag_type ) {
			// we use the user agent because that seems to be how appnexus handles mobile ads
			$current_url = strtok( $_SERVER['REQUEST_URI'], '?' );
			$tag_list    = array_column( $this->ad_tag_ids, 'tag' );

			$dx_url     = $this->default_url . 'adstream_dx.ads/json/MP' . $current_url . '1' . $this->random_number . '@' . implode( ',', $tag_list );
			$user_agent = $_SERVER['HTTP_USER_AGENT'];

			if ( true === $this->cache ) {
				// check the cache for url/user agent combination
				$cached = $this->cache_get(
					array(
						'url'        => $current_url,
						'tag-list'   => $tag_list,
						'user-agent' => $user_agent,
					)
				);
			}

			if ( isset( $cached ) && is_array( $cached ) ) {
				// load data from cache if it is available
				$all_ads = $cached;
			} else {
				// call the ad server to get the json response
				$request_args = array(
					'user-agent' => $user_agent,
				);
				$request      = wp_remote_get( $dx_url, $request_args );
				if ( is_wp_error( $request ) ) {
					return $all_ads;
				}
				$body    = wp_remote_retrieve_body( $request );
				$all_ads = json_decode( $body, true );

				if ( true === $this->cache ) {
					// cache the json response
					$cached = $this->cache_set(
						array(
							'url'        => $current_url,
							'tag-list'   => $tag_list,
							'user-agent' => $user_agent,
						),
						$all_ads
					);
				}
			}
		} elseif ( 'jx' === $this->tag_type ) {
			$tag_list = array_column( $this->ad_tag_ids, 'tag' );
			$all_ads  = $tag_list;
		}
		$this->all_ads = $all_ads;
		return $all_ads;
	}

	/**
	 * Check to see if this API call exists in the cache
	 * if it does, return the transient for that key
	 *
	 * @param mixed $call The API call we'd like to make.
	 * @return $this->form_transients->get $cachekey
	 */
	private function cache_get( $call ) {
		$cachekey = md5( wp_json_encode( $call ) );
		return $this->form_transients->get( $cachekey );
	}

	/**
	 * Create a cache entry for the current result, with the url and args as the key
	 *
	 * @param mixed $call The API query name.
	 * @return Bool whether or not the value was set
	 * @link https://wordpress.stackexchange.com/questions/174330/transient-storage-location-database-xcache-w3total-cache
	 */
	private function cache_set( $call, $data ) {
		$cachekey = md5( wp_json_encode( $call ) );
		return $this->form_transients->set( $cachekey, $data );
	}

	/**
	 * Filter the output HTML for each ad tag to produce the code we need
	 * @param string $output_html
	 * @param string $tag_id
	 *
	 * @return $output_html
	 * return filtered html for the ad code
	 */
	public function filter_output_html( $output_html, $tag_id ) {

		$ad_code_manager = $this->ad_code_manager;
		$ad_tags         = $ad_code_manager->ad_tag_ids;

		$output_html = '';
		switch ( $tag_id ) {
			case 'appnexus_head':
				$tags = array();
				foreach ( (array) $ad_tags as $tag ) {
					if ( 'appnexus_head' !== $tag['tag'] ) {
						$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag['tag'] );
						if ( ! empty( $matching_ad_code ) ) {
							array_push( $tags, $tag['tag'] );
						}
					}
				}
				$tag_type = $this->tag_type;
				switch ( $tag_type ) {
					case 'jx':
						$output_html = '
						<!-- OAS HEADER SETUP begin -->
						<script>
						var OAS_url = "' . $this->default_url . '";
						var OAS_sitepage = "MP" + window.location.pathname;
						var OAS_RN = new String (Math.random());
						var OAS_RNS = OAS_RN.substring (2,11);
						<!-- OAS HEADER SETUP end -->
						</script>';
						break;
					case 'mjx':
						$output_html = "
						<!-- OAS HEADER SETUP begin -->
						<script>
						  /* <![CDATA[ */
						  // Configuration
						  var OAS_url = '" . $this->default_url . "';
						  var OAS_sitepage = 'MP' + window.location.pathname;
						  var OAS_listpos = '" . implode( ',', $tags ) . "';
						  var OAS_query = '';
						  var OAS_target = '_top';

						  var OAS_rns = (Math.random() + \"\").substring(2, 11);
						  document.write('<scr' + 'ipt src=\"' + OAS_url + 'adstream_mjx.ads/' + OAS_sitepage + '/1' + OAS_rns + '@' + OAS_listpos + '?' + OAS_query + '\">' + '<\/script>');

						  function OAS_AD(pos) {
						    if (typeof OAS_RICH != 'undefined') {
						      OAS_RICH(pos);
						    }
						  }
						  /* ]]> */
						</script>
						<!-- OAS HEADER SETUP end -->
						";
						break;
					case 'nx':
						break;
					case 'sx':
						break;
					case 'dx':
						// 'delivery.uat.247realmedia.com'; //Define OAS URL
						// delivery.oasc17.247realmedia.com
						/*$output_html = '';
						$output_html .= "
						<script>
							var oas_tag = oas_tag || {};
							oas_tag.url = '" . $this->default_url . "';
							oas_tag.sizes = function() {
						";
						foreach ( $tags as $tag ) {
							$output_html .= 'oas_tag.definePOS("' . $tag . '");' . "\n";
						}
						$output_html .= '};' . "\n";
						$output_html .= 'oas_tag.site_page = "MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '";' . "\n";
						$output_html .= "(function() {
							oas_tag.version ='1';oas_tag.loadAd = oas_tag.loadAd || function(){};
							var oas = document.createElement('script'),
							protocol = 'https:' == document.location.protocol?'https://':'http://',
							node = document.getElementsByTagName('script')[0];
							oas.type = 'text/javascript'; oas.async = true;
							oas.src = oas_tag.url + '/om/' + oas_tag.version + '.js';
							node.parentNode.insertBefore(oas, node);
							})();
						</script>";*/
						break;
					default:
						break;
				}

				break;
			default:
				$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag_id );
				if ( ! empty( $matching_ad_code ) ) {
					$output_html = $this->get_code_to_insert( $tag_id );
				}
		} // End switch().

		return $output_html;

	}

	/**
	 * Whether to show ads that don't have any conditionals
	 *
	 * @return bool
	 *
	 */
	public function check_conditionals() {
		$show_without_conditionals = get_option( $this->option_prefix . 'show_ads_without_conditionals', '0' );
		if ( '1' === $show_without_conditionals ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Additional arguments for conditionals
	 *
	 * @param array $args
	 * @param string $function
	 * @return array $args
	 *
	 */
	public function conditional_args( $args, $function ) {
		global $wp_query;
		// has_category and has_tag use has_term
		// we should pass queried object id for it to produce correct result

		if ( in_array( $function, array( 'has_category', 'has_tag' ) ) ) {
			if ( true === $wp_query->is_single ) {
				$args[] = $wp_query->queried_object->ID;
			}
			$args['is_singular'] = true;
		}
		return $args;
	}

	/**
	 * Get regular expression for a specific shortcode
	 *
	 * @param string $shortcode
	 * @return string $regex
	 *
	 */
	private function get_single_shortcode_regex( $shortcode ) {
		// The  $shortcode_tags global variable contains all registered shortcodes.
		global $shortcode_tags;

		// Store the shortcode_tags global in a temporary variable.
		$temp_shortcode_tags = $shortcode_tags;

		// Add only one specific shortcode name to the $shortcode_tags global.
		//
		// Replace 'related_posts_by_tax' with the shortcode you want to get the regex for.
		// Don't include the brackets from a shortcode.
		$shortcode_tags = array( $shortcode => '' );

		// Create the regex for your shortcode.
		$regex = '/' . get_shortcode_regex() . '/s';

		// Restore the $shortcode_tags global.
		$shortcode_tags = $temp_shortcode_tags;

		// Print the regex.
		return $regex;
	}

	/**
	 * Use one or more inline ads, depending on the settings. This does not place them into the post editor, but into the post when it renders.
	 *
	 * @param string $content
	 *
	 * @return $content
	 * return the post content with code for ads inside it at the proper places
	 *
	 */
	public function insert_and_render_inline_ads( $content = '' ) {
		if ( is_feed() ) {
			global $wp_query;
			$current_object = $wp_query;
		} else {
			$current_object = get_queried_object();
		}
		if ( is_object( $current_object ) ) {
			$post_type = isset( $current_object->post_type ) ? $current_object->post_type : '';
			$post_id   = isset( $current_object->ID ) ? $current_object->ID : '';
		} else {
			return $content;
		}
		$in_editor = false; // we are not in the editor right now

		// Should we skip rendering ads?
		$should_we_skip = $this->should_we_skip_ads( $content, $post_type, $post_id, $in_editor );
		if ( true === $should_we_skip ) {
			return $content;
		}

		// Render any `[cms_ad` shortcodes, whether they were manually added or added by this plugin
		// this should also be used to render the shortcodes added in the editor
		$shortcode = 'cms_ad';
		$pattern   = $this->get_single_shortcode_regex( $shortcode );
		if ( preg_match_all( $pattern, $content, $matches ) && array_key_exists( 2, $matches ) && in_array( $shortcode, $matches[2] ) ) {

			/*
			[0] => Array (
				[0] => [cms_ad:Middle]
			)

			[1] => Array(
				[0] =>
			)

			[2] => Array(
				[0] => cms_ad
			)

			[3] => Array(
				[0] => :Middle
			)
			*/

			foreach ( $matches[0] as $key => $value ) {
				$position  = ( isset( $matches[3][ $key ] ) && '' !== ltrim( $matches[3][ $key ], ':' ) ) ? ltrim( $matches[3][ $key ], ':' ) : get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
				$rewrite[] = $this->get_code_to_insert( $position );
				$matched[] = $matches[0][ $key ];
			}
			return str_replace( $matched, $rewrite, $content );
		}

		$ad_code_manager = $this->ad_code_manager;

		$content = $this->insert_ads_into_content( $content, false );
		return $content;

	}

	/**
	 * Make [cms_ad] a recognized shortcode
	 *
	 * @param array $atts
	 *
	 *
	 */
	public function render_shortcode( $atts ) {
		return;
	}

	/**
	 * Insert one or more inline ads into the post editor, depending on the settings. Editors can then rearrange them as desired.
	 *
	 * @param string $content
	 * @param int $post_id
	 *
	 * @return $content
	 * return the post content into the editor with shortcodes for ads inside it at the proper places
	 *
	 */
	public function insert_inline_ad_in_editor( $content = '', $post_id ) {

		/*
		// todo: i think this would be nice, but i think it won't work like this
		$user_id = get_current_user_id();
		if ( ! user_can( $user_id, $this->capability ) ) {
			return $content;
		}*/

		$post_type = get_post_type( $post_id );
		$in_editor = true;

		// should we skip rendering ads?
		$should_we_skip = $this->should_we_skip_ads( $content, $post_type, $post_id, $in_editor );
		if ( true === $should_we_skip ) {
			return $content;
		}

		$ad_code_manager = $this->ad_code_manager;

		$content = $this->insert_ads_into_content( $content, true );
		return $content;

	}

	/**
	 * Determine whether the current post should get automatic ad insertion.
	 *
	 * @param string $content
	 * @param string $post_type
	 * @param int $post_id
	 * @param bool $in_editor
	 *
	 * @return bool
	 * return true to skip rendering ads, false otherwise
	 *
	 */
	private function should_we_skip_ads( $content, $post_type, $post_id, $in_editor ) {

		// This is on the story, so we can access the loop
		if ( false === $in_editor ) {
			// Stop if this is not being called In The Loop.
			if ( ! in_the_loop() || ! is_main_query() ) {
				return true;
			}
			if ( ! is_single() && ! is_feed() ) {
				return true;
			}
		} else {
			// Check that there isn't a line starting with `[cms_ad` already.
			// If there is, stop adding automatic short code(s). Assume the user is doing it manually.
			if ( false !== stripos( $content, '[cms_ad' ) || false !== stripos( $content, '<img class="mceItem mceAdShortcode' ) ) {
				return true;
			}
		}

		// Don't add ads if this post is not a supported type
		$post_types = get_option( $this->option_prefix . 'post_types', array() );
		if ( ! in_array( $post_type, $post_types ) ) {
			return true;
		}

		// If this post has the option set to not add automatic ads, do not add them to the editor view. If we're not in the editor, ignore this value because they would be manually added at this point.
		// This field name is stored in the plugin options.
		$field_automatic_name  = get_option( $this->option_prefix . 'prevent_automatic_ads_field', '_post_prevent_appnexus_ads' );
		$field_automatic_value = get_option( $this->option_prefix . 'prevent_automatic_ads_field_value', 'on' );
		if ( true === $in_editor && get_post_meta( $post_id, $field_automatic_name, true ) === $field_automatic_value ) {
			return true;
		}

		// If this post has that option set to not add automatic ads, skip them in the front end view unless they have been manually added.
		if ( false === $in_editor && get_post_meta( $post_id, $field_automatic_name, true ) === $field_automatic_value && false === stripos( $content, '[cms_ad' ) && false === stripos( $content, '<img class="mceItem mceAdShortcode' ) ) {
			return true;
		}

		// allow developers to prevent automatic ads
		$prevent_automatic_ads = apply_filters( 'appnexus_acm_provider_prevent_automatic_ads', false, $post_id );
		if ( true === $prevent_automatic_ads ) {
			return true;
		}

		// Stop if this post has the option set to not add any ads.
		// This field name is stored in the plugin options.
		$field_name  = get_option( $this->option_prefix . 'prevent_ads_field', '_post_prevent_appnexus_ads' );
		$field_value = get_option( $this->option_prefix . 'prevent_ads_field_value', 'on' );
		if ( get_post_meta( $post_id, $field_name, true ) === $field_value ) {
			return true;
		}

		// allow developers to prevent ads
		$prevent_ads = apply_filters( 'appnexus_acm_provider_prevent_ads', false, $post_id );
		if ( true === $prevent_ads ) {
			return true;
		}

		// If we don't have any paragraphs, let's skip the ads for this post
		if ( ! stripos( $content, $this->paragraph_end[ $in_editor ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Place the ad code, or cms shortcode for the ad, into the post body as many times, and in the right location.
	 *
	 * @param string $content
	 * @param bool $in_editor
	 *
	 * @return $content
	 * return the post content with shortcodes for ads inside it at the proper places
	 *
	 */
	private function insert_ads_into_content( $content, $in_editor = false ) {
		$multiple_embeds = get_option( $this->option_prefix . 'multiple_embeds', '0' );
		if ( is_array( $multiple_embeds ) ) {
			$multiple_embeds = $multiple_embeds[0];
		}

		$end      = strlen( $content );
		$position = $end;

		$paragraph_end = $this->paragraph_end[ $in_editor ];

		if ( '1' === $multiple_embeds ) {

			$insert_every_paragraphs = intval( get_option( $this->option_prefix . 'insert_every_paragraphs', 4 ) );
			$minimum_paragraph_count = intval( get_option( $this->option_prefix . 'minimum_paragraph_count', 6 ) );

			$embed_prefix      = get_option( $this->option_prefix . 'embed_prefix', 'x' );
			$start_embed_id    = get_option( $this->option_prefix . 'start_tag_id', 'x100' );
			$start_embed_count = intval( str_replace( $embed_prefix, '', $start_embed_id ) ); // ex 100
			$end_embed_id      = get_option( $this->option_prefix . 'end_tag_id', 'x110' );
			$end_embed_count   = intval( str_replace( $embed_prefix, '', $end_embed_id ) ); // ex 110

			$paragraphs = [];
			$split      = explode( $paragraph_end, $content );
			foreach ( $split as $paragraph ) {
				// filter out empty paragraphs
				if ( strlen( $paragraph ) > 3 ) {
					$paragraphs[] = $paragraph . $paragraph_end;
				}
			}

			$paragraph_count = count( $paragraphs );
			$maximum_ads     = floor( ( $paragraph_count - $minimum_paragraph_count ) / $insert_every_paragraphs ) + $minimum_paragraph_count;

			$ad_num      = 0;
			$counter     = $minimum_paragraph_count;
			$embed_count = $start_embed_count;

			for ( $i = 0; $i < $paragraph_count; $i++ ) {
				if ( 0 === $counter && $embed_count <= $end_embed_count ) {
					// make a shortcode using the number of the shorcode that will be added.
					if ( false === $in_editor ) {
						$shortcode = $this->get_code_to_insert( $embed_prefix . (int) $embed_count );
					} elseif ( true === $in_editor ) {
						$shortcode = "\n" . '[cms_ad:' . $embed_prefix . (int) $embed_count . ']' . "\n\n";
					}
					$otherblocks = '(?:div|dd|dt|li|pre|fieldset|legend|figcaption|details|thead|tfoot|tr|td|style|script|link)';
					if ( preg_match( '!(<' . $otherblocks . '[\s/>])!', $paragraphs[ $i ], $m ) ) {
						continue;
					}
					array_splice( $paragraphs, $i + $ad_num, 0, $shortcode );
					$counter = $insert_every_paragraphs;
					$ad_num++;
					if ( $ad_num > $maximum_ads ) {
						break;
					}
					$embed_count++;
				}
				$counter--;
			}

			if ( true === $in_editor ) {
				$content = implode( $paragraph_end, $paragraphs );
			} else {
				$content = implode( '', $paragraphs );
			}
		} else {
			$tag_id        = get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
			$top_offset    = get_option( $this->option_prefix . 'auto_embed_top_offset', 1000 );
			$bottom_offset = get_option( $this->option_prefix . 'auto_embed_bottom_offset', 400 );

			// if the content is longer than the minimum ad spot find a break.
			// otherwise place the ad at the end
			if ( $position > $top_offset ) {
				// find the break point
				$breakpoints = array(
					'</p>'             => 4,
					'<br />'           => 6,
					'<br/>'            => 5,
					'<br>'             => 4,
					'<!--pagebreak-->' => 0,
					'<p>'              => 0,
					"\n"               => 2,
				);
				// We use strpos on the reversed needle and haystack for speed.
				foreach ( $breakpoints as $point => $offset ) {
					$length = stripos( $content, $point, $top_offset );
					if ( false !== $length ) {
						$position = min( $position, $length + $offset );
					}
				}
			}
			if ( false === $in_editor ) {
				// If the position is at or near the end of the article.
				if ( $position > $end - $bottom_offset ) {
					$position  = $end;
					$shortcode = $this->get_code_to_insert( $tag_id, 'minnpost-ads-ad-article-end' );
				} else {
					$shortcode = $this->get_code_to_insert( $tag_id, 'minnpost-ads-ad-article-middle' );
				}
			} else {
				$shortcode = "\n" . '[cms_ad:' . $tag_id . ']' . "\n\n";
			}

			$content = substr_replace( $content, $shortcode, $position, 0 );
		}

		return $content;
	}

	/**
	 * Get ad code to insert for a given tag.
	 *
	 * @param string $tag_id
	 * @param string $class
	 *
	 * @return $output_html
	 * return the necessary ad code for the specified tag type
	 *
	 */
	public function get_code_to_insert( $tag_id, $class = '' ) {
		// get the code to insert
		$ad_code_manager = $this->ad_code_manager;
		$ad_tags         = $ad_code_manager->ad_tag_ids;

		$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag_id );
		if ( ! empty( $matching_ad_code ) ) {

			$tag_type = $this->tag_type;
			switch ( $tag_type ) {
				case 'jx':
					$tags = $tag_id;
					if ( ! empty( $this->all_ads ) ) {
						$active_positions = array();
						foreach ( $this->all_ads as $ad ) {
							$matching_ad_code = $ad_code_manager->get_matching_ad_code( $ad );
							if ( ! empty( $matching_ad_code ) ) {
								array_push( $active_positions, $ad );
							}
						}
						$key = array_search( $tag_id, $this->all_ads );
						if ( is_int( $key ) ) {
							$positions = implode( ',', $active_positions );
							$tags      = $positions . '!' . $tag_id;
						}
					}

					$output_html             = array();
					$output_html['url']      = $this->default_url . 'adstream_jx.ads/MP/' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $tags;
					$output_html['script']   = '<script>
					<!--';
					$output_html['script']  .= '
						var OAS_pos = "' . $tags . '";
						var OAS_query = "";';
					$output_html['script']  .= "document.write('<scr' + 'ipt src=\"' + OAS_url + 'adstream_jx.ads/' + OAS_sitepage + '/1' + OAS_RNS + '@' + OAS_pos + '?' + OAS_query + '\">' + '<\/script>');
					// --
					</script>";
					$output_html['noscript'] = '<noscript>
					    <a href="' . $this->default_url . 'click_nx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $tags . '">
					    	<img src="' . $this->default_url . 'adstream_nx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $tags . '" border="0">
					    </a>
					</noscript>';
					$output_html             = $this->lazy_loaded_html_or_not( $output_html, $tag_id, true, 'script' );
					break;
				case 'mjx':
					$output_html = '<script>OAS_AD("' . $tag_id . '");</script>';
					break;
				case 'nx':
					$output_html  = '';
					$output_html .= '<script>
					<!--
					OAS_url = "' . $this->default_domain . '";
					OAS_sitepage = "' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '";
					OAS_pos = "' . $tag_id . '";';
					//OAS_query = 'Keyword';
					$output_html .= 'var OAS_rns = (Math.random() + \"\").substring(2, 11);';
					$output_html .= "document.write('<scr' + 'ipt src=\"' + OAS_url + '/$this->server_path/adstream_jx.ads/' + OAS_sitepage + '/1' + OAS_RNS + '@' + OAS_pos + '?' + OAS_query + '\"></scr' + 'ipt>');
					// --
					</script>";
					$output_html .= '<noscript>
						<a href="' . $this->default_url . 'click_nx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $tag_id . '">
					    	<img src="' . $this->default_url . 'adstream_nx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $tag_id . '" border="0">
					    </a>
						</noscript>';
					break;
				case 'sx':
					$not_tags    = implode( ',', array_column( $ad_tags, 'tag' ) );
					$output_html = '<iframe src="' . $this->default_url . 'adstream_sx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . $not_tags . '!' . $tag_id . '?_RM_IP_=' . $_SERVER['REMOTE_ADDR'] . '" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>';
					$output_html = $this->lazy_loaded_html_or_not( $output_html, $tag_id );
					break;
				case 'dx':
					$output_html     = '';
					$impression_html = '';

					if ( ! empty( $this->all_ads['Ad'] ) ) {
						$positions = array_column( $this->all_ads['Ad'], 'Pos' );
						$key       = array_search( $tag_id, $positions );
						if ( is_int( $key ) ) {
							$ad_html = $this->all_ads['Ad'][ $key ]['Text'];

							// add the impression tracker
							$impression_html = '<img class="appnexus-ad-impression" src="' . $this->all_ads['Ad'][ $key ]['ImpUrl'] . '" style="width: 1px; height: 1px; position: absolute; visibility: hidden;">';

							$ad_html         = $this->lazy_loaded_html_or_not( $ad_html, $tag_id, true );
							$impression_html = $this->lazy_loaded_html_or_not( $impression_html, $tag_id );

							$output_html = $ad_html . $impression_html;
						}
					}
					break;
				default:
					break;
			}

			$output_html = '<div class="appnexus-ad ad-' . sanitize_title( $tag_id ) . '">' . $output_html . '</div>';

			/*if ( 4 === strlen( $tag_id ) && 0 === strpos( $tag_id, 'x10' ) ) {
				$output_html = '
					<div class="appnexus-ad ad-' . sanitize_title( $tag_id ) . '">
						<code><!--
						OAS_AD("' . $tag_id . '");
						//-->
						</code>
					</div>
				';
			}*/
		}
		// use the function we already have for the placeholder ad
		if ( function_exists( 'acm_no_ad_users' ) ) {
			if ( ! isset( $output_html ) ) {
				$output_html = '';
			}
			$output_html = acm_no_ad_users( $output_html, $tag_id );
		}
		return $output_html;
	}

	/**
	 * Return HTML, lazy loaded or not, depending on settings and such
	 *
	 * @param string $output_html    The non lazy loaded html
	 * @param string $tag_id         The ad tag id
	 * @param bool $check_html       Whether to check the html contents before trying to lazy load them
	 * @param string $html_tag       What HTML tag we're dealing with here.
	 *
	 * @return $output_html          The ad html, lazy loaded if applicable
	 *
	 */
	private function lazy_loaded_html_or_not( $output_html, $tag_id, $check_html = false, $html_tag = 'img' ) {
		// lazy load everything
		$use_filter = false;
		if ( '1' === $this->lazy_load_all ) {
			$use_filter = true;
		} elseif ( '1' === $this->lazy_load_embeds ) {
			$use_filter = false; // we only want to lazy load the embeds, so set it to true when necessary
			// lazy load embeds only
			$multiple_embeds = get_option( $this->option_prefix . 'multiple_embeds', '0' );
			if ( is_array( $multiple_embeds ) ) {
				$multiple_embeds = $multiple_embeds[0];
			}

			// if multiples are enabled, check to see if the id is in the embed tag range
			if ( '1' === $multiple_embeds ) {
				$embed_prefix        = get_option( $this->option_prefix . 'embed_prefix', 'x' );
				$start_embed_id      = get_option( $this->option_prefix . 'start_tag_id', 'x100' );
				$start_embed_count   = intval( str_replace( $embed_prefix, '', $start_embed_id ) ); // ex 100
				$end_embed_id        = get_option( $this->option_prefix . 'end_tag_id', 'x110' );
				$end_embed_count     = intval( str_replace( $embed_prefix, '', $end_embed_id ) ); // ex 110
				$current_embed_count = intval( str_replace( $embed_prefix, '', $tag_id ) ); // ex 108
				if ( ( $current_embed_count >= $start_embed_count && $current_embed_count <= $end_embed_count ) ) {
					$use_filter = true;
				}
			} else {
				$auto_embed = get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
				if ( $auto_embed === $tag_id ) {
					$use_filter = true;
				}
			}
		}

		// if the filter is enabled, try to transform the HTML to match lozad's requirements.
		// I think it might be good to do this with regex.
		if ( true === $use_filter ) {
			switch ( $html_tag ) {
				case 'script':
					$output_html['script'] = '<div class="lozad" data-src="' . $output_html['url'] . '"></div>';
					break;
				case 'img':
					$output_html = str_replace( '<img src=', '<img class="lozad data-src=', $output_html );
					break;
				default:
					$output_html = $output_html;
					break;
			}
		}

		// if output_html is currently an array, implode the parts we use into a string
		if ( is_array( $output_html ) ) {
			$output_html = implode( '', array( $output_html['script'], $output_html['noscript'] ) );
		}

		return $output_html;
	}

	/**
	 * Add the initialization code in the head if the tag type requires it.
	 */
	public function action_wp_head() {
		$tag_type = $this->tag_type;
		switch ( $tag_type ) {
			case 'jx':
				do_action( 'acm_tag', 'appnexus_head' );
				break;
			case 'mjx':
				do_action( 'acm_tag', 'appnexus_head' );
				break;
			case 'nx':
				break;
			case 'sx':
				break;
			case 'dx':
				do_action( 'acm_tag', 'appnexus_head' );
				break;
			default:
				# code...
				break;
		}
	}

}

/**
 * Class to store all theme/plugin transients as an array in one WordPress transient
 **/
class Appnexus_ACM_Provider_Transient {

	protected $name;

	public $cache_expiration;

	/**
	 * Constructor which sets cache options and the name of the field that lists this plugin's cache keys.
	 *
	 * @param string $name The name of the field that lists all cache keys.
	 */
	public function __construct( $name ) {
		$this->name             = $name;
		$this->cache_expiration = 600;
		$this->cache_prefix     = esc_sql( 'appnexus_acm_' );
	}

	/**
	 * Get the transient that lists all the other transients for this plugin.
	 *
	 * @return mixed value of transient. False of empty, otherwise array.
	 */
	public function all_keys() {
		return get_transient( $this->name );
	}

	/**
	 * Set individual transient, and add its key to the list of this plugin's transients.
	 *
	 * @param string $cachekey the key for this cache item
	 * @param mixed $value the value of the cache item
	 * @param int $cache_expiration. How long the plugin key cache, and this individual item cache, should last before expiring.
	 * @return mixed value of transient. False of empty, otherwise array.
	 */
	public function set( $cachekey, $value ) {

		$prefix   = $this->cache_prefix;
		$cachekey = $prefix . $cachekey;

		$keys   = $this->all_keys();
		$keys[] = $cachekey;
		set_transient( $this->name, $keys, $this->cache_expiration );

		return set_transient( $cachekey, $value, $this->cache_expiration );
	}

	/**
	 * Get the individual cache value
	 *
	 * @param string $cachekey the key for this cache item
	 * @return mixed value of transient. False of empty, otherwise array.
	 */
	public function get( $cachekey ) {
		$prefix   = $this->cache_prefix;
		$cachekey = $prefix . $cachekey;
		return get_transient( $cachekey );
	}

	/**
	 * Delete the individual cache value
	 *
	 * @param string $cachekey the key for this cache item
	 * @return bool True if successful, false otherwise.
	 */
	public function delete( $cachekey ) {
		$prefix   = $this->cache_prefix;
		$cachekey = $prefix . $cachekey;
		return delete_transient( $cachekey );
	}

	/**
	 * Delete the entire cache for this plugin
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function flush() {
		$keys   = $this->all_keys();
		$result = true;
		foreach ( $keys as $key ) {
			$result = delete_transient( $key );
		}
		$result = delete_transient( $this->name );
		return $result;
	}

}
