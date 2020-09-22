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
     * plugin settings
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @var     array
     */

    public static $settings;


    /**
     * disk cache object
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @var     object
     */

    private static $disk;


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
     * @change  1.5.0
     */

    public function __construct() {

        // set default vars
        self::_set_default_vars();

        // init hooks
        add_action( 'init', array( __CLASS__, 'process_clear_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );

        // clear cache hooks
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post_id' ) );
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_total_cache' ) );
        add_action( '_core_updated_successfully', array( __CLASS__, 'clear_total_cache' ) );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'switch_theme', array( __CLASS__, 'clear_total_cache' ) );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'save_post', array( __CLASS__, 'on_save_post' ) );
        add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
        add_action( 'permalink_structure_changed', array( __CLASS__, 'clear_total_cache' ) );
        // third party
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_total_cache' ) );

        // advanced cache hooks
        add_action( 'permalink_structure_changed', array( __CLASS__, 'create_advcache_settings' ), 10, 0 );

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
     * @change  1.4.9
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

                // restore blog
                restore_current_blog();
            }
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

        // if setting enabled clear complete cache on any plugin activation or deactivation
        if ( self::$settings['clear_complete_cache_on_changed_plugin'] ) {
            self::clear_total_cache();
        }
    }


    /**
     * upgrade hook
     *
     * @since   1.2.3
     * @change  1.5.0
     *
     * @param   WP_Upgrader  $obj   upgrade instance
     * @param   array        $data  update data
     */

    public static function on_upgrade( $obj, $data ) {

        // if setting enabled clear complete cache on any plugin update
        if ( self::$settings['clear_complete_cache_on_changed_plugin'] ) {
            self::clear_total_cache();
        }

        // check updated plugins
        if ( $data['action'] === 'update' && $data['type'] === 'plugin' && array_key_exists( 'plugins', $data ) ) {
            foreach ( (array) $data['plugins'] as $each_plugin ) {
                // if Cache Enabler has been updated
                if ( $each_plugin === CE_BASE ) {
                    if ( is_multisite() && is_plugin_active_for_network( CE_BASE ) ) {
                        $network_wide = true;
                    } else {
                        $network_wide = false;
                    }
                    // update requirements
                    self::on_ce_update( $network_wide );
                }
            }
        }
    }


    /**
     * Cache Enabler update actions
     *
     * @since   1.4.0
     * @change  1.5.0
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_ce_update( $network_wide ) {

        // delete advanced cache settings file(s) and clear complete cache
        self::on_ce_activation_deactivation( 'deactivated', $network_wide );
        // decom: delete old advanced cache settings file(s) (1.4.0)
        array_map( 'unlink', glob( WP_CONTENT_DIR . '/cache/cache-enabler-advcache-*.json' ) );
        // decom: delete user(s) meta key from depracted publishing action (1.5.0)
        delete_metadata( 'user', 0, '_clear_post_cache_on_update', '', true );
        // clean: delete incorrect advanced cache settings file(s) that may have been created in 1.4.0 (1.4.5)
        array_map( 'unlink', glob( ABSPATH . 'CE_SETTINGS_PATH-*.json' ) );

        // update database and create advanced cache settings file(s)
        self::on_ce_activation_deactivation( 'activated', $network_wide );

        // update advanced cache file that might have changed
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * create or update advanced cache settings
     *
     * @since   1.4.0
     * @change  1.5.0
     *
     * @param   array  $settings  Cache Enabler settings
     */

    public static function create_advcache_settings( $settings = null ) {

        // set settings if provided, get settings from database otherwise
        $settings = ( $settings ) ? $settings : self::$settings;
        $settings_to_record = array();

        // delete existing advanced cache settings if there are any
        Cache_Enabler_Disk::delete_advcache_settings();

        // set permalink structure for advanced cache
        $settings_to_record['permalink_structure_has_trailing_slash'] = self::permalink_structure_has_trailing_slash();

        // set cache expiry time for advanced cache
        if ( isset( $settings['cache_expires'] ) && isset( $settings['cache_expiry_time'] ) && $settings['cache_expires'] && $settings['cache_expiry_time'] > 0 ) {
            $settings_to_record['cache_expiry_time'] = $settings['cache_expiry_time'];
        }

        // set cookies cache exclusion for advanced cache
        if ( isset( $settings['excluded_cookies'] ) && strlen( $settings['excluded_cookies'] ) > 0 ) {
            $settings_to_record['excluded_cookies'] = $settings['excluded_cookies'];
        }

        // set query strings exclusion for advanced cache
        if ( isset( $settings['excluded_query_strings'] ) && strlen( $settings['excluded_query_strings'] ) > 0 ) {
            $settings_to_record['excluded_query_strings'] = $settings['excluded_query_strings'];
        }

        // record advanced cache settings
        Cache_Enabler_Disk::record_advcache_settings( $settings_to_record );
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

        // add default Cache Enabler option to database if not already added
        add_option( 'cache-enabler', array() );

        // create advanced cache settings file
        self::create_advcache_settings();
    }


    /**
     * uninstall Cache Enabler
     *
     * @since   1.0.0
     * @change  1.4.9
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
                // restore blog
                restore_current_blog();
            }
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
            foreach ( $wp_config as $ln ) {
                @fwrite( $fh, $ln );
            }

            @fclose( $fh );
        }
    }


    /**
     * set default vars
     *
     * @since   1.0.0
     * @change  1.5.0
     */

    private static function _set_default_vars() {

        // get Cache Enabler settings
        self::$settings = self::_get_settings();

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
     * get Cache Enabler settings from database
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @return  array  $settings  Cache Enabler settings
     */

    private static function _get_settings() {

        // get defined settings
        $defined_settings = get_option( 'cache-enabler', array() );

        // change old settings to new settings
        $defined_settings = self::_convert_settings( $defined_settings );

        // set default settings
        $default_settings = array(
            'cache_expires'                          => 0,
            'cache_expiry_time'                      => 0,
            'clear_complete_cache_on_published_post' => 0,
            'clear_cache_on_updated_post'            => 0,
            'clear_type_on_updated_post'             => 'associated',
            'clear_complete_cache_on_new_comment'    => 0,
            'clear_complete_cache_on_changed_plugin' => 0,
            'compress_cache_with_gzip'               => 0,
            'convert_image_urls_to_webp'             => 0,
            'excluded_post_ids'                      => '',
            'excluded_page_paths'                    => '',
            'excluded_query_strings'                 => '',
            'excluded_cookies'                       => '',
            'minify_html'                            => 0,
            'minify_inline_js'                       => 0,
        );

        // merge defined settings into default settings
        $settings = wp_parse_args(
            $defined_settings,
            $default_settings
        );

        return $settings;
    }


    /**
     * convert Cache Enabler settings to new structure
     *
     * @since   1.5.0
     * @change  1.5.0
     *
     * @param   array  $settings  Cache Enabler settings
     * @return  array  $settings  converted Cache Enabler settings if applicable, unchanged otherwise
     */

    private static function _convert_settings( $settings ) {

        // updated settings
        if ( isset( $settings['expires'] ) && $settings['expires'] > 0 ) {
            $settings['cache_expires'] = 1;
            $converted = true;
        }

        if ( isset( $settings['minify_html'] ) && $settings['minify_html'] === 2 ) {
            $settings['minify_html'] = 1;
            $settings['minify_inline_js'] = 1;
            $converted = true;
        }

        // renamed or removed settings
        $settings_names = array(
            'expires'              => 'cache_expiry_time',
            'new_post'             => 'clear_complete_cache_on_published_post',
            'update_product_stock' => '', // depracted in 1.5.0
            'new_comment'          => 'clear_complete_cache_on_new_comment',
            'clear_on_upgrade'     => 'clear_complete_cache_on_changed_plugin',
            'compress'             => 'compress_cache_with_gzip',
            'webp'                 => 'convert_image_urls_to_webp',
            'excl_ids'             => 'excluded_post_ids',
            'excl_regexp'          => 'excluded_page_paths', // <= 1.3.5
            'excl_paths'           => 'excluded_page_paths',
            'excl_cookies'         => 'excluded_cookies',
            'incl_parameters'      => '', // depracted in 1.5.0
        );

        foreach ( $settings_names as $old_name => $new_name ) {
            if ( ! empty( $settings ) && array_key_exists( $old_name, $settings ) ) {
                if ( ! empty( $new_name ) ) {
                    $settings[ $new_name ] = $settings[ $old_name ];
                }
                unset( $settings[ $old_name ] );
                $converted = true;
            }
        }

        // if settings were converted
        if ( ! empty( $converted ) ) {
            // update database
            update_option( 'cache-enabler', $settings );
        }

        return $settings;
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
     * @change  1.5.0
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
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank" rel="nofollow noopener">Documentation</a>',
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
     * @change  1.5.0
     *
     * @return  string  $domain  current blog domain
     */

    public static function get_blog_domain() {

        // get current blog domain
        $domain = parse_url( home_url(), PHP_URL_HOST );

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
     * @change  1.5.0
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

        // set clear URL without query string and check if installation is in a subdirectory
        $installation_dir = parse_url( home_url(), PHP_URL_PATH );
        $clear_url = str_replace( $installation_dir, '', home_url() ) . preg_replace( '/\?.*/', '', $_SERVER['REQUEST_URI'] );

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

        // if setting enabled and any published post type author changes
        if ( $post_before->post_author !== $post_after->post_author ) {
            if ( self::$settings['clear_cache_on_updated_post'] && self::$settings['clear_type_on_updated_post'] === 'associated' ) {
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

        // if any published post type status is changed
        if ( $old_status === 'publish' && in_array( $new_status, array( 'future', 'draft', 'pending', 'private') ) ) {
            self::clear_cache_on_post_save( $post->ID );
        }
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
            // if setting enabled clear complete cache on new comment
            if ( self::$settings['clear_complete_cache_on_new_comment'] ) {
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

        // if setting enabled clear complete cache on new comment
        if ( self::$settings['clear_complete_cache_on_new_comment'] ) {
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
            // if setting enabled clear complete cache on new comment
            if ( self::$settings['clear_complete_cache_on_new_comment'] ) {
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
            // if setting enabled clear complete cache on new comment
            if ( self::$settings['clear_complete_cache_on_new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment->comment_post_ID );
            }
        }
    }


    /**
     * clear complete cache
     *
     * @since   1.0.0
     * @change  1.5.0
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
        $author_archives_url = home_url( '/' ) . $author_base . DIRECTORY_SEPARATOR . $author_username;

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
     * @param   string          $clear_type  clear the `pagination` or the entire `dir` instead of only the cached `page`
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
     * @change  1.5.0
     *
     * @param   string  $clear_url   full URL of a cached page
     * @param   string  $clear_type  clear the `pagination` or the entire `dir` instead of only the cached `page`
     */

    public static function clear_page_cache_by_url( $clear_url, $clear_type = 'page' ) {

        // validate URL
        if ( ! filter_var( $clear_url, FILTER_VALIDATE_URL ) ) {
            return;
        }

        // clear URL
        call_user_func( array( self::$disk, 'delete_asset' ), $clear_url, $clear_type );

        // clear cache by URL post hook
        do_action( 'ce_action_cache_by_url_cleared', $clear_url );
    }


    /**
     * clear blog ID cache
     *
     * @since   1.4.0
     * @change  1.5.0
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

        // check if blog ID exists
        if ( ! in_array( $blog_id, self::_get_blog_ids() ) ) {
            return;
        }

        // get clear URL
        $clear_url = get_home_url( $blog_id );

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
                // set glob path
                $glob_path = CE_CACHE_DIR . DIRECTORY_SEPARATOR . $blog_domain;

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
                self::clear_page_cache_by_url( get_home_url( $blog_id ) );
            // subsite
            } else {
                // clear subsite cache
                self::clear_page_cache_by_url( $clear_url, 'dir' );
            }
        }
    }


    /**
     * clear cache on post save
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

        // any trashed post type
        if ( $trashed ) {
            // clear page cache
            self::clear_page_cache_by_post_id( $post_id );
            // clear associated cache
            self::clear_associated_cache( $post );
        // any new post type
        } elseif ( strtotime( $post->post_date_gmt ) >= strtotime( $post->post_modified_gmt ) ) {
            // if setting enabled clear complete cache
            if ( self::$settings['clear_complete_cache_on_published_post'] ) {
                self::clear_total_cache();
            // clear associated cache otherwise
            } else {
                self::clear_associated_cache( $post );
            }
        // any updated post type
        } else {
            // clear page cache
            self::clear_page_cache_by_post_id( $post_id );

            if ( self::$settings['clear_cache_on_updated_post'] ) {
                // if setting enabled clear associated cache
                if ( self::$settings['clear_type_on_updated_post'] === 'associated' ) {
                    self::clear_associated_cache( $post );
                }
                // if setting enabled clear complete cache
                if ( self::$settings['clear_type_on_updated_post'] === 'complete' ) {
                    self::clear_total_cache();
                }
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
     * check to bypass the cache
     *
     * @since   1.0.0
     * @change  1.5.0
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

        // get Cache Enabler settings
        $settings = self::$settings;

        // if post ID excluded
        if ( $settings['excluded_post_ids'] && is_singular() ) {
            if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), (array) explode( ',', $settings['excluded_post_ids'] ) ) ) {
                return true;
            }
        }

        // if page path excluded
        if ( ! empty( $settings['excluded_page_paths'] ) ) {
            $page_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match( $settings['excluded_page_paths'], $page_path ) ) {
                return true;
            }
        }

        // if query string excluded
        if ( ! empty( $settings['excluded_query_strings'] ) ) {
            $query_string = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );

            if ( preg_match( $settings['excluded_query_strings'], $query_string ) ) {
                return true;
            }
        }

        // check cookies
        if ( ! empty( $_COOKIE ) ) {
            // set regex matching cookies that should bypass the cache
            if ( ! empty( $settings['excluded_cookies'] ) ) {
                $cookies_regex = $settings['excluded_cookies'];
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
        if ( ! self::$settings['minify_html'] ) {
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

        // if selected exclude inline JavaScript
        if ( ! self::$settings['minify_inline_js'] ) {
            $ignore_tags[] = 'script';
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
     * enqueue styles and scripts
     *
     * @since   1.0.0
     * @change  1.5.0
     */

    public static function add_admin_resources( $hook ) {

        // get Cache Enabler data
        $ce_data = get_plugin_data( CE_FILE );

        // settings page
        if ( $hook === 'settings_page_cache-enabler' ) {
            wp_enqueue_style( 'cache-enabler-settings', plugins_url( 'css/settings.min.css', CE_FILE ), array(), $ce_data['Version'] );
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
     * check plugin requirements
     *
     * @since   1.1.0
     * @change  1.5.0
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
                            '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                            'https://wordpress.org/support/article/changing-file-permissions/',
                            esc_html__( 'file permissions', 'cache-enabler' )
                        )
                    )
                )
            );
        }

        // autoptimize minification check
        if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && self::$settings['minify_html'] && get_option( 'autoptimize_html', '' ) !== '' ) {
            show_message(
                sprintf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Autoptimize 2. Cache Enabler Settings
                        esc_html__( 'The %1$s plugin HTML optimization is enabled. Please disable HTML minification in the %2$s.', 'cache-enabler' ),
                        '<strong>Autoptimize</strong>',
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
     * @change  1.5.0
     *
     * @param   string  $re  string containing regex
     * @return  string       string containing regex or empty string if input is invalid
     */

    public static function validate_regex( $re ) {

        if ( ! empty( $re ) ) {
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
     * @change  1.5.0
     *
     * @param   array  $data                form data array
     * @return  array  $validated_settings  valid form data array
     */

    public static function validate_settings( $data ) {

        // validate array
        if ( ! is_array( $data ) ) {
            return;
        }

        $validated_settings = array(
            'cache_expires'                          => (int) ( ! empty( $data['cache_expires'] ) ),
            'cache_expiry_time'                      => (int) @$data['cache_expiry_time'],
            'clear_complete_cache_on_published_post' => (int) ( ! empty( $data['clear_complete_cache_on_published_post'] ) ),
            'clear_cache_on_updated_post'            => (int) ( ! empty( $data['clear_cache_on_updated_post'] ) ),
            'clear_type_on_updated_post'             => (string) sanitize_text_field( @$data['clear_type_on_updated_post'] ),
            'clear_complete_cache_on_new_comment'    => (int) ( ! empty( $data['clear_complete_cache_on_new_comment'] ) ),
            'clear_complete_cache_on_changed_plugin' => (int) ( ! empty( $data['clear_complete_cache_on_changed_plugin'] ) ),
            'compress_cache_with_gzip'               => (int) ( ! empty( $data['compress_cache_with_gzip'] ) ),
            'convert_image_urls_to_webp'             => (int) ( ! empty( $data['convert_image_urls_to_webp'] ) ),
            'excluded_post_ids'                      => (string) sanitize_text_field( @$data['excluded_post_ids'] ),
            'excluded_page_paths'                    => (string) self::validate_regex( @$data['excluded_page_paths'] ),
            'excluded_query_strings'                 => (string) self::validate_regex( @$data['excluded_query_strings'] ),
            'excluded_cookies'                       => (string) self::validate_regex( @$data['excluded_cookies'] ),
            'minify_html'                            => (int) ( ! empty( $data['minify_html'] ) ),
            'minify_inline_js'                       => (int) ( ! empty( $data['minify_inline_js'] ) ),
        );

        // update advanced cache settings file
        self::create_advcache_settings( $validated_settings );

        // check if cache should be cleared
        if ( ! empty( $data['clear_complete_cache_on_saved_settings'] ) ) {
            self::clear_total_cache();
        }

        return $validated_settings;
    }


    /**
     * settings page
     *
     * @since   1.0.0
     * @change  1.5.0
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
                <?php $settings = self::_get_settings(); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Behavior', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <p class="subheading"><?php esc_html_e( 'Expiration', 'cache-enabler' ); ?></p>
                                <label for="cache_expires" class="checkbox--form-control">
                                    <input name="cache-enabler[cache_expires]" type="checkbox" id="cache_expires" value="1" <?php checked( '1', $settings['cache_expires'] ); ?> />
                                </label>
                                <label for="cache_expiry_time">
                                    <?php
                                    printf(
                                        // translators: %s: Number of hours.
                                        esc_html__( 'Cached pages expire %s hours after being created.', 'cache-enabler' ),
                                        '<input name="cache-enabler[cache_expiry_time]" type="number" id="cache_expiry_time" value="' . $settings['cache_expiry_time'] . '" class="small-text">'
                                    );
                                    ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Clearing', 'cache-enabler' ); ?></p>
                                <label for="clear_complete_cache_on_published_post">
                                    <input name="cache-enabler[clear_complete_cache_on_published_post]" type="checkbox" id="clear_complete_cache_on_published_post" value="1" <?php checked( '1', $settings['clear_complete_cache_on_published_post'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if any post type has been published (instead of only the associated cache).', 'cache-enabler' ); ?>
                                    <span class="badge badge--success"><?php esc_html_e( 'Updated', 'cache-enabler' ); ?></span>
                                </label>

                                <br />

                                <label for="clear_cache_on_updated_post" class="checkbox--form-control">
                                    <input name="cache-enabler[clear_cache_on_updated_post]" type="checkbox" id="clear_cache_on_updated_post" value="1" <?php checked( '1', $settings['clear_cache_on_updated_post'] ); ?> />
                                </label>
                                <label for="clear_type_on_updated_post">
                                    <?php
                                    $clear_type_on_updated_post_options = array(
                                        esc_html__( 'associated' ) => 'associated',
                                        esc_html__( 'complete' ) => 'complete',
                                    );
                                    $clear_type_on_updated_post = '<select name="cache-enabler[clear_type_on_updated_post]" id="clear_type_on_updated_post">';
                                    foreach ( $clear_type_on_updated_post_options as $key => $value ) {
                                        $clear_type_on_updated_post .= '<option value="' . esc_attr( $value ) . '"' . selected( $value, $settings['clear_type_on_updated_post'], false ) . '>' . $key . '</option>';
                                    }
                                    $clear_type_on_updated_post .= '</select>';
                                    printf(
                                        // translators: %s: Form field control for clearing the 'associated' or 'complete' cache.
                                        esc_html__( 'Clear the %s cache if any published post type has been updated (instead of only the page cache).', 'cache-enabler' ),
                                        $clear_type_on_updated_post
                                    );
                                    ?>
                                    <span class="badge badge--success"><?php esc_html_e( 'New', 'cache-enabler' ); ?></span>
                                </label>

                                <br />

                                <label for="clear_complete_cache_on_new_comment">
                                    <input name="cache-enabler[clear_complete_cache_on_new_comment]" type="checkbox" id="clear_complete_cache_on_new_comment" value="1" <?php checked( '1', $settings['clear_complete_cache_on_new_comment'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a new comment has been posted (instead of only the page cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="clear_complete_cache_on_changed_plugin">
                                    <input name="cache-enabler[clear_complete_cache_on_changed_plugin]" type="checkbox" id="clear_complete_cache_on_changed_plugin" value="1" <?php checked( '1', $settings['clear_complete_cache_on_changed_plugin'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if any plugin has been activated, updated, or deactivated.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Variants', 'cache-enabler' ); ?></p>
                                <label for="compress_cache_with_gzip">
                                    <input name="cache-enabler[compress_cache_with_gzip]" type="checkbox" id="compress_cache_with_gzip" value="1" <?php checked( '1', $settings['compress_cache_with_gzip'] ); ?> />
                                    <?php esc_html_e( 'Pre-compression of cached pages. Needs to be disabled if the decoding fails in the web browser.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="convert_image_urls_to_webp">
                                    <input name="cache-enabler[convert_image_urls_to_webp]" type="checkbox" id="convert_image_urls_to_webp" value="1" <?php checked( '1', $settings['convert_image_urls_to_webp'] ); ?> />
                                    <?php
                                    printf(
                                        // translators: %s: Optimus
                                        esc_html__( 'Create an additional cached version for WebP image support. Convert your images to WebP with %s.', 'cache-enabler' ),
                                        '<a href="https://optimus.io" target="_blank" rel="nofollow noopener">Optimus</a>'
                                    );
                                    ?>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Minification', 'cache-enabler' ); ?></p>
                                <label for="minify_html" class="checkbox--form-control">
                                    <input name="cache-enabler[minify_html]" type="checkbox" id="minify_html" value="1" <?php checked( '1', $settings['minify_html'] ); ?> />
                                </label>
                                <label for="minify_inline_js">
                                    <?php
                                    $minify_inline_js_options = array(
                                        esc_html__( 'excluding', 'cache-enabler' ) => 0,
                                        esc_html__( 'including', 'cache-enabler' ) => 1,
                                    );
                                    $minify_inline_js = '<select name="cache-enabler[minify_inline_js]" id="minify_inline_js">';
                                    foreach ( $minify_inline_js_options as $key => $value ) {
                                        $minify_inline_js .= '<option value="' . esc_attr( $value ) . '"' . selected( $value, $settings['minify_inline_js'], false ) . '>' . $key . '</option>';
                                    }
                                    $minify_inline_js .= '</select>';
                                    printf(
                                        // translators: %s: Form field control for 'including' or 'excluding' inline JavaScript during HTML minification.
                                        esc_html__( 'Minify HTML in cached pages %s inline JavaScript.', 'cache-enabler' ),
                                        $minify_inline_js
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
                                    <input name="cache-enabler[excluded_post_ids]" type="text" id="excluded_post_ids" value="<?php echo esc_attr( $settings['excluded_post_ids'] ) ?>" class="regular-text" />
                                    <p class="description">
                                    <?php
                                    // translators: %s: ,
                                    printf( esc_html__( 'Post IDs separated by a %s that should bypass the cache.', 'cache-enabler' ), '<code>,</code>' );
                                    ?>
                                    </p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>2,43,65</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Page Paths', 'cache-enabler' ); ?></p>
                                <label for="excluded_page_paths">
                                    <input name="cache-enabler[excluded_page_paths]" type="text" id="excluded_page_paths" value="<?php echo esc_attr( $settings['excluded_page_paths'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching page paths that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^(\/|\/forums\/)$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Query Strings', 'cache-enabler' ); ?><span class="badge badge--success"><?php esc_html_e( 'New', 'cache-enabler' ); ?></span></p>
                                <label for="excluded_query_strings">
                                    <input name="cache-enabler[excluded_query_strings']" type="text" id="excluded_query_strings" value="<?php echo esc_attr( $settings['excluded_query_strings'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching query strings that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^nocache$/</code></p>
                                </label>

                                <br />

                                <p class="subheading"><?php esc_html_e( 'Cookies', 'cache-enabler' ); ?></p>
                                <label for="excluded_cookies">
                                    <input name="cache-enabler[excluded_cookies]" type="text" id="excluded_cookies" value="<?php echo esc_attr( $settings['excluded_cookies'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching cookies that should bypass the cache.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^(comment_author|woocommerce_items_in_cart|wp_woocommerce_session)_?/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author)_/</code></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                <input type="submit" class="button-secondary" value="<?php esc_html_e( 'Save Changes', 'cache-enabler' ); ?>" />
                <input name="cache-enabler[clear_complete_cache_on_saved_settings]" type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes and Clear Cache', 'cache-enabler' ); ?>" />
                </p>
            </form>
        </div>

        <?php

    }
}
