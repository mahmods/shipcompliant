<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/8/14
 * Time: 1:56 PM
 */

namespace H2\ShipCompliant\Model;

use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\Session;
use H2\ShipCompliant\API\SalesOrderService;
use H2\ShipCompliant\Compliance;

class Order extends \WC_Order {

	/**
	 * @var SalesOrderService
	 */
	private $service;

	/**
	 * Last Response from ShipCompliant
	 * @var
	 */
	private $last_response;

	/**
	 * @param null $id
	 */
	public function __construct( $id = null ) {
		parent::__construct( $id );

		$this->service = new SalesOrderService( Plugin::getInstance()->getSecurity() );
	}

	/**
	 * @param mixed $service
	 */
	public function setService( $service ) {
		$this->service = $service;
	}

	/**
	 * @return mixed
	 */
	public function getService() {
		return $this->service;
	}


	/**
	 * Get the compliance status for this order
	 * @return string
	 */
	public function getComplianceStatus() {
		return get_post_meta( $this->id, 'ComplianceStatus', true );
	}

	public function saveTax( $amount ) {
		global $wpdb;

		add_post_meta($this->id, '_order_tax', $amount );

		$orderItem = array(
			'order_item_name' => 'ShipCompliant',
			'order_item_type' => 'tax',
			'order_id'        => $this->id
		);

		$table = $wpdb->prefix . "woocommerce_order_items";

		$metaTable = $wpdb->prefix . "woocommerce_order_itemmeta";

		$wpdb->insert( $table, $orderItem );

		$itemId = $wpdb->insert_id;

		$orderItemMeta = array(
			array(
				'order_item_id' => $itemId,
				'meta_key'      => 'rate_id',
				'meta_value'    => 0
			),
			array(
				'order_item_id' => $itemId,
				'meta_key'      => 'label',
				'meta_value'    => 'ShipCompliant'
			),
			array(
				'order_item_id' => $itemId,
				'meta_key'      => 'compound',
				'meta_value'    => true
			),
			array(
				'order_item_id' => $itemId,
				'meta_key'      => 'tax_amount',
				'meta_value'    => $amount
			),
			array(
				'order_item_id' => $itemId,
				'meta_key'      => 'shipping_tax_amount',
				'meta_value'    => 0
			)
		);

		Logger::debug( 'Attempting to save tax', array( 'order_item_meta' => $orderItemMeta ) );
		foreach ( $orderItemMeta as $data ) {
			$wpdb->insert( $metaTable, $data );
		}

	}

	/**
	 * Check to see if compliance status is in list of matches
	 *
	 * @param array $matches
	 *
	 * @return bool
	 */
	public function complianceStatusIs( array $matches = array() ) {
		return in_array( $this->getComplianceStatus(), $matches );
	}

	/**
	 * Check to see if order status is in the list of matches
	 *
	 * @param array $matches
	 *
	 * @return bool
	 */
	public function statusIs( array $matches = array() ) {
		return ( in_array( $this->status, $matches ) );
	}


	/**
	 * Returns the salesOrderKey recorded in ShipCompliant.
	 * If this order is quarantined the SalesOrderKey will be a temporary GUID key.
	 * Committed orders will return the order id.
	 * @return mixed
	 */
	public function getSalesOrderKey() {
		if ( $this->complianceStatusIs( array( 'quarantined' ) ) ) {
			return get_post_meta( $this->id, 'TemporarySalesOrderKey', true );
		}

		return $this->id;
	}

	public function getSalesOrder() {
		$salesOrderKey = $this->getSalesOrderKey();
		$transientKey  = sprintf( "sales-order-%s", $salesOrderKey );
		$salesOrder    = get_transient( $transientKey );

		if ( false === $salesOrder ) {
			Logger::debug( 'Getting SalesOrder from ShipCompliant', array( 'salesOrderKey' => $salesOrderKey ) );
			$request    = array( 'SalesOrderKey' => $salesOrderKey );
			$response   = $this->service->getSalesOrder( $request );
			$salesOrder = $response->GetSalesOrderResult;
			set_transient( $transientKey, $salesOrder, HOUR_IN_SECONDS );
		}
		Logger::debug( 'Got SalesOrder', array( 'SalesOrder' => $salesOrder ) );

		return $salesOrder;
	}


	/**
	 * @return $this
	 */
	public function quarantine() {
		// accept wc order - set status to "on-hold"
		$this->update_status( 'on-hold', 'ShipCompliant Quarantine' );
		// add order notes

		$compliance_errors = Session::get( 'ComplianceErrors' );
		$sales_order_key   = Session::get( 'SalesOrderKey' );

		// add order meta
		update_post_meta( $this->id, 'ComplianceErrors', $compliance_errors );
		// save the temp sales order key so that we can commit this order later in SC
		update_post_meta( $this->id, 'TemporarySalesOrderKey', $sales_order_key );
		update_post_meta( $this->id, 'ComplianceStatus', 'quarantined' );

		Logger::debug( 'Setting order to Quarantine Mode', array(
			'compliance_errors' => $compliance_errors,
			'sales_order_key'   => $sales_order_key
		) );

		return $this;
	}

	/**
	 * Commit a SalesOrder that has been checked for compliance
	 *
	 * @param $oldSalesOrderKey
	 * @param $salesOrderKey
	 * @param $amount
	 * @param $taxTotal
	 *
	 * @return mixed
	 */
	private function commitSalesOrder( $oldSalesOrderKey, $salesOrderKey, $amount, $taxTotal ) {
		if ( strlen( $oldSalesOrderKey ) > 1 ) {
			Logger::debug( 'Order::commitSalesOrder() - Committing a SalesOrder that has been checked for compliance - CommitSalesOrderUpdateKey', array(
				"oldSalesOrderKey" => $oldSalesOrderKey,
				"salesOrderKey"    => $salesOrderKey,
				"amount"           => $amount,
				"taxTotal"         => $taxTotal
			) );

			$request  = array(
				'CommitOption'          => 'AllShipments', // Null or AllShipments or CompliantShipments
				"ExternalSalesOrderKey" => '', // not sure
				"OldSalesOrderKey"      => $oldSalesOrderKey,
				"Payments"              => array(
					"Payment" => array(
						"Amount" => $amount,
						"Type"   => 'CreditCard'
						// Cash, Check, CreditCard, GiftCard, GiftCertificate, Invoice, MoneyOrder, Other, StoreAccount, and TravelersCheck.
					),
				),
				"SalesTaxCollected"     => $taxTotal,
				"SalesOrderKey"         => $salesOrderKey
			);
			$response = $this->service->CommitSalesOrderUpdateKey( $request );

			$this->last_response = $response;

			return $response->CommitSalesOrderUpdateKeyResult;
		} else {
			Logger::debug( 'Order::commitSalesOrder() - Committing a SalesOrder that has been checked for compliance - CommitSalesOrder', array(
				"salesOrderKey"    => $salesOrderKey,
				"amount"           => $amount,
				"taxTotal"         => $taxTotal
			) );

			$request  = array(
				'CommitOption'          => 'AllShipments', // Null or AllShipments or CompliantShipments
				"ExternalSalesOrderKey" => '', // not sure
				"Payments"              => array(
					"Payment" => array(
						"Amount" => $amount,
						"Type"   => 'CreditCard'
						// Cash, Check, CreditCard, GiftCard, GiftCertificate, Invoice, MoneyOrder, Other, StoreAccount, and TravelersCheck.
					),
				),
				"SalesTaxCollected"     => $taxTotal,
				"SalesOrderKey"         => $salesOrderKey
			);
			$response = $this->service->CommitSalesOrder( $request );

			$this->last_response = $response;

			return $response->CommitSalesOrderResult;
		}
	}

	/**
	 * Overriding and committing a quarantined order.
	 *
	 * @return $this Fluent Interface
	 * @throws \RuntimeException
	 */
	public function commitOverride() {
		Logger::info( 'Order::commitOverride() - Overriding and committing a quarantined order.' );
		$result = $this->commitSalesOrder(
			Session::get( 'SalesOrderKey' ),
			$this->id,
			WC()->cart->cart_contents_total,
			WC()->cart->tax_total
		);

		if ( $result->ResponseStatus == "Success" && $result->Shipments->ShipmentCommitResponse->IsCommitted == true ) {
			delete_post_meta( $this->id, 'ComplianceErrors' );
			delete_post_meta( $this->id, 'TemporarySalesOrderKey' );
			update_post_meta( $this->id, 'ComplianceStatus', 'level1' );
		} else {
			throw new \RuntimeException( 'Could not commit order' );
		}

		return $this;
	}


	/**
	 * Commit a SalesOrder that has been checked for compliance, and is compliant on the first check
	 * @return $this
	 */
	public function commit() {
		$oldSalesOrderKey = get_post_meta( $this->id, 'TemporarySalesOrderKey', true );
		$salesOrderKey = $this->id;
		$amount = $this->get_total();
		$taxTotal = $this->get_total_tax();

		Logger::info( 'Order::commit() - Committing Compliant Sales Order' );

		$this->commitSalesOrder(
			$oldSalesOrderKey,
			$salesOrderKey,
			$amount,
			$taxTotal
		);

		delete_post_meta( $this->id, 'TemporarySalesOrderKey' );
		update_post_meta( $this->id, 'ComplianceStatus', 'compliant' );

		return $this;
	}

	/**
	 * Void a committed SalesOrder
	 * @return $this
	 * @throws RuntimeException
	 */
	public function void() {

		$cacheKey = "void-order-" . $this->getSalesOrderKey();

		$response = get_transient( $cacheKey );

		if ( ! $response ) {
			Logger::info( 'Order::void()' );
			$request  = array( "SalesOrderKey" => $this->getSalesOrderKey() );
			$response = $this->service->voidSalesOrder( $request );
			$result   = $response->VoidSalesOrderResult;

			if ( $result->ResponseStatus == "Success" ) {
				//            delete_post_meta($this->id, 'ComplianceErrors');
				delete_post_meta( $this->id, 'TemporarySalesOrderKey' );
				update_post_meta( $this->id, 'ComplianceStatus', 'voided' );
			} else {
				throw new RuntimeException( 'Could not void this SalesOrder' );
			}

			set_transient( $cacheKey, $response, MINUTE_IN_SECONDS );
		}

		$this->last_response = $response;

		return $this;
	}

	/**
	 * Last response from SalesOrderService
	 * @return mixed
	 */
	public function getLastResponse() {
		return $this->last_response;
	}

}
