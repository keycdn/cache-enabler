<?php


// exit
defined('ABSPATH') OR exit;


/**
 * Cache_Enabler Woocommerce
 *
 * @since 1.3.3
 */

final class Cache_Enabler_Wpml {

    /**
     * Cache_Enabler_Wpml constructor.
     */
    public function __construct() {

        add_filter('ce_cache_cleaner_clean_ids', array($this, 'clear_cache_by_ids'), 10);
        add_filter('ce_cache_cleaner_clean_urls', array($this, 'clear_cache_by_urls'), 10);
    }


    /**
     * Act on WPML clear cache by url
     */
    public function clear_cache_by_ids( $ids ) {

        $lang_array = apply_filters( 'wpml_active_languages', NULL, 'skip_missing=0&orderby=code');

        if( is_array($ids) && is_array($lang_array) ){

            $current_lang = apply_filters( 'wpml_current_language',  ICL_LANGUAGE_CODE);

            foreach ( $lang_array as $code => $lang ) {

                //Switch language
                do_action( 'wpml_switch_language', $code );

                foreach ($ids as $post_ID) {

                    $post_type = get_post_type($post_ID);

                    $tr_post_ID = apply_filters( 'wpml_object_id', $post_ID, $post_type, true, $code );

                    if ($tr_post_ID != $post_ID) {
                        $ids[] = $tr_post_ID;
                    }
                }
            }

            //Restore language
            do_action( 'wpml_switch_language', $current_lang );
        }

        return $ids;
    }


    /**
     * Act on WPML clear cache by url
     *
     * @since 1.3.2
     */
    public function clear_cache_by_urls( $urls ) {

        $lang_array = apply_filters( 'wpml_active_languages', NULL, 'skip_missing=0&orderby=code');

        if( is_array($urls) && is_array($lang_array) ){

            $site_url = trailingslashit(get_site_url());
            $current_lang = apply_filters( 'wpml_current_language',  ICL_LANGUAGE_CODE);

            foreach ($urls as $url) {

                $size_url = strlen($url);

                if ( !is_string($url) || !$size_url ) {
                    $url = $site_url;
                }

                $is_abs_url = $site_url === $url ? false : true;

                foreach ( $lang_array as $code => $lang ) {

                    if ($current_lang !== $code) {

                        $tr_url = apply_filters( 'wpml_permalink', $url, $code, $is_abs_url );

                        if ($tr_url != $url) {
                            $urls[] = $tr_url;
                        }
                    }
                }
            }
        }

        return $urls;
    }
}
