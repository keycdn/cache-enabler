<?php
/**
 * Plugin constants.
 *
 * @since  1.8.0
 */

$cache_enabler_constants = array(
    'CACHE_ENABLER_VERSION'        => '1.8.15',
    'CACHE_ENABLER_MIN_PHP'        => '5.6',
    'CACHE_ENABLER_MIN_WP'         => '5.1',
    'CACHE_ENABLER_DIR'            => __DIR__,
    'CACHE_ENABLER_FILE'           => __DIR__ . '/cache-enabler.php',
    'CACHE_ENABLER_BASE'           => ( function_exists( 'wp_normalize_path' ) ) ? plugin_basename( __DIR__ . '/cache-enabler.php' ) : null,
    'CACHE_ENABLER_CACHE_DIR'      => WP_CONTENT_DIR . '/cache/cache-enabler', // Without a trailing slash.
    'CACHE_ENABLER_SETTINGS_DIR'   => WP_CONTENT_DIR . '/settings/cache-enabler', // Without a trailing slash.
    'CACHE_ENABLER_CONSTANTS_FILE' => __FILE__,
    'CACHE_ENABLER_INDEX_FILE'     => ABSPATH . 'index.php',
);

foreach ( $cache_enabler_constants as $cache_enabler_constant_name => $cache_enabler_constant_value ) {
    if ( ! defined( $cache_enabler_constant_name ) && $cache_enabler_constant_value !== null ) {
        define( $cache_enabler_constant_name, $cache_enabler_constant_value );
    }
}

// Deprecated in 1.7.0.
if ( defined( 'CACHE_ENABLER_BASE' ) ) {
    define( 'CE_VERSION', CACHE_ENABLER_VERSION );
    define( 'CE_MIN_PHP', CACHE_ENABLER_MIN_PHP );
    define( 'CE_MIN_WP', CACHE_ENABLER_MIN_WP );
    define( 'CE_DIR', CACHE_ENABLER_DIR );
    define( 'CE_FILE', CACHE_ENABLER_FILE );
    define( 'CE_BASE', CACHE_ENABLER_BASE );
}
