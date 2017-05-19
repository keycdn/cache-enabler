<?php

// check if request method is GET
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] != 'GET' ) {
    return false;
}

// check if request with query strings
if ( ! empty($_GET) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) ) {
    return false;
}

// check cookie values
if ( !empty($_COOKIE) ) {
    foreach ( $_COOKIE as $k => $v) {
        if ( preg_match('/^(wp-postpass|wordpress_logged_in|comment_author)_/', $k) ) {
            return false;
        }
    }
}

// base path
$path = sprintf(
    '%s%s%s%s',
    WP_CONTENT_DIR . '/cache/cache-enabler',
    DIRECTORY_SEPARATOR,
    parse_url(
        'http://' .strtolower($_SERVER['HTTP_HOST']),
        PHP_URL_HOST
    ),
    parse_url(
        $_SERVER['REQUEST_URI'],
        PHP_URL_PATH
    )
);

// add trailing slash
$path = rtrim( $path, '/\\' ) . '/';

// path to cached variants
$path_html = $path . 'index.html';
$path_gzip = $path . 'index.html.gz';
$path_webp_html = $path . 'index-webp.html';
$path_webp_gzip = $path . 'index-webp.html.gz';

if ( is_readable( $path_html ) ) {

    // set cache handler header
    header('x-cache-handler: wp');

    // get if-modified request headers
    if ( function_exists( 'apache_request_headers' ) ) {
        $headers = apache_request_headers();
        $http_if_modified_since = ( isset( $headers[ 'If-Modified-Since' ] ) ) ? $headers[ 'If-Modified-Since' ] : '';
        $http_accept = ( isset( $headers[ 'Accept' ] ) ) ? $headers[ 'Accept' ] : '';
        $http_accept_encoding = ( isset( $headers[ 'Accept-Encoding' ] ) ) ? $headers[ 'Accept-Encoding' ] : '';
    } else {
        $http_if_modified_since = ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '';
        $http_accept = ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT' ] : '';
        $http_accept_encoding = ( isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '';
    }

    // check modified since with cached file and return 304 if no difference
    if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) == filemtime( $path_html ) ) ) {
        header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
        exit;
    }

    header( 'Last-Modified: ' . gmdate("D, d M Y H:i:s",filemtime( $path_html )).' GMT' );

    // check webp and deliver gzip webp file if support
    if ( $http_accept && ( strpos($http_accept, 'webp') !== false ) ) {
        if ( is_readable( $path_webp_gzip ) ) {
            header('Content-Encoding: gzip');
            readfile( $path_webp_gzip );
            exit;
        } elseif ( is_readable( $path_webp_html ) ) {
            readfile( $path_webp_html );
            exit;
        }
    }

    // check encoding and deliver gzip file if support
    if ( $http_accept_encoding && ( strpos($http_accept_encoding, 'gzip') !== false ) && is_readable( $path_gzip )  ) {
        header('Content-Encoding: gzip');
        readfile( $path_gzip );
        exit;
    }

    // deliver cached file (default)
    readfile( $path_html );
    exit;

} else {
    return false;
}
