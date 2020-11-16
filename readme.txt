=== Cache Enabler - WordPress Cache ===
Contributors: keycdn
Tags: cache, caching, wordpress cache, wp cache, performance, gzip, webp, http2
Requires at least: 5.1
Tested up to: 5.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html



A lightweight caching plugin for WordPress that makes your website faster by generating static HTML files plus WebP support.



== Description ==

= WordPress Cache Engine =
The Cache Enabler plugin creates static HTML files and stores them on the servers disk. The web server will deliver the static HTML file and avoids the resource intensive backend processes (core, plugins and database). This WordPress cache engine will improve the performance of your website.


= Features =
* Efficient and fast disk cache engine
* Automated and/or manual clearing of the cache
* Manually clear the cache of specific pages
* WP-CLI cache clearing
* Display of the actual cache size in your dashboard
* Minification of HTML and inline JavaScript
* WordPress multisite support
* Custom Post Type support
* Expiry Directive
* Support of *304 Not Modified* if the page has not modified since last cached
* WebP Support (when combined with [Optimus](https://optimus.io "Optimus"))
* Supports responsive images via srcset since WP 4.4
* Works perfectly with [Autoptimize](https://wordpress.org/plugins/autoptimize/)

> Cache Enabler is the first WP plugin to allow you to serve WebP images without JavaScript and also fully supports srcset since WP 4.4. WebP is a new image format that provides lossless and lossy compression for images on the web. WebP lossless images are [26% smaller](https://developers.google.com/speed/webp/docs/webp_lossless_alpha_study#results "webp lossless alpha study") in size compared to PNGs.


= How does the caching work? =
This plugin requires minimal setup time and allows you to easily take advantage of the benefits that come from using WordPress caching.

The WordPress Cache Enabler has the ability to create 2 cached files. One is plain HTML and the other version is gzipped (gzip level 9). These static files are then used to deliver content faster to your users without any database lookups or gzipping as the files are already pre-compressed.

When combined with Optimus, the WordPress Cache Enabler allows you to easily deliver WebP images. The plugin will check your upload directory for any JPG or PNG images that have an equivalent WebP file. If there is, the URI of these image will be cached in a WebP static file by Cache Enabler. It is not required for all images to be converted to WebP when the "Create an additional cached version for WebP image support" option is enabled. This will not break any images that are not in WebP format. The plugin will deliver images that do have a WebP equivalent and will fall back to the JPG or PNG format for images that don't.


= WP-CLI =

* Clear all pages cache.
    `wp cache-enabler clear`

* Clear the page cache for post IDs 1, 2, and 3.
    `wp cache-enabler clear --ids=1,2,3`

* Clear the page cache for a particular URL.
    `wp cache-enabler clear --urls=https://example.com/about-us`

* Clear all pages cache for sites with blog IDs 1, 2, and 3.
    `wp cache-enabler clear --sites=1,2,3`


= Website =
* [WordPress Cache Enabler - Documentation](https://www.keycdn.com/support/wordpress-cache-enabler-plugin "WordPress Cache Enabler - Documentation")


= System Requirements =
* PHP >=5.6
* WordPress >=5.1


= Contribute =
* Anyone is welcome to contribute to the plugin on [GitHub](https://github.com/keycdn/cache-enabler).
* Please merge (squash) all your changes into a single commit before you open a pull request.


= Maintainer =
* [KeyCDN](https://www.keycdn.com "KeyCDN")


== Changelog ==

= 1.6.0 =
* Update cache clearing behavior for multisite networks when permalink structure has changed to prevent unnecessary cache clearing (#170)
* Update cache clearing behavior for comment actions to prevent unnecessary cache clearing (#169)
* Update output buffer timing to start earlier on the `advanced-cache.php` drop-in instead of the `init` hook (#168)
* Update plugin upgrade handling (#166)
* Add `cache_enabler_clear_complete_cache`, `cache_enabler_clear_site_cache`, `cache_enabler_clear_site_cache_by_blog_id`, `cache_enabler_clear_page_cache_by_post_id`, `cache_enabler_clear_page_cache_by_url`, `cache_enabler_complete_cache_cleared`, `cache_enabler_site_cache_cleared`, and `cache_enabler_page_cache_cleared` action hooks (#170)
* Add `cache_enabler_user_can_clear_cache`, `cache_enabler_exclude_search`, `cache_enabler_bypass_cache`, `cache_enabler_page_contents_before_store`, `cache_enabler_page_contents_after_webp_conversion`, `cache_enabler_minify_html_ignore_tags` filter hooks (#170)
* Add site cache clearing behavior (#167)
* Fix requirement notices being shown to all users (#170)
* Fix setting up new site in multisite network when new site is added outside of the admin interface (#170)
* Fix getting cache size for main site in subdirectory network (#164)
* Fix deleting cache size transient (#164)
* Fix cache clearing (#164 and #167)
* Fix clear cache request validation
* Deprecate `ce_clear_cache`, `ce_clear_post_cache`, `ce_action_cache_cleared`, and `ce_action_cache_by_url_cleared` action hooks in favor of replacements (#170)
* Deprecate `user_can_clear_cache`, `bypass_cache`, `cache_enabler_before_store`, `cache_enabler_disk_webp_converted_data`, and `cache_minify_ignore_tags` filter hooks in favor of replacements (#170)

= 1.5.5 =
* Update advanced cache to prevent potential errors (#161)
* Update getting settings to create settings file if cache exists but settings file does not (#159)
* Fix getting settings file edge cases (#158)
* Fix cache expiry

= 1.5.4 =
* Update default query string exclusion (#155)
* Update cache engine start check (#155)

= 1.5.3 =
* Add default query string exclusion (#154)

= 1.5.2 =
* Update late cache engine start to be on the `init` hook instead of `plugins_loaded` (#153)
* Add deprecated variable that was previously deleted to improve backwards compatibility (#153)
* Fix WP-CLI notice errors (#153)
* Fix creating settings file on plugin update

= 1.5.1 =
* Fix getting settings file

= 1.5.0 =
* Update settings file type to PHP instead of JSON (#147)
* Update settings file(s) storage location (#147)
* Update plugin activation, deactivation, and uninstall handling (#147)
* Update HTML minification to also include or exclude inline CSS (#147)
* Update cache size handling for multisite networks (#147)
* Update `WP_CACHE` constant handling (#140)
* Update cache cleared admin notice (#139)
* Update admin bar clear cache buttons (#139)
* Update output buffer timing to start earlier on the `init` hook instead of `template_redirect` (#137)
* Update default cache behavior to not bypass the cache for query strings (#129)
* Update cache clearing setting for when any post type is published to include all post actions (#142)
* Update cache clearing setting for post actions to clear the page and/or associated cache by default (#142)
* Update settings page layout (#129 and #142)
* Update WebP URL conversion for images with density descriptors (#125)
* Add cache engine to improve handling and performance (#147)
* Add cache bypass method for Ajax, REST API, and XMLRPC requests (#147)
* Add new cache clearing structure for post publish, update, and trash actions (#129)
* Add post type, taxonomies, author, and date archives to the new associated cache (#129)
* Add new cache exclusions setting for query strings (#129)
* Fix cache size file status edge case (#147)
* Fix `WP_CACHE` constant not being set edge case (#140)
* Fix settings file from using unvalidated data (#129)
* Fix clear URL admin bar button for installations in a subdirectory (#127)
* Fix WebP URL conversion for installations in a subdirectory (#125)
* Remove cache clearing publishing action from post sidebar in favor of the new cache clearing structure for post actions (#129)
* Remove cache clearing setting for WooCommerce stock updates in favor of the new cache clearing structure for post actions (#129)
* Remove cache inclusions setting for URL query parameters because of the updated default cache behavior for query strings (#129)

= 1.4.9 =
* Fix WebP URL conversion changing all image paths to lowercase

= 1.4.8 =
* Update WebP URL conversion for inline CSS (#116)
* Update WP-CLI `clear` subcommand messages (#111)
* Update WP-CLI `clear` subcommand for multisite networks (#111)
* Fix WebP URL conversion image matching edge cases (#116)
* Fix cache clearing for installations in a subdirectory
* Fix advanced cache settings recognition for installations in a subdirectory
* Fix file permissions requirement notice

= 1.4.7 =
* Update getting `wp-config.php` if one level above installation (#106)
* Add clear types for strict cache clearing (#110)
* Fix advanced cache settings recognition for subdirectory multisite networks
* Fix WP-CLI `clear` subcommand for post IDs (#110)
* Fix scheme-based caching for NGINX/PHP-FPM (#109 @centminmod)
* Fix trailing slash handling

= 1.4.6 =
* Add cache bypass method for sitemaps (#104)
* Fix cache clearing for subdirectory multisite networks (#103)

= 1.4.5 =
* Update `WP_CACHE` constant handling (#102)
* Add cache bypass method for `WP_CACHE` constant (#102)
* Add translation descriptions (#102)
* Fix cache handling for default redirects (#102)

= 1.4.4 =
* Update cache handling for HTTP status codes (#100)

= 1.4.3 =
* Update cache clearing by URL (#99)
* Fix advanced cache settings updating unnecessarily (#99)

= 1.4.2 =
* Update cache clearing for the clear URL admin bar button (#98)
* Update scheme-based caching (#98)
* Fix advanced cache path variants (#98)

= 1.4.1 =
* Fix undefined constant

= 1.4.0 =
* Update default cache behavior for WooCommerce stock update (#88)
* Update cache clearing setting for plugin actions (#91)
* Update admin bar clear cache buttons (#96)
* Update cache behavior for logged in users (#95)
* Update default clear cache publishing action (#88)
* Update advanced cache settings (#91 and #92)
* Update trailing slash handling (#91)
* Update settings page (#84 and #92)
* Add cache clearing setting for WooCommerce stock updates (#88)
* Add fbclid as default URL query parameter to bypass cache (#84)
* Add scheme-based caching (#94)
* Fix advanced cache settings recognition for multisite networks (#92)

= 1.3.5 =
* WP-CLI cache clearing (Thanks to Steve Grunwell)
* Added cache_enabler_disk_webp_converted_data filter
* Improved WebP URL conversion
* Fixed advanced cache issue

= 1.3.4 =
* Reverted change to page specific as new default

= 1.3.3 =
* Replaced wp_die in advanced cache

= 1.3.2 =
* Changed to page specific as new default
* Added regex setting for analytics tags in get variables
* Fixed 304 responses

= 1.3.1 =
* Fix for missing trailing slashes was incomplete
* Add filter option before minification

= 1.3.0 =
* Clear cache on WooCommerce stock updates

= 1.2.3 =
* Fix expiry time
* Allow to customize bypass cookies
* Fix Autoptimize config warning
* Pages can now be excluded from cache by a path matching regex
* Plugin upgrades can now trigger cache clear
* Scheduled posts and drafts are now properly handled
* A missing trailing slash will now redirect like WordPress does by default

= 1.2.2 =
* Fixed settings form issue

= 1.2.1 =
* Minor fixes

= 1.2.0 =
* Added advanced cache feature
* Clear cache if reply to a comment in WP admin

= 1.1.0 =
* Added the possibility to clear the cache of a specific URL
* Supports now Windows filesystems
* Added X-Cache-Handler to indicate if loaded through PHP
* Support of WebP images generated by ewww
* Dynamic upload directory for WebP images
* Fixed multisite purge issue
* Added requirements checks
* Made plugin ready for translation

= 1.0.9 =
* Option to disable pre-compression of cached pages if decoding fails

= 1.0.8 =
* Added support for srcset in WP 4.4
* Improved encoding (utf8)

= 1.0.7 =
* Added cache behavior option for new posts
* Improved metainformation of the signature
* Optimized cache handling for nginx

= 1.0.6 =
* Fixed query string related caching issue

= 1.0.5 =
* Credits update

= 1.0.4 =
* Changed WebP static file naming

= 1.0.3 =
* Fixed WebP version switch issue

= 1.0.2 =
* Added support for WebP and CDN Enabler plugin

= 1.0.1 =
* Added WebP support and expiry directive

= 1.0.0 =
* Initial Release


== Screenshots ==

1. Cache Enabler settings page
2. Display of the cache size in your dashboard
