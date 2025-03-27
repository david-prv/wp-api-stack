<?php

/**
 * Disable error reporting.
 */
error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);

/**
 * Define the necessary constants.
 */
define("ABSPATH", dirname(__FILE__) . DIRECTORY_SEPARATOR);
define("BACKPRESS_PATH", ABSPATH . "wp-includes" . DIRECTORY_SEPARATOR);
define("SCHEMAS_PATH", ABSPATH . "schemas" . DIRECTORY_SEPARATOR);
define("WP_PLUGIN_DIR", ABSPATH . "wp-content" . DIRECTORY_SEPARATOR . "plugins");

/**
 * Recursively load all the files in the given directory.
 * 
 * @param string $dir The directory to load the files from.
 * @return void
 */
function bp_load($dir){
    $ignored_files = [
        BACKPRESS_PATH . "loader.wp-object-cache-memcached.php",
        BACKPRESS_PATH . "loader.wp-object-cache.php",
        BACKPRESS_PATH . "class.wp-object-cache-memcached.php",
        BACKPRESS_PATH . "license.txt",
        SCHEMAS_PATH . "license.txt",
    ];

    $ignored_dirs = [
        BACKPRESS_PATH . "pomo" . DIRECTORY_SEPARATOR . ".svn",
        BACKPRESS_PATH . "pomo" . DIRECTORY_SEPARATOR . "sample"
    ];

    if(in_array($dir, $ignored_dirs)) return;

    $ffs = scandir($dir);

    unset($ffs[array_search(".", $ffs, true)]);
    unset($ffs[array_search("..", $ffs, true)]);

    if (count($ffs) < 1)
        return;

    foreach($ffs as $ff){
        $filename = $dir . DIRECTORY_SEPARATOR . $ff;

        if(is_file($filename) && !in_array($filename, get_included_files()) && !in_array($filename, $ignored_files)) {
            require_once $filename;
        }

        if(is_dir($dir . DIRECTORY_SEPARATOR . $ff) && !in_array($dir, $ignored_dirs)) bp_load($dir . DIRECTORY_SEPARATOR . $ff);
    }
}

/**
 * Load the active plugins.
 */
function bp_load_plugins() {
    // Load active plugins.
    foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
        wp_register_plugin_realpath( $plugin );
        include_once $plugin;
    }
    unset( $plugin );
}

/**
 * Load the necessary files.
 */
bp_load(ABSPATH . "wp-includes");
bp_load(ABSPATH . "schemas");

/**
 * Necessary global variables for the code to work.
 */
$GLOBALS['wp_filter'] = [];
$GLOBALS['shortcode_tags'] = [];
$GLOBALS['_registered_pages'] = [];
$GLOBALS['wp_plugin_paths'] = [];
$GLOBALS['wpdb'] = new BPDB("root", "", "wp_test", "localhost");

BP_Options::set_db($GLOBALS['wpdb']);

/**
 * Implements the missing rest_get_server function.
 * 
 * @return WP_REST_Server
 */
if (!defined("rest_get_server")) {
    function rest_get_server() {
        return new WP_REST_Server();
    }
}

/**
 * Implements the missing backpress_get_option function.
 */
if (!defined("get_option")) {
    function get_option($option, $default = false) {
        return backpress_get_option($option) ?? $default;
    }
}

/**
 * Implements the missing backpress_add_option function.
 */
if (!defined("add_option")) {
    function add_option($option, $value, $deprecated = '', $autoload = 'yes') {
        return backpress_add_option($option, $value, $deprecated, $autoload);
    }
}

/**
 * Implements the missing wp_is_stream function.
 */
if (!defined("wp_is_stream")) {
    function wp_is_stream( $path ) {
        $scheme_separator = strpos( $path, '://' );
    
        if ( false === $scheme_separator ) {
            return false;
        }
    
        $stream = substr( $path, 0, $scheme_separator );
    
        return in_array( $stream, stream_get_wrappers(), true );
    }
}

/**
 * Implements the missing wp_normalize_path function.
 */
if (!defined("wp_normalize_path")) {
    function wp_normalize_path( $path ) {
        $wrapper = '';
    
        if ( wp_is_stream( $path ) ) {
            list( $wrapper, $path ) = explode( '://', $path, 2 );
            $wrapper .= '://';
        }
    
        $path = str_replace( '\\', '/', $path );
        $path = preg_replace( '|(?<=.)/+|', '/', $path );

        if ( ':' === substr( $path, 1, 1 ) ) {
            $path = ucfirst( $path );
        }
    
        return $wrapper . $path;
    }
}

if (!defined("wp_get_active_and_valid_plugins")) {
    function wp_get_active_and_valid_plugins() {
        $plugins = get_option("active_plugins", array());
        // print_r($plugins);
        $valid_plugins = array();
    
        foreach ($plugins as $plugin) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                $valid_plugins[] = WP_PLUGIN_DIR . '/' . $plugin;
            }
        }
    
        return $valid_plugins;
    }
}

/**
 * Implements the missing get_plugin_data function.
 */
if (!defined("get_plugin_data")) {
    function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
        $default_headers = array(
            'Name'            => 'Plugin Name',
            'PluginURI'       => 'Plugin URI',
            'Version'         => 'Version',
            'Description'     => 'Description',
            'Author'          => 'Author',
            'AuthorURI'       => 'Author URI',
            'TextDomain'      => 'Text Domain',
            'DomainPath'      => 'Domain Path',
            'Network'         => 'Network',
            'RequiresWP'      => 'Requires at least',
            'RequiresPHP'     => 'Requires PHP',
            'UpdateURI'       => 'Update URI',
            'RequiresPlugins' => 'Requires Plugins',
            '_sitewide'       => 'Site Wide Only',
        );

        return $default_headers;
    }
}

/**
 * Implements the missing wp_register_plugin_realpath function.
 */
if (!defined("wp_register_plugin_realpath")) {
    function wp_register_plugin_realpath( $file ) {
        global $wp_plugin_paths;
    
        // Normalize, but store as static to avoid recalculation of a constant value.
        static $wp_plugin_path = null, $wpmu_plugin_path = null;
    
        if ( ! isset( $wp_plugin_path ) ) {
            $wp_plugin_path   = wp_normalize_path( WP_PLUGIN_DIR );
        }
    
        $plugin_path     = wp_normalize_path( dirname( $file ) );
        $plugin_realpath = wp_normalize_path( dirname( realpath( $file ) ) );
    
        if ( $plugin_path === $wp_plugin_path ) {
            return false;
        }
    
        if ( $plugin_path !== $plugin_realpath ) {
            $wp_plugin_paths[ $plugin_path ] = $plugin_realpath;
        }
    
        return true;
    }
}

add_option("active_plugins", array("hello-dolly/hello.php"));
bp_load_plugins();

// print_r(get_defined_functions());
// print_r($GLOBALS["wp_filter"]);