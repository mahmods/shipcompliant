<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/12/14
 * Time: 2:43 PM
 */

namespace H2\ShipCompliant;

use H2\ShipCompliant\Admin\AbstractAdmin;
use H2\ShipCompliant\Util\View;

class Admin extends AbstractAdmin {

    public function setup_menus()
    {
        $icon_url = 'none';
        $callback = array($this, 'render');
        add_menu_page('ShipCompliant', 'ShipCompliant', 'manage_options', 'shipcompliant-main', $callback, $icon_url, 59);
    }

    public function render()
    {
        View::render("admin/maintab", array());
    }
}