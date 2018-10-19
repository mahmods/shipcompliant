<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/20/14
 * Time: 6:18 PM
 */

namespace H2\ShipCompliant\Shipping;

use H2\ShipCompliant\Util\Logger;

class UPSMapper implements MapperInterface{

    /*
     * Woo Keys
     *
     * "01" => "Next Day Air",//
     * "02" => "2nd Day Air",//
     * "03" => "Ground",//
     * "07" => "Worldwide Express",
     *
     * "08" => "Worldwide Expedited",
     * "11" => "Standard",
     * "12" => "3 Day Select",
     * "13" => "Next Day Air Saver",
     * "14" => "Next Day Air Early AM",
     * // International
     *
     * "54" => "Worldwide Express Plus",
     * "59" => "2nd Day Air AM",
     * "65" => "Saver",
     */

    /*
     * ShipCompliant Keys
     *
     * UP2    UPS 2nd Day
     * 2DM    UPS 2nd Day Air AM
     * UP3    UPS 3 Day
     * UPS    UPS Ground
     * - FTX    UPS LTL Express - Not Implemented
     * FTS    UPS LTL Standard
     * - UP1    UPS Next Day  - Not Implemented
     * UPO    UPS Next Day Air
     * UPA    UPS Next Day Air Early A.M.
     * UPAS    UPS next Day Air Saver
     * - UPSNS    UPS Next Day Air Service - Not Implemented
     * UPSA    UPS Saver
     * UPWEXD    UPS worldwide expedited sm
     * UPWEXP    UPS worldwide Express plus sm
     * UPWEXS    UPS worldwide Express sm
     */
    private $mappings = array(
        '01' => 'UPO',
        '02' => 'UP2',
        '03' => 'UPS',
        '07' => 'UPWEXS',
        '08' => 'UPWEXD',
        '11' => 'FTS',
        '12' => 'UP2',
        '13' => 'UPAS',
        '14' => 'UPA',
        '54' => 'UPWEXP',
        '59' => '2DM',
        '65' => 'UPSA'
    );

    public function getShipCompliantKey($wooKey)
    {
        $key = "OTHER";
        if (array_key_exists($wooKey, $this->mappings))
        {
            $key = $this->mappings[$wooKey];
        }

        Logger::debug('UPSMapper - Mapping Woo shipping key to ShipCompliant key', array(
            'woo_key' => $wooKey,
            'sc_key' => $key
        ));

        return $key;
    }

    public function getWooCommerceKey($scKey) {
        // flip keys and values, check for existance
        throw new \Exception('Not Implemented');
    }
}
