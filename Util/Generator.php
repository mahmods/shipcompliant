<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/14/14
 * Time: 6:18 PM
 */

namespace H2\ShipCompliant\Util;

class Generator {

    /**
     * Generate a globally unique identifier - GUID
     * @return string
     */
    public static function getGUID()
    {
        // For windows systems, use internal COM mechanism
        if (function_exists('com_create_guid'))
        {
            return com_create_guid();
        }
        else
        {
            mt_srand((double)microtime() * 10000); //optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45); // "-"
            $uuid   = chr(123) // "{"
                . substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12)
                . chr(125);
            // "}"
            return $uuid;
        }
    }
} 