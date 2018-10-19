<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/28/14
 * Time: 9:37 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\Plugin;
use H2\ShipCompliant\Util\Logger;
use H2\ShipCompliant\Util\View;
use H2\ShipCompliant\ProductImporter;

class Import extends AbstractAdmin {

    public static function init()
    {
        $instance = parent::init();
        add_action('admin_init', array($instance, 'handle_import'));
    }

    public function setup_menus()
    {
        \add_submenu_page('shipcompliant-main', 'Import', 'Import', 'manage_options', 'shipcompliant-import', array(
            $this,
            'render'
        ));
    }

    public function render()
    {
        wp_enqueue_style('angular-csp', sprintf('%s/assets/js/vendor/angular/angular-csp.css', SHIPCOMPLIANT_PLUGIN_URL));
        wp_enqueue_style('shipcompliant-product-import', sprintf("%s/assets/styles/product-import.css", SHIPCOMPLIANT_PLUGIN_URL), array('angular-csp'));
        wp_enqueue_script('angular', sprintf('%s/assets/js/vendor/angular/angular.min.js', SHIPCOMPLIANT_PLUGIN_URL), array('jquery'));
        wp_enqueue_script('shipcompliant-product-import', sprintf("%s/assets/js/sync/product-import.js", SHIPCOMPLIANT_PLUGIN_URL), array('angular'));

        View::render('admin/import', array());
    }

    private function handle_upload()
    {
        if (!empty($_FILES['csvimport']))
        {
            Logger::info('Attempting CSV Import');

            if (!function_exists('wp_handle_upload'))
            {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $uploadedfile     = $_FILES['csvimport'];
            $upload_overrides = array('test_form' => false);
            $movefile         = wp_handle_upload($uploadedfile, $upload_overrides);
            if (!$movefile)
            {
                $msg = 'There was a problem with the file you uploaded. Try a different one?';
                Logger::error($msg, array('FILES' => $uploadedfile));
                throw new \RuntimeException($msg);
            }
            return $movefile;
        }

        return false;
    }

    public function handle_import()
    {
        try
        {
            $file = $this->handle_upload();
            if ($file !== false && !empty($file['file']))
            {
                $importer = new ProductImporter();
                $importer->importCsv($file['file']);
                $msg = 'File was successfully imported';
                Logger::info($msg);
                Plugin::getInstance()->add_admin_notice($msg);
            }
        }
        catch(\Exception $ex)
        {
            Logger::error($ex->getMessage());
            Plugin::getInstance()->add_admin_notice($ex->getMessage(), 'error');
        }
    }
}
