<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 10/15/14
 * Time: 6:23 PM
 */

namespace H2\ShipCompliant\Util;

use H2\ShipCompliant\Plugin;

class Logger {

	private static $instance = null;

	public static function register() {
		static::rotate_logs();
	}


	/**
	 * Periodically prunes old log entries.
	 */
	public static function rotate_logs() {
		global $wpdb;

		$rotation_done = get_transient( 'shipcompliant_logrotate_done' );
		if ( ! $rotation_done ) {
			// @see http://stackoverflow.com/a/578926
			// using HEREDOC format for long lines
			$sql = <<<SQL
DELETE FROM $wpdb->posts
WHERE post_type='shipcompliant_log'
AND ID NOT IN (
  SELECT ID
  FROM (
    SELECT ID
    FROM $wpdb->posts
    WHERE post_type='shipcompliant_log'
    ORDER BY ID DESC
    LIMIT 1000 -- keep this many records
  ) foo -- every alias must have its own derived table
);
SQL;

			$deleted_rows = $wpdb->query($sql);
	        Logger::debug('Pruning Logs', array('deleted_rows' => $deleted_rows));
            set_transient('shipcompliant_logrotate_done', true, 3600 * 1); // every hour
        }

    }


    public static function get_instance()
    {
        if (is_null(static::$instance))
        {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function write($message, $level)
    {
        if (Plugin::getInstance()->isDebugMode()) {
            $entry = array(
                'post_title'   => Generator::getGUID(),
                'post_content' => $message,
                'post_excerpt' => $level,
                'post_status'  => 'publish',
                'post_type'    => 'shipcompliant_log'
            );

            $post_id = wp_insert_post($entry);
            return $post_id;
        }
    }

    public static function info($message)
    {
        return static::write($message, 'info');
    }

    public static function debug($message, $dump_vars = array())
    {
        $post_id = static::write($message, 'debug');
        static::dump_vars($post_id, $dump_vars);
        return $post_id;
    }

    public static function error($message, $dump_vars = array())
    {
        $post_id = static::write($message, 'error');
        static::dump_vars($post_id, $dump_vars);
        return $post_id;
    }

    public static function dump_vars($post_id, $dump_vars = array())
    {
        $meta_dump_vars = array();
        if ( is_array( $dump_vars ) ) {
        foreach ($dump_vars as $key => $var)
            {
                $meta_dump_vars[$key] = $var;
            }
        }
        else {
            $meta_dump_vars[0] = $dump_vars;
        }
        update_post_meta($post_id, '_dumpVars', $meta_dump_vars);
    }

}
