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
	protected $ad_code_manager;
	protected $ad_panel;
	protected $ad_tag_ids;

	/**
	* Constructor which sets up front end rendering
	*
	* @param string $option_prefix
	* @param string $version
	* @param string $slug
	* @param object $ad_code_manager
	* @param object $ad_panel
	* @param array $ad_tag_ids
	* @throws \Exception
	*/
	public function __construct( $option_prefix, $version, $slug, $ad_code_manager, $ad_panel, $ad_tag_ids ) {

		$this->option_prefix = $option_prefix;
		$this->version = $version;
		$this->slug = $slug;
		$this->ad_code_manager = $ad_code_manager;
		$this->ad_panel = $ad_panel;
		$this->ad_tag_ids = $ad_tag_ids;

		$this->default_domain = trim( get_option( $this->option_prefix . 'default_domain', '' ) );
		$this->server_path = trim( get_option( $this->option_prefix . 'server_path', '' ) );

		if ( '' !== $this->default_domain && '' !== $this->server_path ) {
			$use_https = get_option( $this->option_prefix . 'use_https', true );
			if ( '1' === $use_https ) {
				$protocol = 'https://';
			} else {
				$use_https = 'http://';
			}
			$this->default_url = $protocol . $this->default_domain . '/' . $this->server_path . '/';
		}

		$this->paragraph_end = '</p>';

		$this->whitelisted_script_urls = array( $this->default_domain );

		$this->random_number = mt_rand();

		$this->tag_type = get_option( $this->option_prefix . 'ad_tag_type', '' );

		$this->lazy_load = get_option( $this->option_prefix . 'lazy_load_ads', '0' );

		$this->form_transients = new Appnexus_ACM_Provider_Transient( 'appnexus_acm_transients' );
		$this->all_ads = $this->store_ad_response();

		$this->add_actions();

	}

	/**
	* Create the action hooks to filter the html, render the ads, and the shortcodes
	*
	*/
	private function add_actions() {
		add_filter( 'acm_output_html', array( $this, 'filter_output_html' ), 10, 2 );
		add_filter( 'acm_display_ad_codes_without_conditionals', array( $this, 'check_conditionals' ) );

		// disperse shortcodes in the editor if the settings say to
		$show_in_editor = get_option( $this->option_prefix . 'show_in_editor', '0' );
		if ( '1' === $show_in_editor ) {
			add_filter( 'content_edit_pre', array( $this, 'insert_inline_ad_in_editor' ), 10, 2 );
		}

		// always either replace the shortcodes with ads, or if they are absent disperse ad codes throughout the content
		add_filter( 'the_content', array( $this, 'insert_and_render_inline_ads' ), 10 );
		add_action( 'wp_head', array( $this, 'action_wp_head' ) );
	}

	private function store_ad_response() {
		$all_ads = array();

		if ( 'dx' === $this->tag_type ) {
			$tags_for_url = array_column( $this->ad_tag_ids, 'tag' );
			$dx_url = $this->default_url . 'adstream_dx.ads/json/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . $this->random_number . '@' . implode( ',', $tags_for_url );
			$cached = $this->cache_get( $tags_for_url );
			if ( is_array( $cached ) ) {
				$all_ads = $cached;
			} else {
				$request = wp_remote_get( $dx_url );
				if ( is_wp_error( $request ) ) {
					return $all_ads;
				}
				$body = wp_remote_retrieve_body( $request );
				$all_ads = json_decode( $body, true );
				$cached = $this->cache_set( $tags_for_url, $all_ads );
			}
		}

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
		$ad_tags = $ad_code_manager->ad_tag_ids;

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
	 * Use one or more inline ads, depending on the settings. This does not place them into the post editor, but into the post when it renders.
	 *
	 * @param string $content
	 *
	 * @return $content
	 * return the post content with code for ads inside it at the proper places
	 *
	 */
	public function insert_and_render_inline_ads( $content = '' ) {

		global $wp_query;
		if ( is_object( $wp_query->queried_object ) ) {
			$post_type = $wp_query->queried_object->post_type;
			$post_id = $wp_query->queried_object->ID;
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
		if ( preg_match_all( '/\[\s*(cms_ad)\s*[:]?(\s*([\w+\/\.]+))?\]/i', $content, $match ) ) {
			// $match[0][xx] .... fully matched string [ad:Middle1]
			// $match[1][xx] .... matched tag type ( ad )
			// $match[2][xx] .... matched position ( Middle )
			foreach ( $match[0] as $key => $value ) {
				$position = ( isset( $match[2][ $key ] ) && '' !== $match[2][ $key ] ) ? $match[2][ $key ] : get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
				$rewrite[] = $this->get_code_to_insert( $position );
				$matched[] = $match[0][ $key ];
			}
			return str_replace( $matched, $rewrite, $content );
		}

		$ad_code_manager = $this->ad_code_manager;

		$content = $this->insert_ads_into_content( $content, false );
		return $content;

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
			if ( ! is_single() ) {
				return true;
			}
		} else {
			// Check that there isn't a line starting with `[cms_ad` already.
			// If there is, stop adding automatic short code(s). Assume the user is doing it manually.
			if ( preg_match( '/^\[cms_ad/m', $content ) ) {
				return true;
			}
		}

		// Don't add ads if this post is not a supported type
		$post_types = get_option( $this->option_prefix . 'post_types', array() );
		if ( ! in_array( $post_type, $post_types ) ) {
			return true;
		}

		// Stop if this post has the option set to not add ads.
		// This field name is stored in the plugin options.
		if ( 'on' === get_post_meta( $post_id, $this->option_prefix . 'prevent_ads_field', true ) ) {
			return true;
		}

		// If we don't have any paragraphs, let's skip the ads for this post
		if ( ! stripos( $content, $this->paragraph_end ) ) {
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

		$end = strlen( $content );
		$position = $end;

		if ( '1' === $multiple_embeds ) {

			$insert_every_paragraphs = get_option( $this->option_prefix . 'insert_every_paragraphs', 4 );
			$maximum_embed_count = get_option( $this->option_prefix . 'maximum_embed_count', 10 );
			$minimum_paragraph_count = get_option( $this->option_prefix . 'minimum_paragraph_count', 6 );

			$paragraph_positions = array();
			$last_position = -1;
			$paragraph_end = $this->paragraph_end;

			while ( stripos( $content, $paragraph_end, $last_position + 1 ) !== false ) {
				// Get the position of the end of the next $paragraph_end.
				$last_position = stripos( $content, $paragraph_end, $last_position + 1 ) + 3; // what does the 3 mean?
				$paragraph_positions[] = $last_position;
			}

			// If the total number of paragraphs is bigger than the minimum number of paragraphs
			// It is assumed that $minimum_paragraph_count > $insert_every_paragraphs * $maximum_embed_count
			if ( count( $paragraph_positions ) >= $minimum_paragraph_count ) {
				// How many shortcodes have been added?
				$n = 0;
				// Safety check number: stores the position of the last insertion.
				$previous_position = 0;
				$i = 0;
				while ( $i < count( $paragraph_positions ) && $n <= $maximum_embed_count ) {
					// Modulo math to only output shortcode after $insert_every_paragraphs closing paragraph tags.
					// +1 because of zero-based indexing.
					if ( 0 === ( $i + 1 ) % $insert_every_paragraphs && isset( $paragraph_positions[ $i ] ) ) {
						// make a shortcode using the number of the shorcode that will be added.
						// Using "" here so we can interpolate the variable.
						if ( false === $in_editor ) {
							$shortcode = $this->get_code_to_insert( 'x' . ( 100 + (int) $n ) );
						} elseif ( true === $in_editor ) {
							$shortcode = '[cms_ad:' . 'x' . ( 100 + (int) $n ) . ']';
						}
						$position = $paragraph_positions[ $i ] + 1;
						// Safety check:
						// If the position we're adding the shortcode is at a lower point in the story than the position we're adding,
						// Then something has gone wrong and we should insert no more shortcodes.
						if ( $position > $previous_position ) {
							$content = substr_replace( $content, $shortcode, $paragraph_positions[ $i ] + 1, 0 );
							// Increase the saved last position.
							$previous_position = $position;
							// Increment number of shortcodes added to the post.
							$n++;
						}
						// Increase the position of later shortcodes by the length of the current shortcode.
						foreach ( $paragraph_positions as $j => $pp ) {
							if ( $j > $i ) {
								$paragraph_positions[ $j ] = $pp + strlen( $shortcode );
							}
						}
					}
					$i++;
				}
			}
		} else {
			$tag_id = get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
			$top_offset = get_option( $this->option_prefix . 'auto_embed_top_offset', 1000 );
			$bottom_offset = get_option( $this->option_prefix . 'auto_embed_bottom_offset', 400 );

			// if the content is longer than the minimum ad spot find a break.
			// otherwise place the ad at the end
			if ( $position > $top_offset ) {
				// find the break point
				$breakpoints = array(
					'</p>' => 4,
					'<br />' => 6,
					'<br/>' => 5,
					'<br>' => 4,
					'<!--pagebreak-->' => 0,
					'<p>' => 0,
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
					$position = $end;
					$shortcode = $this->get_code_to_insert( $tag_id, 'minnpost-ads-ad-article-end' );
				} else {
					$shortcode = $this->get_code_to_insert( $tag_id, 'minnpost-ads-ad-article-middle' );
				}
			} else {
				$shortcode = '[cms_ad:' . $tag_id . ']';
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
		$ad_tags = $ad_code_manager->ad_tag_ids;

		$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag_id );
		if ( ! empty( $matching_ad_code ) ) {

			$tag_type = $this->tag_type;
			switch ( $tag_type ) {
				case 'jx':
					break;
				case 'mjx':
					$output_html = '<script>OAS_AD("' . $tag_id . '");</script>';
					break;
				case 'nx':
					$output_html = '';
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
					<a href="' . $this->default_url . 'click_nx.ads' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '@' . $tag_id . '">
						<img src="' . $this->default_url . 'adstream_nx.ads' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '@' . $tag_id . '" border="0" />
						</a>
						</noscript>';
					break;
				case 'sx':
					$not_tags = implode( ',', array_column( $ad_tags, 'tag' ) );
					$output_html = '<iframe src="' . $this->default_url . 'adstream_sx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . mt_rand() . '@' . $not_tags . '!' . $tag_id . '?_RM_IP_=' . $_SERVER['REMOTE_ADDR'] . '" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>';
					// check for the lazy load option and existence of "easy_lazy_loader_html" filter
					if ( '1' === $this->lazy_load && array_key_exists( 'easy_lazy_loader_html', $GLOBALS['wp_filter'] ) ) {
						// lazy load
						$output_html = apply_filters( 'easy_lazy_loader_html', $output_html );
					}
					break;
				case 'dx':
					$output_html = '';
					$impression_html = '';
					$has_image = false;
					$has_iframe = false;
					$has_video = false;
					$has_audio = false;
					if ( ! empty( $this->all_ads['Ad'] ) ) {
						$positions = array_column( $this->all_ads['Ad'], 'Pos' );
						$key = array_search( $tag_id, $positions );
						if ( is_int( $key ) ) {
							$ad_html = $this->all_ads['Ad'][ $key ]['Text'];

							if ( false !== stripos( $ad_html, '<img' ) && false === stripos( $ad_html, '<noscript' ) ) {
								$has_image = true;
							}
							if ( false !== stripos( $ad_html, '<iframe' ) ) {
								$has_iframe = true;
							}
							if ( false !== stripos( $ad_html, '<video' ) ) {
								$has_video = true;
							}
							if ( false !== stripos( $ad_html, '<audio' ) ) {
								$has_audio = true;
							}

							// add the impression tracker
							$impression_html = '<img class="appnexus-ad-impression" src="' . $this->all_ads['Ad'][ $key ]['ImpUrl'] . '" style="width: 1px; height: 1px; position: absolute; visibility: hidden;">';
							// check for the lazy load option and existence of "easy_lazy_loader_html" filter
							if ( '1' === $this->lazy_load && array_key_exists( 'easy_lazy_loader_html', $GLOBALS['wp_filter'] ) ) {
								// lazy load
								$lazy_load_options = get_option( 'easylazyloader_options', array() );
								if ( true === $has_image && (bool) 1 === $lazy_load_options['lazy_load_images'] ) {
									$ad_html = apply_filters( 'easy_lazy_loader_html', $ad_html );
								}
								if ( true === $has_iframe && (bool) 1 === $lazy_load_options['lazy_load_iframes'] ) {
									$ad_html = apply_filters( 'easy_lazy_loader_html', $ad_html );
								}
								if ( true === $has_video && (bool) 1 === $lazy_load_options['lazy_load_videos'] ) {
									$ad_html = apply_filters( 'easy_lazy_loader_html', $ad_html );
								}
								if ( true === $has_audio && (bool) 1 === $lazy_load_options['lazy_load_audios'] ) {
									$ad_html = apply_filters( 'easy_lazy_loader_html', $ad_html );
								}
								$impression_html = apply_filters( 'easy_lazy_loader_html', $impression_html );
							}
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
	 * Add the initialization code in the head if the tag type requires it.
	 */
	public function action_wp_head() {
		$tag_type = $this->tag_type;
		switch ( $tag_type ) {
			case 'jx':
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
		$this->name = $name;
		$this->cache_expiration = 600;
		$this->cache_prefix = esc_sql( 'appnexus_acm_' );
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

		$prefix = $this->cache_prefix;
		$cachekey = $prefix . $cachekey;

		$keys = $this->all_keys();
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
		$prefix = $this->cache_prefix;
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
		$prefix = $this->cache_prefix;
		$cachekey = $prefix . $cachekey;
		return delete_transient( $cachekey );
	}

	/**
	 * Delete the entire cache for this plugin
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function flush() {
		$keys = $this->all_keys();
		$result = true;
		foreach ( $keys as $key ) {
			$result = delete_transient( $key );
		}
		$result = delete_transient( $this->name );
		return $result;
	}

}
