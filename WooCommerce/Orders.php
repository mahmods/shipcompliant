<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/3/14
 * Time: 3:05 AM
 */

namespace H2\ShipCompliant\WooCommerce;

use H2\ShipCompliant\Compliance;
use H2\ShipCompliant\Model\Order;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;

/**
 * Class Orders
 * @package H2\ShipCompliant\WooCommerce
 */
class Orders {

	public static function init() {
		$instance = new self();
		add_action( 'add_meta_boxes', array( $instance, 'add_compliance_metabox' ) );
		add_action( 'wp_ajax_sc_commit_order', array( $instance, 'ajax_commit_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $instance, 'order_status_changed' ) );
		add_action( 'admin_notices', array( $instance, 'display_errors' ) );
	}


	public function display_errors() {
		global $post;
		if ( isset( $post ) && $post->post_type == "shop_order" && isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
			$complianceStatus = get_post_meta( $post->ID, 'ComplianceStatus' );

			if ( ! empty( $complianceStatus ) && $complianceStatus[0] == "quarantined" ) {
				echo '<div class="error">';

				echo '<p>ShipCompliant:  This order has been quarantined.  Please resolve, and commit.</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Adds a box to the main column on the Post and Page edit screens.
	 */
	public function add_compliance_metabox() {
		add_meta_box(
			'shipcompliant_compliance_status',
			__( 'Compliance Status', 'shipcompliant' ),
			array( $this, 'show_compliance_status' ),
			'shop_order',
			'normal',
			'high'
		);

		/*// If the system default fulfillment is InHouse, skip this;
		if ( ! in_array( Plugin::getInstance()->getConfig( 'default_fulfillment_house' ), array(
			'InHouse',
			'InHouseFedEx'
		) )
		) {*/
			add_meta_box(
				'shipcompliant_shipment_tracking',
				__( 'Tracking Number', 'shipcompliant' ),
				array( $this, 'show_tracking_number' ),
				'shop_order',
				'normal',
				'core'
			);

		/*}*/
	}

	public function show_tracking_number( $post ) {

		$order = new Order( $post->ID );

		Logger::debug( 'Orders::show_tracking_number() - order', array( 'order' => $order ) );

		if(!$order->complianceStatusIs(array('committed','level1','overridden'))) {
			print('<div style="background-color: #f0c8c6; padding:20px;">');
			print("<p><strong>This order has not been shipped.</strong></p>");
			print('</div>');
			return;
		}

		$result = $order->getSalesOrder();

		if ( $result->ResponseStatus === "Failure" ) {
			print('<div style="background-color: #f0c8c6; padding:20px;">');
			print( "<p><strong>An error occurred while trying to retrieve this sales order:</strong></p>" );
			print( "<ul>" );
			if ( is_array( $result->Errors->Error ) ) {

				foreach ( $result->Errors->Error as $error ) {
					printf( "<li>%s</li>", $error->Message );
				}
			} else {
				printf( "<li>%s</li>", $result->Errors->Error->Message );
			}

			print( "</ul></div>" );

			return;
		}

		$packages = $result->SalesOrder->Shipments->Shipment->Packages;
		Logger::debug( 'Orders::show_tracking_number() - packages', array( 'packages' => $packages ) );
		if ( ! empty( $packages->Package ) ) {
			if ( !is_array($packages)) {
				printf( "<p>%s - %s</p>", $packages->TrackingNumber, $packages->TrackingStatus );
			} else {
				foreach ( $packages->Package as $package ) {
					printf( "<p>%s - %s</p>", $package->TrackingNumber, $package->TrackingStatus );
				}
			}
		} else {
			print( '<p style="background-color: #f0c8c6; padding:20px;">' );
			print( "No Tracking Numbers. (Is Developer Mode on?)</p>" );
		}
	}

	private function show_compliance_errors( $errors ) {

		echo "<strong>Compliance Errors</strong>";

		if ( ! empty( $errors[0] ) ) {
			foreach ( $errors[0] as $err => $reason ) {
				?>
				<p style="background-color: #f0c8c6; padding:20px;">
					<?php echo $reason; ?>
				</p>
			<?php
			}
		}
	}

	/**
	 * MetaBox content for compliance status on Order edit pages.
	 *
	 * @param $post
	 */
	public function show_compliance_status( $post ) {
		wp_enqueue_script( 'sc-order-edit', SHIPCOMPLIANT_PLUGIN_URL . "/assets/js/orderedit.js", array(
			'jquery',
			'underscore'
		) );

		$order = new Order( $post->ID );

		$errors = get_post_meta( $post->ID, 'ComplianceErrors' );

		// we need a new condition for compliant status
		if ( null == $order->getComplianceStatus() ) {
			echo "<p>Order is Compliant</p>";
		} elseif ( $order->complianceStatusIs( array( 'compliant' ) ) ) {
			echo "<p>Order is Compliant</p>";
		} elseif ( $order->complianceStatusIs( array( 'overridden', 'committed' ) ) ) {
			echo "<p>Order is Non-Compliant, but Overridden</p>";
			$this->show_compliance_errors( $errors );
		} elseif ( $order->complianceStatusIs( array( 'voided' ) ) ) {
			echo "<p>Order was voided</p>";
			$this->show_compliance_errors( $errors );
		} elseif ( $order->complianceStatusIs( array( 'quarantined' ) ) ) {
			?>
			<div id="sc_compliance_quarantine">
				<p>This order is quarantined. Do you want to commit this order in ShipCompliant?</p>

				<p>
					<button id='sc-btn-commit-order'
					        data-order-id='<?php echo $post->ID; ?>'
					        class='button'>Commit this Order


					</button>
					<img id="sc_compliance_commit_spinner"
					     src="<?php echo admin_url( '/images/spinner.gif' ); ?>"
					     style="margin-bottom: -5px;padding-left: 5px;display:none;"/>
				</p>
			</div>
			<?php
			$this->show_compliance_errors( $errors );

		} elseif ( $order->complianceStatusIs( array( "level1" ) ) ) {
			echo "<p>Level 1 - No compliance check run.  This order is committed.</p>";
		}
	}

	/**
	 * Ajax callback for "commit order" button.
	 * Commits a Quarantined order
	 */
	public function ajax_commit_order() {
		header( 'Content-type: application/json' );

		if ( empty( $_POST['orderId'] ) ) {
			Logger::error( 'Orders::ajax_commit_order() - Sent 400 Error - Method called without $order_id' );
			header( "HTTP/1.0 400 OrderId is required" );
			exit;
		}
		$order_id = $_POST['orderId'];
		$order    = new Order( $order_id );

		Logger::info( 'Orders::ajax_commit_order() - Committing Order and updating status to "processing"' );
		$order->commit()->update_status( 'processing', 'ShipCompliant Quarantine Removed.  Order shipped.' );

		echo json_encode( $order->getLastResponse() );
		exit;
	}


	/**
	 * Fired when the status of a WC order is changed.
	 *
	 * @param $order_id
	 */
	public function order_status_changed( $order_id ) {

		$order = new Order( $order_id );

		Logger::info( 'Orders::order_status_changed()' );

		if ( $order->statusIs( array( 'pending' ) ) ) {
			return;
		}

		// only change status if plugin is in quarantine mode
		if ( Plugin::getInstance()->getComplianceMode() == Compliance::COMPLIANCE_MODE_QUARANTINE ) {

			// changing status of quarantined orders automatically commits them in ship compliant
			if ( $order->statusIs( array(
					'completed',
					'processing'
				) ) && ! $order->complianceStatusIs( array( 'committed' ) )
			) {
				Logger::debug( 'Orders::order_status_changed() - Committing quarantined order' );
				// commit order
				try {
					$order->commit();
				}
				catch( \Exception $ex ) {
					Logger::error( 'Orders::order_status_changed() - There was a problem committing an order', array(
						'exception' => $ex->getMessage(),
						'order_id'  => $order_id
					) );
				}
			}
		}

		// if order is canceled or refunded, cancel orders in ship compliant as well
		if ( $order->statusIs( array( 'cancelled', 'refunded' ) ) ) {
			Logger::debug( 'Orders::order_status_changed() - Voiding cancelled order', array( 'order_id' => $order_id ) );
			try {
				$order->void();
			}
			catch( \Exception $ex ) {
				Logger::error( 'Orders::order_status_changed() - Could not void cancelled order', array(
					'exception' => $ex->getMessage(),
					'order_id'  => $order_id
				) );
				exit;
			}
		}
	}

}
