<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/3/14
 * Time: 6:33 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\API\SalesOrderService;
use H2\ShipCompliant\Shipping\FedExMapper;
use H2\ShipCompliant\Shipping\UPSMapper;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\Session;
use H2\ShipCompliant\Util\Generator;
use H2\ShipCompliant\Util\StringDecorator;
use H2\ShipCompliant\Model\Product;

class Compliance {

    const COMPLIANCE_MODE_REJECT     = "reject";
    const COMPLIANCE_MODE_QUARANTINE = "quarantine";
    const COMPLIANCE_MODE_OVERRIDE   = "override";

    protected $service;

    public function __construct()
    {
        $this->service = new SalesOrderService(Plugin::getInstance()->getSecurity());
    }

    /**
     * Create ShipmentItems array from cart contents
     * @return array
     */
    private function getShipmentItems()
    {
        global $woocommerce;
        $items = array();
        foreach ($woocommerce->cart->cart_contents as $item)
        {
            $id = $item['product_id'];

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

            // TODO: account for discounts
            $items[] = array(
                "BrandKey"         => get_post_meta($id, '_brand_key', true),
                "ProductKey"       => $sku,
                "ProductQuantity"  => $item['quantity'],
                "ProductUnitPrice" => floatval($item['data']->price)
            );

        }

        return array(
	        'ShipmentItem' => $items
        );
    }

    /**
     * Get cart discount
     * @return array
     */
    private function getDiscount()
    {
        global $woocommerce;
        $discount = 0;
        if ( $woocommerce->cart->get_cart_discount_total() > 0 ) $discount = $woocommerce->cart->get_cart_discount_total();

        return $discount;
    }

    /**
     * Checks the session for the current sales order key, if it doesnt exist it is generated
     * @return string GUID
     */
    public function getCurrentSalesOrderKey()
    {
        if (!Session::has( 'SalesOrderKey' ))
        {
            Session::set( 'SalesOrderKey' , Generator::getGUID() );
        }
        return Session::get( 'SalesOrderKey' );
    }

    /**
     * Checks the session for the current customer key, if it doesnt exist it is generated
     * @return string GUID or userID
     */
    public function getCurrentCustomerKey()
    {
        if (!Session::has('CustomerKey'))
        {
            $customerKey = Generator::getGUID();
            if (is_user_logged_in())
            {
                $user        = wp_get_current_user();
                $customerKey = $user->ID;
            }
            Session::set('CustomerKey', $customerKey);
        }
        return Session::get('CustomerKey');
    }


    /**
     * Grab and format the date of birth into a YYYY-MM-DD date string
     *
     * @param $args
     *
     * @return string
     */
    public function getDateOfBirth($args)
    {
        $year  = (empty($args['dob_year'])) ? date('Y') : $args['dob_year'];
        $month = (empty($args['dob_month'])) ? date('m') : $args['dob_month'];
        $day   = (empty($args['dob_day'])) ? date('d') : $args['dob_day'];

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * If the shipping address is the same as the billing address, copy the values over
     *
     * @param array $args
     *
     * @return array
     */
    private function populateShippingAddress(array $args)
    {
        // if the shipping address is the same as the billing address
        // copy values from billing to shipping
        if (!isset($args['ship_to_different_address']))
        {
            $args['shipping_first_name'] = $args['billing_first_name'];
            $args['shipping_last_name']  = $args['billing_last_name'];
            $args['shipping_company']    = $args['billing_company'];
            $args['shipping_phone']      = $args['billing_phone'];
        }

        return $args;
    }

    /**
     * For self fulfillment.  Parse out the Fedex or UPS shipping method for reporting to the ShipCompliant API
     *
     * @param $args
     *
     * @return array
     */
    private function getShipping($args)
    {
        global $woocommerce;
        $shipper = 'OTHER';
        $method = 'OTHER';
        $key = false;

        if (!empty($args['shipping_method']) && !empty($args['shipping_method'][0]))
        {
            $methodSlug = $args['shipping_method'][0];
	        $methodSlug = new StringDecorator($methodSlug);
	        if($methodSlug->contains(":")) {

		        list( $method, $key ) = preg_split( '/:/', $methodSlug->__toString() );

		        $mapper = false;
		        switch ( $method ) {
			        case "ups":
				        $mapper = new UPSMapper();
				        break;
			        case "fedex":
				        $mapper = new FedExMapper();
				        break;
		        }

		        if ( $mapper ) {
			        $shipper = $mapper->getShipCompliantKey( $key );
		        }
	        }
	        else if ($methodSlug == 'free_shipping') {

		        //get the default option from the options

		        $default_shipper = get_option('woocommerce_shipcompliant_config_settings');

		        $shipper = $default_shipper['default_free_shipping'];

	        }
        }

        return array(
            'cost'       => $woocommerce->cart->shipping_total,
            'wc_method'  => $method,
            'wc_key'     => $key,
            'sc_shipper' => $shipper
        );

    }

    /**
     * If the zip entered has a +4 suffix, split into zip1 and zip2
     *
     * @param $zip
     *
     * @return array
     */
    private function stripZipPlusFour($zip)
    {
        $sd = new StringDecorator($zip);
        return $sd->splitZipPlusFour();
    }

    /**
     * Do the pre-purchase real time check.
     *
     * @param array $args
     *
     * @return mixed
     */
    public function doRealTimeCheck(array $args)
    {

        global $woocommerce;
        Logger::debug('Compliance::doRealTimeCheck()', array('args' => $args));

        $args          = $this->populateShippingAddress($args);
        $dateOfBirth   = $this->getDateOfBirth($args);
        $customerKey   = $this->getCurrentCustomerKey();
        $salesOrderKey = $this->getCurrentSalesOrderKey();
        $items         = $this->getShipmentItems();
        $shipping      = $this->getShipping($args);
        $discount      = $this->getDiscount();
        if (!empty($args['order_comments']))
        {
            $gift_note = $args['order_comments'];
        } else
        {
            $gift_note = '';
        }

        try {
            $request = array(
                "AddressOption"        => array(
                    "IgnoreStreetLevelErrors"  => true,
                    "RejectIfAddressSuggested" => false,
                ),
                "IncludeSalesTaxRates" => true,
                "PersistOption"        => 'OverrideExisting',
                "SalesOrder"           => array(
                    "BillTo"            => array(
                        "City"        => $woocommerce->customer->get_city(),
                        "Country"     => $woocommerce->customer->get_country(),
                        "DateOfBirth" => $dateOfBirth,
                        //                    "Email"       => "", // TODO
                        "FirstName"   => $args['billing_first_name'],
                        "LastName"    => $args['billing_last_name'],
                        "Phone"       => $args['billing_phone'],
                        "State"       => $woocommerce->customer->get_state(),
                        "Street1"     => $woocommerce->customer->get_address(),
                        "Street2"     => $woocommerce->customer->get_address_2()
                        //                    "Zip2"    => ""
                    ),
                    "CustomerKey"       => $customerKey, // should be customer id in final checkout
                    //                "ExternalCustomerKey"    => "",
                    //                "ExternalOfferKeys"      => array(),
                    //                "ExternalSalesOrderKey"  => "",
                    "SalesOrderDiscount" => $discount,
                    "FulfillmentType"   => 'Daily', // null or Club or Daily
                    "OrderType"         => 'Internet', // or Club or Fax or InPerson
                    //                "Payments"               => array(
                    //                    "Payment" => array(
                    //                        "Amount"        => "",
                    //                        "SubType"       => "",
                    //                        "TransactionID" => "",
                    //                        "Type"          => ""
                    //                    )
                    //                ),
                    "PurchaseDate"      => date('Y-m-d'),
                    //                "ReferenceNumber"        => "",
                    //                "RefundedOrderReference" => "",
                    "SalesOrderKey"     => $salesOrderKey, // should be order id in final call
                    "SalesTaxCollected" => TaxManager::getInstance()->getCartTaxFromService(),
                    //                "SettlementBatchNumber"  => "",
                    "Shipments"         => array(
                        "Shipment" => array(
                            "ExternalClubKeys"    => array(),
                            //                        "FulfillmentAccount"         => "",
                            "FulfillmentHouse"    => Plugin::getInstance()->getConfig('default_fulfillment_location'),
                            //                        "FulfillmentExceptionReason" => "",
                            //                        "FulfillmentExceptionType"   => "",
                            //                        "FulfillmentStatus"          => "",
                            "GiftNote"            => $gift_note,
                            //                        "Handling"                   => "",
                            "InsuredAmount"       => 0,
                            "LicenseRelationship" => "Default",
                            // Default or Pickup
                            "ShipDate"            => date('Y-m-d'),
                            "ShipmentItems"       => $items,
                            "ShipmentKey"         => 1,
                            "ShipmentStatus"      => Plugin::getInstance()->getConfig('default_shipment_status'),
                            // Amount $ collected from the customer for shipping charge​
                            "Shipping"            => $shipping['cost'],
                            "ShippingService"     => $shipping['sc_shipper'],
                            "ShipTo"              => array(
                                "City"        => $woocommerce->customer->get_shipping_city(),
                                "Company"     => $args['shipping_city'],
                                "Country"     => $woocommerce->customer->get_shipping_country(),
                                "DateOfBirth" => $dateOfBirth,
                                //                                                "Email"       => "", // probably not going to happen for pre-checkout
                                //                                                "Fax"         => "", // no
                                "FirstName"   => $args['shipping_first_name'],
                                "LastName"    => $args['shipping_last_name'],
                                "Phone"       => $args['billing_phone'],
                                "State"       => $woocommerce->customer->get_shipping_state(),
                                "Street1"     => $woocommerce->customer->get_shipping_address(),
                                "Street2"     => $woocommerce->customer->get_shipping_address_2(),
                                //                    "Zip2"    => ""
                            ),
                            //                        "SpecialInstructions" => "",
                        )
                    ),
                    //                "Tags"            => array(
                    //                    "Tag" => array(
                    //
                    //                        "Name" => ""
                    //                    )
                    //                )
                )
            );
        } catch ( Exception $e ) {
            Logger::debug( 'Compliance::request error', $e->getMessage() );
        }
        $billingZip = $this->stripZipPlusFour($woocommerce->customer->get_postcode());

        $request['SalesOrder']['BillTo']['Zip1'] = $billingZip['zip'];
        if (!empty($billingZip['zip2']))
        {
            $request['SalesOrder']['BillTo']['Zip2'] = $billingZip['plus_four'];
        }

        $shippingZip = $this->stripZipPlusFour($woocommerce->customer->get_shipping_postcode());

        $request['SalesOrder']['Shipments']['Shipment']['ShipTo']['Zip1'] = $shippingZip['zip'];
        if (!empty($shippingZip['zip2']))
        {
            $request['SalesOrder']['Shipments']['Shipment']['ShipTo']['Zip2'] = $shippingZip['plus_four'];
        }

        $response = $this->service->CheckComplianceOfSalesOrder($request);

        return $response;
    }

}
