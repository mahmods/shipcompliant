<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/29/14
 * Time: 11:36 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\API\TaxService;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\Session;
use H2\ShipCompliant\Model\Product;

/**
 * Class TaxManager
 * @package H2\ShipCompliant
 */
class TaxManager {

	private static $instance = null;
	private static $updated = false;

	private $order_id;
	private static $countries;


	private function __construct() {
		self::$countries = new \WC_Countries();
	}

	public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new TaxManager();
		}

		return self::$instance;
	}

	public static function init() {
		$instance = static::getInstance();

		add_action( 'woocommerce_calculate_totals', array( $instance, 'calculate_totals' ), 1 );
		add_action( 'woocommerce_cart_tax_totals', array( $instance, 'cart_tax_totals' ), 1, 2 );
		add_filter( 'woocommerce_order_tax_totals', array( $instance, 'cart_tax_totals' ), 1, 2 );
		add_action( 'woocommerce_add_to_cart', array( $instance, 'add_to_cart' ), 1, 5 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $instance, 'quantity_updated' ), 1, 5 );
		//        add_action('woocommerce_checkout_order_processed', array($instance, 'process_order'));
		//        add_action('woocommerce_order_status_completed', array($instance, 'complete_order'));

		// This is a hack until WC_Geolocation lets you get a zip code
		add_filter( "woocommerce_countries_base_postcode", function ( $postcode ) {
			Logger::info( 'woocommerce_countries_base_postcode' );
			$zip_in_state = array(
				'AL'=>'35223',
				'AK'=>'99516',
				'AZ'=>'85253',
				'AR'=>'72223',
				'CA'=>'94027',
				'CO'=>'81435',
				'CT'=>'06830',
				'DE'=>'19807',
				'DC'=>'20015',
				'FL'=>'33921',
				'GA'=>'30327',
				'HI'=>'96821',
				'ID'=>'83455',
				'IL'=>'60043',
				'IN'=>'46077',
				'IA'=>'52411',
				'KS'=>'66224',
				'KY'=>'40513',
				'LA'=>'70115',
				'ME'=>'04270',
				'MD'=>'20816',
				'MA'=>'02493',
				'MI'=>'48302',
				'MN'=>'55424',
				'MS'=>'39110',
				'MO'=>'63124',
				'MT'=>'59106',
				'NE'=>'68520',
				'NV'=>'89411',
				'NH'=>'03870',
				'NJ'=>'07620',
				'NM'=>'87506',
				'NY'=>'11976',
				'NC'=>'28480',
				'ND'=>'58503',
				'OH'=>'45174',
				'OK'=>'73078',
				'OR'=>'97210',
				'PA'=>'19035',
				'RI'=>'02835',
				'SC'=>'29915',
				'TN'=>'37215',
				'TX'=>'75205',
				'UT'=>'84060',
				'VT'=>'05445',
				'VA'=>'22066',
				'WA'=>'98039',
				'WV'=>'26541',
				'WI'=>'53726',
				'WY'=>'82009'
			);
			if ( empty( $postcode ) ) {
				$state = self::$countries->get_base_state();
				$postcode = $zip_in_state[$state];
			}
			return $postcode;
		} );
	}

	public function add_to_cart() {
		$this->getCartTaxFromService();
	}

	public function quantity_updated() {
		$this->getCartTaxFromService();
	}

	public function calculate_totals( \WC_Cart $cart ) {

		if ( sizeof( $cart->cart_contents ) == 0 ) {
			return $cart;
		}

		if ( is_checkout() || is_cart() || defined( 'WOOCOMMERCE_CHECKOUT' ) || defined( 'WOOCOMMERCE_CART' ) ) {
			$tax_rate_id     = intval( get_option( 'shipcompliant_tax_rate_id' ) );
			$taxAmount       = $this->getCartTaxFromService();
			$cart->tax_total = $taxAmount;
			$cart->taxes     = array( $tax_rate_id => $taxAmount );
		}

		return $cart;

	}


	public function cart_tax_totals( $tax_totals, $cart ) {
//Logger::debug('$tax_totals', $tax_totals);
		$sc_tax_applied = false;
		foreach ( $tax_totals as $key => $obj ) {
			if ( preg_match( "/^shipcompliant/ui", $key ) ) {
				$sc_tax_applied = true;
				$tax_totals[$key]->label = "Sales and Excise Tax";
			}
		}

		if ( ! $sc_tax_applied ) {
			$tax                         = new \stdClass();
			$tax->amount                 = Session::get( 'cart_tax_total' );
			$tax->tax_rate_id            = intval( get_option( 'shipcompliant_tax_rate_id' ) );
			$tax->is_compound            = 1;
			$tax->label                  = "Sales and Excise Tax";
			$tax->formatted_amount       = wc_price( $tax->amount );
			$tax_totals["shipcompliant"] = $tax;

		}

		return $tax_totals;
	}

	/**
	 * Calculate the tax applied to each item in the cart.  The action hook that this callback registers to calculates
	 * the total cart tax by iterating each item, and applying tax by table lookup.  ShipCompliant only gives us the
	 * tax total for the entire cart.  The implementation of this method is intentionally kludgy, in that it just takes
	 * the total tax amount, and divides it by the number of items in the cart, and returns that fractional value on
	 * each pass.
	 *
	 * @param $taxes
	 * @param $price
	 * @param $rates
	 * @param $price_includes_tax
	 * @param $suppress_rounding
	 *
	 * @return array
	 */
	public function calc_tax( $taxes, $price, $rates, $price_includes_tax, $suppress_rounding ) {
		global $woocommerce;
		$taxAmount = Session::get( 'cart_tax_total' );

		$divisor = count( $woocommerce->cart->cart_contents );
		$itemTax = floatval( $taxAmount / $divisor );

		$taxes = array( $itemTax );

		return $taxes;
	}


	public function get_tax_address() {
		global $woocommerce;
		$country = $woocommerce->customer->get_shipping_country();
		$state   = $woocommerce->customer->get_shipping_state();
		$zip     = $woocommerce->customer->get_shipping_postcode();

		if ( strlen($zip) < 2 ) {
			//$countries = new \WC_Countries();
			$country   = self::$countries->get_base_country();
			$state     = self::$countries->get_base_state();
			//$zip       = self::$countries->get_base_postcode();
		}

		return array(
			'country' => $country,
			'state'   => $state,
			'zip'     => $zip
		);

	}

	private function build_product_array( $item ) {
		$id    = $item['product_id'];
		$price = get_post_meta( $id, '_price', true );

		// Check for variations and then get sku
		$product_variation_id = $item['variation_id'];
		if ($product_variation_id) {
			$product = get_product($item['variation_id']);
		} else {
			$product = get_product($item['product_id']);
		}
		$sku = $product->get_sku();

		return array(
			"ProductKey"       => $sku,
			"BrandKey"         => get_post_meta( $id, '_brand_key', true ),
			"ProductQuantity"  => $item['quantity'],
			"ProductUnitPrice" => floatval( $price )
		);
	}

	/**
	 * Get Cart Tax by address.  This is not a compliance check.
	 *
	 * @param null $cart
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getCartTaxFromService( $cart = null ) {
		global $woocommerce;

		Logger::info( 'TaxManager::getCartTaxFromService()' );

		if ( static::$updated && Session::has( 'cart_tax_total' ) ) {
			Logger::info( 'Skipping duplicate tax lookup.' );

			return Session::get( 'cart_tax_total' );
		}

		if ( is_null( $cart ) ) {
			$cart = $woocommerce->cart;
		}

		extract( $this->get_tax_address() );

		if ( ! empty( $country ) && ! empty( $state ) && ! empty( $zip ) ) {

			$products = array();
			foreach ( $cart->cart_contents as $item ) {

				// NOTE**
				// This is in order to support the WooCommerce Product Bundles plugin
				// see: http://www.woothemes.com/products/product-bundles/
				// We want to make sure to only calculate tax on the bundle, not the bundled item
				if ( ! isset( $item["bundled_item_id"] ) ) {
					$id    = $item['product_id'];
					$price = get_post_meta( $id, '_price', true );

					// Check for variations and then get sku
		            $product_variation_id = $item['variation_id'];
		            if ($product_variation_id) {
		                $product = get_product($item['variation_id']);

		            } else {
		                $product = get_product($item['product_id']);

		            }

		            $sku = $product->get_sku();
		            if (empty($sku)) {
		                $sku = get_post_meta( $id, '_sku', true );
		            }

		            if (empty($sku)){
						Logger::error( 'Could not get product SKU', $sku );
					}


					$products[] = array(
						"ProductKey"       => $sku,
						"BrandKey"         => get_post_meta( $id, '_brand_key', true ),
						"ProductQuantity"  => $item['quantity'],
						"ProductUnitPrice" => floatval( $price )
					);
				}

			}

			$request = array(
				"ShipToAddress"                => array(
					"State" => $state,
					'Zip1'  => $zip
				),
				"EffectiveDate"                => date( 'Y-m-d' ),
				"TaxSaleType"                  => "Offsite",
				"ShippingAndHandlingCollected" => 0,
				"OrderItems"                   => array(
					"CalculateSalesTaxItem" => $products
				)
			);

			$service  = new TaxService( Plugin::getInstance()->getSecurity() );
			$response = $service->CalculateSalesTaxDueForOrder( $request );

			if ( $response->CalculateSalesTaxDueForOrderResult->ResponseStatus == "Success" ) {
				$amount = $response->CalculateSalesTaxDueForOrderResult->SalesTaxDue;
				Session::set( 'cart_tax_total', $amount );
				static::$updated = true;

				return $amount;
			}

			if ( $response->CalculateSalesTaxDueForOrderResult->ResponseStatus == "Failure" ) {
				$msg    = 'A problem occurred while trying to do a tax lookup';
				$errors = "<ul>";
				foreach ( $response->CalculateSalesTaxDueForOrderResult->Errors as $error ) {
					$errors .= sprintf( "<li>%s</li>", $error->Message );
				}
				$errors .= "</ul>";
				Logger::error( $msg, $response );

				$notice = sprintf( "<p><strong>%s</strong></p>%s", $msg, $errors );

				$woocommerce->add_message( $notice );

				return 0;

			}

			$msg = 'Unknown error while trying to do a tax lookup';
			Logger::error( $msg, $response );
			$woocommerce->add_message( $msg );

			return 0;

		}
	}
}
