<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/25/14
 * Time: 9:57 PM
 */

namespace H2\ShipCompliant\Util;

class View {

	/**
	 * Convert WooCommerce php based notice templates to javascript JST
	 */
	public static function noticeToJST() {
		$html = file_get_contents( SHIPCOMPLIANT_PLUGIN_DIR . "/assets/js/templates/address-suggest.html" );
		woocommerce_get_template( "notices/notice.php", array( 'messages' => array( $html ) ) );
	}

	/**
	 * Render a php view template
	 *
	 * @param $viewName
	 * @param array $data
	 *
	 * @return string
	 */
	public static function render( $viewName, array $data = array() ) {
		$viewPath   = SHIPCOMPLIANT_PLUGIN_DIR . "views/";
		$pathToFile = $viewPath . $viewName . ".php";
		extract( $data, EXTR_SKIP );
		include( $pathToFile );
	}

} 