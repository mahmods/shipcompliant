<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/9/14
 * Time: 3:25 PM
 */

namespace H2\ShipCompliant\Model;

use H2\ShipCompliant\API\ProductService;
use H2\ShipCompliant\Plugin;

/**
 * Class ProductMapper
 * TODO: move all logic related to product entities here
 * @package H2\ShipCompliant\Model
 */
class ProductMapper {

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
    public static function product_exists($sku)
    {
        $result = static::find_by_sku($sku);
        if ($result)
        {
            return true;
        }
        return false;
    }


    /**
     * Create the wp post for this product
     *
     * @param $title
     *
     * @return array
     */
    public static function create_post($title)
    {
        return array(
            'post_title'   => $title,
            'post_content' => $title,
            'post_status'  => 'publish',
            'post_type'    => 'product'
        );
    }


    /**
     * Retrieve product from ShipCompliant
     *
     * @param $brand_key
     * @param $product_key
     *
     * @return mixed
     * @throws \Exception
     */
    public static function get_shipcompliant_product($brand_key, $product_key)
    {
        $service  = new ProductService(Plugin::getInstance()->getSecurity());
        $response = $service->getProduct(array(
            'BrandKey'   => $brand_key,
            'ProductKey' => $product_key
        ));

        $result = $response->GetProductResult;
        if ($result->ResponseStatus != "Success")
        {
            throw new \Exception('There was a problem getting product information from shipcompliant');
        }

        return $result->Product;
    }

    public static function get_more_shipcompliant_products(ProductService $service, $pagingCookie)
    {
        $searchMore = true;
        $products   = array();

        do
        {
            $response = $service->SearchMoreProducts(array("PagingCookie" => $pagingCookie));

            $result = $response->SearchMoreProductsResult;
            if ($result->ResponseStatus != "Success")
            {
                throw new \Exception('There was a problem getting product information from shipcompliant');
            }

            $products = array_merge($products, $result->Products->ProductOutput);
            $pagingCookie = $result->PagingCookie;

            if ($result->CountMoreProductsAvailable <= 0)
            {
                $searchMore = false;
            }
        } while ($searchMore);

        return $products;
    }


    /**
     * Get ALL of the products in shipcompliant
     */
    public static function get_shipcompliant_products()
    {
        $service = new ProductService(Plugin::getInstance()->getSecurity());

        // get first 100 products
        $response = $service->SearchProducts(array());

        $result = $response->SearchProductsResult;
        if ($result->ResponseStatus != "Success")
        {
            throw new \Exception('There was a problem getting product information from shipcompliant');
        }

        $products = $result->Products->ProductOutput;

        // get the rest now
        if ($result->CountMoreProductsAvailable > 0)
        {
            $products = array_merge($products,static::get_more_shipcompliant_products($service, $result->PagingCookie));
        }

        return $products;

    }

}