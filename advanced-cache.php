<?php
/**
 * Cache Enabler advanced cache
 *
 * @since   1.2.0
 * @change  1.5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ce_dir = ( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/cache-enabler';
$ce_engine_file = $ce_dir . '/inc/cache_enabler_engine.class.php';
$ce_disk_file   = $ce_dir . '/inc/cache_enabler_disk.class.php';

if ( file_exists( $ce_engine_file ) && file_exists( $ce_disk_file ) ) {
    require_once $ce_engine_file;
    require_once $ce_disk_file;
}

if ( class_exists( 'Cache_Enabler_Engine' ) ) {
    Cache_Enabler_Engine::start();
    Cache_Enabler_Engine::deliver_cache();
}
