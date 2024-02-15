<?php
/**
 * The advanced-cache.php drop-in file for Cache Enabler.
 *
 * The advanced-cache.php creation method uses this during the disk setup and
 * requirements check. You can copy this file to the wp-content directory and edit
 * the $cache_enabler_constants_file value as needed. If your web server supports it,
 * you may also symlink this file into wp-content; no editing is needed in that case.
 * The copy/symlink will automatically delete itself if stale or abandoned.
 *
 * @since   1.2.0
 * @change  1.8.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cache_enabler_constants_file = realpath(__DIR__) . '/constants.php';

if ( file_exists( $cache_enabler_constants_file ) ) {
    require $cache_enabler_constants_file;

    $cache_enabler_engine_file = CACHE_ENABLER_DIR . '/inc/cache_enabler_engine.class.php';
    $cache_enabler_disk_file   = CACHE_ENABLER_DIR . '/inc/cache_enabler_disk.class.php';

    if ( file_exists( $cache_enabler_engine_file ) && file_exists( $cache_enabler_disk_file ) ) {
        require_once $cache_enabler_engine_file;
        require_once $cache_enabler_disk_file;

        if ( Cache_Enabler_Engine::start() && ! Cache_Enabler_Engine::deliver_cache() ) {
            Cache_Enabler_Engine::start_buffering();
        }
    }
} elseif ( __DIR__ === WP_CONTENT_DIR ) {
    @unlink( __FILE__ );
}
