<?php
/**
 * Integration tests for the analytics AJAX endpoint (isxf_get_analytics_data).
 *
 * Regression coverage: the endpoint previously fataled with
 * "Class ISXF\Ajax\DateTimeImmutable not found" (unqualified class inside a
 * namespace), leaving the dashboard stuck on "Loading…" — no test exercised
 * the endpoint at the time.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group analytics
 */
class AnalyticsTest extends WP_Ajax_UnitTestCase {

    public function set_up() {
        parent::set_up();
        isxf_create_db_table();

        // The endpoint is gated on manage_options; ajax tests run as user 0 by default.
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

        // Seed one entry so aggregates have data.
        ( new \ISXF\Repository\EntryRepository() )->insert_submission(
            [ 'name' => 'Analytics Seed', 'email' => 'seed@example.com' ],
            1,
            '127.0.0.1'
        );
    }

    private function call_endpoint( array $params = [] ) {
        $_POST = array_merge(
            [
                'nonce' => wp_create_nonce( 'isxf_analytics_nonce' ),
                'range' => '30',
            ],
            $params
        );
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax( 'isxf_get_analytics_data' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected path — response captured in _last_response.
        }
        return json_decode( $this->_last_response, true );
    }

    public function test_endpoint_returns_success_with_expected_structure(): void {
        $response = $this->call_endpoint();

        $this->assertNotNull( $response, 'Response must be valid JSON, got: ' . substr( (string) $this->_last_response, 0, 200 ) );
        $this->assertTrue( $response['success'] );
        foreach ( [ 'stats', 'daily', 'status_counts', 'top_forms', 'recent' ] as $key ) {
            $this->assertArrayHasKey( $key, $response['data'], "Missing data key: $key" );
        }
        $this->assertSame( 1, $response['data']['stats']['total'] );
        // 30-day lookback includes both the start day and today → 31 buckets.
        $this->assertCount( 31, $response['data']['daily'] );
    }

    public function test_endpoint_supports_custom_date_range(): void {
        $today = wp_date( 'Y-m-d' );
        $response = $this->call_endpoint(
            [
                'range'      => 'custom',
                'start_date' => $today,
                'end_date'   => $today,
            ]
        );

        $this->assertTrue( $response['success'] );
        $this->assertCount( 1, $response['data']['daily'] );
    }

    public function test_endpoint_rejects_bad_nonce(): void {
        $_POST = [ 'nonce' => 'bogus', 'range' => '30' ];
        $_REQUEST = $_POST;

        $this->expectException( 'WPAjaxDieStopException' );
        $this->_handleAjax( 'isxf_get_analytics_data' );
    }
}
