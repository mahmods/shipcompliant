<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/25/14
 * Time: 7:44 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\API\AddressService;

class AddressManager {

    private $service = null;

    public function __construct()
    {
        $this->service = new AddressService(Plugin::getInstance()->getSecurity());
    }

    /**
     *
     * @param array $address
     */
    public function validateAddress(array $address)
    {
        $request = array(
            'Address' => $address
        );

        $response = $this->service->validateAddress($request);
        return $response;
    }
} 