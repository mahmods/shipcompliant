<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 10/15/14
 * Time: 7:13 PM
 */

namespace H2\ShipCompliant\Admin;

use H2\ShipCompliant\Util\View;
use H2\ShipCompliant\Plugin;

class LogViewer extends AbstractAdmin {

    public static function init()
    {
        $instance = parent::init();
        add_action('wp_ajax_shipcompliant_logs', array($instance, 'ajax_get_entries'));
    }

    public function setup_menus()
    {
        $parent = "shipcompliant-main";
        if (!Plugin::getInstance()->is_license_activated())
        {
           $parent = "shipcompliant-activation";
        }

        add_submenu_page($parent, 'Log Viewer', 'Log Viewer', 'manage_options', 'shipcompliant-logviewer', array(
            $this,
            'render'
        ));
    }

    public function ajax_get_entries()
    {
        header('Content-type: application/json');

        $pager = $this->get_pager();

        $entries = $this->get_entries($pager);

        for ($i = 0; $i < count($entries); $i++)
        {
            $entries[$i]->dumpVars = get_post_meta($entries[$i]->ID, '_dumpVars');
        }

        $response = array(
            'pager'   => $pager,
            'entries' => $entries
        );

        echo json_encode($response);
        exit;
    }

    public function load_assets()
    {
        wp_enqueue_style('angular-csp', sprintf('%s/assets/js/vendor/angular/angular-csp.css', SHIPCOMPLIANT_PLUGIN_URL));
        wp_enqueue_style('font-awesome', "//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css");
        wp_enqueue_style('shipcompliant-logviewer', sprintf("%s/assets/styles/logviewer.css", SHIPCOMPLIANT_PLUGIN_URL), array('angular-csp'));

        wp_enqueue_script('angular', sprintf('%s/assets/js/vendor/angular/angular.min.js', SHIPCOMPLIANT_PLUGIN_URL), array('jquery'));
        wp_enqueue_script('shipcompliant-logviewer', sprintf("%s/assets/js/logviewer.js", SHIPCOMPLIANT_PLUGIN_URL), array('angular'));
    }

    public function get_pager()
    {
        global $wpdb;
        ini_set('html_errors', 0);

        $page = 1;
        if (isset($_REQUEST['page']))
        {
            $page = intval($_REQUEST['page']);
        }

        $total_posts    = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type='shipcompliant_log'");
        $posts_per_page = 40;
        $total_pages    = ceil($total_posts / $posts_per_page);
        $offset         = $posts_per_page * ($page - 1);

        $prev = $page - 1;
        $next = $page + 1;

        $hasPrev = true;
        $hasNext = true;

        if ($prev <= 0)
        {
            $hasPrev = false;
        }

        if ($next > $total_pages)
        {
            $hasNext = false;
        }

        $pager = array(
            'page'           => $page,
            'offset'         => $offset,
            'posts_per_page' => $posts_per_page,
            'total_posts'    => $total_posts,
            'total_pages'    => $total_pages,
            'has_prev'       => $hasPrev,
            'has_next'       => $hasNext,
            'prev_page'      => $prev,
            'next_page'      => $next
        );

        return $pager;
    }

    public function get_entries($pager)
    {
        return get_posts(array(
            'posts_per_page' => $pager['posts_per_page'],
            'offset'         => $pager['offset'],
            'post_type'      => 'shipcompliant_log'
        ));
    }

    public function render()
    {
        $this->load_assets();
        View::render('admin/logs');
    }
}