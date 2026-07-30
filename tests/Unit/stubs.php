<?php
/**
 * Lightweight WordPress stubs for pure unit tests (no WordPress, no DB).
 *
 * Every symbol is defined ONLY if it does not already exist, so this file is
 * safe to load in any order. Test-visible state lives in globals:
 *
 * - $GLOBALS['isxf_test_options']  — backing store for get_option().
 * - $GLOBALS['isxf_test_filters']  — hook => callable registry for apply_filters().
 * - $GLOBALS['isxf_test_log']      — messages captured by isxf_log_error().
 *
 * @package InsightX_Form\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' ); // Plugin files bail out when this is missing.
}

if ( ! function_exists( 'wp_salt' ) ) {
    /**
     * Deterministic salt for unit tests (per scheme, like real WP).
     *
     * @param string $scheme Salt scheme.
     * @return string
     */
    function wp_salt( $scheme = 'auth' ) {
        return 'isxf-unit-test-salt|' . $scheme;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    /**
     * Minimal WP_Error stand-in.
     */
    class WP_Error {
        /** @var string */
        private $code;
        /** @var string */
        private $message;

        /**
         * @param string $code    Error code.
         * @param string $message Error message.
         */
        public function __construct( $code = '', $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }

        /**
         * @return string
         */
        public function get_error_code() {
            return $this->code;
        }

        /**
         * @return string
         */
        public function get_error_message() {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    /**
     * @param mixed $thing Value to check.
     * @return bool
     */
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'isxf_log_error' ) ) {
    /**
     * Capture log messages instead of writing to error_log.
     *
     * @param mixed $message Message to log.
     */
    function isxf_log_error( $message ) {
        $GLOBALS['isxf_test_log'][] = $message;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    /**
     * @param string $option  Option name.
     * @param mixed  $default Default when unset.
     * @return mixed
     */
    function get_option( $option, $default = false ) {
        $options = isset( $GLOBALS['isxf_test_options'] ) ? $GLOBALS['isxf_test_options'] : [];
        return array_key_exists( $option, $options ) ? $options[ $option ] : $default;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    /**
     * @param string $hook  Hook name.
     * @param mixed  $value Value to filter.
     * @return mixed
     */
    function apply_filters( $hook, $value ) {
        $filters = isset( $GLOBALS['isxf_test_filters'] ) ? $GLOBALS['isxf_test_filters'] : [];
        if ( isset( $filters[ $hook ] ) && is_callable( $filters[ $hook ] ) ) {
            return call_user_func( $filters[ $hook ], $value );
        }
        return $value;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    /**
     * No-op — unit tests never trigger hooks.
     *
     * @return true
     */
    function add_action() {
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    /**
     * No-op — unit tests never trigger hooks.
     *
     * @return true
     */
    function add_filter() {
        return true;
    }
}

if ( ! function_exists( 'wp_cache_get' ) ) {
    /**
     * In-memory object-cache stubs (non-persistent, per-request semantics —
     * exactly what the plugin's 'isxf' cache group assumes). Backing store:
     * $GLOBALS['isxf_test_cache'], keyed "group:key".
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return mixed Cached value, or false on a miss (WP semantics).
     */
    function wp_cache_get( $key, $group = '' ) {
        $store = isset( $GLOBALS['isxf_test_cache'] ) ? $GLOBALS['isxf_test_cache'] : [];
        $full  = $group . ':' . $key;
        return array_key_exists( $full, $store ) ? $store[ $full ] : false;
    }
}

if ( ! function_exists( 'wp_cache_set' ) ) {
    /**
     * @param string $key   Cache key.
     * @param mixed  $data  Value to store.
     * @param string $group Cache group.
     * @return true
     */
    function wp_cache_set( $key, $data, $group = '' ) {
        $GLOBALS['isxf_test_cache'][ $group . ':' . $key ] = $data;
        return true;
    }
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
    /**
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return true
     */
    function wp_cache_delete( $key, $group = '' ) {
        unset( $GLOBALS['isxf_test_cache'][ $group . ':' . $key ] );
        return true;
    }
}
