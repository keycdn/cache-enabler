<?php
/**
 * Cache Enabler advanced cache
 *
 * @since   1.2.0
 * @change  1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Set the CACHE_ENABLER_DIR constant in your wp-config.php file if the plugin resides
 * somewhere other than wp-content/plugins/cache-enabler/.
 */
if ( defined( 'CACHE_ENABLER_DIR' ) ) {
    $ce_dir = CACHE_ENABLER_DIR;
} else {
    $ce_dir = ( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/cache-enabler';
}

$ce_engine_file = $ce_dir . '/inc/cache_enabler_engine.class.php';
$ce_disk_file   = $ce_dir . '/inc/cache_enabler_disk.class.php';

if ( file_exists( $ce_engine_file ) && file_exists( $ce_disk_file ) ) {
    require_once $ce_engine_file;
    require_once $ce_disk_file;
}

if ( class_exists( 'Cache_Enabler_Engine' ) ) {
    $ce_engine_started = Cache_Enabler_Engine::start();

    if ( $ce_engine_started ) {
        $ce_cache_delivered = Cache_Enabler_Engine::deliver_cache();

        if ( ! $ce_cache_delivered ) {
            Cache_Enabler_Engine::start_buffering();
        }
    }
}
