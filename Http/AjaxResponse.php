<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/23/15
 * Time: 12:54 PM
 */

namespace H2\ShipCompliant\Http;

/**
 * Class AjaxResponse
 * Thin wrapper around wp_send_* functions to fix issues with modern js frameworks.
 * @package LeagueCMS\Http
 */
class AjaxResponse {

	const APPLICATION_ERROR = 400;
	const SERVER_ERROR = 500;

	public static function success(array $data = array()) {
		\wp_send_json_success($data);
	}

	/**
	 * WordPress' implementation doesnt send HTTP error status codes.
	 * @param $message
	 * @param int $status
	 */
	public static function error($message, $status = 400){
		header($message, true, $status);
		\wp_send_json_error($message);
	}
}