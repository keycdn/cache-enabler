<?php


// exit
defined( 'ABSPATH' ) || exit;


/**
 * Cache_Enabler_Disk
 *
 * @since  1.0.0
 */

final class Cache_Enabler_Disk {


    /**
     * cached filename settings
     *
     * @since   1.0.7
     * @change  1.4.0
     *
     * @var     string
     */

    const FILE_GLOB      = '*index*';
    const FILE_HTML      = 'index.html';
    const FILE_GZIP      = 'index.html.gz';
    const FILE_WEBP_HTML = 'index-webp.html';
    const FILE_WEBP_GZIP = 'index-webp.html.gz';


    /**
     * permalink check
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if installed
     */

    public static function is_permalink() {

        return get_option( 'permalink_structure' );
    }


    /**
     * store asset
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  content of the asset
     */

    public static function store_asset( $data ) {

        // check if empty
        if ( empty( $data ) ) {
            wp_die( 'Asset is empty.' );
        }

        // save asset
        self::_create_files( $data );
    }


    /**
     * check asset
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if asset exists
     */

    public static function check_asset() {

        return is_readable( self::_file_html() );
    }


    /**
     * check expiry
     *
     * @since   1.0.1
     * @change  1.0.1
     *
     * @return  boolean  true if asset expired
     */

    public static function check_expiry() {

        // get Cache Enabler options
        $options = Cache_Enabler::$options;

        // check if an expiry time is set
        if ( $options['expires'] === 0) {
            return false;
        }

        $now = time();
        $expires_seconds = 3600 * $options['expires'];

        // check if asset has expired
        if ( ( filemtime( self::_file_html() ) + $expires_seconds ) <= $now ) {
            return true;
        }

        return false;
    }


    /**
     * delete asset
     *
     * @since   1.0.0
     * @change  1.4.7
     *
     * @param   string  $clear_url   full or relative URL of a page
     * @param   string  $clear_type  if `dir` clear the entire directory
     */

    public static function delete_asset( $clear_url, $clear_type ) {

        // get directory
        $dir = self::_file_path( $clear_url );

        // delete all cached variants in directory
        array_map( 'unlink', glob( $dir . self::FILE_GLOB ) );

        // get directory data
        $objects = self::_get_dir( $dir );

        // check if directory is now empty or if it needs to be cleared anyways
        if ( empty( $objects ) || $clear_type === 'dir' ) {
            self::_clear_dir( $dir );
        }
    }


    /**
     * clear cache
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function clear_cache() {

        // clear complete cache
        self::_clear_dir( CE_CACHE_DIR );
    }


    /**
     * get asset
     *
     * @since   1.0.0
     * @change  1.0.9
     */

    public static function get_asset() {

        // set X-Cache-Handler response header
        header( 'X-Cache-Handler: php' );

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
        if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( self::_file_html() ) ) ) {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
            exit;
        }

        // check webp and deliver gzip webp file if support
        if ( $http_accept && ( strpos( $http_accept, 'webp' ) !== false ) ) {
            if ( is_readable( self::_file_webp_gzip() ) ) {
                header( 'Content-Encoding: gzip' );
                readfile( self::_file_webp_gzip() );
                exit;
            } elseif ( is_readable( self::_file_webp_html() ) ) {
                readfile( self::_file_webp_html() );
                exit;
            }
        }

        // check encoding and deliver gzip file if support
        if ( $http_accept_encoding && ( strpos( $http_accept_encoding, 'gzip' ) !== false ) && is_readable( self::_file_gzip() )  ) {
            header( 'Content-Encoding: gzip' );
            readfile( self::_file_gzip() );
            exit;
        }

        // deliver cached file (default)
        readfile( self::_file_html() );
        exit;
    }


    /**
     * create signature
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  string  signature
     */

    private static function _cache_signature() {

        return sprintf(
            "\n\n<!-- %s @ %s",
            'Cache Enabler by KeyCDN',
            date_i18n(
                'd.m.Y H:i:s',
                current_time( 'timestamp' )
            )
        );
    }


    /**
     * create files
     *
     * @since   1.0.0
     * @change  1.4.8
     *
     * @param   string  $data  HTML content
     */

    private static function _create_files( $data ) {

        // get Cache Enabler options
        $options = Cache_Enabler::$options;

        // get base signature
        $cache_signature = self::_cache_signature();

        // create folder
        if ( ! wp_mkdir_p( self::_file_path() ) ) {
            wp_die( 'Unable to create directory.' );
        }

        // create files
        self::_create_file( self::_file_html(), $data . $cache_signature . ' (' . self::_file_scheme() . ' html) -->' );

        // create pre-compressed file
        if ( $options['compress'] ) {
            self::_create_file( self::_file_gzip(), gzencode( $data . $cache_signature . ' (' . self::_file_scheme() . ' gzip) -->', 9) );
        }

        // create webp supported files
        if ( $options['webp'] ) {
            // magic regex rule
            $regex_rule = '#(?:(?:(src|srcset|data-[^=]+)\s*=|(url)\()\s*[\'\"]?\s*)\K(?:[^\?\"\'\s>]+)(?:\.jpe?g|\.png)(?:\s\d+w[^\"\'>]*)?(?=\/?[\"\'\s\)>])(?=[^<{]*(?:\)[^<{]*\}|>))#i';

            // call the webp converter callback
            $converted_data = apply_filters( 'cache_enabler_disk_webp_converted_data', preg_replace_callback( $regex_rule, 'self::_convert_webp', $data ) );

            self::_create_file( self::_file_webp_html(), $converted_data . $cache_signature . ' (' . self::_file_scheme() . ' webp html) -->' );

            // create pre-compressed file
            if ( $options['compress'] ) {
                self::_create_file( self::_file_webp_gzip(), gzencode( $converted_data . $cache_signature . ' (' . self::_file_scheme() . ' webp gzip) -->', 9) );
            }
        }
    }


    /**
     * create file
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $file  file path
     * @param   string  $data  content of the HTML
     */

    private static function _create_file( $file, $data ) {

        // open file handler
        if ( ! $handle = @fopen( $file, 'wb' ) ) {
            wp_die( 'Cannot write to file.' );
        }

        // write
        @fwrite( $handle, $data );
        fclose( $handle );
        clearstatcache();

        // set permissions
        $stat  = @stat( dirname( $file ) );
        $perms = $stat['mode'] & 0007777;
        $perms = $perms & 0000666;
        @chmod( $file, $perms );
        clearstatcache();
    }


    /**
     * clear directory
     *
     * @since   1.0.0
     * @change  1.4.7
     *
     * @param   string  $dir  directory
     */

    private static function _clear_dir( $dir ) {

        // remove slashes
        $dir = untrailingslashit( $dir );

        // check if directory
        if ( ! is_dir( $dir ) ) {
            return;
        }

        // get directory data
        $objects = self::_get_dir( $dir );

        // check if directory is empty
        if ( empty( $objects ) ) {
            // delete empty directory
            @rmdir( $dir );

            // get parent directory
            $parent_dir = preg_replace( '/\/[^\/]+$/', '', $dir );

            // get parent directory data
            $parent_objects = self::_get_dir( $parent_dir );

            // check if parent directory is also empty
            if ( empty( $parent_objects ) ) {
                self::_clear_dir( $parent_dir );
            }

            return;
        }

        foreach ( $objects as $object ) {
            // full path
            $object = $dir . DIRECTORY_SEPARATOR . $object;

            // check if directory
            if ( is_dir( $object ) ) {
                self::_clear_dir( $object );
            } else {
                @unlink( $object );
            }
        }

        // delete directory
        @rmdir( $dir );

        // clears file status cache
        clearstatcache();
    }


    /**
     * get cache size
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $dir   folder path
     * @return  mixed   $size  size in bytes
     */

    public static function cache_size( $dir = '.' ) {

        // check if directory
        if ( ! is_dir( $dir ) ) {
            return;
        }

        // get directory data
        $objects = self::_get_dir( $dir );

        // check if empty
        if ( empty( $objects ) ) {
            return;
        }

        $size = 0;

        foreach ( $objects as $object ) {
            // full path
            $object = $dir . DIRECTORY_SEPARATOR . $object;

            // check if directory
            if ( is_dir( $object ) ) {
                $size += self::cache_size( $object );
            } else {
                $size += filesize( $object );
            }
        }

        return $size;
    }


    /**
     * get cached file path
     *
     * @since   1.0.0
     * @change  1.4.8
     *
     * @param   string  $url  full URL of a cached page
     * @return  string        path to cached file
     */

    private static function _file_path( $url = null ) {

        $file_path = sprintf(
            '%s%s%s%s',
            CE_CACHE_DIR,
            DIRECTORY_SEPARATOR,
            parse_url(
                ( $url ) ? $url : 'http://' . strtolower( $_SERVER['HTTP_HOST'] ),
                PHP_URL_HOST
            ),
            parse_url(
                ( $url ) ? $url : $_SERVER['REQUEST_URI'],
                PHP_URL_PATH
            )
        );

        if ( is_file( $file_path ) ) {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404 );
            exit;
        }

        return trailingslashit( $file_path );
    }


    /**
     * get file scheme
     *
     * @since   1.4.0
     * @change  1.4.7
     *
     * @return  string  https or http
     */

    private static function _file_scheme() {

        return ( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || $_SERVER['SERVER_PORT'] === '443' ) ? 'https' : 'http';
    }


    /**
     * get file path
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @return  string  path to the HTML file
     */

    private static function _file_html() {

        return self::_file_path() . self::_file_scheme() . '-' . self::FILE_HTML;
    }


    /**
     * get gzip file path
     *
     * @since   1.0.1
     * @change  1.4.0
     *
     * @return  string  path to the gzipped HTML file
     */

    private static function _file_gzip() {

        return self::_file_path() . self::_file_scheme() . '-' . self::FILE_GZIP;
    }


    /**
     * get webp file path
     *
     * @since   1.0.7
     * @change  1.4.0
     *
     * @return  string  path to the webp HTML file
     */

    private static function _file_webp_html() {

        return self::_file_path() . self::_file_scheme() . '-' . self::FILE_WEBP_HTML;
    }


    /**
     * get gzip webp file path
     *
     * @since   1.0.1
     * @change  1.4.0
     *
     * @return  string  path to the webp gzipped HTML file
     */

    private static function _file_webp_gzip() {

        return self::_file_path() . self::_file_scheme() . '-' . self::FILE_WEBP_GZIP;
    }


    /**
     * get settings file
     *
     * @since   1.4.0
     * @change  1.4.8
     *
     * @return  string  settings file path
     */

    private static function _get_settings() {

        // network with subdirectory configuration
        if ( is_multisite() && ! is_subdomain_install() ) {
            // get blog path
            $blog_path = trim( get_blog_details()->path, '/' );
            // check if subsite
            if ( ! empty( $blog_path ) ) {
                $blog_path = '-' . $blog_path;
            }
        // single site, network subdirectory main site, or any network subdomain site
        } else {
            $blog_path = '';
        }

        // get settings file
        $settings_file = sprintf(
            '%s-%s%s.json',
            WP_CONTENT_DIR . '/plugins/cache-enabler/settings/cache-enabler-advcache',
            Cache_Enabler::get_blog_domain(),
            $blog_path
        );

        return $settings_file;
    }


    /**
     * get directory data
     *
     * @since   1.4.7
     * @change  1.4.7
     *
     * @param   string  $dir      directory path
     * @return  array   $objects  directory objects
     */

    private static function _get_dir( $dir ) {

        // scan directory
        $data_dir = @scandir( $dir );

        if ( is_array( $data_dir ) ) {
            $objects = array_diff( $data_dir, array( '..', '.' ) );
            return $objects;
        }
    }


    /**
     * read settings file
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @param   string  $settings_file  settings file path
     * @return  array                   settings or empty
     */

    private static function _read_settings( $settings_file ) {

        // check if settings file exists
        if ( ! file_exists( $settings_file ) ) {
            return array();
        }

        // check if any errors occur when reading the settings file
        if ( ! $settings = json_decode( file_get_contents( $settings_file ), true ) ) {
            return array();
        }

        return $settings;
    }


    /**
     * write settings file
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @param   string  $settings_file  settings file path
     * @param   array   $settings       settings
     */

    private static function _write_settings( $settings_file, $settings ) {

        file_put_contents( $settings_file, wp_json_encode( $settings ) );
    }


    /**
     * record settings for advanced-cache.php
     *
     * @since   1.2.3
     * @change  1.4.0
     *
     * @param   array    settings as array pairs
     * @return  boolean  true if successful
     */

    public static function record_advcache_settings( $settings ) {

        // get settings file
        $settings_file = self::_get_settings();

        // create folder if neccessary
        if ( ! wp_mkdir_p( dirname( $settings_file ) ) ) {
            wp_die( 'Unable to create directory.' );
        }

        // merge with old settings
        $settings = array_merge( self::_read_settings( $settings_file ), $settings );

        // update settings file
        self::_write_settings( $settings_file, $settings );

        return true;
    }


    /**
     * delete settings for advanced-cache.php
     *
     * @since   1.2.3
     * @change  1.4.0
     *
     * @param   array    settings keys as array or empty for delete all
     * @return  boolean  true if successful
     */

    public static function delete_advcache_settings( $settings_keys = array() ) {

        // get settings file
        $settings_file = self::_get_settings();

        // check if settings file exists
        if ( ! file_exists( $settings_file ) ) {
            return true;
        }

        $settings = self::_read_settings( $settings_file );
        foreach ( $settings_keys as $key ) {
            if ( array_key_exists( $key, $settings ) ) {
                unset( $settings[ $key ] );
            }
        }

        if ( empty( $settings ) || empty( $settings_keys ) ) {
            unlink( $settings_file );
            return true;
        }

        // update settings file
        self::_write_settings( $settings_file, $settings );

        return true;
    }


    /**
     * get image path
     *
     * @since   1.4.8
     * @change  1.4.8
     *
     * @param   string  $image_url   full or relative URL with or without intrinsic width
     * @return  string  $image_path  path to image
     */

    private static function _image_path( $image_url ) {

        // in case image has intrinsic width
        $image_parts = explode( ' ', $image_url );
        $image_url = $image_parts[0];
        $image_path = ABSPATH . ltrim( parse_url( $image_url, PHP_URL_PATH ), '/' );

        return $image_path;
    }


    /**
     * convert image URL to WebP
     *
     * @since   1.0.1
     * @change  1.4.8
     *
     * @param   array   $matches      pattern matches from parsed HTML file
     * @return  string  $conversion   converted image URL(s) to WebP if applicable, default URL(s) otherwise
     */

    private static function _convert_webp( $matches ) {

        $full_match = strtolower( $matches[0] );
        $image_count = substr_count( $full_match, '.png' ) + substr_count( $full_match, '.jpg' ) + substr_count( $full_match, '.jpeg' );

        if ( $image_count > 0 ) {
            $image_urls = explode( ',', $full_match );
            foreach ( $image_urls as &$image_url ) {
                // remove spaces if there are any
                $image_url = trim( $image_url, ' ' );
                // append .webp extension
                $image_url_webp = preg_replace( '/(\.jpe?g|\.png)/', '$1.webp', $image_url );
                // get WebP image path
                $image_path_webp = self::_image_path( $image_url_webp );

                // check if WebP image exists
                if ( is_file( $image_path_webp ) ) {
                    $image_url = $image_url_webp;
                } else {
                    // remove default extension
                    $image_url_webp = preg_replace( '/(\.jpe?g|\.png)/', '', $image_url_webp );
                    // get WebP image path
                    $image_path_webp = self::_image_path( $image_url_webp );

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
}
