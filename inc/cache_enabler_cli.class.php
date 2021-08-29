<?php
/**
 * Interact with Cache Enabler from the command line.
 *
 * @since  1.3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Enabler_CLI {
    /**
     * Clear the page cache.
     *
     * ## OPTIONS
     *
     * [--ids=<id>]
     * : Clear the cache of a given post ID. Separate multiple IDs with commas.
     *
     * [--urls=<url>]
     * : Clear the cache of a given URL. The URL can be with or without a scheme,
     * wildcard path, and query string. Separate multiple URLs with commas.
     *
     * [--sites=<site>]
     * : Clear the cache of a given blog ID. Separate multiple blog IDs with commas.
     *
     * ## EXAMPLES
     *
     *    # Clear all pages cache.
     *    $ wp cache-enabler clear
     *    Success: Site cache cleared.
     *
     *    # Clear the page cache of post IDs 1, 2, and 3.
     *    $ wp cache-enabler clear --ids=1,2,3
     *    Success: Pages cache cleared.
     *
     *    # Clear the page cache of https://www.example.com/about-us/.
     *    $ wp cache-enabler clear --urls=www.example.com/about-us/
     *    Success: Page cache cleared.
     *
     *    # Clear the page cache of any URL that starts with https://www.example.com/blog/how-to-.
     *    $ wp cache-enabler clear --urls=www.example.com/blog/how-to-*
     *    Success: Page cache cleared.
     *
     *    # Clear the page cache of https://www.example.com/blog/ and all of its subpages.
     *    $ wp cache-enabler clear --urls=www.example.com/blog/*
     *    Success: Page cache cleared.
     *
     *    # Clear the page cache of sites with blog IDs 1, 2, and 3.
     *    $ wp cache-enabler clear --sites=1,2,3
     *    Success: Sites cache cleared.
     *
     * @alias clear
     */
    public function clear( $args, $assoc_args ) {

        $assoc_args = wp_parse_args(
            $assoc_args,
            array(
                'ids'   => '',
                'urls'  => '',
                'sites' => '',
            )
        );

        if ( $assoc_args['ids'] === '' && $assoc_args['urls'] === '' && $assoc_args['sites'] === '' ) {
            Cache_Enabler::clear_complete_cache();

            return WP_CLI::success( is_multisite() ? esc_html__( 'Network cache cleared.', 'cache-enabler' ) : esc_html__( 'Site cache cleared.', 'cache-enabler' ) );
        }

        if ( $assoc_args['ids'] !== '' || $assoc_args['urls'] !== '' ) {
            array_map( 'Cache_Enabler::clear_page_cache_by_post', explode( ',', $assoc_args['ids'] ) );
            array_map( 'Cache_Enabler::clear_page_cache_by_url', explode( ',', $assoc_args['urls'] ) );

            $separators = substr_count( $assoc_args['ids'], ',' ) + substr_count( $assoc_args['urls'], ',' );

            if ( $separators > 0 ) {
                return WP_CLI::success( esc_html__( 'Pages cache cleared.', 'cache-enabler' ) );
            } else {
                return WP_CLI::success( esc_html__( 'Page cache cleared.', 'cache-enabler' ) );
            }
        }

        if ( $assoc_args['sites'] !== '' ) {
            array_map( 'Cache_Enabler::clear_page_cache_by_site', explode( ',', $assoc_args['sites'] ) );

            $separators = substr_count( $assoc_args['sites'], ',' );

            if ( $separators > 0 ) {
                return WP_CLI::success( esc_html__( 'Sites cache cleared.', 'cache-enabler' ) );
            } else {
                return WP_CLI::success( esc_html__( 'Site cache cleared.', 'cache-enabler' ) );
            }
        }
    }
}
