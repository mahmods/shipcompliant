<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/18/14
 * Time: 10:08 AM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\API\ProductService;
use H2\ShipCompliant\API\ProductTypes;
use H2\ShipCompliant\Model\Product;
use H2\ShipCompliant\Model\ProductMapper;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\Session;

class ProductSync {

	protected static $instance = null;

	protected $service = null;

	protected $created = 0;
	protected $updated = 0;
	protected $errors = array();

	private function __construct() {
		$this->service = new ProductService( Plugin::getInstance()->getSecurity() );
	}

	public static function init() {
		$instance = static::get_instance();
		add_action( 'save_post_product', array( $instance, 'save_post' ) );

		add_action( 'admin_notices', array( $instance, 'post_edit_notices' ) );
		add_action( 'wp_ajax_shipcompliant_get_products', array( $instance, 'ajax_get_products' ) );
		add_action( 'wp_ajax_shipcompliant_import_products', array( $instance, 'ajax_import_products' ) );
		add_action( 'wp_ajax_shipcompliant_export_products', array( $instance, 'ajax_export_products' ) );
	}

	public static function get_instance() {
		if ( static::$instance === null ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public static function remove_actions() {
		remove_action( 'save_post_product', array( static::get_instance(), 'save_post' ) );
	}

	public static function register_actions() {
		add_action( 'save_post_product', array( static::get_instance(), 'save_post' ) );
	}

	public function ajax_get_products() {
		$products = ProductMapper::get_shipcompliant_products();
		for ( $i = 0; $i < count( $products ); $i ++ ) {
			if ( ProductMapper::product_exists( $products[ $i ]->ProductKey ) ) {
				$products[ $i ]->ExistsInWooCommerce = true;
			} else {
				$products[ $i ]->ExistsInWooCommerce = false;
			}
		}

		header( 'Content-type: application/json' );
		echo json_encode( $products );
		exit;
	}


	private function add_or_update_product( $scp ) {
		// if product exists
		$id = Product::find_by_sku( $scp->ProductKey );
		if ( $id ) {
			// update it
			$product = new Product( $id );

			// update title if neccesary
			if ( trim( $scp->Description ) != trim( $product->get_title() ) ) {
				$product->set_title( $scp->Description );
			}

			$this->updated ++;
		} else {
			// create a new product
			try {
				$product = Product::create( $scp->Description, $scp->ProductKey );
				$this->created ++;
			}
			catch( \Exception $ex ) {
				$this->errors[] = array(
					'message'      => $ex->getMessage(),
					'product_data' => $scp
				);

				return false;
			}
		}

		$product->set_brand_key( $scp->BrandKey )
		        ->set_product_type( $scp->ProductType )
		        ->set_bottle_size( $scp->VolumeAmount )
		        ->set_bottle_units( $scp->VolumeUnit )
		        ->set_vintage( $scp->Vintage )
		        ->set_alcohol_by_volume( $scp->PercentAlcohol )
		        ->set_age( $scp->Age )
		        ->set_price( $scp->DefaultRetailUnitPrice );

		if ( isset( $scp->Varietal ) ) {
			$product->set_varietal( $scp->Varietal );
		}

		if ( isset( $scp->Label ) && isset( $scp->Label->SerialNumber ) ) {
			$product->set_serial_number( $scp->Label->SerialNumber );
		}

		return $product;
	}


	public function ajax_import_products() {
		$this->remove_actions();
		$products = ProductMapper::get_shipcompliant_products();
		foreach ( $products as $scp ) {
			$this->add_or_update_product( $scp );
		}

		$response = array(
			'created' => $this->created,
			'updated' => $this->updated,
			'errors'  => $this->errors
		);
		header( 'Content-type: application/json' );
		echo json_encode( $response );
		exit;
	}


	public function ajax_export_products() {
		$posts = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => - 1 ) );

		$response = (object) array(
			'success'  => false,
			'count'    => 0,
			'messages' => array(),
			'errors'   => array()
		);

		try {
			foreach ( $posts as $post ) {
				$sc_response = $this->update_sc_product_from_db( $post->ID );

				if ( ! empty( $sc_response ) && $sc_response->AddUpdateProductResult->ResponseStatus != "Success" ) {
					$response->errors[] = array(
						'product'  => $post,
						'response' => $sc_response->AddUpdateProductResult->Errors
					);
				} else {
					$response->count ++;
				}

			}

			$response->success    = true;
			$response->messages[] = sprintf( "Successfully updated %d products in ShipCompliant", $response->count );
		}
		catch( \Exception $ex ) {
			$response->success    = false;
			$response->messages[] = "There was a problem exporting your products";
			$response->messages[] = $ex->getMessage();
		}

		header( 'Content-type: application/json' );
		echo json_encode( $response );
		exit;

	}

	/**
	 * Sync a WooCommerce Product to ShipCompliant
	 *
	 * @param $post_id
	 *
	 * @return mixed
	 */
	public function update_sc_product_from_post( $post_id ) {

		Logger::debug( 'Syncing WC Product with SC' );

		$product      = new Product( $post_id );
		$product_type = $_POST['_product_type'];

		$data = array(
			"ProductType"              => $product_type,
			"Description"              => $product->get_title(),
			"ProductDistribution"      => "Direct",
			"ProductKey"               => $_POST['_sku'],
			"BrandKey"                 => $_POST['_brand_key'],
			"UnitPrice"                => $_POST['_regular_price'],
			"DefaultRetailUnitPrice"   => $_POST['_regular_price'],
			"ShippingWeightInLbs"      => $_POST['_weight'],
			"ContainersPerSellingUnit" => 1,
			"PercentAlcohol"           => 0,
			"VolumeAmount"             => 0,
			"VolumeUnit"               => 'milliliter',

		);

		if ( ProductTypes::is_beverage( $product_type ) ) {

			$data = array_merge( $data, array(
				"ContainersPerSellingUnit" => $_POST['_bottles_per_sku'],
				"PercentAlcohol"           => $_POST['_alcohol_by_volume'],
				"VolumeAmount"             => $_POST['_bottle_size'],
				"VolumeUnit"               => $_POST['_bottle_units'],
			) );
		}

		if ( ProductTypes::is_wine_type( $product_type ) ) {
			$data['Varietal'] = $_POST['_varietal'];
			$data['Vintage']  = $_POST['_vintage'];
		}

		if ( ProductTypes::is_beer_type( $product_type ) ) {
			$request['Style'] = $_POST['_style'];
		}

		if ( ProductTypes::has_container( $product_type ) ) {
			$data['ContainerType'] = $_POST['_container_type'];
		}

		if ( ProductTypes::is_spirit( $product_type ) ) {
			$data['Flavor'] = $_POST['_flavor'];
			$data['Age']    = $_POST['_age'];
		}

		$response = $this->service->AddUpdateProduct( array(
			'Product'    => $data,
			"UpdateMode" => "UpdateExisting"
		) );

		return $response;
	}

	public function update_sc_product_from_db( $post_id ) {
		set_time_limit( 0 );

		Logger::debug( 'Syncing WC Product with SC' );

		$product = new Product( $post_id );

		$data = array(
			"ProductType"              => $product->get_product_type(),
			"Description"              => $product->get_title(),
			"BrandKey"                 => $product->get_brand_key(),
			"ProductDistribution"      => "Direct",
			"ProductKey"               => $product->get_sku(),
			"UnitPrice"                => $product->get_price(),
			"DefaultRetailUnitPrice"   => $product->get_price(),
			"ShippingWeightInLbs"      => $product->get_weight(),
			"ContainersPerSellingUnit" => 1,
			"PercentAlcohol"           => 0,
			"VolumeAmount"             => 0,
			"VolumeUnit"               => 'milliliter',
		);

		if ( $product->is_beverage() ) {
			$data = array_merge( $data, array(
				"PercentAlcohol"           => $product->get_alcohol_by_volume(),
				"VolumeAmount"             => $product->get_bottle_size(),
				"VolumeUnit"               => $product->get_bottle_units(),
				'ContainersPerSellingUnit' => $product->get_bottles_per_sku()
			) );
		}

		if ( $product->is_wine() ) {
			$data['Varietal'] = $product->get_varietal();
			$data['Vintage']  = $product->get_vintage();
		}

		if ( $product->is_beer() ) {
			$data['Style'] = $product->get_style();
		}

		if ( $product->is_spirit() ) {
			$data['Flavor'] = $product->get_flavor();
			$data['Age']    = $product->get_age();
		}

		if ( $product->has_container() ) {
			$data['ContainerType'] = $product->get_container_type();
		}

		$response = $this->service->AddUpdateProduct( array(
			'Product'    => $data,
			"UpdateMode" => "UpdateExisting"
		) );

		return $response;

	}

	public function save_post( $post_id ) {
		// if this is a revision, get the real post id
		if ( $parent_id = wp_is_post_revision( $post_id ) ) {
			$post_id = $parent_id;
		}

		// make sure post status isnt "trash" and we're not doing autosave
		if ( 'trash' != get_post_status( $post_id ) && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			$response = $this->update_sc_product_from_post( $post_id );

			if ( ! empty( $response ) && $response->AddUpdateProductResult->ResponseStatus != "Success" ) {
				$errors = $response->AddUpdateProductResult->Errors->Error;
				Session::set( 'post_edit_messages', $errors );

				return false;
			}

			return $post_id;
		}

		return false;
	}

	function post_edit_notices() {
		if ( Session::has( 'post_edit_messages' ) ) {

			$messages = Session::get( 'post_edit_messages' );
			//var_dump( $messages );

			if ( ! empty( $messages ) ): ?>
				<div class="error">
					<p> Error syncing product with ShipCompliant:</p>
					<?php if(is_array($messages)): ?>
					<ul>
						<?php foreach ( $messages as $error ): ?>
							<li><strong><?php echo $error->Message; ?></strong></li>
						<?php endforeach; ?>
					</ul>
					<?php else: ?>
						<p><?php echo $messages->Message; ?></p>
					<?php endif; ?>
				</div>
			<?php endif;

		}

		Session::remove( 'post_edit_messages' );
	}

}
