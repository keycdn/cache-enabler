<?php


// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler WPML
 *
 * @since 1.3.2
 */

final class Cache_Enabler_WpRestCache {


    /**
     * Cache_Enabler_WpRestCache constructor.
     */
    public function __construct() {

        add_action('ce_action_cache_by_post_id_cleared', array($this, 'clear_cache_by_post_id'), 10);
    }


    /**
     * Act on Rest Cache clear by id
     */
    public function clear_cache_by_post_id( $post_id ) {

        //Ignore if editpost action on admin
        if (isset($_POST['action']) && $_POST['action'] === 'editpost' && is_admin()) {
            return;
        }

        //Load Class
        $caching = WP_Rest_Cache_Plugin\Includes\Caching\Caching::get_instance();

        //Clear Cache api by object type.
        $caching->delete_object_type_caches( get_post_type($post_id) );

    }
}
