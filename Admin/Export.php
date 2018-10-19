<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/28/14
 * Time: 9:39 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\View;
use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Model\Product;
use H2\ShipCompliant\API\ProductTypes;

class Export extends AbstractAdmin {


    public static function init()
    {
        $instance = parent::init();
        add_action('admin_init', array($instance, 'download_csv'));
    }

    public function setup_menus()
    {
        \add_submenu_page('shipcompliant-main', 'Export', 'Export', 'manage_options', 'shipcompliant-export', array(
                $this,
                'render'
            ));
    }

    public function render()
    {
        wp_enqueue_style('angular-csp', sprintf('%s/assets/js/vendor/angular/angular-csp.css', SHIPCOMPLIANT_PLUGIN_URL));
        wp_enqueue_style('shipcompliant-product-import', sprintf("%s/assets/styles/product-import.css", SHIPCOMPLIANT_PLUGIN_URL), array('angular-csp'));
        wp_enqueue_script('angular', sprintf('%s/assets/js/vendor/angular/angular.min.js', SHIPCOMPLIANT_PLUGIN_URL), array('jquery'));
        wp_enqueue_script('shipcompliant-product-export', sprintf("%s/assets/js/sync/product-export.js", SHIPCOMPLIANT_PLUGIN_URL), array('angular'));


        View::render('admin/export', array());
    }


    public function get_csv_titles()
    {
        return array(
            "Version",
            "Product Key",
            "Brand Key",
            "Product Type",
            "Product Description",
            "# Bottles per SKU (Retail)",
            "Bottle Size",
            "Bottle Units",
            "Bottles Per Case (Wholesale)",
            "Alcohol By Volume %",
            "Default Retail Unit Price per SKU",
            "Default Wholesale Price per Case",
            "Varietal (Wine)/ Flavor (Spirits)/	Container Type (Beer)",
            "Vintage (Wine)/ Age (Spirits)/	Containers per Selling Unit (Beer)",
            "UPC",
            "UNIMERC",
            "NABCA",
            "GTIN",
            "SCC",
            "Weight Lbs",
            "Label Key",
            "TTB ID",
            "Serial Number",
            "Sell In Direct",
            "Sell in Wholesale",
            "Sub Brand",
            "Reserved 2",
            "Reserved 3",
            "FOB Key"
        );
    }

    public function download_csv()
    {
        if (empty($_REQUEST['exportcsv']))
        {
            return;
        }

        $titles = $this->get_csv_titles();
        $output = implode(",", $titles);
        $output .= "\n";

        $results = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1));
        foreach ($results as $post)
        {

            //$product = new \WC_Product($post->ID);
            $product = new Product( $post->ID );

            switch ($product->get_product_type())
            {

                case "beer":
                    $varietal = $product->get_attribute('style');
                    $vintage  = $product->get_attribute('containers_per_selling_unit');
                    break;
                case "spirits":
                    $varietal = $product->get_attribute('flavor');
                    $vintage  = $product->get_attribute('age');
                    break;
                case "wine":
                default:
                    $varietal = $product->get_attribute('varietal');
                    $vintage  = $product->get_attribute('vintage');
                    break;
            }

            $data = array(
                2,
                $product->get_attribute('product-key'),
                $product->get_attribute('brand-key'),
                $product->get_attribute('product-type'),
                $post->post_title,
                $product->get_attribute('bottles-per-sku'),
                $product->get_attribute('bottle-size'),
                $product->get_attribute('bottle-units'),
                null, // containers per case
                str_replace('%', '', $product->get_attribute('alcohol_by_volume')),
                $product->get_price(),
                null,
                $varietal,
                $vintage,
                null, // UPC
                null, // UNIMERC
                null, // NABCA
                null, // GTIN
                null, // SCC
                $product->get_weight(),
                null, // label key - TODO: start importing this if we can
                null, // TTB ID
                null, // Serial Number
                1, // sell in direct
                0, // sell in wholesale
                null, // sub brand
                null, // reserved 1
                null, // reserved 2
                null, // FOB KEY
            );

            $output .= implode(',', $data);
            $output .= "\n";

        }

        Logger::info('Exported CSV');

        header("Content-type: application/x-msdownload");
        header(sprintf("Content-Disposition: attachment; filename=ProductExport-%s.csv", date('Y-m-d')));
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $output;
        exit();
    }


}
