<?php


// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler
 *
 * @since 1.0.0
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

    const MINIFY_DISABLED = 0;
    const MINIFY_HTML_ONLY = 1;
    const MINIFY_HTML_JS = 2;


    /**
     * constructor wrapper
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function instance()
    {
        new self();
    }


    /**
     * constructor
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param   void
     * @return  void
     */

    public function __construct()
    {
        // set default vars
        self::_set_default_vars();

        // register publish hook
        add_action(
            'init',
            array(
                __CLASS__,
                'register_publish_hooks'
            ),
            99
        );

        // clear cache hooks
        add_action(
            'ce_clear_post_cache',
            array(
                __CLASS__,
                'clear_page_cache_by_post_id'
            )
        );
        add_action(
            'ce_clear_cache',
            array(
                __CLASS__,
                'clear_total_cache'
            )
        );
        add_action(
            '_core_updated_successfully',
            array(
                __CLASS__,
                'clear_total_cache'
            )
        );
        add_action(
            'switch_theme',
            array(
                __CLASS__,
                'clear_total_cache'
            )
        );
        add_action(
            'wp_trash_post',
            function( $post_id ) {
                if ( get_post_status ( $post_id ) == 'publish' ) {
                    self::clear_total_cache();
                }
                self::check_future_posts();
            }
        );
        add_action(
            'save_post',
            array(
                __CLASS__,
                'check_future_posts'
            )
        );
        add_action(
            'autoptimize_action_cachepurged',
            array(
                __CLASS__,
                'clear_total_cache'
            )
        );
        add_action(
            'upgrader_process_complete',
            array(
                __CLASS__,
                'on_upgrade_hook'
            ), 10, 2);

        // act on woocommerce actions
        add_action(
            'woocommerce_product_set_stock',
            array(
                __CLASS__,
                'woocommerce_product_set_stock',
            ),
            10,
            1
        );
        add_action(
            'woocommerce_product_set_stock_status',
            array(
                __CLASS__,
                'woocommerce_product_set_stock_status',
            ),
            10,
            1
        );
        add_action(
            'woocommerce_variation_set_stock',
            array(
                __CLASS__,
                'woocommerce_product_set_stock',
            ),
            10,
            1
        );
        add_action(
            'woocommerce_variation_set_stock_status',
            array(
                __CLASS__,
                'woocommerce_product_set_stock_status',
            ),
            10,
            1
        );

        // add admin clear link
        add_action(
            'admin_bar_menu',
            array(
                __CLASS__,
                'add_admin_links'
            ),
            90
        );
        add_action(
            'init',
            array(
                __CLASS__,
                'process_clear_request'
            )
        );
        if ( !is_admin() ) {
            add_action(
                'admin_bar_menu',
                array(
                    __CLASS__,
                    'register_textdomain'
                )
            );
        }

        // admin
        if ( is_admin() ) {
            add_action(
                'wpmu_new_blog',
                array(
                    __CLASS__,
                    'install_later'
                )
            );
            add_action(
                'delete_blog',
                array(
                    __CLASS__,
                    'uninstall_later'
                )
            );

            add_action(
                'admin_init',
                array(
                    __CLASS__,
                    'register_textdomain'
                )
            );
            add_action(
                'admin_init',
                array(
                    __CLASS__,
                    'register_settings'
                )
            );

            add_action(
                'admin_menu',
                array(
                    __CLASS__,
                    'add_settings_page'
                )
            );
            add_action(
                'admin_enqueue_scripts',
                array(
                    __CLASS__,
                    'add_admin_resources'
                )
            );

            add_action(
                'transition_comment_status',
                array(
                    __CLASS__,
                    'change_comment'
                ),
                10,
                3
            );
            add_action(
                'comment_post',
                array(
                    __CLASS__,
                    'comment_post'
                ),
                99,
                2
            );
            add_action(
                'edit_comment',
                array(
                    __CLASS__,
                    'edit_comment'
                )
            );

            add_filter(
                'dashboard_glance_items',
                array(
                    __CLASS__,
                    'add_dashboard_count'
                )
            );
            add_action(
                'post_submitbox_misc_actions',
                array(
                    __CLASS__,
                    'add_clear_dropdown'
                )
            );
            add_filter(
                'plugin_row_meta',
                array(
                    __CLASS__,
                    'row_meta'
                ),
                10,
                2
            );
            add_filter(
                'plugin_action_links_' .CE_BASE,
                array(
                    __CLASS__,
                    'action_links'
                )
            );

            // warnings and notices
            add_action(
                'admin_notices',
                array(
                    __CLASS__,
                    'warning_is_permalink'
                )
            );
            add_action(
                'admin_notices',
                array(
                    __CLASS__,
                    'requirements_check'
                )
            );

        // caching
        } else {
            add_action(
                'pre_comment_approved',
                array(
                    __CLASS__,
                    'new_comment'
                ),
                99,
                2
            );

            add_action(
                'template_redirect',
                array(
                    __CLASS__,
                    'handle_cache'
                ),
                0
            );
        }
    }


    /**
     * deactivation hook
     *
     * @since   1.0.0
     * @change  1.1.1
     */

    public static function on_deactivation() {
        self::clear_total_cache(true);

        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            // unset WP_CACHE
            self::_set_wp_cache(false);
        }

        // delete advanced cache file
        unlink(WP_CONTENT_DIR . '/advanced-cache.php');
    }


    /**
     * activation hook
     *
     * @since   1.0.0
     * @change  1.1.1
     */

    public static function on_activation() {

        // multisite and network
        if ( is_multisite() && ! empty($_GET['networkwide']) ) {
            // blog ids
            $ids = self::_get_blog_ids();

            // switch to blog
            foreach ($ids as $id) {
                switch_to_blog($id);
                self::_install_backend();
            }

            // restore blog
            restore_current_blog();

        } else {
            self::_install_backend();
        }

        if ( !defined( 'WP_CACHE' ) || !WP_CACHE ) {
            // set WP_CACHE
            self::_set_wp_cache(true);
        }

        // copy advanced cache file
        copy(CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php');
    }


    /**
     * upgrade hook actions
     *
     * @since 1.2.3
     */

    public static function on_upgrade_hook( $obj, $options ) {
        // clear cache on other plugins being upgraded
        if ( self::$options['clear_on_upgrade'] ) {
            self::clear_total_cache();
        }

        // check if we got upgraded ourselves
        if ( $options['action'] == 'update' && $options['type'] == 'plugin' && array_key_exists('plugins', $options) ) {
            foreach ( (array)$options['plugins'] as $each_plugin ) {
                if ( preg_match("/^cache-enabler\//", $each_plugin) ) {
                    // we got updated!
                    self::on_upgrade();
                }
            }
        }
    }


    /**
     * upgrade actions
     *
     * @since 1.2.3
     */

    public static function on_upgrade() {
        // copy advanced cache file which might have changed
        copy(CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php');
    }


    /**
     * install on multisite setup
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function install_later($id) {

        // check if multisite setup
        if ( ! is_plugin_active_for_network(CE_BASE) ) {
            return;
        }

        // switch to blog
        switch_to_blog($id);

        // installation
        self::_install_backend();

        // restore
        restore_current_blog();
    }


    /**
     * installation options
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _install_backend() {

        add_option(
            'cache-enabler',
            array()
        );

        // clear
        self::clear_total_cache(true);
    }


    /**
     * installation WP_CACHE (advanced cache)
     *
     * @since   1.1.1
     * @change  1.1.1
     */

    private static function _set_wp_cache($wp_cache_value = true) {
        $wp_config_file = ABSPATH . 'wp-config.php';

        if ( file_exists( $wp_config_file ) && is_writable( $wp_config_file ) ) {
            // get wp config as array
            $wp_config = file( $wp_config_file );

            if ($wp_cache_value) {
                $wp_cache_ce_line = "define('WP_CACHE', true); // Added by Cache Enabler". "\r\n";
            } else {
                $wp_cache_ce_line = '';
            }

            $found_wp_cache = false;

            foreach ( $wp_config as &$line ) {
                if ( preg_match( '/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(.*)\s*\)/', $line ) ) {
                    $line = $wp_cache_ce_line;
                    $found_wp_cache = true;
                    break;
                }
            }

            // add wp cache ce line if not found yet
            if ( ! $found_wp_cache ) {
                array_shift( $wp_config );
                array_unshift( $wp_config, "<?php\r\n", $wp_cache_ce_line );
            }

            // write wp-config.php file
            $fh = @fopen( $wp_config_file, 'w' );
            foreach( $wp_config as $ln ) {
                @fwrite( $fh, $ln );
            }

            @fclose( $fh );
        }
    }


    /**
     * uninstall per multisite blog
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function on_uninstall() {
        global $wpdb;

        // multisite and network
        if ( is_multisite() && ! empty($_GET['networkwide']) ) {
            // legacy blog
            $old = $wpdb->blogid;

            // blog id
            $ids = self::_get_blog_ids();

            // uninstall per blog
            foreach ($ids as $id) {
                switch_to_blog($id);
                self::_uninstall_backend();
            }

            // restore
            switch_to_blog($old);
        } else {
            self::_uninstall_backend();
        }
    }


    /**
     * uninstall for multisite and network
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function uninstall_later($id) {

        // check if network plugin
        if ( ! is_plugin_active_for_network(CE_BASE) ) {
            return;
        }

        // switch
        switch_to_blog($id);

        // uninstall
        self::_uninstall_backend();

        // restore
        restore_current_blog();
    }


    /**
     * uninstall
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _uninstall_backend() {

        // delete options
        delete_option('cache-enabler');

        // clear cache
        self::clear_total_cache(true);
    }


    /**
     * get blog ids
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  blog ids array
     */

    private static function _get_blog_ids() {
        global $wpdb;

        return $wpdb->get_col("SELECT blog_id FROM `$wpdb->blogs`");
    }


    /**
     * set default vars
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _set_default_vars() {

        // get options
        self::$options = self::_get_options();

        // disk cache
        if ( Cache_Enabler_Disk::is_permalink() ) {
            self::$disk = new Cache_Enabler_Disk;
        }
    }


    /**
     * get options
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @return  array  options array
     */

    private static function _get_options() {

        // decom
        $ce_leg = get_option('cache');
        if (!empty($ce_leg)) {
            delete_option('cache');
            add_option(
                'cache-enabler',
                $ce_leg
            );
        }

        return wp_parse_args(
            get_option('cache-enabler'),
            array(
                'expires'           => 0,
                'new_post'          => 0,
                'new_comment'       => 0,
                'compress'          => 0,
                'webp'              => 0,
                'clear_on_upgrade'  => 0,
                'excl_ids'          => '',
                'excl_regexp'       => '',
                'excl_cookies'      => '',
                'incl_attributes'   => '',
                'minify_html'       => self::MINIFY_DISABLED,
            )
        );
    }


    /**
     * warning if no custom permlinks
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  options array
     */

    public static function warning_is_permalink() {

        if ( !Cache_Enabler_Disk::is_permalink() && current_user_can('manage_options') ) { ?>

            <div class="error">
                <p><?php printf( __('The <b>%s</b> plugin requires a custom permalink structure to start caching properly. Please go to <a href="%s">Permalink</a> to enable it.', 'cache-enabler'), 'Cache Enabler', admin_url( 'options-permalink.php' ) ); ?></p>
            </div>

            <?php
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

    public static function action_links($data) {

        // check user role
        if ( ! current_user_can('manage_options') ) {
            return $data;
        }

        return array_merge(
            $data,
            array(
                sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        array(
                            'page' => 'cache-enabler'
                        ),
                        admin_url('options-general.php')
                    ),
                    esc_html__('Settings')
                )
            )
        );
    }


    /**
     * cache enabler meta links
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   array   $input  existing links
     * @param   string  $page   page
     * @return  array   $data   appended links
     */

    public static function row_meta($input, $page) {

        // check permissions
        if ( $page != CE_BASE ) {
            return $input;
        }

        return array_merge(
            $input,
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin/" target="_blank">Support Page</a>',
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
        if ( ! current_user_can('manage_options') ) {
            return $items;
        }

        // get cache size
        $size = self::get_cache_size();

        // display items
        $items[] = sprintf(
            '<a href="%s" title="%s">%s %s</a>',
            add_query_arg(
                array(
                    'page' => 'cache-enabler'
                ),
                admin_url('options-general.php')
            ),
            esc_html__('Disk Cache', 'cache-enabler'),
            ( empty($size) ? esc_html__('Empty', 'cache-enabler') : size_format($size) ),
            esc_html__('Cache Size', 'cache-enabler')
        );

        return $items;
    }


    /**
     * get cache size
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $size  cache size (bytes)
     */

    public static function get_cache_size() {

        if ( ! $size = get_transient('cache_size') ) {

            $size = is_object( self::$disk ) ? (int) self::$disk->cache_size(CE_CACHE_DIR) : 0;

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
     * add admin links
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @hook    mixed
     *
     * @param   object  menu properties
     */

    public static function add_admin_links($wp_admin_bar) {

        // check user role
        if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
            return;
        }

        // add admin purge link
        $wp_admin_bar->add_menu(
            array(
                'id'      => 'clear-cache',
                'href'   => wp_nonce_url( add_query_arg('_cache', 'clear'), '_cache__clear_nonce'),
                'parent' => 'top-secondary',
                'title'     => '<span class="ab-item">'.esc_html__('Clear Cache', 'cache-enabler').'</span>',
                'meta'   => array( 'title' => esc_html__('Clear Cache', 'cache-enabler') )
            )
        );

        if ( ! is_admin() ) {
            // add admin purge link
            $wp_admin_bar->add_menu(
                array(
                    'id'      => 'clear-url-cache',
                    'href'   => wp_nonce_url( add_query_arg('_cache', 'clearurl'), '_cache__clear_nonce'),
                    'parent' => 'top-secondary',
                    'title'     => '<span class="ab-item">'.esc_html__('Clear URL Cache', 'cache-enabler').'</span>',
                    'meta'   => array( 'title' => esc_html__('Clear URL Cache', 'cache-enabler') )
                )
            );
        }
    }


    /**
     * process clear request
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @param   array  $data  array of metadata
     */

    public static function process_clear_request($data) {

        // check if clear request
        if ( empty($_GET['_cache']) OR ( $_GET['_cache'] !== 'clear' && $_GET['_cache'] !== 'clearurl' ) ) {
            return;
        }

        // validate nonce
        if ( empty($_GET['_wpnonce']) OR ! wp_verify_nonce($_GET['_wpnonce'], '_cache__clear_nonce') ) {
            return;
        }

        // check user role
        if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
            return;
        }

        // load if network
        if ( ! function_exists('is_plugin_active_for_network') ) {
            require_once( ABSPATH. 'wp-admin/includes/plugin.php' );
        }

        // set clear url w/o query string
        $clear_url = preg_replace('/\?.*/', '', home_url( add_query_arg( NULL, NULL ) ));

        // multisite and network setup
        if ( is_multisite() && is_plugin_active_for_network(CE_BASE) ) {

            if ( is_network_admin() ) {

                // legacy blog
                $legacy = $GLOBALS['wpdb']->blogid;

                // blog ids
                $ids = self::_get_blog_ids();

                // switch blogs
                foreach ($ids as $id) {
                    switch_to_blog($id);
                    self::clear_page_cache_by_url(home_url());
                }

                // restore
                switch_to_blog($legacy);

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'network_admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice'
                        )
                    );
                }
            } else {
                if ($_GET['_cache'] == 'clearurl') {
                    // clear specific multisite url cache
                    self::clear_page_cache_by_url($clear_url);
                } else {
                    // clear specific multisite cache
                    self::clear_page_cache_by_url(home_url());

                    // clear notice
                    if ( is_admin() ) {
                        add_action(
                            'admin_notices',
                            array(
                                __CLASS__,
                                'clear_notice'
                            )
                        );
                    }
                }
            }
        } else {
            if ($_GET['_cache'] == 'clearurl') {
                // clear url cache
                self::clear_page_cache_by_url($clear_url);
            } else {
                // clear cache
                self::clear_total_cache();

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice'
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
        if ( ! is_admin_bar_showing() OR ! apply_filters('user_can_clear_cache', current_user_can('manage_options')) ) {
            return false;
        }

        echo sprintf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('The cache has been cleared.', 'cache-enabler')
        );
    }


    /**
     * clear cache if post comment
     *
     * @since   1.2.0
     * @change  1.2.0
     *
     * @param   integer  $id  id of the comment
     * @param   mixed  $approved  approval status
     */

    public static function comment_post($id, $approved) {

        // check if comment is approved
        if ( $approved === 1 ) {
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id(
                    get_comment($id)->comment_post_ID
                );
            }
        }
    }


    /**
     * clear cache if edit comment
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $id  id of the comment
     */

    public static function edit_comment($id) {

        // clear complete cache if option enabled
        if ( self::$options['new_comment'] ) {
            self::clear_total_cache();
        } else {
            self::clear_page_cache_by_post_id(
                get_comment($id)->comment_post_ID
            );
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

    public static function new_comment($approved, $comment) {

        // check if comment is approved
        if ( $approved === 1 ) {
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment['comment_post_ID'] );
            }
        }

        return $approved;
    }


    /**
     * clear cache if comment changes
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $after_status
     * @param   string  $before_status
     * @param   object  $comment
     */

    public static function change_comment($after_status, $before_status, $comment) {

        // check if changes occured
        if ( $after_status != $before_status ) {
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
     * @since   1.2.3
     *
     * @param   void
     * @return  void
     */

    public static function register_publish_hooks() {

        // get post types
        $post_types = get_post_types(
            array('public' => true)
        );

        // check if empty
        if ( empty($post_types) ) {
            return;
        }

        // post type actions
        foreach ( $post_types as $post_type ) {
            add_action(
                'publish_' .$post_type,
                array(
                    __CLASS__,
                    'publish_post_types'
                ),
                10,
                2
            );
            add_action(
                'publish_future_' .$post_type,
                function( $post_id ) {
                    // clear complete cache if option enabled
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
     * @change  1.0.7
     *
     * @param   integer  $post_ID  Post ID
     */

    public static function publish_post_types($post_ID, $post) {

        // check if post id or post is empty
        if ( empty($post_ID) OR empty($post) ) {
            return;
        }

        // check post status
        if ( ! in_array( $post->post_status, array('publish', 'future') ) ) {
            return;
        }

        // purge cache if clean post on update
        if ( ! isset($_POST['_clear_post_cache_on_update']) ) {

            // clear complete cache if option enabled
            if ( self::$options['new_post'] ) {
                return self::clear_total_cache();
            } else {
                return self::clear_home_page_cache();
            }

        }

        // validate nonce
        if ( ! isset($_POST['_cache__status_nonce_' .$post_ID]) OR ! wp_verify_nonce($_POST['_cache__status_nonce_' .$post_ID], CE_BASE) ) {
            return;
        }

        // validate user role
        if ( ! current_user_can('publish_posts') ) {
            return;
        }

        // save as integer
        $clear_post_cache = (int)$_POST['_clear_post_cache_on_update'];

        // save user metadata
        update_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            $clear_post_cache
        );

        // purge complete cache or specific post
        if ( $clear_post_cache ) {
            self::clear_page_cache_by_post_id( $post_ID );
        } else {
            self::clear_total_cache();
        }
    }


    /**
     * clear page cache by post id
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $post_ID  Post ID
     */

    public static function clear_page_cache_by_post_id($post_ID) {

        // is int
        if ( ! $post_ID = (int)$post_ID ) {
            return;
        }

        // clear cache by URL
        self::clear_page_cache_by_url(
            get_permalink( $post_ID )
        );
    }


    /**
     * clear page cache by url
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param  string  $url  url of a page
     */

    public static function clear_page_cache_by_url($url) {

        // validate string
        if ( ! $url = (string)$url ) {
            return;
        }

        call_user_func(
            array(
                self::$disk,
                'delete_asset'
            ),
            $url
        );

        // clear cache by url post hook
        do_action('ce_action_cache_by_url_cleared');
    }


    /**
     * clear home page cache
     *
     * @since   1.0.7
     * @change  1.2.3
     *
     */

    public static function clear_home_page_cache() {

        call_user_func(
            array(
                self::$disk,
                'clear_home'
            )
        );

        // clear home page cache post hook
        do_action('ce_action_home_page_cache_cleared');
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
        return strtolower(basename($_SERVER['SCRIPT_NAME'])) != 'index.php';
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
        return ( strpos(TEMPLATEPATH, 'wptouch') OR strpos(TEMPLATEPATH, 'carrington') OR strpos(TEMPLATEPATH, 'jetpack') OR strpos(TEMPLATEPATH, 'handheld') );
    }


    /**
     * check if logged in
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if logged in or cookie set
     */

    private static function _is_logged_in() {

        // check if logged in
        if ( is_user_logged_in() ) {
            return true;
        }

        // check cookie
        if ( empty($_COOKIE) ) {
            return false;
        }

        // check cookie values
        $options = self::$options;
        if ( !empty($options['excl_cookies']) ) {
            $cookies_regex = $options['excl_cookies'];
        } else {
            $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
        }

        foreach ( $_COOKIE as $k => $v) {
            if ( preg_match($cookies_regex, $k) ) {
                return true;
            }
        }
    }


    /**
     * check if there are post to be published in the future
     *
     * @since   1.2.3
     *
     * @return  void
     *
     */

    public static function check_future_posts() {

        $future_posts = new WP_Query(array('post_status' => array('future')));

        if ( $future_posts->have_posts() ) {
            $post_dates = array_column($future_posts->get_posts(), "post_date");
            sort($post_dates);
            Cache_Enabler_Disk::record_advcache_settings(array(
                "cache_timeout" => strtotime($post_dates[0])));
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array("cache_timeout"));
        }
    }


    /**
     * check to bypass the cache
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @return  boolean  true if exception
     *
     * @hook    boolean  bypass cache
     */

    private static function _bypass_cache() {

        // bypass cache hook
        if ( apply_filters('bypass_cache', false) ) {
            return true;
        }

        // conditional tags
        if ( self::_is_index() OR is_search() OR is_404() OR is_feed() OR is_trackback() OR is_robots() OR is_preview() OR post_password_required() ) {
            return true;
        }

        // DONOTCACHEPAGE check e.g. woocommerce
        if ( defined('DONOTCACHEPAGE') && DONOTCACHEPAGE ) {
            return true;
        }

        // cache enabler options
        $options = self::$options;

        // Request method GET
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] != 'GET' ) {
            return true;
        }

        // Request with query strings
        if ( ! empty($_GET) && ! isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] ) && get_option('permalink_structure') ) {
            return true;
        }

        // if logged in
        if ( self::_is_logged_in() ) {
            return true;
        }

        // if mobile request
        if ( self::_is_mobile() ) {
            return true;
        }

        // if post id excluded
        if ( $options['excl_ids'] && is_singular() ) {
            if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), (array)explode(',', $options['excl_ids']) ) ) {
                return true;
            }
        }

        // if post path excluded
        if ( !empty($options['excl_regexp']) ) {
            $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            if ( preg_match($options['excl_regexp'], $url_path) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * minify html
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  minify request data
     * @return  string  $data  minify response data
     *
     * @hook    array   cache_minify_ignore_tags
     */

    private static function _minify_cache($data) {

        // check if disabled
        if ( ! self::$options['minify_html'] ) {
            return $data;
        }

        // strlen limit
        if ( strlen($data) > 700000) {
            return $data;
        }

        // ignore this tags
        $ignore_tags = (array)apply_filters(
            'cache_minify_ignore_tags',
            array(
                'textarea',
                'pre'
            )
        );

        // ignore JS if selected
        if ( self::$options['minify_html'] !== self::MINIFY_HTML_JS ) {
            $ignore_tags[] = 'script';
        }

        // return of no ignore tags
        if ( ! $ignore_tags ) {
            return $data;
        }

        // stringify
        $ignore_regex = implode('|', $ignore_tags);

        // regex minification
        $cleaned = preg_replace(
            array(
                '/<!--[^\[><](.*?)-->/s',
                '#(?ix)(?>[^\S ]\s*|\s{2,})(?=(?:(?:[^<]++|<(?!/?(?:' .$ignore_regex. ')\b))*+)(?:<(?>' .$ignore_regex. ')\b|\z))#'
            ),
            array(
                '',
                ' '
            ),
            $data
        );

        // something went wrong
        if ( strlen($cleaned) <= 1 ) {
            return $data;
        }

        return $cleaned;
    }


    /**
     * clear complete cache
     *
     * @since   1.0.0
     * @change  1.2.3
     */

    public static function clear_total_cache() {
        // we need this here to update advanced-cache.php for the 1.2.3 upgrade
        self::on_upgrade();

        // clear disk cache
        Cache_Enabler_Disk::clear_cache();

        // delete transient
        delete_transient('cache_size');

        // clear cache post hook
        do_action('ce_action_cache_cleared');
    }


    /**
     * Act on WooCommerce stock changes
     *
     * @since 1.3.0
     */

    public static function woocommerce_product_set_stock($product) {
        self::woocommerce_product_set_stock_status($product->get_id());
    }

    public static function woocommerce_product_set_stock_status($product_id) {
        self::clear_total_cache();
    }


    /**
     * set cache
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  content of a page
     * @return  string  $data  content of a page
     */

    public static function set_cache($data) {

        // check if empty
        if ( empty($data) ) {
            return '';
        }

        $data = apply_filters('cache_enabler_before_store', $data);

        // store as asset
        call_user_func(
            array(
                self::$disk,
                'store_asset'
            ),
            self::_minify_cache($data)
        );

        return $data;
    }


    /**
     * handle cache
     *
     * @since   1.0.0
     * @change  1.0.1
     */

    public static function handle_cache() {

        // bypass cache
        if ( self::_bypass_cache() ) {
            return;
        }

        // get asset cache status
        $cached = call_user_func(
            array(
                self::$disk,
                'check_asset'
            )
        );

        // check if cache empty
        if ( empty($cached) ) {
            ob_start('Cache_Enabler::set_cache');
            return;
        }

        // get expiry status
        $expired = call_user_func(
            array(
                self::$disk,
                'check_expiry'
            )
        );

        // check if expired
        if ( $expired ) {
            ob_start('Cache_Enabler::set_cache');
            return;
        }

        // check if we are missing a trailing slash
        if ( self::missing_trailing_slash() ) {
            return;
        }

        // return cached asset
        call_user_func(
            array(
                self::$disk,
                'get_asset'
            )
        );
    }


    /**
     * add clear option dropdown on post publish widget
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_clear_dropdown() {

        // on published post page only
        if ( empty($GLOBALS['pagenow']) OR $GLOBALS['pagenow'] !== 'post.php' OR empty($GLOBALS['post']) OR ! is_object($GLOBALS['post']) OR $GLOBALS['post']->post_status !== 'publish' ) {
            return;
        }

        // check user role
        if ( ! current_user_can('publish_posts') ) {
            return;
        }

        // validate nonce
        wp_nonce_field(CE_BASE, '_cache__status_nonce_' .$GLOBALS['post']->ID);

        // get current action
        $current_action = (int)get_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            true
        );

        // init variables
        $dropdown_options = '';
        $available_options = array(
            esc_html__('Completely', 'cache-enabler'),
            esc_html__('Page specific', 'cache-enabler')
        );

        // set dropdown options
        foreach( $available_options as $key => $value ) {
            $dropdown_options .= sprintf(
                '<option value="%1$d" %3$s>%2$s</option>',
                $key,
                $value,
                selected($key, $current_action, false)
            );
        }

        // output drowdown
        echo sprintf(
            '<div class="misc-pub-section" style="border-top:1px solid #eee">
                <label for="cache_action">
                    %1$s: <span id="output-cache-action">%2$s</span>
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
            esc_html__('Clear cache', 'cache-enabler'),
            $available_options[$current_action],
            esc_html__('Edit'),
            $dropdown_options,
            esc_html__('OK'),
            esc_html__('Cancel')
        );
    }


    /**
     * enqueue scripts
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_admin_resources($hook) {

        // hook check
        if ( $hook !== 'index.php' && $hook !== 'post.php' ) {
            return;
        }

        // plugin data
        $plugin_data = get_plugin_data(CE_FILE);

        // enqueue scripts
        switch($hook) {

            case 'post.php':
                wp_enqueue_script(
                    'cache-post',
                    plugins_url('js/post.js', CE_FILE),
                    array('jquery'),
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
                'settings_page'
            )
        );
    }


    /**
     * minify caching dropdown
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array    Key => value array
     */

    private static function _minify_select() {

        return array(
            self::MINIFY_DISABLED  => esc_html__('Disabled', 'cache-enabler'),
            self::MINIFY_HTML_ONLY => esc_html__('HTML', 'cache-enabler'),
            self::MINIFY_HTML_JS   => esc_html__('HTML & Inline JS', 'cache-enabler')
        );
    }


    /**
     * Check plugin requirements
     *
     * @since   1.1.0
     * @change  1.1.0
     */

    public static function requirements_check() {

        // cache enabler options
        $options = self::$options;

        // WordPress version check
        if ( version_compare($GLOBALS['wp_version'], CE_MIN_WP.'alpha', '<') ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        __('The <b>%s</b> is optimized for WordPress %s. Please disable the plugin or upgrade your WordPress installation (recommended).', 'cache-enabler'),
                        'Cache Enabler',
                        CE_MIN_WP
                    )
                )
            );
        }

        // permission check
        if ( file_exists( CE_CACHE_DIR ) && !is_writable( CE_CACHE_DIR ) ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        __('The <b>%s</b> requires write permissions %s on %s. Please <a href="%s" target="_blank">change the permissions</a>.', 'cache-enabler'),
                        'Cache Enabler',
                        '<code>755</code>',
                        '<code>wp-content/cache</code>',
                        'http://codex.wordpress.org/Changing_File_Permissions',
                        CE_MIN_WP
                    )
                )
            );
        }

        // autoptimize minification check
        if ( defined('AUTOPTIMIZE_PLUGIN_DIR') && $options['minify_html'] && get_option('autoptimize_html', '') != '' ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        __('The <b>%s</b> plugin is already active. Please disable minification in the <b>%s</b> settings.', 'cache-enabler'),
                        'Autoptimize',
                        'Cache Enabler'
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

        load_plugin_textdomain(
            'cache-enabler',
            false,
            'cache-enabler/lang'
        );
    }

    /**
     * missing training slash
     *
     * we only have to really check that in advanced-cache.php
     *
     * @since 1.2.3
     *
     * @return boolean  true if we need to redirct, otherwise false
     */

    public static function missing_trailing_slash() {
        if ( ($permalink_structure = get_option('permalink_structure')) &&
            preg_match("/\/$/", $permalink_structure) ) {

            // record permalink structure for advanced-cache
            Cache_Enabler_Disk::record_advcache_settings(array(
                "permalink_trailing_slash" => true
            ));

            if ( ! preg_match("/\/(|\?.*)$/", $_SERVER["REQUEST_URI"]) ) {
                return true;
            }
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array(
                "permalink_trailing_slash"));
        }

        return false;
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
                'validate_settings'
            )
        );
    }


    /**
     * validate regexps
     *
     * @since   1.2.3
     *
     * @param   string  $re   string containing regexps
     * @return  string        string containing regexps or emty string if input invalid
     */

    public static function validate_regexps($re) {
        if ( $re != '' ) {

            if ( ! preg_match('/^\/.*\/$/', $re) ) {
                $re = '/'.$re.'/';
            }

            if ( @preg_match($re, null) === false ) {
                return '';
            }

            return sanitize_text_field($re);
        }

        return '';
    }

    /**
     * validate settings
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param   array  $data  array form data
     * @return  array         array form data valid
     */

    public static function validate_settings($data) {

        // check if empty
        if ( empty($data) ) {
            return;
        }

        // clear complete cache
        self::clear_total_cache(true);

        // ignore result, but call for settings recording
        self::missing_trailing_slash();

        // record expiry time value for advanced-cache.php
        if ( $data['expires'] > 0 ){
            Cache_Enabler_Disk::record_advcache_settings(array(
                "expires" => $data['expires']));
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array("expires"));
        }

        // path bypass regexp
        if ( strlen($data["excl_regexp"]) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings(array(
                "excl_regexp" => $data["excl_regexp"]));
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array("excl_regexp"));
        }

        // custom cookie exceptions
        if ( strlen($data["excl_cookies"]) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings(array(
                "excl_cookies" => $data["excl_cookies"]));
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array("excl_cookies"));
        }

        // custom GET attribute exceptions
        if ( strlen($data["incl_attributes"]) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings(array(
                "incl_attributes" => $data["incl_attributes"]));
        } else {
            Cache_Enabler_Disk::delete_advcache_settings(array("incl_attributes"));
        }

        return array(
            'expires'           => (int)$data['expires'],
            'new_post'          => (int)(!empty($data['new_post'])),
            'new_comment'       => (int)(!empty($data['new_comment'])),
            'webp'              => (int)(!empty($data['webp'])),
            'clear_on_upgrade'  => (int)(!empty($data['clear_on_upgrade'])),
            'compress'          => (int)(!empty($data['compress'])),
            'excl_ids'          => (string)sanitize_text_field(@$data['excl_ids']),
            'excl_regexp'       => (string)self::validate_regexps(@$data['excl_regexp']),
            'excl_cookies'      => (string)self::validate_regexps(@$data['excl_cookies']),
            'incl_attributes'   => (string)self::validate_regexps(@$data['incl_attributes']),
            'minify_html'       => (int)$data['minify_html']
        );
    }


    /**
     * settings page
     *
     * @since   1.0.0
     * @change  1.2.3
     */

    public static function settings_page() {

        // wp cache check
        if ( !defined('WP_CACHE') || !WP_CACHE ) {
            echo sprintf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    __("%s is not set in %s.", 'cache-enabler'),
                    "<code>define('WP_CACHE', true);</code>",
                    "wp-config.php"
                )
            );
        }

        ?>

        <div class="wrap" id="cache-settings">
            <h2>
                <?php _e("Cache Enabler Settings", "cache-enabler") ?>
            </h2>

            <div class="notice notice-info" style="margin-bottom: 35px;">
                <p><?php printf( __('Combine <b><a href="%s">%s</a></b> with Cache Enabler for even better WordPress performance and achieve the next level of caching with a CDN.', 'cache-enabler'), 'https://www.keycdn.com?utm_source=wp-admin&utm_medium=plugins&utm_campaign=cache-enabler', 'KeyCDN'); ?></p>
            </div>

            <p><?php $size=self::get_cache_size(); printf( __("Current cache size: <b>%s</b>", "cache-enabler"), ( empty($size) ? esc_html__("Empty", "cache-enabler") : size_format($size) ) ); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('cache-enabler') ?>

                <?php $options = self::_get_options() ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e("Cache Expiry", "cache-enabler") ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_expires">
                                    <input type="text" name="cache-enabler[expires]" id="cache_expires" value="<?php echo esc_attr($options['expires']) ?>" />
                                    <p class="description"><?php _e("Cache expiry in hours. An expiry time of 0 means that the cache never expires.", "cache-enabler"); ?></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e("Cache Behavior", "cache-enabler") ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_new_post">
                                    <input type="checkbox" name="cache-enabler[new_post]" id="cache_new_post" value="1" <?php checked('1', $options['new_post']); ?> />
                                    <?php _e("Clear the complete cache if a new post has been published (instead of only the home page cache).", "cache-enabler") ?>
                                </label>

                                <br />

                                <label for="cache_new_comment">
                                    <input type="checkbox" name="cache-enabler[new_comment]" id="cache_new_comment" value="1" <?php checked('1', $options['new_comment']); ?> />
                                    <?php _e("Clear the complete cache if a new comment has been posted (instead of only the page specific cache).", "cache-enabler") ?>
                                </label>

                                <br />

                                <label for="cache_compress">
                                    <input type="checkbox" name="cache-enabler[compress]" id="cache_compress" value="1" <?php checked('1', $options['compress']); ?> />
                                    <?php _e("Pre-compression of cached pages. Needs to be disabled if the decoding fails in the web browser.", "cache-enabler") ?>
                                </label>

                                <br />

                                <label for="cache_webp">
                                    <input type="checkbox" name="cache-enabler[webp]" id="cache_webp" value="1" <?php checked('1', $options['webp']); ?> />
                                    <?php _e("Create an additional cached version for WebP image support. Convert your images to WebP with <a href=\"https://optimus.io/en/\" target=\"_blank\">Optimus</a>.", "cache-enabler") ?>
                                </label>

                                <br />

                                <label for="cache_clear_on_upgrade">
                                    <input type="checkbox" name="cache-enabler[clear_on_upgrade]" id="cache_clear_on_upgrade" value="1" <?php checked('1', $options['clear_on_upgrade']); ?> />
                                    <?php _e("Clear the complete cache if any plugin has been upgraded.", "cache-enabler") ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e("Cache Exclusions", "cache-enabler") ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_excl_ids">
                                    <input type="text" name="cache-enabler[excl_ids]" id="cache_excl_ids" value="<?php echo esc_attr($options['excl_ids']) ?>" />
                                    <p class="description">
                                        <?php echo sprintf(__("Post or Pages IDs separated by a %s that should not be cached.", "cache-enabler"), "<code>,</code>"); ?>
                                    </p>
                                </label>

                                <br />

                                <label for="cache_excl_regexp">
                                    <input type="text" name="cache-enabler[excl_regexp]" id="cache_excl_regexp" value="<?php echo esc_attr($options['excl_regexp']) ?>" />
                                    <p class="description">
                                        <?php _e("Regexp matching page paths that should not be cached.", "cache-enabler"); ?><br>
                                        <?php _e("Example:", "cache-enabler"); ?> <code>/(^\/$|\/robot\/$|^\/2018\/.*\/test\/)/</code>
                                    </p>
                                </label>

                                <br />

                                <label for="cache_excl_cookies">
                                    <input type="text" name="cache-enabler[excl_cookies]" id="cache_excl_cookies" value="<?php echo esc_attr($options['excl_cookies']) ?>" />
                                    <p class="description">
                                        <?php _e("Regexp matching cookies that should cause the cache to be bypassed.", "cache-enabler"); ?><br>
                                        <?php _e("Example:", "cache-enabler"); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author|(woocommerce_items_in_cart|wp_woocommerce_session)_?)/</code><br>
                                        <?php _e("Default if unset:", "cache-enabler"); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author)_/</code>
                                    </p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e("Cache Inclusions", "cache-enabler") ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_incl_attributes">
                                    <input type="text" name="cache-enabler[incl_attributes]" id="cache_incl_attributes" value="<?php echo esc_attr($options['incl_attributes']) ?>" />
                                    <p class="description">
                                        <?php _e("Regexp matching campaign tracking GET attributes that should not cause the cache to be bypassed.", "cache-enabler"); ?><br>
                                        <?php _e("Example:", "cache-enabler"); ?> <code>/^pk_(source|medium|campaign|kwd|content)$/</code><br>
                                        <?php _e("Default if unset:", "cache-enabler"); ?> <code>/^utm_(source|medium|campaign|term|content)$/</code>
                                    </p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e("Cache Minification", "cache-enabler") ?>
                        </th>
                        <td>
                            <label for="cache_minify_html">
                                <select name="cache-enabler[minify_html]" id="cache_minify_html">
                                    <?php foreach( self::_minify_select() as $k => $v ) { ?>
                                        <option value="<?php echo esc_attr($k) ?>" <?php selected($options['minify_html'], $k); ?>>
                                            <?php echo esc_html($v) ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php submit_button() ?>
                        </th>
                        <td>
                            <p class="description"><?php _e("Saving these settings will clear the complete cache.", "cache-enabler") ?></p>
                        </td>
                    </tr>
                </table>
            </form>
            <p class="description"><?php _e("It is recommended to enable HTTP/2 on your origin server and use a CDN that supports HTTP/2. Avoid domain sharding and concatenation of your assets to benefit from parallelism of HTTP/2.", "cache-enabler") ?></p>
        </div><?php
    }
}
