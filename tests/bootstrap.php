<?php
/**
 * PHPUnit bootstrap for InsightX Form.
 *
 * Two environments:
 *
 * - Unit (no WordPress): loads lightweight stubs for the handful of WP
 *   functions the tested classes touch. Active when ISXF_UNIT_ONLY=1, or
 *   when no WP test suite can be located.
 * - Integration (WordPress): loads the WP PHPUnit test suite from
 *   WP_TESTS_DIR (env), the default /tmp/wordpress-tests-lib location used
 *   by bin/install-wp-tests.sh, or the wp-phpunit/wp-phpunit composer
 *   package. Requires MySQL.
 *
 * @package InsightX_Form\Tests
 */

define( 'ISXF_TESTS_DIR', __DIR__ );
define( 'ISXF_PLUGIN_ROOT', dirname( __DIR__ ) );

// Composer autoloader (dev deps + autoload-dev).
if ( file_exists( ISXF_PLUGIN_ROOT . '/vendor/autoload.php' ) ) {
    require_once ISXF_PLUGIN_ROOT . '/vendor/autoload.php';
}

/**
 * Locate the WordPress PHPUnit test suite directory.
 *
 * @return string|null Path with trailing slash trimmed, or null if not found.
 */
function isxf_locate_wp_tests_dir() {
    $candidates = [];
    if ( getenv( 'WP_TESTS_DIR' ) ) {
        $candidates[] = getenv( 'WP_TESTS_DIR' );
    }
    // Default location used by bin/install-wp-tests.sh.
    $candidates[] = sys_get_temp_dir() . '/wordpress-tests-lib';
    // Composer package fallback.
    $candidates[] = ISXF_PLUGIN_ROOT . '/vendor/wp-phpunit/wp-phpunit';

    foreach ( $candidates as $dir ) {
        if ( $dir && file_exists( rtrim( $dir, '/' ) . '/includes/functions.php' ) ) {
            return rtrim( $dir, '/' );
        }
    }
    return null;
}

$wp_tests_dir = getenv( 'ISXF_UNIT_ONLY' ) ? null : isxf_locate_wp_tests_dir();

if ( null !== $wp_tests_dir ) {
    // === Integration environment: real WordPress ===
    define( 'ISXF_TEST_ENV', 'wp' );

    if ( ! defined( 'WP_TESTS_DIR' ) ) {
        define( 'WP_TESTS_DIR', $wp_tests_dir );
    }

    require_once $wp_tests_dir . '/includes/functions.php';

    // Load the plugin under test before WordPress finishes bootstrapping.
    tests_add_filter(
        'muplugins_loaded',
        function () {
            require ISXF_PLUGIN_ROOT . '/advanced-secure-form.php';
        }
    );

    require $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // === Unit environment: stubs instead of WordPress ===
    define( 'ISXF_TEST_ENV', 'unit' );
    require_once ISXF_TESTS_DIR . '/Unit/stubs.php';

    if ( ! getenv( 'ISXF_UNIT_ONLY' ) ) {
        // Running the full suite without a WP test suite: integration tests
        // skip themselves (each file returns early when WP_UnitTestCase is
        // missing). Say so once so the skip is not silent.
        fwrite(
            STDERR,
            "\n[ISXF] WordPress test suite not found — Integration tests will be skipped.\n" .
            "[ISXF] Run bin/install-wp-tests.sh (needs MySQL) or set WP_TESTS_DIR to enable them.\n\n"
        );
    }
}
