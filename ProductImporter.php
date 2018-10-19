<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/21/14
 * Time: 5:18 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\StringDecorator;
use H2\ShipCompliant\Model\ProductMapper;
use H2\ShipCompliant\Plugin;

class ProductImporter {


    private $created = 0;
    private $updated = 0;


    /**
     * @param $filename
     *
     * @return array
     * @throws \Exception
     */
    public function importCsv($filename)
    {
        Logger::info('Importing CSV', array('filename' => $filename));
        $this->created = 0;
        $this->updated = 0;

        $data = $this->csvToArray($filename);
        if ($data == false)
        {
            Logger::error('Bad file format while trying to import CSV.', array(
                'data'     => $data,
                'filename' => $filename
            ));

            throw new \Exception('Bad file format.  Please check the file and try again.');
        }

        ProductSync::remove_actions();

        foreach ($data as $row)
        {

            $post_id = ProductMapper::find_by_sku($row['product-key']);
            $price   = floatval(str_replace('$', '', $row['default-retail-unit-price-per-sku']));
            $product = ProductMapper::create_post($row['product-description']);

            if (empty($post_id))
            {
                $post_id = wp_insert_post($product);
                $this->created++;
            }
            else
            {
                $product['ID'] = $post_id;
                wp_update_post($product);
                $this->updated++;
            }

            wp_set_object_terms($post_id, 'simple', 'product-type');

            update_post_meta($post_id, '_sku', $row['product-key']);
            update_post_meta($post_id, '_price', $price);
            update_post_meta($post_id, '_visibility', 'visible');
            update_post_meta($post_id, '_brand_key', $row['brand-key']);
            update_post_meta($post_id, '_bottles_per_sku', $row['bottles-per-sku-retail']);
            update_post_meta($post_id, '_bottle_size', $row['bottle-size']);
            update_post_meta($post_id, '_bottle_units', $row['bottle-units']);
            update_post_meta($post_id, '_alcohol_by_volume', $row['alcohol-by-volume']);
            update_post_meta($post_id, '_product_type', $row['product-type']);
            update_post_meta($post_id, '_ttb_id', $row['ttb-id']);
            update_post_meta($post_id, '_serial_number', $row['serial-number']);

            $varietal = $row['varietal-wine-flavor-spirits-style-beer'];
            $vintage  = $row['vintage-wine-age-spirits-containers-per-selling-unit-beer'];

            switch (strtolower($row['product-type']))
            {
                case "wine":
                    update_post_meta($post_id, '_varietal', $varietal);
                    update_post_meta($post_id, '_vintage', $vintage);
                    break;
                case "beer":
                    update_post_meta($post_id, '_style', $varietal);
                    update_post_meta($post_id, '_containers_per_selling_unit', $vintage);
                    break;
                case "spirits":
                    update_post_meta($post_id, '_flavor', $varietal);
                    update_post_meta($post_id, '_age', $vintage);
                    break;
            }

        }

        return array(
            'created' => $this->created,
            'updated' => $this->updated
        );
    }


    /**
     * Convert a CSV file to PHP Array
     *
     * @param $filename
     *
     * @return array|bool
     * @throws \Exception
     */
    public function csvToArray($filename)
    {
        $delimiter = ",";
        $header    = null;
        $data      = array();

        if (!file_exists($filename) || !is_readable($filename))
        {
            return false;
        }

        if (($handle = fopen($filename, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false)
            {
                if (!$header)
                {
                    $header = array();
                    foreach ($row as $key => $value)
                    {
                        $value    = new StringDecorator($value);
                        $header[] = $value->toSlug();
                    }
                }
                else
                {
                    $data[] = @array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        else
        {
            throw new \Exception('Sorry, the import failed. Please check the file and try again.');
        }

        if ($data == false)
        {
            throw new \Exception('Invalid file format. Please check the file and try again.');
        }

        return $data;
    }

}
