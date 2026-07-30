<?php
/**
 * Integration test for the Phase 2.4 AJAX controller split.
 *
 * Asserts the full wp_ajax hook surface is still registered after the plugin
 * bootstrap — same action names as before the AjaxHandler → controllers split.
 *
 * Requires the WordPress test suite (skipped otherwise — see tests/bootstrap.php).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group ajax
 */
class AjaxHooksTest extends WP_UnitTestCase {

    public function test_all_ajax_actions_registered_after_bootstrap(): void {
        $actions = [
            'wp_ajax_isxf_submit_form',
            'wp_ajax_nopriv_isxf_submit_form',
            'wp_ajax_isxf_send_test_email',
            'wp_ajax_isxf_update_entry_status',
            'wp_ajax_isxf_update_entry_note',
            'wp_ajax_isxf_get_analytics_data',
            'wp_ajax_isxf_preview_email',
            'wp_ajax_isxf_send_template_test',
        ];

        foreach ( $actions as $action ) {
            $this->assertNotFalse( has_action( $action ), $action . ' is not registered' );
        }
    }

    public function test_legacy_class_alias_still_resolves(): void {
        $this->assertTrue( class_exists( 'ISXF_AJAX_Handler' ) );
        $this->assertTrue( is_a( 'ISXF_AJAX_Handler', ISXF\AjaxHandler::class, true ) );
    }
}
