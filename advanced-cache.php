<?php
/**
 * Cache Enabler advanced cache
 *
 * @since   1.2.0
 * @change  1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ce_dir = ( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/cache-enabler';

require_once $ce_dir . '/inc/cache_enabler_engine.class.php';
require_once $ce_dir . '/inc/cache_enabler_disk.class.php';

$cache_engine = new Cache_Enabler_Engine;
$cache_engine->deliver_cache();
