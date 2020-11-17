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
     * @since   1.0.0
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
     * fire page cache cleared hook
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @var     boolean
     */

    public static $fire_page_cache_cleared_hook = true;


    /**
     * constructor
     *
     * @since   1.0.0
     * @change  1.6.0
     */

    public function __construct() {

        // init hooks
        add_action( 'init', array( 'Cache_Enabler_Engine', 'start' ) );
        add_action( 'init', array( __CLASS__, 'process_clear_cache_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );

        // public clear cache hooks
        add_action( 'cache_enabler_clear_complete_cache', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'cache_enabler_clear_site_cache', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'cache_enabler_clear_site_cache_by_blog_id', array( __CLASS__, 'clear_site_cache_by_blog_id' ) );
        add_action( 'cache_enabler_clear_page_cache_by_post_id', array( __CLASS__, 'clear_page_cache_by_post_id' ) );
        add_action( 'cache_enabler_clear_page_cache_by_url', array( __CLASS__, 'clear_page_cache_by_url' ) );
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_complete_cache' ) ); // deprecated in 1.6.0
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post_id' ) ); // deprecated in 1.6.0

        // system clear cache hooks
        add_action( '_core_updated_successfully', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'switch_theme', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'permalink_structure_changed', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'save_post', array( __CLASS__, 'on_save_post' ) );
        add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
        add_action( 'comment_post', array( __CLASS__, 'on_comment_post' ), 99, 2 );
        add_action( 'edit_comment', array( __CLASS__, 'on_edit_comment' ), 10, 2 );
        add_action( 'transition_comment_status', array( __CLASS__, 'on_transition_comment_status' ), 10, 3 );

        // third party clear cache hooks
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_complete_cache' ) );

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
            add_filter( 'plugin_action_links_' . CE_BASE, array( __CLASS__, 'add_plugin_action_links' ) );
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

        // add backend requirements, triggering the settings file(s) to be created
        self::each_site( $network_wide, 'self::update_backend' );

        // configure system files
        Cache_Enabler_Disk::setup();
    }


    /**
     * upgrade hook
     *
     * @since   1.2.3
     * @change  1.6.0
     *
     * @param   WP_Upgrader  $obj   upgrade instance
     * @param   array        $data  update data
     */

    public static function on_upgrade( $obj, $data ) {

        // if setting enabled clear site cache on any plugin update
        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_changed_plugin'] ) {
            self::clear_site_cache();
        }

        // check if Cache Enabler has been updated
        if ( $data['action'] === 'update' && $data['type'] === 'plugin' && array_key_exists( 'plugins', $data ) ) {
            foreach ( (array) $data['plugins'] as $plugin_file ) {
                if ( $plugin_file === CE_BASE ) {
                    self::on_ce_update();
                }
            }
        }
    }


    /**
     * Cache Enabler update actions
     *
     * @since   1.4.0
     * @change  1.6.0
     */

    public static function on_ce_update() {

        // clean system files
        self::each_site( is_multisite(), 'Cache_Enabler_Disk::clean' );

        // configure system files
        Cache_Enabler_Disk::setup();

        // clear complete cache
        self::clear_complete_cache();
    }


    /**
     * deactivation hook
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   boolean  $network_wide  network deactivated
     */

    public static function on_deactivation( $network_wide ) {

        // clean system files
        self::each_site( $network_wide, 'Cache_Enabler_Disk::clean' );

        // clear site(s) cache
        self::each_site( $network_wide, 'self::clear_site_cache' );
    }


    /**
     * uninstall hook
     *
     * @since   1.0.0
     * @change  1.6.0
     */

    public static function on_uninstall() {

        // uninstall backend requirements
        self::each_site( is_multisite(), 'self::uninstall_backend' );
    }


    /**
     * install on new site in multisite network
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   WP_Site  $new_site  new site instance
     */

    public static function install_later( $new_site ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( CE_BASE ) ) {
            return;
        }

        // switch to blog
        switch_to_blog( (int) $new_site->blog_id );

        // add backend requirements, triggering the settings file to be created
        self::update_backend();

        // restore blog
        restore_current_blog();
    }


    /**
     * add or update backend requirements
     *
     * @since   1.5.0
     * @change  1.6.0
     *
     * @return  $new_option_value  new or current database option value
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
        $old_option_value = self::convert_settings( $old_option_value );

        // update default system settings
        $old_option_value = wp_parse_args( self::get_default_settings( 'system' ), $old_option_value );

        // merge defined settings into default settings
        $new_option_value = wp_parse_args( $old_option_value, self::get_default_settings() );

        // add or update database option
        update_option( 'cache_enabler', $new_option_value );

        // create settings file if action has not been registered for hook yet, like when in activation hook
        if ( has_action( 'update_option_cache_enabler', array( __CLASS__, 'on_update_backend' ) ) === false ) {
            Cache_Enabler_Disk::create_settings_file( $new_option_value );
        }

        return $new_option_value;
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
     * @change  1.5.0
     *
     * @param   WP_Site  $old_site  old site instance
     */

    public static function uninstall_later( $old_site ) {

        $delete_cache_size_transient = false;

        // clean system files
        Cache_Enabler_Disk::clean();

        // clear site cache of deleted site
        self::clear_site_cache_by_blog_id( (int) $old_site->blog_id, $delete_cache_size_transient );
    }


    /**
     * uninstall backend requirements
     *
     * @since   1.0.0
     * @change  1.4.0
     */

    private static function uninstall_backend() {

        // delete database option
        delete_option( 'cache_enabler' );
    }


    /**
     * enter each site
     *
     * @since   1.5.0
     * @change  1.5.0
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
                $callback_return[] = (int) call_user_func_array( $callback, $callback_params );
                restore_current_blog();
            }
        } else {
            $callback_return[] = (int) call_user_func_array( $callback, $callback_params );
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

        // if setting enabled clear site cache on any plugin activation or deactivation
        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_changed_plugin'] ) {
            self::clear_site_cache();
        }
    }


    /**
     * get settings from database
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  array  $settings  current settings from database
     */

    public static function get_settings() {

        // get database option value
        $settings = get_option( 'cache_enabler' );

        // if database option does not exist or settings are outdated
        if ( $settings === false || isset( $settings['version'] ) && $settings['version'] !== CE_VERSION ) {
            $settings = self::update_backend();
        }

        return $settings;
    }


    /**
     * get blog IDs
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @return  array  $blog_ids  blog IDs
     */

    private static function get_blog_ids() {

        $blog_ids = array( '1' );

        if ( is_multisite() ) {
            global $wpdb;
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        }

        return $blog_ids;
    }


    /**
     * get blog path
     *
     * @since   1.6.0
     * @change  1.6.0
     *
     * @return  string  $blog_path  blog path from site address URL, empty otherwise
     */

    public static function get_blog_path() {

        $site_url_path = parse_url( home_url(), PHP_URL_PATH );
        $site_url_path = rtrim( $site_url_path, '/' );
        $site_url_path_pieces = explode( '/', $site_url_path );

        // get last piece in case installation is in a subdirectory
        $blog_path = ( ! empty( end( $site_url_path_pieces ) ) ) ? '/' . end( $site_url_path_pieces ) . '/' : '';

        return $blog_path;
    }


    /**
     * get blog paths
     *
     * @since   1.4.0
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
     * get permalink structure
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @return  string  permalink structure
     */

    private static function get_permalink_structure() {

        // get permalink structure
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
     * get cache size
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  integer  $cache_size  cache size in bytes
     */

    public static function get_cache_size() {

        $cache_size = get_transient( self::get_cache_size_transient_name() );

        if ( ! $cache_size ) {
            $cache_size = Cache_Enabler_Disk::cache_size();
            set_transient( self::get_cache_size_transient_name(), $cache_size, MINUTE_IN_SECONDS * 15 );
        }

        return $cache_size;
    }


    /**
     * get the cache size transient name
     *
     * @since   1.5.0
     * @change  1.6.0
     *
     * @return  string  $transient_name  transient name
     */

    private static function get_cache_size_transient_name() {

        $transient_name = 'cache_enabler_cache_size';

        return $transient_name;
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
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   string  $settings_type                              default `system` settings
     * @return  array   $system_default_settings|$default_settings  only default system settings or all default settings
     */

    private static function get_default_settings( $settings_type = null ) {

        $system_default_settings = array(
            'version'             => (string) CE_VERSION,
            'permalink_structure' => (string) self::get_permalink_structure(),
        );

        if ( $settings_type === 'system' ) {
            return $system_default_settings;
        }

        $user_default_settings = array(
            'cache_expires'                      => 0,
            'cache_expiry_time'                  => 0,
            'clear_site_cache_on_saved_post'     => 0,
            'clear_site_cache_on_new_comment'    => 0,
            'clear_site_cache_on_changed_plugin' => 0,
            'compress_cache'                     => 0,
            'convert_image_urls_to_webp'         => 0,
            'excluded_post_ids'                  => '',
            'excluded_page_paths'                => '',
            'excluded_query_strings'             => '',
            'excluded_cookies'                   => '',
            'minify_html'                        => 0,
            'minify_inline_css_js'               => 0,
        );

        // merge default settings
        $default_settings = wp_parse_args( $user_default_settings, $system_default_settings );

        return $default_settings;
    }


    /**
     * convert settings to new structure
     *
     * @since   1.5.0
     * @change  1.6.0
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
            'new_comment'                            => 'clear_site_cache_on_new_comment',
            'clear_on_upgrade'                       => 'clear_site_cache_on_changed_plugin',
            'compress'                               => 'compress_cache',
            'webp'                                   => 'convert_image_urls_to_webp',
            'excl_ids'                               => 'excluded_post_ids',
            'excl_paths'                             => 'excluded_page_paths',
            'excl_cookies'                           => 'excluded_cookies',
            'incl_parameters'                        => '', // deprecated

            // 1.6.0
            'clear_complete_cache_on_saved_post'     => 'clear_site_cache_on_saved_post',
            'clear_complete_cache_on_new_comment'    => 'clear_site_cache_on_new_comment',
            'clear_complete_cache_on_changed_plugin' => 'clear_site_cache_on_changed_plugin',
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
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   array  $action_links  action links
     * @return  array  $action_links  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_action_links( $action_links ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $action_links;
        }

        // append action link
        $action_links = wp_parse_args(
            array(
                sprintf(
                    '<a href="%s">%s</a>',
                    admin_url( 'options-general.php?page=cache-enabler' ),
                    esc_html__( 'Settings', 'cache-enabler' )
                )
            ),
            $action_links
        );

        return $action_links;
    }


    /**
     * add plugin metadata in the plugins list table
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   array   $plugin_meta  plugin metadata, including the version, author, author URI, and plugin URI
     * @param   string  $plugin_file  path to the plugin file relative to the plugins directory
     * @return  array   $plugin_meta  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_row_meta( $plugin_meta, $plugin_file ) {

        // check if Cache Enabler row
        if ( $plugin_file !== CE_BASE ) {
            return $plugin_meta;
        }

        // append metadata
        $plugin_meta = wp_parse_args(
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank" rel="nofollow noopener">Documentation</a>',
            ),
            $plugin_meta
        );

        return $plugin_meta;
    }


    /**
     * add dashboard cache size count
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   array  $items  initial array with dashboard items
     * @return  array  $items  merged array with dashboard items
     */

    public static function add_dashboard_cache_size( $items = array() ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        // get cache size
        $cache_size = self::get_cache_size();

        // display items
        $items = array(
            sprintf(
                '<a href="%s" title="%s">%s %s</a>',
                admin_url( 'options-general.php?page=cache-enabler' ),
                esc_html__( 'Refreshes every 15 minutes', 'cache-enabler' ),
                ( empty( $cache_size ) ) ? esc_html__( 'Empty', 'cache-enabler' ) : size_format( $cache_size ),
                esc_html__( 'Cache Size', 'cache-enabler' )
            )
        );

        return $items;
    }


    /**
     * add admin bar items
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   object  menu properties
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
     * @change  1.5.0
     */

    public static function add_admin_resources( $hook ) {

        // settings page
        if ( $hook === 'settings_page_cache-enabler' ) {
            wp_enqueue_style( 'cache-enabler-settings', plugins_url( 'css/settings.min.css', CE_FILE ), array(), CE_VERSION );
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
     * @since   1.0.0
     * @change  1.6.0
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
            // set clear URL without query string
            $clear_url = parse_url( home_url(), PHP_URL_SCHEME ) . '://' . parse_url( home_url(), PHP_URL_HOST ) . preg_replace( '/\?.*/', '', $_SERVER['REQUEST_URI'] );
            self::clear_page_cache_by_url( $clear_url );
        // clear site(s) cache
        } elseif ( $_GET['_action'] === 'clear' ) {
            self::each_site( ( is_multisite() && is_network_admin() ), 'self::clear_site_cache' );
        }

        // redirect to same page
        wp_safe_redirect( wp_get_referer() );

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
     * @since   1.0.0
     * @change  1.6.0
     */

    public static function cache_cleared_notice() {

        // check user role
        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        if ( get_transient( self::get_cache_cleared_transient_name() ) ) {
            echo sprintf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                ( is_multisite() && is_network_admin() ) ? esc_html__( 'Network cache cleared.', 'cache-enabler' ) : esc_html__( 'Site cache cleared.', 'cache-enabler' )
            );

            delete_transient( self::get_cache_cleared_transient_name() );
        }
    }


    /**
     * save post hook
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_save_post( $post_id ) {

        // if any published post type is created or updated
        if ( get_post_status( $post_id ) === 'publish' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }


    /**
     * post updated hook
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $post_id      post ID
     * @param   WP_Post  $post_after   post instance following the update
     * @param   WP_Post  $post_before  post instance before the update
     */

    public static function on_post_updated( $post_id, $post_after, $post_before ) {

        // if setting disabled and any published post type author changes
        if ( $post_before->post_author !== $post_after->post_author ) {
            if ( ! Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ) {
                // clear before the update author archives
                self::clear_author_archives_cache_by_user_id( $post_before->post_author );
            }
        }
    }


    /**
     * trash post hook
     *
     * @since   1.4.0
     * @change  1.5.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_trash_post( $post_id ) {

        // if any published post type is trashed
        if ( get_post_status( $post_id ) === 'publish' ) {
            $trashed = true;
            self::clear_cache_on_post_save( $post_id, $trashed );
        }
    }


    /**
     * transition post status hook
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   string   $new_status  new post status
     * @param   string   $old_status  old post status
     * @param   WP_Post  $post        post instance
     */

    public static function on_transition_post_status( $new_status, $old_status, $post ) {

        // if any published post type status has changed
        if ( $old_status === 'publish' && in_array( $new_status, array( 'future', 'draft', 'pending', 'private' ) ) ) {
            self::clear_cache_on_post_save( $post->ID );
        }
    }


    /**
     * comment post hook
     *
     * @since   1.2.0
     * @change  1.6.0
     *
     * @param   integer         $comment_id        comment ID
     * @param   integer|string  $comment_approved  comment approval status
     */

    public static function on_comment_post( $comment_id, $comment_approved ) {

        // if new approved comment is posted
        if ( $comment_approved === 1 ) {
            // if setting enabled clear site cache
            if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_new_comment'] ) {
                self::clear_site_cache();
            // clear page cache otherwise
            } else {
                self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
            }
        }
    }


    /**
     * edit comment hook
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   integer  $comment_id    comment ID
     * @param   array    $comment_data  comment data
     */

    public static function on_edit_comment( $comment_id, $comment_data ) {

        $comment_approved = (int) $comment_data['comment_approved'];

        // if approved comment is edited
        if ( $comment_approved === 1 ) {
            self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
        }
    }


    /**
     * transition comment status hook
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   integer|string  $new_status  new comment status
     * @param   integer|string  $old_status  old comment status
     * @param   WP_Comment      $comment     comment instance
     */

    public static function on_transition_comment_status( $new_status, $old_status, $comment ) {

        // if comment status has changed from or to approved
        if ( $old_status === 'approved' || $new_status === 'approved' ) {
            self::clear_page_cache_by_post_id( $comment->comment_post_ID );
        }
    }


    /**
     * clear complete cache
     *
     * @since   1.0.0
     * @change  1.6.0
     */

    public static function clear_complete_cache() {

        // clear site(s) cache
        self::each_site( is_multisite(), 'self::clear_site_cache' );

        // delete cache size transient(s)
        self::each_site( is_multisite(), 'delete_transient', array( self::get_cache_size_transient_name() ) );
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
     * clear site cache
     *
     * @since   1.6.0
     * @change  1.6.0
     */

    public static function clear_site_cache() {

        self::clear_site_cache_by_blog_id( get_current_blog_id() );
    }


    /**
     * clear cached pages that might have changed from any new or updated post
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   WP_Post  $post  post instance
     */

    public static function clear_associated_cache( $post ) {

        // clear post type archives
        self::clear_post_type_archives_cache( $post->post_type );

        // clear taxonomies archives
        self::clear_taxonomies_archives_cache_by_post_id( $post->ID );

        if ( $post->post_type === 'post' ) {
            // clear author archives
            self::clear_author_archives_cache_by_user_id( $post->post_author );
            // date archives
            self::clear_date_archives_cache_by_post_id( $post->ID );
        }
    }


    /**
     * clear post type archives page cache
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   string  $post_type  post type
     */

    public static function clear_post_type_archives_cache( $post_type ) {

        // get post type archives URL
        $post_type_archives_url = get_post_type_archive_link( $post_type );

        if ( ! empty( $post_type_archives_url ) ) {
            // clear post type archives page and its pagination page(s) cache
            self::clear_page_cache_by_url( $post_type_archives_url, 'pagination' );
        }
    }


    /**
     * clear taxonomies archives pages cache by post ID
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function clear_taxonomies_archives_cache_by_post_id( $post_id ) {

        // get taxonomies
        $taxonomies = get_taxonomies();

        foreach ( $taxonomies as $taxonomy ) {
            if ( wp_count_terms( $taxonomy ) > 0 ) {
                // get terms attached to post
                $term_ids = wp_get_post_terms( $post_id, $taxonomy,  array( 'fields' => 'ids' ) );
                foreach ( $term_ids as $term_id ) {
                    $term_archives_url = get_term_link( (int) $term_id, $taxonomy );
                    // validate URL and ensure it does not have a query string
                    if ( filter_var( $term_archives_url, FILTER_VALIDATE_URL ) && ! filter_var( $term_archives_url, FILTER_VALIDATE_URL, FILTER_FLAG_QUERY_REQUIRED ) ) {
                        // clear taxonomy archives page and its pagination page(s) cache
                        self::clear_page_cache_by_url( $term_archives_url, 'pagination' );
                    }
                }
            }
        }
    }


    /**
     * clear author archives page cache by user ID
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $user_id  user ID of the author
     */

    public static function clear_author_archives_cache_by_user_id( $user_id ) {

        // get author archives URL
        $author_username     = get_the_author_meta( 'user_login', $user_id );
        $author_base         = $GLOBALS['wp_rewrite']->author_base;
        $author_archives_url = home_url( '/' ) . $author_base . '/' . $author_username;

        // clear author archives page and its pagination page(s) cache
        self::clear_page_cache_by_url( $author_archives_url, 'pagination' );
    }


    /**
     * clear date archives pages cache
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function clear_date_archives_cache_by_post_id( $post_id ) {

        // get post dates
        $post_date_day   = get_the_date( 'd', $post_id );
        $post_date_month = get_the_date( 'm', $post_id );
        $post_date_year  = get_the_date( 'Y', $post_id );

        // get post dates archives URLs
        $date_archives_day_url   = get_day_link( $post_date_year, $post_date_month, $post_date_day );
        $date_archives_month_url = get_month_link( $post_date_year, $post_date_month );
        $date_archives_year_url  = get_year_link( $post_date_year );

        // clear date archives pages and their pagination pages cache
        self::clear_page_cache_by_url( $date_archives_day_url, 'pagination' );
        self::clear_page_cache_by_url( $date_archives_month_url, 'pagination' );
        self::clear_page_cache_by_url( $date_archives_year_url, 'pagination' );
    }


    /**
     * clear page cache by post ID
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   integer|string  $post_id     post ID
     * @param   string          $clear_type  clear the `pagination` cache or all `subpages` cache instead of only the `page` cache
     */

    public static function clear_page_cache_by_post_id( $post_id, $clear_type = 'page'  ) {

        // validate integer
        if ( ! is_int( $post_id ) ) {
            // if string try to convert to integer
            $post_id = (int) $post_id;
            // conversion failed
            if ( ! $post_id ) {
                return;
            }
        }

        // clear page cache
        self::clear_page_cache_by_url( get_permalink( $post_id ), $clear_type );
    }


    /**
     * clear page cache by URL
     *
     * @since   1.0.0
     * @change  1.6.0
     *
     * @param   string  $clear_url   full URL to potentially cached page
     * @param   string  $clear_type  clear the `pagination` cache or all `subpages` cache instead of only the `page` cache
     */

    public static function clear_page_cache_by_url( $clear_url, $clear_type = 'page' ) {

        Cache_Enabler_Disk::clear_cache( $clear_url, $clear_type );
    }


    /**
     * clear site cache by blog ID
     *
     * @since   1.4.0
     * @change  1.6.0
     *
     * @param   integer|string  $blog_id                      blog ID
     * @param   boolean         $delete_cache_size_transient  whether or not the cache size transient should be deleted
     */

    public static function clear_site_cache_by_blog_id( $blog_id, $delete_cache_size_transient = true ) {

        // validate integer
        if ( ! is_int( $blog_id ) ) {
            // if string try to convert to integer
            $blog_id = (int) $blog_id;
            // conversion failed
            if ( ! $blog_id ) {
                return;
            }
        }

        // check if blog ID exists
        if ( ! in_array( $blog_id, self::get_blog_ids() ) ) {
            return;
        }

        // disable page cache cleared hook
        self::$fire_page_cache_cleared_hook = false;

        // get site URL
        $site_url = get_home_url( $blog_id );

        // get site objects
        $site_objects = Cache_Enabler_Disk::get_site_objects( $site_url );

        // clear all first level pages and subpages cache
        foreach ( $site_objects as $site_object ) {
            self::clear_page_cache_by_url( trailingslashit( $site_url ) . $site_object, 'subpages' );
        }

        // clear home page cache
        self::clear_page_cache_by_url( $site_url );

        // delete cache size transient
        if ( $delete_cache_size_transient ) {
            if ( is_multisite() ) {
                switch_to_blog( $blog_id );
                delete_transient( self::get_cache_size_transient_name() );
                restore_current_blog();
            } else {
                delete_transient( self::get_cache_size_transient_name() );
            }
        }
    }


    /**
     * clear cache when any post type is created or updated
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   integer  $post_id  post ID
     * @param   boolean  $trashed  whether this is an existing post being trashed
     */

    public static function clear_cache_on_post_save( $post_id, $trashed = false ) {

        // get post data
        $post = get_post( $post_id );

        // if setting enabled clear site cache
        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ) {
            self::clear_site_cache();
        // clear page and/or associated cache otherwise
        } else {
            // if updated or trashed clear page cache
            if ( strtotime( $post->post_modified_gmt ) > strtotime( $post->post_date_gmt ) || $trashed ) {
                self::clear_page_cache_by_post_id( $post_id );
            }

            // clear associated cache
            self::clear_associated_cache( $post );
        }
    }


    /**
     * check plugin requirements
     *
     * @since   1.1.0
     * @change  1.6.0
     */

    public static function requirements_check() {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check WordPress version
        if ( version_compare( $GLOBALS['wp_version'], CE_MIN_WP . 'alpha', '<' ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. WordPress version (e.g. 5.1)
                    esc_html__( 'The %1$s plugin is optimized for WordPress %2$s. Please disable the plugin or upgrade your WordPress installation (recommended).', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    CE_MIN_WP
                )
            );
        }

        // check permalink structure
        if ( Cache_Enabler_Engine::$settings['permalink_structure'] === 'plain' && current_user_can( 'manage_options' ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. Permalink Settings
                    esc_html__( 'The %1$s plugin requires a custom permalink structure to start caching properly. Please enable a custom structure in the %2$s.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    sprintf(
                        '<a href="%s">%s</a>',
                        admin_url( 'options-permalink.php' ),
                        esc_html__( 'Permalink Settings', 'cache-enabler' )
                    )
                )
            );
        }

        // check permissions
        if ( file_exists( Cache_Enabler_Disk::$cache_dir ) && ! is_writable( Cache_Enabler_Disk::$cache_dir ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. 755 3. wp-content/cache 4. file permissions
                    esc_html__( 'The %1$s plugin requires write permissions %2$s in %3$s. Please change the %4$s.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    '<code>755</code>',
                    '<code>wp-content/cache</code>',
                    sprintf(
                        '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                        'https://wordpress.org/support/article/changing-file-permissions/',
                        esc_html__( 'file permissions', 'cache-enabler' )
                    )
                )
            );
        }

        // check Autoptimize HTML optimization
        if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && Cache_Enabler_Engine::$settings['minify_html'] && get_option( 'autoptimize_html', '' ) !== '' ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Autoptimize 2. Cache Enabler Settings
                    esc_html__( 'The %1$s plugin HTML optimization is enabled. Please disable HTML minification in the %2$s.', 'cache-enabler' ),
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
     * @change  1.6.0
     *
     * @param   array  $settings            user defined settings
     * @return  array  $validated_settings  validated settings
     */

    public static function validate_settings( $settings ) {

        // validate array
        if ( ! is_array( $settings ) ) {
            return;
        }

        $validated_settings = array(
            'cache_expires'                      => (int) ( ! empty( $settings['cache_expires'] ) ),
            'cache_expiry_time'                  => (int) @$settings['cache_expiry_time'],
            'clear_site_cache_on_saved_post'     => (int) ( ! empty( $settings['clear_site_cache_on_saved_post'] ) ),
            'clear_site_cache_on_new_comment'    => (int) ( ! empty( $settings['clear_site_cache_on_new_comment'] ) ),
            'clear_site_cache_on_changed_plugin' => (int) ( ! empty( $settings['clear_site_cache_on_changed_plugin'] ) ),
            'compress_cache'                     => (int) ( ! empty( $settings['compress_cache'] ) ),
            'convert_image_urls_to_webp'         => (int) ( ! empty( $settings['convert_image_urls_to_webp'] ) ),
            'excluded_post_ids'                  => (string) sanitize_text_field( @$settings['excluded_post_ids'] ),
            'excluded_page_paths'                => (string) self::validate_regex( @$settings['excluded_page_paths'] ),
            'excluded_query_strings'             => (string) self::validate_regex( @$settings['excluded_query_strings'] ),
            'excluded_cookies'                   => (string) self::validate_regex( @$settings['excluded_cookies'] ),
            'minify_html'                        => (int) ( ! empty( $settings['minify_html'] ) ),
            'minify_inline_css_js'               => (int) ( ! empty( $settings['minify_inline_css_js'] ) ),
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
     * @change  1.6.0
     */

    public static function settings_page() {

        ?>

        <div id="cache_enabler_settings" class="wrap">
            <h2>
                <?php esc_html_e( 'Cache Enabler Settings', 'cache-enabler' ); ?>
            </h2>

            <?php
            if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. define( 'WP_CACHE', true ); 2. wp-config.php
                        esc_html__( 'Caching is disabled because %1$s is not set in the %2$s file.', 'cache-enabler' ),
                        "<code>define( 'WP_CACHE', true );</code>",
                        '<code>wp-config.php</code>'
                    )
                );
            }
            ?>

            <div class="notice notice-info">
                <p>
                <?php
                printf(
                    // translators: %s: KeyCDN
                    esc_html__( 'Combine %s with Cache Enabler for even better WordPress performance and achieve the next level of caching with a CDN.', 'cache-enabler' ),
                    '<strong><a href="https://www.keycdn.com?utm_source=wp-admin&utm_medium=plugins&utm_campaign=cache-enabler">KeyCDN</a></strong>'
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
                                <label for="cache_expires" class="checkbox--form-control">
                                    <input name="cache_enabler[cache_expires]" type="checkbox" id="cache_expires" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['cache_expires'] ); ?> />
                                </label>
                                <label for="cache_expiry_time">
                                    <?php
                                    printf(
                                        // translators: %s: Number of hours.
                                        esc_html__( 'Cached pages expire %s hours after being created.', 'cache-enabler' ),
                                        '<input name="cache_enabler[cache_expiry_time]" type="number" id="cache_expiry_time" value="' . Cache_Enabler_Engine::$settings['cache_expiry_time'] . '" class="small-text">'
                                    );
                                    ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Clearing', 'cache-enabler' ); ?></p>
                                <label for="clear_site_cache_on_saved_post">
                                    <input name="cache_enabler[clear_site_cache_on_saved_post]" type="checkbox" id="clear_site_cache_on_saved_post" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if any post type has been published, updated, or trashed (instead of only the page and/or associated cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="clear_site_cache_on_new_comment">
                                    <input name="cache_enabler[clear_site_cache_on_new_comment]" type="checkbox" id="clear_site_cache_on_new_comment" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_new_comment'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if a new comment has been posted (instead of only the page cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="clear_site_cache_on_changed_plugin">
                                    <input name="cache_enabler[clear_site_cache_on_changed_plugin]" type="checkbox" id="clear_site_cache_on_changed_plugin" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['clear_site_cache_on_changed_plugin'] ); ?> />
                                    <?php esc_html_e( 'Clear the site cache if any plugin has been activated, updated, or deactivated.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Variants', 'cache-enabler' ); ?></p>
                                <label for="convert_image_urls_to_webp">
                                    <input name="cache_enabler[convert_image_urls_to_webp]" type="checkbox" id="convert_image_urls_to_webp" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['convert_image_urls_to_webp'] ); ?> />
                                    <?php
                                    printf(
                                        // translators: %s: Optimus
                                        esc_html__( 'Create an additional cached version for WebP image support. Convert your images to WebP with %s.', 'cache-enabler' ),
                                        '<a href="https://optimus.io" target="_blank" rel="nofollow noopener">Optimus</a>'
                                    );
                                    ?>
                                </label>

                                <br />

                                <label for="compress_cache">
                                    <input name="cache_enabler[compress_cache]" type="checkbox" id="compress_cache" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['compress_cache'] ); ?> />
                                    <?php esc_html_e( 'Pre-compress cached pages with Gzip.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Minification', 'cache-enabler' ); ?></p>
                                <label for="minify_html" class="checkbox--form-control">
                                    <input name="cache_enabler[minify_html]" type="checkbox" id="minify_html" value="1" <?php checked( '1', Cache_Enabler_Engine::$settings['minify_html'] ); ?> />
                                </label>
                                <label for="minify_inline_css_js">
                                    <?php
                                    $minify_inline_css_js_options = array(
                                        esc_html__( 'excluding', 'cache-enabler' ) => 0,
                                        esc_html__( 'including', 'cache-enabler' ) => 1,
                                    );
                                    $minify_inline_css_js = '<select name="cache_enabler[minify_inline_css_js]" id="minify_inline_css_js">';
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
                                <label for="excluded_post_ids">
                                    <input name="cache_enabler[excluded_post_ids]" type="text" id="excluded_post_ids" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_post_ids'] ) ?>" class="regular-text" />
                                    <p class="description">
                                    <?php
                                    // translators: %s: ,
                                    printf( esc_html__( 'Post IDs separated by a %s that should bypass the cache.', 'cache-enabler' ), '<code class="code--form-control">,</code>' );
                                    ?>
                                    </p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">2,43,65</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Page Paths', 'cache-enabler' ); ?></p>
                                <label for="excluded_page_paths">
                                    <input name="cache_enabler[excluded_page_paths]" type="text" id="excluded_page_paths" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_page_paths'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching page paths that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">/^(\/|\/forums\/)$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Query Strings', 'cache-enabler' ); ?></p>
                                <label for="excluded_query_strings">
                                    <input name="cache_enabler[excluded_query_strings]" type="text" id="excluded_query_strings" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_query_strings'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching query strings that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code class="code--form-control">/^nocache$/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code class="code--form-control">/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Cookies', 'cache-enabler' ); ?></p>
                                <label for="excluded_cookies">
                                    <input name="cache_enabler[excluded_cookies]" type="text" id="excluded_cookies" value="<?php echo esc_attr( Cache_Enabler_Engine::$settings['excluded_cookies'] ) ?>" class="regular-text code" />
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
