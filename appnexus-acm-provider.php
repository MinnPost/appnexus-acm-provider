<?php
/*
Plugin Name: Appnexus ACM Provider
Plugin URI:
Description:
Version: 0.0.14
Author: Jonathan Stegall
Author URI: https://code.minnpost.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: appnexus-acm-provider
*/

if ( ! class_exists( 'Ad_Code_Manager' ) ) {
	die();
}

class Appnexus_ACM_Provider extends ACM_Provider {

	private $option_prefix;
	private $version;
	private $slug;

	public $default_domain;
	public $server_path;
	public $default_url;

	public function __construct() {

		$this->option_prefix = 'appnexus_acm_provider_';
		$this->version       = '0.0.14';
		$this->slug          = 'appnexus-acm-provider';
		$this->capability    = 'manage_advertising';

		global $ad_code_manager;
		$this->ad_code_manager = $ad_code_manager;

		// setup
		$this->add_actions();

		// ACM Ad Panel
		$this->ad_panel = $this->ad_panel();

		// tags for AppNexus
		$this->ad_tag_ids = $this->ad_panel->ad_tag_ids();

		// Default fields for AppNexus
		$this->ad_code_args = $this->ad_panel->ad_code_args();

		// front end for rendering ads
		$this->front_end = $this->front_end();

		// admin settings
		$this->admin = $this->load_admin();

		parent::__construct();
	}

	/**
	* Do actions
	*
	*/
	private function add_actions() {
		add_action( 'plugins_loaded', array( $this, 'textdomain' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	* Load the admin panel
	* Creates the admin screen for the ACM Ad Code Manager
	*
	* @throws \Exception
	*/
	private function ad_panel() {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/class-' . $this->slug . '-ad-panel.php' );
		$ad_panel = new Appnexus_ACM_Provider_Ad_Panel( $this->option_prefix, $this->version, $this->slug, $this->capability, $this->ad_code_manager );
		add_filter( 'acm_ad_code_args', array( $ad_panel, 'filter_ad_code_args' ) );
		return $ad_panel;
	}

	/**
	* load the front end
	* Renders and places the ads
	*
	* @throws \Exception
	*/
	private function front_end() {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/class-' . $this->slug . '-front-end.php' );
		$front_end = new Appnexus_ACM_Provider_Front_End( $this->option_prefix, $this->version, $this->slug, $this->capability, $this->ad_code_manager, $this->ad_panel, $this->ad_tag_ids );
		return $front_end;
	}

	/**
	* load the admin stuff
	* creates admin menu to save the config options
	*
	* @throws \Exception
	*/
	private function load_admin() {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/class-' . $this->slug . '-admin.php' );
		$admin = new Appnexus_ACM_Provider_Admin( $this->option_prefix, $this->version, $this->slug, $this->capability, $this->ad_panel, $this->front_end );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		return $admin;
	}

	/**
	* Display a Settings link on the main Plugins page
	*
	* @param array $links
	* @param string $file
	* @return array $links
	* These are the links that go with this plugin's entry
	*/
	public function plugin_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$settings = '<a href="' . get_admin_url() . 'options-general.php?page=' . $this->slug . '">' . __( 'Settings', 'appnexus-acm-provider' ) . '</a>';
			array_unshift( $links, $settings );
		}
		return $links;
	}

	/**
	 * Activate plugin
	 *
	 * @return void
	 */
	public function activate() {
		// by default, only administrators can configure the plugin
		$role = get_role( 'administrator' );
		$role->add_cap( $this->capability );
	}

	/**
	 * Deactivate plugin
	 *
	 * @return void
	 */
	public function deactivate() {
		$role = get_role( 'administrator' );
		$role->remove_cap( $this->capability );
	}

	/**
	 * Load textdomain
	 *
	 * @return void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'appnexus-acm-provider', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

}

class Appnexus_ACM_WP_List_Table extends ACM_WP_List_Table {
	/**
	 * Register table settings
	 *
	 * @uses parent::__construct
	 * @return null
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'appnexus_acm_wp_list_table', //Singular label
			'plural'   => 'appnexus_acm_wp_list_table', //plural label, also this well be one of the table css class
			'ajax'     => true,
		) );
	}

	/**
	 * @return array The columns that shall be used
	 */
	function filter_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'id'           => __( 'ID', 'ad-code-manager' ),
			'tag'          => __( 'Tag', 'ad-code-manager' ),
			'tag_id'       => __( 'Tag ID', 'ad-code-manager' ),
			'tag_name'     => __( 'Tag Name', 'ad-code-manager' ),
			'priority'     => __( 'Priority', 'ad-code-manager' ),
			'operator'     => __( 'Logical Operator', 'ad-code-manager' ),
			'conditionals' => __( 'Conditionals', 'ad-code-manager' ),
		);
	}

	/**
	 * This is nuts and bolts of table representation
	 */
	function get_columns() {
		add_filter( 'acm_list_table_columns', array( $this, 'filter_columns' ) );
		return parent::get_columns();
	}

	/**
	 * Set which columns can be sortable
	 */
	function get_sortable_columns() {
		$sortable_columns = array(
			'tag'          => array( 'tag', false ),
			'tag_id'       => array( 'tag_id', false ),
			'tag_name'     => array( 'tag_name', false ),
			'priority'     => array( 'priority', false ),
			'operator'     => array( 'operator', false ),
			'conditionals' => array( 'conditionals', false ),
		);
		return $sortable_columns;
	}

	/**
	 * Sort the columns. Because of how the plugin stores array parameters, some of the structures here have to be defined manually (conditionals, especially)
	 */
	function usort_reorder( $a, $b ) {

		// If no sort, default to tag
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'post_id';
		// If no order, default to asc
		$order = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';

		// Determine sort order
		if ( isset( $a['url_vars'][ $orderby ] ) ) {
			$result = strcmp( $a['url_vars'][ $orderby ], $b['url_vars'][ $orderby ] );
		} elseif ( isset( $a[ $orderby ] ) ) {
			if ( is_array( $a[ $orderby ] ) ) {
				if ( 'conditionals' === $orderby ) {
					$result = strcmp( $a[ $orderby ][0]['function'], $b[ $orderby ][0]['function'] );
				}
			} elseif ( isset( $a[ $orderby ] ) ) {
				$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
			}
		}

		// Send final sort direction to usort
		return ( 'asc' === $order ) ? $result : -$result;
	}

	/**
	 * Prepare table data. We have to manually keep this in sync with the plugin's version because it doesn't seem like something we can get from the parent itself
	 */
	function prepare_items() {

		global $ad_code_manager;

		$screen = get_current_screen();

		$this->items = $ad_code_manager->get_ad_codes();

		if ( empty( $this->items ) ) {
			return;
		}

		/* -- Pagination parameters -- */
		//Number of elements in your table?
		$totalitems = count( $this->items ); //return the total number of affected rows

		//How many to display per page?
		$perpage = apply_filters( 'acm_list_table_per_page', 50 );

		//Which page is this?
		$paged = ! empty( $_GET['paged'] ) ? intval( $_GET['paged'] ) : '';

		//Page Number
		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}
		//How many pages do we have in total?

		$totalpages = ceil( $totalitems / $perpage );

		//adjust the query to take pagination into account

		if ( ! empty( $paged ) && ! empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args( array(
			'total_items' => $totalitems,
			'total_pages' => $totalpages,
			'per_page'    => $perpage,
		) );
		//The pagination links are automatically built according to those parameters

		/* -- Register the Columns -- */
		$columns               = $this->get_columns();
		$hidden                = array(
			'id',
		);
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		/**
		 * Items are set in Ad_Code_Manager class
		 * All we need to do is to prepare it for pagination
		 */
		$this->items = array_slice( $this->items, $offset, $perpage );

		// this is the part where we modify the items. everything else is from the parent class.
		usort( $this->items, array( $this, 'usort_reorder' ) );
		$this->items = $this->items;
	}

	/**
	 * Output a search box for the table
	 */
	public function display_tablenav( $which ) { ?>
		<form action="" method="POST">
			<?php $this->search_box( __( 'Search' ), 'search-box-id' ); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
		</form>
		<?php
	}


	/**
	 * Output the tag cell in the list table
	 */
	function column_tag( $item ) {
		$output  = isset( $item['tag'] ) ? esc_html( $item['tag'] ) : esc_html( $item['url_vars']['tag'] );
		$output .= $this->row_actions_output( $item );
		return $output;
	}


}

// add this plugin to the ACM provider list and initialize it
if ( ! function_exists( 'acm_register_appnexus_slug' ) ) :
	add_filter( 'acm_register_provider_slug', 'acm_register_appnexus_slug' );
	function acm_register_appnexus_slug( $providers ) {
		$providers->appnexus = array(
			'provider' => 'Appnexus_ACM_Provider',
			'table'    => 'Appnexus_ACM_WP_List_Table',
		);
		return $providers;
	}
endif;
