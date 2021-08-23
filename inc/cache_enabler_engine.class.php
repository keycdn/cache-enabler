<?php
/**
 * Class used for handling engine-related operations.
 *
 * @since  1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler_Engine {
    /**
     * Start the cache engine.
     *
     * @since   1.5.2
     * @change  1.8.0
     *
     * @param   bool  Whether the cache engine should be force started.
     * @return  bool  True if the cache engine was started, false if not.
     */
    public static function start( $force = false ) {

        if ( $force || self::should_start() ) {
            new self();
        }

        return self::$started;
    }

    /**
     * Whether the cache engine is started.
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var  bool
     */
    public static $started = false;

    /**
     * Specific HTTP request headers from the current request.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @var  string[]
     */
    public static $request_headers;

    /**
     * Plugin settings from the disk or database.
     *
     * This will be from the disk when a frontend page is loaded and from the database
     * when an admin page is loaded.
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var  array
     */
    public static $settings;

    /**
     * Constructor.
     *
     * This is called by self::start() and starts up the cache engine.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @global  WP_Rewrite  $wp_rewrite  WordPress rewrite component.
     */
    public function __construct() {

        if ( self::$started ) {
            // Pick up the correct data in the cache engine restart. This is for
            // url_to_postid(), user_trailingslashit(), and the pagination bases.
            global $wp_rewrite;
            $wp_rewrite->init();
        }

        self::$request_headers = self::get_request_headers();

        if ( self::is_index() ) {
            self::$settings = Cache_Enabler_Disk::get_settings();
        } elseif ( class_exists( 'Cache_Enabler' ) ) {
            self::$settings = Cache_Enabler::get_settings();
            Cache_Enabler::$options = self::$settings; // Deprecated in 1.5.0.
            Cache_Enabler::$options['webp'] = self::$settings['convert_image_urls_to_webp']; // Deprecated in 1.5.0.
        }

        self::$started = ( ! empty( self::$settings ) ) ? true : false;
    }

    /**
     * Whether the cache engine should start.
     *
     * @since   1.5.2
     * @change  1.8.0
     *
     * @return  bool  True if the cache engine should start, false otherwise.
     */
    public static function should_start() {

        $valid_engine_running = ( self::$started && ( ! is_multisite() || ! ms_is_switched() ) );
        $early_ajax_request   = ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! class_exists( 'Cache_Enabler' ) );
        $rest_request         = ( defined( 'REST_REQUEST' ) && REST_REQUEST );
        $xmlrpc_request       = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );
        $bad_request_uri      = ( str_replace( array( '.ico', '.txt', '.xml', '.xsl' ), '', $_SERVER['REQUEST_URI'] ) !== $_SERVER['REQUEST_URI'] );

        if ( $valid_engine_running || $early_ajax_request || $rest_request || $xmlrpc_request || $bad_request_uri ) {
            return false;
        }

        return true;
    }

    /**
     * Start the output buffering.
     *
     * @since   1.5.0
     * @change  1.6.0
     */
    public static function start_buffering() {

        ob_start( 'self::end_buffering' );
    }

    /**
     * End the output buffering and maybe cache the page.
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @param   string  $contents  Contents from the output buffer.
     * @param   int     $phase     Bitmask of the PHP_OUTPUT_HANDLER_* constants.
     * @return  string             Unmodified contents from the output buffer.
     */
    private static function end_buffering( $contents, $phase ) {

        if ( $phase & PHP_OUTPUT_HANDLER_FINAL || $phase & PHP_OUTPUT_HANDLER_END ) {
            if ( self::is_cacheable( $contents ) && ! self::bypass_cache() ) {
                Cache_Enabler_Disk::cache_page( $contents );
            }
        }

        return $contents;
    }

    /**
     * Get the required HTTP request headers from the current request.
     *
     * @since   1.7.0
     * @change  1.8.0
     *
     * @return  string[]  An array of HTTP request headers with names as the keys.
     */
    private static function get_request_headers() {

        if ( ! empty( self::$request_headers ) ) {
            return self::$request_headers;
        }

        $request_headers = function_exists( 'apache_request_headers' ) ? apache_request_headers() : array();

        $request_headers = array(
            'Accept'             => isset( $request_headers['Accept'] ) ? $request_headers['Accept'] : ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) ? $_SERVER[ 'HTTP_ACCEPT' ] : '' ),
            'Accept-Encoding'    => isset( $request_headers['Accept-Encoding'] ) ? $request_headers['Accept-Encoding'] : ( isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '' ),
            'Host'               => isset( $request_headers['Host'] ) ? $request_headers['Host'] : ( isset( $_SERVER[ 'HTTP_HOST' ] ) ? $_SERVER[ 'HTTP_HOST' ] : '' ),
            'If-Modified-Since'  => isset( $request_headers['If-Modified-Since'] ) ? $request_headers['If-Modified-Since'] : ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '' ),
            'User-Agent'         => isset( $request_headers['User-Agent'] ) ? $request_headers['User-Agent'] : ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ? $_SERVER[ 'HTTP_USER_AGENT' ] : '' ),
            'X-Forwarded-Proto'  => isset( $request_headers['X-Forwarded-Proto'] ) ? $request_headers['X-Forwarded-Proto'] : ( isset( $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] ) ? $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] : '' ),
            'X-Forwarded-Scheme' => isset( $request_headers['X-Forwarded-Scheme'] ) ? $request_headers['X-Forwarded-Scheme'] : ( isset( $_SERVER[ 'HTTP_X_FORWARDED_SCHEME' ] ) ? $_SERVER[ 'HTTP_X_FORWARDED_SCHEME' ] : '' ),
        );

        return $request_headers;
    }

    /**
     * Whether the script being executed is the installation directory index file.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  bool  True if the script being executed is the index file, false if not.
     */
    private static function is_index() {

        if ( defined( 'CACHE_ENABLER_INDEX_FILE' ) && $_SERVER['SCRIPT_FILENAME'] === CACHE_ENABLER_INDEX_FILE ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the contents from the output buffer can be cached.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string  $contents  Contents from the output buffer.
     * @return  bool               True if contents from the output buffer are cacheable, false if not.
     */
    private static function is_cacheable( $contents ) {

        $has_html_tag       = ( stripos( $contents, '<html' ) !== false );
        $has_html5_doctype  = preg_match( '/^<!DOCTYPE.+html\s*>/i', ltrim( $contents ) );
        $has_xsl_stylesheet = ( stripos( $contents, '<xsl:stylesheet' ) !== false || stripos( $contents, '<?xml-stylesheet' ) !== false );

        if ( $has_html_tag && $has_html5_doctype && ! $has_xsl_stylesheet ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the permalink structure is wrong.
     *
     * This checks whether the current site uses trailing slashes and then whether the
     * request URI matches what is set. The root index and file extensions are ignored.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  bool  True if the request URI does not match the permalink structure, false otherwise.
     */
    private static function is_wrong_permalink_structure() {

        if ( self::$settings['use_trailing_slashes'] ) {
            if ( preg_match( '/\/[^\.\/\?]+(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
                return true;
            }
        } elseif ( preg_match( '/\/[^\.\/\?]+\/(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the request is excluded from the cache.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  bool  True if the request is excluded from the cache, false otherwise.
     */
    private static function is_excluded() {

        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        if ( http_response_code() !== 200 ) {
            return true;
        }

        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // Post ID exclusions.
        if ( ! empty( self::$settings['excluded_post_ids'] ) && function_exists( 'is_singular' ) && is_singular() ) {
            $post_id = get_queried_object_id();
            $excluded_post_ids = array_map( 'absint', (array) explode( ',', self::$settings['excluded_post_ids'] ) );

            if ( in_array( $post_id, $excluded_post_ids, true ) ) {
                return true;
            }
        }

        // Page path exclusions.
        if ( ! empty( self::$settings['excluded_page_paths'] ) ) {
            $page_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match( self::$settings['excluded_page_paths'], $page_path ) ) {
                return true;
            }
        }

        // Query string exclusions.
        if ( ! empty( $_GET ) ) {
            if ( ! empty( self::$settings['excluded_query_strings'] ) ) {
                $query_string_regex = self::$settings['excluded_query_strings'];
            } else {
                $query_string_regex = '/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/';
            }

            $query_string = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );

            if ( preg_match( $query_string_regex, $query_string ) ) {
                return true;
            }
        }

        // Cookie exclusions.
        if ( ! empty( $_COOKIE ) ) {
            if ( ! empty( self::$settings['excluded_cookies'] ) ) {
                $cookies_regex = self::$settings['excluded_cookies'];
            } else {
                $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
            }

            foreach ( $_COOKIE as $key => $value ) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        // When the output buffering is ending.
        if ( class_exists( 'WP' ) ) {
            if ( is_admin() || self::is_search() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the query is for a search.
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @return  bool  True if the query is for a search, false if not.
     */
    private static function is_search() {

        /**
         * Filters whether search queries should be excluded from the cache.
         *
         * @since   1.6.0
         * @change  1.6.0
         *
         * @param  bool  $exclude_search  True if search queries should be excluded from the cache, false if not. Default
         *                                is the value returned by is_search().
         */
        if ( apply_filters( 'cache_enabler_exclude_search', is_search() ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the cache should be bypassed.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  bool  True if the cache should be bypassed, false otherwise.
     */
    private static function bypass_cache() {

        /**
         * Filters whether the cache should be bypassed.
         *
         * @since   1.6.0
         * @change  1.6.0
         *
         * @param  bool  $bypass_cache  True if the cache should be bypassed, false if not. Default false.
         */
        if ( apply_filters( 'cache_enabler_bypass_cache', false ) || apply_filters_deprecated( 'bypass_cache', array( false ), '1.6.0', 'cache_enabler_bypass_cache' ) ) {
            return true;
        }

        if ( self::is_wrong_permalink_structure() || self::is_excluded() ) {
            return true;
        }

        return false;
    }

    /**
     * Deliver the cached page for the current URL.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  bool  False if the cached page was not delivered.
     */
    public static function deliver_cache() {

        $cache_file = Cache_Enabler_Disk::get_cache_file();

        if ( Cache_Enabler_Disk::cache_exists( $cache_file ) && ! Cache_Enabler_Disk::cache_expired( $cache_file ) && ! self::bypass_cache() ) {
            header( 'X-Cache-Handler: cache-enabler-engine' );

            if ( strtotime( self::$request_headers['If-Modified-Since'] >= filemtime( $cache_file ) ) ) {
                header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
                exit; // Deliver empty body.
            }

            switch ( substr( $cache_file, -2, 2 ) ) {
                case 'br':
                    header( 'Content-Encoding: br' );
                    break;
                case 'gz':
                    header( 'Content-Encoding: gzip' );
                    break;
            }

            readfile( $cache_file );
            exit;
        }

        return false;
    }
}
