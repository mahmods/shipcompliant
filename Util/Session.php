<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 8/14/14
 * Time: 8:58 PM
 */

namespace H2\ShipCompliant\Util;

/**
 * Class Session
 * Plugin specific session handler
 * @package H2\ShipCompliant\Util
 */
class Session {

    const SESSION_KEY = "ShipCompliant";

    public static function init()
    {
        if (!array_key_exists(static::SESSION_KEY, $_SESSION))
        {
            $_SESSION[static::SESSION_KEY] = array();
        }
    }

    public static function get($key)
    {
        if (static::has($key))
        {
            return $_SESSION[static::SESSION_KEY][$key];
        }
        return null;
    }

    public static function set($key, $value)
    {
        $_SESSION[static::SESSION_KEY][$key] = $value;
    }

    public static function has($key)
    {
        if (array_key_exists(static::SESSION_KEY,$_SESSION))
        {
            if (array_key_exists($key, $_SESSION[static::SESSION_KEY]))
            {
                return true;
            }
        }
        return false;
    }

    public static function remove($key)
    {
        if (static::has($key))
        {
            unset($_SESSION[static::SESSION_KEY][$key]);
        }
    }

    public static function all()
    {
        return $_SESSION[static::SESSION_KEY];
    }

    public static function removeAll() {
        $_SESSION[static::SESSION_KEY] = array();
    }

}
