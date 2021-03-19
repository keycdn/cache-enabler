<?php
/**
 * Cache Enabler advanced cache
 *
 * @since   1.2.0
 * @change  1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * set the CACHE_ENABLER_DIR constant without trailing slash in your wp-config.php file if the plugin resides
 * somewhere other than path/to/wp-content/plugins/cache-enabler
 */
if ( defined( 'CACHE_ENABLER_DIR' ) ) {
    $cache_enabler_dir = CACHE_ENABLER_DIR;
} else {
    $cache_enabler_dir = ( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/cache-enabler';
}

$cache_enabler_engine_file = $cache_enabler_dir . '/inc/cache_enabler_engine.class.php';
$cache_enabler_disk_file   = $cache_enabler_dir . '/inc/cache_enabler_disk.class.php';

if ( file_exists( $cache_enabler_engine_file ) && file_exists( $cache_enabler_disk_file ) ) {
    require_once $cache_enabler_engine_file;
    require_once $cache_enabler_disk_file;
}

if ( class_exists( 'Cache_Enabler_Engine' ) ) {
    $cache_enabler_engine_started = Cache_Enabler_Engine::start();

    if ( $cache_enabler_engine_started ) {
        $cache_enabler_cache_delivered = Cache_Enabler_Engine::deliver_cache();

        if ( ! $cache_enabler_cache_delivered ) {
            Cache_Enabler_Engine::start_buffering();
        }
    }
}
