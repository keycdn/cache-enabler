<?php
/**
 * The advanced-cache.php drop-in for Cache Enabler
 *
 * The advanced-cache.php creation method uses this during the disk setup and requirements
 * check. You can copy this file, edit the $cache_enabler_constants_file value, and save it
 * as "advanced-cache.php" in the wp-content directory.
 *
 * @since   1.2.0
 * @change  1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cache_enabler_constants_file = '/your/path/to/wp-content/plugins/cache-enabler/constants.php';

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
}
