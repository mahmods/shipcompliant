<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/20/14
 * Time: 6:18 PM
 */

namespace H2\ShipCompliant\Shipping;


    /**
     * WC Keys
     * -'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
     * -'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
     * -'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
     * -'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
     * -'FEDEX_2_DAY'                        => 'FedEx 2Day',
     * -'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
     * -'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
     * -'FEDEX_GROUND'                       => 'FedEx Ground',
     * -'INTERNATIONAL_ECONOMY'              => 'FedEx International Economy',
     * -'INTERNATIONAL_FIRST'                => 'FedEx International First',
     * -'INTERNATIONAL_PRIORITY'             => 'FedEx International Priority',
     * -'EUROPE_FIRST_INTERNTIONAL_PRIORITY' => 'FedEx Europe First International Priority',
     * 'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
     * 'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
     * 'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
     * 'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
     * 'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
     * 'FEDEX_FREIGHT'                      => 'Fedex Freight',
     * 'FEDEX_NATIONAL_FREIGHT'             => 'FedEx National Freight',
     * 'INTERNATIONAL_GROUND'               => 'FedEx International Ground',
     * 'SMART_POST'                         => 'FedEx Smart Post',
     * 'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
     * 'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
     * 'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
     */

    /**
     * SC Keys
     * - FEX    FedEx 2Day
     * FEX3    FedEX 3Day
     * FEXCC    FedEx Cold Chain
     * - FXES    FedEx Express Saver
     * FXE    FedEx Express Service
     * - FXFO    FedEx First overnight
     * - FXG    FedEx Ground
     * - FGH    FedEx Ground Home
     * - FXIE    FedEx International economy
     * - FXIF    FedEx International First
     * - FXIP    FedEx International Priority
     * - FXP    FedEx Priority Overnight
     * - FEXA    FedEx Second Day AM
     * - FXO    FedEx Standard Overnight
     */
use H2\ShipCompliant\Util\Logger;

/**
 * Class FedExMapper
 * @package H2\ShipCompliant\Shipping
 */
class FedExMapper implements MapperInterface {

    private $mappings = array(
        'FIRST_OVERNIGHT'                    => 'FXFO',
        'PRIORITY_OVERNIGHT'                 => 'FXP',
        'STANDARD_OVERNIGHT'                 => 'FXO',
        'FEDEX_2_DAY_AM'                     => 'FEXA',
        'FEDEX_2_DAY'                        => 'FEX',
        'FEDEX_EXPRESS_SAVER'                => 'FXES',
        'GROUND_HOME_DELIVERY'               => 'FGH',
        'FEDEX_GROUND'                       => 'FXG',
        'INTERNATIONAL_ECONOMY'              => 'FXIE',
        'INTERNATIONAL_FIRST'                => 'FXIF',
        'INTERNATIONAL_PRIORITY'             => 'FXIP',
        'EUROPE_FIRST_INTERNTIONAL_PRIORITY' => 'FXIP',
    );

//    private $unsupported = array(
//        'FEDEX_1_DAY_FREIGHT',
//        'FEDEX_2_DAY_FREIGHT',
//        'FEDEX_3_DAY_FREIGHT',
//        'INTERNATIONAL_ECONOMY_FREIGHT',
//        'INTERNATIONAL_PRIORITY_FREIGHT',
//        'FEDEX_FREIGHT',
//        'FEDEX_NATIONAL_FREIGHT',
//        'INTERNATIONAL_GROUND',
//        'SMART_POST',
//        'FEDEX_FIRST_FREIGHT',
//        'FEDEX_FREIGHT_ECONOMY',
//        'FEDEX_FREIGHT_PRIORITY',
//    );

    public function getShipCompliantKey($wooKey)
    {
        $key = "OTHER";
        if (array_key_exists($wooKey, $this->mappings))
        {
            $key = $this->mappings[$wooKey];
        }

        Logger::debug('FedExMapper - Mapping Woo shipping key to ShipCompliant key', array(
            'woo_key' => $wooKey,
            'sc_key' => $key
        ));

        return $key;
    }

    public function getWooCommerceKey($sckey)
    {
        // TODO: Implement getWooCommerceKey() method.
        throw new \Exception('Not Implemented');
    }
}
