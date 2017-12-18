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
	protected $slug;
	protected $version;
	protected $ad_code_manager;
	protected $ad_panel;

	/**
	* Constructor which sets up front end rendering
	*
	* @param string $option_prefix
	* @param string $slug
	* @param string $version
	* @param object $ad_code_manager
	* @param object $ad_panel
	* @throws \Exception
	*/
	public function __construct( $option_prefix, $version, $slug, $ad_code_manager, $ad_panel ) {

		$this->option_prefix = $option_prefix;
		$this->version = $version;
		$this->slug = $slug;
		$this->ad_code_manager = $ad_code_manager;
		$this->ad_panel = $ad_panel;

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

		$this->add_actions();

	}

	private function add_actions() {
		add_filter( 'acm_output_html', array( $this, 'filter_output_html' ), 10, 2 );
		add_filter( 'acm_display_ad_codes_without_conditionals', array( $this, 'check_conditionals' ) );
		$show_in_editor = get_option( $this->option_prefix . 'show_in_editor', '0' );
		if ( '1' === $show_in_editor ) {
			add_filter( 'content_edit_pre', array( $this, 'insert_inline_ad_in_editor' ), 10, 2 );
		} else {
			add_filter( 'the_content', array( $this, 'insert_and_render_inline_ads' ), 10 );
		}
		add_action( 'wp_head', array( $this, 'action_wp_head' ) );
	}

	/**
	 * Filter the output HTML for each ad tag to produce the code we need
	 */
	public function filter_output_html( $output_html, $tag_id ) {

		$ad_code_manager = $this->ad_code_manager;
		$ad_tags = $ad_code_manager->ad_tag_ids;

		$output_script = '';
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
				$output_script = "
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
			default:
				$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag_id );
				if ( ! empty( $matching_ad_code ) ) {
					$output_script = $this->get_code_to_insert( $tag_id );
				}
		} // End switch().

		return $output_script;

	}

	/**
	 * Whether to show ads that don't have any conditionals
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
	 */
	public function insert_and_render_inline_ads( $content = '' ) {

		global $wp_query;

		$post_type = $wp_query->queried_object->post_type;
		$post_id = $wp_query->queried_object->ID;
		$in_editor = false;

		// Should we skip rendering ads?
		$should_we_skip = $this->should_we_skip_ads( $content, $post_type, $post_id, $in_editor );
		if ( true === $should_we_skip ) {
			return $content;
		}

		// Render any `[cms_ad` shortcodes, whether they were manually added or added by this plugin
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

		$multiple_embeds = get_option( $this->option_prefix . 'multiple_embeds', '0' );
		if ( is_array( $multiple_embeds ) ) {
			$multiple_embeds = $multiple_embeds[0];
		}

		if ( '1' === $multiple_embeds ) {
			$insert_every_paragraphs = get_option( $this->option_prefix . 'insert_every_paragraphs', 4 );
			$maximum_embed_count = get_option( $this->option_prefix . 'maximum_embed_count', 10 );
			$minimum_paragraph_count = get_option( $this->option_prefix . 'minimum_paragraph_count', 6 );
		} else {
			$tag_id = get_option( $this->option_prefix . 'auto_embed_position', 'Middle' );
			$top_offset = get_option( $this->option_prefix . 'auto_embed_top_offset', 1000 );
			$bottom_offset = get_option( $this->option_prefix . 'auto_embed_bottom_offset', 400 );
		}

		$end = strlen( $content );
		$position = $end;

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
					$shortcode = $this->get_code_to_insert( 'x' . ( 100 + (int) $n ) );
					//$shortcode = "[cms_ad:$n]";
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

		return $content;

	}

	/**
	 * Insert one or more inline ads into the post editor, depending on the settings. Editors can then rearrange them as desired.
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

	}

	/**
	 * Determine whether the current post should get automatic ad insertion.
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
	 * Get ad code to insert for a given tag.
	 */
	public function get_code_to_insert( $tag_id ) {
		// get the code to insert
		$ad_code_manager = $this->ad_code_manager;
		$ad_tags = $ad_code_manager->ad_tag_ids;

		$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag_id );
		if ( ! empty( $matching_ad_code ) ) {

			$tag_type = get_option( $this->option_prefix . 'ad_tag_type', '' );
			switch ( $tag_type ) {
				case 'jx':
					break;
				case 'mjx':
					$output_html = '<script>OAS_AD("' . $tag_id . '");</script>';
					break;
				case 'nx':
					break;
				case 'sx':
					$not_tags = implode( ',', array_column( $ad_tags, 'tag' ) );
					$output_html = '<iframe src="' . $this->default_url . 'adstream_sx.ads/MP' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . mt_rand() . '@' . $not_tags . '!' . $tag_id . '" frameborder="0" scrolling="no" marginheight="0"><a href="' . $this->default_url . 'click_nx.ads/MP/' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . mt_rand() . '@' . $not_tags . '!' . $tag_id . '"><img src="' . $this->default_url . 'adstream_nx.ads/MP/' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '1' . mt_rand() . '@' . $not_tags . '!' . $tag_id . '"></a></iframe>';
					// lazy load
					$output_html = apply_filters( 'easy_lazy_loader_html', $output_html );
					break;
				case 'dx':
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
	 * Add the initialization code in the head
	 */
	public function action_wp_head() {
		$tag_type = get_option( $this->option_prefix . 'ad_tag_type', '' );
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
				break;
			default:
				# code...
				break;
		}
	}

}
