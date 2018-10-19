<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/21/14
 * Time: 3:17 PM
 */

namespace H2\ShipCompliant\API;

class Security {

    public $Username;
    public $Password;
    public $PartnerKey;

    /**
     * @param $username
     * @param $password
     * @param $partner_key
     *
     * @return Security
     */
    public function __construct($username, $password, $partner_key) {
        $this->Username = $username;
        $this->Password = $password;
        $this->PartnerKey = $partner_key;
    }


    public function isReady() {
        if(!empty($this->Username) && !empty($this->Password) && !empty($this->PartnerKey)) {
            return true;
        }
        return false;
    }

} 