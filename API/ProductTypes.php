<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 4/15/15
 * Time: 6:11 PM
 */

namespace H2\ShipCompliant\API;

class ProductTypes {

	const WINE = "Wine";
	const WINE_SPARKLING = "SparklingWine";
	const CIDER = "Cider";
	const CIDER_APPLE = "AppleCider";
	const BEER = "Beer";
	const MALT = "MaltLiquor";
	const SPIRITS = "Spirits";
	const FOOD = "Food";
	const FREIGHT = "Freight";
	const GENERAL_MERCHANDISE = "MerchandiseTaxable";
	const GENERAL_NOTAX = "MerchandiseNonTaxable";


	public static function get_wine_types() {
		return array(
			static::WINE,
			static::WINE_SPARKLING
		);
	}

	public static function get_beer_types() {
		return array(
			static::BEER,
			static::MALT
		);
	}

	public static function get_beverage_types() {
		return array(
			static::WINE,
			static::WINE_SPARKLING,
			static::CIDER,
			static::CIDER_APPLE,
			static::BEER,
			static::MALT,
			static::SPIRITS,

		);
	}

	public static function beverage_types_with_container() {
		return array(
			static::CIDER,
			static::CIDER_APPLE,
			static::BEER,
			static::MALT,
		);
	}

	public static function is_beverage($type) {
		return in_array($type, static::get_beverage_types());
	}

	public static function is_wine_type( $type ) {
		return in_array( $type, static::get_wine_types() );
	}

	public static function is_beer_type($type) {
		return in_array($type, static::get_beer_types());
	}

	public static function has_container($type) {
		return in_array($type, static::beverage_types_with_container());
	}

	public static function is_spirit( $type ) {
		return (static::SPIRITS === $type);
	}
}