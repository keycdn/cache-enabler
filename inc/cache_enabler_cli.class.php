<?php
/**
 * Interact with Cache Enabler.
 */
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
     * ## EXAMPLES
     *
     * # Clear all page caches
     * wp cache-enabler clear
     *
     * # Clear the cache for object IDs 1, 2, and 3
     * wp cache-enabler clear --ids=1,2,3
     *
     * # Clear the cache for a particular URL
     * wp cache-enabler clear --urls=https://example.com/about-us
     *
     * @alias clear
     */
    public function clear( $args, $assoc_args ) {
        $assoc_args = wp_parse_args(
            $assoc_args,
            array(
                'ids'  => '',
                'urls' => '',
            )
        );

        // clear everything if we aren't given IDs and/or URLs.
        if ( empty( $assoc_args['ids'] ) && empty( $assoc_args['urls'] ) ) {
            Cache_Enabler::clear_total_cache();

            return WP_CLI::success( esc_html__( 'The page cache has been cleared.', 'cache-enabler' ) );
        }

        // clear specific IDs and/or URLs.
        array_map( 'Cache_Enabler::clear_page_cache_by_post_id', explode( ',', $assoc_args['ids'] ) );
        array_map( 'Cache_Enabler::clear_page_cache_by_url', explode( ',', $assoc_args['urls'] ) );

        WP_CLI::success( 'The requested caches have been cleared.', 'cache-enabler' );
    }
}
