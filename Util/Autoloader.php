<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/14/14
 * Time: 12:00 PM
 */

namespace H2\ShipCompliant\Util;

/**
 * PSR-4 Compliant Autoloader
 * @package H2\ShipCompliant\Util
 */
class Autoloader {

    private $prefix;

    public static function init($prefix)
    {
        spl_autoload_register(array(new Autoloader($prefix), 'loadClass'));
    }

    public function __construct($prefix) {
        $this->prefix = $prefix;
    }

    public function loadClass($className)
    {


        // base directory for the namespace prefix
        $base_dir = realpath(__DIR__ . '/../');

        // does the class use the namespace prefix?
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $className, $len) !== 0)
        {
            // no, move to the next registered autoloader
            return;
        }

        // get the relative class name
        $relative_class = substr($className, $len);

        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $base_dir . DIRECTORY_SEPARATOR . str_replace('\\', '/', $relative_class) . '.php';

        // if the file exists, require it
        if (file_exists($file))
        {
            require $file;
        }
    }
}