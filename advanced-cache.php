<?php

// check if request method is GET
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] != 'GET' ) {
    return false;
}

// base path
$path = _ce_file_path();

// path to cached variants
$path_html = $path . 'index.html';
$path_gzip = $path . 'index.html.gz';
$path_webp_html = $path . 'index-webp.html';
$path_webp_gzip = $path . 'index-webp.html.gz';


// if we don't have a cache copy, we do not need to proceed
if ( ! is_readable( $path_html ) ) {
    return false;
}

// check if there are settings passed out to us
$settings_file = sprintf('%s-%s%s.json',
    WP_CONTENT_DIR. "/cache/cache-enabler-advcache",
    parse_url(
        'http://' .strtolower($_SERVER['HTTP_HOST']),
        PHP_URL_HOST
    ),
    is_multisite() ? '-'. abs(intval($blog_id)) : ''
);
$settings = _read_settings($settings_file);

// if post path excluded
if ( !empty($settings['excl_regexp']) ) {
    $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ( preg_match($settings['excl_regexp'], $url_path) ) {
        return false;
    }
}

// check GET variables
if ( !empty($_GET) ) {
	// set regex of analytics campaign tags that shall not prevent caching
	if ( !empty($settings['incl_attributes']) ) {
		$attributes_regex = $settings['incl_attributes'];
	} else {
		$attributes_regex = '/^utm_(source|medium|campaign|term|content)$/';
	}
	// prevent cache use if there is any GET variable not covered by the campaign tag regex
	if ( sizeof( preg_grep( $attributes_regex, array_keys( $_GET ), PREG_GREP_INVERT ) ) > 0 ) {
		return false;
	}
}

// check cookie values
if ( !empty($_COOKIE) ) {
    // check cookie values
    if ( !empty($settings['excl_cookies']) ) {
        // if custom cookie regexps exist, we merge them
        $cookies_regex = $settings['excl_cookies'];
    } else {
        $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
    }

    foreach ( $_COOKIE as $k => $v) {
        if ( preg_match($cookies_regex, $k) ) {
            return false;
        }
    }
}

// if an expiry time is set, check the file against it
if ( isset($settings["expires"]) and $settings["expires"] > 0 ) {
    $now = time();
    $expires_seconds = 3600*$settings["expires"];

    // check if asset has expired
    if ( ( filemtime($path_html) + $expires_seconds ) <= $now ) {
        return false;
    }
}

// if a cache timeout is set, check if we have to bypass the cache
if ( !empty($settings["cache_timeout"]) ) {
    $now = time();

    // check if timeout has been reached
    if ( $settings["cache_timeout"] <= $now ) {
        unlink($timeout_file);
        return false;
    }
}

// check if we need drop the ball to cause a redirect
if ( isset($settings["permalink_trailing_slash"]) ) {
    if ( ! preg_match("/\/(|\?.*)$/", $_SERVER["REQUEST_URI"]) ) {
        return false;
    }
}

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
if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( $path_html ) ) ) {
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


// generate cache path
function _ce_file_path($path = NULL) {
    $path = sprintf(
        '%s%s%s%s',
        WP_CONTENT_DIR . '/cache/cache-enabler',
        DIRECTORY_SEPARATOR,
        parse_url(
            'http://' .strtolower($_SERVER['HTTP_HOST']),
            PHP_URL_HOST
        ),
        parse_url(
            ( $path ? $path : $_SERVER['REQUEST_URI'] ),
            PHP_URL_PATH
        )
    );

    if ( is_file($path) > 0 ) {
        header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404 );
        exit;
    }

    // add trailing slash
    $path = rtrim( $path, '/\\' ) . '/';

    return $path;
}

// read settings file
function _read_settings($settings_file) {
    if (! file_exists($settings_file) ) {
        return [];
    }

    if ( ! $settings = json_decode(file_get_contents($settings_file), true) ) {
        // if there is an error reading our settings
        return [];
    }

    return $settings;
}
