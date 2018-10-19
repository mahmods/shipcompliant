<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 11/14/14
 * Time: 4:58 PM
 */

namespace H2\ShipCompliant\Model;

use H2\ShipCompliant\API\ProductTypes;

class Product extends \WC_Product {

    public static function create($title, $sku = null, $product_type = null)
    {
        $post = array(
            'post_title'   => $title,
            'post_content' => $title,
            'post_status'  => 'publish',
            'post_type'    => 'product'
        );


        $post_id = wp_insert_post($post, true);
        if($post_id instanceof \WP_Error) {
            $messages = implode("\n", $post_id->get_error_messages());
            throw new \Exception($messages);
        }

        $product = new Product($post_id);

        if (!is_null($sku))
        {
            $product->set_sku($sku);
        }

        if (!is_null($product_type))
        {
            $product->set_product_type($product_type);
        }

        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');

        return $product;
    }

    /**
     * Get the product id of product with matching sku
     *
     * @param $sku
     *
     * @return null|string
     */
    public static function find_by_sku($sku)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
    }

    /**
     * Determine if product with matching skew exists in the database
     *
     * @param $sku
     *
     * @return bool
     */
    public static function sku_exists($sku)
    {
        $result = static::find_by_sku($sku);
        if ($result)
        {
            return true;
        }
        return false;
    }

    public function set_title($title) {
        $this->post->post_title = $title;
        wp_update_post($this->post);
        return $this;
    }


    public function get_meta($key='', $single = true, $context = 'view')
    {
        return get_post_meta($this->id, $key, $single);
    }

    public function set_meta($key, $value)
    {
        update_post_meta($this->id, $key, $value);
    }

    public function set_sku($sku)
    {
        $this->set_meta('_sku', $sku);
        return $this;
    }

    public function set_product_type($product_type)
    {
        $this->set_meta('_product_type', $product_type);
        return $this;
    }

    public function get_product_type()
    {
        return $this->get_meta('_product_type');
    }

    /**
     * Get internal type. Should return string and *should be overridden* by child classes.
     *
     *
     * @since 3.0.0
     * @return string
     */
    public function get_type() {
        return $this->get_meta('_product_type');
    }

    public function set_brand_key($brand_key)
    {
        $this->set_meta('_brand_key', $brand_key);
        return $this;
    }

    public function get_brand_key()
    {
        return $this->get_meta('_brand_key');
    }

    public function set_bottles_per_sku($bps)
    {
        $this->set_meta('_bottles_per_sku', $bps);
        return $this;
    }

    public function get_bottles_per_sku()
    {
        return $this->get_meta('_bottles_per_sku');
    }

    public function set_bottle_size($size)
    {
        $this->set_meta('_bottle_size', $size);
        return $this;
    }

    public function get_bottle_size()
    {
        return $this->get_meta('_bottle_size');
    }

    public function set_bottle_units($units)
    {
        $this->set_meta('_bottle_units', $units);
        return $this;
    }

    public function get_bottle_units()
    {
        return $this->get_meta('_bottle_units');
    }

    public function set_alcohol_by_volume($abv)
    {
        $this->set_meta('_alcohol_by_volume', $abv);
        return $this;
    }

    public function get_alcohol_by_volume()
    {
        return $this->get_meta('_alcohol_by_volume');
    }

    public function set_varietal($varietal)
    {
        $this->set_meta('_varietal', $varietal);
        return $this;
    }

    public function get_varietal()
    {
        return $this->get_meta('_varietal');
    }

    public function set_vintage($vintage)
    {
        $this->set_meta('_vintage', $vintage);
        return $this;
    }

    public function get_vintage()
    {
        return $this->get_meta('_vintage');
    }

    public function set_style($style)
    {
        $this->set_meta('_style', $style);
        return $this;
    }

    public function get_style()
    {
        return $this->get_meta('_style');
    }


	public function set_container_type($type) {
		$this->set_meta('_container_type', $type);
		return $this;
	}

	public function get_container_type() {
		return $this->get_meta('_container_type');
	}

    public function set_flavor($flavor)
    {
        $this->set_meta('_flavor', $flavor);
        return $this;
    }

    public function get_flavor()
    {
        return $this->get_meta('_flavor');
    }

    public function set_age($age)
    {
        $this->set_meta('_age', $age);
        return $this;
    }

    public function get_age()
    {
        return $this->get_meta('_age');
    }

    public function set_ttb_id($ttb_id)
    {
        $this->set_meta('_ttb_id', $ttb_id);
        return $this;
    }

    public function get_ttb_id()
    {
        return $this->get_meta('_ttb_id');
    }

    public function set_serial_number($serial_number)
    {
        $this->set_meta('_serial_number', $serial_number);
        return $this;
    }

    public function get_serial_number()
    {
        return $this->get_meta('_serial_number');
    }


    public function set_price($price) {
        $price = floatval($price);
        if($price <= 0) {
            $price = null;
        }
        $this->price = $price;
        $this->set_meta('_price', $price);
        $this->set_meta('_regular_price', $price);
        $this->set_meta('_sale_price', $price);
    }


    public function has_enough_data_to_sync($yesno = null)
    {
        if (is_bool($yesno))
        {
            $this->set_meta('_has_enough_data_to_sync', $yesno);
        }
        return $this->get_meta('_has_enough_data_to_sync');
    }

    public function is_shipcompliant_product($yesno = null)
    {
        if (is_bool($yesno))
        {
            $this->set_meta('_shipcompliant_product', $yesno);
        }
        return $this->get_meta('_shipcompliant_product');
    }

	public function is_wine() {
		return ProductTypes::is_wine_type($this->get_product_type());
	}

	public function is_beer() {
		return ProductTypes::is_beer_type($this->get_product_type());
	}

	public function is_spirit() {
		return ProductTypes::is_spirit($this->get_product_type());
	}

	public function is_beverage() {
		return ProductTypes::is_beverage($this->get_product_type());
	}

	public function has_container() {
		return ProductTypes::has_container($this->get_product_type());
	}

}
