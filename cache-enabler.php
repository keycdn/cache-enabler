<?php
/*
Plugin Name: Cache Enabler
Text Domain: cache-enabler
Description: Simple and fast WordPress caching plugin.
Author: KeyCDN
Author URI: https://www.keycdn.com
License: GPLv2 or later
Version: 1.8.7
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

require __DIR__ . '/constants.php';

add_action( 'plugins_loaded', array( 'Cache_Enabler', 'init' ) );
register_activation_hook( CACHE_ENABLER_FILE, array( 'Cache_Enabler', 'on_activation' ) );
register_deactivation_hook( CACHE_ENABLER_FILE, array( 'Cache_Enabler', 'on_deactivation' ) );
register_uninstall_hook( CACHE_ENABLER_FILE, array( 'Cache_Enabler', 'on_uninstall' ) );

spl_autoload_register( 'cache_enabler_autoload' );

function cache_enabler_autoload( $class_name ) {
    if ( in_array( $class_name, array( 'Cache_Enabler', 'Cache_Enabler_Engine', 'Cache_Enabler_Disk' ), true ) ) {
        require_once sprintf(
            '%s/inc/%s.class.php',
            CACHE_ENABLER_DIR,
            strtolower( $class_name )
        );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
    require_once CACHE_ENABLER_DIR . '/inc/cache_enabler_cli.class.php';
    WP_CLI::add_command( 'cache-enabler', 'Cache_Enabler_CLI' );
}
