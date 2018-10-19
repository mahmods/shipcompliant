<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/28/14
 * Time: 9:42 PM
 */

namespace H2\ShipCompliant\Admin;

abstract class AbstractAdmin {

    public static function init()
    {
        return static::get_instance();
    }

    abstract public function setup_menus();

    abstract public function render();


    private function __construct()
    {
        add_action('admin_menu', array($this, 'setup_menus'));
    }

    final public static function get_instance()
    {
        static $instances = array();

        $calledClass = get_called_class();

        if (!isset($instances[$calledClass]))
        {
            $instances[$calledClass] = new $calledClass();
        }

        return $instances[$calledClass];
    }

    final private function __clone()
    {
    }



} 