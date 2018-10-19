<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/12/14
 * Time: 11:49 AM
 */

namespace H2\ShipCompliant\WooCommerce;

use H2\ShipCompliant\AddressManager;
use H2\ShipCompliant\Compliance;
use H2\ShipCompliant\Model\Order;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\ProductSync;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\Session;
use H2\ShipCompliant\Util\StringDecorator;
use H2\ShipCompliant\Util\View;

class Checkout {


	public static function init() {
		$instance = new Checkout();
		add_action('woocommerce_checkout_init', array( $instance, 'loadAddressSuggestAssets' ));
		add_action('woocommerce_before_order_notes', array( $instance, 'addCheckoutFields' ));
		add_action('woocommerce_checkout_process', array( $instance, 'processCheckoutFields' ));
		add_action('woocommerce_checkout_update_order_meta', array( $instance, 'updateOrderMeta' ));
		add_action('woocommerce_payment_complete_order_status', array( $instance, 'afterOrderProcessed' ), 10, 2);
		//add_action('woocommerce_checkout_order_processed', array($instance, 'afterOrderProcessed'));

		// register ajax actions
		add_action('wp_ajax_verify_address', array( $instance, 'verifyAddress' ));
		add_action('wp_ajax_nopriv_verify_address', array( $instance, 'verifyAddress' ));
	}


	public function loadAddressSuggestAssets() {
		$css_path    = sprintf("%s/assets/styles/checkout.css", SHIPCOMPLIANT_PLUGIN_URL);
		$script_path = sprintf("%s/assets/js/checkout.js", SHIPCOMPLIANT_PLUGIN_URL);

		wp_enqueue_style('shipcompliant-checkout', $css_path);
		if( ! Plugin::getInstance()->disableAddressSuggest()) {
			wp_enqueue_script('shipcompliant-checkout', $script_path, array(
				'jquery',
				'underscore',
				'backbone'
			), '4.1');
		}

		if(!defined('DOING_AJAX')) {
			echo '<div id="billing-suggest"></div>';
			echo '<div id="shipping-suggest"></div>';
			echo "<script id='tpl-suggest' type='text/html'>";
			View::noticeToJST();
			echo "</script>";
		}
	}

	public function verifyAddress() {
		$address     = $_POST['address'];
		$sd          = new StringDecorator($address['Zip']);
		$zipPlusFour = $sd->splitZipPlusFour();

		$address['Zip1'] = $zipPlusFour['zip'];
		$address['Zip2'] = $zipPlusFour['plus_four'];

		unset( $address['Zip'] );

		$addressManager = new AddressManager();
		$result         = $addressManager->validateAddress($address);

		Logger::debug('Checkout::verifyAddress()', array( 'input' => $address, 'result' => $result ));

		header('Content-type: application/json');
		echo json_encode($result);
		exit;
	}

	public function afterOrderProcessed($status, $order_id) {
		global $woocommerce;
		if( ! $order_id) {
			Logger::info('Could not retrieve order_id afterOrderProcessed');

			return 'wc-failed';
		} else {
			Logger::debug( 'afterOrderProcessed::order_id', array( $order_id ) );
		}

		// set the order key in the session
		Session::set('SalesOrderKey', $order_id);

		ProductSync::remove_actions();

		$order = new Order($order_id);

		// get order errors
		$errors = Session::get( 'ComplianceErrors' );

		// do a extra compliance check with actual order number to avoid failure commit ResponseStatus
		Logger::debug( 'afterOrderProcessed::extra_compliance_check', array( $order_id ) );
		if (!$errors) {
			$complianceMode = Plugin::getInstance()->getConfig('compliance_mode');
			$compliance     = new Compliance();
			try {
				$response = $compliance->doRealTimeCheck($_POST);
			} catch(\Exception $ex) {
				$woocommerce->add_message('extra compliance check - '.$ex->getMessage());
			}
		}

		$complianceMode = Plugin::getInstance()->getComplianceMode();
		switch($complianceMode) {
			case Compliance::COMPLIANCE_MODE_REJECT:
				if (!$errors) {
					Logger::debug('Checkout::afterOrderProcessed() - COMPLIANCE_MODE_REJECT - Order is Compliant', array(
						'compliance_mode' => 'COMPLIANCE_MODE_REJECT',
						'order_id'        => $order_id
					));
					// commit the order
					$order->commit();
					return 'wc-processing';
				} else {
					Logger::debug('Checkout::afterOrderProcessed() - COMPLIANCE_MODE_REJECT - Rejecting Non-compliant Order', array(
						'compliance_mode' => 'COMPLIANCE_MODE_REJECT',
						'order_id'        => $order_id
					));
					$woocommerce->add_message('Cannot commit non-compliant orders.');
					return 'wc-cancelled';
				}

			case Compliance::COMPLIANCE_MODE_QUARANTINE:
				if (!$errors) {
					Logger::debug('Checkout::afterOrderProcessed() - COMPLIANCE_MODE_QUARANTINE - Order is Compliant', array(
						'compliance_mode' => 'COMPLIANCE_MODE_QUARANTINE',
						'order_id'        => $order_id
					));
					// commit the order
					$order->commit();
					return 'wc-processing';
				} else {
					Logger::debug('Checkout::afterOrderProcessed() - Quarantined Order', array(
						'compliance_mode' => 'COMPLIANCE_MODE_QUARANTINE',
						'order_id'        => $order_id
					));
					$order->quarantine();
					return 'wc-on-hold';
				}

			case Compliance::COMPLIANCE_MODE_OVERRIDE:
			default:
				Logger::debug('Checkout::afterOrderProcessed() - LEVEL1 Compliance Override', array(
					'compliance_mode' => 'COMPLIANCE_MODE_OVERRIDE',
					'order_id'        => $order_id
				));
				// commit the order
				$order->commitOverride();

				return 'wc-processing';
		}

	}


	/**
	 * Add Age Verification Fields to checkout
	 *
	 * @param $checkout
	 */
	public function addCheckoutFields($checkout) {
		ProductSync::remove_actions();
//        TaxManager::getInstance()->remove_actions_for_checkout();

		$years = array();
		for($i = intval(date('Y')); $i > 1900; $i --) {
			$years[ $i ] = $i;
		}

		echo '<div id="shipcompliant_dob">';
		echo '<h2>' . __('Date of Birth') . '</h2>';

		woocommerce_form_field('dob_month', array(
			'type'        => 'text',
			'required'    => true,
			'class'       => array( 'dob_month' ),
			'label'       => __('Month'),
			'placeholder' => __('MM'),
			'size'        => 2,
			'maxlength'   => 2
		), $checkout->get_value('dob_month'));

		woocommerce_form_field('dob_day', array(
			'type'        => 'text',
			'required'    => true,
			'class'       => array( 'dob-day' ),
			'label'       => __('Day'),
			'placeholder' => __('DD'),
			'size'        => 2,
			'maxlength'   => 2
		), $checkout->get_value('dob_day'));

		woocommerce_form_field('dob_year', array(
			'type'        => 'select',
			'required'    => true,
			'class'       => array( 'dob-year' ),
			'label'       => __('Year'),
			'placeholder' => __('YYYY'),
			'options'     => $years
		), $checkout->get_value('dob_year'));

		echo '</div>';

	}

	private function isValidAge($dob) {
		$dob     = new \DateTime($dob);
		$min_age = new \DateTime('now - ' . 21 . 'years');

		return $dob <= $min_age;
	}

	/**
	 * Do additional form submission validation.
	 * This is where we call ShipCompliant for CheckComplianceOfSalesOrder
	 */
	public function processCheckoutFields() {
		global $woocommerce;
		ProductSync::remove_actions();

		//if there are any session errors clear them out in preperation for compliance check
		$existing_errors = Session::get('ComplianceErrors');
		Logger::debug('Checkout::processCheckoutFields - Here are any existing errors set in the session: ', $existing_errors);
		if($existing_errors){
			Session::remove('ComplianceErrors');
			Logger::debug('Checkout::processCheckoutFields - Errors have been removed from the session', Session::all());
		}


		$complianceMode = Plugin::getInstance()->getConfig('compliance_mode');
		$compliance     = new Compliance();
		try {
			Logger::debug('Checkout::processCheckoutFields()', array('post' => $_POST));
			$response = $compliance->doRealTimeCheck($_POST);


			Logger::debug( 'processCheckoutFields::response', array( $response ) );
			$result = $response->CheckComplianceOfSalesOrderResult;
			Logger::debug( 'processCheckoutFields::result', array( $result ) );


			if(strtolower($result->ResponseStatus) !== 'success') {
				$errors = $response->CheckComplianceOfSalesOrderResult->Errors->Error;
				if( ! $this->isValidAge($compliance->getDateOfBirth($_POST))) {
					$woocommerce->add_message('You must be at least 21 years old to place this order.');
				}
			} else {
				// save compliance errors to order
				$errors             = array();
				$complianceResponse = $result->SalesOrder->Shipments->ShipmentComplianceResponse;


				Logger::debug( 'processCheckoutFields::complianceResponse', array( $complianceResponse ) );
				if( ! (bool) $complianceResponse->IsCompliant ) {
					if ( is_array( $complianceResponse->Rules->RuleComplianceResponse ) ) {
						foreach($complianceResponse->Rules->RuleComplianceResponse as $rule) {
							$errors[] = $rule->ComplianceDescription;
						}
					} else {
						$errors[] = $complianceResponse->Rules->RuleComplianceResponse->ComplianceDescription;
					}
				}

				//TESTING
				//$errors = !empty($errors) ? json_encode($errors) : '';
				//Logger::error('RAW ERRORS?', $errors);

				if ($errors) {
					Logger::error( 'ComplianceErrors::Order' , array( json_encode($errors)) );
					Session::set( 'ComplianceErrors', $errors );

					Logger::error( 'Order is not compliant');
					switch($complianceMode) {
						case Compliance::COMPLIANCE_MODE_REJECT:
							$error_message = 'We\'re sorry. We are unable fufill your order. ';
							foreach ($errors as $error => $error_value) {
								$error_message .= $error_value;
							}
							$woocommerce->add_message($error_message);
							Logger::info('complianceMode::COMPLIANCE_MODE_REJECT');
							break;
						case Compliance::COMPLIANCE_MODE_QUARANTINE:
							// do something to mark this order as quarantined

							Logger::info('complianceMode::COMPLIANCE_MODE_QUARANTINE');
							break;
						case Compliance::COMPLIANCE_MODE_OVERRIDE:
						default:
							// dont worry, be happy.
							Logger::info('complianceMode::COMPLIANCE_MODE_OVERRIDE');
							break;
					}

				}


			}

		} catch(\Exception $ex) {
			$woocommerce->add_message('Processing checkout fields - '.$ex->getMessage());
		}
	}

	/**
	 * Update the order meta with field value
	 **/
	function updateOrderMeta($order_id) {
		ProductSync::remove_actions();

		( $_POST['dob_month'] ) ? update_post_meta($order_id, 'dob_month', esc_attr($_POST['dob_month'])) : 0;
		( $_POST['dob_year'] ) ? update_post_meta($order_id, 'dob_year', esc_attr($_POST['dob_year'])) : 0;
		( $_POST['dob_day'] ) ? update_post_meta($order_id, 'dob_day', esc_attr($_POST['dob_day'])) : 0;
	}
}
