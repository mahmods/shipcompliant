<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/23/15
 * Time: 12:55 PM
 */

namespace H2\ShipCompliant\Http;


/**
 * Class Request
 * This class is a thin wrapper around the $_REQUEST object
 * @package LeagueCMS\Http
 */
class Request {

	public static function has($key) {
		return ! empty( $_REQUEST[ $key ] );
	}

	public static function get($key, $default = null) {
		if( ! empty( $_REQUEST[ $key ] )) {
			return $_REQUEST[ $key ];
		}

		return $default;
	}

}