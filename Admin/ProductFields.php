<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 11/10/14
 * Time: 5:48 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\API\ProductTypes;
use H2\ShipCompliant\Util\Session;
use H2\ShipCompliant\Util\StringDecorator;

class ProductFields {

	private static $messages = array();

	/**
	 * Register hooks
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'add_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_fields' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_messages' ) );
	}

	public static function display_messages() {
		$messages = array();
		if ( Session::has( 'ProductSaveMessages' ) ) {
			$messages = Session::get( 'ProductSaveMessages' );
		}

		foreach ( $messages as $msg ) {
			printf( "<div id='message' class='error'><p>%s</p></div>", $msg );
		}

		Session::set( 'ProductSaveMessages', array() );
	}

	public static function div_start( $product_type ) {
		printf( '<div class="product-type-container %s">', $product_type );
	}

	public static function div_end() {
		print( "</div>" );
	}

	/**
	 * Render custom product fields in product edit screen.
	 */
	public static function add_fields() {
		wp_enqueue_style( 'shipcompliant-product-types', sprintf( '%s/assets/styles/product-edit.css', SHIPCOMPLIANT_PLUGIN_URL ) );
		wp_enqueue_script( 'shipcompliant-product-types', sprintf( '%s/assets/js/product-edit.js', SHIPCOMPLIANT_PLUGIN_URL ), array( 'jquery' ) );

		echo '<div class="shipcompliant_options_group">';
		echo "<h3>ShipCompliant</h3>";

		/**
		 * Product Type Selector
		 */
		woocommerce_wp_select( array(
			'id'      => '_product_type',
			'label'   => __( 'Product Type', 'shipcompliant' ),
			'options' => array(
				ProductTypes::WINE                => __( 'Wine', 'shipcompliant' ),
				ProductTypes::WINE_SPARKLING      => __( 'Sparkling Wine', 'shipcompliant' ),
				ProductTypes::CIDER               => __( 'Cider', 'shipcompliant' ),
				ProductTypes::CIDER_APPLE         => __( 'Cider (Apple)', 'shipcompliant' ),
				ProductTypes::BEER                => __( 'Beer', 'shipcompliant' ),
				ProductTypes::MALT                => __( 'Malt Liqour', 'shipcompliant' ),
				ProductTypes::SPIRITS             => __( 'Spirits', 'shipcompliant' ),
				ProductTypes::FOOD                => __( 'Food', 'shipcompliant' ),
				ProductTypes::GENERAL_MERCHANDISE => __( 'General Merchandise', 'shipcompliant' ),
				ProductTypes::GENERAL_NOTAX       => __( 'General Not Taxable', 'shipcompliant' ),
			)
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_brand_key',
			'label'       => __( 'Brand Key' ),
			'desc_tip'    => true,
			'description' => 'ShipCompliant Brand Key'
		) );

		/*
		 * All Beverage Types
		 */
		static::div_start( 'all-beverages' );

		woocommerce_wp_text_input(
			array(
				'id'                => '_bottles_per_sku',
				'label'             => __( 'Bottles Per Sku', 'shipcompliant' ),
				'placeholder'       => '1',
				'desc_tip'          => false,
				'description'       => '',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0'
				)
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_bottle_size',
				'label'             => __( 'Bottle Size', 'shipcompliant' ),
				'placeholder'       => '750.00',
				'desc_tip'          => false,
				'description'       => '',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0'
				)
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_bottle_units',
				'label'   => __( 'Bottle Units', 'shipcompliant' ),
				'options' => array(
					'milliliter' => __( 'MilliLiters', 'shipcompliant' ),
					'ounce'      => __( 'Ounces', 'shipcompliant' ),
					'liter'      => __( 'Liters', 'shipcompliant' ),
					'gallon'     => __( 'Gallons', 'shipcompliant' )
				)
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_alcohol_by_volume',
				'label'             => __( 'Alcohol By Volume (percent)', 'shipcompliant' ),
				'placeholder'       => '',
				'desc_tip'          => 'Percent Alcohol By Volume (e.g. 12.4%)',
				'description'       => false,
				'type'              => 'number',
				'custom_attributes' => array(
					'size' => '70',
					'step' => '0.01',
					'min'  => '0'
				)
			)
		);
		static::div_end();

		/*
		 * Fields for Wine
		 */
		static::div_start( 'wine wine_sparkling' );

		woocommerce_wp_text_input(
			array(
				'id'          => '_varietal',
				'label'       => __( 'Varietal', 'shipcompliant' ),
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => ''
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_vintage',
				'label'       => __( 'Vintage', 'shipcompliant' ),
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => ''
			)
		);
		static::div_end();

		/*
		 * Ciders, Beers, and Malt Beverages
		 */
		static::div_start( 'cider cider_apple beer malt' );
		woocommerce_wp_select(
			array(
				'id'          => '_container_type',
				'label'       => __( 'Container Type', 'shipcompliant' ),
				'placeholder' => '',
				'desc_type'   => 'Bottle, Can, Keg, Long Neck, etc',
				'description' => '',
				'options'     => array(
					'bottle'              => 'Bottle',
					'can'                 => 'Can',
					'plastic_bottle'      => 'Plastic Bottle',
					'aluminium_long_neck' => 'Aluminium Long Neck',
					'keg'                 => 'Keg',
					'pet'                 => 'PET',
					'long_neck'           => 'Long Neck'
				)
			)
		);
		static::div_end();

		// beer

		static::div_start( 'beer' );

		woocommerce_wp_text_input(
			array(
				'id'          => '_style',
				'label'       => __( 'Style', 'shipcompliant' ),
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => ''
			)
		);

		static::div_end();

		// spirits

		static::div_start( 'spirits' );

		woocommerce_wp_text_input(
			array(
				'id'          => '_flavor',
				'label'       => __( 'Flavor', 'shipcompliant' ),
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => ''
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_age',
				'label'       => __( 'Age', 'shipcompliant' ),
				'placeholder' => '',
				'desc_tip'    => false,
				'description' => 'Years'
			)
		);

		static::div_end();

		static::div_end();
	}


	/**
	 * Convenience method to write post_metas or save an error message if it is empty and required
	 *
	 * @param $post_id
	 * @param $key
	 * @param bool $required
	 *
	 * @return mixed
	 */
	public static function write_meta( $post_id, $key, $required = true ) {
		// Text Field
		$data = $_POST[ $key ];
		if ( ! empty( $data ) ) {
			update_post_meta( $post_id, $key, esc_attr( $data ) );
		} else if ( $required ) {
			$key = new StringDecorator( $key );
			// add validation message
			static::$messages[] = sprintf( '<strong>%s</strong> is a required field', $key->underscoreToTitle() );
		}

		return $data;
	}


	/**
	 * Save custom fields as post_meta
	 *
	 * @param $post_id
	 */
	public static function save_fields( $post_id ) {

		$product_type = static::write_meta( $post_id, '_product_type' );

		static::write_meta( $post_id, '_brand_key' );

		if ( ProductTypes::is_beverage( $product_type ) ) {
			static::write_meta( $post_id, '_bottles_per_sku' );
			static::write_meta( $post_id, '_bottle_size' );
			static::write_meta( $post_id, '_bottle_units' );
			static::write_meta( $post_id, '_alcohol_by_volume' );
		}

		if ( ProductTypes::is_wine_type( $product_type ) ) {
			static::write_meta( $post_id, '_varietal' );
			static::write_meta( $post_id, '_vintage' );
		}

		if ( ProductTypes::is_beer_type( $product_type ) ) {
			static::write_meta( $post_id, '_style' );
		}

		if ( ProductTypes::has_container( $product_type ) ) {
			static::write_meta( $post_id, '_container_type' );
		}

		if ( ProductTypes::is_spirit( $product_type ) ) {
			static::write_meta( $post_id, '_flavor' );
			static::write_meta( $post_id, '_age' );
		}

		if ( empty( static::$messages ) ) {
			update_post_meta( $post_id, '_has_enough_data_to_sync', true );
		} else {
			static::$messages[] = "You must enter all required fields to be able to sync this product with ShipCompliant";
			update_post_meta( $post_id, '_has_enough_data_to_sync', false );
		}

		Session::set( 'ProductSaveMessages', static::$messages );
	}
} 