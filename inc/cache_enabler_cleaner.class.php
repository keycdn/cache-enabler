<?php

// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler Cleaner
 *
 */
final class Cache_Enabler_Cleaner {

    /**
     * Post ID queue
     * @var array
     */
    private $post_ids;

    /**
     * Url queue
     * @var array
     */
    private $urls = array();


    /**
     * The single instance of the class
     *
     * @var null
     */
    private static $_instance = null;


    /**
     * Get the instance of the cleaner
     *
     * @return Singleton
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }


    /**
     * Cloning is forbidden.
     *
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'cache-enabler' ), '1.3.3' );
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'cache-enabler' ), '1.3.3' );
    }


    /**
     * Cache_Enabler_Cleaner constructor.
     */
    private function __construct() {

        $this->post_ids = array();
        $this->urls = array();

        new Cache_Enabler_Woocommerce();
        new Cache_Enabler_Wpml();
    }

    /**
     * Resolver archive url
     *
     * Resolve url for public taxonomies term link and post archive link and add url link to url queue.
     *
     *
     * @param $post_id
     */
    private function resolve_archive_url ( $post_id ) {

        $post_type = get_post_type($post_id);
        $post_taxonomies = get_taxonomies(array('object_type' => array($post_type), 'public' => true));
        $site_url = trailingslashit(get_site_url());

        $post_archive_link = get_post_type_archive_link( $post_type );

        if ($post_archive_link) {
            $this->add_url( $post_archive_link );
        }

        foreach ($post_taxonomies as $post_taxonomy) {

            $post_terms = get_the_terms ($post_id, $post_taxonomy );

            if ( is_array($post_terms) ) {
                foreach ($post_terms as $post_term) {

                    $post_term_url = get_term_link($post_term, $post_taxonomy);

                    if ( is_wp_error($post_term_url) ) {
                        continue;
                    }

                    $_clean_url = trailingslashit(strtok($post_term_url, '?'));

                    if ($_clean_url !== $site_url) {
                        $this->add_url( $_clean_url );
                    }
                }
            }
        }
    }


    /**
     * Add id to cleaner queue
     *
     * @param $post_id
     * @return bool
     */
    public function add_post_id( $post_id ) {

        if (! $post_id = absint($post_id)) {
            return false;
        }

        $this->post_ids[] = $post_id;

        return true;
    }


    /**
     * Add url to cleaner queue
     *
     * @param $url
     * @return bool
     */
    public function add_url( $url ) {

        if ( !is_string($url) || !strlen($url)) {
            return false;
        }

        $this->urls[] = $url;

        return true;
    }


    /**
     * Run process clean cache by post id
     *
     * @param $post_id
     */
    public function clean_by_post_id( $post_id ) {

        $this->add_post_id( $post_id );
        $this->clean();
    }


    /**
     * Run process clean cache by url
     *
     * @param $url
     */
    public function clean_by_url( $url ) {

        $this->add_url( $url );
        $this->clean();
    }


    /**
     * Clean process
     */
    public function clean() {


        $this->post_ids = apply_filters('ce_cache_cleaner_clean_ids', $this->post_ids);
        $this->post_ids = array_unique($this->post_ids);

        foreach ($this->post_ids as $post_id) {

            $permalink = get_permalink( $post_id );

            $this->add_url($permalink);

            //resolve cache archive url
            $this->resolve_archive_url($post_id);
        }


        $this->urls = apply_filters('ce_cache_cleaner_clean_urls', $this->urls);
        $this->urls = array_unique($this->urls);

        //Delete cache urls
        foreach ($this->urls as $url) {

            call_user_func( array( 'Cache_Enabler_Disk', 'delete_asset' ), $url);
        }
    }


    /**
     * Clean all cache
     */
    public function clean_all() {

        call_user_func( array( 'Cache_Enabler_Disk', 'clear_cache' ));
    }

}
