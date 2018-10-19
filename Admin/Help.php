<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 9/28/14
 * Time: 9:53 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\Util\View;

class Help extends AbstractAdmin {

    public function setup_menus()
    {
        add_submenu_page('shipcompliant-main', 'Help', 'Help', 'manage_options', 'shipcompliant-help', array(
            $this,
            'render'
        ));
    }

    public function render()
    {
        View::render('admin/help', array());
    }
}