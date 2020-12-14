<?php
/**
 * Interact with Cache Enabler.
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
     * : Clear the cache for given post ID(s). Separate multiple IDs with commas.
     *
     * [--urls=<url>]
     * : Clear the cache for the given URL(s). Separate multiple URLs with commas.
     *
     * [--sites=<site>]
     * : Clear the cache for the given blog ID(s). Separate multiple blog IDs with commas.
     *
     * ## EXAMPLES
     *
     *    # Clear all pages cache.
     *    $ wp cache-enabler clear
     *    Success: Site cache cleared.
     *
     *    # Clear the page cache for post IDs 1, 2, and 3.
     *    $ wp cache-enabler clear --ids=1,2,3
     *    Success: Pages cache cleared.
     *
     *    # Clear the page cache for a particular URL.
     *    $ wp cache-enabler clear --urls=https://www.example.com/about-us/
     *    Success: Page cache cleared.
     *
     *    # Clear all pages cache for sites with blog IDs 1, 2, and 3.
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

        // clear complete cache if no associative arguments are given
        if ( empty( $assoc_args['ids'] ) && empty( $assoc_args['urls'] ) && empty( $assoc_args['sites'] ) ) {
            Cache_Enabler::clear_complete_cache();

            return WP_CLI::success( ( is_multisite() ) ? esc_html__( 'Network cache cleared.', 'cache-enabler' ) : esc_html__( 'Site cache cleared.', 'cache-enabler' ) );
        }

        // clear page(s) cache by post ID(s) and/or URL(s)
        if ( ! empty( $assoc_args['ids'] ) || ! empty( $assoc_args['urls'] ) ) {
            array_map( 'Cache_Enabler::clear_page_cache_by_post_id', explode( ',', $assoc_args['ids'] ) );
            array_map( 'Cache_Enabler::clear_page_cache_by_url', explode( ',', $assoc_args['urls'] ) );

            // check if there is more than one ID and/or URL
            $separators = substr_count( $assoc_args['ids'], ',' ) + substr_count( $assoc_args['urls'], ',' );

            if ( $separators > 0 ) {
                return WP_CLI::success( esc_html__( 'Pages cache cleared.', 'cache-enabler' ) );
            } else {
                return WP_CLI::success( esc_html__( 'Page cache cleared.', 'cache-enabler' ) );
            }
        }

        // clear pages cache by blog ID(s)
        if ( ! empty( $assoc_args['sites'] ) ) {
            array_map( 'Cache_Enabler::clear_site_cache_by_blog_id', explode( ',', $assoc_args['sites'] ) );

            // check if there is more than one site
            $separators = substr_count( $assoc_args['sites'], ',' );

            if ( $separators > 0 ) {
                return WP_CLI::success( esc_html__( 'Sites cache cleared.', 'cache-enabler' ) );
            } else {
                return WP_CLI::success( esc_html__( 'Site cache cleared.', 'cache-enabler' ) );
            }
        }
    }
}

// add WP-CLI command
WP_CLI::add_command( 'cache-enabler', 'Cache_Enabler_CLI' );
