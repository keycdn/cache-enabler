<?php
/*
Plugin Name: Cache Enabler
Text Domain: cache-enabler
Description: Simple and fast WordPress caching plugin.
Author: KeyCDN
Author URI: https://www.keycdn.com
License: GPLv2 or later
Version: 1.7.2
*/

/*
Copyright (C) 2021 KeyCDN

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// constants
define( 'CACHE_ENABLER_VERSION', '1.7.2' );
define( 'CACHE_ENABLER_MIN_PHP', '5.6' );
define( 'CACHE_ENABLER_MIN_WP', '5.1' );
define( 'CACHE_ENABLER_FILE', __FILE__ );
define( 'CACHE_ENABLER_BASE', plugin_basename( __FILE__ ) );

if ( ! defined( 'CACHE_ENABLER_DIR' ) ) {
    define( 'CACHE_ENABLER_DIR', __DIR__ );
}

// deprecated constants (1.7.0)
define( 'CE_VERSION', CACHE_ENABLER_VERSION );
define( 'CE_MIN_PHP', CACHE_ENABLER_MIN_PHP );
define( 'CE_MIN_WP', CACHE_ENABLER_MIN_WP );
define( 'CE_FILE', CACHE_ENABLER_FILE );
define( 'CE_BASE', CACHE_ENABLER_BASE );
define( 'CE_DIR', CACHE_ENABLER_DIR );

// hooks
add_action( 'plugins_loaded', array( 'Cache_Enabler', 'init' ) );
register_activation_hook( __FILE__, array( 'Cache_Enabler', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Cache_Enabler', 'on_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'Cache_Enabler', 'on_uninstall' ) );

// register autoload
spl_autoload_register( 'cache_enabler_autoload' );

// load required classes
function cache_enabler_autoload( $class_name ) {
    // check if classes were loaded in advanced-cache.php
    if ( in_array( $class_name, array( 'Cache_Enabler', 'Cache_Enabler_Engine', 'Cache_Enabler_Disk' ), true ) && ! class_exists( $class_name ) ) {
        require_once sprintf(
            '%s/inc/%s.class.php',
            CACHE_ENABLER_DIR,
            strtolower( $class_name )
        );
    }
}

// load WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
    require_once CACHE_ENABLER_DIR . '/inc/cache_enabler_cli.class.php';
}
