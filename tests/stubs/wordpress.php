<?php
/**
 * Just enough of WordPress for the unit tests of the hook classes. The suite loads no WordPress:
 * each function returns its input, and `flywp()` answers with the brand a test sets.
 */

if ( ! defined( 'FLYWP_PLUGIN_BASENAME' ) ) {
    define( 'FLYWP_PLUGIN_BASENAME', 'flywp/flywp.php' );
}

if ( ! defined( 'FLYWP_PLUGIN_URL' ) ) {
    define( 'FLYWP_PLUGIN_URL', 'https://example.test/wp-content/plugins/snoka-helper/' );
}

if ( ! defined( 'FLYWP_PLUGIN_FILE' ) ) {
    define( 'FLYWP_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/flywp.php' );
}

if ( ! defined( 'SNOKA_ADMIN_USER' ) ) {
    define( 'SNOKA_ADMIN_USER', 'csadmin' );
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user() {
        return (object) [ 'user_login' => $GLOBALS['flywp_test_user_login'] ?? 'csadmin' ];
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return $url;
    }
}

if ( ! function_exists( 'get_plugin_data' ) ) {
    function get_plugin_data( $file, $markup = true, $translate = true ) {
        return [ 'Name' => 'Snoka Helper' ];
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
        $GLOBALS['flywp_test_filters'][ $hook ] = $args;

        return true;
    }
}

if ( ! function_exists( 'flywp' ) ) {
    function flywp() {
        return $GLOBALS['flywp_test_plugin'];
    }
}
