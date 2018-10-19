<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/21/14
 * Time: 1:33 PM
 */

namespace H2\ShipCompliant\Shipping;


interface MapperInterface {

    public function getShipCompliantKey($wckey);

    public function getWooCommerceKey($sckey);
} 