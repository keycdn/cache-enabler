<?php
/**
 * Class used for handling base plugin operations.
 *
 * @since  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler {
    /**
     * Initialize the plugin.
     *
     * @since  1.5.0
     */
    public static function init() {

        new self();
    }

    /**
     * Settings from the database (deprecated).
     *
     * @since       1.0.0
     * @deprecated  1.5.0
     */
    public static $options;

    /**
     * Fire the page cache cleared hook (deprecated).
     *
     * @since       1.6.0
     * @deprecated  1.8.0
     */
    public static $fire_page_cache_cleared_hook = true;

    /**
     * Constructor.
     *
     * This is called by self::init() and sets up the plugin.
     *
     * @since   1.0.0
     * @change  1.8.0
     */
    public function __construct() {

        // Init hooks.
        add_action( 'init', array( 'Cache_Enabler_Engine', 'start' ) );
        add_action( 'init', array( __CLASS__, 'process_clear_cache_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );
        add_action( 'init', array( __CLASS__, 'schedule_events' ) );

        // Option hooks.
        add_action( 'add_option', array( __CLASS__, 'on_add_option' ), 10, 2 );
        add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
        add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 10, 3 );

        // Public clear cache hooks.
        add_action( 'cache_enabler_clear_complete_cache', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'cache_enabler_clear_site_cache', array( __CLASS__, 'clear_site_cache' ) );
        add_action( 'cache_enabler_clear_expired_cache', array( __CLASS__, 'clear_expired_cache' ) );
        add_action( 'cache_enabler_clear_page_cache_by_post', array( __CLASS__, 'clear_page_cache_by_post' ) );
        add_action( 'cache_enabler_clear_page_cache_by_url', array( __CLASS__, 'clear_page_cache_by_url' ) );
        add_action( 'cache_enabler_clear_site_cache_by_blog_id', array( __CLASS__, 'clear_page_cache_by_site' ) ); // Deprecated in 1.8.0.
        add_action( 'cache_enabler_clear_page_cache_by_post_id', array( __CLASS__, 'clear_page_cache_by_post' ) ); // Deprecated in 1.8.0.
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_complete_cache' ) ); // Deprecated in 1.6.0.
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post' ) ); // Deprecated in 1.6.0.

        // System clear cache hooks.
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'save_post', array( __CLASS__, 'on_save_trash_post' ) );
        add_action( 'pre_post_update', array( __CLASS__, 'on_pre_post_update' ), 10, 2 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_save_trash_post' ) );
        add_action( 'comment_post', array( __CLASS__, 'on_comment_post' ), 99, 2 );
        add_action( 'edit_comment', array( __CLASS__, 'on_edit_comment' ), 10, 2 );
        add_action( 'transition_comment_status', array( __CLASS__, 'on_transition_comment_status' ), 10, 3 );
        add_action( 'saved_term', array( __CLASS__, 'on_saved_delete_term' ), 10, 3 );
        add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 2 );
        add_action( 'delete_term', array( __CLASS__, 'on_saved_delete_term' ), 10, 3 );
        add_action( 'user_register', array( __CLASS__, 'on_register_update_delete_user' ) );
        add_action( 'profile_update', array( __CLASS__, 'on_register_update_delete_user' ) );
        add_action( 'delete_user', array( __CLASS__, 'on_register_update_delete_user' ) );
        add_action( 'deleted_user', array( __CLASS__, 'on_deleted_user' ), 10, 2 );

        // Third party clear cache hooks.
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );
        add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_woocommerce_stock_update' ) );

        // System cache created/cleared hooks.
        add_action( 'cache_enabler_page_cache_created', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );
        add_action( 'cache_enabler_site_cache_cleared', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );
        add_action( 'cache_enabler_page_cache_cleared', array( __CLASS__, 'on_cache_created_cleared' ), 10, 3 );

        // Multisite hooks.
        add_action( 'wp_initialize_site', array( __CLASS__, 'install_later' ) );
        add_action( 'wp_uninitialize_site', array( __CLASS__, 'uninstall_later' ) );

        // Admin bar hook.
        add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_items' ), 90 );

        // Admin interface hooks.
        if ( is_admin() ) {
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_resources' ) );
            add_filter( 'dashboard_glance_items', array( __CLASS__, 'add_dashboard_cache_size' ) );
            add_filter( 'plugin_action_links_' . CACHE_ENABLER_BASE, array( __CLASS__, 'add_plugin_action_links' ) );
            add_filter( 'plugin_row_meta', array( __CLASS__, 'add_plugin_row_meta' ), 10, 2 );
            add_action( 'admin_notices', array( __CLASS__, 'requirements_check' ) );
            add_action( 'admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
            add_action( 'network_admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
        }
    }

    /**
     * When the plugin is activated.
     *
     * This runs on the 'activate_cache-enabler/cache-enabler.php' action. It adds or
     * updates the 'cache_enabler' option in the database for each site Cache Enabler
     * is activated on, creates the advanced-cache.php file in the wp-content
     * directory, and then maybe sets the WP_CACHE constant in the wp-config.php file.
     *
     * @since   1.0.0
     * @change  1.8.14
     *
     * @param  bool  $network_wide  True if the plugin was network activated, false otherwise.
     */
    public static function on_activation( $network_wide ) {

        self::each_site( $network_wide, self::class . '::update_backend' );

        Cache_Enabler_Disk::setup();
    }

    /**
     * When the upgrader process is complete.
     *
     * This runs on the 'upgrader_process_complete' action. It clears the cache when
     * the core, themes, or plugins are updated.
     *
     * @since   1.4.0
     * @change  1.8.0
     *
     * @param  WP_Upgrader  $upgrader  Upgrader instance.
     * @param  array        $data      Array of bulk item update data.
     */
    public static function on_upgrade( $upgrader, $data ) {

        if ( $data['action'] !== 'update' ) {
            return;
        }

        if ( $data['type'] === 'core' ) {
            self::clear_complete_cache();
        }

        if ( $data['type'] === 'theme' && isset( $data['themes'] ) ) {
            $updated_themes = (array) $data['themes'];
            $sites_themes   = self::each_site( is_multisite(), 'wp_get_theme' );

            foreach ( $sites_themes as $blog_id => $site_theme ) {
                // Clear the site cache if the active or parent theme has been updated.
                if ( in_array( $site_theme->stylesheet, $updated_themes, true ) || in_array( $site_theme->template, $updated_themes, true ) ) {
                    self::clear_page_cache_by_site( $blog_id );
                }
            }
        }

        if ( $data['type'] === 'plugin' && isset( $data['plugins'] ) ) {
            $updated_plugins = (array) $data['plugins'];
            $network_plugins = is_multisite() ? array_flip( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();

            // Clear the complete cache if a network activated plugin has been updated.
            if ( ! empty( array_intersect( $updated_plugins, $network_plugins ) ) ) {
                self::clear_complete_cache();
            } else {
                $sites_plugins = self::each_site( is_multisite(), 'get_option', array( 'active_plugins', array() ) );

                foreach ( $sites_plugins as $blog_id => $site_plugins ) {
                    // Clear the site cache if an activated plugin has been updated.
                    if ( ! empty( array_intersect( $updated_plugins, (array) $site_plugins ) ) ) {
                        self::clear_page_cache_by_site( $blog_id );
                    }
                }
            }
        }
    }

    /**
     * When Cache Enabler is updated (deprecated).
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
     * When the plugin is deactivated.
     *
     * This runs on the 'deactivate_cache-enabler/cache-enabler.php' action. It
     * deletes the settings and advanced-cache.php files and then maybe unsets the
     * WP_CACHE constant in the wp-config.php file. The site cache is then cleared and
     * the WP-Cron events unscheduled.
     *
     * @since   1.0.0
     * @change  1.8.14
     *
     * @param  bool  $network_wide  True if the plugin was network deactivated, false otherwise.
     */
    public static function on_deactivation( $network_wide ) {

        self::each_site( $network_wide, 'Cache_Enabler_Disk::clean' );
        self::each_site( $network_wide, self::class . '::clear_site_cache', array(), true );
        self::each_site( $network_wide, self::class . '::unschedule_events' );
    }

    /**
     * When the plugin is uninstalled.
     *
     * This runs on the 'uninstall_cache-enabler/cache-enabler.php' action. It deletes
     * the 'cache_enabler' option and plugin transients from the database for each
     * site in the installation.
     *
     * @since   1.0.0
     * @change  1.8.14
     */
    public static function on_uninstall() {

        self::each_site( is_multisite(), self::class . '::uninstall_backend' );
    }

    /**
     * When the cache is created or cleared.
     *
     * This runs on the 'cache_enabler_page_cache_created',
     * 'cache_enabler_site_cache_cleared', and 'cache_enabler_page_cache_cleared'
     * actions. It keeps the 'cache_enabler_cache_size' transient up to date. The
     * cache index count can only be greater than 1 when the cache has been cleared,
     * which can be the case for both cache cleared hooks.
     *
     * @since   1.8.0
     * @change  1.8.2
     *
     * @param  string  $url    Site or post URL.
     * @param  int     $id     Blog or post ID
     * @param  array   $index  Index of the cache created or cleared.
     */
    public static function on_cache_created_cleared( $url, $id, $index ) {

        if ( is_multisite() && ! wp_is_site_initialized( get_current_blog_id() ) ) {
            return;
        }

        $current_cache_size = get_transient( 'cache_enabler_cache_size' );

        if ( count( $index ) > 1 ) {
            if ( $current_cache_size !== false ) {
                // Prevent an incorrect cache size being built when the cache cleared index is not the entire site.
                delete_transient( 'cache_enabler_cache_size' );
            }
        } else {
            // The changed cache size is negative when the cache is cleared.
            $changed_cache_size = array_sum( current( $index )['versions'] );

            if ( $current_cache_size === false ) {
                if ( $changed_cache_size > 0 ) {
                    self::get_cache_size();
                }
            } else {
                $new_cache_size = $current_cache_size + $changed_cache_size;
                $new_cache_size = ( $new_cache_size >= 0 ) ? $new_cache_size : 0;

                set_transient( 'cache_enabler_cache_size', $new_cache_size, DAY_IN_SECONDS );
            }
        }
    }

    /**
     * When a site's initialization routine should be executed.
     *
     * This runs on the 'wp_initialize_site' action. If the plugin is network
     * activated the 'cache_enabler' option will be added to the new site's database,
     * triggering the new site's settings file to be created.
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @param  WP_Site  $new_site  New site instance.
     */
    public static function install_later( $new_site ) {

        if ( ! is_plugin_active_for_network( CACHE_ENABLER_BASE ) ) {
            return;
        }

        self::switch_to_blog( (int) $new_site->blog_id );
        self::update_backend();
        self::restore_current_blog();
    }

    /**
     * Update the disk and backend requirements for the current site.
     *
     * This update process begins by first deleting the settings and
     * advanced-cache.php files and then maybe unsets the WP_CACHE constant in the
     * wp-config.php file. A new advanced-cache.php file is then created and the
     * WP_CACHE constant is maybe set. If a multisite network, the preceding actions
     * will only be done when the first site in the network is updated. Next, the
     * 'cache_enabler' option is updated in the database for the current site, which
     * triggers a new settings file to be created. Lastly, the site cache is cleared.
     *
     * @since   1.8.0
     * @change  1.8.7
     */
    public static function update() {

        self::update_disk();
        self::update_backend();
        self::clear_site_cache();
    }

    /**
     * Add or update the backend requirements for the current site.
     *
     * This adds or updates the 'cache_enabler' option in the database, which triggers
     * the creation of the settings file. It will call self::on_update_backend() when
     * the plugin actions have not been registered as hooks yet, like when the plugin
     * is activated, but in this case even if the backend was not truly updated.
     *
     * @since   1.5.0
     * @change  1.8.6
     *
     * @return  array  The new or current option value.
     */
    public static function update_backend() {

        delete_metadata( 'user', 0, '_clear_post_cache_on_update', '', true ); // < 1.5.0

        $old_value = get_option( 'cache-enabler' ); // < 1.5.0
        if ( $old_value !== false ) {
            delete_option( 'cache-enabler' );
            add_option( 'cache_enabler', $old_value );
        }

        $old_value = get_option( 'cache_enabler', array() );
        $value     = self::upgrade_settings( $old_value );
        $value     = self::validate_settings( $value );

        update_option( 'cache_enabler', $value );

        if ( has_action( 'update_option', array( __CLASS__, 'on_update_option' ) ) === false ) {
            self::on_update_backend( 'cache_enabler', $value );
        }

        return $value;
    }

    /**
     * Update the disk requirements for the current site.
     *
     * This deletes the settings and advanced-cache.php files and then maybe unsets
     * the WP_CACHE constant in the wp-config.php file. A new advanced-cache.php file
     * is then created and the WP_CACHE constant is maybe set. If a multisite network,
     * the 'cache_enabler_disk_updated' site transient is set afterward to only allow
     * this to be ran once.
     *
     * @since   1.8.0
     * @change  1.8.7
     */
    public static function update_disk() {

        if ( is_multisite() ) {
            if ( get_site_transient( 'cache_enabler_disk_updated' ) !== CACHE_ENABLER_VERSION ) {
                self::each_site( true, 'Cache_Enabler_Disk::clean' );
                Cache_Enabler_Disk::setup();
                set_site_transient( 'cache_enabler_disk_updated', CACHE_ENABLER_VERSION, HOUR_IN_SECONDS );
            }
        } else {
            Cache_Enabler_Disk::clean();
            Cache_Enabler_Disk::setup();
        }
    }

    /**
     * When the backend is about to be updated.
     *
     * This runs when the 'cache_enabler' option is about to be added or updated in
     * the database.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param  string  $option  Name of the option (for legacy reasons).
     * @param  array   $value   The new option value.
     */
    public static function on_update_backend( $option, $value ) {

        Cache_Enabler_Disk::create_settings_file( $value );

        self::unschedule_events();
    }

    /**
     * Before an option is added.
     *
     * This runs on the 'add_option' action.
     *
     * @since  1.8.0
     *
     * @param  string  $option  Name of the option to add.
     * @param  mixed   $value   Value of the option.
     */
    public static function on_add_option( $option, $value ) {

        if ( $option === 'cache_enabler' ) {
            self::on_update_backend( $option, $value );
        }
    }

    /**
     * Before an option value is updated.
     *
     * This runs on the 'update_option' action.
     *
     * @since  1.8.0
     *
     * @param  string  $option     Name of the option to update.
     * @param  mixed   $old_value  The old option value.
     * @param  mixed   $value      The new option value.
     */
    public static function on_update_option( $option, $old_value, $value ) {

        $options = array(
            // wp-admin/options-general.php?page=cache-enabler
            'cache_enabler',

            // wp-admin/options-general.php
            'home',

            // wp-admin/options-reading.php
            'page_on_front',
            'page_for_posts',
        );

        if ( in_array( $option, $options, true ) ) {
            if ( $option === 'cache_enabler' ) {
                self::on_update_backend( $option, $value );
            } else {
                self::clear_cache_on_option_save( $option, $old_value, $value );
            }

            if ( $option === 'home' ) {
                Cache_Enabler_Disk::delete_settings_file();
            }
        }
    }

    /**
     * After the value of an option has been successfully updated.
     *
     * This runs on the 'updated_option' action.
     *
     * @since  1.8.0
     *
     * @param  string  $option     Name of the updated option.
     * @param  mixed   $old_value  The old option value.
     * @param  mixed   $value      The new option value.
     */
    public static function on_updated_option( $option, $old_value, $value ) {

        $options = array(
            // wp-admin/options-general.php
            'blogname',
            'blogdescription',
            'WPLANG',
            'timezone_string',
            'gmt_offset',
            'date_format',
            'time_format',
            'start_of_week',

            // wp-admin/options-reading.php
            'page_on_front',
            'page_for_posts',
            'posts_per_page',
            'blog_public',

            // wp-admin/options-discussion.php
            'require_name_email',
            'comment_registration',
            'close_comments_for_old_posts',
            'show_comments_cookies_opt_in',
            'thread_comments',
            'thread_comments_depth',
            'page_comments',
            'comments_per_page',
            'default_comments_page',
            'comment_order',
            'show_avatars',
            'avatar_rating',
            'avatar_default',

            // wp-admin/options-permalink.php
            'permalink_structure',
            'category_base',
            'tag_base',

            // wp-admin/themes.php
            'template',
            'stylesheet',

            // wp-admin/widgets.php
            'sidebars_widgets',
            'widget_*',

            // wp-admin/customize.php
            'site_icon',
        );

        if ( strpos( $option, 'widget_' ) === 0 ) {
            $option = 'widget_*';
        }

        if ( in_array( $option, $options, true ) ) {
            self::clear_cache_on_option_save( $option, $old_value, $value );

            if ( $option === 'permalink_structure' ) {
                self::update_backend();
            }
        }
    }

    /**
     * When a site's uninitialization routine should be executed.
     *
     * This runs on the 'wp_uninitialize_site' action. This deletes the settings file
     * and then clears the site cache for the deleted site. The advanced-cache.php
     * file will also be deleted and the WP_CACHE constant maybe unset if it was the
     * only site that had Cache Enabler activated.
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @param  WP_Site  $old_site  Deleted site instance.
     */
    public static function uninstall_later( $old_site ) {

        Cache_Enabler_Disk::clean();

        self::clear_page_cache_by_site( (int) $old_site->blog_id );
    }

    /**
     * Uninstall backend requirements.
     *
     * @since   1.5.0
     * @change  1.8.7
     */
    private static function uninstall_backend() {

        delete_option( 'cache_enabler' );
        delete_transient( 'cache_enabler_cache_size' );
        delete_site_transient( 'cache_enabler_disk_updated' );
    }

    /**
     * Enter each site and call a callback with an array of parameters.
     *
     * This assumes that the callback function exists on the site being entered. It
     * will not perform the callback or restart the cache engine on sites that do not
     * have Cache Enabler active, unless it is a must-use plugin or it is being
     * activated or uninstalled.
     *
     * @since   1.5.0
     * @since   1.8.0  The `$restart_engine` parameter was added.
     * @change  1.8.0
     *
     * @param   bool    $sites            Whether to enter all sites or the current site.
     * @param   string  $callback         Callback function.
     * @param   array   $callback_params  (Optional) Callback function parameters. Default empty array.
     * @param   bool    $restart_engine   (Optional) Whether to restart the cache engine. Default false.
     * @return  array                     An array of callback returns with blog IDs as the keys.
     */
    private static function each_site( $sites, $callback, $callback_params = array(), $restart_engine = false ) {

        $blog_ids          = $sites ? self::get_blog_ids() : array( get_current_blog_id() );
        $last_blog_id      = end( $blog_ids );
        $skip_active_check = ! self::is_cache_enabler_active();
        $callback_return   = array();

        foreach ( $blog_ids as $blog_id ) {
            self::switch_to_blog( $blog_id, $restart_engine, $skip_active_check );

            if ( $skip_active_check || self::is_cache_enabler_active() ) {
                $callback_return[ $blog_id ] = call_user_func_array( $callback, $callback_params );
            }

            $_restart_engine = ( $restart_engine && $blog_id === $last_blog_id ) ? true : false;

            self::restore_current_blog( $_restart_engine, $skip_active_check );
        }

        return $callback_return;
    }

    /**
     * Switch the current blog.
     *
     * This is a wrapper for switch_to_blog() that can restart the cache engine,
     * allowing the correct site data to be picked up after the switch.
     *
     * @since  1.8.0
     *
     * @param   int   $blog_id         The ID of the blog to switch to.
     * @param   bool  $restart_engine  (Optional) Whether to restart the cache engine after the switch. Default false.
     * @param   bool  $force_restart   (Optional) Whether to force restart the cache engine. Default false.
     * @return  bool                   True if the current blog was switched, false otherwise.
     */
    public static function switch_to_blog( $blog_id, $restart_engine = false, $force_restart = false ) {

        if ( ! is_multisite() || $blog_id === get_current_blog_id() ) {
            return false;
        }

        switch_to_blog( $blog_id );

        if ( ( $force_restart || self::is_cache_enabler_active() ) && $restart_engine ) {
            Cache_Enabler_Engine::start();
        }

        return true;
    }

    /**
     * Restore the current blog after switching.
     *
     * This is a wrapper for restore_current_blog() that can restart the cache engine,
     * allowing the correct site data to be picked up after the switch.
     *
     * @since  1.8.0
     *
     * @param   bool  $restart_engine  (Optional) Whether to restart the cache engine after the switch. Default false.
     * @param   bool  $force_restart   (Optional) Whether to force restart the cache engine. Default false.
     * @return  bool                   True if the current blog was restored, false otherwise.
     */
    public static function restore_current_blog( $restart_engine = false, $force_restart = false  ) {

        if ( ! is_multisite() || ! ms_is_switched() ) {
            return false;
        }

        restore_current_blog();

        if ( ( $force_restart || self::is_cache_enabler_active() ) && $restart_engine ) {
            Cache_Enabler_Engine::start( true );
        }

        return true;
    }

    /**
     * Whether Cache Enabler is active.
     *
     * This checks if Cache Enabler is in the active plugins list. It will not be in
     * that list when installed as a must-use plugin. This copies is_plugin_active().
     * That function is not being used directly because of its availability.
     *
     * @since  1.8.0
     *
     * @return  bool  True if Cache Enabler is in the active plugins list, false if not.
     */
    private static function is_cache_enabler_active() {

        if ( in_array( CACHE_ENABLER_BASE, (array) get_option( 'active_plugins', array() ), true ) ) {
            return true;
        }

        if ( ! is_multisite() ) {
            return false;
        }

        $plugins = get_site_option( 'active_sitewide_plugins' );
        if ( isset( $plugins[ CACHE_ENABLER_BASE ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * After a plugin has been activated or deactivated.
     *
     * This runs on the 'activated_plugin' and 'deactivated_plugin' actions.
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
     * Get the plugin settings from the database for the current site.
     *
     * This can update the disk and backend requirements and then clear the site
     * cache if the settings do not exist or are outdated. If that occurs, the
     * settings after the update will be returned.
     *
     * @since   1.5.0
     * @since   1.8.0  The `$update` parameter was added.
     * @change  1.8.0
     *
     * @param   bool        $update  Whether to update the disk and backend requirements if the settings are
     *                               outdated. Default true.
     * @return  array|bool           Plugin settings from the database, false if settings do not exist and update
     *                               was skipped or failed.
     */
    public static function get_settings( $update = true ) {

        $settings = get_option( 'cache_enabler' );

        if ( $settings === false || ! isset( $settings['version'] ) || $settings['version'] !== CACHE_ENABLER_VERSION ) {
            if ( $update ) {
                self::update();
                $settings = self::get_settings( false );
            }
        }

        return $settings;
    }

    /**
     * Get the blog ID for the current site or of a given site.
     *
     * @since  1.8.0
     *
     * @param   WP_Site|int|string  $site  (Optional) Site instance or site blog ID. Default is the current site.
     * @return  int                        The blog ID or 0 if not found.
     */
    private static function get_blog_id( $site = null ) {

        if ( empty( $site ) ) {
            return get_current_blog_id();
        }

        if ( $site instanceof WP_Site ) {
            return (int) $site->blog_id;
        }

        if ( is_numeric( $site ) ) {
            $blog_id = (int) $site;

            if ( in_array( $blog_id, self::get_blog_ids(), true ) ) {
                return $blog_id;
            }
        }

        return 0;
    }

    /**
     * Get the blog IDs.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @global  wpdb  $wpdb  WordPress database abstraction object.
     *
     * @return  int[]  Blog IDs.
     */
    private static function get_blog_ids() {

        if ( is_multisite() ) {
            global $wpdb;
            $blog_ids = array_map( 'absint', $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) );
        } else {
            $blog_ids = array( 1 );
        }

        return $blog_ids;
    }

    /**
     * Get the blog path for the current site.
     *
     * This gets the end part of the URL in case the installation is in a nested
     * subdirectory. An empty string is being returned instead of '/' as WordPress
     * does because it simplifies checking the blog path in
     * self::get_root_blog_exclusions().
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @return  string  Blog path from site address URL (with leading and trailing slashes), empty
     *                  string if not found.
     */
    public static function get_blog_path() {

        $site_url_path        = (string) parse_url( home_url(), PHP_URL_PATH );
        $site_url_path_pieces = explode( '/', trim( $site_url_path, '/' ) );

        $blog_path = end( $site_url_path_pieces );
        $blog_path = ( ! empty( $blog_path ) ) ? '/' . $blog_path . '/' : '';

        return $blog_path;
    }

    /**
     * Get the blog path from a given URL.
     *
     * @since  1.8.0
     *
     * @return  string  Blog path from URL (with leading and trailing slashes), '/' if not found.
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
     * Get the blog paths.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @global  wpdb  $wpdb  WordPress database abstraction object.
     *
     * @return  string[]  Blog paths.
     */
    public static function get_blog_paths() {

        if ( is_multisite() ) {
            global $wpdb;
            $blog_paths = $wpdb->get_col( "SELECT path FROM $wpdb->blogs" );
        } else {
            $blog_paths = array( '/' );
        }

        return $blog_paths;
    }

    /**
     * Get the WP-Cron events.
     *
     * @since  1.8.0
     *
     * @return  string[]  An array of events with action hooks as the keys and recurrences as the values.
     */
    private static function get_events() {

        $events = array( 'cache_enabler_clear_expired_cache' => 'hourly' );

        return $events;
    }

    /**
     * Get the permalink structure (deprecated).
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */
    private static function get_permalink_structure() {

        $permalink_structure = get_option( 'permalink_structure' );

        if ( $permalink_structure && preg_match( '/\/$/', $permalink_structure ) ) {
            return 'has_trailing_slash';
        }

        if ( $permalink_structure && ! preg_match( '/\/$/', $permalink_structure ) ) {
            return 'no_trailing_slash';
        }

        if ( empty( $permalink_structure ) ) {
            return 'plain';
        }
    }

    /**
     * Get the cache index for the current site.
     *
     * @since  1.8.0
     *
     * @return  array[]  Cache index from the disk.
     */
    public static function get_cache_index() {

        $args['subpages']['exclude'] = self::get_root_blog_exclusions();
        $cache = Cache_Enabler_Disk::cache_iterator( home_url(), $args );
        $cache_index = $cache['index'];

        return $cache_index;
    }

    /**
     * Get the cache size for the current site.
     *
     * This sets the 'cache_enabler_cache_size' transient in the database when the
     * cache size is retrieved from the disk.
     *
     * @since   1.0.0
     * @change  1.8.0
     *
     * @return  int  Cache size in bytes, either from the database or disk.
     */
    public static function get_cache_size() {

        $cache_size = get_transient( 'cache_enabler_cache_size' );

        if ( $cache_size === false ) {
            $args['subpages']['exclude'] = self::get_root_blog_exclusions();
            $cache = Cache_Enabler_Disk::cache_iterator( home_url(), $args );
            $cache_size = $cache['size'];

            set_transient( 'cache_enabler_cache_size', $cache_size, DAY_IN_SECONDS );
        }

        return $cache_size;
    }

    /**
     * Get the name of the transient that is used in the cache clear notice.
     *
     * @since  1.5.0
     *
     * @return  string  Name of the transient.
     */
    private static function get_cache_cleared_transient_name() {

        $transient_name = 'cache_enabler_cache_cleared_' . get_current_user_id();

        return $transient_name;
    }

    /**
     * Get the default plugin settings.
     *
     * @since   1.5.0
     * @since   1.8.6  The `$settings_type` parameter was updated to also accept 'user'.
     * @change  1.8.6
     *
     * @param   string  $settings_type  (Optional) The default plugin 'system' or 'user' settings, all default plugin
     *                                  settings otherwise.
     * @return  array                   Default plugin settings.
     */
    private static function get_default_settings( $settings_type = '' ) {

        switch ( $settings_type ) {
            case 'system':
                return self::get_default_system_settings();
            case 'user':
                return self::get_default_user_settings();
            default:
                return wp_parse_args( self::get_default_user_settings(), self::get_default_system_settings() );
        }
    }

    /**
     * Get the default plugin system settings.
     *
     * @since  1.8.6
     *
     * @return  array  Default plugin system settings.
     */
    private static function get_default_system_settings() {

        $default_system_settings = array(
            'version'              => (string) CACHE_ENABLER_VERSION,
            'use_trailing_slashes' => (int) ( substr( get_option( 'permalink_structure' ), -1, 1 ) === '/' ),
            'permalink_structure'  => (string) self::get_permalink_structure(), // Deprecated in 1.8.0.
        );

        return $default_system_settings;
    }

    /**
     * Get the default plugin user settings.
     *
     * @since  1.8.6
     *
     * @return  array  Default plugin user settings.
     */
    private static function get_default_user_settings() {

        $default_user_settings = array(
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

        return $default_user_settings;
    }

    /**
     * Get the subpages that do not belong to the root blog in a subdirectory network.
     *
     * @since  1.8.0
     *
     * @return  string[]  Blog paths to the other sites in a network if the current site is the root blog
     *                    in a subdirectory network, empty otherwise.
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
     * Upgrade the plugin settings.
     *
     * This runs when self::update_backend() is called. An empty replacement value
     * means the setting will be removed.
     *
     * @since   1.8.0
     * @change  1.8.6
     *
     * @param   array  $settings  Plugin settings.
     * @return  array             The plugin settings after maybe being upgraded.
     */
    private static function upgrade_settings( $settings ) {

        if ( empty( $settings ) ) {
            return $settings;
        }

        // < 1.5.0
        if ( isset( $settings['expires'] ) && $settings['expires'] > 0 ) {
            $settings['cache_expires'] = 1;
        }

        // < 1.5.0
        if ( isset( $settings['minify_html'] ) && $settings['minify_html'] === 2 ) {
            $settings['minify_html'] = 1;
            $settings['minify_inline_css_js'] = 1;
        }

        $settings_names = array(
            // 1.4.0
            'excl_regexp'                            => 'excluded_page_paths',
            'incl_attributes'                        => '',

            // 1.5.0
            'expires'                                => 'cache_expiry_time',
            'new_post'                               => 'clear_site_cache_on_saved_post',
            'update_product_stock'                   => '',
            'new_comment'                            => 'clear_site_cache_on_saved_comment',
            'clear_on_upgrade'                       => 'clear_site_cache_on_changed_plugin',
            'webp'                                   => 'convert_image_urls_to_webp',
            'compress'                               => 'compress_cache',
            'excl_ids'                               => 'excluded_post_ids',
            'excl_paths'                             => 'excluded_page_paths',
            'excl_cookies'                           => 'excluded_cookies',
            'incl_parameters'                        => '',

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
     * Add the plugin action links in the plugins list table.
     *
     * This runs on the 'plugin_action_links_cache-enabler/cache-enabler.php' action.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param   string[]  $action_links  Action links.
     * @return  string[]                 The action links after maybe being updated.
     */
    public static function add_plugin_action_links( $action_links ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return $action_links;
        }

        array_unshift( $action_links, sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=cache-enabler' ),
            esc_html__( 'Settings', 'cache-enabler' )
        ) );

        return $action_links;
    }

    /**
     * Add the plugin metadata in the plugins list table.
     *
     * This runs on the 'plugin_row_meta' action.
     *
     * @since   1.5.0
     * @change  1.7.2
     *
     * @param   string[]  $plugin_meta  An array of the plugin's metadata, including the version, author, author URI,
     *                                  and plugin URI.
     * @param   string    $plugin_file  Path to the plugin file relative to the plugins directory.
     * @return  string[]                An array of the plugin's metadata after maybe being updated.
     */
    public static function add_plugin_row_meta( $plugin_meta, $plugin_file ) {

        if ( $plugin_file !== CACHE_ENABLER_BASE ) {
            return $plugin_meta;
        }

        $plugin_meta = wp_parse_args(
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank" rel="nofollow noopener">' . esc_html__( 'Documentation', 'cache-enabler' ) . '</a>',
            ),
            $plugin_meta
        );

        return $plugin_meta;
    }

    /**
     * Add the cache size to the 'At a Glance' dashboard widget.
     *
     * This runs on the 'dashboard_glance_items' action.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param   string[]  $items  Extra 'At a Glance' widget items.
     * @return  string[]          Extra 'At a Glance' widget items after maybe being updated.
     */
    public static function add_dashboard_cache_size( $items ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        $cache_size = self::get_cache_size();

        $items[] = sprintf(
            '<a href="%s">%s %s</a>',
            admin_url( 'options-general.php?page=cache-enabler' ),
            ( empty( $cache_size ) ) ? esc_html__( 'Empty', 'cache-enabler' ) : size_format( $cache_size ),
            esc_html__( 'Cache Size', 'cache-enabler' )
        );

        return $items;
    }

    /**
     * Add the admin bar items.
     *
     * This runs on the 'admin_bar_menu' action. It adds the clear cache buttons to
     * the admin bar.
     *
     * @since  1.6.0
     *
     * @param  WP_Admin_Bar  $wp_admin_bar  Admin bar instance, passed by reference.
     */
    public static function add_admin_bar_items( $wp_admin_bar ) {

        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        $title = ( is_multisite() && is_network_admin() ) ? esc_html__( 'Clear Network Cache', 'cache-enabler' ) : esc_html__( 'Clear Site Cache', 'cache-enabler' );

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
     * Add the admin resources.
     *
     * This runs on the 'admin_enqueue_scripts' action.
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
     * Add the settings page.
     *
     * This runs on the 'admin_menu' action. It updates the admin panel's menu
     * structure by adding the plugin settings page as a submenu page in the Settings
     * main menu.
     *
     * @since  1.0.0
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
     * Whether the current user can clear the cache.
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @return  bool  True if the current user can clear the cache, false otherwise.
     */
    private static function user_can_clear_cache() {

        /**
         * Filters whether the current user can clear the cache.
         *
         * @since  1.6.0
         *
         * @param  bool  $can_clear_cache  Whether the current user can clear the cache. Default is whether the current
         *                                 user has the 'manage_options' capability.
         */
        $can_clear_cache = apply_filters( 'cache_enabler_user_can_clear_cache', current_user_can( 'manage_options' ) );
        $can_clear_cache = apply_filters_deprecated( 'user_can_clear_cache', array( $can_clear_cache ), '1.6.0', 'cache_enabler_user_can_clear_cache' );

        return $can_clear_cache;
    }

    /**
     * Process a clear cache request.
     *
     * This runs on the 'init' action. It clears the cache when a clear cache button
     * is clicked in the admin bar.
     *
     * @since   1.5.0
     * @change  1.8.14
     */
    public static function process_clear_cache_request() {

        if ( empty( $_GET['_cache'] ) || empty( $_GET['_action'] ) || $_GET['_cache'] !== 'cache-enabler' || ( $_GET['_action'] !== 'clear' && $_GET['_action'] !== 'clearurl' ) ) {
            return;
        }

        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cache_enabler_clear_cache_nonce' ) ) {
            return;
        }

        if ( ! self::user_can_clear_cache() ) {
            return;
        }

        if ( $_GET['_action'] === 'clearurl' ) {
            self::clear_page_cache_by_url( Cache_Enabler_Engine::$request_headers['Host'] . Cache_Enabler_Engine::sanitize_server_input($_SERVER['REQUEST_URI'], false) );
        } elseif ( $_GET['_action'] === 'clear' ) {
            self::each_site( ( is_multisite() && is_network_admin() ), self::class . '::clear_site_cache', array(), true );
        }

        // Redirect to the same page.
        wp_safe_redirect( remove_query_arg( array( '_cache', '_action', '_wpnonce' ) ) );

        if ( is_admin() ) {
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        exit;
    }

    /**
     * Display an admin notice after the cache has been cleared.
     *
     * This runs on the 'admin_notices' action.
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
     * When a post has been saved or before it is sent to the trash.
     *
     * This runs on the 'save_post' and 'wp_trash_post' actions. It will clear the cache
     * when any published post type has been created, updated, or about to be trashed.
     *
     * @since   1.5.0
     * @change  1.7.0
     *
     * @param  int  $post_id  Post ID.
     */
    public static function on_save_trash_post( $post_id ) {

        $post_status = get_post_status( $post_id );

        if ( $post_status === 'publish' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }

    /**
     * Before an existing post is updated in the database.
     *
     * This runs on the 'pre_post_update' action. It will clear the cache when any
     * published post type is about to be updated but not trashed.
     *
     * @since  1.7.0
     *
     * @param  int    $post_id    Post ID.
     * @param  array  $post_data  Array of unslashed post data.
     */
    public static function on_pre_post_update( $post_id, $post_data ) {

        $old_post_status = get_post_status( $post_id );
        $new_post_status = $post_data['post_status'];

        if ( $old_post_status === 'publish' && $new_post_status !== 'trash' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }

    /**
     * After a comment is inserted into the database.
     *
     * This runs on the 'comment_post' action. It will clear the cache when a new
     * approved comment is posted.
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param  int         $comment_id        Comment ID.
     * @param  int|string  $comment_approved  1 if the comment is approved, 0 if not, 'spam' if spam.
     */
    public static function on_comment_post( $comment_id, $comment_approved ) {

        if ( $comment_approved === 1 ) {
            self::clear_cache_on_comment_save( $comment_id );
        }
    }

    /**
     * After a comment is updated in the database.
     *
     * This runs on the 'edit_comment' action. It will clear the cache when an
     * approved comment is edited.
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param  int    $comment_id    Comment ID.
     * @param  array  $comment_data  Comment data.
     */
    public static function on_edit_comment( $comment_id, $comment_data ) {

        $comment_approved = (int) $comment_data['comment_approved'];

        if ( $comment_approved === 1 ) {
            self::clear_cache_on_comment_save( $comment_id );
        }
    }

    /**
     * When the comment status is in transition.
     *
     * This runs on the 'transition_comment_status' action. It will clear the cache
     * when a comment's status has changed from or to 'approved'.
     *
     * @since   1.6.0
     * @change  1.8.0
     *
     * @param  int|string  $new_status  The new comment status.
     * @param  int|string  $old_status  The old comment status.
     * @param  WP_Comment  $comment     Comment instance.
     */
    public static function on_transition_comment_status( $new_status, $old_status, $comment ) {

        if ( $old_status === 'approved' || $new_status === 'approved' ) {
            self::clear_cache_on_comment_save( $comment );
        }
    }

    /**
     * Before the given terms are edited.
     *
     * This runs on the 'edit_terms' action. It will clear the cache before a term is
     * updated in the database and its taxonomy is viewable.
     *
     * @since  1.8.0
     *
     * @param  int     $term_id   Term ID
     * @param  string  $taxonomy  Taxonomy name that `$term_id` is part of.
     */
    public static function on_edit_terms( $term_id, $taxonomy ) {

        if ( is_taxonomy_viewable( $taxonomy ) ) {
            self::clear_cache_on_term_save( $term_id, $taxonomy );
        }
    }

    /**
     * After a term has been saved or deleted and the term cache has been cleaned.
     *
     * This runs on the 'saved_term' and 'delete_term' actions. It will clear the
     * cache after a term has been updated or deleted from the database and its
     * taxonomy is viewable.
     *
     * @since  1.8.0
     *
     * @param  int     $term_id   Term ID.
     * @param  int     $tt_id     Term taxonomy ID.
     * @param  string  $taxonomy  Taxonomy name that `$term_id` is part of.
     */
    public static function on_saved_delete_term( $term_id, $tt_id, $taxonomy ) {

        if ( is_taxonomy_viewable( $taxonomy ) ) {
            self::clear_cache_on_term_save( $term_id, $taxonomy );
        }
    }

    /**
     * After a user is registered or updated and before a user is deleted.
     *
     * This runs on the 'user_register', 'profile_update', and 'delete_user' actions.
     * It will clear the cache after a new user is registered or an existing user is
     * updated, and before a user is deleted from the database.
     *
     * @since  1.8.0
     *
     * @param  int  $user_id  ID of the newly registered, updated, or about to be deleted user.
     */
    public static function on_register_update_delete_user( $user_id ) {

        self::clear_cache_on_user_save( $user_id );
    }

    /**
     * After a user is deleted from the database.
     *
     * This runs on the 'deleted_user' action. It will clear the cache after a user is
     * deleted from the database and the old posts of that user were reassigned.
     *
     * @since  1.8.0
     *
     * @param  int       $user_id   ID of the deleted user.
     * @param  int|null  $reassign  ID of the user reassigned to the old posts of `$user_id`.
     */
    public static function on_deleted_user( $user_id, $reassign ) {

        if ( $reassign ) {
            self::clear_cache_on_user_save( $reassign );
        }
    }

    /**
     * When the WooCommerce stock is updated.
     *
     * This runs on the 'woocommerce_product_set_stock',
     * 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', and
     * 'woocommerce_variation_set_stock_status' actions. It will clear the cache after
     * a product's stock is updated.
     *
     * @since   1.4.0
     * @change  1.6.1
     *
     * @param  WC_Product|int  $product  Product instance or product ID.
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
     * Clear the site cache of a single site or all sites in a multisite network.
     *
     * @since   1.5.0
     * @change  1.8.14
     */
    public static function clear_complete_cache() {

        self::each_site( is_multisite(), self::class . '::clear_site_cache', array(), true );
    }

    /**
     * Clear the complete cache (deprecated).
     *
     * @since       1.0.0
     * @deprecated  1.5.0
     */
    public static function clear_total_cache() {

        self::clear_complete_cache();
    }

    /**
     * Clear the site cache for the current site or of a given site.
     *
     * @since   1.6.0
     * @since   1.8.0  The `$site` parameter was added.
     * @change  1.8.0
     *
     * @param  WP_Site|int|string  $site  (Optional) Site instance or site blog ID. Default is the current site.
     */
    public static function clear_site_cache( $site = null ) {

        self::clear_page_cache_by_site( $site );
    }

    /**
     * Clear the expired cache for the current site or of a given site.
     *
     * @since  1.8.0
     *
     * @param  WP_Site|int|string  $site  (Optional) Site instance or site blog ID. Default is the current site.
     */
    public static function clear_expired_cache( $site = null ) {

        $args['expired'] = 1;
        $args['hooks']['include'] = 'cache_enabler_page_cache_cleared';

        self::clear_page_cache_by_site( $site, $args );
    }

    /**
     * Clear the post cache for the current post or of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  (Optional) Post instance or post ID. Default is the current post if set.
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
     * Clear the comment cache for the current comment or of a given comment.
     *
     * @since  1.8.0
     *
     * @param  WP_Comment|int|string  $comment  (Optional) Comment instance or comment ID. Default is the current comment if set.
     */
    public static function clear_comment_cache( $comment = null ) {

        $comment = get_comment( $comment );

        if ( $comment instanceof WP_Comment ) {
            self::clear_page_cache_by_comment( $comment, 'pagination' );
        }
    }

    /**
     * Clear the term cache of a given term.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int  $term      Term instance or term ID.
     * @param  string       $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
     */
    public static function clear_term_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            self::clear_page_cache_by_term( $term, '', 'pagination' );
            self::clear_term_archive_cache( $term );

            if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
                self::clear_term_children_archives_cache( $term );
                self::clear_term_parents_archives_cache( $term );
            }
        }
    }

    /**
     * Clear the user cache for the current user or of a given user.
     *
     * @since  1.8.0
     *
     * @param  WP_User|int|string  $user  (Optional) User instance or user ID. Default is the current user if logged in.
     */
    public static function clear_user_cache( $user = null ) {

        if ( empty( $user ) ) {
            $user = wp_get_current_user();
        } elseif ( is_numeric( $user ) ) {
            $user = get_userdata( $user );
        }

        if ( $user instanceof WP_User ) {
            self::clear_page_cache_by_user( $user, 'pagination' );
            self::clear_author_archive_cache( $user );
        }
    }

    /**
     * Clear the cache for pages associated with a new or updated post (deprecated).
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
     * Clear the post type archives page cache (deprecated).
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
     * Clear the post type archive cache for the current post or of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  (Optional) Post instance or post ID. Default is the current post if set.
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
     * Clear the post terms archives cache for the current post or of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  (Optional) Post instance or post ID. Default is the current post if set.
     */
    public static function clear_post_terms_archives_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            $terms = wp_get_post_terms( $post->ID, get_taxonomies() );

            if ( is_array( $terms ) ) {
                foreach ( $terms as $term ) {
                    self::clear_term_archive_cache( $term );

                    if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
                        self::clear_term_parents_archives_cache( $term ); // Post can be in the term's parents' archives.
                    }
                }
            }
        }
    }

    /**
     * Clear the post author archive cache for the current post or of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  (Optional) Post instance or post ID. Default is the current post if set.
     */
    public static function clear_post_author_archive_cache( $post = null ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            self::clear_author_archive_cache( (int) $post->post_author );
        }
    }

    /**
     * Clear the post date archives cache for the current post or of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  (Optional) Post instance or post ID. Default is the current post if set.
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
     * Clear the taxonomies archives cache by post ID (deprecated).
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */
    public static function clear_taxonomies_archives_cache_by_post_id( $post_id ) {

        self::clear_post_terms_archives_cache( $post_id );
    }

    /**
     * Clear the author archives page cache by user ID (deprecated).
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */
    public static function clear_author_archives_cache_by_user_id( $user_id ) {

        self::clear_author_archive_cache( $user_id );
    }

    /**
     * Clear the date archives cache by post ID (deprecated).
     *
     * @since       1.5.0
     * @deprecated  1.8.0
     */
    public static function clear_date_archives_cache_by_post_id( $post_id ) {

        self::clear_post_date_archives_cache( $post_id );
    }

    /**
     * Clear the term archive cache of a given term.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int  $term      Term instance or term ID.
     * @param  string       $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
     */
    public static function clear_term_archive_cache( $term, $taxonomy = '' ) {

        $term = get_term( $term, $taxonomy );

        if ( $term instanceof WP_Term ) {
            if ( ! is_taxonomy_viewable( $term->taxonomy ) ) {
                return; // Term archive cache does not exist.
            }

            $term_archive_url = get_term_link( $term );

            if ( ! is_wp_error( $term_archive_url ) && strpos( $term_archive_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $term_archive_url, 'pagination' );
            }
        }
    }

    /**
     * Clear the term children archives cache of a given term.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int  $term      Term instance or term ID.
     * @param  string       $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
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
     * Clear the term parents archives cache of a given term.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int  $term      Term instance or term ID.
     * @param  string       $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
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
     * Clear the author archive cache for the current user or of a given user.
     *
     * @since  1.8.0
     *
     * @param  WP_User|int|string  $author  (Optional) User instance or user ID of the author. Default is the current user
     *                                      if logged in.
     */
    public static function clear_author_archive_cache( $author = null ) {

        if ( empty( $author ) ) {
            $author = wp_get_current_user();
        } elseif ( is_numeric( $author ) ) {
            $author = get_userdata( $author );
        }

        if ( $author instanceof WP_User ) {
            if ( empty( $author->user_nicename ) ) {
                return; // Author archive cache does not exist.
            }

            $author_archive_url = get_author_posts_url( $author->ID, $author->user_nicename );

            if ( strpos( $author_archive_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $author_archive_url, 'pagination' );
            }
        }
    }

    /**
     * Clear the page cache associated with a given site.
     *
     * @since  1.8.0
     *
     * @param  WP_Site|int|string  $site  Site instance or site blog ID.
     * @param  array|string        $args  (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                                    arguments. Default empty array.
     */
    public static function clear_page_cache_by_site( $site, $args = array() ) {

        $blog_id = self::get_blog_id( $site );

        if ( $blog_id === 0 ) {
            return; // Page cache does not exist.
        }

        if ( is_array( $args ) ) {
            $args['subpages']['exclude'] = self::get_root_blog_exclusions();

            if ( ! isset( $args['hooks']['include'] ) ) {
                $args['hooks']['include'] = 'cache_enabler_complete_cache_cleared,cache_enabler_site_cache_cleared';
            }
        }

        self::clear_page_cache_by_url( get_home_url( $blog_id ), $args );
    }

    /**
     * Clear the page cache by post ID (deprecated).
     *
     * @since       1.0.0
     * @deprecated  1.8.0
     */
    public static function clear_page_cache_by_post_id( $post_id, $args = array() ) {

        self::clear_page_cache_by_post( $post_id, $args );
    }

    /**
     * Clear the page cache of a given post.
     *
     * @since  1.8.0
     *
     * @param  WP_Post|int|string  $post  Post instance or post ID.
     * @param  array|string        $args  (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                                    arguments. Default empty array.
     */
    public static function clear_page_cache_by_post( $post, $args = array() ) {

        $post = get_post( $post );

        if ( $post instanceof WP_Post ) {
            if ( $post->post_status !== 'publish' ) {
                return; // Page cache does not exist.
            }

            $post_url = get_permalink( $post );

            if ( $post_url !== false && strpos( $post_url, '?' ) === false ) {
                self::clear_page_cache_by_url( $post_url, $args );
            }
        }
    }

    /**
     * Clear the page cache of the post associated with a given comment.
     *
     * @since  1.8.0
     *
     * @param  WP_Comment|int|string  $comment  Comment instance or comment ID.
     * @param  array|string           $args     (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                                          arguments. Default empty array.
     */
    public static function clear_page_cache_by_comment( $comment, $args = array() ) {

        $comment = get_comment( $comment );

        if ( $comment instanceof WP_Comment ) {
            if ( $comment->comment_approved !== '1' ) {
                return; // Page cache does not exist.
            }

            self::clear_page_cache_by_post( (int) $comment->comment_post_ID, $args );
        }
    }

    /**
     * Clear the page cache of the posts associated with a given term.
     *
     * This clears the page cache of the posts that have the term set.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int   $term      Term instance or term ID.
     * @param  string        $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
     * @param  array|string  $args      (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                                  arguments. Default empty array.
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
     * Clear the page cache of the posts associated with a given user.
     *
     * This clears the page cache of the posts that the user is the author of or has
     * commented on.
     *
     * @since  1.8.0
     *
     * @param  WP_User|int|string  $user  User instance or user ID.
     * @param  array|string        $args  (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                                    arguments. Default empty array.
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
     * Clear the page cache of a given URL.
     *
     * @since   1.0.0
     * @since   1.8.0  The `$args` parameter was added.
     * @change  1.8.0
     *
     * @param  string        $url   URL to a cached page (with or without scheme, wildcard path, and query string).
     * @param  array|string  $args  (Optional) See Cache_Enabler_Disk::cache_iterator() for the available
     *                              arguments. Default empty array.
     */
    public static function clear_page_cache_by_url( $url, $args = array() ) {

        if ( is_array( $args ) ) {
            $args['clear'] = 1;

            if ( ! isset( $args['hooks']['include'] ) ) {
                $args['hooks']['include'] = 'cache_enabler_page_cache_cleared';
            }
        }

        Cache_Enabler_Disk::cache_iterator( $url, $args );
    }

    /**
     * Clear the site cache by blog ID (deprecated).
     *
     * @since       1.4.0
     * @deprecated  1.8.0
     */
    public static function clear_site_cache_by_blog_id( $blog_id, $deprecated = null ) {

        self::clear_page_cache_by_site( $blog_id );
    }

    /**
     * Clear the cache when any post type has been published, updated, or trashed.
     *
     * @since   1.5.0
     * @change  1.8.0
     *
     * @param  WP_Post|int|string  $post  Post instance or post ID.
     */
    public static function clear_cache_on_post_save( $post ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_post'] ) {
            self::clear_site_cache();
        } else {
            self::clear_post_cache( $post );
        }
    }

    /**
     * Clear the cache when a comment been posted, updated, spammed, or trashed.
     *
     * @since  1.8.0
     *
     * @param  WP_Comment|int|string  $comment  Comment instance or comment ID.
     */
    public static function clear_cache_on_comment_save( $comment ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_comment'] ) {
            self::clear_site_cache();
        } else {
            self::clear_comment_cache( $comment );
        }
    }

    /**
     * Clear the cache when any term has been added, updated, or deleted.
     *
     * @since  1.8.0
     *
     * @param  WP_Term|int  $term      Term instance or term ID.
     * @param  string       $taxonomy  (Optional) Taxonomy name that `$term` is part of. Default empty string.
     */
    public static function clear_cache_on_term_save( $term, $taxonomy = '' ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_term'] ) {
            self::clear_site_cache();
        } else {
            self::clear_term_cache( $term, $taxonomy );
        }
    }

    /**
     * Clear the cache when any user has been added, updated, or deleted.
     *
     * @since  1.8.0
     *
     * @param  WP_User|int|string  $user  User instance or user ID.
     */
    public static function clear_cache_on_user_save( $user ) {

        if ( Cache_Enabler_Engine::$settings['clear_site_cache_on_saved_user'] ) {
            self::clear_site_cache();
        } else {
            self::clear_user_cache( $user );
        }
    }

    /**
     * Clear the cache when an option is about to be updated or already has been.
     *
     * @since  1.8.0
     * @change 1.8.14
     *
     * @param  string  $option     Name of the option.
     * @param  mixed   $old_value  The old option value.
     * @param  mixed   $value      The new option value.
     */
    public static function clear_cache_on_option_save( $option, $old_value, $value ) {

        switch ( $option ) {
            case 'page_for_posts':
            case 'page_on_front':
                array_map( self::class . '::clear_page_cache_by_post', array( $old_value, $value ) );
                break;
            default:
                self::clear_site_cache();
        }
    }

    /**
     * Check plugin's requirements.
     *
     * @since   1.1.0
     * @change  1.8.6
     *
     * @global  string  $wp_version  WordPress version.
     */
    public static function requirements_check() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check the PHP version.
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

        // Check the WordPress version.
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

        // Check the advanced-cache.php drop-in file.
        if ( ! file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) && Cache_Enabler_Disk::create_advanced_cache_file() === false ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. advanced-cache.php 3. /path/to/wp-content/plugins/cache-enabler 4. /path/to/wp-content
                    esc_html__( '%1$s was unable to create the required %2$s drop-in file. You can manually create it by locating the sample file in the %3$s directory, editing it as needed, and then saving it in the %4$s directory.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    '<code>advanced-cache.php</code>',
                    '<code>' . CACHE_ENABLER_DIR . '</code>',
                    '<code>' . WP_CONTENT_DIR . '</code>'
                )
            );
        }

        // Check the WordPress installation directory index file.
        if ( ! file_exists( CACHE_ENABLER_INDEX_FILE ) ) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    // translators: 1. Cache Enabler 2. /path/to/index.php 3. CACHE_ENABLER_INDEX_FILE 4. wp-config.php
                    esc_html__( '%1$s was unable to find the WordPress installation directory index file at %2$s. Please define the %3$s constant in your %4$s file as the full path to the location of this file.', 'cache-enabler' ),
                    '<strong>Cache Enabler</strong>',
                    '<code>' . CACHE_ENABLER_INDEX_FILE . '</code>',
                    '<code>CACHE_ENABLER_INDEX_FILE</code>',
                    '<code>wp-config.php</code>'
                )
            );
        }

        // Check the permalink structure.
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

        // Check file and directory permissions. The cache directory
        // is created on-demand, so we can't simply warn if it doesn't
        // exist. Instead we warn if (a) it exists but isn't writable,
        // or (b) it doesn't exist and it doesn't look like we'll be
        // able to create it.
        $dirs = array( CACHE_ENABLER_CACHE_DIR, CACHE_ENABLER_SETTINGS_DIR );
        foreach ( $dirs as $dir ) {
            $parent_dir = dirname( $dir );
            if (
                ( file_exists( $parent_dir ) && ! is_writable( $parent_dir ) ) ||
                ( ! file_exists( $parent_dir ) && ! is_writable( dirname($parent_dir) ) )
            ) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        // translators: 1. Cache Enabler 2. /path/to/wp-content/cache 3. 755 4. file permissions
                        esc_html__( '%1$s requires the directory %2$s to exist and be writable (mode %3$s, for example). Please create it and/or change its %4$s.', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        '<code>' . $parent_dir . '</code>',
                        '<code>755</code>',
                        sprintf(
                            '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                            'https://wordpress.org/support/article/changing-file-permissions/',
                            esc_html__( 'file permissions', 'cache-enabler' )
                        )
                    )
                );
            }
        }

        // Check the Autoptimize HTML optimization.
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
     * Load plugin's translated strings.
     *
     * @since  1.0.0
     */
    public static function register_textdomain() {

        load_plugin_textdomain( 'cache-enabler', false, 'cache-enabler/lang' );
    }

    /**
     * Register plugin's settings.
     *
     * @since   1.0.0
     * @change  1.5.0
     */
    public static function register_settings() {

        register_setting( 'cache_enabler', 'cache_enabler', array( __CLASS__, 'validate_settings' ) );
    }

    /**
     * Schedule WP-Cron events.
     *
     * @since  1.8.0
     */
    public static function schedule_events() {

        if ( ! Cache_Enabler_Engine::$started ) {
            return;
        }

        $events = self::get_events();

        foreach ( $events as $hook => $recurrence ) {
            if ( $hook === 'cache_enabler_clear_expired_cache' ) {
                if ( ! Cache_Enabler_Engine::$settings['cache_expires'] || Cache_Enabler_Engine::$settings['cache_expiry_time'] === 0 ) {
                    continue;
                }
            }

            if ( ! wp_next_scheduled( $hook ) ) {
                wp_schedule_event( time(), $recurrence, $hook );
            }
        }
    }

    /**
     * Unschedule WP-Cron events.
     *
     * @since  1.8.0
     */
    public static function unschedule_events() {

        $events = self::get_events();

        foreach ( $events as $hook => $recurrence ) {
            wp_unschedule_event( wp_next_scheduled( $hook ), $hook );
        }
    }

    /**
     * Validate regex.
     *
     * @since   1.2.3
     * @change  1.5.0
     *
     * @param   string  $regex  Regex.
     * @return  string          Validated regex.
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
     * Validate plugin settings.
     *
     * @since   1.0.0
     * @change  1.8.6
     *
     * @param   array  $settings  Plugin settings.
     * @return  array             Validated plugin settings.
     */
    public static function validate_settings( $settings ) {

        /**
         * Filters the plugin settings before being validated and added or maybe updated.
         *
         * This can be an empty array or not contain all plugin settings. It will depend
         * on if the plugin was just installed, the plugin version being upgraded from, or
         * the form submitted in the plugin settings page. The plugin system settings are
         * protected and cannot be overwritten.
         *
         * @since  1.8.6
         *
         * @param  array  $settings  Plugin settings.
         */
        $settings = (array) apply_filters( 'cache_enabler_settings_before_validation', $settings );
        $settings = wp_parse_args( $settings, self::get_default_settings( 'user' ) );

        $validated_settings = wp_parse_args( array(
            'cache_expires'                      => (int) ( ! empty( $settings['cache_expires'] ) ),
            'cache_expiry_time'                  => absint( $settings['cache_expiry_time'] ),
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
        ), self::get_default_settings( 'system' ) );

        if ( ! empty( $settings['clear_site_cache_on_saved_settings'] ) ) {
            self::clear_site_cache();
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        return $validated_settings;
    }

    /**
     * Plugin settings page.
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
