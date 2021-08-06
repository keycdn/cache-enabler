<?php
/**
 * Cache Enabler base
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler {

    /**
     * initialize plugin
     *
     * @since   1.5.0
     * @change  1.5.0
     */

    public static function init() {

        new self();
    }


    /**
     * settings from database (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.5.0
     */

    public static $options;


    /**
     * fire page cache cleared hook (deprecated)
     *
     * @since       1.6.0
     * @deprecated  1.8.0
     */

    public static $fire_page_cache_cleared_hook = true;


    /**
     * constructor
     *
     * @since   1.0.0
     * @change  1.8.0
     */

    public function __construct() {

        // init hooks
        add_action( 'init', array( 'Cache_Enabler_Engine', 'start' ) );
        add_action( 'init', array( __CLASS__, 'process_clear_cache_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );
        add_action( 'init', array( __CLASS__, 'schedule_events' ) );

        // public clear cache hooks
        add_action( 'cache_enabler_clear_complete_cache', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'cache_enabler_clear_site_cache', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'cache_enabler_clear_page_cache_by_post_id', array( __CLASS__, 'clear_page_cache_by_post' ) );
        add_action( 'cache_enabler_clear_page_cache_by_url', array( __CLASS__, 'clear_page_cache_by_url' ) );
        add_action( 'cache_enabler_clear_expired_cache', array( __CLASS__, 'clear_expired_cache' ) );
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_complete_cache' ) ); // deprecated in 1.6.0
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post' ) ); // deprecated in 1.6.0
        add_action( 'cache_enabler_clear_site_cache_by_blog_id', array( __CLASS__, 'clear_page_cache_by_site' ) ); // deprecated in 1.8.0

        // system clear cache hooks
        add_action( '_core_updated_successfully', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'switch_theme', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'permalink_structure_changed', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'save_post', array( __CLASS__, 'on_save_trash_post' ) );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_save_trash_post' ) );
        add_action( 'pre_post_update', array( __CLASS__, 'on_pre_post_update' ), 10, 2 );
        add_action( 'comment_post', array( __CLASS__, 'on_comment_post' ), 99, 2 );
        add_action( 'edit_comment', array( __CLASS__, 'on_edit_comment' ), 10, 2 );
        add_action( 'transition_comment_status', array( __CLASS__, 'on_transition_comment_status' ), 10, 3 );
        add_action( 'saved_term', array( __CLASS__, 'on_saved_delete_term' ), 10, 3 );
        add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 2 );
        add_action( 'delete_term', array( __CLASS__, 'on_saved_delete_term' ), 10, 3 );
        add_action( 'user_register', array( __CLASS__, 'on_register_update_delete_user' ), 10 );
        add_action( 'profile_update', array( __CLASS__, 'on_register_update_delete_user' ), 10 );
        add_action( 'delete_user', array( __CLASS__, 'on_register_update_delete_user' ), 10 );
        add_action( 'deleted_user', array( __CLASS__, 'on_deleted_user' ), 10, 2 );

        // third party clear cache hooks
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );

        // system cache created/cleared hooks
        add_action( 'cache_enabler_page_cache_created', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );
        add_action( 'cache_enabler_site_cache_cleared', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );
        add_action( 'cache_enabler_page_cache_cleared', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );

        // multisite hooks
        add_action( 'wp_initialize_site', array( __CLASS__, 'install_later' ) );
        add_action( 'wp_uninitialize_site', array( __CLASS__, 'uninstall_later' ) );

        // settings hooks
        add_action( 'permalink_structure_changed', array( __CLASS__, 'update_backend' ) );
        add_action( 'add_option_cache_enabler', array( __CLASS__, 'on_update_backend' ), 10, 2 );
        add_action( 'update_option_cache_enabler', array( __CLASS__, 'on_update_backend' ), 10, 2 );

        // admin bar hook
        add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_items' ), 90 );

        // admin interface hooks
        if ( is_admin() ) {
            // settings
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_resources' ) );
            // dashboard
            add_filter( 'dashboard_glance_items', array( __CLASS__, 'add_dashboard_cache_size' ) );
            add_filter( 'plugin_action_links_' . CACHE_ENABLER_BASE, array( __CLASS__, 'add_plugin_action_links' ) );
            add_filter( 'plugin_row_meta', array( __CLASS__, 'add_plugin_row_meta' ), 10, 2 );
            // notices
            add_action( 'admin_notices', array( __CLASS__, 'requirements_check' ) );
            add_action( 'admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
            add_action( 'network_admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
        }
    }


    /**
     * activation hook
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_activation( $network_wide ) {

        self::each_site( $network_wide, 'self::update_backend' );

        Cache_Enabler_Disk::setup();
    }


    /**
     * upgrade hook
     *
     * @since   1.4.0
     * @change  1.8.0
     *
     * @param   WP_Upgrader  $obj   upgrade instance
     * @param   array        $data  update data
     */

    public static function on_upgrade( $obj, $data ) {

        if ( $data['action'] !== 'update' ) {
            return;
        }

        // updated themes
        if ( $data['type'] === 'theme' && isset( $data['themes'] ) ) {
            $updated_themes = (array) $data['themes'];
            $sites_themes   = self::each_site( is_multisite(), 'wp_get_theme' );

            foreach ( $sites_themes as $blog_id => $site_theme ) {
                // if the active or parent theme has been updated clear site cache
                if ( in_array( $site_theme->stylesheet, $updated_themes, true ) || in_array( $site_theme->template, $updated_themes, true ) ) {
                    self::clear_page_cache_by_site( $blog_id );
                }
            }
        }

        // updated plugins
        if ( $data['type'] === 'plugin' && isset( $data['plugins'] ) ) {
            $updated_plugins = (array) $data['plugins'];
            $network_plugins = ( is_multisite() ) ? array_flip( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();

            // if a network activated plugin has been updated clear complete cache
            if ( ! empty( array_intersect( $updated_plugins, $network_plugins ) ) ) {
                self::clear_complete_cache();
            // check each site otherwise
            } else {
                $sites_plugins = self::each_site( is_multisite(), 'get_option', array( 'active_plugins', array() ) );

                foreach ( $sites_plugins as $blog_id => $site_plugins ) {
                    // if an activated plugin has been updated clear site cache
                    if ( ! empty( array_intersect( $updated_plugins, (array) $site_plugins ) ) ) {
                        self::clear_page_cache_by_site( $blog_id );
                    }
                }
            }
        }
    }


    /**
     * Cache Enabler update actions (deprecated)
     *
     * @since       1.4.0
     * @deprecated  1.8.0
     */

    public static function on_cache_enabler_update() {

        self::each_site( is_multisite(), 'Cache_Enabler_Disk::clean' );

        Cache_Enabler_Disk::setup();

        self::clear_complete_cache();
    }


    /**
     * deactivation hook
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   bool   $network_wide  network deactivated
     */

    public static function on_deactivation( $network_wide ) {

        self::each_site( $network_wide, 'Cache_Enabler_Disk::clean' );
        self::each_site( $network_wide, 'self::clear_site_cache' );
    }


    /**
     * uninstall hook
     *
     * @since   1.0.0
     * @change  1.6.0
     */

    public static function on_uninstall() {

        self::each_site( is_multisite(), 'self::uninstall_backend' );
    }


    /**
     * cache created or cleared hook
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param  string   $url    site or post URL
     * @param  integer  $id     blog or post ID
     * @param  array    $index  cache created or cleared index
     */

    public static function on_cache_created_cleared( $url, $id, $index ) {

        if ( is_multisite() && ! wp_is_site_initialized( get_current_blog_id() ) ) {
            return;
        }

        $current_cache_size = get_transient( 'cache_enabler_cache_size' );

        if ( $current_cache_size === false ) {
            self::get_cache_size(); // get current cache size from disk and set cache size transient
        } else {
            if ( count( $index ) > 1 ) {
                delete_transient( 'cache_enabler_cache_size' ); // prevent incorrect cache size being built if cache cleared index is not entire site
            } else {
                $changed_cache_size = array_sum( current( $index )['versions'] );
                $new_cache_size     = $current_cache_size + $changed_cache_size; // changed cache size is negative if cache cleared

                if ( $new_cache_size >= 0 ) {
                    set_transient( 'cache_enabler_cache_size', $new_cache_size, HOUR_IN_SECONDS );
                }
            }
        }
    }


    /**
     * install on new site in multisite network
     *
     * @since   1.0.0
     * @change  1.7.0
     *
     * @param   WP_Site  $new_site  new site instance
     */

    public static function install_later( $new_site ) {

        if ( ! is_plugin_active_for_network( CACHE_ENABLER_BASE ) ) {
            return;
        }

        switch_to_blog( (int) $new_site->blog_id );
        self::update_backend();
        restore_current_blog();
    }


    /**
     * update disk and backend requirements
     *
     * @since   1.8.0
     * @change  1.8.0
     */

    public static function update() {

        self::update_disk();
        self::each_site( is_multisite(), 'self::update_backend' );
        self::clear_complete_cache();
    }


    /**
     * add or update backend requirements (triggers creation of settings file)
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  array  $new_option_value  new or current database option value
     */

    public static function update_backend() {

        // delete user(s) meta key from deleted publishing action (1.5.0)
        delete_metadata( 'user', 0, '_clear_post_cache_on_update', '', true );

        // maybe rename old database option (1.5.0)
        $old_option_value = get_option( 'cache-enabler' );
        if ( $old_option_value !== false ) {
            delete_option( 'cache-enabler' );
            add_option( 'cache_enabler', $old_option_value );
        }

        // get defined settings, fall back to empty array if not found
        $old_option_value = get_option( 'cache_enabler', array() );

        // maybe convert old settings to new settings
        $new_option_value = self::convert_settings( $old_option_value );

        // update default system settings
        $new_option_value = wp_parse_args( self::get_default_settings( 'system' ), $new_option_value );

        // merge defined settings into default settings
        $new_option_value = wp_parse_args( $new_option_value, self::get_default_settings() );

        // validate settings
        $new_option_value = self::validate_settings( $new_option_value );

        // add or update database option
        update_option( 'cache_enabler', $new_option_value );

        // create settings file if action has not been registered for hook yet, like when in activation hook (even if backend was not actually updated)
        if ( has_action( 'update_option_cache_enabler', array( __CLASS__, 'on_update_backend' ) ) === false ) {
            self::on_update_backend( $old_option_value, $new_option_value );
        }

        return $new_option_value;
    }


    /**
     * update disk requirements
     *
     * @since   1.8.0
     * @change  1.8.0
     */

    public static function update_disk() {

        self::each_site( is_multisite(), 'Cache_Enabler_Disk::clean' );

        Cache_Enabler_Disk::setup();
    }


    /**
     * add or update database option hook
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   mixed  $option            old database option value or name of the option to add
     * @param   mixed  $new_option_value  new database option value
     */

    public static function on_update_backend( $option, $new_option_value ) {

        Cache_Enabler_Disk::create_settings_file( $new_option_value );
    }


    /**
     * uninstall on deleted site in multisite network
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @param   WP_Site  $old_site  old site instance
     */

    public static function uninstall_later( $old_site ) {

        Cache_Enabler_Disk::clean();

        self::clear_page_cache_by_site( (int) $old_site->blog_id );
    }


    /**
     * uninstall backend requirements
     *
     * @since   1.5.0
     * @change  1.8.0
     */

    private static function uninstall_backend() {

        delete_option( 'cache_enabler' );
        delete_transient( 'cache_enabler_cache_size' );
    }


    /**
     * enter each site
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   boolean  $network          whether or not each site in network
     * @param   string   $callback         callback function
     * @param   array    $callback_params  callback function parameters
     * @return  array    $callback_return  returned value(s) from callback function
     */

    private static function each_site( $network, $callback, $callback_params = array() ) {

        $callback_return = array();

        if ( $network ) {
            $blog_ids = self::get_blog_ids();

            // switch to each site in network
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                $callback_return[ $blog_id ] = call_user_func_array( $callback, $callback_params );
                restore_current_blog();
            }
        } else {
            $blog_id = 1;
            $callback_return[ $blog_id ] = call_user_func_array( $callback, $callback_params );
        }

        return $callback_return;
    }


    /**
     * plugin activation and deactivation hooks
     *
     * @since   1.4.0
     * @change  1.6.0
     */

    public static function on_plugin_activation_deactivation() {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_changed_plugin'] ) {
            self::clear_site_cache();
        }
    }


    /**
     * get settings from the database for the current site
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @return  array  $settings  current settings from database
     */

    public static function get_settings() {

        $settings = get_option( 'cache_enabler' );

        if ( $settings === false || ! isset( $settings['version'] ) || $settings['version'] !== CACHE_ENABLER_VERSION ) {
            self::update();
            $settings = self::get_settings();
        }

        return $settings;
    }


    /**
     * get blog IDs
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @return  array  $blog_ids  blog IDs
     */

    private static function get_blog_ids() {

        $blog_ids = array( 1 );

        if ( is_multisite() ) {
            global $wpdb;

            $blog_ids = array_map( 'absint', $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) );
        }

        return $blog_ids;
    }


    /**
     * get blog path
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @return  string  $blog_path  blog path (with leading and trailing slash) from site address URL if it exists, empty if not
     */

    public static function get_blog_path() {

        $site_url_path        = (string) parse_url( home_url(), PHP_URL_PATH );
        $site_url_path_pieces = explode( '/', trim( $site_url_path, '/' ) );

        // get last piece in case installation is in a nested subdirectory
        $blog_path = end( $site_url_path_pieces );

        // using empty string instead of '/' because it simplifies the blog path check handling
        $blog_path = ( ! empty( $blog_path ) ) ? '/' . $blog_path . '/' : '';

        return $blog_path;
    }


    /**
     * get blog path from URL
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @return  string  $blog_path  blog path (with leading and trailing slash) from URL if it exists, empty if not
     */

    public static function get_blog_path_from_url( $url ) {

        $url_path        = (string) parse_url( $url, PHP_URL_PATH );
        $url_path_pieces = explode( '/', trim( $url_path, '/' ) );
        $blog_path       = '/';
        $blog_paths      = self::get_blog_paths();

        foreach ( $url_path_pieces as $url_path_piece ) {
            $url_path_piece = '/' . $url_path_piece . '/';

            if ( in_array( $url_path_piece, $blog_paths, true ) ) {
                $blog_path = $url_path_piece;
                break;
            }
        }

        return $blog_path;
    }


    /**
     * get blog paths
     *
     * @since   1.5.0
     * @change  1.6.0
     *
     * @return  array  $blog_paths  blog paths
     */

    public static function get_blog_paths() {

        $blog_paths = array( '/' );

        if ( is_multisite() ) {
            global $wpdb;

            $blog_paths = $wpdb->get_col( "SELECT path FROM $wpdb->blogs" );
        }

        return $blog_paths;
    }


    /**
     * get permalink structure (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    private static function get_permalink_structure() {

        $permalink_structure = get_option( 'permalink_structure' );

        // permalink structure is custom and has a trailing slash
        if ( $permalink_structure && preg_match( '/\/$/', $permalink_structure ) ) {
            return 'has_trailing_slash';
        }

        // permalink structure is custom and does not have a trailing slash
        if ( $permalink_structure && ! preg_match( '/\/$/', $permalink_structure ) ) {
            return 'no_trailing_slash';
        }

        // permalink structure is not custom
        if ( empty( $permalink_structure ) ) {
            return 'plain';
        }
    }


    /**
     * get the cache index for the current site
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @return  array  $cache_index  cache index from the disk
     */

    public static function get_cache_index() {

        $args['subpages']['exclude'] = self::get_root_blog_exclusions();
        $cache = Cache_Enabler_Disk::cache_iterator( home_url(), $args );
        $cache_index = $cache['index'];

        return $cache_index;
    }


    /**
     * get the cache size for the current site
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @return  int    $cache_size  cache size in bytes, either from the database or disk
     */

    public static function get_cache_size() {

        $cache_size = get_transient( 'cache_enabler_cache_size' );

        if ( $cache_size === false ) {
            $args['subpages']['exclude'] = self::get_root_blog_exclusions();
            $cache = Cache_Enabler_Disk::cache_iterator( home_url(), $args );
            $cache_size = $cache['size'];

            set_transient( 'cache_enabler_cache_size', $cache_size, HOUR_IN_SECONDS );
        }

        return $cache_size;
    }


    /**
     * get the cache cleared transient name used for the clear notice
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @return  string  $transient_name  transient name
     */

    private static function get_cache_cleared_transient_name() {

        $transient_name = 'cache_enabler_cache_cleared_' . get_current_user_id();

        return $transient_name;
    }


    /**
     * get default settings
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string  $settings_type                              default 'system' settings, defaults to all default settings if empty
     * @return  array   $system_default_settings|$default_settings  only default system settings or all default settings
     */

    private static function get_default_settings( $settings_type = null ) {

        $system_default_settings = array(
            'version'              => (string) CACHE_ENABLER_VERSION,
            'use_trailing_slashes' => (int) $GLOBALS['wp_rewrite']->use_trailing_slashes,
            'permalink_structure'  => (string) self::get_permalink_structure(), // deprecated in 1.8.0
        );

        if ( $settings_type === 'system' ) {
            return $system_default_settings;
        }

        $user_default_settings = array(
            'cache_expires'                      => 0,
            'cache_expiry_time'                  => 0,
            'clear_site_cache_on_saved_post'     => 0,
            'clear_site_cache_on_saved_comment'  => 0,
            'clear_site_cache_on_saved_term'     => 0,
            'clear_site_cache_on_saved_user'     => 0,
            'clear_site_cache_on_changed_plugin' => 0,
            'convert_image_urls_to_webp'         => 0,
            'mobile_cache'                       => 0,
            'compress_cache'                     => 0,
            'minify_html'                        => 0,
            'minify_inline_css_js'               => 0,
            'excluded_post_ids'                  => '',
            'excluded_page_paths'                => '',
            'excluded_query_strings'             => '',
            'excluded_cookies'                   => '',
        );

        $default_settings = wp_parse_args( $user_default_settings, $system_default_settings );

        return $default_settings;
    }


    /**
     * get subpages that do not belong to root blog in subdirectory network
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @return  array  blog paths to other sites in network if current site is root blog in subdirectory network, empty otherwise
     */

    private static function get_root_blog_exclusions() {

        if ( ! is_multisite() || is_subdomain_install() ) {
            return array();
        }

        $current_blog_path  = self::get_blog_path();
        $network_blog_paths = self::get_blog_paths();

        if ( ! in_array( $current_blog_path, $network_blog_paths, true ) ) {
            return $network_blog_paths;
        }

        return array();
    }


    /**
     * convert settings to new structure
     *
     * @since   1.5.0
     * @change  1.6.1
     *
     * @param   array  $settings  settings
     * @return  array  $settings  converted settings if applicable, unchanged otherwise
     */

    private static function convert_settings( $settings ) {

        // check if there are any settings to convert
        if ( empty( $settings ) ) {
            return $settings;
        }

        // updated settings
        if ( isset( $settings['expires'] ) && $settings['expires'] > 0 ) {
            $settings['cache_expires'] = 1;
        }

        if ( isset( $settings['minify_html'] ) && $settings['minify_html'] === 2 ) {
            $settings['minify_html'] = 1;
            $settings['minify_inline_css_js'] = 1;
        }

        // renamed or removed settings
        $settings_names = array(
            // 1.4.0
            'excl_regexp'                            => 'excluded_page_paths',

            // 1.5.0
            'expires'                                => 'cache_expiry_time',
            'new_post'                               => 'clear_site_cache_on_saved_post',
            'update_product_stock'                   => '', // deprecated
            'new_comment'                            => 'clear_site_cache_on_saved_comment',
            'clear_on_upgrade'                       => 'clear_site_cache_on_changed_plugin',
            'webp'                                   => 'convert_image_urls_to_webp',
            'compress'                               => 'compress_cache',
            'excl_ids'                               => 'excluded_post_ids',
            'excl_paths'                             => 'excluded_page_paths',
            'excl_cookies'                           => 'excluded_cookies',
            'incl_parameters'                        => '', // deprecated

            // 1.6.0
            'clear_complete_cache_on_saved_post'     => 'clear_site_cache_on_saved_post',
            'clear_complete_cache_on_new_comment'    => 'clear_site_cache_on_saved_comment',
            'clear_complete_cache_on_changed_plugin' => 'clear_site_cache_on_changed_plugin',

            // 1.6.1
            'clear_site_cache_on_new_comment'        => 'clear_site_cache_on_saved_comment',
        );

        foreach ( $settings_names as $old_name => $new_name ) {
            if ( array_key_exists( $old_name, $settings ) ) {
                if ( ! empty( $new_name ) ) {
                    $settings[ $new_name ] = $settings[ $old_name ];
                }

                unset( $settings[ $old_name ] );
            }
        }

        return $settings;
    }


    /**
     * add plugin action links in the plugins list table
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   array  $action_links  action links
     * @return  array  $action_links  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_action_links( $action_links ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $action_links;
        }

        // prepend action link
        array_unshift( $action_links, sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=cache-enabler' ),
            esc_html__( 'Settings', 'cache-enabler' )
        ) );

        return $action_links;
    }


    /**
     * add plugin metadata in the plugins list table
     *
     * @since   1.5.0
     * @change  1.7.2
     *
     * @param   array   $plugin_meta  plugin metadata, including the version, author, author URI, and plugin URI
     * @param   string  $plugin_file  path to the plugin file relative to the plugins directory
     * @return  array   $plugin_meta  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_row_meta( $plugin_meta, $plugin_file ) {

        // check if Cache Enabler row
        if ( $plugin_file !== CACHE_ENABLER_BASE ) {
            return $plugin_meta;
        }

        // append metadata
        $plugin_meta = wp_parse_args(
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank" rel="nofollow noopener">' . esc_html__( 'Documentation', 'cache-enabler' ) . '</a>',
            ),
            $plugin_meta
        );

        return $plugin_meta;
    }


    /**
     * add dashboard cache size count
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   array  $items  extra 'At a Glance' widget items
     * @return  array  $items  extra 'At a Glance' widget items with cache size maybe added
     */

    public static function add_dashboard_cache_size( $items ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        $cache_size = self::get_cache_size();

        // add cache size as new widget item
        $items[] = sprintf(
            '<a href="%s">%s %s</a>',
            admin_url( 'options-general.php?page=cache-enabler' ),
            ( empty( $cache_size ) ) ? esc_html__( 'Empty', 'cache-enabler' ) : size_format( $cache_size ),
            esc_html__( 'Cache Size', 'cache-enabler' )
        );

        return $items;
    }


    /**
     * add admin bar items
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @param   object  $wp_admin_bar  menu properties
     */

    public static function add_admin_bar_items( $wp_admin_bar ) {

        // check user role
        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        // set clear cache button title
        $title = ( is_multisite() && is_network_admin() ) ? esc_html__( 'Clear Network Cache', 'cache-enabler' ) : esc_html__( 'Clear Site Cache', 'cache-enabler' );

        // add "Clear Network Cache" or "Clear Site Cache" button in admin bar
        $wp_admin_bar->add_menu(
            array(
                'id'     => 'cache_enabler_clear_cache',
                'href'   => wp_nonce_url( add_query_arg( array(
                                '_cache'  => 'cache-enabler',
                                '_action' => 'clear',
                            ) ), 'cache_enabler_clear_cache_nonce' ),
                'parent' => 'top-secondary',
                'title'  => '<span class="ab-item">' . $title . '</span>',
                'meta'   => array( 'title' => $title ),
            )
        );

        // add "Clear Page Cache" button in admin bar
        if ( ! is_admin() ) {
            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'cache_enabler_clear_page_cache',
                    'href'   => wp_nonce_url( add_query_arg( array(
                                    '_cache'  => 'cache-enabler',
                                    '_action' => 'clearurl',
                                ) ), 'cache_enabler_clear_cache_nonce' ),
                    'parent' => 'top-secondary',
                    'title'  => '<span class="ab-item">' . esc_html__( 'Clear Page Cache', 'cache-enabler' ) . '</span>',
                    'meta'   => array( 'title' => esc_html__( 'Clear Page Cache', 'cache-enabler' ) ),
                )
            );
        }
    }


    /**
     * enqueue styles and scripts
     *
     * @since   1.0.0
     * @change  1.7.0
     */

    public static function add_admin_resources( $hook ) {

        if ( $hook === 'settings_page_cache-enabler' ) {
            wp_enqueue_style( 'cache-enabler-settings', plugins_url( 'css/settings.min.css', CACHE_ENABLER_FILE ), array(), CACHE_ENABLER_VERSION );
        }
    }


    /**
     * add settings page
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_settings_page() {

        add_options_page(
            'Cache Enabler',
            'Cache Enabler',
            'manage_options',
            'cache-enabler',
            array( __CLASS__, 'settings_page' )
        );
    }


    /**
     * check if user can clear cache
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @return  boolean  true if user can clear cache, false otherwise
     */

    private static function user_can_clear_cache() {

        if ( apply_filters( 'cache_enabler_user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return true;
        }

        if ( apply_filters_deprecated( 'user_can_clear_cache', array( current_user_can( 'manage_options' ) ), '1.6.0', 'cache_enabler_user_can_clear_cache' ) ) {
            return true;
        }

        return false;
    }


    /**
     * process clear cache request
     *
     * @since   1.5.0
     * @change  1.8.0
     */

    public static function process_clear_cache_request() {

        // check if clear cache request
        if ( empty( $_GET['_cache'] ) || empty( $_GET['_action'] ) || $_GET['_cache'] !== 'cache-enabler' || ( $_GET['_action'] !== 'clear' && $_GET['_action'] !== 'clearurl' ) ) {
            return;
        }

        // validate nonce
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cache_enabler_clear_cache_nonce' ) ) {
            return;
        }

        // check user role
        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        // clear page cache
        if ( $_GET['_action'] === 'clearurl' ) {
            self::clear_page_cache_by_url( Cache_Enabler_Engine::$request_headers['Host'] . $_SERVER['REQUEST_URI'] );
        // clear site(s) cache
        } elseif ( $_GET['_action'] === 'clear' ) {
            self::each_site( ( is_multisite() && is_network_admin() ), 'self::clear_site_cache' );
        }

        // redirect to same page
        wp_safe_redirect( remove_query_arg( array( '_cache', '_action', '_wpnonce' ) ) );

        // set transient for clear notice
        if ( is_admin() ) {
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        // clear cache request completed
        exit;
    }


    /**
     * admin notice after cache has been cleared
     *
     * @since   1.5.0
     * @change  1.7.0
     */

    public static function cache_cleared_notice() {

        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        if ( get_transient( self::get_cache_cleared_transient_name() ) ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                ( is_multisite() && is_network_admin() ) ? esc_html__( 'Network cache cleared.', 'cache-enabler' ) : esc_html__( 'Site cache cleared.', 'cache-enabler' )
            );

            delete_transient( self::get_cache_cleared_transient_name() );
        }
    }


    /**
     * save or trash post hook
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_save_trash_post( $post_id ) {

        $post_status = get_post_status( $post_id );

        // if any published post type has been created, updated, or about to be trashed
        if ( $post_status === 'publish' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }


    /**
     * pre post update hook
     *
     * @since   1.7.0
     * @change  1.7.0
     *
     * @param   integer  $post_id    post ID
     * @param   array    $post_data  unslashed post data
     */

    public static function on_pre_post_update( $post_id, $post_data ) {

        $old_post_status = get_post_status( $post_id );
        $new_post_status = $post_data['post_status'];

        // if any published post type is about to be updated but not trashed
        if ( $old_post_status === 'publish' && $new_post_status !== 'trash' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }


    /**
     * comment post hook
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param   integer         $comment_id        comment ID
     * @param   integer|string  $comment_approved  comment approval status
     */

    public static function on_comment_post( $comment_id, $comment_approved ) {

        // if new approved comment is posted
        if ( $comment_approved === 1 ) {
            self::clear_cache_on_comment_save( $comment_id );
        }
    }


    /**
     * edit comment hook
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param   integer  $comment_id    comment ID
     * @param   array    $comment_data  comment data
     */

    public static function on_edit_comment( $comment_id, $comment_data ) {

        $comment_approved = (int) $comment_data['comment_approved'];

        // if approved comment is edited
        if ( $comment_approved === 1 ) {
            self::clear_cache_on_comment_save( $comment_id );
        }
    }


    /**
     * transition comment status hook
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param   integer|string  $new_status  new comment status
     * @param   integer|string  $old_status  old comment status
     * @param   WP_Comment      $comment     comment instance
     */

    public static function on_transition_comment_status( $new_status, $old_status, $comment ) {

        // if comment status has changed from or to approved
        if ( $old_status === 'approved' || $new_status === 'approved' ) {
            self::clear_cache_on_comment_save( $comment );
        }
    }


    /**
     * edit terms hook
     *
     * runs just before the term is updated in the database
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   integer  $term_id   term ID
     * @param   string   $taxonomy  taxonomy name that $term_id is part of
     */

    public static function on_edit_terms( $term_id, $taxonomy ) {

        if ( is_taxonomy_viewable( $taxonomy ) ) {
            self::clear_cache_on_term_save( $term_id, $taxonomy );
        }
    }


    /**
     * saved or delete term hook
     *
     * runs after the term has been updated in the database
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   integer  $term_id   term ID
     * @param   integer  $tt_id     term taxonomy ID
     * @param   string   $taxonomy  taxonomy name that $term_id is part of
     */

    public static function on_saved_delete_term( $term_id, $tt_id, $taxonomy ) {

        if ( is_taxonomy_viewable( $taxonomy ) ) {
            self::clear_cache_on_term_save( $term_id, $taxonomy );
        }
    }


    /**
     * register, update, or delete user hook
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   int    $user_id  ID of the newly registered, updated, or about to be deleted user
     */

    public static function on_register_update_delete_user( $user_id ) {

        self::clear_cache_on_user_save( $user_id );
    }


    /**
     * deleted user hook
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   int       $user_id   ID of the deleted user
     * @param   int|null  $reassign  ID of the user reassigned to the old posts of $user_id
     */

    public static function on_deleted_user( $user_id, $reassign ) {

        if ( $reassign ) {
            self::clear_cache_on_user_save( $reassign );
        }
    }


    /**
     * WooCommerce stock hooks
     *
     * @since   1.4.0
     * @change  1.6.1
     *
     * @param   WC_Product|int  $product  product instance or product ID
     */

    public static function on_woocommerce_stock_update( $product ) {

        if ( is_int( $product ) ) {
            $product_id = $product;
        } else {
            $product_id = $product->get_id();
        }

        self::clear_cache_on_post_save( $product_id );
    }


    /**
     * clear the site cache of a single site or all sites in a multisite network
     *
     * @since   1.5.0
     * @change  1.8.0
     */

    public static function clear_complete_cache() {

        self::each_site( is_multisite(), 'self::clear_site_cache' );
    }


    /**
     * clear complete cache (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.5.0
     */

    public static function clear_total_cache() {

        self::clear_complete_cache();
    }


    /**
     * clear the site cache for the current site or of a given site
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param   WP_Site|int|string  $site  (optional) site instance or site blog ID, defaults to current site if empty
     */

    public static function clear_site_cache( $site = null ) {

        if ( is_multisite() ) {
            $site = get_site( $site );

            if ( ! $site instanceof WP_Site ) {
                return;
            }

            $blog_id = (int) $site->blog_id;
        } else {
            $blog_id = get_current_blog_id();
        }

        self::clear_page_cache_by_site( $blog_id );
    }


    /**
     * clear the expired cache for the current site or of a given site
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Site|int|string  $site  (optional) site instance or site blog ID, defaults to current site if empty
     */

    public static function clear_expired_cache( $site = null ) {

        if ( is_multisite() ) {
            $site = get_site( $site );

            if ( ! $site instanceof WP_Site ) {
                return;
            }

            $blog_id = (int) $site->blog_id;
        } else {
            $blog_id = get_current_blog_id();
        }

        $args['hooks']['include'] = 'cache_enabler_page_cache_cleared';
        $args['expired'] = 1;

        self::clear_page_cache_by_site( $blog_id, $args );
    }


    /**
     * clear the post cache for the current post (if it is set) or of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  (optional) post instance or post ID, maybe defaults to current post if empty
     */

    public static function clear_post_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            self::clear_page_cache_by_post( $post, 'pagination' );
            self::clear_post_type_archive_cache( $post );
            self::clear_post_terms_archives_cache( $post );

            if ( $post->post_type === 'post' ) {
                self::clear_post_author_archive_cache( $post );
                self::clear_post_date_archives_cache( $post );
            }
        }
    }


    /**
     * clear the comment cache for the current comment (if it is set) or of a given comment
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Comment|int|string  $comment  (optional) comment instance or comment ID, maybe defaults to current comment if empty
     */

    public static function clear_comment_cache( $comment = null ) {

        $comment = get_comment( $comment );

        if ( $comment instanceof WP_Comment ) {
            self::clear_page_cache_by_comment( $comment, 'pagination' );
        }
    }


    /**
     * clear the term cache for the current term (if it is set) or of a given term
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int  $term      term instance or term ID
     * @param   string       $taxonomy  (optional) taxonomy name that $term is part of
     */

    public static function clear_term_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            self::clear_page_cache_by_term( $term, '', 'pagination' ); // clear the page cache for all posts that have the term set
            self::clear_term_archive_cache( $term );

            if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
                self::clear_term_children_archives_cache( $term );
                self::clear_term_parents_archives_cache( $term );
            }
        }
    }


    /**
     * clear the user cache for the current user (if logged in) or of a given user
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_User|int|string  $user  (optional) user instance or user ID, maybe defaults to current user if empty
     */

    public static function clear_user_cache( $user = null ) {

        if ( empty( $user ) ) {
            $user = wp_get_current_user();
        }

        if ( is_numeric( $user ) ) {
            $user = get_userdata( $user );
        }

        if ( $user instanceof WP_User ) {
            self::clear_page_cache_by_user( $user, 'pagination' ); // clear the page cache for all posts that the user is the author or has commented on
            self::clear_author_archive_cache( $user );
        }
    }


    /**
     * clear cached pages that might have changed from any new or updated post (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    public static function clear_associated_cache( $post ) {

        self::clear_post_type_archive_cache( $post );
        self::clear_post_terms_archives_cache( $post );

        if ( $post->post_type === 'post' ) {
            self::clear_post_author_archive_cache( $post );
            self::clear_post_date_archives_cache( $post );
        }
    }


    /**
     * clear post type archives page cache (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    public static function clear_post_type_archives_cache( $post_type ) {

        $post_type_archives_url = get_post_type_archive_link( $post_type );

        if ( ! empty( $post_type_archives_url ) ) {
            self::clear_page_cache_by_url( $post_type_archives_url, 'pagination' );
        }
    }


    /**
     * clear the post type archive cache for the current post (if it is set) or of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  (optional) post instance or post ID, maybe defaults to current post if empty
     */

    public static function clear_post_type_archive_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            $post_type_archive_url = get_post_type_archive_link( $post->post_type );

            if ( $post_type_archive_url !== false && strpos( $post_type_archive_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $post_type_archive_url, 'pagination' );
            }
        }
    }


    /**
     * clear the post terms archives cache for the current post (if it is set) or of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  (optional) post instance or post ID, maybe defaults to current post if empty
     */

    public static function clear_post_terms_archives_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            $terms = wp_get_post_terms( $post->ID, get_taxonomies() );

            if ( is_array( $terms ) ) {
                foreach ( $terms as $term ) {
                    self::clear_term_archive_cache( $term );

                    if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
                        self::clear_term_parents_archives_cache( $term ); // post can be in term parents archives
                    }
                }
            }
        }
    }


    /**
     * clear the post author archive cache for the current post (if it is set) or of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  (optional) post instance or post ID, maybe defaults to current post if empty
     */

    public static function clear_post_author_archive_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            self::clear_author_archive_cache( (int) $post->post_author );
        }
    }


    /**
     * clear the post date archives cache for the current post (if it is set) or of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  (optional) post instance or post ID, maybe defaults to current post if empty
     */

    public static function clear_post_date_archives_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            $date_archive_day    = get_the_date( 'd', $post );
            $date_archive_month  = get_the_date( 'm', $post );
            $date_archive_year   = get_the_date( 'Y', $post );
            $date_archive_urls[] = get_day_link( $date_archive_year, $date_archive_month, $date_archive_day );
            $date_archive_urls[] = get_month_link( $date_archive_year, $date_archive_month );
            $date_archive_urls[] = get_year_link( $date_archive_year );

            foreach ( $date_archive_urls as $date_archive_url ) {
                if ( strpos( $date_archive_url, '?' ) === false ) {
                    self::clear_page_cache_by_url( $date_archive_url, 'pagination' );
                }
            }
        }
    }


    /**
     * clear taxonomies archives cache by post ID (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    public static function clear_taxonomies_archives_cache_by_post_id( $post_id ) {

        self::clear_post_terms_archives_cache( $post_id );
    }


    /**
     * clear author archives page cache by user ID (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    public static function clear_author_archives_cache_by_user_id( $user_id ) {

        self::clear_author_archive_cache( $user_id );
    }


    /**
     * clear date archives cache by post ID (deprecated)
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */

    public static function clear_date_archives_cache_by_post_id( $post_id ) {

        self::clear_post_date_archives_cache( $post_id );
    }


    /**
     * clear the term archive cache of a given term
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int  $term      term instance or term ID
     * @param   string       $taxonomy  (optional) taxonomy name that $term is part of
     */

    public static function clear_term_archive_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            if ( ! is_taxonomy_viewable( $term->taxonomy ) ) {
                return; // term archive cache does not exist
            }

            $term_archive_url = get_term_link( $term );

            if ( ! is_wp_error( $term_archive_url ) && strpos( $term_archive_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $term_archive_url, 'pagination' );
            }
        }
    }


    /**
     * clear the term children archives cache of a given term
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int  $term      term instance or term ID
     * @param   string       $taxonomy  (optional) taxonomy name that $term is part of
     */

    public static function clear_term_children_archives_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            $child_ids = get_term_children( $term->term_id, $term->taxonomy );

            if ( is_array( $child_ids ) ) {
                foreach ( $child_ids as $child_id ) {
                    self::clear_term_archive_cache( $child_id, $term->taxonomy );
                }
            }
        }
    }


    /**
     * clear the term parents archives cache of a given term
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int  $term      term instance or term ID
     * @param   string       $taxonomy  (optional) taxonomy name that $term is part of
     */

    public static function clear_term_parents_archives_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            $parent_ids = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );

            foreach ( $parent_ids as $parent_id ) {
                self::clear_term_archive_cache( $parent_id, $term->taxonomy );
            }
        }
    }


    /**
     * clear the author archive cache for the current user (if logged in) or of a given user
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_User|int|string  $author  (optional) author user instance or author user ID, maybe defaults to current user if empty
     */

    public static function clear_author_archive_cache( $author = null ) {

        if ( empty( $author ) ) {
            $author = wp_get_current_user();
        }

        if ( is_numeric( $author ) ) {
            $author = get_userdata( $author );
        }

        if ( $author instanceof WP_User ) {
            if ( empty( $author->user_nicename ) ) {
                return; // author archive cache does not exist
            }

            $author_archive_url = get_author_posts_url( $author->ID, $author->user_nicename );

            if ( strpos( $author_archive_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $author_archive_url, 'pagination' );
            }
        }
    }


    /**
     * clear the page cache associated with a given site
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Site|int|string  $site  site instance or site blog ID
     * @param   array|string        $args  (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_site( $site, $args = array() ) {

        if ( is_multisite() ) {
            $site    = get_site( $site );
            $blog_id = ( $site instanceof WP_Site ) ? (int) $site->blog_id : 0;
        } else {
            $blog_id = (int) $site;
        }

        if ( ! in_array( $blog_id, self::get_blog_ids(), true ) ) {
            return; // blog ID does not exist
        }

        $args['subpages']['exclude'] = self::get_root_blog_exclusions();

        if ( ! isset( $args['hooks']['include'] ) ) {
            $args['hooks']['include'] = 'cache_enabler_complete_cache_cleared,cache_enabler_site_cache_cleared';
        }

        self::clear_page_cache_by_url( get_home_url( $blog_id ), $args );
    }


    /**
     * clear page cache by post ID (deprecated)
     *
     * @since       1.0.0
     * @deprecated  1.8.0
     */

    public static function clear_page_cache_by_post_id( $post_id, $args = array() ) {

        self::clear_page_cache_by_post( $post_id, $args );
    }


    /**
     * clear the page cache of a given post
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  post instance or post ID
     * @param   array|string        $args  (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_post( $post, $args = array() ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            if ( $post->post_status !== 'publish' ) {
                return; // page cache does not exist
            }

            $post_url = get_permalink( $post );

            if ( $post_url !== false && strpos( $post_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $post_url, $args );
            }
        }
    }


    /**
     * clear the page cache of the post associated with a given comment
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Comment|int|string  $comment  comment instance or comment ID
     * @param   array|string           $args     (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_comment( $comment, $args = array() ) {

        $comment = get_comment( $comment );

        if ( $comment instanceof WP_Comment ) {
            if ( $comment->comment_approved !== '1' ) {
                return; // page cache does not exist
            }

            self::clear_page_cache_by_post( (int) $comment->comment_post_ID, $args );
        }
    }


    /**
     * clear the page cache of the posts associated with a given term
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int   $term      term instance or term ID
     * @param   string        $taxonomy  (optional) taxonomy name that $term is part of
     * @param   array|string  $args      (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_term( $term, $taxonomy = '', $args = array() ) {

        $term = get_term( $term, $taxonomy );

        if ( ! $term instanceof WP_Term ) {
            return;
        }

        $post_query_args = array(
            'post_type'     => 'any',
            'post_status'   => 'publish',
            'numberposts'   => -1,
            'order'         => 'none',
            'cache_results' => false,
            'no_found_rows' => true,
            'tax_query'     => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'terms'    => $term->term_id,
                ),
            ),
        );

        $posts = get_posts( $post_query_args );

        foreach ( $posts as $post ) {
            self::clear_page_cache_by_post( $post, $args );
        }
    }


    /**
     * clear the page cache of the posts associated with a given user
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_User|int|string  $user  user instance or user ID
     * @param   array|string        $args  (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_user( $user, $args = array() ) {

        if ( is_numeric( $user ) ) {
            $user = get_userdata( $user );
        }

        if ( ! $user instanceof WP_User ) {
            return;
        }

        $post_query_args = array(
            'author'        => $user->ID,
            'post_type'     => 'any',
            'post_status'   => 'publish',
            'numberposts'   => -1,
            'fields'        => 'ids',
            'order'         => 'none',
            'cache_results' => false,
            'no_found_rows' => true,
        );

        $post_ids = get_posts( $post_query_args );

        $comment_query_args = array(
            'status'  => 'approve',
            'user_id' => $user->ID,
        );

        $comments = get_comments( $comment_query_args );

        foreach ( $comments as $comment ) {
            $comment_post_id = (int) $comment->comment_post_ID;

            if ( ! in_array( $comment_post_id, $post_ids, true ) ) {
                $post_ids[] = $comment_post_id;
            }
        }

        foreach ( $post_ids as $post_id ) {
            self::clear_page_cache_by_post( $post_id, $args );
        }
    }


    /**
     * clear the page cache of a given URL
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @param   string        $url   URL to potentially cached page (with or without scheme)
     * @param   array|string  $args  (optional) cache iterator arguments (see Cache_Enabler_Disk::cache_iterator()), 'pagination', or 'subpages'
     */

    public static function clear_page_cache_by_url( $url, $args = array() ) {

        if ( ! is_string( $url ) ) {
            return;
        }

        switch ( $args ) {
            case 'pagination':
                global $wp_rewrite;
                $included_subpages[] = ( isset( $wp_rewrite->pagination_base ) ) ? $wp_rewrite->pagination_base : '';
                $included_subpages[] = ( isset( $wp_rewrite->comments_pagination_base ) ) ? $wp_rewrite->comments_pagination_base . '-*' : '';
                $args = array( 'subpages' => array( 'include' => $included_subpages ) );
                break;
            case 'subpages':
                $args = array( 'subpages' => 1 );
                break;
        }

        if ( ! is_array( $args ) ) {
            $args = array();
        }

        $args['clear'] = 1;

        if ( ! isset( $args['hooks']['include'] ) ) {
            $args['hooks']['include'] = 'cache_enabler_page_cache_cleared';
        }

        $url_path = (string) parse_url( $url, PHP_URL_PATH );

        if ( substr( $url_path, -1, 1 ) === '*' ) {
            $url_path_pieces  = explode( '/', $url_path );
            $wildcard_subpage = end( $url_path_pieces );
            $new_url_path     = substr( $url_path, 0, -strlen( $wildcard_subpage ) );
            $url              = str_replace( $url_path, $new_url_path, $url );

            $args['subpages']['include'] = $wildcard_subpage;
            $args['root'] = CACHE_ENABLER_CACHE_DIR . '/' . substr( (string) parse_url( $url, PHP_URL_HOST ) . $url_path, 0, -1 );
        }

        Cache_Enabler_Disk::cache_iterator( $url, $args );
    }


    /**
     * clear site cache by blog ID (deprecated)
     *
     * @since       1.4.0
     * @deprecated  1.8.0
     */

    public static function clear_site_cache_by_blog_id( $blog_id, $deprecated = null ) {

        self::clear_page_cache_by_site( $blog_id );
    }


    /**
     * clear the site or post cache when any post type has been published, updated, or trashed
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   WP_Post|int|string  $post  post instance or post ID
     */

    public static function clear_cache_on_post_save( $post ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ) {
            self::clear_site_cache();
        } else {
            self::clear_post_cache( $post );
        }
    }


    /**
     * clear the site or comment cache when a comment been posted, updated, spammed, or trashed
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Comment|int|string  $comment  comment instance or comment ID
     */

    public static function clear_cache_on_comment_save( $comment ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_comment'] ) {
            self::clear_site_cache();
        } else {
            self::clear_comment_cache( $comment );
        }
    }


    /**
     * clear the site or term cache when any term has been added, updated, or deleted
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_Term|int  $term      term instance or term ID
     * @param   string       $taxonomy  (optional) taxonomy name that $term is part of
     */

    public static function clear_cache_on_term_save( $term, $taxonomy = '' ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_term'] ) {
            self::clear_site_cache();
        } else {
            self::clear_term_cache( $term, $taxonomy );
        }
    }


    /**
     * clear the site or user cache when any user has been added, updated, or deleted
     *
     * @since   1.8.0
     * @change  1.8.0
     *
     * @param   WP_User|int|string  $user  user instance or user ID
     */

    public static function clear_cache_on_user_save( $user ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_user'] ) {
            self::clear_site_cache();
        } else {
            self::clear_user_cache( $user );
        }
    }


    /**
     * check plugin requirements
     *
     * @since   1.1.0
     * @change  1.8.0
     */

    public static function requirements_check() {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check PHP version
        if ( version_compare( PHP_VERSION, CACHE_ENABLER_MIN_PHP, '<' ) ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. PHP version (e.g. 5.6)
                    esc_html__( '%1$s requires PHP %2$s or higher to function properly. Please update PHP or disable the plugin.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    CACHE_ENABLER_MIN_PHP
                )
            );
        }

        // check WordPress version
        if ( version_compare( $GLOBALS['wp_version'], CACHE_ENABLER_MIN_WP . 'alpha', '<' ) ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. WordPress version (e.g. 5.1)
                    esc_html__( '%1$s requires WordPress %2$s or higher to function properly. Please update WordPress or disable the plugin.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    CACHE_ENABLER_MIN_WP
                )
            );
        }

        // check advanced-cache.php drop-in
        if ( ! file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) && Cache_Enabler_Disk::create_advanced_cache_file() !== false ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. advanced-cache.php 3. wp-content/plugins/cache-enabler 4. wp-content
                    esc_html__( '%1$s requires the %2$s drop-in. Please deactivate and then activate this plugin to automatically create this file. You can manually create an %2$s file by locating the sample file named %3$s (located in the %4$s directory), editing it as required, and then saving it as %2$s in the %5$s directory.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    '<code>advanced-cache.php</code>',
                    '<code>advanced-cache-sample.php</code>',
                    '<code>' . str_replace( ABSPATH, '', CACHE_ENABLER_DIR ) . '</code>',
                    '<code>' . basename( WP_CONTENT_DIR ) . '</code>'
                )
            );
        }

        // check WordPress installation directory index file
        if ( ! file_exists( CACHE_ENABLER_INDEX_FILE ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. /path/to/index.php 3. CACHE_ENABLER_INDEX_FILE 4. wp-config.php
                    esc_html__( '%1$s was unable to find the WordPress installation directory index file at %2$s. Please define the %3$s constant in your %4$s file as the full path to the location of this file.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    '<code>' . CACHE_ENABLER_INDEX_FILE . '</code>',
                    '<code>CACHE_ENABLER_INDEX_FILE</code>',
                    '<code>wp-config.php</code>',
                )
            );
        }

        // check permalink structure
        if ( empty( get_option( 'permalink_structure' ) ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. Permalink Settings
                    esc_html__( '%1$s requires a custom permalink structure. Please enable a custom structure in the %2$s.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    sprintf(
                        '<a href="%s">%s</a>',
                        admin_url( 'options-permalink.php' ),
                        esc_html__( 'Permalink Settings', 'cache-enabler' )
                    )
                )
            );
        }

        // check file permissions
        $dirs = array( CACHE_ENABLER_CACHE_DIR, CACHE_ENABLER_SETTINGS_DIR );

        foreach ( $dirs as $dir ) {
            $parent_dir = dirname( $dir );

            if ( file_exists( $parent_dir ) && ! is_writable( $parent_dir ) ) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Cache Enabler 2. 755 3. wp-content/cache 4. file permissions
                        esc_html__( '%1$s requires write permissions %2$s in the %3$s directory. Please change the %4$s.', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        '<code>755</code>',
                        '<code>' . str_replace( ABSPATH, '', $parent_dir ) . '</code>',
                        sprintf(
                            '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                            'https://wordpress.org/support/article/changing-file-permissions/',
                            esc_html__( 'file permissions', 'cache-enabler' )
                        )
                    )
                );
            }
        }

        // check Autoptimize HTML optimization
        if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && Cache_Enabler_Engine::$settings['minify_html'] && get_option( 'autoptimize_html', '' ) !== '' ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Autoptimize 2. Cache Enabler Settings
                    esc_html__( '%1$s HTML optimization is enabled. Please disable HTML minification in the %2$s.', 'cache-enabler' ),
                    '<strong>Autoptimize</strong>',
                    sprintf(
                        '<a href="%s">%s</a>',
                        admin_url( 'options-general.php?page=cache-enabler' ),
                        esc_html__( 'Cache Enabler Settings', 'cache-enabler' )
                    )
                )
            );
        }
    }


    /**
     * register textdomain
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function register_textdomain() {

        // load translated strings
        load_plugin_textdomain( 'cache-enabler', false, 'cache-enabler/lang' );
    }


    /**
     * register settings
     *
     * @since   1.0.0
     * @change  1.5.0
     */

    public static function register_settings() {

        register_setting( 'cache_enabler', 'cache_enabler', array( __CLASS__, 'validate_settings' ) );
    }


    /**
     * schedule cron events
     *
     * @since   1.8.0
     * @change  1.8.0
     */

    public static function schedule_events() {

        if ( ! Cache_Enabler_Engine::$started ) {
            return;
        }

        if ( Cache_Enabler_Engine::$settings['cache_expires'] && ! wp_next_scheduled( 'cache_enabler_clear_expired_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'cache_enabler_clear_expired_cache' );
        }
    }


    /**
     * validate regex
     *
     * @since   1.2.3
     * @change  1.5.0
     *
     * @param   string  $regex            string containing regex
     * @return  string  $validated_regex  string containing regex or empty string if input is invalid
     */

    public static function validate_regex( $regex ) {

        if ( ! empty( $regex ) ) {
            if ( ! preg_match( '/^\/.*\/$/', $regex ) ) {
                $regex = '/' . $regex . '/';
            }

            if ( @preg_match( $regex, null ) === false ) {
                return '';
            }

            $validated_regex = sanitize_text_field( $regex );

            return $validated_regex;
        }

        return '';
    }


    /**
     * validate settings
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @param   array  $settings            user defined settings
     * @return  array  $validated_settings  validated settings
     */

    public static function validate_settings( $settings ) {

        $validated_settings = array(
            'cache_expires'                      => (int) ( ! empty( $settings['cache_expires'] ) ),
            'cache_expiry_time'                  => (int) $settings['cache_expiry_time'],
            'clear_site_cache_on_saved_post'     => (int) ( ! empty( $settings['clear_site_cache_on_saved_post'] ) ),
            'clear_site_cache_on_saved_comment'  => (int) ( ! empty( $settings['clear_site_cache_on_saved_comment'] ) ),
            'clear_site_cache_on_saved_term'     => (int) ( ! empty( $settings['clear_site_cache_on_saved_term'] ) ),
            'clear_site_cache_on_saved_user'     => (int) ( ! empty( $settings['clear_site_cache_on_saved_user'] ) ),
            'clear_site_cache_on_changed_plugin' => (int) ( ! empty( $settings['clear_site_cache_on_changed_plugin'] ) ),
            'convert_image_urls_to_webp'         => (int) ( ! empty( $settings['convert_image_urls_to_webp'] ) ),
            'mobile_cache'                       => (int) ( ! empty( $settings['mobile_cache'] ) ),
            'compress_cache'                     => (int) ( ! empty( $settings['compress_cache'] ) ),
            'minify_html'                        => (int) ( ! empty( $settings['minify_html'] ) ),
            'minify_inline_css_js'               => (int) ( ! empty( $settings['minify_inline_css_js'] ) ),
            'excluded_post_ids'                  => (string) sanitize_text_field( $settings['excluded_post_ids'] ),
            'excluded_page_paths'                => (string) self::validate_regex( $settings['excluded_page_paths'] ),
            'excluded_query_strings'             => (string) self::validate_regex( $settings['excluded_query_strings'] ),
            'excluded_cookies'                   => (string) self::validate_regex( $settings['excluded_cookies'] ),
        );

        // add default system settings
        $validated_settings = wp_parse_args( $validated_settings, self::get_default_settings( 'system' ) );

        // check if site cache should be cleared
        if ( ! empty( $settings['clear_site_cache_on_saved_settings'] ) ) {
            self::clear_site_cache();
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        return $validated_settings;
    }


    /**
     * settings page
     *
     * @since   1.0.0
     * @change  1.8.0
     */

    public static function settings_page() {

        ?>

        <div id="cache_enabler_settings" class="wrap">
            <h1><?php esc_html_e( 'Cache Enabler Settings', 'cache-enabler' ); ?></h1>

            <?php
            if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Cache Enabler 2. define( 'WP_CACHE', true ); 3. wp-config.php 4. require_once ABSPATH . 'wp-settings.php';
                        esc_html__( '%1$s requires %2$s to be set. Please set this in the %3$s file (must be before %4$s).', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        "<code>define( 'WP_CACHE', true );</code>",
                        '<code>wp-config.php</code>',
                        "<code>require_once ABSPATH . 'wp-settings.php';</code>"
                    )
                );
            }
            ?>

            <div class="notice notice-info">
                <p>
                    <?php
                    printf(
                        // translators: %s: KeyCDN
                        esc_html__( 'Combine Cache Enabler with %s for even better WordPress performance and achieve the next level of caching with a CDN.', 'cache-enabler' ),
                        '<strong><a href="https://www.keycdn.com?utm_source=wp-admin&utm_medium=plugins&utm_campaign=cache-enabler" target="_blank" rel="nofollow noopener">KeyCDN</a></strong>'
                    );
                    ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'cache_enabler' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Behavior', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <p class="subheading"><?php esc_html_e( 'Expiration', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_cache_expires" class="checkbox--form-control">
                                    <input name="cache_enabler[cache_expires]" type="checkbox" id="cache_enabler_cache_expires" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['cache_expires'] ); ?> />
                                </label>
                                <label for="cache_enabler_cache_expiry_time">
                                    <?php
                                    printf(
                                        // translators: %s: Form field input for number of hours.
                                        esc_html__( 'Cached pages expire %s hours after being created.', 'cache-enabler' ),
                                        '<input name="cache_enabler[cache_expiry_time]" type="number" id="cache_enabler_cache_expiry_time" value="' . Cache_Enabler_Engine::$settings['cache_expiry_time'] . '" class="small-text">'
                                    );
                                    ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Clearing', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_clear_site_cache_on_saved_post">
                                    <input name="cache_enabler[clear_site_cache_on_saved_post]" type="checkbox" id="cache_enabler_clear_site_cache_on_saved_post" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if any post type has been published, updated, or trashed (instead of the post cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_enabler_clear_site_cache_on_saved_comment">
                                    <input name="cache_enabler[clear_site_cache_on_saved_comment]" type="checkbox" id="cache_enabler_clear_site_cache_on_saved_comment" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_comment'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if a comment has been posted, updated, spammed, or trashed (instead of the comment cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_enabler_clear_site_cache_on_saved_term">
                                    <input name="cache_enabler[clear_site_cache_on_saved_term]" type="checkbox" id="cache_enabler_clear_site_cache_on_saved_term" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_term'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if a term has been added, updated, or deleted (instead of the term cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_enabler_clear_site_cache_on_saved_user">
                                    <input name="cache_enabler[clear_site_cache_on_saved_user]" type="checkbox" id="cache_enabler_clear_site_cache_on_saved_user" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_user'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if a user has been added, updated, or deleted (instead of the user cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_enabler_clear_site_cache_on_changed_plugin">
                                    <input name="cache_enabler[clear_site_cache_on_changed_plugin]" type="checkbox" id="cache_enabler_clear_site_cache_on_changed_plugin" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_changed_plugin'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if a plugin has been activated or deactivated.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Versions', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_convert_image_urls_to_webp">
                                    <input name="cache_enabler[convert_image_urls_to_webp]" type="checkbox" id="cache_enabler_convert_image_urls_to_webp" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['convert_image_urls_to_webp'] ); ?> />
                                    <?php
                                    printf(
                                        // translators: %s: Optimus
                                        esc_html__( 'Create a cached version for WebP support. Convert your images to WebP with %s.', 'cache-enabler' ),
                                        '<a href="https://optimus.io" target="_blank" rel="nofollow noopener">Optimus</a>'
                                    );
                                    ?>
                                </label>

                                <br />

                                <label for="cache_enabler_mobile_cache">
                                    <input name="cache_enabler[mobile_cache]" type="checkbox" id="cache_enabler_mobile_cache" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['mobile_cache'] ); ?> />
                                    <?php esc_html_e( 'Create a cached version for mobile devices.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_enabler_compress_cache">
                                    <input name="cache_enabler[compress_cache]" type="checkbox" id="cache_enabler_compress_cache" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['compress_cache'] ); ?> />
                                    <?php ( function_exists( 'brotli_compress' ) && is_ssl() ) ? esc_html_e( 'Create a cached version pre-compressed with Brotli or Gzip.', 'cache-enabler' ) : esc_html_e( 'Create a cached version pre-compressed with Gzip.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Minification', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_minify_html" class="checkbox--form-control">
                                    <input name="cache_enabler[minify_html]" type="checkbox" id="cache_enabler_minify_html" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['minify_html'] ); ?> />
                                </label>
                                <label for="cache_enabler_minify_inline_css_js">
                                    <?php
                                    $minify_inline_css_js_options = array(
                                        esc_html__( 'excluding', 'cache-enabler' ) => 0,
                                        esc_html__( 'including', 'cache-enabler' ) => 1,
                                    );
                                    $minify_inline_css_js = '<select name="cache_enabler[minify_inline_css_js]" id="cache_enabler_minify_inline_css_js">';
                                    foreach ( $minify_inline_css_js_options as $key => $value ) {
                                        $minify_inline_css_js .= '<option value="' . esc_attr( $value ) . '"' . selected( $value, Cache_Enabler_Engine::$settings['minify_inline_css_js'], false ) . '>' . $key . '</option>';
                                    }
                                    $minify_inline_css_js .= '</select>';
                                    printf(
                                        // translators: %s: Form field control for 'excluding' or 'including' inline CSS and JavaScript during HTML minification.
                                        esc_html__( 'Minify HTML in cached pages %s inline CSS and JavaScript.', 'cache-enabler' ),
                                        $minify_inline_css_js
                                    );
                                    ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Exclusions', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <p class="subheading"><?php esc_html_e( 'Post IDs', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_excluded_post_ids">
                                    <input name="cache_enabler[excluded_post_ids]" type="text" id="cache_enabler_excluded_post_ids" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_post_ids'] ) ?>" class="regular-text" />
                                    <p class="description">
                                        <?php
                                        // translators: %s: ,
                                        printf(
                                            esc_html__( 'Post IDs separated by a %s that should bypass the cache.', 'cache-enabler' ),
                                            '<code class="code--form-control">,</code>'
                                        );
                                        ?>
                                    </p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">2,43,65</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Page Paths', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_excluded_page_paths">
                                    <input name="cache_enabler[excluded_page_paths]" type="text" id="cache_enabler_excluded_page_paths" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_page_paths'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching page paths that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">/^(\/|\/forums\/)$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Query Strings', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_excluded_query_strings">
                                    <input name="cache_enabler[excluded_query_strings]" type="text" id="cache_enabler_excluded_query_strings" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_query_strings'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching query strings that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">/^nocache$/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code class="code--form-control">/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Cookies', 'cache-enabler' ); ?></p>
                                <label for="cache_enabler_excluded_cookies">
                                    <input name="cache_enabler[excluded_cookies]" type="text" id="cache_enabler_excluded_cookies" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_cookies'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching cookies that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">/^(comment_author|woocommerce_items_in_cart|wp_woocommerce_session)_?/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code class="code--form-control">/^(wp-postpass|wordpress_logged_in|comment_author)_/</code></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button-secondary" value="<?php esc_html_e( 'Save Changes', 'cache-enabler' ); ?>" />
                    <input name="cache_enabler[clear_site_cache_on_saved_settings]" type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes and Clear Site Cache', 'cache-enabler' ); ?>" />
                </p>
            </form>
        </div>

        <?php

    }
}
