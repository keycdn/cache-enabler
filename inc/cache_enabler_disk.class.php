<?php
/**
 * Class used for handling disk-related operations.
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler_Disk {
    /**
     * Plugin cache directory (deprecated).
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */
    public static $cache_dir = WP_CONTENT_DIR . '/cache/cache-enabler';

    /**
     * File path to the cached page for the current URL.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @var  string
     */
    private static $cache_file;

    /**
     * Add and configure files required by plugin.
     *
     * @since   1.5.0
     * @change  1.8.0
     */
    public static function setup() {

        self::create_advanced_cache_file();
        self::set_wp_cache_constant();
    }

    /**
     * Delete and unconfigure files required by plugin.
     *
     * @since   1.5.0
     * @change  1.8.0
     */
    public static function clean() {

        self::delete_settings_file();

        if ( ! is_dir( CACHE_ENABLER_SETTINGS_DIR ) ) {
            array_map( 'unlink', glob( WP_CONTENT_DIR . '/cache/cache-enabler-advcache-*.json' ) ); // < 1.4.0
            array_map( 'unlink', glob( ABSPATH . 'CE_SETTINGS_PATH-*.json' ) ); // = 1.4.0
            @unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
            self::set_wp_cache_constant( false );
        }
    }

    /**
     * Create a static HTML file from the page contents received from the cache engine.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param  string  $page_contents  Page contents from the cache engine as raw HTML.
     */
    public static function cache_page( $page_contents ) {

        /**
         * Filters the page contents before a static HTML file is created.
         *
         * @since   1.6.0
         * @change  1.6.0
         *
         * @param  string  $page_contents  Page contents from the cache engine as raw HTML.
         */
        $page_contents = apply_filters( 'cache_enabler_page_contents_before_store', $page_contents );
        $page_contents = apply_filters_deprecated( 'cache_enabler_before_store', array( $page_contents ), '1.6.0', 'cache_enabler_page_contents_before_store' );

        self::create_cache_file( $page_contents );
    }

    /**
     * Whether a cached page exists.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string  $cache_file  File path to the cached page.
     * @return  bool                 True if the cached page exists and is readable, false otherwise.
     */
    public static function cache_exists( $cache_file ) {

        return is_readable( $cache_file );
    }

    /**
     * Whether a cached page is expired.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string  $cache_file  File path to the existing cached page.
     * @return  bool                 True if the cached page is expired, false otherwise.
     */
    public static function cache_expired( $cache_file ) {

        if ( ! Cache_Enabler_Engine::$settings['cache_expires'] || Cache_Enabler_Engine::$settings['cache_expiry_time'] === 0 ) {
            return false;
        }

        $expires_seconds = 3600 * Cache_Enabler_Engine::$settings['cache_expiry_time'];

        if ( ( filemtime( $cache_file ) + $expires_seconds ) <= time() ) {
            return true;
        }

        return false;
    }

    /**
     * Iterate over cache objects to perform actions and/or gather data.
     *
     * @since   1.8.0
     * @change  1.8.0
     * @access  private
     *
     * @param   string        $url   URL to a cached page (with or without scheme and wildcard path).
     * @param   array|string  $args  TODO
     * @return  array                Cache data.
     */
    public static function cache_iterator( $url, $args = array() ) {

        $cache = array(
            'index' => array(),
            'size'  => 0,
        );

        if ( ! is_string( $url ) || empty( $url ) ) {
            return $cache;
        }

        $url       = esc_url_raw( $url, array( 'http', 'https' ) );
        $cache_dir = self::get_cache_dir( $url );

        if ( ! is_dir( $cache_dir ) ) {
            return $cache;
        }

        $switched = false;
        if ( is_multisite() && ! ms_is_switched() ) {
            $blog_domain = (string) parse_url( $url, PHP_URL_HOST );
            $blog_path   = is_subdomain_install() ? '/' : Cache_Enabler::get_blog_path_from_url( $url );
            $blog_id     = get_blog_id_from_url( $blog_domain, $blog_path );

            if ( $blog_id !== 0 ) {
                $switched = Cache_Enabler::switch_to_blog( $blog_id, true );
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
                    continue; // Skip to next object because file does not start with provided root path.
                }

                $cache_object_name = basename( $cache_object );

                if ( $cache_keys_regex && ! preg_match( $cache_keys_regex, $cache_object_name ) ) {
                    continue; // Skip to next object because file name does not match provided cache keys.
                }

                if ( $args['expired'] && ! self::cache_expired( $cache_object ) ) {
                    continue; // Skip to next object because file is not expired.
                }

                $cache_object_dir  = dirname( $cache_object );
                $cache_object_size = (int) @filesize( $cache_object );

                if ( $args['clear'] ) {
                    if ( ! @unlink( $cache_object ) ) {
                        continue; // Skip to next object because file deletion failed.
                    }

                    $cache_object_size = -$cache_object_size; // Cache size is negative when cleared.

                    self::rmdir( $cache_object_dir, true ); // Remove containing directory if empty along with any of its empty parents.
                }

                if ( strpos( $cache_object_name, 'index' ) === false ) {
                    continue; // Skip to next object because file is not a cache version and no longer needs to be handled (e.g. hidden file).
                }

                if ( ! isset( $cache['index'][ $cache_object_dir ]['url'] ) ) {
                    $cache['index'][ $cache_object_dir ]['url'] = self::get_cache_url( $cache_object_dir );
                    $cache['index'][ $cache_object_dir ]['id']  = url_to_postid( $cache['index'][ $cache_object_dir ]['url'] );
                }

                $cache['index'][ $cache_object_dir ]['versions'][ $cache_object_name ] = $cache_object_size;
                $cache['size'] += $cache_object_size;
            }
        }

        uksort( $cache['index'], 'self::sort_dir_objects' ); // Sort cache index so path with least amount of slashes is first.

        if ( $args['clear'] ) {
            self::fire_cache_cleared_hooks( $cache['index'], $args['hooks'] );
        }

        if ( $switched ) {
            Cache_Enabler::restore_current_blog( true );
        }

        return $cache;
    }

    /**
     * Get the cache size (deprecated).
     *
     * @since       1.0.0
     * @deprecated  1.7.0
     */
    public static function cache_size( $dir = null ) {

        return self::get_cache_size( $dir );
    }

    /**
     * Clear the cache (deprecated).
     *
     * @since       1.0.0
     * @deprecated  1.8.0
     */
    public static function clear_cache( $clear_url = null, $clear_type = 'page' ) {

        Cache_Enabler::clear_page_cache_by_url( $clear_url, $clear_type );
    }

    /**
     * Create the advanced-cache.php drop-in file.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @return  string|bool  Path to the created file, false on failure.
     */
    public static function create_advanced_cache_file() {

        $advanced_cache_sample_file = CACHE_ENABLER_DIR . '/advanced-cache-sample.php';

        if ( ! is_readable( $advanced_cache_sample_file ) ) {
            return false;
        }

        $advanced_cache_file          = WP_CONTENT_DIR . '/advanced-cache.php';
        $advanced_cache_file_contents = file_get_contents( $advanced_cache_sample_file );
        $advanced_cache_file_contents = str_replace( '/your/path/to/wp-content/plugins/cache-enabler', CACHE_ENABLER_DIR, $advanced_cache_file_contents );

        if ( ! self::mkdir_p( dirname( $advanced_cache_file ) ) ) {
            return false;
        }

        $advanced_cache_file_created = file_put_contents( $advanced_cache_file, $advanced_cache_file_contents, LOCK_EX );

        return ( $advanced_cache_file_created === false ) ? false : $advanced_cache_file;
    }

    /**
     * Create a static HTML file.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param  string  $page_contents  Page contents from the cache engine as raw HTML.
     */
    private static function create_cache_file( $page_contents ) {

        if ( ! is_string( $page_contents ) || strlen( $page_contents ) === 0 ) {
            return;
        }

        $new_cache_file      = self::get_cache_file();
        $new_cache_file_dir  = dirname( $new_cache_file );
        $new_cache_file_name = basename( $new_cache_file );

        if ( Cache_Enabler_Engine::$settings['minify_html'] ) {
            $page_contents = self::minify_html( $page_contents );
        }

        $page_contents = $page_contents . self::get_cache_signature( $new_cache_file_name );

        if ( strpos( $new_cache_file_name, 'webp' ) !== false ) {
            $page_contents = self::converter( $page_contents );
        }

        switch ( substr( $new_cache_file_name, -2, 2 ) ) {
            case 'br':
                $page_contents = brotli_compress( $page_contents );
                break;
            case 'gz':
                $page_contents = gzencode( $page_contents, 9 );
                break;
        }

        if ( $page_contents === false ) {
            return; // Compression failed.
        }

        if ( ! self::mkdir_p( $new_cache_file_dir ) ) {
            return;
        }

        $new_cache_file_created = file_put_contents( $new_cache_file, $page_contents, LOCK_EX );

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

            /**
             * Fires after the page cache has been created.
             *
             * @since   1.8.0
             * @change  1.8.0
             *
             * @param  string   $page_created_url     Full URL of the page created.
             * @param  int      $page_created_id      Post ID of the page created.
             * @param  array[]  $cache_created_index  Index of the cache created.
             */
            do_action( 'cache_enabler_page_cache_created', $page_created_url, $page_created_id, $cache_created_index );
        }
    }

    /**
     * Create a settings file.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   array        $settings  Plugin settings from the database.
     * @return  string|bool             Path to the created file, false on failure.
     */
    public static function create_settings_file( $settings ) {

        if ( ! is_array( $settings ) || ! function_exists( 'home_url' ) ) {
            return false;
        }

        $new_settings_file = self::get_settings_file();

        $new_settings_file_contents  = '<?php' . PHP_EOL;
        $new_settings_file_contents .= '/**' . PHP_EOL;
        $new_settings_file_contents .= ' * Cache Enabler settings for ' . home_url() . PHP_EOL;
        $new_settings_file_contents .= ' *' . PHP_EOL;
        $new_settings_file_contents .= ' * @since    1.5.0' . PHP_EOL;
        $new_settings_file_contents .= ' * @change   1.8.0' . PHP_EOL;
        $new_settings_file_contents .= ' * @created  ' . self::get_current_time() . PHP_EOL;
        $new_settings_file_contents .= ' */' . PHP_EOL;
        $new_settings_file_contents .= PHP_EOL;
        $new_settings_file_contents .= 'return ' . var_export( $settings, true ) . ';';

        if ( ! self::mkdir_p( dirname( $new_settings_file ) ) ) {
            return false;
        }

        $new_settings_file_created = file_put_contents( $new_settings_file, $new_settings_file_contents, LOCK_EX );

        return ( $new_settings_file_created === false ) ? false : $new_settings_file;
    }

    /**
     * Fire the cache cleared hooks.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param  array[]  $cache_cleared_index  Index of the cache cleared.
     * @param  array[]  $hooks                Cache cleared hooks to 'include' and/or 'exclude' from being fired.
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
                do_action( 'ce_action_cache_by_url_cleared', $page_cleared_url ); // Deprecated in 1.6.0.
            }
        }

        if ( in_array( 'cache_enabler_site_cache_cleared', $hooks_to_fire, true ) && empty( Cache_Enabler::get_cache_index() ) ) {
            $site_cleared_url = user_trailingslashit( home_url() );
            $site_cleared_id  = get_current_blog_id();

            do_action( 'cache_enabler_site_cache_cleared', $site_cleared_url, $site_cleared_id, $cache_cleared_index );
        }

        if ( in_array( 'cache_enabler_complete_cache_cleared', $hooks_to_fire, true ) && ! is_dir( CACHE_ENABLER_CACHE_DIR ) ) {
            do_action( 'cache_enabler_complete_cache_cleared' );
            do_action( 'ce_action_cache_cleared' ); // Deprecated in 1.6.0.
        }
    }

    /**
     * Filters whether a file or directory should be included or excluded.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string   $dir_object  File or directory path to filter (without trailing slash).
     * @param   array[]  $filter      File or directory path(s) to 'include' and/or 'exclude' (without trailing slash).
     * @return  bool                  True if directory object should be included, false if excluded.
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

        if ( ! isset( $match ) ) {
            return true;
        }

        ksort( $filter ); // Sort keys in alphabetical order to check for an exclusion first.

        if ( is_dir( $dir_object ) ) {
            $dir_object = $dir_object . '/'; // Append trailing slash to prevent a false match.
        }

        foreach ( $filter as $filter_type => $filter_value ) {
            if ( $filter_type !== 'include' && $filter_type !== 'exclude' ) {
                continue;
            }

            foreach ( $filter_value as $filter_object ) {
                // If a trailing asterisk exists remove it to allow a wildcard match.
                if ( substr( $filter_object, -1, 1 ) === '*' ) {
                    $filter_object = substr( $filter_object, 0, -1 );
                // Otherwise, maybe append a trailing slash to force a strict match.
                } elseif ( is_dir( $dir_object ) ) {
                    $filter_object = $filter_object . '/';
                }

                if ( str_replace( $filter_object, '', $dir_object ) !== $dir_object ) {
                    switch ( $filter_type ) {
                        case 'include':
                            return true; // Past inclusion or present wildcard inclusion.
                        case 'exclude':
                            return false; // Present wildcard exclusion.
                    }
                }

                if ( strpos( $filter_object, $dir_object ) === 0 && $filter_type === 'include' ) {
                    return true; // Future strict or wildcard inclusion.
                }
            }
        }

        if ( isset( $filter['include'] ) ) {
            return false; // Match not found.
        }

        return true;
    }

    /**
     * Get the cache directory path for the current URL or from a given URL.
     *
     * This does not check whether the returned cache directory path exists.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $url  (Optional) Full URL to a cached page (with or without wildcard path). Default
     *                        is the current URL.
     * @return  string        Cache directory path (without trailing slash), empty string if the URL is invalid.
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
        } elseif ( substr( $url_path, -1, 1 ) === '*' ) {
            $url_path = dirname( $url_path );
        }

        $cache_dir = sprintf(
            '%s/%s%s',
            CACHE_ENABLER_CACHE_DIR,
            strtolower( $url_host ),
            $url_path
        );

        // Remove trailing slash. Not using the untrailingslashit() function because it is
        // not available in the early cache engine start.
        $cache_dir = rtrim( $cache_dir, '/\\' );

        return $cache_dir;
    }

    /**
     * Get the cache iterator arguments.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @global  WP_Rewrite  $wp_rewrite  WordPress rewrite component.
     *
     * @param   string        $url   (Optional) Full URL to a cached page (with or without wildcard path and query
     *                               string). Default null.
     * @param   array|string  $args  (Optional) Cache iterator arguments or an arguments template. Default empty array.
     * @return  array                Cache iterator arguments.
     */
    private static function get_cache_iterator_args( $url = null, $args = array() ) {

        $default_args = array(
            'clear'    => 0,
            'expired'  => 0,
            'hooks'    => 0,
            'keys'     => 0,
            'root'     => '',
            'subpages' => 0,
        );

        if ( ! is_array( $args ) ) {
            $args_template = $args;
            $args = array(
                'clear' => 1,
                'hooks' => array( 'include' => 'cache_enabler_page_cache_cleared' ),
            );

            switch ( $args_template ) {
                case 'pagination':
                    global $wp_rewrite;
                    $included_subpages[] = isset( $wp_rewrite->pagination_base ) ? $wp_rewrite->pagination_base : '';
                    $included_subpages[] = isset( $wp_rewrite->comments_pagination_base ) ? $wp_rewrite->comments_pagination_base . '-*' : '';
                    $args['subpages']['include'] = $included_subpages;
                    break;
                case 'subpages':
                    $args['subpages'] = 1;
                    break;
                default:
                    $args = array();
            }
        }

        $url_path = (string) parse_url( $url, PHP_URL_PATH );
        if ( substr( $url_path, -1, 1 ) === '*' ) {
            $args['root'] = CACHE_ENABLER_CACHE_DIR . '/' . substr( (string) parse_url( $url, PHP_URL_HOST ) . $url_path, 0, -1 );
            $args['subpages']['include'] = basename( $url_path );
        }

        // Merge query string arguments into the parameter arguments and then the default arguments.
        wp_parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query_string_args );
        $args = wp_parse_args( $query_string_args, $args );
        $args = wp_parse_args( $args, $default_args );
        $args = self::validate_cache_iterator_args( $args );

        return $args;
    }

    /**
     * Get the path to the cache file for the current URL.
     *
     * This does not check whether the returned cache file exists.
     *
     * @since   1.7.0
     * @change  1.8.0
     *
     * @return  string  Path to the cache file.
     */
    public static function get_cache_file() {

        if ( ! empty( self::$cache_file ) ) {
            return self::$cache_file;
        }

        self::$cache_file = sprintf(
            '%s/%s',
            self::get_cache_dir(),
            self::get_cache_file_name()
        );

        return self::$cache_file;
    }

    /**
     * Get the name of the cache file for the current URL.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  string  Name of the cache file.
     */
    private static function get_cache_file_name() {

        $cache_keys      = self::get_cache_keys();
        $cache_file_name = $cache_keys['scheme'] . 'index' . $cache_keys['device'] . $cache_keys['webp'] . '.html' . $cache_keys['compression'];

        return $cache_file_name;
    }

    /**
     * Get the cache keys from the request headers for the cache file name.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  string[]  An array of cache keys with names as the keys and keys as the values.
     */
    private static function get_cache_keys() {

        $cache_keys = array(
            'scheme'      => 'http-',
            'device'      => '',
            'webp'        => '',
            'compression' => '',
        );

        if ( isset( $_SERVER['HTTPS'] ) && ( strtolower( $_SERVER['HTTPS'] ) === 'on' || $_SERVER['HTTPS'] == '1' ) ) {
            $cache_keys['scheme'] = 'https-';
        } elseif ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == '443' ) {
            $cache_keys['scheme'] = 'https-';
        } elseif ( Cache_Enabler_Engine::$request_headers['X-Forwarded-Proto'] === 'https'
            || Cache_Enabler_Engine::$request_headers['X-Forwarded-Scheme'] === 'https'
        ) {
            $cache_keys['scheme'] = 'https-';
        }

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

        if ( Cache_Enabler_Engine::$settings['convert_image_urls_to_webp'] ) {
            if ( strpos( Cache_Enabler_Engine::$request_headers['Accept'], 'image/webp' ) !== false ) {
                $cache_keys['webp'] = '-webp';
            }
        }

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
     * Get the cache keys regex for the cache iterator.
     *
     * This uses positive and negative lookaheads to create a regex that will be used
     * to check the name of the cache file in the cache iterator, for example:
     *     * #^(?=.*https)(?=.*webp).+$#
     *     * #^(?=.*https)(?!.*webp).+$#
     *     * #^.+$#
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   array[]  $cache_keys  Cache keys to 'include' and/or 'exclude'.
     * @return  string                Cache keys regex, false on failure.
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
                    continue 2; // Skip to the next filter value.
            }

            foreach ( $filter_value as $cache_key ) {
                $cache_keys_regex .= '(' . $lookahead . '.*' . preg_quote( $cache_key ) . ')';
            }
        }

        $cache_keys_regex .= '.+$#';

        return $cache_keys_regex;
    }

    /**
     * Get the cache signature.
     *
     * This gets an HTML comment to insert at the bottom of a new static HTML file.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @param   string  $cache_file_name  Name of the new cache file.
     * @return  string                    HTML comment with the current time in HTTP-date format and the new cache file name.
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
     * Get the cache size from the disk (deprecated).
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
     * Get the cache URL for a given directory path.
     *
     * This only checks if the given directory path is in the plugin cache directory.
     * It does not check whether the URL returned is from a cache directory that
     * exists.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $dir  Directory path to a cached page.
     * @return  string        Full cache URL (with trailing slash if set), empty string if the directory path
     *                        is invalid.
     */
    private static function get_cache_url( $dir ) {

        if ( strpos( $dir, CACHE_ENABLER_CACHE_DIR ) !== 0 ) {
            return '';
        }

        $cache_url = parse_url( home_url(), PHP_URL_SCHEME ) . '://' . str_replace( CACHE_ENABLER_CACHE_DIR . '/', '', $dir );
        $cache_url = user_trailingslashit( $cache_url );

        return $cache_url;
    }

    /**
     * Get the path to the settings file for the current site.
     *
     * @since   1.4.0
     * @change  1.8.0
     *
     * @param   bool     $fallback  (Optional) Whether the fallback settings file should be returned. Default false.
     * @return  string              Path to the settings file.
     */
    private static function get_settings_file( $fallback = false ) {

        $settings_file = sprintf(
            '%s/%s',
            CACHE_ENABLER_SETTINGS_DIR,
            self::get_settings_file_name( $fallback )
        );

        return $settings_file;
    }

    /**
     * Get the name of the settings file for the current site.
     *
     * @since   1.5.5
     * @change  1.8.0
     *
     * @param   bool    $fallback        (Optional) Whether the fallback settings file name should be returned. Default false.
     * @param   bool    $skip_blog_path  (Optional) Whether the blog path should be included in the settings file name.
     *                                   Default false.
     * @return  string                   Name of the settings file.
     */
    private static function get_settings_file_name( $fallback = false, $skip_blog_path = false ) {

        $settings_file_name = '';

        // When creating or deleting the settings file.
        if ( function_exists( 'home_url' ) ) {
            $settings_file_name = parse_url( home_url(), PHP_URL_HOST );

            if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
                $blog_path = Cache_Enabler::get_blog_path();
                $settings_file_name .= ( ! empty( $blog_path ) ) ? '.' . trim( $blog_path, '/' ) : '';
            }

            $settings_file_name .= '.php';
        // When getting the plugin settings from the settings file.
        } elseif ( is_dir( CACHE_ENABLER_SETTINGS_DIR ) ) {
            if ( $fallback ) {
                $settings_files      = array_map( 'basename', self::get_dir_objects( CACHE_ENABLER_SETTINGS_DIR ) );
                $settings_file_regex = '/\.php$/';

                if ( is_multisite() ) {
                    $settings_file_regex = '/^' . strtolower( Cache_Enabler_Engine::$request_headers['Host'] );
                    $settings_file_regex = str_replace( '.', '\.', $settings_file_regex );

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

                if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && ! $skip_blog_path ) {
                    $url_path = $_SERVER['REQUEST_URI'];
                    $url_path_pieces = explode( '/', $url_path, 3 );
                    $blog_path = $url_path_pieces[1];

                    if ( ! empty( $blog_path ) ) {
                        $settings_file_name .= '.' . $blog_path;
                    }

                    $settings_file_name .= '.php';

                    // Check if the main site in a subdirectory network.
                    if ( ! is_file( CACHE_ENABLER_SETTINGS_DIR . '/' . $settings_file_name ) ) {
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
     * Get the plugin settings from the settings file for the current site.
     *
     * This will create the settings file if it does not exist and the cache engine
     * was started late. It will also update the disk and backend requirements and
     * then clear the complete cache if the settings are outdated. Having the backend
     * updated will trigger a new settings file to be created, which would result in
     * this returning the settings from that new file afterward.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  array  Plugin settings from the settings file, empty array on failure.
     */
    public static function get_settings() {

        $settings      = array();
        $settings_file = self::get_settings_file();

        if ( is_file( $settings_file ) ) {
            $settings = include $settings_file;
        } else {
            $fallback      = true;
            $settings_file = self::get_settings_file( $fallback );

            if ( is_file( $settings_file ) ) {
                $settings = include $settings_file;
            }
        }

        $outdated_settings = ( ! empty( $settings ) && ( ! defined( 'CACHE_ENABLER_VERSION' ) || ! isset( $settings['version'] ) || $settings['version'] !== CACHE_ENABLER_VERSION ) );

        if ( $outdated_settings ) {
            $settings = array();
        }

        if ( empty( $settings ) && class_exists( 'Cache_Enabler' ) ) {
            if ( $outdated_settings ) {
                Cache_Enabler::update();
                wp_opcache_invalidate( $settings_file );
                $settings = self::get_settings();
            } else {
                $settings_file = self::create_settings_file( Cache_Enabler::get_settings() );

                if ( $settings_file !== false ) {
                    $settings = include $settings_file;
                }
            }
        }

        return $settings;
    }

    /**
     * Get the files and directories inside of a given directory.
     *
     * @since   1.4.7
     * @change  1.8.0
     *
     * @param   string    $dir        Directory path to scan (without trailing slash).
     * @param   bool      $recursive  (Optional) Whether to recursively include directory objects in nested
     *                                directories. Default false.
     * @param   array[]   $filter     (Optional) Directory paths relative to $dir (without leading and/or trailing
     *                                slashes) to 'include' and/or 'exclude'. Default null.
     * @return  string[]              File and directory paths to objects found, empty array if the directory path is invalid.
     */
    private static function get_dir_objects( $dir, $recursive = false, $filter = null ) {

        $dir_objects = array();

        if ( ! is_dir( $dir ) ) {
            return $dir_objects;
        }

        $dir_object_names = scandir( $dir ); // Sorted order is alphabetical in ascending order.

        if ( is_array( $filter ) && empty( $filter['full_path'] ) ) {
            $filter['full_path'] = 1;

            foreach ( $filter as $filter_type => &$filter_value ) {
                if ( $filter_type === 'include' || $filter_type === 'exclude' ) {
                    foreach ( $filter_value as &$filter_object ) {
                        $filter_object = $dir . '/' . $filter_object;
                    }
                }
            }
        }

        foreach ( $dir_object_names as $dir_object_name ) {
            if ( $dir_object_name === '.' || $dir_object_name === '..' ) {
                continue; // Skip object because it is the current or parent folder link.
            }

            $dir_object = $dir . '/' . $dir_object_name;

            if ( is_dir( $dir_object ) ) {
                if ( ! empty( $filter['full_path'] ) && ! self::filter_dir_object( $dir_object, $filter ) ) {
                    continue; // Skip object because it is excluded.
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
     * Get the site objects (deprecated).
     *
     * @since       1.6.0
     * @deprecated  1.8.0
     */
    public static function get_site_objects( $site_url ) {

        $site_objects = array();
        $dir          = self::get_cache_dir( $site_url );

        if ( ! is_dir( $dir ) ) {
            return $site_objects;
        }

        $site_objects = array_map( 'basename', self::get_dir_objects( $dir ) );

        // Maybe filter the site objects.
        if ( is_multisite() && ! is_subdomain_install() ) {
            $blog_path  = Cache_Enabler::get_blog_path();
            $blog_paths = Cache_Enabler::get_blog_paths();

            // Check if the main site in a subdirectory network.
            if ( ! in_array( $blog_path, $blog_paths, true ) ) {
                foreach ( $site_objects as $key => $site_object ) {
                    // Delete the site object if it does not belong to the main site.
                    if ( in_array( '/' . $site_object . '/', $blog_paths, true ) ) {
                        unset( $site_objects[ $key ] );
                    }
                }
            }
        }

        return $site_objects;
    }

    /**
     * Get the current time.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @return  string  Current time in HTTP-date format.
     */
    private static function get_current_time() {

        $current_time = current_time( 'D, d M Y H:i:s', true ) . ' GMT';

        return $current_time;
    }

    /**
     * Get the image path from an image URL.
     *
     * This does not check whether the returned image exists.
     *
     * @since   1.4.8
     * @change  1.8.0
     *
     * @param   string  $image_url  Full or relative URL maybe with an intrinsic width or density descriptor.
     * @return  string              File path to the image.
     */
    private static function get_image_path( $image_url ) {

        // In case there is an intrinsic width or density descriptor.
        $image_pieces = explode( ' ', $image_url );
        $image_url    = $image_pieces[0];

        // In case installation is in a subdirectory.
        $image_url_path   = ltrim( parse_url( $image_url, PHP_URL_PATH ), '/' );
        $installation_dir = ltrim( parse_url( site_url( '/' ), PHP_URL_PATH ), '/' );
        $image_path       = str_replace( $installation_dir, '', ABSPATH ) . $image_url_path;

        return $image_path;
    }

    /**
     * Get the current WordPress filesystem instance.
     *
     * This will initialize the WordPress filesystem if it has not yet been and will
     * cache the result afterward.
     *
     * @since   1.7.0
     * @change  1.7.1
     *
     * @throws  \RuntimeException  If the WordPress filesystem could not be initialized.
     *
     * @global  WP_Filesystem_Base  $wp_filesystem  WordPress filesystem subclass.
     *
     * @return  WP_Filesystem_Base  WordPress filesystem.
     */
    public static function get_filesystem() {

        global $wp_filesystem;

        if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
            return $wp_filesystem;
        }

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
                        is_numeric( $wp_filesystem->errors->get_error_code() ) ? (int) $wp_filesystem->errors->get_error_code() : 0
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
     * Make a directory recursively based on the directory path.
     *
     * This assumes that the directory (and its parent) should have 755 permissions,
     * and will attempt to update any existing directories accordingly.
     *
     * @since   1.7.0
     * @change  1.7.2
     *
     * @param   string  $dir  Directory path to create.
     * @return  bool          True if the directory either already exists or was created *and* has the
     *                        correct permissions, false otherwise.
     */
    private static function mkdir_p( $dir ) {

        /**
         * Filters the mode assigned to directories on creation.
         *
         * @since   1.7.2
         * @change  1.7.2
         *
         * @param  int  $mode  Mode that defines the access permissions for the created directory. The mode
         *                     must be an octal number, which means it should have a leading zero. Default is 0755.
         */
        $mode_octal  = apply_filters( 'cache_enabler_mkdir_mode', 0755 );
        $mode_string = decoct( $mode_octal ); // Get the last three digits (e.g. '755').
        $parent_dir  = dirname( $dir );
        $fs          = self::get_filesystem();

        if ( $fs->is_dir( $dir ) && $fs->getchmod( $dir ) === $mode_string && $fs->getchmod( $parent_dir ) === $mode_string ) {
            return true;
        }

        if ( ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        if ( $fs->getchmod( $parent_dir ) !== $mode_string ) {
            return $fs->chmod( $parent_dir, $mode_octal, true );
        }

        if ( $fs->getchmod( $dir ) !== $mode_string ) {
            return $fs->chmod( $dir, $mode_octal );
        }

        return true;
    }

    /**
     * Remove an empty directory based on the directory path.
     *
     * This is a wrapper for rmdir() that can delete empty parent directories and will
     * call clearstatcache() when necessary. It suppresses errors on failure.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string         $dir      Directory path to remove.
     * @param   bool           $parents  (Optional) Whether empty parent directories should also be removed. Default false.
     * @return  array[]|bool             An array of removed directories with paths as the keys and objects as the
     *                                   values. There are no directory objects because a directory has to be empty to
     *                                   be removed, which is why it will always be an empty array. False if no
     *                                   directories were removed.
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
     * Set or unset the WP_CACHE constant in the wp-config.php file.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param  bool  $set  (Optional) True to set the WP_CACHE constant, false to unset. Default true.
     */
    private static function set_wp_cache_constant( $set = true ) {

        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            // The config file resides in ABSPATH.
            $wp_config_file = ABSPATH . 'wp-config.php';
        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            // The config file resides one level above ABSPATH but is not part of another installation.
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        } else {
            $wp_config_file = false;
        }

        if ( ! $wp_config_file || ! is_writable( $wp_config_file ) ) {
            return;
        }

        $wp_config_file_contents = file_get_contents( $wp_config_file );

        if ( ! is_string( $wp_config_file_contents ) ) {
            return;
        }

        $found_wp_cache_constant = preg_match( '/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,.+\);/', $wp_config_file_contents );

        if ( $set && ! $found_wp_cache_constant ) {
            $ce_wp_config_lines  = '/** Enables page caching for Cache Enabler. */' . PHP_EOL;
            $ce_wp_config_lines .= "if ( ! defined( 'WP_CACHE' ) ) {" . PHP_EOL;
            $ce_wp_config_lines .= "\tdefine( 'WP_CACHE', true );" . PHP_EOL;
            $ce_wp_config_lines .= '}' . PHP_EOL;
            $ce_wp_config_lines .= PHP_EOL;
            $wp_config_file_contents = preg_replace( '/(\/\*\* Sets up WordPress vars and included files\. \*\/)/', $ce_wp_config_lines . '$1', $wp_config_file_contents );
        }

        if ( ! $set ) {
            $wp_config_file_contents = preg_replace( '/.+Added by Cache Enabler\r\n/', '', $wp_config_file_contents ); // < 1.5.0
            $wp_config_file_contents = preg_replace( '/\/\*\* Enables page caching for Cache Enabler\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
        }

        file_put_contents( $wp_config_file, $wp_config_file_contents, LOCK_EX );
    }

    /**
     * Sort file and directory paths by the number of slashes.
     *
     * This sorts paths by the lowest amount of slashes to the highest.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   string  $a  File or directory path to compare in sort.
     * @param   string  $b  File or directory path to compare in sort.
     * @return  int         1 if $a has more slashes than $b, 0 if equal, and -1 if less.
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
     * Convert the page contents.
     *
     * This handles converting inline image URLs for the WebP cache.
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @param   string  $page_contents  Page contents from the cache engine as raw HTML.
     * @return  string                  Page contents after maybe being converted.
     */
    private static function converter( $page_contents ) {

        /**
         * Filters the HTML attributes to convert during the WebP conversion.
         *
         * @since   1.6.1
         * @change  1.6.1
         *
         * @param  string[]  $attributes  HTML attributes to convert during the WebP conversion. Default are 'src',
         *                                'srcset', and 'data-*'.
         */
        $attributes       = (array) apply_filters( 'cache_enabler_convert_webp_attributes', array( 'src', 'srcset', 'data-[^=]+' ) );
        $attributes_regex = implode( '|', $attributes );
        $image_urls_regex = '#(?:(?:(' . $attributes_regex . ')\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\?\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+[wx][^\"\'>]*)?(?=\/?[\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';

        /**
         * Filters whether inline image URLs with query strings should be ignored during the WebP conversion.
         *
         * @since   1.6.1
         * @change  1.6.1
         *
         * @param  bool  $ignore_query_strings  True if inline image URLs with query strings should be ignored during the WebP
         *                                      conversion, false if not. Default true.
         */
        if ( ! apply_filters( 'cache_enabler_convert_webp_ignore_query_strings', true ) ) {
            $image_urls_regex = '#(?:(?:(' . $attributes_regex . ')\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+[wx][^\"\'>]*)?(?=\/?[\?\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';
        }

        /**
         * Filters the page contents after the inline image URLs were maybe converted to WebP.
         *
         * @since   1.6.0
         * @change  1.6.0
         *
         * @param  string  $page_contents  Page contents from the cache engine as raw HTML.
         */
        $converted_page_contents = apply_filters( 'cache_enabler_page_contents_after_webp_conversion', preg_replace_callback( $image_urls_regex, 'self::convert_webp', $page_contents ) );
        $converted_page_contents = apply_filters_deprecated( 'cache_enabler_disk_webp_converted_data', array( $converted_page_contents ), '1.6.0', 'cache_enabler_page_contents_after_webp_conversion' );

        return $converted_page_contents;
    }

    /**
     * Convert image URL(s) to WebP.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string[]  $matches  Pattern matches from parsed page contents.
     * @return  string              The image URL(s) after maybe being converted to WebP.
     */
    private static function convert_webp( $matches ) {

        $full_match            = $matches[0];
        $image_extension_regex = '/(\.jpe?g|\.png)/i';
        $image_found           = preg_match( $image_extension_regex, $full_match );

        if ( $image_found ) {
            $image_urls = explode( ',', $full_match );

            foreach ( $image_urls as &$image_url ) {
                $image_url       = trim( $image_url, ' ' );
                $image_url_webp  = preg_replace( $image_extension_regex, '$1.webp', $image_url ); // Append .webp extension.
                $image_path_webp = self::get_image_path( $image_url_webp );

                if ( is_file( $image_path_webp ) ) {
                    $image_url = $image_url_webp;
                } else {
                    $image_url_webp  = preg_replace( $image_extension_regex, '', $image_url_webp ); // Remove default extension.
                    $image_path_webp = self::get_image_path( $image_url_webp );

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
     * Minify HTML.
     *
     * This removes HTML, CSS, and JavaScript comments. Whitespaces of any size are
     * replaced with a single space.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string  $html  Page contents from the cache engine as raw HTML.
     * @return  string         Page contents after maybe being minified.
     */
    private static function minify_html( $html ) {

        if ( strlen( $html ) > 700000 ) {
            return $html;
        }

        /**
         * Filters the HTML tags to ignore during HTML minification.
         *
         * @since   1.6.0
         * @change  1.6.0
         *
         * @param  string[]  $ignore_tags  The names of HTML tags to ignore. Default are 'textarea', 'pre', and 'code'.
         */
        $ignore_tags = (array) apply_filters( 'cache_enabler_minify_html_ignore_tags', array( 'textarea', 'pre', 'code' ) );
        $ignore_tags = (array) apply_filters_deprecated( 'cache_minify_ignore_tags', array( $ignore_tags ), '1.6.0', 'cache_enabler_minify_html_ignore_tags' );

        if ( ! Cache_Enabler_Engine::$settings['minify_inline_css_js'] ) {
            array_push( $ignore_tags, 'style', 'script' );
        }

        if ( ! $ignore_tags ) {
            return $html; // At least one HTML tag is required.
        }

        $ignore_tags_regex = implode( '|', $ignore_tags );

        // Remove HTML comments.
        $minified_html = preg_replace( '#<!--[^\[><].*?-->#s', '', $html );

        if ( Cache_Enabler_Engine::$settings['minify_inline_css_js'] ) {
            // Remove CSS and JavaScript comments.
            $minified_html = preg_replace(
                '#/\*(?!!)[\s\S]*?\*/|(?:^[ \t]*)//.*$|((?<!\()[ \t>;,{}[\]])//[^;\n]*$#m',
                '$1',
                $minified_html
            );
        }

        // Replace whitespaces of any size with a single space.
        $minified_html = preg_replace(
            '#(?>[^\S ]\s*|\s{2,})(?=[^<]*+(?:<(?!/?(?:' . $ignore_tags_regex . ')\b)[^<]*+)*+(?:<(?>' . $ignore_tags_regex . ')\b|\z))#ix',
            ' ',
            $minified_html
        );

        if ( strlen( $minified_html ) <= 1 ) {
            return $html; // HTML minification failed.
        }

        return $minified_html;
    }

    /**
     * Delete a settings file based on a given settings file path.
     *
     * This will try to remove the settings file directory and any of its empty parent
     * directories. It suppresses errors on failure.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param  string  (Optional) Path to the settings file. Default is the settings file for the
     *                 current site.
     */
    private static function delete_settings_file( $settings_file = null ) {

        if ( empty( $settings_file ) ) {
            $settings_file = self::get_settings_file();
        }

        if ( @unlink( $settings_file ) ) {
            self::rmdir( CACHE_ENABLER_SETTINGS_DIR, true );
        }
    }

    /**
     * Delete an asset (deprecated).
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
     * Validate the cache iterator arguments.
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   array  $args  Cache iterator arguments.
     * @return  array         Validated cache iterator arguments.
     */
    private static function validate_cache_iterator_args( $args ) {

        $validated_args = array();

        foreach ( $args as $arg_name => $arg_value ) {
            if ( $arg_name === 'root' ) {
                $validated_args[ $arg_name ] = (string) $arg_value;
            } elseif ( is_array( $arg_value ) ) {
                foreach ( $arg_value as $filter_type => $filter_value ) {
                    if ( is_string( $filter_value ) ) {
                        $filter_value = ( substr_count( $filter_value, '|' ) > 0 ) ? explode( '|', $filter_value ) : explode( ',', $filter_value );
                    } elseif ( ! is_array( $filter_value ) ) {
                        $filter_value = array(); // Not converting type to avoid unwanted values.
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
