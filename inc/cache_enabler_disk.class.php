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
     */

    public static $cache_dir = WP_CONTENT_DIR . '/cache/cache-enabler';


    /**
     * settings directory
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     */

    private static $settings_dir = WP_CONTENT_DIR . '/settings/cache-enabler';


    /**
     * base cache file names
     *
     * @since   1.0.7
     * @change  1.5.0
     *
     * @var     string
     */

    const CACHE_FILE_GLOB      = '*index*';
    const CACHE_FILE_HTML      = 'index.html';
    const CACHE_FILE_GZIP      = 'index.html.gz';
    const CACHE_FILE_WEBP_HTML = 'index-webp.html';
    const CACHE_FILE_WEBP_GZIP = 'index-webp.html.gz';


    /**
     * configure system files
     *
     * @since   1.5.0
     * @change  1.5.0
     */

    public static function setup() {

        // add advanced-cache.php drop-in
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );

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

        // delete settings file
        self::delete_settings_file();

        // check if settings directory exists
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
     * store cached page(s)
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $page_contents  content of a page from the output buffer
     */

    public static function cache_page( $page_contents ) {

        // check if page is empty
        if ( empty( $page_contents ) ) {
            return;
        }

        // create cached page(s)
        self::create_cache_files( $page_contents );
    }


    /**
     * check if cached page exists
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if cached page exists, false otherwise
     */

    public static function cache_exists() {

        return is_readable( self::cache_file_html() );
    }


    /**
     * check if cached page expired
     *
     * @since   1.0.1
     * @change  1.5.0
     *
     * @return  boolean  true if cached page expired, false otherwise
     */

    public static function cache_expired() {

        // check if cached pages are set to expire
        if ( ! Cache_Enabler_Engine::$settings['cache_expires'] || Cache_Enabler_Engine::$settings['cache_expiry_time'] === 0 ) {
            return false;
        }

        $now = time();
        $expires_seconds = HOUR_IN_SECONDS * Cache_Enabler_Engine::$settings['cache_expiry_time'];

        // check if cached page has expired
        if ( ( filemtime( self::cache_file_html() ) + $expires_seconds ) <= $now ) {
            return true;
        }

        return false;
    }


    /**
     * create signature
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  string  signature
     */

    private static function cache_signature() {

        return sprintf(
            '<!-- %s @ %s',
            'Cache Enabler by KeyCDN',
            date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) )
        );
    }


    /**
     * get cache size
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string   $dir   file system directory
     * @return  integer  $size  size in bytes
     */

    public static function cache_size( $dir = null ) {

        // set directory if provided, get directory otherwise
        $dir = ( $dir ) ? $dir : self::cache_file_dir_path( home_url() );

        // validate directory
        if ( ! is_dir( $dir ) ) {
            return;
        }

        // get directory data
        $dir_objects = self::get_dir_objects( $dir );

        // check if empty
        if ( empty( $dir_objects ) ) {
            return;
        }

        $size = 0;

        foreach ( $dir_objects as $dir_object ) {
            // get full path
            $dir_object = $dir . '/' . $dir_object;

            // check if directory
            if ( is_dir( $dir_object ) ) {
                $size += self::cache_size( $dir_object );
            // check if file otherwise
            } elseif ( is_file( $dir_object ) ) {
                $size += filesize( $dir_object );
            }
        }

        return $size;
    }


    /**
     * clear cached page(s)
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $clear_url   full URL to potentially cached page
     * @param   string  $clear_type  clear the `pagination` or the entire `dir` instead of only the cached `page`
     */

    public static function clear_cache( $clear_url = null, $clear_type = null ) {

        // check if complete cache should be cleared
        if ( empty( $clear_url ) || empty( $clear_type ) ) {
            self::clear_dir( self::$cache_dir );
            return;
        }

        // get directory
        $dir = self::cache_file_dir_path( $clear_url );

        // delete all cached variants in directory
        array_map( 'unlink', glob( $dir . self::CACHE_FILE_GLOB ) );

        // check if pagination needs to be cleared
        if ( $clear_type === 'pagination' ) {
            // get pagination base
            $pagination_base = $GLOBALS['wp_rewrite']->pagination_base;
            if ( strlen( $pagination_base ) > 0 ) {
                $pagination_dir = $dir . $pagination_base;
                // clear pagination page(s) cache
                self::clear_dir( $pagination_dir );
            }
        }

        // get directory data
        $dir_objects = self::get_dir_objects( $dir );

        // check if directory is now empty or if it needs to be cleared anyways
        if ( empty( $dir_objects ) || $clear_type === 'dir' ) {
            self::clear_dir( $dir );
        }
    }


    /**
     * clear directory
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $dir  directory
     */

    private static function clear_dir( $dir ) {

        // remove trailing slash
        $dir = untrailingslashit( $dir );

        // validate directory
        if ( ! is_dir( $dir ) ) {
            return;
        }

        // get directory data
        $dir_objects = self::get_dir_objects( $dir );

        // check if directory is empty
        if ( empty( $dir_objects ) ) {
            // delete empty directory
            @rmdir( $dir );

            // clear file status cache
            clearstatcache();

            // get parent directory
            $parent_dir = preg_replace( '/\/[^\/]+$/', '', $dir );

            // get parent directory data
            $parent_dir_objects = self::get_dir_objects( $parent_dir );

            // check if parent directory is also empty
            if ( empty( $parent_dir_objects ) ) {
                self::clear_dir( $parent_dir );
            }

            return;
        }

        foreach ( $dir_objects as $dir_object ) {
            // get full path
            $dir_object = $dir . '/' . $dir_object;

            // check if directory
            if ( is_dir( $dir_object ) ) {
                self::clear_dir( $dir_object );
            // check if file otherwise
            } elseif ( is_file( $dir_object ) ) {
                unlink( $dir_object );
            }
        }

        // delete directory
        @rmdir( $dir );

        // clear file status cache
        clearstatcache();
    }


    /**
     * create files for cache
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $page_contents  content of a page from the output buffer
     */

    private static function create_cache_files( $page_contents ) {

        // get base signature
        $cache_signature = self::cache_signature();

        // make directory if necessary
        if ( ! wp_mkdir_p( self::cache_file_dir_path() ) ) {
            wp_die( 'Unable to create directory.' );
        }

        // minify HTML
        $page_contents = self::minify_html( $page_contents );

        // create default file
        self::create_cache_file( self::cache_file_html(), $page_contents . $cache_signature . ' (' . self::cache_file_scheme() . ' html) -->' );

        // create pre-compressed file
        if ( Cache_Enabler_Engine::$settings['compress_cache'] ) {
            $compressed_page_contents = gzencode( $page_contents . $cache_signature . ' (' . self::cache_file_scheme() . ' gzip) -->', 9 );
            // validate compression
            if ( is_string( $compressed_page_contents ) ) {
                self::create_cache_file( self::cache_file_gzip(), $compressed_page_contents );
            }
        }

        // create WebP supported files
        if ( Cache_Enabler_Engine::$settings['convert_image_urls_to_webp'] ) {
            // magic regex rule
            $image_urls_regex = '#(?:(?:(src|srcset|data-[^=]+)\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\?\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+[wx][^\"\'>]*)?(?=\/?[\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';

            // call the WebP converter callback
            $converted_page_contents = apply_filters( 'cache_enabler_disk_webp_converted_data', preg_replace_callback( $image_urls_regex, 'self::convert_webp', $page_contents ) );

            // create default WebP file
            self::create_cache_file( self::cache_file_webp_html(), $converted_page_contents . $cache_signature . ' (' . self::cache_file_scheme() . ' webp html) -->' );

            // create pre-compressed file
            if ( Cache_Enabler_Engine::$settings['compress_cache'] ) {
                $compressed_converted_page_contents = gzencode( $converted_page_contents . $cache_signature . ' (' . self::cache_file_scheme() . ' webp gzip) -->', 9 );
                // validate compression
                if ( is_string( $compressed_converted_page_contents ) ) {
                    self::create_cache_file( self::cache_file_webp_gzip(), $compressed_converted_page_contents );
                }
            }
        }
    }


    /**
     * create file for cache
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $file_path      file path
     * @param   string  $page_contents  content of a page from the output buffer
     */

    private static function create_cache_file( $file_path, $page_contents ) {

        // write page contents from output buffer to file
        file_put_contents( $file_path, $page_contents );

        // clear file status cache
        clearstatcache();

        // set permissions
        $file_stats  = @stat( dirname( $file_path ) );
        $permissions = $file_stats['mode'] & 0007777;
        $permissions = $permissions & 0000666;
        @chmod( $file_path, $permissions );

        // clear file status cache
        clearstatcache();
    }


    /**
     * create settings file
     *
     * @since   1.2.3
     * @change  1.5.0
     *
     * @param   array  $settings  settings from database
     */

    public static function create_settings_file( $settings ) {

        // validate array
        if ( ! is_array( $settings ) ) {
            return;
        }

        // check settings file requirements
        if ( ! function_exists( 'home_url' ) ) {
            return;
        }

        // get settings file
        $settings_file = self::get_settings_file();

        // make directory if necessary
        if ( ! wp_mkdir_p( dirname( $settings_file ) ) ) {
            wp_die( 'Unable to create directory.' );
        }

        // create new settings file
        $new_settings_file_contents  = '<?php' . PHP_EOL;
        $new_settings_file_contents .= '/**' . PHP_EOL;
        $new_settings_file_contents .= ' * Cache Enabler settings for ' . home_url() . PHP_EOL;
        $new_settings_file_contents .= ' *' . PHP_EOL;
        $new_settings_file_contents .= ' * @since      1.5.0' . PHP_EOL;
        $new_settings_file_contents .= ' * @change     1.5.0' . PHP_EOL;
        $new_settings_file_contents .= ' *' . PHP_EOL;
        $new_settings_file_contents .= ' * @generated  ' . date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) ) . PHP_EOL;
        $new_settings_file_contents .= ' */' . PHP_EOL;
        $new_settings_file_contents .= PHP_EOL;
        $new_settings_file_contents .= 'return ' . var_export( $settings, true ) . ';';

        file_put_contents( $settings_file, $new_settings_file_contents );
    }


    /**
     * get cache file directory path
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   string  $url            full URL to potentially cached page
     * @return  string  $file_dir_path  file directory path to new or potentially cached page
     */

    private static function cache_file_dir_path( $url = null ) {

        $file_dir_path = sprintf(
            '%s/%s%s',
            self::$cache_dir,
            parse_url(
                ( $url ) ? $url : 'http://' . strtolower( $_SERVER['HTTP_HOST'] ),
                PHP_URL_HOST
            ),
            parse_url(
                ( $url ) ? $url : $_SERVER['REQUEST_URI'],
                PHP_URL_PATH
            )
        );

        if ( is_file( $file_dir_path ) ) {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404 );
            exit;
        }

        // add trailing slash
        $file_dir_path = rtrim( $file_dir_path, '/\\' ) . '/';

        return $file_dir_path;
    }


    /**
     * get cache file scheme
     *
     * @since   1.4.0
     * @change  1.5.0
     *
     * @return  string  https or http
     */

    private static function cache_file_scheme() {

        return ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || $_SERVER['SERVER_PORT'] == '443' ) ? 'https' : 'http';
    }


    /**
     * get complete cache file path (HTML)
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @return  string  file path to new or potentially cached page
     */

    private static function cache_file_html() {

        return self::cache_file_dir_path() . self::cache_file_scheme() . '-' . self::CACHE_FILE_HTML;
    }


    /**
     * get complete cache file path (Gzip)
     *
     * @since   1.0.1
     * @change  1.4.0
     *
     * @return  string  file path to new or potentially cached page
     */

    private static function cache_file_gzip() {

        return self::cache_file_dir_path() . self::cache_file_scheme() . '-' . self::CACHE_FILE_GZIP;
    }


    /**
     * get complete cache file path (WebP HTML)
     *
     * @since   1.0.7
     * @change  1.4.0
     *
     * @return  string  file path to new or potentially cached page
     */

    private static function cache_file_webp_html() {

        return self::cache_file_dir_path() . self::cache_file_scheme() . '-' . self::CACHE_FILE_WEBP_HTML;
    }


    /**
     * get complete cache file path (WebP Gzip)
     *
     * @since   1.0.1
     * @change  1.4.0
     *
     * @return  string  file path to new or potentially cached page
     */

    private static function cache_file_webp_gzip() {

        return self::cache_file_dir_path() . self::cache_file_scheme() . '-' . self::CACHE_FILE_WEBP_GZIP;
    }


    /**
     * get cached page
     *
     * @since   1.0.0
     * @change  1.5.0
     */

    public static function get_cache() {

        // set X-Cache-Handler response header
        header( 'X-Cache-Handler: cache-enabler-engine' );

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
        if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( self::cache_file_html() ) ) ) {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
            exit;
        }

        // check webp and deliver gzip webp file if support
        if ( $http_accept && ( strpos( $http_accept, 'webp' ) !== false ) ) {
            if ( is_readable( self::cache_file_webp_gzip() ) ) {
                header( 'Content-Encoding: gzip' );
                return self::cache_file_webp_gzip();
            } elseif ( is_readable( self::cache_file_webp_html() ) ) {
                return self::cache_file_webp_html();
            }
        }

        // check encoding and deliver gzip file if support
        if ( $http_accept_encoding && ( strpos( $http_accept_encoding, 'gzip' ) !== false ) && is_readable( self::cache_file_gzip() )  ) {
            header( 'Content-Encoding: gzip' );
            return self::cache_file_gzip();
        }

        // get default cached file
        return self::cache_file_html();
    }


    /**
     * get settings file
     *
     * @since   1.4.0
     * @change  1.5.0
     *
     * @param   boolean  $fallback       whether or not to provide fallback settings file path
     * @return  string   $settings_file  settings file path
     */

    private static function get_settings_file( $fallback = false ) {

        // network with subdirectory configuration
        if ( ! $fallback && is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
            if ( function_exists( 'home_url' ) ) {
                $url_path = parse_url( home_url( '/' ), PHP_URL_PATH ); // trailing slash required
            } else {
                $url_path = $_SERVER['REQUEST_URI'];
            }

            $url_path_pieces = explode( '/', $url_path, 3 );
            $blog_path = $url_path_pieces[1];

            // check if blog path is empty
            if ( ! empty( $blog_path ) ) {
                $blog_path = '.' . $blog_path;
            }
        // single site, network subdirectory main site, any network subdomain site, or fallback
        } else {
            $blog_path = '';
        }

        // get settings file
        $settings_file = sprintf(
            '%s/%s.php',
            self::$settings_dir,
            parse_url( ( function_exists( 'home_url' ) ) ? home_url() : 'http://' . strtolower( $_SERVER['HTTP_HOST'] ), PHP_URL_HOST ) . $blog_path
        );

        return $settings_file;
    }


    /**
     * get settings from settings file
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @return  array  $settings  current settings from settings file
     */

    public static function get_settings() {

        // get settings file
        $settings_file = self::get_settings_file();

        // include existing settings file
        if ( file_exists( $settings_file ) ) {
            $settings = include_once $settings_file;
        // if settings file does not exist try to get fallback settings file when network with subdirectory configuration
        } elseif ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
            $fallback = true;
            $fallback_settings_file = self::get_settings_file( $fallback );
            // include existing fallback settings file
            if ( file_exists( $fallback_settings_file ) ) {
                $settings = include_once $fallback_settings_file;
            }
        } else {
            $settings = array();
        }

        return $settings;
    }


    /**
     * get directory file system objects
     *
     * @since   1.4.7
     * @change  1.5.0
     *
     * @param   string  $dir          directory path
     * @return  array   $dir_objects  directory objects
     */

    private static function get_dir_objects( $dir ) {

        // scan directory
        $dir_data = @scandir( $dir );

        if ( is_array( $dir_data ) ) {
            $dir_objects = array_diff( $dir_data, array( '..', '.' ) );
            return $dir_objects;
        }
    }


    /**
     * set or unset WP_CACHE constant in wp-config.php
     *
     * @since   1.1.1
     * @change  1.5.0
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
        }

        // check if config file can be written to
        if ( ! is_writable( $wp_config_file ) ) {
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
        file_put_contents( $wp_config_file, $wp_config_file_contents );
    }


    /**
     * get image path
     *
     * @since   1.4.8
     * @change  1.5.0
     *
     * @param   string  $image_url   full or relative URL with or without intrinsic width or density descriptor
     * @return  string  $image_path  path to image
     */

    private static function image_path( $image_url ) {

        // in case image has intrinsic width or density descriptor
        $image_parts = explode( ' ', $image_url );
        $image_url = $image_parts[0];

        // in case installation is in a subdirectory
        $image_url_path = ltrim( parse_url( $image_url, PHP_URL_PATH ), '/' );
        $installation_dir = preg_replace( '/^[^\/]+\/\K.+/', '', $image_url_path );
        $image_path = str_replace( $installation_dir, '', ABSPATH ) . $image_url_path;

        return $image_path;
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
                // remove spaces if there are any
                $image_url = trim( $image_url, ' ' );
                // append .webp extension
                $image_url_webp = preg_replace( $image_extension_regex, '$1.webp', $image_url );
                // get WebP image path
                $image_path_webp = self::image_path( $image_url_webp );

                // check if WebP image exists
                if ( is_file( $image_path_webp ) ) {
                    $image_url = $image_url_webp;
                } else {
                    // remove default extension
                    $image_url_webp = preg_replace( $image_extension_regex, '', $image_url_webp );
                    // get WebP image path
                    $image_path_webp = self::image_path( $image_url_webp );

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
     * @change  1.5.0
     *
     * @param   string  $page_contents                 content of a page from the output buffer
     * @return  string  $minified_html|$page_contents  minified page contents if applicable, unchanged otherwise
     *
     * @hook    array   cache_minify_ignore_tags
     */

    private static function minify_html( $page_contents ) {

        // check if disabled
        if ( ! Cache_Enabler_Engine::$settings['minify_html'] ) {
            return $page_contents;
        }

        // HTML character limit
        if ( strlen( $page_contents ) > 700000) {
            return $page_contents;
        }

        // HTML tags to ignore
        $ignore_tags = (array) apply_filters( 'cache_minify_ignore_tags', array( 'textarea', 'pre', 'code' ) );

        // if selected exclude inline CSS and JavaScript
        if ( ! Cache_Enabler_Engine::$settings['minify_inline_css_js'] ) {
            array_push( $ignore_tags, 'style', 'script' );
        }

        // check if there are ignore tags
        if ( ! $ignore_tags ) {
            return $page_contents;
        }

        // stringify
        $ignore_regex = implode( '|', $ignore_tags );

        // regex minification
        $minified_html = preg_replace(
            array(
                '/<!--[^\[><](.*?)-->/s',
                '#(?ix)(?>[^\S ]\s*|\s{2,})(?=(?:(?:[^<]++|<(?!/?(?:' . $ignore_regex . ')\b))*+)(?:<(?>' . $ignore_regex . ')\b|\z))#',
            ),
            array(
                '',
                ' ',
            ),
            $page_contents
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
     * @change  1.5.0
     */

    private static function delete_settings_file() {

        // get settings file
        $settings_file = self::get_settings_file();

        // delete settings file
        @unlink( $settings_file );

        // delete settings directory if empty
        @rmdir( self::$settings_dir );
    }


    /**
     * delete asset (deprecated)
     *
     * @deprecated  1.5.0
     */

    public static function delete_asset( $url ) {

        if ( empty( $url ) ) {
            wp_die( 'URL is empty.' );
        }

        self::clear_dir( self::cache_file_dir_path( $url ) );
    }
}
