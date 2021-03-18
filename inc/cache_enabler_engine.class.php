<?php
/**
 * Cache Enabler engine
 *
 * @since  1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler_Engine {

    /**
     * start engine
     *
     * @since   1.5.2
     * @change  1.6.0
     *
     * @return  boolean  true if engine started, false otherwise
     */

    public static function start() {

        if ( self::should_start() ) {
            new self();
        }

        return self::$started;
    }


    /**
     * engine status
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var     boolean
     */

    public static $started = false;


    /**
     * specific HTTP request headers from current request
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @var     array
     */

    public static $request_headers;


    /**
     * engine settings from disk or database
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var     array
     */

    public static $settings;


    /**
     * constructor
     *
     * @since   1.5.0
     * @change  1.7.0
     */

    public function __construct() {

        // get request headers
        self::$request_headers = self::get_request_headers();

        // get settings from disk if directory index file
        if ( self::is_index() ) {
            self::$settings = Cache_Enabler_Disk::get_settings();
        // get settings from database in late engine start otherwise
        } elseif ( class_exists( 'Cache_Enabler' ) ) {
            self::$settings = Cache_Enabler::get_settings();
            // set deprecated settings
            Cache_Enabler::$options = self::$settings;
            Cache_Enabler::$options['webp'] = self::$settings['convert_image_urls_to_webp'];
        }

        // check engine status
        if ( ! empty( self::$settings ) ) {
            self::$started = true;
        }
    }


    /**
     * check if engine should start
     *
     * @since   1.5.2
     * @change  1.7.0
     *
     * @return  boolean  true if engine should start, false otherwise
     */

    public static function should_start() {

        // check if engine is running already
        if ( self::$started ) {
            return false;
        }

        // check if Ajax request in early engine start
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! class_exists( 'Cache_Enabler' ) ) {
            return false;
        }

        // check if REST API request
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }

        // check if XMLRPC request
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return false;
        }

        // check request URI
        if ( str_replace( array( '.ico', '.txt', '.xml', '.xsl' ), '', $_SERVER['REQUEST_URI'] ) !== $_SERVER['REQUEST_URI'] ) {
            return false;
        }

        return true;
    }


    /**
     * start output buffering
     *
     * @since   1.5.0
     * @change  1.6.0
     */

    public static function start_buffering() {

        ob_start( 'self::end_buffering' );
    }


    /**
     * end output buffering and cache page if applicable
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @param   string   $contents  contents from the output buffer
     * @param   integer  $phase     bitmask of PHP_OUTPUT_HANDLER_* constants
     * @return  string   $contents  unchanged contents from the output buffer
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
     * get specific HTTP request headers from current request
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  array  $request_headers  specific HTTP request headers from current request
     */

    private static function get_request_headers() {

        $request_headers = ( function_exists( 'apache_request_headers' ) ) ? apache_request_headers() : array();

        $request_headers = array(
            'Accept'             => ( isset( $request_headers['Accept'] ) ) ? $request_headers['Accept'] : ( ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT' ] : '' ),
            'Accept-Encoding'    => ( isset( $request_headers['Accept-Encoding'] ) ) ? $request_headers['Accept-Encoding'] : ( ( isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '' ),
            'Host'               => ( isset( $request_headers['Host'] ) ) ? $request_headers['Host'] : ( ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) ? $_SERVER[ 'HTTP_HOST' ] : '' ),
            'If-Modified-Since'  => ( isset( $request_headers['If-Modified-Since'] ) ) ? $request_headers['If-Modified-Since'] : ( ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '' ),
            'User-Agent'         => ( isset( $request_headers['User-Agent'] ) ) ? $request_headers['User-Agent'] : ( ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) ? $_SERVER[ 'HTTP_USER_AGENT' ] : '' ),
            'X-Forwarded-Proto'  => ( isset( $request_headers['X-Forwarded-Proto'] ) ) ? $request_headers['X-Forwarded-Proto'] : ( ( isset( $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] ) ) ? $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] : '' ),
            'X-Forwarded-Scheme' => ( isset( $request_headers['X-Forwarded-Scheme'] ) ) ? $request_headers['X-Forwarded-Scheme'] : ( ( isset( $_SERVER[ 'HTTP_X_FORWARDED_SCHEME' ] ) ) ? $_SERVER[ 'HTTP_X_FORWARDED_SCHEME' ] : '' ),
        );

        return $request_headers;
    }


    /**
     * check if directory index file
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  boolean  true if directory index file, false otherwise
     */

    private static function is_index() {

        if ( strtolower( basename( $_SERVER['SCRIPT_NAME'] ) ) === 'index.php' ) {
            return true;
        }

        return false;
    }


    /**
     * check if contents from the output buffer can be cached
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string   $contents  contents from the output buffer
     * @return  boolean             true if contents from the output buffer are cacheable, false otherwise
     */

    private static function is_cacheable( $contents ) {

        $has_html_tag       = ( stripos( $contents, '<html' ) !== false );
        $has_html5_doctype  = preg_match( '/^<!DOCTYPE.+html>/i', ltrim( $contents ) );
        $has_xsl_stylesheet = ( stripos( $contents, '<xsl:stylesheet' ) !== false || stripos( $contents, '<?xml-stylesheet' ) !== false );

        if ( $has_html_tag && $has_html5_doctype && ! $has_xsl_stylesheet ) {
            return true;
        }

        return false;
    }


    /**
     * check permalink structure
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @return  boolean  true if request URI does not match permalink structure or if plain, false otherwise
     */

    private static function is_wrong_permalink_structure() {

        // check if trailing slash is set and missing (ignoring root index and file extensions)
        if ( self::$settings['permalink_structure'] === 'has_trailing_slash' ) {
            if ( preg_match( '/\/[^\.\/\?]+(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
                return true;
            }
        }

        // check if trailing slash is not set and appended (ignoring root index and file extensions)
        if ( self::$settings['permalink_structure'] === 'no_trailing_slash' ) {
            if ( preg_match( '/\/[^\.\/\?]+\/(\?.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
                return true;
            }
        }

        // check if custom permalink structure is not set
        if ( self::$settings['permalink_structure'] === 'plain' ) {
            return true;
        }

        return false;
    }


    /**
     * check if page is excluded from cache
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @return  boolean  true if page is excluded from the cache, false otherwise
     */

    private static function is_excluded() {

        // if post ID excluded
        if ( ! empty( self::$settings['excluded_post_ids'] ) && function_exists( 'is_singular' ) && is_singular() ) {
            $post_id = get_queried_object_id();
            $excluded_post_ids = array_map( 'absint', (array) explode( ',', self::$settings['excluded_post_ids'] ) );

            if ( in_array( $post_id, $excluded_post_ids, true ) ) {
                return true;
            }
        }

        // if page path excluded
        if ( ! empty( self::$settings['excluded_page_paths'] ) ) {
            $page_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match( self::$settings['excluded_page_paths'], $page_path ) ) {
                return true;
            }
        }

        // if query string excluded
        if ( ! empty( $_GET ) ) {
            // set regex matching query strings that should bypass the cache
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

        // if cookie excluded
        if ( ! empty( $_COOKIE ) ) {
            // set regex matching cookies that should bypass the cache
            if ( ! empty( self::$settings['excluded_cookies'] ) ) {
                $cookies_regex = self::$settings['excluded_cookies'];
            } else {
                $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
            }
            // bypass cache if an excluded cookie is found
            foreach ( $_COOKIE as $key => $value ) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * check if search page
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @return  boolean  true if search page, false otherwise
     */

    private static function is_search() {

        if ( apply_filters( 'cache_enabler_exclude_search', is_search() ) ) {
            return true;
        }

        return false;
    }


    /**
     * check if cache should be bypassed
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @return  boolean  true if cache should be bypassed, false otherwise
     */

    private static function bypass_cache() {

        // bypass cache hook
        if ( apply_filters( 'cache_enabler_bypass_cache', false ) ) {
            return true;
        }

        // deprecated bypass cache hook
        if ( apply_filters_deprecated( 'bypass_cache', array( false ), '1.6.0', 'cache_enabler_bypass_cache' ) ) {
            return true;
        }

        // check request method
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        // check HTTP status code
        if ( http_response_code() !== 200 ) {
            return true;
        }

        // check DONOTCACHEPAGE constant
        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // check conditional tags
        if ( self::is_wrong_permalink_structure() || self::is_excluded() ) {
            return true;
        }

        // check conditional tags when output buffering has ended
        if ( class_exists( 'WP' ) ) {
            if ( is_admin() || self::is_search() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
                return true;
            }
        }

        return false;
    }


    /**
     * deliver cache
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @return  boolean  false if cached page was not delivered
     */

    public static function deliver_cache() {

        $cache_file = Cache_Enabler_Disk::get_cache_file();

        if ( Cache_Enabler_Disk::cache_exists( $cache_file ) && ! Cache_Enabler_Disk::cache_expired( $cache_file ) && ! self::bypass_cache()  ) {
            // set X-Cache-Handler response header
            header( 'X-Cache-Handler: cache-enabler-engine' );

            // return 304 Not Modified with empty body if applicable
            if ( strtotime( self::$request_headers['If-Modified-Since'] >= filemtime( $cache_file ) ) ) {
                header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
                exit;
            }

            // set Content-Encoding response header if applicable
            if ( strpos( basename( $cache_file ), 'gz' ) !== false ) {
                header( 'Content-Encoding: gzip' );
            }

            // deliver cache
            readfile( $cache_file );
            exit;
        }

        return false;
    }
}
