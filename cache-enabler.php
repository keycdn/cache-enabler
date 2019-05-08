<?php
/*
Plugin Name: Cache Enabler
Text Domain: cache-enabler
Description: Simple and fast WordPress disk caching plugin.
Author: KeyCDN
Author URI: https://www.keycdn.com
License: GPLv2 or later
Version: 1.3.4
*/

/*
Copyright (C)  2017 KeyCDN
Copyright (C)  2015 Sergej Müller

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/


// exit
defined('ABSPATH') OR exit;


// constants
define('CE_FILE', __FILE__);
define('CE_DIR', dirname(__FILE__));
define('CE_BASE', plugin_basename(__FILE__));
define('CE_CACHE_DIR', WP_CONTENT_DIR. '/cache/cache-enabler');
define('CE_MIN_WP', '4.1');

// hooks
add_action(
    'plugins_loaded',
    array(
        'Cache_Enabler',
        'instance'
    )
);
register_activation_hook(
    __FILE__,
    array(
        'Cache_Enabler',
        'on_activation'
    )
);
register_deactivation_hook(
    __FILE__,
    array(
        'Cache_Enabler',
        'on_deactivation'
    )
);
register_uninstall_hook(
    __FILE__,
    array(
        'Cache_Enabler',
        'on_uninstall'
    )
);


// autoload register
spl_autoload_register('cache_autoload');

// autoload function
function cache_autoload($class) {
    if ( in_array($class, array('Cache_Enabler', 'Cache_Enabler_Disk')) ) {
        require_once(
            sprintf(
                '%s/inc/%s.class.php',
                CE_DIR,
                strtolower($class)
            )
        );
    }
}
