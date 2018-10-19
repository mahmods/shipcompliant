<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/20/14
 * Time: 6:54 PM
 */

namespace H2\ShipCompliant\API;

use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;

class AbstractService {

	/**
	 * @var null|string
	 */
	protected $service_name;

	/**
	 * @var \SoapClient
	 */
	private $soapClient;

	/**
	 * @var \H2\ShipCompliant\Security
	 */
	private $security;

	private $api_mode;

	private $firstAthentication;


	/**
	 * @param Security $security
	 * @param string $api_mode
	 */
	public function __construct( Security $security, $api_mode = null, $firstAthentication = false ) {
		$this->security = $security;
		$this->firstAthentication = $firstAthentication;

		if ( ! is_null( $api_mode ) ) {
			$this->api_mode = $api_mode;
		} else {
			$this->api_mode = Plugin::getInstance()->getConfig( 'api_mode' );
		}
//Logger::debug( 'security', $security );
//Logger::debug( 'api_mode', $api_mode );
//Logger::debug( 'firstAthentication', $firstAthentication );
		$opts = array(
            'ssl' => array(
            	//'ciphers'=>'RC4-SHA',
            	'verify_peer'=>false,
            	'verify_peer_name'=>false,
            	'allow_self_signed' => true
            ),
            'user_agent' => array(
            	'PHP/SOAP'
            )
        );
		$params = array (
			//'encoding' => 'UTF-8',
			//'verifypeer' => false,
			//'verifyhost' => false,
			'soap_version' => SOAP_1_1,
			'trace' => 1,
			'exceptions' => 1,
			//'connection_timeout' => 180,
			'cache_wsdl' => WSDL_CACHE_NONE,
			'stream_context' => stream_context_create($opts)
		);

		$page_name = basename($_SERVER['PHP_SELF']);
		$query_string = $_SERVER['QUERY_STRING'];
		$pluginReady =Plugin::getInstance()->is_plugin_ready();

		if ( $page_name === 'admin.php' && substr( $query_string, 0, 34 ) === 'page=woocommerce_settings&tab=shipcompliant' ){
			$isAuthenticationPage = true;
		} else {
			$isAuthenticationPage = false;
		}

		if ( $this->firstAthentication || Plugin::getInstance()->is_plugin_ready() ) {
//Logger::debug( 'getServiceDescriptionUrl', $this->getServiceDescriptionUrl() );
	        try {
				$this->soapClient = new \SoapClient( $this->getServiceDescriptionUrl(), $params );
				/*$this->soapClient = new \SoapClient( $this->getServiceDescriptionUrl(), array(
					'trace'        => true,
					'exceptions'   => 1,
					'soap_version' => SOAP_1_1
				) );*/
//$soapy = $this->soapClient->__getFunctions();
//Logger::debug( 'soapClient__getFunctions', $soapy );
			} catch (\SoapFault $fault) {
				Plugin::getInstance()->add_admin_notice('We are having trouble connecting with the ShipCompliant server. Please check your <a href="'.admin_url('admin.php?page=woocommerce_settings&tab=shipcompliant').'">ShipCompliant Authenication</a> settings.</p>', 'error');

				Logger::debug( "SOAP Fault", array( "faultcode: "=>$fault->faultcode, "faultstring: "=>$fault->faultstring) );
			}
		} elseif ( !$isAuthenticationPage && ( (substr( $query_string, 0, 24 ) !== "action=shipcompliant_log") && (substr( $query_string, 0, 24 ) !== "page=shipcompliant-logvi") ) && empty( $_POST ) ) {
				Plugin::getInstance()->add_admin_notice('Please enter your ShipCompliant API credentials to begin using the plugin &mdash; <a href="'.admin_url('admin.php?page=woocommerce_settings&tab=shipcompliant').'">ShipCompliant Authenication</a>.</p>', 'warning');
		}

	}


	/**
	 * @param $name
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	public function __call( $name, array $arguments = array() ) {
		$defaults = array(
			'Security' => $this->security
		);

		$request = array(
			'Request' => array_merge($defaults, $arguments[0])
		);

		// get out if username is not set
		if ( !isset( $request['Request']['Security']->Username[3] ) ) {
			Logger::error( 'Invalid username', $request['Request']['Security']->Username );
			return false;
		}


		$reflect = new \ReflectionClass( get_class($this) );
		Logger::debug( $reflect->getShortName() . '::' . $name . ' - Soap Request', array( 'arguments' => $request ) );

		$response = $this->soapClient->{$name}( $request );

		Logger::debug( $reflect->getShortName() . '::' . $name . ' - Soap Response', array( 'response' => $response ) );

		return $response;
	}

	/**
	 * @param SoapClient $soapClient
	 */
	public function setSoapClient( SoapClient $soapClient ) {
		$this->soapClient = $soapClient;
	}

	/**
	 * @return \SoapClient
	 */
	public function getSoapClient() {
		return $this->soapClient;
	}

	/**
	 * @return Response
	 */
	public function getSoapClientResponse() {
		return $this->soapClient->__getLastResponse();
	}

	/**
	 * @return Request
	 */
	public function getSoapClientRequest() {
		return $this->soapClient->__getLastRequest();
	}

	/**
	 * @param \H2\ShipCompliant\Security $security
	 */
	public function setSecurity( $security ) {
		$this->security = $security;
	}

	/**
	 * @return \H2\ShipCompliant\Security
	 */
	public function getSecurity() {
		return $this->security;
	}

	/**
	 * Gets WSDL URL for the service name of extending objects
	 * @return string
	 */
	public function getServiceDescriptionUrl() {
		switch ( $this->api_mode ) {
			case 'developer':
				$subdomain = "ws-dev";
				break;
			case 'staging':
				$subdomain = "ws-staging";
				break;
			case 'production':
			default:
				$subdomain = "ws";
				break;
		}

		$uri = sprintf( 'https://%s.shipcompliant.com/services/1.2', $subdomain );

		return sprintf( '%s/%sservice.asmx?WSDL', $uri, $this->service_name );
	}

}
