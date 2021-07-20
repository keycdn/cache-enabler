<?php
/**
 * Cache Enabler disk handling
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler_Disk {

    /**
     * cache directory
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var     string
     */

    public static $cache_dir = WP_CONTENT_DIR . '/cache/cache-enabler';


    /**
     * settings directory
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @var     string
     */

    private static $settings_dir = WP_CONTENT_DIR . '/settings/cache-enabler';


    /**
     * configure system files
     *
     * @since   1.5.0
     * @change  1.7.0
     */

    public static function setup() {

        // add advanced-cache.php drop-in
        copy( CACHE_ENABLER_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );

        // set WP_CACHE constant in config file if not already set
        self::set_wp_cache_constant();
    }


    /**
     * clean system files
     *
     * @since   1.5.0
     * @change  1.5.0
     */

    public static function clean() {

        self::delete_settings_file();

        if ( ! is_dir( self::$settings_dir ) ) {
            // delete old advanced cache settings file(s) (1.4.0)
            array_map( 'unlink', glob( WP_CONTENT_DIR . '/cache/cache-enabler-advcache-*.json' ) );
            // delete incorrect advanced cache settings file(s) that may have been created in 1.4.0 (1.4.5)
            array_map( 'unlink', glob( ABSPATH . 'CE_SETTINGS_PATH-*.json' ) );
            // delete advanced-cache.php drop-in
            @unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
            // unset WP_CACHE constant in config file if set by Cache Enabler
            self::set_wp_cache_constant( false );
        }
    }


    /**
     * store cached page
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string  $page_contents  contents of a page from the output buffer
     */

    public static function cache_page( $page_contents ) {

        // page contents before store hook
        $page_contents = apply_filters( 'cache_enabler_page_contents_before_store', $page_contents );

        // deprecated page contents before store hook
        $page_contents = apply_filters_deprecated( 'cache_enabler_before_store', array( $page_contents ), '1.6.0', 'cache_enabler_page_contents_before_store' );

        // create cached page to be stored
        self::create_cache_file( $page_contents );
    }


    /**
     * check if cached page exists
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @param   string   $cache_file  file path to potentially cached page
     * @return  boolean               true if cached page exists and is readable, false otherwise
     */

    public static function cache_exists( $cache_file ) {

        return is_readable( $cache_file );
    }


    /**
     * check if cached page expired
     *
     * @since   1.0.1
     * @change  1.7.0
     *
     * @param   string   $cache_file  file path to existing cached page
     * @return  boolean               true if cached page expired, false otherwise
     */

    public static function cache_expired( $cache_file ) {

        // check if cached pages are set to expire
        if ( ! Cache_Enabler_Engine::$settings['cache_expires'] || Cache_Enabler_Engine::$settings['cache_expiry_time'] === 0 ) {
            return false;
        }

        $now = time();
        $expires_seconds = 60 * 60 * Cache_Enabler_Engine::$settings['cache_expiry_time'];

        // check if cached page has expired
        if ( ( filemtime( $cache_file ) + $expires_seconds ) <= $now ) {
            return true;
        }

        return false;
    }


    /**
     * iterate over cache objects to perform actions and/or gather data
     *
     * @since   1.8.0
     * @change  1.8.0
     * @access  private
     *
     * @param   string  $url    URL to potentially cached page (with or without scheme)
     * @param   array   $args   TODO
     * @return  array   $cache  cache data
     */

    public static function cache_iterator( $url, $args = array() ) {

        $cache = array(
            'index' => array(),
            'size'  => 0,
        );

        $url       = esc_url_raw( $url, array( 'http', 'https' ) );
        $cache_dir = self::get_cache_dir( $url );

        if ( ! is_dir( $cache_dir ) ) {
            return $cache;
        }

        $switch = false;
        if ( is_multisite() ) {
            $blog_domain = (string) parse_url( $url, PHP_URL_HOST );
            $blog_path   = ( is_subdomain_install() ) ? '/' : Cache_Enabler::get_blog_path_from_url( $url );
            $blog_id     = get_blog_id_from_url( $blog_domain, $blog_path );

            if ( $blog_id !== 0 && $blog_id !== get_current_blog_id() ) {
                $switch = true;
                switch_to_blog( $blog_id );
            }
        }

        $args             = self::get_cache_iterator_args( $url, $args );
        $recursive        = ( $args['subpages'] === 1 || ! empty( $args['subpages']['include'] ) || isset( $args['subpages']['exclude'] ) );
        $filter           = ( $recursive && $args['subpages'] !== 1 ) ? $args['subpages'] : null;
        $cache_objects    = self::get_dir_objects( $cache_dir, $recursive, $filter );
        $cache_keys_regex = self::get_cache_keys_regex( $args['keys'] );

        foreach ( $cache_objects as $cache_object ) {
            if ( is_file( $cache_object ) ) {
                if ( $args['root'] && strpos( $cache_object, $args['root'] ) !== 0 ) {
                    continue; // skip to next object because file does not start with provided root path
                }

                $cache_object_name = basename( $cache_object );

                if ( $cache_keys_regex && ! preg_match( $cache_keys_regex, $cache_object_name ) ) {
                    continue; // skip to next object because file name does not match provided cache keys
                }

                if ( $args['expired'] && ! self::cache_expired( $cache_object ) ) {
                    continue; // skip to next object because file is not expired
                }

                $cache_object_dir  = dirname( $cache_object );
                $cache_object_size = (int) @filesize( $cache_object );

                if ( $args['clear'] ) {
                    if ( ! @unlink( $cache_object ) ) {
                        continue; // skip to next object because file deletion failed
                    }

                    $cache_object_size = -$cache_object_size; // cache size is negative when cleared

                    self::rmdir( $cache_object_dir, true ); // remove containing directory if empty along with any of its empty parents
                }

                if ( strpos( $cache_object_name, 'index' ) === false ) {
                    continue; // skip to next object because file is not a cache version and no longer needs to be handled (e.g. hidden file)
                }

                if ( ! isset( $cache['index'][ $cache_object_dir ]['url'] ) ) {
                    $cache['index'][ $cache_object_dir ]['url'] = self::get_cache_url( $cache_object_dir );
                    $cache['index'][ $cache_object_dir ]['id']  = url_to_postid( $cache['index'][ $cache_object_dir ]['url'] );
                }

                $cache['index'][ $cache_object_dir ]['versions'][ $cache_object_name ] = $cache_object_size;
                $cache['size'] += $cache_object_size;
            }
        }

        uksort( $cache['index'], 'self::sort_dir_objects' ); // sort cache index so path with least amount of slashes is first

        if ( $args['clear'] ) {
            self::fire_cache_cleared_hooks( $cache['index'], $args['hooks'] );
        }

        if ( $switch ) {
            restore_current_blog();
        }

        return $cache;
    }


    /**
     * get cache size (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.7.0
     */

    public static function cache_size( $dir = null ) {

        return self::get_cache_size( $dir );
    }


    /**
     * clear cached page(s) (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.8.0
     */

    public static function clear_cache( $clear_url = null, $clear_type = 'page' ) {

        // check if URL has been provided for legacy reasons
        if ( empty( $clear_url ) ) {
            return;
        }

        Cache_Enabler::clear_page_cache_by_url( $clear_url, $clear_type );
    }


    /**
     * create file for cache
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string  $page_contents  contents of a page from the output buffer
     */

    private static function create_cache_file( $page_contents ) {

        // check cache file requirements
        if ( ! is_string( $page_contents ) || strlen( $page_contents ) === 0 ) {
            return;
        }

        // get new cache file
        $new_cache_file      = self::get_cache_file();
        $new_cache_file_dir  = dirname( $new_cache_file );
        $new_cache_file_name = basename( $new_cache_file );

        // if setting enabled minify HTML
        if ( Cache_Enabler_Engine::$settings['minify_html'] ) {
            $page_contents = self::minify_html( $page_contents );
        }

        // append cache signature
        $page_contents = $page_contents . self::get_cache_signature( $new_cache_file_name );

        // convert image URLs to WebP if applicable
        if ( strpos( $new_cache_file_name, 'webp' ) !== false ) {
            $page_contents = self::converter( $page_contents );
        }

        // compress page contents with Brotli or Gzip if applicable
        if ( strpos( $new_cache_file_name, 'br' ) !== false ) {
            $page_contents = brotli_compress( $page_contents );

            // check if Brotli compression failed
            if ( $page_contents === false ) {
                return;
            }
        } elseif ( strpos( $new_cache_file_name, 'gz' ) !== false ) {
            $page_contents = gzencode( $page_contents, 9 );

            // check if Gzip compression failed
            if ( $page_contents === false ) {
                return;
            }
        }

        // create directory if necessary
        if ( ! self::mkdir_p( $new_cache_file_dir ) ) {
            return;
        }

        // try to create new cache file
        $new_cache_file_created = file_put_contents( $new_cache_file, $page_contents, LOCK_EX );

        // set file permissions if successfully created
        if ( $new_cache_file_created !== false ) {
            clearstatcache();
            $new_cache_file_stats = @stat( $new_cache_file_dir );
            $new_cache_file_perms = $new_cache_file_stats['mode'] & 0007777;
            $new_cache_file_perms = $new_cache_file_perms & 0000666;
            @chmod( $new_cache_file, $new_cache_file_perms );
            clearstatcache();

            $page_created_url = self::get_cache_url( $new_cache_file_dir );
            $page_created_id  = url_to_postid( $page_created_url );
            $cache_created_index[ $new_cache_file_dir ]['url'] = $page_created_url;
            $cache_created_index[ $new_cache_file_dir ]['id']  = $page_created_id;
            $cache_created_index[ $new_cache_file_dir ]['versions'][ $new_cache_file_name ] = $new_cache_file_created;

            do_action( 'cache_enabler_page_cache_created', $page_created_url, $page_created_id, $cache_created_index );
        }
    }


    /**
     * create settings file
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   array   $settings           settings from database
     * @return  string  $new_settings_file  file path to new settings file
     */

    public static function create_settings_file( $settings ) {

        // check settings file requirements
        if ( ! is_array( $settings ) || ! function_exists( 'home_url' ) ) {
            return;
        }

        // get new settings file
        $new_settings_file = self::get_settings_file();

        // add new settings file contents
        $new_settings_file_contents  = '<?php' . PHP_EOL;
        $new_settings_file_contents .= '/**' . PHP_EOL;
        $new_settings_file_contents .= ' * Cache Enabler settings for ' . home_url() . PHP_EOL;
        $new_settings_file_contents .= ' *' . PHP_EOL;
        $new_settings_file_contents .= ' * @since      1.5.0' . PHP_EOL;
        $new_settings_file_contents .= ' * @change     1.5.0' . PHP_EOL;
        $new_settings_file_contents .= ' *' . PHP_EOL;
        $new_settings_file_contents .= ' * @generated  ' . self::get_current_time() . PHP_EOL;
        $new_settings_file_contents .= ' */' . PHP_EOL;
        $new_settings_file_contents .= PHP_EOL;
        $new_settings_file_contents .= 'return ' . var_export( $settings, true ) . ';';

        // create directory if necessary
        if ( ! self::mkdir_p( dirname( $new_settings_file ) ) ) {
            return;
        }

        // create new settings file
        file_put_contents( $new_settings_file, $new_settings_file_contents, LOCK_EX );

        return $new_settings_file;
    }


    /**
     * fire cache cleared hooks
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   array  $cache_cleared_index  index of cache that was cleared
     * @param   array  $hooks                cache cleared hooks to 'include' and/or 'exclude' from being fired
     */

    private static function fire_cache_cleared_hooks( $cache_cleared_index, $hooks ) {

        if ( empty( $cache_cleared_index ) || empty( $hooks ) ) {
            return;
        }

        if ( isset( $hooks['include'] ) ) {
            $hooks_to_fire = $hooks['include'];
        } else {
            $hooks_to_fire = array( 'cache_enabler_complete_cache_cleared', 'cache_enabler_site_cache_cleared', 'cache_enabler_page_cache_cleared' );
        }

        if ( ! empty( $hooks['exclude'] ) ) {
            $hooks_to_fire = array_diff( $hooks_to_fire, $hooks['exclude'] );
        }

        if ( empty( $hooks_to_fire ) ) {
            return;
        }

        if ( in_array( 'cache_enabler_page_cache_cleared', $hooks_to_fire, true ) ) {
            foreach ( $cache_cleared_index as $cache_cleared_dir => $cache_cleared_data ) {
                $page_cleared_url = $cache_cleared_data['url'];
                $page_cleared_id  = $cache_cleared_data['id'];

                do_action( 'cache_enabler_page_cache_cleared', $page_cleared_url, $page_cleared_id, $cache_cleared_index );
                do_action( 'ce_action_cache_by_url_cleared', $page_cleared_url ); // deprecated in 1.6.0
            }
        }

        if ( in_array( 'cache_enabler_site_cache_cleared', $hooks_to_fire, true ) && empty( Cache_Enabler::get_cache_index() ) ) {
            $site_cleared_url = self::get_cache_url(); // not using home_url() directly in case trailing slash needs to be appended
            $site_cleared_id  = get_current_blog_id();

            do_action( 'cache_enabler_site_cache_cleared', $site_cleared_url, $site_cleared_id, $cache_cleared_index );
        }

        if ( in_array( 'cache_enabler_complete_cache_cleared', $hooks_to_fire, true ) && ! is_dir( self::$cache_dir ) ) {
            do_action( 'cache_enabler_complete_cache_cleared' );
            do_action( 'ce_action_cache_cleared' ); // deprecated in 1.6.0
        }
    }


    /**
     * filter directory object
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string   $dir_object  file or directory path to filter (without trailing slash)
     * @param   array[]  $filter      file or directory path(s) to 'include' and/or 'exclude' (without trailing slash)
     * @return  boolean               true if directory object should be included, false if excluded
     */

    private static function filter_dir_object( $dir_object, $filter ) {

        if ( isset( $filter['exclude'] ) ) {
            $match = in_array( $dir_object, $filter['exclude'], true );

            if ( $match ) {
                return false;
            }
        }

        if ( isset( $filter['include'] ) ) {
            $match = in_array( $dir_object, $filter['include'], true );

            if ( $match ) {
                return true;
            }
        }

        if ( isset( $match ) && is_dir( $dir_object ) ) {
            $nested_dir_object = $dir_object . '/'; // append trailing slash to prevent a false match
            ksort( $filter ); // check exclusions first

            foreach( $filter as $filter_type => $filter_value ) {
                if ( $filter_type !== 'include' && $filter_type !== 'exclude' ) {
                    continue;
                }

                foreach ( $filter_value as $filter_object ) {
                    // if trailing asterisk exists remove it to allow a wildcard match
                    if ( substr( $filter_object, -1 ) === '*' ) {
                        $filter_object = substr( $filter_object, 0, -1 );
                    // append trailing slash to force a strict match otherwise
                    } else {
                        $filter_object = $filter_object . '/';
                    }

                    if ( str_replace( $filter_object, '', $nested_dir_object ) !== $nested_dir_object ) {
                        switch( $filter_type ) {
                            case 'include':
                                return true; // past inclusion or present wildcard inclusion
                            case 'exclude':
                                return false; // present wildcard exclusion
                        }
                    }

                    if ( strpos( $filter_object, $nested_dir_object ) === 0 ) {
                        return true; // future strict or wildcard inclusion
                    }
                }
            }
        }

        if ( isset( $filter['include'] ) ) {
            return false; // match not found
        }

        return true;
    }


    /**
     * get cache directory path from URL
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $url        full URL to potentially cached page, defaults to current URL if empty
     * @return  string  $cache_dir  directory path to new or potentially cached page, empty if URL is invalid
     */

    private static function get_cache_dir( $url = null ) {

        if ( empty ( $url ) ) {
            $url = 'http://' . Cache_Enabler_Engine::$request_headers['Host'] . $_SERVER['REQUEST_URI'];
        }

        $url_host = parse_url( $url, PHP_URL_HOST );
        if ( ! is_string( $url_host ) ) {
            return '';
        }

        $url_path = parse_url( $url, PHP_URL_PATH );
        if ( ! is_string( $url_path ) ) {
            $url_path = '';
        }

        $cache_dir = sprintf(
            '%s/%s%s',
            self::$cache_dir,
            strtolower( $url_host ),
            $url_path
        );

        // remove trailing slash (not using untrailingslashit() because it is not available in early engine start)
        $cache_dir = rtrim( $cache_dir, '/\\' );

        return $cache_dir;
    }


    /**
     * get cache iterator arguments
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $url   full URL to potentially cached page
     * @param   array   $args  cache iterator arguments from query string (precedence) and/or parameter, default otherwise
     * @return  array   $args  cache iterator arguments
     */

    private static function get_cache_iterator_args( $url = null, $args = array() ) {

        $default_args = array(
            'clear'    => 0,
            'subpages' => 0,
            'keys'     => 0,
            'expired'  => 0,
            'hooks'    => 0,
            'root'     => '',
        );

        // merge arguments defined in query string into arguments defined in parameter
        wp_parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query_string_args );
        $args = wp_parse_args( $query_string_args, $args );

        // merge defined arguments into default arguments
        $args = wp_parse_args( $args, $default_args );

        // validate arguments
        $args = self::validate_cache_iterator_args( $args );

        return $args;
    }


    /**
     * get cache file
     *
     * @since   1.7.0
     * @change  1.8.0
     *
     * @return  string  $cache_file  file path to new or potentially cached page
     */

    public static function get_cache_file() {

        $cache_file = sprintf(
            '%s/%s',
            self::get_cache_dir(),
            self::get_cache_file_name()
        );

        return $cache_file;
    }


    /**
     * get cache file name
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  string  $cache_file_name  file name for new or potentially cached page
     */

    private static function get_cache_file_name() {

        $cache_keys = self::get_cache_keys();
        $cache_file_name = $cache_keys['scheme'] . 'index' . $cache_keys['device'] . $cache_keys['webp'] . '.html' . $cache_keys['compression'];

        return $cache_file_name;
    }


    /**
     * get cache keys
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  array  $cache_keys  cache keys to new or potentially cached page
     */

    private static function get_cache_keys() {

        // set default cache keys
        $cache_keys = array(
            'scheme'      => 'http-',
            'device'      => '',
            'webp'        => '',
            'compression' => '',
        );

        // scheme cache key
        if ( isset( $_SERVER['HTTPS'] ) && ( strtolower( $_SERVER['HTTPS'] ) === 'on' || $_SERVER['HTTPS'] == '1' ) ) {
            $cache_keys['scheme'] = 'https-';
        } elseif ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == '443' ) {
            $cache_keys['scheme'] = 'https-';
        } elseif ( Cache_Enabler_Engine::$request_headers['X-Forwarded-Proto'] === 'https' || Cache_Enabler_Engine::$request_headers['X-Forwarded-Scheme'] === 'https' ) {
            $cache_keys['scheme'] = 'https-';
        }

        // device cache key
        if ( Cache_Enabler_Engine::$settings['mobile_cache'] ) {
            if ( strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Mobile' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Android' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Silk/' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Kindle' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'BlackBerry' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Opera Mini' ) !== false
                || strpos( Cache_Enabler_Engine::$request_headers['User-Agent'], 'Opera Mobi' ) !== false
            ) {
                $cache_keys['device'] = '-mobile';
            }
        }

        // webp cache key
        if ( Cache_Enabler_Engine::$settings['convert_image_urls_to_webp'] ) {
            if ( strpos( Cache_Enabler_Engine::$request_headers['Accept'], 'image/webp' ) !== false ) {
                $cache_keys['webp'] = '-webp';
            }
        }

        // compression cache key
        if ( Cache_Enabler_Engine::$settings['compress_cache'] ) {
            if ( function_exists( 'brotli_compress' )
                && $cache_keys['scheme'] === 'https-'
                && strpos( Cache_Enabler_Engine::$request_headers['Accept-Encoding'], 'br' ) !== false
            ) {
                $cache_keys['compression'] = '.br';
            } elseif ( strpos( Cache_Enabler_Engine::$request_headers['Accept-Encoding'], 'gzip' ) !== false ) {
                $cache_keys['compression'] = '.gz';
            }
        }

        return $cache_keys;
    }


    /**
     * get cache keys regex (e.g. #^(?=.*https)(?=.*webp).+$#, #^(?=.*https)(?!.*webp).+$#, or #^.+$#)
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   array   $cache_keys        cache keys to include or exclude
     * @return  string  $cache_keys_regex  cache keys regex if valid array, false if not
     */

    private static function get_cache_keys_regex( $cache_keys ) {

        if ( ! is_array( $cache_keys ) ) {
            return false;
        }

        $cache_keys_regex = '#^';

        foreach ( $cache_keys as $filter_type => $filter_value ) {
            switch ( $filter_type ) {
                case 'include':
                    $lookahead = '?=';
                    break;
                case 'exclude':
                    $lookahead = '?!';
                    break;
                default:
                    continue 2; // skip to next filter value
            }

            foreach( $filter_value as $cache_key ) {
                $cache_keys_regex .= '(' . $lookahead . '.*' . preg_quote( $cache_key ) . ')';
            }
        }

        $cache_keys_regex .= '.+$#';

        return $cache_keys_regex;
    }


    /**
     * get cache signature
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @param   string  $cache_file_name  file name for new cached page
     * @return  string  $cache_signature  cache signature
     */

    private static function get_cache_signature( $cache_file_name ) {

        $cache_signature = sprintf(
            '<!-- %s @ %s (%s) -->',
            'Cache Enabler by KeyCDN',
            self::get_current_time(),
            $cache_file_name
        );

        return $cache_signature;
    }


    /**
     * get cache size from disk (deprecated)
     *
     * @since       1.7.0
     * @deprecated  1.8.0
     */

    public static function get_cache_size( $dir = null ) {

        if ( empty( $dir ) ) {
            $cache_size = Cache_Enabler::get_cache_size();
        } else {
            $url        = self::get_cache_url( $dir );
            $cache      = self::cache_iterator( $url, array( 'subpages' => 1 ) );
            $cache_size = $cache['size'];
        }

        return $cache_size;
    }


    /**
     * get cache URL from directory path
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $dir        directory path (without trailing slash) to potentially cached page, defaults to site cache directory if empty
     * @return  string  $cache_url  full URL to potentially cached page
     */

    private static function get_cache_url( $dir = null ) {

        if ( empty( $dir ) ) {
            $dir = self::get_cache_dir( home_url() );
        }

        $cache_url = parse_url( home_url(), PHP_URL_SCHEME ) . '://' . str_replace( self::$cache_dir . '/', '', $dir );

        if ( Cache_Enabler_Engine::$settings['permalink_structure'] === 'has_trailing_slash' ) {
            $cache_url .= ( $cache_url !== home_url() || ! empty( Cache_Enabler::get_blog_path() ) ) ? '/' : '';
        }

        return $cache_url;
    }


    /**
     * get settings file
     *
     * @since   1.4.0
     * @change  1.5.5
     *
     * @param   boolean  $fallback       whether or not fallback settings file should be returned
     * @return  string   $settings_file  file path to settings file
     */

    private static function get_settings_file( $fallback = false ) {

        $settings_file = sprintf(
            '%s/%s',
            self::$settings_dir,
            self::get_settings_file_name( $fallback )
        );

        return $settings_file;
    }


    /**
     * get settings file name
     *
     * @since   1.5.5
     * @change  1.8.0
     *
     * @param   boolean  $fallback            whether or not fallback settings file name should be returned
     * @param   boolean  $skip_blog_path      whether or not blog path should be included in settings file name
     * @return  string   $settings_file_name  file name for settings file
     */

    private static function get_settings_file_name( $fallback = false, $skip_blog_path = false ) {

        $settings_file_name = '';

        // if creating or deleting settings file
        if ( function_exists( 'home_url' ) ) {
            $settings_file_name = parse_url( home_url(), PHP_URL_HOST );

            // subdirectory network
            if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
                $blog_path = Cache_Enabler::get_blog_path();
                $settings_file_name .= ( ! empty( $blog_path ) ) ? '.' . trim( $blog_path, '/' ) : '';
            }

            $settings_file_name .= '.php';
        // if getting settings from settings file
        } elseif ( is_dir( self::$settings_dir ) ) {
            if ( $fallback ) {
                $settings_files = array_map( 'basename', self::get_dir_objects( self::$settings_dir ) );
                $settings_file_regex = '/\.php$/';

                if ( is_multisite() ) {
                    $settings_file_regex = '/^' . strtolower( Cache_Enabler_Engine::$request_headers['Host'] );
                    $settings_file_regex = str_replace( '.', '\.', $settings_file_regex );

                    // subdirectory network
                    if ( defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && ! $skip_blog_path ) {
                        $url_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

                        if ( ! empty( $url_path ) ) {
                            $url_path_regex = str_replace( '/', '|', $url_path );
                            $url_path_regex = '\.(' . $url_path_regex . ')';
                            $settings_file_regex .= $url_path_regex;
                        }
                    }

                    $settings_file_regex .= '\.php$/';
                }

                $filtered_settings_files = preg_grep( $settings_file_regex, $settings_files );

                if ( ! empty( $filtered_settings_files ) ) {
                    $settings_file_name = current( $filtered_settings_files );
                } elseif ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && ! $skip_blog_path ) {
                    $fallback = true;
                    $skip_blog_path = true;
                    $settings_file_name = self::get_settings_file_name( $fallback, $skip_blog_path );
                }
            } else {
                $settings_file_name = strtolower( Cache_Enabler_Engine::$request_headers['Host'] );

                // subdirectory network
                if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && ! $skip_blog_path ) {
                    $url_path = $_SERVER['REQUEST_URI'];
                    $url_path_pieces = explode( '/', $url_path, 3 );
                    $blog_path = $url_path_pieces[1];

                    if ( ! empty( $blog_path ) ) {
                        $settings_file_name .= '.' . $blog_path;
                    }

                    $settings_file_name .= '.php';

                    // check if main site
                    if ( ! is_file( self::$settings_dir . '/' . $settings_file_name ) ) {
                        $fallback = false;
                        $skip_blog_path = true;
                        $settings_file_name = self::get_settings_file_name( $fallback, $skip_blog_path );
                    }
                }

                $settings_file_name .= ( strpos( $settings_file_name, '.php' ) === false ) ? '.php' : '';
            }
        }

        return $settings_file_name;
    }


    /**
     * get settings from settings file
     *
     * @since   1.5.0
     * @change  1.6.0
     *
     * @return  array  $settings  current settings from settings file
     */

    public static function get_settings() {

        $settings = array();

        // get settings file
        $settings_file = self::get_settings_file();

        // include settings file if it exists
        if ( is_file( $settings_file ) ) {
            $settings = include_once $settings_file;
        // try to get fallback settings file otherwise
        } else {
            $fallback = true;
            $fallback_settings_file = self::get_settings_file( $fallback );

            if ( is_file( $fallback_settings_file ) ) {
                $settings = include_once $fallback_settings_file;
            }
        }

        // create settings file if it does not exist and in late engine start
        if ( empty( $settings ) && class_exists( 'Cache_Enabler' ) ) {
            $new_settings_file = self::create_settings_file( Cache_Enabler::get_settings() );

            if ( is_file( $new_settings_file ) ) {
                $settings = include_once $new_settings_file;
            }
        }

        return $settings;
    }


    /**
     * get directory file system objects (files and directories)
     *
     * @since   1.4.7
     * @change  1.8.0
     *
     * @param   string   $dir          directory path to scan (without trailing slash)
     * @param   boolean  $recursive    whether to recursively include directory objects in nested directories
     * @param   array    $filter       directory path(s) relative to $dir to 'include' and/or 'exclude' (without leading and/or trailing slash)
     * @return  array    $dir_objects  path(s) to directory objects if valid directory, empty if not
     */

    private static function get_dir_objects( $dir, $recursive = false, $filter = null ) {

        $dir_objects = array();

        if ( ! is_dir( $dir ) ) {
            return $dir_objects;
        }

        $dir_object_names = scandir( $dir ); // sorted order is alphabetical in ascending order

        if ( is_array( $filter ) && empty( $filter['full_path'] ) ) {
            $filter['full_path'] = 1;

            foreach( $filter as $filter_type => &$filter_value ) {
                if ( $filter_type === 'include' || $filter_type === 'exclude' ) {
                    foreach( $filter_value as &$filter_object ) {
                        $filter_object = $dir . '/' . $filter_object;
                    }
                }
            }
        }

        foreach ( $dir_object_names as $dir_object_name ) {
            if ( $dir_object_name === '.' || $dir_object_name === '..' ) {
                continue; // skip object because it is current or parent folder link
            }

            $dir_object = $dir . '/' . $dir_object_name;

            if ( is_dir( $dir_object ) ) {
                if ( ! empty( $filter['full_path'] ) && ! self::filter_dir_object( $dir_object, $filter ) ) {
                    continue; // skip object because it is excluded
                }

                if ( $recursive ) {
                    $dir_objects = array_merge( $dir_objects, self::get_dir_objects( $dir_object, $recursive, $filter ) );
                }
            }

            $dir_objects[] = $dir_object;
        }

        return $dir_objects;
    }


    /**
     * get site file system objects (deprecated)
     *
     * @since       1.6.0
     * @deprecated  1.8.0
     */

    public static function get_site_objects( $site_url ) {

        $site_objects = array();

        // get directory
        $dir = self::get_cache_dir( $site_url );

        // check if directory exists
        if ( ! is_dir( $dir ) ) {
            return $site_objects;
        }

        // get site objects
        $site_objects = array_map( 'basename', self::get_dir_objects( $dir ) );

        // maybe filter subdirectory network site objects
        if ( is_multisite() && ! is_subdomain_install() ) {
            $blog_path  = Cache_Enabler::get_blog_path();
            $blog_paths = Cache_Enabler::get_blog_paths();

            // check if main site in subdirectory network
            if ( ! in_array( $blog_path, $blog_paths, true ) ) {
                foreach ( $site_objects as $key => $site_object ) {
                    // delete site object if it does not belong to main site
                    if ( in_array( '/' . $site_object . '/', $blog_paths, true ) ) {
                        unset( $site_objects[ $key ] );
                    }
                }
            }
        }

        return $site_objects;
    }


    /**
     * get current time
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  string  $current_time  current time in HTTP-date format
     */

    private static function get_current_time() {

        $current_time = current_time( 'D, d M Y H:i:s', true ) . ' GMT';

        return $current_time;
    }


    /**
     * get image path
     *
     * @since   1.4.8
     * @change  1.7.0
     *
     * @param   string  $image_url   full or relative URL with or without intrinsic width or density descriptor
     * @return  string  $image_path  file path to image
     */

    private static function get_image_path( $image_url ) {

        // in case image has intrinsic width or density descriptor
        $image_parts = explode( ' ', $image_url );
        $image_url = $image_parts[0];

        // in case installation is in a subdirectory
        $image_url_path = ltrim( parse_url( $image_url, PHP_URL_PATH ), '/' );
        $installation_dir = ltrim( parse_url( site_url( '/' ), PHP_URL_PATH ), '/' );
        $image_path = str_replace( $installation_dir, '', ABSPATH ) . $image_url_path;

        return $image_path;
    }


    /**
     * get current WP Filesystem instance
     *
     * @since   1.7.0
     * @change  1.7.1
     *
     * @throws  \RuntimeException                   if WordPress filesystem could not be initialized
     * @return  WP_Filesystem_Base  $wp_filesystem  WordPress filesystem instance
     */

    public static function get_filesystem() {

        global $wp_filesystem;

        // check if we already have a filesystem instance
        if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
            return $wp_filesystem;
        }

        // try initializing filesystem instance and cache the result
        try {
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $filesystem = WP_Filesystem();

            if ( $filesystem === null ) {
                throw new \RuntimeException( 'The provided filesystem method is unavailable.' );
            }

            if ( $filesystem === false ) {
                if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
                    throw new \RuntimeException(
                        $wp_filesystem->errors->get_error_message(),
                        ( is_numeric( $wp_filesystem->errors->get_error_code() ) ) ? (int) $wp_filesystem->errors->get_error_code() : 0
                    );
                }

                throw new \RuntimeException( 'Unspecified failure.' );
            }

            if ( ! is_object( $wp_filesystem ) || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
                throw new \RuntimeException( '$wp_filesystem is not an instance of WP_Filesystem_Base.' );
            }
        } catch ( \Exception $e ) {
            throw new \RuntimeException(
                sprintf( 'There was an error initializing the WP_Filesystem class: %1$s', $e->getMessage() ),
                $e->getCode(),
                $e
            );
        }

        return $wp_filesystem;
    }


    /**
     * make directory recursively based on directory path
     *
     * @since   1.7.0
     * @change  1.7.2
     *
     * @param   string   $dir  directory path to create
     * @return  boolean        true if the directory either already exists or was created and has the correct permissions, false otherwise
     */

    private static function mkdir_p( $dir ) {

        $fs          = self::get_filesystem();
        $mode_octal  = apply_filters( 'cache_enabler_mkdir_mode', 0755 );
        $mode_string = decoct( $mode_octal ); // get last three digits (e.g. '755')
        $parent_dir  = dirname( $dir );

        // check if directory and its parent have correct permissions
        if ( $fs->is_dir( $dir ) && $fs->getchmod( $dir ) === $mode_string && $fs->getchmod( $parent_dir ) === $mode_string ) {
            return true;
        }

        // create any directories that do not exist yet
        if ( ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        // check parent directory permissions
        if ( $fs->getchmod( $parent_dir ) !== $mode_string ) {
            return $fs->chmod( $parent_dir, $mode_octal, true );
        }

        // check directory permissions
        if ( $fs->getchmod( $dir ) !== $mode_string ) {
            return $fs->chmod( $dir, $mode_octal );
        }

        return true;
    }


    /**
     * remove empty directory based on directory path with the option to remove empty parent directories
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param    string         $dir          directory path to remove
     * @param    boolean        $parents      whether or not empty parent directories should be removed
     * @return   array|boolean  $removed_dir  directory path(s) that were removed, false if directory was not removed
     */

    private static function rmdir( $dir, $parents = false ) {

        $removed_dir = @rmdir( $dir );

        clearstatcache();

        if ( $removed_dir ) {
            $removed_dir = array( $dir => array() );

            if ( $parents ) {
                $parent_dir = dirname( $dir );

                while ( @rmdir( $parent_dir ) ) {
                    clearstatcache();
                    $removed_dir[ $parent_dir ] = array();
                    $parent_dir = dirname( $parent_dir );
                }
            }
        }

        return $removed_dir;
    }


    /**
     * set or unset WP_CACHE constant in wp-config.php
     *
     * @since   1.1.1
     * @change  1.7.0
     *
     * @param   boolean  $set  true to set WP_CACHE constant, false to unset
     */

    private static function set_wp_cache_constant( $set = true ) {

        // get config file
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            // config file resides in ABSPATH
            $wp_config_file = ABSPATH . 'wp-config.php';
        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            // config file resides one level above ABSPATH but is not part of another installation
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        } else {
            $wp_config_file = false;
        }

        // check if config file can be written to
        if ( ! $wp_config_file || ! is_writable( $wp_config_file ) ) {
            return;
        }

        // get config file contents
        $wp_config_file_contents = file_get_contents( $wp_config_file );

        // validate config file
        if ( ! is_string( $wp_config_file_contents ) ) {
            return;
        }

        // search for WP_CACHE constant
        $found_wp_cache_constant = preg_match( '/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,.+\);/', $wp_config_file_contents );

        // if not found set WP_CACHE constant when config file is default (must be before WordPress sets up)
        if ( $set && ! $found_wp_cache_constant ) {
            $ce_wp_config_lines  = '/** Enables page caching for Cache Enabler. */' . PHP_EOL;
            $ce_wp_config_lines .= "if ( ! defined( 'WP_CACHE' ) ) {" . PHP_EOL;
            $ce_wp_config_lines .= "\tdefine( 'WP_CACHE', true );" . PHP_EOL;
            $ce_wp_config_lines .= '}' . PHP_EOL;
            $ce_wp_config_lines .= PHP_EOL;
            $wp_config_file_contents = preg_replace( '/(\/\*\* Sets up WordPress vars and included files\. \*\/)/', $ce_wp_config_lines . '$1', $wp_config_file_contents );
        }

        // unset WP_CACHE constant if set by Cache Enabler
        if ( ! $set ) {
            $wp_config_file_contents = preg_replace( '/.+Added by Cache Enabler\r\n/', '', $wp_config_file_contents ); // < 1.5.0
            $wp_config_file_contents = preg_replace( '/\/\*\* Enables page caching for Cache Enabler\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
        }

        // update config file
        file_put_contents( $wp_config_file, $wp_config_file_contents, LOCK_EX );
    }


    /**
     * sort directory objects so path with least amount of slashes is first
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string   $a  file or directory path to compare in sort
     * @param   string   $b  file or directory path to compare in sort
     * @return  integer      1 if $a has more slashes than $b, 0 if equal, and -1 if less
     */

    private static function sort_dir_objects( $a, $b ) {

        $a = substr_count( $a, '/' );
        $b = substr_count( $b, '/' );

        if ( $a === $b ) {
            return 0;
        }

        return ( $a > $b ) ? 1 : -1;
    }


    /**
     * convert page contents
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @param   string  $page_contents            contents of a page from the output buffer
     * @return  string  $converted_page_contents  converted contents of a page from the output buffer
     */

    private static function converter( $page_contents ) {

        // attributes to convert during WebP conversion hook
        $attributes = (array) apply_filters( 'cache_enabler_convert_webp_attributes', array( 'src', 'srcset', 'data-[^=]+' ) );

        // stringify
        $attributes_regex = implode( '|', $attributes );

        // magic regex rule
        $image_urls_regex = '#(?:(?:(' . $attributes_regex . ')\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\?\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+[wx][^\"\'>]*)?(?=\/?[\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';

        // ignore query strings during WebP conversion hook
        if ( ! apply_filters( 'cache_enabler_convert_webp_ignore_query_strings', true ) ) {
            $image_urls_regex = '#(?:(?:(' . $attributes_regex . ')\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+[wx][^\"\'>]*)?(?=\/?[\?\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';
        }

        // page contents after WebP conversion hook
        $converted_page_contents = apply_filters( 'cache_enabler_page_contents_after_webp_conversion', preg_replace_callback( $image_urls_regex, 'self::convert_webp', $page_contents ) );

        // deprecated page contents after WebP conversion hook
        $converted_page_contents = apply_filters_deprecated( 'cache_enabler_disk_webp_converted_data', array( $converted_page_contents ), '1.6.0', 'cache_enabler_page_contents_after_webp_conversion' );

        return $converted_page_contents;
    }


    /**
     * convert image URL(s) to WebP
     *
     * @since   1.0.1
     * @change  1.5.0
     *
     * @param   array   $matches     pattern matches from parsed page contents
     * @return  string  $conversion  converted image URL(s) to WebP if applicable, default URL(s) otherwise
     */

    private static function convert_webp( $matches ) {

        $full_match = $matches[0];
        $image_extension_regex = '/(\.jpe?g|\.png)/i';
        $image_found = preg_match( $image_extension_regex, $full_match );

        if ( $image_found ) {
            // set image URL(s)
            $image_urls = explode( ',', $full_match );

            foreach ( $image_urls as &$image_url ) {
                $image_url = trim( $image_url, ' ' );
                $image_url_webp = preg_replace( $image_extension_regex, '$1.webp', $image_url ); // append .webp extension
                $image_path_webp = self::get_image_path( $image_url_webp );

                // check if WebP image exists
                if ( is_file( $image_path_webp ) ) {
                    $image_url = $image_url_webp;
                } else {
                    $image_url_webp = preg_replace( $image_extension_regex, '', $image_url_webp ); // remove default extension
                    $image_path_webp = self::get_image_path( $image_url_webp );

                    // check if WebP image exists
                    if ( is_file( $image_path_webp ) ) {
                        $image_url = $image_url_webp;
                    }
                }
            }

            $conversion = implode( ', ', $image_urls );

            return $conversion;
        }
    }


    /**
     * minify HTML
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @param   string  $page_contents                 contents of a page from the output buffer
     * @return  string  $page_contents|$minified_html  minified page contents if applicable, unchanged otherwise
     */

    private static function minify_html( $page_contents ) {

        // HTML character limit
        if ( strlen( $page_contents ) > 700000 ) {
            return $page_contents;
        }

        // HTML tags to ignore hook
        $ignore_tags = (array) apply_filters( 'cache_enabler_minify_html_ignore_tags', array( 'textarea', 'pre', 'code' ) );

        // deprecated HTML tags to ignore hook
        $ignore_tags = (array) apply_filters_deprecated( 'cache_minify_ignore_tags', array( $ignore_tags ), '1.6.0', 'cache_enabler_minify_html_ignore_tags' );

        // if setting selected exclude inline CSS and JavaScript
        if ( ! Cache_Enabler_Engine::$settings['minify_inline_css_js'] ) {
            array_push( $ignore_tags, 'style', 'script' );
        }

        // check if there are ignore tags
        if ( ! $ignore_tags ) {
            return $page_contents;
        }

        // stringify
        $ignore_tags_regex = implode( '|', $ignore_tags );

        // remove HTML comments
        $minified_html = preg_replace( '#<!--[^\[><].*?-->#s', '', $page_contents );

        // if setting selected remove CSS and JavaScript comments
        if ( Cache_Enabler_Engine::$settings['minify_inline_css_js'] ) {
            $minified_html = preg_replace(
                '#/\*(?!!)[\s\S]*?\*/|(?:^[ \t]*)//.*$|((?<!\()[ \t>;,{}[\]])//[^;\n]*$#m',
                '$1',
                $minified_html
            );
        }

        // minify HTML
        $minified_html = preg_replace(
            '#(?>[^\S ]\s*|\s{2,})(?=[^<]*+(?:<(?!/?(?:' . $ignore_tags_regex . ')\b)[^<]*+)*+(?:<(?>' . $ignore_tags_regex . ')\b|\z))#ix',
            ' ',
            $minified_html
        );

        // something went wrong
        if ( strlen( $minified_html ) <= 1 ) {
            return $page_contents;
        }

        return $minified_html;
    }


    /**
     * delete settings file
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string  file path to settings file, defaults to settings file path of current site if empty
     */

    private static function delete_settings_file( $settings_file = null ) {

        if ( empty( $settings_file ) ) {
            $settings_file = self::get_settings_file();
        }

        // delete settings file
        @unlink( $settings_file );

        // delete settings directory and its parent directory if empty
        self::rmdir( self::$settings_dir, true );
    }


    /**
     * delete asset (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.5.0
     */

    public static function delete_asset( $url ) {

        if ( empty( $url ) ) {
            return;
        }

        Cache_Enabler::clear_page_cache_by_url( $url, 'subpages' );
    }


    /**
     * validate cache iterator arguments
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   array  $args            see self::cache_iterator() for available arguments
     * @return  array  $validated_args  validated cache iterator arguments
     */

    private static function validate_cache_iterator_args( $args ) {

        $validated_args = array();

        foreach( $args as $arg_name => $arg_value ) {
            if ( $arg_name === 'root' ) {
                $validated_args[ $arg_name ] = (string) $arg_value;
            } elseif ( is_array( $arg_value ) ) {
                foreach( $arg_value as $filter_type => $filter_value ) {
                    if ( is_string( $filter_value ) ) {
                        $filter_value = ( substr_count( $filter_value, '|' ) > 0 ) ? explode( '|', $filter_value ) : explode( ',', $filter_value );
                    } elseif ( ! is_array( $filter_value ) ) {
                        $filter_value = array(); // not converting type to avoid unwanted values
                    }

                    foreach ( $filter_value as $filter_value_key => &$filter_value_item ) {
                        $filter_value_item = trim( $filter_value_item, '/- ' );

                        if ( empty( $filter_value_item ) ) {
                            unset( $filter_value[ $filter_value_key ] );
                        }
                    }

                    if ( $filter_type !== 'include' || $filter_type !== 'exclude' ) {
                        unset( $arg_value[ $filter_type ] );

                        if ( $filter_type === 0 || $filter_type === 'i' ) {
                            $filter_type = 'include';
                        } elseif ( $filter_type === 1 || $filter_type === 'e' ) {
                            $filter_type = 'exclude';
                        }
                    }

                    $arg_value[ $filter_type ] = $filter_value;
                }

                $validated_args[ $arg_name ] = $arg_value;
            } else {
                $validated_args[ $arg_name ] = (int) $arg_value;
            }
        }

        return $validated_args;
    }
}
