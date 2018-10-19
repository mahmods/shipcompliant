<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/28/14
 * Time: 10:14 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\Http\Request;
use H2\ShipCompliant\Http\AjaxResponse;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\View;

class LicenseActivation extends AbstractAdmin {


	public static function init() {
		$instance = parent::init();

		add_action('wp_ajax_shipcompliant_activate', array( $instance, 'ajax_activate' ));
		add_action('wp_ajax_shipcompliant_deactivate', array( $instance, 'ajax_deactivate' ));

		return $instance;
	}


	public function setup_menus() {
		$icon_url = SHIPCOMPLIANT_PLUGIN_URL . "/assets/images/shipcompliant-icon.png";
		$callback = array( $this, 'render' );

		if(Plugin::getInstance()->is_license_activated()) {
			add_submenu_page('shipcompliant-main', 'License Activation', 'License Activation', 'manage_options', 'shipcompliant-activation', $callback);
		} else {
			add_menu_page('ShipCompliant', 'ShipCompliant', 'manage_options', 'shipcompliant-activation', $callback, $icon_url, 59);
		}
	}


	/**
	 *
	 */
	public function ajax_activate() {

		if( ! Request::has('license')) {
			AjaxResponse::error('No license key was sent');
		}

		$key = Request::get('license');

		update_option('shipcompliant_license_key', $key);

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $key,
			'item_name'  => urlencode(trim(SHIPCOMPLIANT_ITEM_NAME)), // the name of our product in EDD
			'url'        => home_url()
		);
		Logger::debug('Attempting License Activation', $api_params);

		// Call the custom API.
		$response = wp_remote_get(add_query_arg($api_params, SHIPCOMPLIANT_STORE_URL),
			array(
				'timeout'   => 15,
				'sslverify' => false
			));

		// make sure the response came back okay
		if(is_wp_error($response)) {
			$msg = 'There was a network communication error with the activation server.';
			Logger::error($msg, array( 'response' => $response ));
			AjaxResponse::error($msg, 500);
		}

		// decode the license data
		$license_data = json_decode(wp_remote_retrieve_body($response));

		if($license_data->success !== true || $license_data->license !== "valid") {
			$msg = 'There was a problem activating your license key: ' . ucwords($license_data->error) .
			       "<br/>Please contact <a href='mailto:plugins@h2medialabs.com'>plugins@h2medialabs.com</a> for more information.";

			Logger::error("Error activating License Key", $license_data);
			AjaxResponse::error($msg);
		} else {

			Logger::info('License successfully activated.');
		}

		// $license_data->license will be either "valid" or "invalid"
		update_option('shipcompliant_license_status', $license_data->license);
		set_transient('shipcompliant_license_status', $license_data->license, ( 3600 * 24 )); // once a day

		$data = array(
			'license' => $key,
			'status'  => $license_data->license
		);

		AjaxResponse::success($data);
	}


	public function ajax_deactivate() {

		Logger::info('Attempting License Deactivation');

		// retrieve the license from the database
		$license = get_option('shipcompliant_license_key', true);

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode(SHIPCOMPLIANT_ITEM_NAME), // the name of our product in EDD
			'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_get(add_query_arg($api_params, SHIPCOMPLIANT_STORE_URL), array(
			'timeout'   => 15,
			'sslverify' => false
		));

		// make sure the response came back okay
		if(is_wp_error($response)) {
			$msg = 'There was a problem communicating with the activation server';
			Logger::error($msg, array( 'response' => $response ));
			AjaxResponse::error($msg, 500);
		}

		// decode the license data
		$license_data = json_decode(wp_remote_retrieve_body($response));

		// $license_data->license will be either "deactivated" or "failed"
		if($license_data->license == 'deactivated') {
			delete_option('shipcompliant_license_status');
			delete_transient('shipcompliant_license_status');
		}

		$data = array(
			'license' => $license,
			'status'  => $license_data->license
		);

		Logger::info('Successfully deactivated plugin.');

		AjaxResponse::success($data);

	}


	/**
	 * Render activation screen
	 */
	public function render() {

		$script = "activation.js";
		if(defined('WP_ENV') && WP_ENV === "production") {
			$script = "activation.min.js";
		}

		\wp_enqueue_style('shipcompliant-activation', sprintf('%s/assets/styles/activation.css', SHIPCOMPLIANT_PLUGIN_URL));
		\wp_enqueue_script('shipcompliant-activation', sprintf("%s/assets/js/%s", SHIPCOMPLIANT_PLUGIN_URL, $script), array( 'jquery' ));

		View::render("admin/activation", array(
			'license' => get_option('shipcompliant_license_key', ''),
			'status'  => get_option('shipcompliant_license_status', '')
		));

	}
}
