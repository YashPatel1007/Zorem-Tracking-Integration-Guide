<?php

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Trackers {		
	
	/**
	 * URL to the AST Tracker API endpoint.
	 *
	 * @var string
	 */
	private static $api_url = 'https://tracking.zorem.com/wp-json/usage-tracking/v1/update';

	/**
	 * Initialize the main plugin function
	*/
	public function __construct( $plugin_name, $plugin_slug, $user_id ,$setting_page_type, $setting_page_location, $parent_menu_type,  $menu_slug ) {				
		$this->plugin_name = $plugin_name;
		$this->plugin_slug = $plugin_slug;
		$this->user_id = $user_id;
		$this->setting_page_type = $setting_page_type;
		$this->setting_page_location = $setting_page_location;
		$this->parent_menu_type = $parent_menu_type;
		$this->menu_slug = $menu_slug;
		$this->plugin_slug_with_hyphens = str_replace('-', '_', $this->plugin_slug);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_plugin_styles'));
		$this->init();			
	}
	
	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;
	
	/**
	 * Get the class instance
	 *
	 * @return WC_Advanced_Shipment_Tracking_Settings
	*/
	public static function get_instance( $plugin_name, $plugin_slug, $user_id ,$setting_page_type, $setting_page_location, $parent_menu_type,  $menu_slug ) {

		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_name, $plugin_slug, $user_id ,$setting_page_type, $setting_page_location, $parent_menu_type,  $menu_slug );
		}

		return self::$instance;
	}
	/**
	 * Set custom data for tracking
	 *
	 * @param array $data Custom data array
	 */
	
	/*
	* init from parent mail class
	*/
	public function init() {
		
		//add_action( 'before_plugin_settings', array( $this, 'usage_data_signup_box' ) );
		add_action( 'wp_ajax_' . $this->plugin_slug_with_hyphens . '_activate_usage_data', array( $this, 'ast_activate_usage_data_fun') );
		add_action( 'wp_ajax_' . $this->plugin_slug_with_hyphens . '_skip_usage_data', array( $this, 'ast_skip_usage_data_fun') );	
		add_action( 'zorem_usage_data_' . $this->plugin_slug_with_hyphens, array( $this, 'send_tracking_data' ) );	
		add_action( 'init' , array( $this, 'load_admin_page' ) );
		
		add_action( 'admin_init', array( $this, 'set_unset_usage_data_cron') );
	
	}
	public function enqueue_plugin_styles() {
		// Enqueue your CSS file
		wp_enqueue_style('plugin-css', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
		wp_enqueue_script('plugin-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', array(), '1.0.0');
		 
		wp_localize_script('plugin-js', 'zorem_tracking_data', [
			'plugin_slug_with_hyphens' => $this->plugin_slug_with_hyphens,
		]);
		
	}
	public function load_admin_page() {

		if (isset($_GET['page']) && $_GET['page'] === $this->menu_slug) {
			if (!get_option($this->plugin_slug_with_hyphens . '_usage_data_selector')) {
				
				$this->usage_data_signup_box();
			}
			
		}
	}
	public function usage_data_signup_box() {
		
		include 'views/usage_data_signup_box.php';
	}

	public function ast_activate_usage_data_fun() {
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			exit( 'You are not allowed' );
		}
		
		check_ajax_referer( $this->plugin_slug_with_hyphens . '_usage_data_form', $this->plugin_slug_with_hyphens . '_usage_data_form_nonce' );

		if ( isset( $_POST[ $this->plugin_slug_with_hyphens . '_optin_email_notification' ] ) && 0 == $_POST[ $this->plugin_slug_with_hyphens . '_optin_email_notification' ] && isset( $_POST[ $this->plugin_slug_with_hyphens . '_enable_usage_data' ] ) && 0 == $_POST[ $this->plugin_slug_with_hyphens . '_enable_usage_data' ] ) {
			update_option( $this->plugin_slug_with_hyphens . '_usage_data_selector', true );
			die();
		}

		if ( isset( $_POST[ $this->plugin_slug_with_hyphens . '_optin_email_notification' ] ) ) {						
			update_option( $this->plugin_slug_with_hyphens . '_optin_email_notification', wc_clean( $_POST[ $this->plugin_slug_with_hyphens . '_optin_email_notification' ] ) );
		}

		if ( isset( $_POST[ $this->plugin_slug_with_hyphens . '_enable_usage_data' ] ) ) {						
			update_option( $this->plugin_slug_with_hyphens . '_enable_usage_data', wc_clean( $_POST[ $this->plugin_slug_with_hyphens . '_enable_usage_data' ] ) );			
		}

		$this->set_unset_usage_data_cron();

		update_option( $this->plugin_slug_with_hyphens . '_usage_data_selector', true );		
	}

	public function ast_skip_usage_data_fun() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			exit( 'You are not allowed' );
		}

		check_ajax_referer( $this->plugin_slug_with_hyphens . '_usage_skip_form', $this->plugin_slug_with_hyphens . '_usage_skip_form_nonce' );

		update_option( $this->plugin_slug_with_hyphens . '_usage_data_selector', true );
		update_option( $this->plugin_slug_with_hyphens . '_optin_email_notification', 0 );
		update_option( $this->plugin_slug_with_hyphens . '_enable_usage_data', 0 );

		$this->set_unset_usage_data_cron();
	}

	public function set_unset_usage_data_cron() {
		$ast_enable_usage_data = get_option( $this->plugin_slug_with_hyphens . '_enable_usage_data', 0 );
		$ast_optin_email_notification = get_option( $this->plugin_slug_with_hyphens . '_optin_email_notification', 0 );

		if ( 0 == $ast_enable_usage_data && 0 == $ast_optin_email_notification ) {
			wp_clear_scheduled_hook( 'zorem_usage_data_' . $this->plugin_slug_with_hyphens );
		} else if ( ! wp_next_scheduled ( 'zorem_usage_data_' . $this->plugin_slug_with_hyphens ) ) {
			wp_schedule_event( time() + 10, 'weekly', 'zorem_usage_data_' . $this->plugin_slug_with_hyphens );
		}
	}

	public function send_tracking_data() {
		
		// Don't trigger this on AJAX Requests.
		if ( Constants::is_true( 'DOING_AJAX' ) ) {
			return;
		}
		
		$ast_enable_usage_data = get_option( $this->plugin_slug_with_hyphens . '_enable_usage_data', 0 );
		$ast_optin_email_notification = get_option( $this->plugin_slug_with_hyphens . '_optin_email_notification', 0 );

		if ( 0 == $ast_enable_usage_data && 0 == $ast_optin_email_notification ) {
			return;
		}
		
		// Update time first before sending to ensure it is set.
		update_option( $this->plugin_slug_with_hyphens . '_usage_tracker_last_send', time() );

		$params = $this->get_tracking_data();
		
		wp_safe_remote_post(
			self::$api_url,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false,
				'headers'     => array( 'user-agent' => 'zoremTracker/' . md5( esc_url_raw( home_url( '/' ) ) ) . ';' ),
				'body'        => wp_json_encode( $params ),
				'cookies'     => array(),
			)
		);
	}

	/**
	 * Get all the tracking data.
	 *
	 * @return array
	 */
	public function get_tracking_data() {
		$data = array();

		$ast_enable_usage_data = get_option( $this->plugin_slug_with_hyphens . '_enable_usage_data', 0 );
		$ast_optin_email_notification = get_option( $this->plugin_slug_with_hyphens . '_optin_email_notification', 0 );
		
		// General site info.
		$data['plugin'] = $this->plugin_name;
		$data['plugin_slug'] = $this->plugin_slug;
		$data['setting_page_type'] = $this->setting_page_type;
		$data['setting_page_location'] = $this->setting_page_location;
		$data['parent_menu_type'] = $this->parent_menu_type;
		$data['menu_slug'] = $this->menu_slug;
		$data['user_id'] = $this->user_id;
		$data['url']   = home_url();
		$data['email'] = get_option( 'admin_email' );
		$data['opt_in'] = $ast_optin_email_notification;
		
		if ( 1 == $ast_enable_usage_data ) {

			$data['theme'] = $this->get_theme_info();

			// WordPress Info.
			$data['wp'] = $this->get_wordpress_info();

			// Server Info.
			$data['server'] = $this->get_server_info();

			// Plugin info.
			$all_plugins              = $this->get_all_plugins();
			$data['active_plugins']   = $all_plugins['active_plugins'];		

			// Shipping method info.
			$data['shipping_methods'] = $this->get_active_shipping_methods();	
			// get_order_counts
			$data['get_order_counts'] = $this->get_order_counts();
			
			$data['currency'] = get_woocommerce_currency();

			$data['country'] = WC()->countries->get_base_country();

			$data['get_order_value'] = $this->get_order_value();
			
		}

		return $data;
	}

	/**
	 * Get the current theme info, theme name and version.
	 *
	 * @return array
	 */
	public function get_theme_info() {
		$theme_data           = wp_get_theme();
		$theme_child_theme    = wc_bool_to_string( is_child_theme() );
		$theme_wc_support     = wc_bool_to_string( current_theme_supports( 'woocommerce' ) );
		$theme_is_block_theme = wc_bool_to_string( wc_current_theme_is_fse_theme() );

		return array(
			'name'        => $theme_data->Name, // @phpcs:ignore
			'version'     => $theme_data->Version, // @phpcs:ignore
			'child_theme' => $theme_child_theme,
			'wc_support'  => $theme_wc_support,
			'block_theme' => $theme_is_block_theme,
		);
	}

	/**
	 * Get WordPress related data.
	 *
	 * @return array
	 */
	public function get_wordpress_info() {
		$wp_data = array();

		$memory = wc_let_to_num( WP_MEMORY_LIMIT );

		if ( function_exists( 'memory_get_usage' ) ) {
			$system_memory = wc_let_to_num( @ini_get( 'memory_limit' ) );
			$memory        = max( $memory, $system_memory );
		}

		// WordPress 5.5+ environment type specification.
		// 'production' is the default in WP, thus using it as a default here, too.
		$environment_type = 'production';
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$environment_type = wp_get_environment_type();
		}

		$wp_data['memory_limit'] = size_format( $memory );
		$wp_data['debug_mode']   = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Yes' : 'No';
		$wp_data['locale']       = get_locale();
		$wp_data['version']      = get_bloginfo( 'version' );
		$wp_data['multisite']    = is_multisite() ? 'Yes' : 'No';
		$wp_data['env_type']     = $environment_type;
		$wp_data['dropins']      = array_keys( get_dropins() );

		return $wp_data;
	}

	/**
	 * Get server related info.
	 *
	 * @return array
	 */
	public function get_server_info() {
		$server_data = array();

		if ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_data['software'] = wc_clean( $_SERVER['SERVER_SOFTWARE'] ); // @phpcs:ignore
		}

		if ( function_exists( 'phpversion' ) ) {
			$server_data['php_version'] = phpversion();
		}

		if ( function_exists( 'ini_get' ) ) {
			$server_data['php_post_max_size']  = size_format( wc_let_to_num( ini_get( 'post_max_size' ) ) );
			$server_data['php_time_limt']      = ini_get( 'max_execution_time' );
			$server_data['php_max_input_vars'] = ini_get( 'max_input_vars' );
			$server_data['php_suhosin']        = extension_loaded( 'suhosin' ) ? 'Yes' : 'No';
		}

		$database_version             = wc_get_server_database_version();
		$server_data['mysql_version'] = $database_version['number'];

		$server_data['php_max_upload_size']  = size_format( wp_max_upload_size() );
		$server_data['php_default_timezone'] = date_default_timezone_get();
		$server_data['php_soap']             = class_exists( 'SoapClient' ) ? 'Yes' : 'No';
		$server_data['php_fsockopen']        = function_exists( 'fsockopen' ) ? 'Yes' : 'No';
		$server_data['php_curl']             = function_exists( 'curl_init' ) ? 'Yes' : 'No';

		return $server_data;
	}

	/**
	 * Get all plugins grouped into activated or not.
	 *
	 * @return array
	 */
	public function get_all_plugins() {
		// Ensure get_plugins function is loaded.
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$plugins             = get_plugins();
		$active_plugins_keys = get_option( 'active_plugins', array() );
		$active_plugins      = array();

		foreach ( $plugins as $k => $v ) {
			// Take care of formatting the data how we want it.
			$formatted         = array();
			$formatted['name'] = strip_tags( $v['Name'] );
			if ( isset( $v['Version'] ) ) {
				$formatted['version'] = strip_tags( $v['Version'] );
			}
			if ( isset( $v['Author'] ) ) {
				$formatted['author'] = strip_tags( $v['Author'] );
			}
			if ( isset( $v['Network'] ) ) {
				$formatted['network'] = strip_tags( $v['Network'] );
			}
			if ( isset( $v['PluginURI'] ) ) {
				$formatted['plugin_uri'] = strip_tags( $v['PluginURI'] );
			}
			if ( in_array( $k, $active_plugins_keys ) ) {
				// Remove active plugins from list so we can show active and inactive separately.
				unset( $plugins[ $k ] );
				$active_plugins[ $k ] = $formatted;
			} else {
				$plugins[ $k ] = $formatted;
			}
		}

		return array(
			'active_plugins'   => $active_plugins,
			'inactive_plugins' => $plugins,
		);
	}	

	/**
	 * Get a list of all active shipping methods.
	 *
	 * @return array
	 */
	public function get_active_shipping_methods() {
		$active_methods   = array();
		$shipping_methods = WC()->shipping()->get_shipping_methods();
		global $wpdb;
		
		foreach ( $shipping_methods as $id => $shipping_method ) {
			if ( isset( $shipping_method->enabled ) && 'yes' === $shipping_method->enabled ) {
				
				$shipping_stats = $wpdb->get_row( $wpdb->prepare( "SELECT FLOOR( SUM(total_sales) ) as revenue, COUNT(*) as orders, SUM(shipping_total) as shipping_charge FROM {$wpdb->prefix}wc_order_stats as stats LEFT JOIN {$wpdb->prefix}woocommerce_order_items as order_items ON(stats.order_id = order_items.order_id) WHERE order_items.order_item_name = %s", $shipping_method->method_title ) );
				
				//echo '<pre>';print_r($results);echo '</pre>';exit;
				$active_methods[ $id ] = array(
					'title' => $shipping_method->method_title,
					'orders' => $shipping_stats->orders,
					'revenue' => $shipping_stats->revenue,
					'shipping_charge' => $shipping_stats->shipping_charge,
				);
			}
		}

		return $active_methods;
	}
	/**
	 * Get a list one year average order count
	 *
	 * @return array
	 */
	public function get_order_counts() {
		global $wpdb;
		$min_max = $wpdb->get_row(
			"
			SELECT
				MIN( date_created_gmt ) as 'first', MAX( date_created_gmt ) as 'last' 
			FROM {$wpdb->prefix}wc_order_stats
		",
			ARRAY_A
		);
		if ( is_null( $min_max ) ) {
			$min_max = array(
				'first' => '-',
				'last'  => '-',
			);
		}else {
			$first = $min_max['first'];
			$last = $min_max['last'];
		}
		$firstDate = strtotime('-12 months');
		$current_date = date('Y-m-d');
		$last_12_months_start_date = date('Y-m-d', strtotime('-12 months', strtotime($current_date)));
		$orderStatuses = array('wc-completed', 'wc-processing');
		$countQuery = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*) 
				FROM {$wpdb->prefix}wc_order_stats 
				WHERE date_created_gmt >= %s 
				AND date_created_gmt <= %s
				AND status IN ('" . implode("','", $orderStatuses) . "')
				",
				$first,
				$last
			)
		);
	
		$total_orders = $countQuery;
	
		if (strtotime($first) > $firstDate) {
			$today = new DateTime();
			$firstDateObj = new DateTime($first);
			$interval = $today->diff($firstDateObj);
			$months = $interval->format('%m');
			// Perform actions if $first is greater than $firstDate
			$monthly_average = round( $total_orders / $months, 2 );
		} else {
			$monthly_average = round( $total_orders / 12, 2 );
		} 
		
	
		return $monthly_average;
	}
	
	/**
	 * Get a list one year average order value count
	 *
	 * @return array
	 */
	public function get_order_value() {
		global $wpdb;
		$min_max = $wpdb->get_row(
			"
			SELECT
				MIN(date_created_gmt) as 'first', MAX(date_created_gmt) as 'last' 
			FROM {$wpdb->prefix}wc_order_stats
			",
			ARRAY_A
		);
		
		if (is_null($min_max)) {
			$min_max = array(
				'first' => '-',
				'last'  => '-',
			);
		} else {
			$first = $min_max['first'];
			$last = $min_max['last'];
		}
		
		$orderStatuses = array('wc-completed', 'wc-processing');
		$net_total = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT SUM(net_total) 
				FROM {$wpdb->prefix}wc_order_stats 
				WHERE date_created_gmt >= %s 
				AND date_created_gmt <= %s
				AND status IN ('" . implode("','", $orderStatuses) . "')
				",
				$first,
				$last
			)
		);
		$firstDate = strtotime('-12 months');
		if (strtotime($first) > $firstDate) {
			// Calculate monthly average based on $month_count
			$today = new DateTime();
			$firstDateObj = new DateTime($first);
			$interval = $today->diff($firstDateObj);
			$months = $interval->format('%m');
			$monthly_average = $net_total / $months;
		} else {
			// Calculate monthly average based on the last 12 months
			$monthly_average = $net_total / 12;
		}
		
		return $monthly_average;
		
	}
	
}
