<?php


// exit
defined( 'ABSPATH' ) || exit;


/**
 * Cache_Enabler
 *
 * @since  1.0.0
 */

final class Cache_Enabler {


    /**
     * plugin options
     *
     * @since  1.0.0
     * @var    array
     */

    public static $options;


    /**
     * disk cache object
     *
     * @since  1.0.0
     * @var    object
     */

    private static $disk;


    /**
     * minify default settings
     *
     * @since  1.0.0
     * @var    integer
     */

    const MINIFY_DISABLED  = 0;
    const MINIFY_HTML_ONLY = 1;
    const MINIFY_HTML_JS   = 2;


    /**
     * constructor wrapper
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function instance() {

        new self();
    }


    /**
     * constructor
     *
     * @since   1.0.0
     * @change  1.4.4
     */

    public function __construct() {

        // set default vars
        self::_set_default_vars();

        // init hooks
        add_action( 'init', array( __CLASS__, 'process_clear_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );
        add_action( 'init', array( __CLASS__, 'register_publish_hooks' ), 99 );

        // clear cache hooks
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post_id' ) );
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_total_cache' ) );
        add_action( '_core_updated_successfully', array( __CLASS__, 'clear_total_cache' ) );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'switch_theme', array( __CLASS__, 'clear_total_cache' ) );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );
        add_action( 'permalink_structure_changed', array( __CLASS__, 'clear_total_cache' ) );
        // third party
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_total_cache' ) );
        add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );

        // advanced cache hooks
        add_action( 'permalink_structure_changed', array( __CLASS__, 'create_advcache_settings' ) );
        add_action( 'save_post', array( __CLASS__, 'check_future_posts' ) );

        // admin bar hooks
        add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_links' ), 90 );

        // admin interface hooks
        if ( is_admin() ) {
            // multisite
            add_action( 'wp_initialize_site', array( __CLASS__, 'install_later' ) );
            add_action( 'wp_uninitialize_site', array( __CLASS__, 'uninstall_later' ) );
            // settings
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_resources' ) );
            add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
            // comments
            add_action( 'transition_comment_status', array( __CLASS__, 'change_comment' ), 10, 3 );
            add_action( 'comment_post', array( __CLASS__, 'comment_post' ), 99, 2 );
            add_action( 'edit_comment', array( __CLASS__, 'edit_comment' ) );
            // dashboard
            add_filter( 'dashboard_glance_items', array( __CLASS__, 'add_dashboard_count' ) );
            add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_clear_dropdown' ) );
            add_filter( 'plugin_action_links_' . CE_BASE, array( __CLASS__, 'action_links' ) );
            // warnings and notices
            add_action( 'admin_notices', array( __CLASS__, 'warning_is_permalink' ) );
            add_action( 'admin_notices', array( __CLASS__, 'requirements_check' ) );
        // caching hooks
        } else {
            // comments
            add_action( 'pre_comment_approved', array( __CLASS__, 'new_comment' ), 99, 2 );
            // output buffer
            add_action( 'template_redirect', array( __CLASS__, 'handle_cache' ), 0 );
        }
    }


    /**
     * activation hook
     *
     * @since   1.0.0
     * @change  1.4.5
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_activation( $network_wide ) {

        // activation requirements
        self::on_ce_activation_deactivation( 'activated', $network_wide );

        // set WP_CACHE if not already set
        if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
            self::_set_wp_cache();
        }

        // copy advanced cache file
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * deactivation hook
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   boolean  $network_wide  network deactivated
     */

    public static function on_deactivation( $network_wide ) {

        // deactivation requirements
        self::on_ce_activation_deactivation( 'deactivated', $network_wide );

        // unset WP_CACHE
        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            self::_set_wp_cache( false );
        }

        // delete advanced cache file
        unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * Cache Enabler activation and deactivation actions
     *
     * @since   1.4.0
     * @change  1.4.0
     *
     * @param   string   $action        activated or deactivated
     * @param   boolean  $network_wide  network activated or deactivated
     */

    public static function on_ce_activation_deactivation( $action, $network_wide ) {

        // network activated
        if ( is_multisite() && $network_wide ) {
            // blog IDs
            $blog_ids = self::_get_blog_ids();

            // switch to each blog in network
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );

                if ( $action === 'activated' ) {
                    // install requirements
                    self::_install_backend();
                }

                if ( $action === 'deactivated' ) {
                    // delete advanced cache settings file
                    Cache_Enabler_Disk::delete_advcache_settings();
                }
            }

            // restore blog
            restore_current_blog();
        // site activated
        } else {
            if ( $action === 'activated') {
                // install requirements
                self::_install_backend();
            }

            if ( $action === 'deactivated') {
                // delete advanced cache settings file
                Cache_Enabler_Disk::delete_advcache_settings();
            }
        }

        if ( $action === 'deactivated') {
            // clear complete cache
            self::clear_total_cache();
        }
    }


    /**
     * plugin activation and deactivation hooks
     *
     * @since   1.4.0
     * @change  1.4.0
     */

    public static function on_plugin_activation_deactivation() {

        // if option enabled clear complete cache on any plugin activation or deactivation
        if ( self::$options['clear_on_upgrade'] ) {
            self::clear_total_cache();
        }
    }


    /**
     * upgrade hook
     *
     * @since   1.2.3
     * @change  1.4.0
     *
     * @param   WP_Upgrader  $obj   upgrade instance
     * @param   array        $data  update data
     */

    public static function on_upgrade( $obj, $data ) {

        // if option enabled clear complete cache on any plugin update
        if ( self::$options['clear_on_upgrade'] ) {
            self::clear_total_cache();
        }

        // check updated plugins
        if ( $data['action'] === 'update' && $data['type'] === 'plugin' && array_key_exists( 'plugins', $data ) ) {
            foreach ( (array) $data['plugins'] as $each_plugin ) {
                // if Cache Enabler has been updated
                if ( $each_plugin === CE_BASE ) {
                    // update requirements
                    if ( is_multisite() && is_plugin_active_for_network( CE_BASE ) ) {
                        $network_wide = true;
                        self::on_ce_update( $network_wide );
                    } else {
                        $network_wide = false;
                        self::on_ce_update( $network_wide );
                    }
                }
            }
        }
    }


    /**
     * Cache Enabler update actions
     *
     * @since   1.4.0
     * @change  1.4.5
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_ce_update( $network_wide ) {

        // delete advanced cache settings file(s) and clear complete cache
        self::on_ce_activation_deactivation( 'deactivated', $network_wide );
        // decom: delete old advanced cache settings file(s) (1.4.0)
        array_map( 'unlink', glob( WP_CONTENT_DIR . '/cache/cache-enabler-advcache-*.json' ) );
        // clean: delete incorrect advanced cache settings file(s) that may have been created (1.4.5)
        array_map( 'unlink', glob( ABSPATH . '/CE_SETTINGS_PATH-*.json' ) );

        // create advanced cache settings file(s)
        self::on_ce_activation_deactivation( 'activated', $network_wide );

        // update advanced cache file that might have changed
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * create or update advanced cache settings
     *
     * @since   1.4.0
     * @change  1.4.3
     */

    public static function create_advcache_settings() {

        // ignore results and create advanced cache settings file
        self::validate_settings( self::_get_options() );
    }


    /**
     * install Cache Enabler on new site in multisite network
     *
     * @since   1.0.0
     * @change  1.4.0
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

        // install requirements
        self::_install_backend();

        // restore blog
        restore_current_blog();
    }


    /**
     * installation requirements
     *
     * @since   1.0.0
     * @change  1.4.0
     */

    private static function _install_backend() {

        // add default Cache Enabler option if not already added
        add_option( 'cache-enabler', array() );

        // create advanced cache settings file
        self::create_advcache_settings();
    }


    /**
     * uninstall Cache Enabler
     *
     * @since   1.0.0
     * @change  1.4.0
     */

    public static function on_uninstall() {

        // network
        if ( is_multisite() ) {
            // blog IDs
            $blog_ids = self::_get_blog_ids();

            // switch to each blog in network
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                // uninstall requirements
                self::_uninstall_backend();
            }

            // restore blog
            restore_current_blog();
        // site
        } else {
            // uninstall requirements
            self::_uninstall_backend();
        }

        // clear complete cache
        self::clear_total_cache();
    }


    /**
     * uninstall Cache Enabler on deleted site in multisite network
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   WP_Site  $old_site  old site instance
     */

    public static function uninstall_later( $old_site ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( CE_BASE ) ) {
            return;
        }

        // delete advanced cache settings file
        Cache_Enabler_Disk::delete_advcache_settings();

        // clear complete cache of deleted site
        self::clear_blog_id_cache( (int) $old_site->blog_id );
    }


    /**
     * uninstall installation requirements
     *
     * @since   1.0.0
     * @change  1.4.0
     */

    private static function _uninstall_backend() {

        // delete Cache Enabler option
        delete_option( 'cache-enabler' );
    }


    /**
     * set or unset WP_CACHE constant
     *
     * @since   1.1.1
     * @change  1.4.7
     *
     * @param   boolean  $wp_cache_value  true to set WP_CACHE constant in wp-config.php, false to unset
     */

    private static function _set_wp_cache( $wp_cache_value = true ) {

        // get config file
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            // config file resides in ABSPATH
            $wp_config_file = ABSPATH . 'wp-config.php';
        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            // config file resides one level above ABSPATH but is not part of another installation
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        }

        // check if config file can be written to
        if ( is_writable( $wp_config_file ) ) {
            // get config file as array
            $wp_config = file( $wp_config_file );

            // set Cache Enabler line
            if ( $wp_cache_value ) {
                $wp_cache_ce_line = "define( 'WP_CACHE', true ); // Added by Cache Enabler" . "\r\n";
            } else {
                $wp_cache_ce_line = '';
            }

            // search for WP_CACHE constant
            $found_wp_cache = false;
            foreach ( $wp_config as &$line ) {
                if ( preg_match( '/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(.*)\s*\);/', $line ) ) {
                    // found WP_CACHE constant
                    $found_wp_cache = true;
                    // check if constant was set by Cache Enabler
                    if ( preg_match( '/\/\/\sAdded\sby\sCache\sEnabler/', $line ) ) {
                        // update Cache Enabler line
                        $line = $wp_cache_ce_line;
                    }

                    break;
                }
            }

            // add WP_CACHE if not found
            if ( ! $found_wp_cache ) {
                array_shift( $wp_config );
                array_unshift( $wp_config, "<?php\r\n", $wp_cache_ce_line );
            }

            // write config file
            $fh = @fopen( $wp_config_file, 'w' );
            foreach( $wp_config as $ln ) {
                @fwrite( $fh, $ln );
            }

            @fclose( $fh );
        }
    }


    /**
     * set default vars
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _set_default_vars() {

        // get Cache Enabler options
        self::$options = self::_get_options();

        // disk cache
        if ( Cache_Enabler_Disk::is_permalink() ) {
            self::$disk = new Cache_Enabler_Disk;
        }
    }


    /**
     * get blog IDs
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  blog IDs array
     */

    private static function _get_blog_ids() {

        global $wpdb;

        return $wpdb->get_col("SELECT blog_id FROM `$wpdb->blogs`");
    }


    /**
     * get blog paths
     *
     * @since   1.4.0
     * @change  1.4.0
     *
     * @return  array  blog paths array
     */

    private static function _get_blog_paths() {

        global $wpdb;

        return $wpdb->get_col("SELECT path FROM `$wpdb->blogs`");
    }


    /**
     * get Cache Enabler options
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @return  array  Cache Enabler options
     */

    private static function _get_options() {

        // decom: rename option
        $ce_leg = get_option( 'cache' );
        if ( ! empty( $ce_leg ) ) {
            delete_option( 'cache' );
            add_option(
                'cache-enabler',
                $ce_leg
            );
        }

        // decom: rename options
        $options = get_option( 'cache-enabler', array() );
        // excl_regexp to excl_paths (1.4.0)
        if ( ! empty( $options ) && array_key_exists( 'excl_regexp', $options ) ) {
            $options['excl_paths'] = $options['excl_regexp'];
            unset( $options['excl_regexp'] );
            update_option( 'cache-enabler', $options );
        }
        // incl_attributes to incl_parameters (1.4.0)
        if ( ! empty( $options ) && array_key_exists( 'incl_attributes', $options ) ) {
            $options['incl_parameters'] = $options['incl_attributes'];
            unset( $options['incl_attributes'] );
            update_option( 'cache-enabler', $options );
        }

        return wp_parse_args(
            get_option( 'cache-enabler' ),
            array(
                'expires'              => 0,
                'clear_on_upgrade'     => 0,
                'new_post'             => 0,
                'new_comment'          => 0,
                'update_product_stock' => 0,
                'compress'             => 0,
                'webp'                 => 0,
                'excl_ids'             => '',
                'excl_paths'           => '',
                'excl_cookies'         => '',
                'incl_parameters'      => '',
                'minify_html'          => self::MINIFY_DISABLED,
            )
        );
    }


    /**
     * warning if no custom permlink structure
     *
     * @since   1.0.0
     * @change  1.4.5
     */

    public static function warning_is_permalink() {

        if ( ! Cache_Enabler_Disk::is_permalink() && current_user_can( 'manage_options' ) ) {

            show_message(
                sprintf(
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
                )
            );
        }
    }


    /**
     * add action links
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   array  $data  existing links
     * @return  array  $data  appended links
     */

    public static function action_links( $data ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $data;
        }

        return array_merge(
            $data,
            array(
                sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        array(
                            'page' => 'cache-enabler',
                        ),
                        admin_url( 'options-general.php' )
                    ),
                    esc_html__( 'Settings', 'cache-enabler' )
                )
            )
        );
    }


    /**
     * Cache Enabler meta links
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   array   $input  existing links
     * @param   string  $page   page
     * @return  array   $data   appended links
     */

    public static function row_meta( $input, $page ) {

        // check permissions
        if ( $page !== CE_BASE ) {
            return $input;
        }

        return array_merge(
            $input,
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank">Documentation</a>',
            )
        );
    }


    /**
     * add dashboard cache size count
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @param   array  $items  initial array with dashboard items
     * @return  array  $items  merged array with dashboard items
     */

    public static function add_dashboard_count( $items = array() ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        // get cache size
        $size = self::get_cache_size();

        // display items
        $items = array(
            sprintf(
                '<a href="%s" title="%s">%s %s</a>',
                add_query_arg(
                    array(
                        'page' => 'cache-enabler',
                    ),
                    admin_url( 'options-general.php' )
                ),
                esc_html__( 'Disk Cache', 'cache-enabler' ),
                ( empty( $size ) ? esc_html__( 'Empty', 'cache-enabler' ) : size_format( $size ) ),
                esc_html__( 'Cache Size', 'cache-enabler' )
            )
        );

        return $items;
    }


    /**
     * get cache size
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  integer  $size  cache size (bytes)
     */

    public static function get_cache_size() {

        if ( ! $size = get_transient( 'cache_size' ) ) {

            $size = ( is_object( self::$disk ) ) ? (int) self::$disk->cache_size( CE_CACHE_DIR ) : 0;

            // set transient
            set_transient(
                'cache_size',
                $size,
                60 * 15
            );
        }

        return $size;
    }


    /**
     * get blog domain
     *
     * @since   1.4.0
     * @change  1.4.0
     *
     * @return  string  $domain  current blog domain
     */

    public static function get_blog_domain() {

        // get current blog domain
        $domain = parse_url( get_site_url(), PHP_URL_HOST );

        // check if empty when creating new site in network
        if ( is_multisite() && empty( $domain ) ) {
            $domain = get_blog_details()->domain;
        }

        return $domain;
    }


    /**
     * add admin links
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   object  menu properties
     *
     * @hook    mixed   user_can_clear_cache
     */

    public static function add_admin_links( $wp_admin_bar ) {

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // get clear complete cache button title
        $title = ( is_multisite() && is_network_admin() ) ? esc_html__( 'Clear Network Cache', 'cache-enabler' ) : esc_html__( 'Clear Cache', 'cache-enabler' );

        // add Clear Cache or Clear Network Cache button in admin bar
        $wp_admin_bar->add_menu(
            array(
                'id'     => 'clear-cache',
                'href'   => wp_nonce_url( add_query_arg( array(
                                '_cache'  => 'cache-enabler',
                                '_action' => 'clear',
                                '_cid'    => time(),
                            ) ), '_cache__clear_nonce' ),
                'parent' => 'top-secondary',
                'title'  => '<span class="ab-item">' . $title . '</span>',
                'meta'   => array(
                                'title' => $title,
                            ),
            )
        );

        // add Clear URL Cache button in admin bar
        if ( ! is_admin() ) {
            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'clear-url-cache',
                    'href'   => wp_nonce_url( add_query_arg( array(
                                    '_cache'  => 'cache-enabler',
                                    '_action' => 'clearurl',
                                    '_cid'    => time(),
                                ) ), '_cache__clear_nonce' ),
                    'parent' => 'top-secondary',
                    'title'  => '<span class="ab-item">' . esc_html__( 'Clear URL Cache', 'cache-enabler' ) . '</span>',
                    'meta'   => array(
                                    'title' => esc_html__( 'Clear URL Cache', 'cache-enabler' ),
                                ),
                )
            );
        }
    }


    /**
     * process clear request
     *
     * @since   1.0.0
     * @change  1.4.6
     *
     * @param   array  $data  array of metadata
     */

    public static function process_clear_request( $data ) {

        // check if clear request
        if ( empty( $_GET['_cache'] ) || empty( $_GET['_action'] ) || $_GET['_cache'] !== 'cache-enabler' && ( $_GET['_action'] !== 'clear' || $_GET['_action'] !== 'clearurl' ) ) {
            return;
        }

        // validate clear ID (prevent duplicate processing)
        if ( empty( $_GET['_cid'] ) || ! empty( $_COOKIE['cache_enabler_clear_id'] ) && $_COOKIE['cache_enabler_clear_id'] === $_GET['_cid'] ) {
            return;
        }

        // validate nonce
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], '_cache__clear_nonce' ) ) {
            return;
        }

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // load if network activated
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        // set clear ID cookie
        setcookie( 'cache_enabler_clear_id', $_GET['_cid'] );

        // set clear URL without query string
        $clear_url = preg_replace( '/\?.*/', '', home_url( add_query_arg( null, null ) ) );

        // network activated
        if ( is_multisite() && is_plugin_active_for_network( CE_BASE ) ) {
            // network admin
            if ( is_network_admin() && $_GET['_action'] === 'clear' ) {
                // clear complete cache
                self::clear_total_cache();

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'network_admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice',
                        )
                    );
                }
            // site admin
            } else {
                if ( $_GET['_action'] === 'clearurl' ) {
                    // clear specific site URL cache
                    self::clear_page_cache_by_url( $clear_url );
                } elseif ( $_GET['_action'] === 'clear' ) {
                    // clear specific site complete cache
                    self::clear_blog_id_cache( get_current_blog_id() );

                    // clear notice
                    if ( is_admin() ) {
                        add_action(
                            'admin_notices',
                            array(
                                __CLASS__,
                                'clear_notice',
                            )
                        );
                    }
                }
            }
        // site activated
        } else {
            if ( $_GET['_action'] === 'clearurl' ) {
                // clear URL cache
                self::clear_page_cache_by_url( $clear_url );
            } elseif ( $_GET['_action'] === 'clear' ) {
                // clear complete cache
                self::clear_total_cache();

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice',
                        )
                    );
                }
            }
        }

        if ( ! is_admin() ) {
            wp_safe_redirect(
                remove_query_arg(
                    '_cache',
                    wp_get_referer()
                )
            );

            exit();
        }
    }


    /**
     * notification after clear cache
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @hook    mixed  user_can_clear_cache
     */

    public static function clear_notice() {

        // check if admin
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return false;
        }

        echo sprintf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__( 'The cache has been cleared.', 'cache-enabler' )
        );
    }


    /**
     * clear cache if post comment
     *
     * @since   1.2.0
     * @change  1.4.0
     *
     * @param   integer  $comment_id  comment ID
     * @param   mixed    $approved    approval status
     */

    public static function comment_post( $comment_id, $approved ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            // if option enabled clear complete cache on new comment
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
            }
        }
    }


    /**
     * clear cache if edit comment
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   integer  $comment_id  comment ID
     */

    public static function edit_comment( $comment_id ) {

        // if option enabled clear complete cache on new comment
        if ( self::$options['new_comment'] ) {
            self::clear_total_cache();
        } else {
            self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
        }
    }


    /**
     * clear cache if new comment
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   mixed  $approved  approval status
     * @param   array  $comment
     * @return  mixed  $approved  approval status
     */

    public static function new_comment( $approved, $comment ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            // if option enabled clear complete cache on new comment
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment['comment_post_ID'] );
            }
        }

        return $approved;
    }


    /**
     * clear cache if comment status changes
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $after_status
     * @param   string  $before_status
     * @param   object  $comment
     */

    public static function change_comment( $after_status, $before_status, $comment ) {

        // check if changes occured
        if ( $after_status !== $before_status ) {
            // if option enabled clear complete cache on new comment
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment->comment_post_ID );
            }
        }
    }


    /**
     * register publish hooks for custom post types
     *
     * @since   1.0.0
     * @change  1.2.3
     */

    public static function register_publish_hooks() {

        // get post types
        $post_types = get_post_types(
            array(
                'public' => true,
            )
        );

        // check if empty
        if ( empty( $post_types ) ) {
            return;
        }

        // post type actions
        foreach ( $post_types as $post_type ) {
            add_action(
                'publish_' . $post_type,
                array(
                    __CLASS__,
                    'publish_post_types',
                ),
                10,
                2
            );
            add_action(
                'publish_future_' . $post_type,
                function( $post_id ) {
                    // if option enabled clear complete cache on new post
                    if ( self::$options['new_post'] ) {
                        self::clear_total_cache();
                    } else {
                        self::clear_home_page_cache();
                    }
                }
            );
        }
    }


    /**
     * delete post type cache on post updates
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function publish_post_types( $post_id, $post ) {

        // check if post ID or post is empty
        if ( empty( $post_id ) || empty( $post ) ) {
            return;
        }

        // check post status
        if ( ! in_array( $post->post_status, array( 'publish', 'future' ) ) ) {
            return;
        }

        // clear cache on post publish
        if ( ! isset( $_POST['_clear_post_cache_on_update'] ) &&  $post->post_date_gmt === $post->post_modified_gmt ) {

            // if option enabled clear complete cache on new post
            if ( self::$options['new_post'] ) {
                return self::clear_total_cache();
            } else {
                return self::clear_home_page_cache();
            }

        }

        // validate nonce
        if ( ! isset( $_POST['_cache__status_nonce_' . $post_id] ) || ! wp_verify_nonce( $_POST['_cache__status_nonce_' . $post_id], CE_BASE ) ) {
            return;
        }

        // validate user role
        if ( ! current_user_can( 'publish_posts' ) ) {
            return;
        }

        // get clear cache publishing action
        $clear_post_cache = (int) $_POST['_clear_post_cache_on_update'];

        // save user metadata
        update_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            $clear_post_cache
        );

        // clear cache on post publishing action
        if ( $clear_post_cache ) {
            self::clear_total_cache();
        } else {
            self::clear_page_cache_by_post_id( $post_id );
        }
    }


    /**
     * trash post hook
     *
     * @since   1.4.0
     * @change  1.4.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_trash_post( $post_id ) {

        // if any published post type is sent to the trash clear complete cache
        if ( get_post_status( $post_id ) === 'publish' ) {
            self::clear_total_cache();
        }

        // check if cache timeout needs to be recorded
        self::check_future_posts();
    }


    /**
     * clear page cache by post ID
     *
     * @since   1.0.0
     * @change  1.4.7
     *
     * @param   integer|string  $post_id  post ID
     */

    public static function clear_page_cache_by_post_id( $post_id ) {

        // check if post ID is empty
        if ( empty( $post_id ) ) {
            return;
        }

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
        self::clear_page_cache_by_url( get_permalink( $post_id ) );
    }


    /**
     * clear page cache by URL
     *
     * @since   1.0.0
     * @change  1.4.7
     *
     * @param   string  $clear_url   full or relative URL of a page
     * @param   string  $clear_type  clear all specific `page` variants or the entire `dir`
     */

    public static function clear_page_cache_by_url( $clear_url, $clear_type = 'page' ) {

        // check if clear URL is empty
        if ( empty( $clear_url ) ) {
            return;
        }

        // validate string
        if ( ! is_string( $clear_url ) ) {
            return;
        }

        // clear URL
        call_user_func( array( self::$disk, 'delete_asset' ), $clear_url, $clear_type );

        // clear cache by URL post hook
        do_action( 'ce_action_cache_by_url_cleared', $clear_url );
    }


    /**
     * clear home page cache
     *
     * @since   1.0.7
     * @change  1.4.7
     */

    public static function clear_home_page_cache() {

        // clear home page cache
        self::clear_page_cache_by_url( get_site_url() );

        // clear home page cache post hook
        do_action( 'ce_action_home_page_cache_cleared' );
    }


    /**
     * clear blog ID cache
     *
     * @since   1.4.0
     * @change  1.4.7
     *
     * @param   integer|string  $blog_id  blog ID
     */

    public static function clear_blog_id_cache( $blog_id ) {

        // check if network
        if ( ! is_multisite() ) {
            return;
        }

        // validate integer
        if ( ! is_int( $blog_id ) ) {
            // if string try to convert to integer
            $blog_id = (int) $blog_id;
            // conversion failed
            if ( ! $blog_id ) {
                return;
            }
        }

        // set clear URL
        $clear_url = get_site_url( $blog_id );

        // network with subdomain configuration
        if ( is_subdomain_install() ) {
            // clear main site or subsite cache
            self::clear_page_cache_by_url( $clear_url, 'dir' );
        // network with subdirectory configuration
        } else {
            // get blog path
            $blog_path = get_blog_details( $blog_id )->path;

            // main site
            if ( $blog_path === '/' ) {
                // get blog paths
                $blog_paths = self::_get_blog_paths();

                // get blog domain
                $blog_domain = self::get_blog_domain();

                // glob path
                $glob_path = CE_CACHE_DIR . '/' . $blog_domain;

                // get cached page paths
                $page_paths = glob( $glob_path . '/*', GLOB_MARK | GLOB_ONLYDIR );
                foreach ( $page_paths as $page_path ) {
                    $page_path = str_replace( $glob_path, '', $page_path );
                    // if cached page belongs to main site
                    if ( ! in_array( $page_path, $blog_paths ) ) {
                        // clear page cache
                        self::clear_page_cache_by_url( $clear_url . $page_path, 'dir' );
                    }
                }

                // clear home page cache
                self::clear_home_page_cache();
            // subsite
            } else {
                // clear subsite cache
                self::clear_page_cache_by_url( $clear_url, 'dir' );
            }
        }
    }


    /**
     * check if index.php
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if index.php
     */

    private static function _is_index() {

        return strtolower( basename( $_SERVER['SCRIPT_NAME'] ) ) !== 'index.php';
    }


    /**
     * check if caching is disabled
     *
     * @since   1.4.5
     * @change  1.4.5
     *
     * @return  boolean  true if WP_CACHE is set to disable caching, false otherwise
     */

    private static function _is_wp_cache_disabled() {

        if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
            return true;
        }

        return false;
    }


    /**
     * check if sitemap
     *
     * @since   1.4.6
     * @change  1.4.6
     *
     * @return  boolean  true if sitemap, false otherwise
     */

    private static function _is_sitemap() {

        if ( preg_match( '/\.x(m|s)l$/', $_SERVER['REQUEST_URI'] ) ) {
            return true;
        }

        return false;
    }




    /**
     * check if trailing slash redirect
     *
     * @since   1.4.7
     * @change  1.4.7
     *
     * @return  boolean  true if a redirect is required, false otherwise
     */

    private static function _is_trailing_slash_redirect() {

        // check if trailing slash is set and missing
        if ( self::permalink_structure_has_trailing_slash() ) {
            if ( ! preg_match( '/\/(|\?.*)$/', $_SERVER['REQUEST_URI'] ) ) {
                return true;
            }
        // if trailing slash is not set and appended
        } elseif ( preg_match( '/(?!^)\/(|\?.*)$/', $_SERVER['REQUEST_URI'] ) ) {
            return true;
        }

        return false;
    }


    /**
     * check if mobile
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if mobile
     */

    private static function _is_mobile() {

        return ( strpos( TEMPLATEPATH, 'wptouch' ) || strpos( TEMPLATEPATH, 'carrington' ) || strpos( TEMPLATEPATH, 'jetpack' ) || strpos( TEMPLATEPATH, 'handheld' ) );
    }


    /**
     * check if there are posts to be published in the future
     *
     * @since   1.2.3
     * @change  1.2.3
     */

    public static function check_future_posts() {

        $future_posts = new WP_Query( array(
            'post_status' => array( 'future' ),
        ) );

        if ( $future_posts->have_posts() ) {
            $post_dates = array_column( $future_posts->get_posts(), 'post_date' );
            sort( $post_dates );
            // record cache timeout for advanced cache
            Cache_Enabler_Disk::record_advcache_settings( array(
                'cache_timeout' => strtotime( $post_dates[0] )
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'cache_timeout' ) );
        }
    }


    /**
     * check to bypass the cache
     *
     * @since   1.0.0
     * @change  1.4.7
     *
     * @return  boolean  true if exception, false otherwise
     *
     * @hook    boolean  bypass_cache
     */

    private static function _bypass_cache() {

        // bypass cache hook
        if ( apply_filters( 'bypass_cache', false ) ) {
            return true;
        }

        // check if request method is GET
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        // check HTTP status code
        if ( http_response_code() !== 200 ) {
            return true;
        }

        // check conditional tags
        if ( self::_is_wp_cache_disabled() || self::_is_index() || self::_is_sitemap() || self::_is_trailing_slash_redirect() || is_search() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
            return true;
        }

        // check DONOTCACHEPAGE
        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // check mobile request
        if ( self::_is_mobile() ) {
            return true;
        }

        // get Cache Enabler options
        $options = self::$options;

        // if post ID excluded
        if ( $options['excl_ids'] && is_singular() ) {
            if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), (array) explode( ',', $options['excl_ids'] ) ) ) {
                return true;
            }
        }

        // if page path excluded
        if ( ! empty( $options['excl_paths'] ) ) {
            $url_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match( $options['excl_paths'], $url_path ) ) {
                return true;
            }
        }

        // check cookies
        if ( ! empty( $_COOKIE ) ) {
            // set regex matching cookies that should cause the cache to be bypassed
            if ( ! empty( $options['excl_cookies'] ) ) {
                $cookies_regex = $options['excl_cookies'];
            } else {
                $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
            }
            // bypass the cache if an excluded cookie is found
            foreach ( $_COOKIE as $key => $value) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        // check URL query parameters
        if ( ! empty( $_GET ) ) {
            // set regex matching URL query parameters that should not cause the cache to be bypassed
            if ( ! empty( $options['incl_parameters'] ) ) {
                $parameters_regex = $options['incl_parameters'];
            } else {
                $parameters_regex = '/^fbclid|utm_(source|medium|campaign|term|content)$/';
            }
            // bypass the cache if no included URL query parameters are found
            if ( sizeof( preg_grep( $parameters_regex, array_keys( $_GET ), PREG_GREP_INVERT ) ) > 0 ) {
                return true;
            }
        }

        return false;
    }


    /**
     * minify HTML
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  minify request data
     * @return  string  $data  minify response data
     *
     * @hook    array   cache_minify_ignore_tags
     */

    private static function _minify_cache( $data ) {

        // check if disabled
        if ( ! self::$options['minify_html'] ) {
            return $data;
        }

        // HTML character limit
        if ( strlen( $data ) > 700000) {
            return $data;
        }

        // HTML tags to ignore
        $ignore_tags = (array) apply_filters(
            'cache_minify_ignore_tags',
            array(
                'textarea',
                'pre',
            )
        );

        // ignore JS if selected
        if ( self::$options['minify_html'] !== self::MINIFY_HTML_JS ) {
            $ignore_tags = array( 'script' );
        }

        // return of no ignore tags
        if ( ! $ignore_tags ) {
            return $data;
        }

        // stringify
        $ignore_regex = implode( '|', $ignore_tags );

        // regex minification
        $cleaned = preg_replace(
            array(
                '/<!--[^\[><](.*?)-->/s',
                '#(?ix)(?>[^\S ]\s*|\s{2,})(?=(?:(?:[^<]++|<(?!/?(?:' . $ignore_regex . ')\b))*+)(?:<(?>' . $ignore_regex . ')\b|\z))#',
            ),
            array(
                '',
                ' ',
            ),
            $data
        );

        // something went wrong
        if ( strlen( $cleaned ) <= 1 ) {
            return $data;
        }

        return $cleaned;
    }


    /**
     * clear complete cache
     *
     * @since   1.0.0
     * @change  1.4.0
     */

    public static function clear_total_cache() {

        // clear disk cache
        Cache_Enabler_Disk::clear_cache();

        // delete transient
        delete_transient( 'cache_size' );

        // clear cache post hook
        do_action( 'ce_action_cache_cleared' );
    }


    /**
     * WooCommerce stock hooks
     *
     * @since   1.3.0
     * @change  1.4.0
     *
     * @param   integer|WC_Product  $product  product ID or product instance
     */

    public static function on_woocommerce_stock_update( $product ) {

        // get product ID
        if ( is_int( $product ) ) {
            $product_id = $product;
        } else {
            $product_id = $product->get_id();
        }

        // if option enabled clear complete cache on product stock update
        if ( self::$options['update_product_stock'] ) {
            self::clear_total_cache();
        } else {
            self::clear_page_cache_by_post_id( $product_id );
        }
    }


    /**
     * set cache
     *
     * @since   1.0.0
     * @change  1.3.1
     *
     * @param   string  $data  content of a page
     * @return  string  $data  content of a page
     *
     * @hook    string  cache_enabler_before_store
     */

    public static function set_cache( $data ) {

        // check if page is empty
        if ( empty( $data ) ) {
            return '';
        }

        $data = apply_filters( 'cache_enabler_before_store', $data );

        // store as asset
        call_user_func( array( self::$disk, 'store_asset' ), self::_minify_cache( $data ) );

        return $data;
    }


    /**
     * handle cache
     *
     * @since   1.0.0
     * @change  1.4.3
     */

    public static function handle_cache() {

        // check if cache needs to be bypassed
        if ( self::_bypass_cache() ) {
            return;
        }

        // get asset cache status
        $cached = call_user_func( array( self::$disk, 'check_asset' ) );

        // check if cache is empty
        if ( empty( $cached ) ) {
            ob_start( 'Cache_Enabler::set_cache' );
            return;
        }

        // get cache expiry status
        $expired = call_user_func( array( self::$disk, 'check_expiry' ) );

        // check if cache has expired
        if ( $expired ) {
            ob_start( 'Cache_Enabler::set_cache' );
            return;
        }

        // return cached asset
        call_user_func( array( self::$disk, 'get_asset' ) );
    }


    /**
     * add clear option dropdown on post publish widget
     *
     * @since   1.0.0
     * @change  1.4.5
     *
     * @param   WP_Post  $post  post instance
     */

    public static function add_clear_dropdown( $post ) {

        // on published post/page only
        if ( empty( $GLOBALS['pagenow'] ) || $GLOBALS['pagenow'] !== 'post.php' || empty( $post ) || ! is_object( $post ) || $post->post_status !== 'publish' ) {
            return;
        }

        // check user role
        if ( ! current_user_can( 'publish_posts' ) ) {
            return;
        }

        // validate nonce
        wp_nonce_field( CE_BASE, '_cache__status_nonce_' . $post->ID );

        // get current publishing action
        $current_action = (int) get_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            true
        );

        // init variables
        $dropdown_options = '';
        $available_options = array(
            esc_html__( 'Page specific', 'cache-enabler' ),
            esc_html__( 'Completely', 'cache-enabler' ),
        );

        // set dropdown options
        foreach ( $available_options as $key => $value ) {
            $dropdown_options .= sprintf(
                '<option value="%1$d" %3$s>%2$s</option>',
                $key,
                $value,
                selected( $key, $current_action, false )
            );
        }

        // output dropdown
        echo sprintf(
            '<div class="misc-pub-section" style="border-top:1px solid #eee">
                <label for="cache_action">
                    %1$s: <strong id="output-cache-action">%2$s</strong>
                </label>
                <a href="#" class="edit-cache-action hide-if-no-js">%3$s</a>

                <div class="hide-if-js">
                    <select name="_clear_post_cache_on_update" id="cache_action">
                        %4$s
                    </select>

                    <a href="#" class="save-cache-action hide-if-no-js button">%5$s</a>
                    <a href="#" class="cancel-cache-action hide-if-no-js button-cancel">%6$s</a>
                 </div>
            </div>',
            esc_html__( 'Clear cache', 'cache-enabler' ),
            $available_options[ $current_action ],
            esc_html__( 'Edit', 'cache-enabler' ),
            $dropdown_options,
            esc_html__( 'OK', 'cache-enabler' ),
            esc_html__( 'Cancel', 'cache-enabler' )
        );
    }


    /**
     * enqueue scripts
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_admin_resources( $hook ) {

        // hook check
        if ( $hook !== 'index.php' && $hook !== 'post.php' ) {
            return;
        }

        // plugin data
        $plugin_data = get_plugin_data( CE_FILE );

        // enqueue scripts
        switch( $hook ) {

            case 'post.php':
                wp_enqueue_script(
                    'cache-post',
                    plugins_url( 'js/post.js', CE_FILE ),
                    array( 'jquery' ),
                    $plugin_data['Version'],
                    true
                );
                break;

            default:
                break;
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
            array(
                __CLASS__,
                'settings_page',
            )
        );
    }


    /**
     * minify caching dropdown
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  cache minification options
     */

    private static function _minify_select() {

        return array(
            self::MINIFY_DISABLED  => esc_html__( 'Disabled', 'cache-enabler' ),
            self::MINIFY_HTML_ONLY => esc_html__( 'HTML', 'cache-enabler' ),
            self::MINIFY_HTML_JS   => esc_html__( 'HTML and Inline JS', 'cache-enabler' ),
        );
    }


    /**
     * check plugin requirements
     *
     * @since   1.1.0
     * @change  1.4.5
     */

    public static function requirements_check() {

        // WordPress version check
        if ( version_compare( $GLOBALS['wp_version'], CE_MIN_WP . 'alpha', '<' ) ) {
            show_message(
                sprintf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Cache Enabler 2. WordPress version (e.g. 5.1)
                        esc_html__( 'The %1$s plugin is optimized for WordPress %2$s. Please disable the plugin or upgrade your WordPress installation (recommended).', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        CE_MIN_WP
                    )
                )
            );
        }

        // permission check
        if ( file_exists( CE_CACHE_DIR ) && ! is_writable( CE_CACHE_DIR ) ) {
            show_message(
                sprintf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Cache Enabler 2. 755 3. wp-content/cache 4. file permissions
                        esc_html__( 'The %1$s plugin requires write permissions %2$s in %3$s. Please change the %4$s.', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        '<code>755</code>',
                        '<code>wp-content/cache</code>',
                        sprintf(
                            '<a href="%s" target="_blank"></a>',
                            'https://wordpress.org/support/article/changing-file-permissions/',
                            esc_html__( 'file permissions', 'cache-enabler' )
                        )
                    )
                )
            );
        }

        // autoptimize minification check
        if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && self::$options['minify_html'] && get_option( 'autoptimize_html', '' ) !== '' ) {
            show_message(
                sprintf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Autoptimize 2. Cache Minification 3. Cache Enabler Settings
                        esc_html__( 'The %1$s plugin is already active. Please disable %2$s in the %3$s.', 'cache-enabler' ),
                        '<strong>Autoptimize</strong>',
                        esc_html__( 'Cache Minification', 'cache-enabler' ),
                        sprintf(
                            '<a href="%s">%s</a>',
                            add_query_arg(
                                array(
                                    'page' => 'cache-enabler',
                                ),
                                admin_url( 'options-general.php' )
                            ),
                            esc_html__( 'Cache Enabler Settings', 'cache-enabler' )
                        )
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
        load_plugin_textdomain(
            'cache-enabler',
            false,
            'cache-enabler/lang'
        );
    }


    /**
     * register settings
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function register_settings() {

        register_setting(
            'cache-enabler',
            'cache-enabler',
            array(
                __CLASS__,
                'validate_settings',
            )
        );
    }


    /**
     * permalink structure has trailing slash
     *
     * @since   1.4.3
     * @change  1.4.3
     *
     * @return  boolean  true if permalink structure has a trailing slash, false otherwise
     */

    public static function permalink_structure_has_trailing_slash() {

        $permalink_structure = get_option( 'permalink_structure' );

        // check permalink structure
        if ( $permalink_structure && preg_match( '/\/$/', $permalink_structure ) ) {
            // permalink structure has a trailing slash
            return true;
        } else {
            // permalink structure does not have a trailing slash
            return false;
        }
    }


    /**
     * validate regex
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @param   string  $re  string containing regex
     * @return  string       string containing regex or empty string if input is invalid
     */

    public static function validate_regex( $re ) {

        if ( $re !== '' ) {
            if ( ! preg_match( '/^\/.*\/$/', $re ) ) {
                $re = '/' . $re . '/';
            }

            if ( @preg_match( $re, null ) === false ) {
                return '';
            }

            return sanitize_text_field( $re );
        }

        return '';
    }

    /**
     * validate settings
     *
     * @since   1.0.0
     * @change  1.4.0
     *
     * @param   array  $data  form data array
     * @return  array         valid form data array
     */

    public static function validate_settings( $data ) {

        // check if empty
        if ( empty( $data ) ) {
            return;
        }

        // check if cache should be cleared
        if ( isset( $data['clear_cache'] ) && $data['clear_cache'] ) {
            self::clear_total_cache();
        }

        // record permalink structure for advanced cache
        if ( self::permalink_structure_has_trailing_slash() ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'permalink_trailing_slash' => true,
            ) );
        } else {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'permalink_trailing_slash' => false,
            ) );
        }

        // record cache expiry for advanced cache
        if ( $data['expires'] > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'expires' => $data['expires'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'expires' ) );
        }

        // record cookies cache exclusion for advanced cache
        if ( strlen( $data['excl_cookies'] ) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'excl_cookies' => $data['excl_cookies'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'excl_cookies' ) );
        }

        // record URL query parameters inclusion for advanced cache
        if ( strlen( $data['incl_parameters'] ) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'incl_parameters' => $data['incl_parameters'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'incl_parameters' ) );
        }

        return array(
            'expires'              => (int) $data['expires'],
            'clear_on_upgrade'     => (int) ( ! empty( $data['clear_on_upgrade'] ) ),
            'new_post'             => (int) ( ! empty( $data['new_post'] ) ),
            'new_comment'          => (int) ( ! empty( $data['new_comment'] ) ),
            'update_product_stock' => (int) ( ! empty( $data['update_product_stock'] ) ),
            'webp'                 => (int) ( ! empty( $data['webp'] ) ),
            'compress'             => (int) ( ! empty( $data['compress'] ) ),
            'excl_ids'             => (string) sanitize_text_field( @$data['excl_ids'] ),
            'excl_paths'           => (string) self::validate_regex( @$data['excl_paths'] ),
            'excl_cookies'         => (string) self::validate_regex( @$data['excl_cookies'] ),
            'incl_parameters'      => (string) self::validate_regex( @$data['incl_parameters'] ),
            'minify_html'          => (int) $data['minify_html'],
        );
    }


    /**
     * settings page
     *
     * @since   1.0.0
     * @change  1.4.5
     */

    public static function settings_page() {

        // check WP_CACHE constant
        if ( self::_is_wp_cache_disabled() ) {
            show_message(
                sprintf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. define( 'WP_CACHE', true ); 2. wp-config.php
                        esc_html__( 'Caching is disabled because %1$s is not set in the %2$s file.', 'cache-enabler' ),
                        "<code>define( 'WP_CACHE', true );</code>",
                        '<code>wp-config.php</code>'
                    )
                )
            );
        }

        ?>

        <div id="cache-enabler-settings" class="wrap">
            <h2>
                <?php esc_html_e( 'Cache Enabler Settings', 'cache-enabler' ); ?>
            </h2>

            <div class="notice notice-info" style="margin-bottom: 35px;">
                <p>
                <?php
                printf(
                    esc_html__( 'Combine %s with Cache Enabler for even better WordPress performance and achieve the next level of caching with a CDN.', 'cache-enabler' ),
                    '<strong><a href="https://www.keycdn.com?utm_source=wp-admin&utm_medium=plugins&utm_campaign=cache-enabler">KeyCDN</a></strong>'
                );
                ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'cache-enabler' ); ?>
                <?php $options = self::_get_options(); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Expiry', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <input name="cache-enabler[expires]" type="number" id="cache_expires" value="<?php echo esc_attr( $options['expires'] ); ?>" class="small-text" /> <?php esc_html_e( 'hours', 'cache-enabler' ); ?>
                            <p class="description"><?php esc_html_e( 'An expiry time of 0 means that the cache never expires.', 'cache-enabler' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Behavior', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_clear_on_upgrade">
                                    <input name="cache-enabler[clear_on_upgrade]" type="checkbox" id="cache_clear_on_upgrade" value="1" <?php checked( '1', $options['clear_on_upgrade'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a plugin has been activated, updated, or deactivated.', 'cache-enabler' ); ?>
                                    <span style="display: inline-block; color: #155724; background-color: #d4edda; font-size: 75%; font-weight: 700; white-space: nowrap; border-radius: .25rem; padding: .25em .4em; margin-left: .5rem;"><?php esc_html_e( 'Updated', 'cache-enabler' ); ?></span>
                                </label>

                                <br />

                                <label for="cache_new_post">
                                    <input name="cache-enabler[new_post]" type="checkbox" id="cache_new_post" value="1" <?php checked( '1', $options['new_post'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if any new post type has been published (instead of only the home page cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_new_comment">
                                    <input name="cache-enabler[new_comment]" type="checkbox" id="cache_new_comment" value="1" <?php checked( '1', $options['new_comment'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a new comment has been posted (instead of only the page specific cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <?php if ( is_plugin_active( 'woocommerce/woocommerce.php') ): ?>
                                <label for="cache_update_product_stock">
                                    <input name="cache-enabler[update_product_stock]" type="checkbox" id="cache_update_product_stock" value="1" <?php checked( '1', $options['update_product_stock'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a product stock has been updated (instead of only the page specific cache).', 'cache-enabler' ); ?>
                                    <span style="display: inline-block; color: #155724; background-color: #d4edda; font-size: 75%; font-weight: 700; white-space: nowrap; border-radius: .25rem; padding: .25em .4em; margin-left: .5rem;"><?php esc_html_e( 'New', 'cache-enabler' ); ?></span>
                                </label>

                                <br />
                                <?php endif; ?>

                                <label for="cache_compress">
                                    <input name="cache-enabler[compress]" type="checkbox" id="cache_compress" value="1" <?php checked( '1', $options['compress'] ); ?> />
                                    <?php esc_html_e( 'Pre-compression of cached pages. Needs to be disabled if the decoding fails in the web browser.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_webp">
                                    <input name="cache-enabler[webp]" type="checkbox" id="cache_webp" value="1" <?php checked( '1', $options['webp'] ); ?> />
                                    <?php printf( esc_html__( 'Create an additional cached version for WebP image support. Convert your images to WebP with %s.', 'cache-enabler' ), '<a href="https://optimus.io" target="_blank">Optimus</a>' ); ?>
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
                                <label for="cache_excl_ids">
                                    <input name="cache-enabler[excl_ids]" type="text" id="cache_excl_ids" value="<?php echo esc_attr( $options['excl_ids'] ) ?>" class="regular-text" />
                                    <p class="description"><?php printf( esc_html__( 'Post and page IDs separated by a %s that should not be cached.', 'cache-enabler' ), '<code>,</code>' ); ?>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>2,43,65</code></p>
                                    </p>
                                </label>

                                <br />

                                <label for="cache_excl_paths">
                                    <input name="cache-enabler[excl_paths]" type="text" id="cache_excl_paths" value="<?php echo esc_attr( $options['excl_paths'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching page paths that should not be cached.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/(^\/$|\/robot\/$|^\/2018\/.*\/test\/)/</code></p>
                                </label>

                                <br />

                                <label for="cache_excl_cookies">
                                    <input name="cache-enabler[excl_cookies]" type="text" id="cache_excl_cookies" value="<?php echo esc_attr( $options['excl_cookies'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching cookies that should cause the cache to be bypassed.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author|woocommerce_items_in_cart|wp_woocommerce_session)_?/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author)_/</code></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Inclusions', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_incl_parameters">
                                    <input name="cache-enabler[incl_parameters]" type="text" id="cache_incl_parameters" value="<?php echo esc_attr( $options['incl_parameters'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching URL query parameters that should not cause the cache to be bypassed.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^fbclid|pk_(source|medium|campaign|kwd|content)$/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code>/^fbclid|utm_(source|medium|campaign|term|content)$/</code><span style="display: inline-block; color: #155724; background-color: #d4edda; font-size: 75%; font-weight: 700; white-space: nowrap; border-radius: .25rem; padding: .25em .4em; margin-left: .5rem;"><?php esc_html_e( 'Updated', 'cache-enabler' ); ?></span></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Minification', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <label for="cache_minify_html">
                                <select name="cache-enabler[minify_html]" id="cache_minify_html">
                                    <?php foreach ( self::_minify_select() as $key => $value ): ?>
                                        <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $options['minify_html'], $key ); ?>>
                                            <?php echo esc_html( $value ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                <input type="submit" class="button-secondary" value="<?php esc_html_e( 'Save Changes', 'cache-enabler' ); ?>" />
                <input name="cache-enabler[clear_cache]" type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes and Clear Cache', 'cache-enabler' ); ?>" />
                </p>
            </form>
        </div>

        <?php

    }
}
