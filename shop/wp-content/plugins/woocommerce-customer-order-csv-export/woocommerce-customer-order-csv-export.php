<?php
/**
 * Plugin Name: WooCommerce Customer/Order CSV Export
 * Plugin URI: http://www.woothemes.com/products/ordercustomer-csv-export/
 * Description: Easily download customers & orders in CSV format and automatically export FTP or HTTP POST on a recurring schedule
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com
 * Version: 3.0.1
 * Text Domain: woocommerce-customer-order-csv-export
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2014 SkyVerge (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Customer-Order-CSV-Export
 * @author    SkyVerge
 * @category  Export
 * @copyright Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '914de15813a903c767b55445608bf290', '18652' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '2.0', __( 'WooCommerce Customer/Order CSV Export', 'woocommerce-customer-order-csv-export' ), __FILE__, 'init_woocommerce_customer_order_csv_export' );

function init_woocommerce_customer_order_csv_export() {

/**
 * # WooCommerce Customer/Order CSV Export
 *
 * ## Plugin Overview
 *
 * This plugin exports customers and orders in CSV format. Customers can be exported via
 * CSV Export > Export and are selected from orders in a selectable date range. Orders can be
 * exported in bulk from CSV Export > Export and from the Orders / Edit Order screen, as well as auto-exported
 * via FTP and HTTP POST on a recurring schedule.
 *
 * ## Class Description
 *
 * The main class for Customer/Order CSV Export. This class handles general lifecycle and setup functions, as well
 * as marking new orders as un-exported and handling the AJAX export action on the Order screen.
 *
 * ## Admin Considerations
 *
 * A 'CSV Export' sub-menu item is added under 'WooCommerce', with two tabs: 'Export' for handling bulk exports of
 * both customers and orders, and 'Settings' which define the output format for both customers and orders, as well as
 * auto-export interval & FTP/HTTP POST settings.
 *
 * An 'Export Status' column is added to the Orders list table, along with a new order action icon for downloading the order
 * to a CSV. Another order action is added to the Edit Order screen under the order actions select box.
 *
 * ## Database
 *
 * ### Options Table
 *
 * + `wc_customer_order_csv_export_order_format` - order export format
 * + `wc_customer_order_csv_export_customer_format` - customer export format
 * + `wc_customer_order_csv_export_order_filename` - filename used for order exports
 * + `wc_customer_order_csv_export_customer_filename` - filename used for customer exports
 * + `wc_customer_order_csv_export_auto_export_method` - export method for auto-exports, defaults to 'disabled'
 * + `wc_customer_order_csv_export_auto_export_interval` - export interval for auto-exports, in minutes
 * + `wc_customer_order_csv_export_auto_export_statuses` - array of order statuses that are valid for auto-export
 * + `wc_customer_order_csv_export_ftp_server` - FTP server
 * + `wc_customer_order_csv_export_ftp_username` - FTP username
 * + `wc_customer_order_csv_export_ftp_password` - FTP password
 * + `wc_customer_order_csv_export_ftp_port` - FTP port
 * + `wc_customer_order_csv_export_ftp_path` - FTP initial path
 * + `wc_customer_order_csv_export_ftp_security` - type of FTP security, e.g. 'sftp'
 * + `wc_customer_order_csv_export_passive_mode` - whether to enable passive mode for FTP connections
 * + `wc_customer_order_csv_export_http_post_url` - the URL to POST exported CSV data to, when HTTP POST is enabled as a method
 * + `wc_customer_order_csv_export_version` the plugin version, set on install & upgrade
 *
 * ### Order Meta
 *
 * + `_wc_customer_order_csv_export_is_exported` - bool, indicates if an order has been auto-exported or not, set on post insert

 * ## Cron
 *
 * + `wc_customer_order_csv_export_auto_export_interval` - custom interval for auto-export action
 * + `wc_customer_order_csv_export_auto_export_orders` - custom hook for auto-exporting orders
 *
 */
class WC_Customer_Order_CSV_Export extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '3.0.1';

	/** plugin id */
	const PLUGIN_ID = 'customer_order_csv_export';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-customer-order-csv-export';

	/** @var \WC_Customer_Order_CSV_Export_Admin instance */
	public $admin;

	/** @var \WC_Customer_Order_CSV_Export_Compatibility instance */
	public $compatibility;

	/** @var \WC_Customer_Order_CSV_Export_Cron instance */
	public $cron;


	/**
	 * Setup main plugin class
	 *
	 * @since 3.0
	 * @return \WC_Customer_Order_CSV_Export
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN
		);

		// required files
		$this->includes();

		// Set orders as not-exported when created
		add_action( 'wp_insert_post',  array( $this, 'mark_order_not_exported' ), 10, 2 );

		// Admin
		if ( is_admin() ) {

			// handle single order CSV export download from order action button
			add_action( 'wp_ajax_wc_customer_order_csv_export_export_order', array( $this, 'process_ajax_export_order' ) );

			if ( ! defined( 'DOING_AJAX' ) ) {

				$this->admin_includes();
			}
		}
	}


	/**
	 * Set each new order as not exported. This is done because querying orders that have a specific meta key / value
	 * is much more reliable than querying orders that don't have a specific meta key / value AND prevents accidental
	 * export of a massive set of old orders on first run
	 *
	 * @since 3.0
	 * @param int $post_id new order ID
	 * @param object $post the post object
	 */
	public function mark_order_not_exported( $post_id, $post ) {

		if ( 'shop_order' == $post->post_type ) {

			// force unique, because oddly this can be invoked when changing the status of an existing order
			add_post_meta( $post_id, '_wc_customer_order_csv_export_is_exported', 0, true );
		}
	}


	/**
	 * Downloads order in XML format (from order action button on 'Orders' page)
	 *
	 * @since 3.0
	 */
	public function process_ajax_export_order() {

		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {

			wp_die( __( 'You do not have sufficient permissions to access this page.', WC_Customer_Order_CSV_Export::TEXT_DOMAIN ) );
		}

		if ( ! check_admin_referer( 'wc_customer_order_csv_export_export_order' ) ) {

			wp_die( __( 'You have taken too long, please go back and try again.', WC_Customer_Order_CSV_Export::TEXT_DOMAIN ) );
		}

		$order_id = ! empty( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : '';

		if ( ! $order_id ) {

			die;
		}

		$export = new WC_Customer_Order_CSV_Export_Handler( $order_id );

		$export->download();

		wp_redirect( wp_get_referer() );

		exit;
	}


	/**
	 * Includes required classes
	 *
	 * @since 3.0
	 */
	public function includes() {

		// handles exporting / uploading / emailing
		require_once( 'includes/class-wc-customer-order-csv-export-handler.php' );
		require_once( 'includes/export-methods/interface-wc-customer-order-csv-export-method.php' );

		// handles generating CSV
		require_once( 'includes/class-wc-customer-order-csv-export-generator.php' );

		// compatibility for legacy export formats
		require_once( 'includes/class-wc-customer-order-csv-export-compatibility.php' );
		$this->compatibility = new WC_Customer_Order_CSV_Export_Compatibility();

		// handles scheduling and execution of automatic export / upload
		require_once( 'includes/class-wc-customer-order-csv-export-cron.php' );
		$this->cron = new WC_Customer_Order_CSV_Export_Cron();
	}


	/**
	 * Loads the Admin & AJAX classes
	 *
	 * @since 3.0
	 */
	public function admin_includes() {

		// loads the admin settings page and adds functionality to the order admin
		require_once( 'includes/admin/class-wc-customer-order-csv-export-admin.php' );
		$this->admin = new WC_Customer_Order_CSV_Export_Admin();

		// message handler
		$this->admin->message_handler = $this->get_message_handler();
	}


	/**
	 * Load plugin text domain.
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {

		load_plugin_textdomain( 'woocommerce-customer-order-csv-export', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/** Admin Methods ******************************************************/


	/**
	 * Render a notice for the user to select their desired export format
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::render_admin_notices()
	 */
	public function render_admin_notices() {

		// show any dependency notices
		parent::render_admin_notices();

		// add notice for selecting export format
		if ( ! $this->is_message_dismissed( 'export-format-notice' ) ) {

			$this->add_dismissible_notice(
				sprintf( __( 'Thanks for installing the Customer/Order CSV Export plugin! To get started, please %sset your export format%s. ', WC_Customer_Order_CSV_Export::TEXT_DOMAIN ), '<a href="' . $this->get_settings_url() . '">', '</a>' ),
				'export-format-notice'
			);
		}
	}


	/** Helper Methods ******************************************************/


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {

		return __( 'WooCommerce Customer/Order CSV Export', self::TEXT_DOMAIN );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {

		return __FILE__;
	}


	/**
	 * Gets the plugin documentation url, which for Customer/Order CSV Export is non-standard
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {

		return 'http://docs.woothemes.com/document/ordercustomer-csv-exporter/';
	}


	/**
	 * Gets the URL to the settings page
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @param string $_ unused
	 * @return string URL to the settings page
	 */
	public function get_settings_url( $_ = '' ) {

		return admin_url( 'admin.php?page=wc_customer_order_csv_export&tab=settings' );
	}


	/**
	 * Returns true if on the gateway settings page
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {

		return ( isset( $_GET['page'] ) && 'wc_customer_order_csv_export' == $_GET['page'] );
	}


	/**
	 * Returns conditional dependencies based on the FTP security selected
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::get_dependencies()
	 * @return array of dependencies
	 */
	protected function get_dependencies() {

		$ftp_security = get_option( 'wc_customer_order_csv_export_ftp_security' );

		if ( 'sftp' == $ftp_security ) {

			return array( 'ssh2' );
		}

		if ( 'ftp_ssl' == $ftp_security ) {

			return array( 'curl' );
		}

		return array();
	}


	/** Lifecycle Methods ******************************************************/


	/**
	 * Install default settings
	 *
	 * @since 3.0
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		require_once( 'includes/admin/class-wc-customer-order-csv-export-admin.php' );

		foreach ( WC_Customer_Order_CSV_Export_Admin::get_settings( 'settings' ) as $setting ) {

			if ( isset( $setting['default'] ) ) {

				update_option( $setting['id'], $setting['default'] );
			}
		}
	}


} // end \WC_Customer_Order_CSV_Export class


/**
 * The WC_Customer_Order_CSV_Export global object
 * @name $wc_customer_order_csv_export
 * @global WC_Customer_Order_CSV_Export $GLOBALS['wc_customer_order_csv_export']
 */
$GLOBALS['wc_customer_order_csv_export'] = new WC_Customer_Order_CSV_Export();

} // init_woocommerce_customer_order_csv_export()
