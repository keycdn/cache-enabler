<?php


// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler_Disk
 *
 * @since 1.0.0
 */

final class Cache_Enabler_Disk {


    /**
     * cached filename settings
     *
     * @since  1.0.7
     * @change 1.0.7
     *
     * @var    string
     */

    const FILE_HTML = 'index.html';
    const FILE_GZIP = 'index.html.gz';
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
        return get_option('permalink_structure');
    }


    /**
     * store asset
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string   $data    content of the asset
     */

    public static function store_asset($data) {

        // check if empty
        if ( empty($data) ) {
            wp_die('Asset is empty.');
        }

        // save asset
        self::_create_files(
            $data
        );

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
        return is_readable(
            self::_file_html()
        );
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

        // cache enabler options
        $options = Cache_Enabler::$options;

        // check if expires is active
        if ($options['expires'] == 0) {
            return false;
        }

        $now = time();
        $expires_seconds = 3600*$options['expires'];

        // check if asset has expired
        if ( ( filemtime(self::_file_html()) + $expires_seconds ) <= $now ) {
            return true;
        }

        return false;

    }


    /**
     * delete asset
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string   $url   url of cached asset
     */

    public static function delete_asset($url) {

        // check if url empty
        if ( empty($url) ) {
            wp_die('URL is empty.');
        }

        // delete
        self::_clear_dir(
            self::_file_path($url)
        );
    }


    /**
     * clear cache
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function clear_cache() {
        self::_clear_dir(
            CE_CACHE_DIR
        );
    }


    /**
     * clear home cache
     *
     * @since   1.0.7
     * @change  1.0.9
     */

    public static function clear_home() {
        $path = sprintf(
            '%s%s%s%s',
            CE_CACHE_DIR,
            DIRECTORY_SEPARATOR,
            preg_replace('#^https?://#', '', get_option('siteurl')),
            DIRECTORY_SEPARATOR
        );

        @unlink($path.self::FILE_HTML);
        @unlink($path.self::FILE_GZIP);
        @unlink($path.self::FILE_WEBP_HTML);
        @unlink($path.self::FILE_WEBP_GZIP);
    }


    /**
     * get asset
     *
     * @since   1.0.0
     * @change  1.0.9
     */

    public static function get_asset() {

        // set cache handler header
        header('x-cache-handler: php');

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
        if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) >= filemtime( self::_file_html() ) ) ) {
            header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
            exit;
        }

        // check webp and deliver gzip webp file if support
        if ( $http_accept && ( strpos($http_accept, 'webp') !== false ) ) {
            if ( is_readable( self::_file_webp_gzip() ) ) {
                header('Content-Encoding: gzip');
                readfile( self::_file_webp_gzip() );
                exit;
            } elseif ( is_readable( self::_file_webp_html() ) ) {
                readfile( self::_file_webp_html() );
                exit;
            }
        }

        // check encoding and deliver gzip file if support
        if ( $http_accept_encoding && ( strpos($http_accept_encoding, 'gzip') !== false ) && is_readable( self::_file_gzip() )  ) {
            header('Content-Encoding: gzip');
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

    private static function _cache_signatur() {
        return sprintf(
            "\n\n<!-- %s @ %s",
            'Cache Enabler by KeyCDN',
            date_i18n(
                'd.m.Y H:i:s',
                current_time('timestamp')
            )
        );
    }


    /**
     * create files
     *
     * @since   1.0.0
     * @change  1.1.1
     *
     * @param   string  $data  html content
     */

    private static function _create_files($data) {

        // create folder
        if ( ! wp_mkdir_p( self::_file_path() ) ) {
            wp_die('Unable to create directory.');
        }

        // get base signature
        $cache_signature = self::_cache_signatur();

        // cache enabler options
        $options = Cache_Enabler::$options;

        // create files
        self::_create_file( self::_file_html(), $data.$cache_signature." (html) -->" );

        // create pre-compressed file
        if ($options['compress']) {
            self::_create_file( self::_file_gzip(), gzencode($data.$cache_signature." (html gzip) -->", 9) );
        }

        // create webp supported files
        if ($options['webp']) {
            // magic regex rule
            $regex_rule = '#(?<=(?:(ref|src|set)=[\"\']))(?:http[s]?[^\"\']+)(\.png|\.jp[e]?g)(?:[^\"\']+)?(?=[\"\')])#';

            // call the webp converter callback
            $converted_data = preg_replace_callback($regex_rule,'self::_convert_webp',$data);

            self::_create_file( self::_file_webp_html(), $converted_data.$cache_signature." (webp) -->" );

            // create pre-compressed file
            if ($options['compress']) {
                self::_create_file( self::_file_webp_gzip(), gzencode($converted_data.$cache_signature." (webp gzip) -->", 9) );
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
     * @param   string  $data  content of the html
     */

    private static function _create_file($file, $data) {

        // open file handler
        if ( ! $handle = @fopen($file, 'wb') ) {
            wp_die('Can not write to file.');
        }

        // write
        @fwrite($handle, $data);
        fclose($handle);
        clearstatcache();

        // set permissions
        $stat = @stat( dirname($file) );
        $perms = $stat['mode'] & 0007777;
        $perms = $perms & 0000666;
        @chmod($file, $perms);
        clearstatcache();
    }


    /**
     * clear directory
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $dir  directory
     */

    private static function _clear_dir($dir) {

        // remove slashes
        $dir = untrailingslashit($dir);

        // check if dir
        if ( ! is_dir($dir) ) {
            return;
        }

        // get dir data
        $objects = array_diff(
            scandir($dir),
            array('..', '.')
        );

        if ( empty($objects) ) {
            return;
        }

        foreach ( $objects as $object ) {
            // full path
            $object = $dir. DIRECTORY_SEPARATOR .$object;

            // check if directory
            if ( is_dir($object) ) {
                self::_clear_dir($object);
            } else {
                unlink($object);
            }
        }

        // delete
        @rmdir($dir);

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

    public static function cache_size($dir = '.') {

        // check if not dir
        if ( ! is_dir($dir) ) {
            return;
        }

        // get dir data
        $objects = array_diff(
            scandir($dir),
            array('..', '.')
        );

        if ( empty($objects) ) {
            return;
        }

        $size = 0;

        foreach ( $objects as $object ) {
            // full path
            $object = $dir. DIRECTORY_SEPARATOR .$object;

            // check if dir
            if ( is_dir($object) ) {
                $size += self::cache_size($object);
            } else {
                $size += filesize($object);
            }
        }

        return $size;
    }


    /**
     * cache path
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @param   string  $path  uri or permlink
     * @return  string  $diff  path to cached asset
     */

    private static function _file_path($path = NULL) {

        $path = sprintf(
            '%s%s%s%s',
            CE_CACHE_DIR,
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
            wp_die('Path is not valid.');
        }

        return trailingslashit($path);
    }


    /**
     * get file path
     *
     * @since   1.0.0
     * @change  1.0.7
     *
     * @return  string  path to the html file
     */

    private static function _file_html() {
        return self::_file_path(). self::FILE_HTML;
    }


    /**
     * get gzip file path
     *
     * @since   1.0.1
     * @change  1.0.7
     *
     * @return  string  path to the gzipped html file
     */

    private static function _file_gzip() {
        return self::_file_path(). self::FILE_GZIP;
    }


    /**
     * get webp file path
     *
     * @since   1.0.7
     * @change  1.0.7
     *
     * @return  string  path to the webp html file
     */

    private static function _file_webp_html() {
        return self::_file_path(). self::FILE_WEBP_HTML;
    }


    /**
     * get gzip webp file path
     *
     * @since   1.0.1
     * @change  1.0.7
     *
     * @return  string  path to the webp gzipped html file
     */

    private static function _file_webp_gzip() {
        return self::_file_path(). self::FILE_WEBP_GZIP;
    }


    /**
     * read settings file
     *
     * @since 1.2.3
     *
     * @return array  settings or emtpy
     */

    private static function _read_settings($settings_file) {
        if (! file_exists($settings_file) ) {
            return [];
        }

        if ( ! $settings = json_decode(file_get_contents($settings_file), true) ) {
            // if there is an error reading our settings
            return [];
        }

        return $settings;
    }


    /**
     * write settings file
     *
     * @since 1.2.3
     *
     * @return void
     */

    private static function _write_settings($settings_file, $settings) {
        file_put_contents( $settings_file, wp_json_encode($settings) );
    }


    /**
     * record settings for advanced-cache.php
     *
     * @since   1.2.3
     *
     * @param   array    settings as array pairs
     * @return  boolean  true if successful
     */

    public static function record_advcache_settings($settings) {
        $settings_file = sprintf('%s-%s%s.json',
            WP_CONTENT_DIR. "/cache/cache-enabler-advcache",
            parse_url(
                'http://' .strtolower($_SERVER['HTTP_HOST']),
                PHP_URL_HOST
            ),
            is_multisite() ? '-'. get_current_blog_id() : ''
        );

        // create folder if neccessary
        if ( ! wp_mkdir_p(dirname($settings_file)) ) {
            wp_die('Unable to create directory.');
        }

        // merge with old settings
        $settings = array_merge(self::_read_settings($settings_file), $settings);

        // update settings file
        self::_write_settings($settings_file, $settings);

        return true;
    }


    /**
     * delete settings for advanced-cache.php
     *
     * @since   1.2.3
     *
     * @param   array    settings as array or empty for delete all
     * @return  boolean  true if successful
     */

    public static function delete_advcache_settings($remsettings = array()) {
        $settings_file = sprintf('%s-%s%s.json',
            WP_CONTENT_DIR. "/cache/cache-enabler-advcache",
            parse_url(
                'http://' .strtolower($_SERVER['HTTP_HOST']),
                PHP_URL_HOST
            ),
            is_multisite() ? '-'. get_current_blog_id() : ''
        );

        if ( ! file_exists($settings_file) or empty($remsettings)) {
            return true;
        }

        $settings = self::_read_settings($settings_file);
        foreach ($remsettings as $key) {
            if ( array_key_exists($key, $settings) ) {
                unset($settings[$key]);
            }
        }

        if (empty($settings)) {
            unlink($settings_file);
            return true;
        }

        // update settings file
        self::_write_settings($settings_file, $settings);

        return true;
    }


    /**
     * convert to webp
     *
     * @since   1.0.1
     * @change  1.1.1
     *
     * @return  string  converted HTML file
     */

    private static function _convert_webp($asset) {

        if ($asset[1] == 'src') {
            return self::_convert_webp_src($asset[0]);
        } elseif ($asset[1] == 'ref') {
            return self::_convert_webp_src($asset[0]);
        } elseif ($asset[1] == 'set') {
            return self::_convert_webp_srcset($asset[0]);
        }

        return $asset[0];

    }


    /**
     * convert src to webp source
     *
     * @since   1.0.1
     * @change  1.1.0
     *
     * @return  string  converted src webp source
     */

    private static function _convert_webp_src($src) {
        $upload_dir = wp_upload_dir();
        $src_url = parse_url($upload_dir['baseurl']);
        $upload_path = $src_url['path'];

        if ( strpos($src, $upload_path) !== false ) {

            $src_webp = str_replace('.jpg', '.webp', $src);
            $src_webp = str_replace('.jpeg', '.webp', $src_webp);
            $src_webp = str_replace('.png', '.webp', $src_webp);

            $parts = explode($upload_path, $src_webp);
            $relative_path = $parts[1];

            // check if relative path is not empty and file exists
            if ( !empty($relative_path) && file_exists($upload_dir['basedir'].$relative_path) ) {
                return $src_webp;
            } else {
                // try appended webp extension
                $src_webp_appended = $src.'.webp';
                $parts_appended = explode($upload_path, $src_webp_appended);
                $relative_path_appended = $parts_appended[1];

                // check if relative path is not empty and file exists
                if ( !empty($relative_path_appended) && file_exists($upload_dir['basedir'].$relative_path_appended) ) {
                    return $src_webp_appended;
                }
            }
        }

        return $src;
    }


    /**
     * convert srcset to webp source
     *
     * @since   1.0.8
     * @change  1.1.0
     *
     * @return  string  converted srcset webp source
     */

    private static function _convert_webp_srcset($srcset) {

        $sizes = explode(', ', $srcset);
        $upload_dir = wp_upload_dir();
        $src_url = parse_url($upload_dir['baseurl']);
        $upload_path = $src_url['path'];

        for ($i=0; $i<count($sizes); $i++) {

            if ( strpos($sizes[$i], $upload_path) !== false ) {

                $src_webp = str_replace('.jpg', '.webp', $sizes[$i]);
                $src_webp = str_replace('.jpeg', '.webp', $src_webp);
                $src_webp = str_replace('.png', '.webp', $src_webp);

                $size_parts = explode(' ', $src_webp);
                $parts = explode($upload_path, $size_parts[0]);
                $relative_path = $parts[1];

                // check if relative path is not empty and file exists
                if ( !empty($relative_path) && file_exists($upload_dir['basedir'].$relative_path) ) {
                    $sizes[$i] = $src_webp;
                } else {
                    // try appended webp extension
                    $size_parts_appended = explode(' ', $sizes[$i]);
                    $src_webp_appended = $size_parts_appended[0].'.webp';
                    $parts_appended = explode($upload_path, $src_webp_appended);
                    $relative_path_appended = $parts_appended[1];
                    $src_webp_appended = $src_webp_appended.' '.$size_parts_appended[1];

                    // check if relative path is not empty and file exists
                    if ( !empty($relative_path_appended) && file_exists($upload_dir['basedir'].$relative_path_appended) ) {
                        $sizes[$i] = $src_webp_appended;
                    }
                }

            }

        }

        $srcset = implode(', ', $sizes);

        return $srcset;
    }

}
