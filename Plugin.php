<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/25/14
 * Time: 3:38 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\Admin\Export;
use H2\ShipCompliant\Admin\Help;
use H2\ShipCompliant\Admin\Import;
use H2\ShipCompliant\Admin\LicenseActivation;
use H2\ShipCompliant\Admin\LogViewer;
use H2\ShipCompliant\Admin\ProductFields;
use H2\ShipCompliant\API\SalesOrderService;
use H2\ShipCompliant\API\Security;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\WooCommerce\Authentication;
use H2\ShipCompliant\WooCommerce\Checkout;
use H2\ShipCompliant\WooCommerce\Orders;
use H2\ShipCompliant\WooCommerce\Settings;

/**
 * Singleton Class Plugin
 * @package H2\ShipCompliant
 */
class Plugin {

	const BRANDING = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARYAAABSCAYAAACR8/uBAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoTWFjaW50b3NoKSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo4ODVENTcyODM2NDYxMUU0QjE2RUQ2OUIxOUU1MEFBNiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo4ODVENTcyOTM2NDYxMUU0QjE2RUQ2OUIxOUU1MEFBNiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjg4NUQ1NzI2MzY0NjExRTRCMTZFRDY5QjE5RTUwQUE2IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjg4NUQ1NzI3MzY0NjExRTRCMTZFRDY5QjE5RTUwQUE2Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+VifKeAAAHu9JREFUeNrsXQl8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";

	/**
	 * Singleton Instance
	 * @var \H2\Shipcompliant\Plugin
	 */
	private static $instance = null;

	/**
	 * Store combined settings information
	 * @var array
	 */
	private $config = array();

	/**
	 * @var Security
	 */
	private $security = null;

	/**
	 * @var firstAthentication
	 */
	private $firstAthentication = null;

	/**
	 * ShipCompliant "Partner Key" for H2MediaLabs
	 */
	const PARTNER_KEY = "15D3E98D-E736-4F59-9c03-775492848221";

	/**
	 * Key for Auth data stored in get_options()
	 */
	const OPTIONS_AUTH_KEY = "woocommerce_auth_settings";

	/**
	 * Key for Plugin settings stored in get_options
	 */
	const OPTIONS_SETTINGS_KEY = "woocommerce_config_settings";

	public $sc_auth;

	/**
	 * Private constructor.  Make this class a singleton.
	 */
	private function __construct() {
		register_activation_hook( SHIPCOMPLIANT_PLUGIN_FILE, array( $this, 'copy_mu_updater' ) );
		register_activation_hook( SHIPCOMPLIANT_PLUGIN_FILE, array( $this, 'activation' ) );
		register_deactivation_hook( SHIPCOMPLIANT_PLUGIN_FILE, array( $this, 'deactivation' ) );
		
		$this->readConfig();
		
		add_action( 'init', array( $this, 'wpInit' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'init', array( $this, 'initIntegrations' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'shipcompliant', '\H2\ShipCompliant\CliCommands' );
		}
	}

	public function deactivation() {
		global $wpdb;

		$original_blog_id = get_current_blog_id();

		// is multisite
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// check if it is a network activation - if so, run the deactivation function for each blog id
			if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) ) {
				$blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->deactivate_site();
				}
				switch_to_blog( $original_blog_id );

				return;
			}
		}

		$this->deactivate_site();

		return;
	}

	public function deactivate_site() {
		global $wpdb;
		// thermonuclear delete all log entries
		$wpdb->delete( $wpdb->posts, array( 'post_type' => 'shipcompliant_log' ), array( '%s' ) );
	}


	public function activation() {
		global $wpdb;

		$original_blog_id = get_current_blog_id();

		// is multisite
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) ) {
				$blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->activate_site();
				}
				switch_to_blog( $original_blog_id );

				return;
			}
		}

		$this->activate_site();

		return;
	}

	public function copy_mu_updater() {
		if ( is_multisite() ) {

			if ( ! file_exists( WPMU_PLUGIN_DIR ) ) {
				if ( mkdir( WPMU_PLUGIN_DIR ) === false ) {
					error_log( 'ShipCompliant ERROR - Couldn\'t create mu-plugins directory' );

					// TODO: display admin_notices
					return false;
				}
			}

			if ( file_exists( WPMU_PLUGIN_DIR ) && is_writable( WPMU_PLUGIN_DIR ) ) {
				$src  = sprintf( "%sshipcompliant-mu-updater.php", plugin_dir_path( SHIPCOMPLIANT_PLUGIN_FILE ) );
				$dest = sprintf( "%s/shipcompliant-mu-updater.php", WPMU_PLUGIN_DIR );

				if ( file_exists( $src ) && ! file_exists( $dest ) ) {
					error_log( "ShipCompliant INFO - Copying From:  " . $src );
					error_log( "ShipCompliant INFO - Copying To: " . $dest );

					if ( copy( $src, $dest ) === false ) {
						error_log( 'ShipCompliant ERROR - Couldn\'t copy shipcompliant-mu-updater.php to mu-plugins folder.' );

						return false;
					}
				}
			}

		}

	}

	public function activate_site() {
		global $wpdb;

		// set configuration defaults
		// Enable tax calculations
		update_option( 'woocommerce_calc_taxes', 'yes' );

		// Set exclusive
		update_option( 'woocommerce_prices_include_tax', 'no' );

		// Set "Tax based on" option to "Customer shipping address"
		update_option( 'woocommerce_tax_based_on', 'shipping' );

		// Set default customer address
		update_option( 'woocommerce_default_customer_address', 'base' );

		// Set shipping tax class to "Based on cart items"
		update_option( 'woocommerce_shipping_tax_class', '' );

		// Set "Round at subtotal level" to false
		update_option( 'woocommerce_tax_round_at_subtotal', 0 );

		// Make sure prices are displayed excluding tax
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );

		// Display taxes in "single" form (prevents duplicate sales tax row from showing)
		update_option( 'woocommerce_tax_total_display', 'single' );

		// Loop through all coupons; make sure "apply_before_tax" is set to "yes"
		$coupons = get_posts( array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => '-1',
			'fields'         => 'ids'
		) );

		foreach ( $coupons as $coupon ) {
			update_post_meta( $coupon, 'apply_before_tax', 'yes' );
		}
		wp_reset_postdata();

		$wpdb->delete( "{$wpdb->prefix}woocommerce_tax_rates", array( 'tax_rate_class' => 'shipcompliant' ) );

		// insert tax rate row
		$wpdb->insert( "{$wpdb->prefix}woocommerce_tax_rates", array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate_name'     => 'ShipCompliant',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 1,
			'tax_rate_shipping' => 0,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => 'shipcompliant'
		) );
		$tax_rate_id = $wpdb->insert_id;
		update_option( 'shipcompliant_tax_rate_id', $tax_rate_id );

		$this->add_admin_notice( 'Your WooCommerce settings have been updated to work best with the ShipCompliant Plugin. Please <a href="/wp-admin/admin.php?page=shipcompliant-activation">enter your license key</a> to begin the plugin activation process.' );

		update_option( 'shipcompliant_activation_message_shown', false );

	}

	public function admin_init() {

		wp_enqueue_style( 'shipcompliant-admin', sprintf( "%s/assets/styles/admin.css", SHIPCOMPLIANT_PLUGIN_URL ), 0.01 );
		wp_enqueue_script( 'shipcompliant-admin', sprintf( "%s/assets/js/admin.js", SHIPCOMPLIANT_PLUGIN_URL ) );

		$self = $this;
		add_action( 'admin_notices', function () use ( $self ) {
			$self->display_admin_notices();
		} );
	}

	/**
	 * Get Singleton Instance
	 * @return Plugin
	 */
	public static function getInstance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}


	/**
	 * Initialize Plugin
	 * @return \H2\Shipcompliant\Plugin
	 */
	public static function init() {
		return static::getInstance();
	}

	/**
	 * Initialize components that register hooks
	 */
	public function wpInit() {		
		Logger::register();

		if ( $this->is_license_activated() ) {
			if ( is_admin() ) {
				Admin::init();
				Import::init();
				Export::init();
				Help::init();
				Orders::init();
				ProductSync::init();
				ProductFields::init();

			} else {
				// TODO: there has got to be a better way
				wp_register_script( 'sc-ajax', sprintf( "%s/assets/js/noop.js", SHIPCOMPLIANT_PLUGIN_URL ) );
				wp_localize_script( "sc-ajax", 'ShipCompliant', array( 'ajaxurl' => admin_url( "/admin-ajax.php" ) ) );
				wp_enqueue_script( 'sc-ajax' );
			}
		}

		TaxManager::init();
		Checkout::init();
		LicenseActivation::init();
		LogViewer::init();

		$this->sc_auth = new Authentication();
	}


	public function initIntegrations() {
		// Checks if WooCommerce is installed.

		if ( class_exists( 'WC_Settings_API' ) ) {

			//Tab for ShipCompliant

			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addShipcompliantTab' ), 50 );

			//Section Links for ShipCompliant

			add_action( 'woocommerce_settings_tabs_shipcompliant', array( $this, 'output_sections' ) );

			//Section Content for ShipCompliant

			add_action( 'woocommerce_settings_shipcompliant', array( $this, 'output_shipcompliant_sections' ) );
			

		}
	}


	public function output_shipcompliant_sections() {

		global $current_section;

		if ( $current_section == 'config' ) {

			//initialize class
			$sc_config = new Settings();

			//check for any field processing
			$sc_config->process_admin_options();

			// load page
			$sc_config->admin_options();


		} elseif ('auth') {

			//initialize class
			//$sc_auth = new Authentication();

			//check for any field processing
			$this->sc_auth->process_admin_options();

			// load page
			$this->sc_auth->admin_options();

		}
	}


	public function get_sections() {

		$sections = array(
			'auth'   => __( 'ShipCompliant Authentication'),
			'config' => __( 'ShipCompliant Settings' )
		);

		return $sections;
	}


	public function output_sections() {

		global $current_section;

		$sections = $this->get_sections();

		if ( empty( $sections ) ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {

			echo '<li><a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=shipcompliant&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
		}


		echo '</ul><br class="clear" />';

		$this->output_shipcompliant_sections();
	}

	public function addShipcompliantTab( $settings_tabs ) {

		$settings_tabs['shipcompliant'] = __( 'Ship Compliant' );

		return $settings_tabs;
	}

	/**
	 * Read configuration from transient cache, else read it from the database then,
	 * updates the local Security instance with values from the config
	 */
	public function readConfig() {

		$options  = array();
		$auth     = get_option( static::OPTIONS_AUTH_KEY );
		$settings = get_option( static::OPTIONS_SETTINGS_KEY );

		if ( ! empty( $settings ) && is_array( $settings ) ) {
			$options = array_merge( $options, $settings );
		}

		if ( ! empty( $auth ) && is_array( $auth ) ) {
			$options = array_merge( $options, $auth );
		}

		$this->config = $options;
		// reset security based on configuration
		$this->security = new Security( $this->getConfig( 'username' ), $this->getConfig( 'password' ), self::PARTNER_KEY );
	}


	/**
	 * If $key is passed, will search config for key.  If not will return all config options.
	 *
	 * @param null $key
	 *
	 * @return mixed|null|void
	 */
	public function getConfig( $key = null ) {

		if ( is_null( $key ) ) {
			return $this->config;
		}

		if ( isset( $this->config[ $key ] ) ) {
			return $this->config[ $key ];
		}

		return null;
	}

	/**
	 * The current Compliance Mode
	 * @return string  REJECT, QUARANTINE, or OVERRIDE
	 */
	public function getComplianceMode() {
		$mode = $this->getConfig( 'compliance_mode' );
		if ( empty( $mode ) ) {
			$mode = Compliance::COMPLIANCE_MODE_OVERRIDE;
		}

		return $mode;
	}

	/**
	 * Returns true if user has disabled address suggestion
	 * @return bool
	 */
	public function disableAddressSuggest() {
		$mode = $this->getConfig( 'disable_address_suggest' );
		if ( empty( $mode ) ) {
			return false;
		}

		if ( $mode == "yes" ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if plugin is in debug mode
	 * @return bool
	 */
	public function isDebugMode() {
		$mode = $this->getConfig( 'debug_mode' );
		/*if ( empty( $mode ) ) {
			return false;
		}*/

		if ( empty( $mode ) || $mode == "yes" ) {
			return true;
		}

		return false;
	}


	/**
	 * Set local security object.  This is used for ship compliant api credentials.
	 *
	 * @param Security $security
	 */
	public function setSecurity( Security $security ) {
		$this->security = $security;
	}

	/**
	 * Get the local security object
	 * @return Security
	 */
	public function getSecurity() {
		return $this->security;
	}

	/**
	 * Confirm ability to authenticate with SC API
	 *
	 * (this is somewhat of a cheap hack.  it queries SalesOrderService::GetPossibleFulfillmentHouses)
	 *
	 * @param $username
	 * @param $password
	 * @param $api_mode
	 *
	 * @return bool
	 */
	public function confirmAccess( $username = null, $password = null, $api_mode = null, $firstAthentication = false ) {
//Logger::debug( 'confirmAccess - firstAthentication', $firstAthentication );
		$ready = $this->is_plugin_ready();
//Logger::debug( 'confirmAccess - ready', $ready );
		
		if ( ( $firstAthentication == false ) && ( $ready == false ) ) {
			return false;
		}

		$request  = array( "ReturnOnlyActiveForSupplier" => true );
		$security = Plugin::getInstance()->getSecurity();

		if ( ! is_null( $username ) ) {
			$security->Username = $username;
		}

		if ( ! is_null( $password ) ) {
			$security->Password = $password;
		}

		$salesOrderService = new SalesOrderService( $security, $api_mode, $firstAthentication );
		$response = $salesOrderService->getPossibleFulfillmentHouses( $request );
		try {
			$response = $salesOrderService->getPossibleFulfillmentHouses( $request );
/*echo "<pre>";
   var_dump($response);
echo "</pre><br />";*/
			//$response = $salesOrderService->GetVersion(array());
//echo 'confirmAccess - __getLastRequest: <pre>'. $salesOrderService->getSoapClientRequest()."</pre>\n<br />";
//echo 'confirmAccess - getLastResponse: <pre>'. $salesOrderService->getSoapClientResponse()."</pre>\n<br />";
		} catch ( Exception $ex ) {
			Logger::error( 'Could not confirm API access', $ex->getMessage() );
			$this->errors[] = $ex->getMessage();
			return false;
		}
//Logger::debug( 'confirmAccess - response', $response );

		if ( is_object($response) ) {
			$result = $response->GetPossibleFulfillmentHousesResult;
//Logger::debug( 'confirmAccess - result', $result );
			if ( strtolower( $result->ResponseStatus ) === "success" ) {
				Logger::info( 'Plugin::confirmAccess - Successfully authenticated with ShipCompliant API' );
				return true;
			}
		}

/*try {
	$client = $salesOrderService->getSoapClient();
echo "<pre>";
   var_dump($client->__soapCall('getPossibleFulfillmentHouses', array('parameters' => $request)));
echo "</pre><br />";
} catch (Exception $e) {
   var_dump($e);
}
echo "\nLast Request: " . $client->__getLastRequest()."\n<br />";
echo "Last Request Headers: " . $client->__getLastRequestHeaders()."\n\n<br />";
echo "Last Response: " . $client->__getLastResponse()."\n<br />";
echo "Last Response Headers: " . $client->__getLastResponseHeaders()."\n<br />";
*/

		Logger::error( 'Plugin::confirmAccess - Invalid credentials for ShipCompliant API', $response );
		return false;

	}


	public function is_license_activated() {
		return true;

		global $wp_version;

		$status = get_transient( 'shipcompliant_license_status' );

		if ( ! $status ) {

			$license = get_option( 'shipcompliant_license_key', true );

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license,
				'item_name'  => urlencode( SHIPCOMPLIANT_ITEM_NAME ),
				'url'        => home_url()
			);

			Logger::debug( 'Verifying License with Activation Server', $api_params );

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, SHIPCOMPLIANT_STORE_URL ), array(
					'timeout'   => 15,
					'sslverify' => false
				) );

			if ( is_wp_error( $response ) ) {
				Logger::error( 'Plugin::is_license_activated() - Problem communicating with License Activation server.', array( 'response' => $response ) );
				$status = false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			Logger::debug( 'License Verification Result', $license_data );

			if (  is_object( $license_data ) ) {
				$status = $license_data->license;
				update_option( 'shipcompliant_license_status', $status );
				set_transient( 'shipcompliant_license_status', $status, ( 3600 * 24 ) ); // once a day
			}
		}

		if ( $status == 'valid' ) {
			return true;
		}

		return false;

	}

	public function is_plugin_ready() {

		$api_status = get_transient( 'shipcompliant_api_status' );

		if ( ! $api_status ) {
			$api_status = get_option( 'shipcompliant_api_status' );
		}

		$license_activated = $this->is_license_activated();

		if ( $api_status && $license_activated ) {
			return true;
		} else {
			return false;
		}
	}

	public function add_admin_notice( $message, $type = 'info' ) {
		// Fetch messages
		$messages = get_transient( 'shipcompliant_flash_messages' );

		$newMessage = array( 'message' => $message, 'type' => $type );

		if ( (!$messages) or (!in_array($newMessage, $messages, true)) ) {
			$messages[] = $newMessage;
			// Update transient
			set_transient( 'shipcompliant_flash_messages', $messages, 0 );
		}
	}

	public function display_admin_notices() {

		// Fetch messages
		$messages = get_transient( 'shipcompliant_flash_messages' );

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $notice ) {
				printf( '<div id="message" class="notice notice-%s is-dismissible"><p>%s</p></div>', $notice['type'], $notice['message'] );
			}
		}

		delete_transient( 'shipcompliant_flash_messages' );

	}

}
