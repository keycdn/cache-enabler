=== Cache Enabler ===
Contributors: keycdn
Tags: cache, caching, performance, webp, gzip, brotli, mobile, speed
Tested up to: 6.1
Stable tag: 1.8.12
Requires at least: 5.1
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


A lightweight caching plugin for WordPress that makes your website faster by generating static HTML files.


== Description ==
Cache Enabler is a simple, yet powerful WordPress caching plugin that is easy to use, needs minimal configuration, and best of all helps improve site performance for a faster load time. It creates static HTML files of frontend pages and stores them on the server's disk. This allows the static HTML files to be delivered instead of generating pages on the fly, avoiding resource intensive backend processes from the WordPress core, plugins, and database.


= Features =
* Fast and efficient cache engine
* Automatic smart cache clearing
* Manual cache clearing
* WP-CLI cache clearing
* Cache expiry
* WebP support (convert images to WebP with [Optimus](https://optimus.io))
* Mobile support
* Brotli and Gzip pre-compression support
* Minification of HTML excluding or including inline CSS and JavaScript
* Real-time cache size display in the WordPress dashboard
* Custom post type support
* `304 Not Modified` support
* Works perfectly with [Autoptimize](https://wordpress.org/plugins/autoptimize/)


= How does the caching work? =
Cache Enabler captures page contents and saves it as a static HTML file on the server's disk. The static HTML file created can be one of several possible cache versions depending on the plugin settings and HTTP request. Accepted static HTML files are then delivered without any database queries or on the fly compression, allowing for a quicker page load.


= Documentation =
* [Installation](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#installation)
* [Settings](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#settings)
* [Hooks](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#hooks)
* [WP-CLI](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#wp-cli)
* [Advanced configuration](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#advanced-configuration)
* [FAQ](https://www.keycdn.com/support/wordpress-cache-enabler-plugin#faq)


= Want to help? =
* Want to file a bug, contribute some code, or improve translations? Excellent! Check out our [GitHub issues](https://github.com/keycdn/cache-enabler/issues) or [translations](https://translate.wordpress.org/projects/wp-plugins/cache-enabler/).


= Maintainer =
* [KeyCDN](https://www.keycdn.com)


== Changelog ==

= 1.8.13 =
* WordPress 6.1 compatibility

= 1.8.12 =
* Update directory validation (@robwoodgate)

= 1.8.11 =
* Fix directory validation

= 1.8.10 =
* Fix recursive chmod issue (#317 @robwoodgate)

= 1.8.9 =
* Update sanitization

= 1.8.8 =
* Add server input sanitization

= 1.8.7 =
* Update plugin upgrade process for multisite networks (#303)
* Update `wp-config.php` file handling (#302)

= 1.8.6 =
* Update requirements check notices (#300)
* Update `advanced-cache.php` drop-in file handling (#297)
* Add additional validation when creating cached files (#299)
* Add type casts to several filter hooks (#299)
* Add `cache_enabler_settings_before_validation` filter hook (#298)

= 1.8.5 =
* Update required WordPress version from 5.5 to 5.1 (#295)
* Fix plugin upgrade process when disk settings are outdated and a frontend page is requested (#295)

= 1.8.4 =
* Update `advanced-cache.php` drop-in file handling (#292)

= 1.8.3 =
* Update index file handling (#289)

= 1.8.2 =
* Update cache size transient handling (#287)

= 1.8.1 =
* Fix requirements check (#285)

= 1.8.0 =
* Update `advanced-cache.php` drop-in file handling to improve reliability and compatibility (#260 and #283)
* Update settings file to be deleted before the `home` option is updated to prevent a leftover settings file (#279)
* Update `cache_enabler_bypass_cache` filter hook default value to allow a complete override (#277)
* Update cache size transient to be in real time (#237 and #269)
* Update cache expiry time to always be a non-negative integer (#265)
* Update WP-CLI `clear` subcommand (#261)
* Update required WordPress version from 5.1 to 5.5 (#260)
* Update plugin upgrade process to improve reliability and compatibility (#260)
* Update getting the cache file path to improve creating cache files (#256)
* Update HTML5 doctype check to be less strict (#254)
* Update permalink structure handling (#251 and #263)
* Update requirements check to improve notices shown (#249 and #260)
* Update cache clearing structure to enhance the automatic cache clearing actions (#247)
* Add WP-Cron event to clear the expired cache on an hourly basis (#237, #268, and #281)
* Add new cache clearing structure for option actions (#272 and #280)
* Add cache engine restart support (#271 and #278)
* Add `constants.php` file to plugin directory to allow constant overrides (#260)
* Add wildcard cache clearing support (#246)
* Add Brotli compression support (#243 @nlemoine)
* Add new cache clearing structure for term actions (#234 @davelit)
* Add cache iterator to improve cache object handling (#237)
* Fix WebP URL conversion edge case (#275)
* Deprecate `cache_enabler_clear_site_cache_by_blog_id` and `cache_enabler_clear_page_cache_by_post_id` action hooks in favor of replacements (#247 and #274)

= 1.7.2 =
* Update string to be translatable (#235 @timse201)
* Add `cache_enabler_mkdir_mode` filter hook (#233)

= 1.7.1 =
* Fix directory creation handling (#221 @stevegrunwell)

= 1.7.0 =
* Update cache clearing for theme, plugin, post, and upgrade actions (#215 and #216)
* Update cache handling with cache keys (#211)
* Update settings file deletion handling (#205)
* Update output buffer handling (#203)
* Update removing CSS and JavaScript comments during HTML minification (#202)
* Update WebP URL conversion for installations in a subdirectory (#198)
* Add `CACHE_ENABLER_DIR` as definable plugin directory constant (#195 @stevegrunwell)
* Add explicit directory access permissions (#194 @stevegrunwell)
* Add exclusive lock when writing files (#191 @nawawi)
* Fix clear cache request handling (#212)
* Fix getting `wp-config.php` (#210 @stevegrunwell)

= 1.6.2 =
* Fix removing CSS and JavaScript comments during HTML minification (#188)

= 1.6.1 =
* Update requirements check (#186)
* Update cache clearing behavior for comment actions (#185)
* Update HTML minification to remove CSS and JavaScript comments (#184)
* Update site cache clearing behavior for multisite networks to ensure cache cleared action hooks are fired when using WP-CLI or clear cache action hooks (#180)
* Add `cache_enabler_convert_webp_attributes` and `cache_enabler_convert_webp_ignore_query_strings` filter hooks (#183)
* Fix cache clearing behavior on WooCommerce stock update (#179)

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
2. Cache Enabler cache size in the WordPress dashboard
