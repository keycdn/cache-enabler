<?php


// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler_Dependencies
 *
 * @since 2.1.0
 * @change 2.1.0
 */

final class Cache_Enabler_Dependencies {

    /**
     * The active plugins.
     *
     * @access private
     */
    private $active_plugins;

    /**
     * The singleton instance of this class.
     *
     * @access private
     */
    private static $_instance = null;


    /**
     * Cache_Enabler_Dependencies constructor.
     */
    public function __construct() {

        $this->active_plugins = (array) get_option( 'active_plugins', array() );

        if ( is_multisite() ) {
            $this->active_plugins = array_merge( $this->active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
        }
    }

    /**
     * Get the singleton instance of this class.
     *
     * @return Cache_Enabler_Dependencies|null
     */
    public static function getInstance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function is_active( $plugin ) {

        $instance = self::getInstance();

        return in_array( $plugin, $instance->active_plugins ) || array_key_exists( $plugin, $instance->active_plugins );
    }
}


