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
     * engine status
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var     boolean
     */

    public static $started = false;


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
     * @change  1.5.0
     */

    public function __construct() {

        // get settings from disk if cache exists
        if ( Cache_Enabler_Disk::cache_exists() ) {
            self::$settings = Cache_Enabler_Disk::get_settings();
        // get settings from database otherwise
        } elseif ( class_exists( 'Cache_Enabler' ) ) {
            self::$settings = Cache_Enabler::get_settings();
        }

        // check engine requirements
        if ( ! empty( self::$settings ) ) {
            self::$started = true;
        }
    }


    /**
     * check if output buffering should start
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @return  boolean  true if output buffering should start, false otherwise
     */

    public static function should_buffer() {

        if ( self::$started && self::is_index() ) {
            return true;
        }

        return false;
    }


    /**
     * start output buffering
     *
     * @since   1.5.0
     * @change  1.5.0
     */

    public static function start_buffering() {

        if ( self::should_buffer() ) {
            ob_start( 'self::end_buffering' );
        }
    }


    /**
     * end output buffering and cache page if applicable
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string   $page_contents  content of a page from the output buffer
     * @param   integer  $phase          bitmask of PHP_OUTPUT_HANDLER_* constants
     * @return  string   $page_contents  content of a page from the output buffer
     *
     * @hook    string   cache_enabler_before_store
     */

    private static function end_buffering( $page_contents, $phase ) {

        if ( $phase & PHP_OUTPUT_HANDLER_FINAL || $phase & PHP_OUTPUT_HANDLER_END ) {
            if ( ! self::is_cacheable( $page_contents ) || self::bypass_cache() ) {
                return $page_contents;
            }

            $page_contents = apply_filters( 'cache_enabler_before_store', $page_contents );

            Cache_Enabler_Disk::cache_page( $page_contents );

            return $page_contents;
        }
    }


    /**
     * check if installation directory index
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  boolean  true if installation directory index, false otherwise
     */

    private static function is_index() {

        if ( strtolower( basename( $_SERVER['SCRIPT_NAME'] ) ) === 'index.php' ) {
            return true;
        }

        return false;
    }


    /**
     * check if page can be cached
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   string   $page_contents  content of a page from the output buffer
     * @return  boolean                  true if page contents are cacheable, false otherwise
     */

    private static function is_cacheable( $page_contents ) {

        $has_html_tag       = ( stripos( $page_contents, '<html' ) !== false );
        $has_html5_doctype  = preg_match( '/^<!DOCTYPE.+html>/i', ltrim( $page_contents ) );
        $has_xsl_stylesheet = ( stripos( $page_contents, '<xsl:stylesheet' ) !== false || stripos( $page_contents, '<?xml-stylesheet' ) !== false );

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
     * @change  1.5.0
     *
     * @return  boolean  true if page is excluded from the cache, false otherwise
     */

    private static function is_excluded() {

        // if post ID excluded
        if ( ! empty( self::$settings['excluded_post_ids'] ) && function_exists( 'is_singular' ) && is_singular() ) {
            if ( in_array( get_queried_object_id(), (array) explode( ',', self::$settings['excluded_post_ids'] ) ) ) {
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
        if ( ! empty( self::$settings['excluded_query_strings'] ) ) {
            $query_string = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );

            if ( preg_match( self::$settings['excluded_query_strings'], $query_string ) ) {
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
            foreach ( $_COOKIE as $key => $value) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * check if mobile template
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if mobile template, false otherwise
     */

    private static function is_mobile() {

        return ( strpos( TEMPLATEPATH, 'wptouch' ) || strpos( TEMPLATEPATH, 'carrington' ) || strpos( TEMPLATEPATH, 'jetpack' ) || strpos( TEMPLATEPATH, 'handheld' ) );
    }


    /**
     * check if cache should be bypassed
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  boolean  true if cache should be bypassed, false otherwise
     *
     * @hook    boolean  bypass_cache
     */

    private static function bypass_cache() {

        // bypass cache hook
        if ( apply_filters( 'bypass_cache', false ) ) {
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

        // check WP_CACHE constant
        if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
            return true;
        }

        // check DONOTCACHEPAGE constant
        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // check if Ajax request
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return true;
        }

        // check if REST API request
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }

        // check if XMLRPC request
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return true;
        }

        // check conditional tags
        if ( self::is_wrong_permalink_structure() || self::is_excluded() ) {
            return true;
        }

        // check conditional tags when output buffer has ended
        if ( class_exists( 'WP' ) ) {
            if ( is_admin() || is_search() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() || self::is_mobile() ) {
                return true;
            }
        }

        return false;
    }


    /**
     * deliver cache
     *
     * @since   1.5.0
     * @change  1.5.0
     */

    public function deliver_cache() {

        if ( ! self::$started || Cache_Enabler_Disk::cache_expired() || self::bypass_cache() ) {
            return;
        }

        readfile( Cache_Enabler_Disk::get_cache() );
        exit;
    }
}
