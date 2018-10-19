<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/23/14
 * Time: 1:39 PM
 */

namespace H2\ShipCompliant\Commands;

use H2\ShipCompliant\API\TaxService;
use H2\ShipCompliant\API\Security;
use H2\ShipCompliant\Util\States;

/**
 * Commands for ShipCompliant
 * @package H2\Commands
 */
class CliCommands extends \WP_CLI_Command {

    /**
     * Import ShipCompliant Product CSV
     *
     * ## OPTIONS
     *
     * <filename>
     * : The full path to the csv file to import
     *
     * ## EXAMPLES
     *
     *     wp shipcompliant import_products /path/to/export.csv
     *
     * @synopsis <filename>
     * @subcommand import-products
     */
    public function importProducts($args, $assoc_args)
    {
        list($filename) = $args;
        $importer = new ProductImporter();
        $importer->importCsv($filename);
    }


}