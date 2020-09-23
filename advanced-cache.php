<?php
/**
 * Cache Enabler advanced cache
 *
 * @since   1.2.0
 * @change  1.5.0
 */

// check request method
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    return false;
}

// base path
$path = _file_path();

// scheme
$scheme = ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || $_SERVER['SERVER_PORT'] === '443' ) ? 'https' : 'http';

// path to cached variants
$path_html      = $path . $scheme . '-index.html';
$path_gzip      = $path . $scheme . '-index.html.gz';
$path_webp_html = $path . $scheme . '-index-webp.html';
$path_webp_gzip = $path . $scheme . '-index-webp.html.gz';

// check if cached file exists
if ( ! is_readable( $path_html ) ) {
    return false;
}

// check if there are settings
$settings_file = sprintf(
    '%s-%s%s.json',
    WP_CONTENT_DIR . '/plugins/cache-enabler/settings/cache-enabler-advcache',
    parse_url(
        'http://' . strtolower( $_SERVER['HTTP_HOST'] ),
        PHP_URL_HOST
    ),
    ( is_multisite() && ! SUBDOMAIN_INSTALL ) ? _get_blog_path() : ''
);
$settings = _read_settings( $settings_file );

// check trailing slash
if ( isset( $settings['permalink_structure_has_trailing_slash'] ) && $settings['permalink_structure_has_trailing_slash'] ) {
    // if trailing slash is set and missing (ignoring root index and file extensions)
    if ( preg_match( '/\/[^\.\/\?]+(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
        return false;
    }
} elseif ( isset( $settings['permalink_structure_has_trailing_slash'] ) && ! $settings['permalink_structure_has_trailing_slash'] ) {
    // if trailing slash is not set and appended (ignoring root index and file extensions)
    if ( preg_match( '/\/[^\.\/\?]+\/(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
        return false;
    }
}

// check cache expiry time
if ( isset( $settings['cache_expiry_time'] ) && $settings['cache_expiry_time'] > 0 ) {
    $now = time();
    $expires_seconds = 3600 * $settings['cache_expiry_time'];

    // check if cached file has expired
    if ( ( filemtime( $path_html ) + $expires_seconds ) <= $now ) {
        return false;
    }
}

// check query string
if ( ! empty( $settings['excluded_query_strings'] ) ) {
    $query_string = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );

    if ( preg_match( $settings['excluded_query_strings'], $query_string ) ) {
        return true;
    }
}

// check cookies
if ( ! empty( $_COOKIE ) ) {
    // set regex matching cookies that should bypass the cache
    if ( ! empty( $settings['excluded_cookies'] ) ) {
        $cookies_regex = $settings['excluded_cookies'];
    } else {
        $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
    }
    // bypass cache if an excluded cookie is found
    foreach ( $_COOKIE as $key => $value) {
        if ( preg_match( $cookies_regex, $key ) ) {
            return false;
        }
    }
}

// set X-Cache-Handler response header
header( 'X-Cache-Handler: WP' );

// get request headers
if ( function_exists( 'apache_request_headers' ) ) {
    $headers                = apache_request_headers();
    $http_if_modified_since = ( isset( $headers[ 'If-Modified-Since' ] ) ) ? $headers[ 'If-Modified-Since' ] : '';
    $http_accept            = ( isset( $headers[ 'Accept' ] ) ) ? $headers[ 'Accept' ] : '';
    $http_accept_encoding   = ( isset( $headers[ 'Accept-Encoding' ] ) ) ? $headers[ 'Accept-Encoding' ] : '';
} else {
    $http_if_modified_since = ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '';
    $http_accept            = ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT' ] : '';
    $http_accept_encoding   = ( isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '';
}

// check modified since with cached file and return 304 if no difference
if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( $path_html ) ) ) {
    header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
    exit;
}

// set Last-Modified response header
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $path_html ) ) . ' GMT' );

// check webp and deliver gzip webp file if supported
if ( $http_accept && ( strpos( $http_accept, 'webp' ) !== false ) ) {
    if ( is_readable( $path_webp_gzip ) ) {
        header( 'Content-Encoding: gzip' );
        readfile( $path_webp_gzip );
        exit;
    } elseif ( is_readable( $path_webp_html ) ) {
        readfile( $path_webp_html );
        exit;
    }
}

// check encoding and deliver gzip file if supported
if ( $http_accept_encoding && ( strpos($http_accept_encoding, 'gzip') !== false ) && is_readable( $path_gzip )  ) {
    header( 'Content-Encoding: gzip' );
    readfile( $path_gzip );
    exit;
}

// deliver cached file (default)
readfile( $path_html );
exit;


// get cached file path
function _file_path() {

    $path = sprintf(
        '%s%s%s%s',
        WP_CONTENT_DIR . '/cache/cache-enabler',
        DIRECTORY_SEPARATOR,
        parse_url(
            'http://' . strtolower( $_SERVER['HTTP_HOST'] ),
            PHP_URL_HOST
        ),
        parse_url(
            $_SERVER['REQUEST_URI'],
            PHP_URL_PATH
        )
    );

    if ( is_file( $path ) ) {
        header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404 );
        exit;
    }

    // add trailing slash
    $path = rtrim( $path, '/\\' ) . '/';

    return $path;
}

// get blog path
function _get_blog_path() {

    // get blog path
    $path = explode( '/', $_SERVER['REQUEST_URI'], 3 );
    $path = $path[1];

    // check if blog path is empty
    if ( ! empty( $path ) ) {
        $path = '-' . $path;
    }

    return $path;
}

// read settings file
function _read_settings( $settings_file ) {

    // check if settings file exists
    if ( ! file_exists( $settings_file ) ) {
        // check if settings file exists for main site in network with subdirectory configuration
        if ( is_multisite() && ! SUBDOMAIN_INSTALL && file_exists( str_replace( _get_blog_path(), '', $settings_file ) ) ) {
            $settings_file = str_replace( _get_blog_path(), '', $settings_file );
        } else {
            return array();
        }
    }

    // check if any errors occur when reading the settings file
    if ( ! $settings = json_decode( file_get_contents( $settings_file ), true ) ) {
        return array();
    }

    return $settings;
}
