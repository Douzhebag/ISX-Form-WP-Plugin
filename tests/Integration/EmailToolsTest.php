<?php
/**
 * Integration tests for the email-template tools AJAX endpoints
 * (isxf_preview_email / isxf_send_template_test, v0.8.0).
 *
 * Covers: nonce and capability gates, sample-data rendering for the
 * booking/inquiry/custom templates (incl. {field:name} merge tags resolved
 * from the POSTed builder fields), the merge-tag-intact editable_body for
 * "edit from this template", and the test-send path (wp_mail called with the
 * SMTP hooks attached, 🧪-prefixed subject, failure reporting).
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group ajax
 * @group email-tools
 */
class EmailToolsTest extends WP_Ajax_UnitTestCase {

    /** @var int */
    private $form_id;

    /** @var int */
    private $admin_id;

    public function set_up() {
        parent::set_up();

        // Silence isxf_maybe_upgrade_db on the admin_init that _handleAjax()
        // fires (its dbDelta output would pollute the JSON response) — same
        // trick SubmissionTest uses.
        isxf_create_db_table();

        $this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->admin_id );

        $this->form_id = self::factory()->post->create(
            [
                'post_type'   => 'isxf_form',
                'post_title'  => 'Email Tools Test Form',
                'post_status' => 'publish',
            ]
        );
    }

    public function tear_down() {
        $_POST    = [];
        $_GET     = [];
        $_REQUEST = [];
        parent::tear_down();
    }

    /**
     * Populate $_POST with a valid preview request (nonce included).
     *
     * @param array $overrides Values merged over the valid defaults.
     */
    private function post_valid_request( array $overrides = [] ): void {
        $_POST = array_merge(
            [
                'nonce'    => wp_create_nonce( 'isxf_email_tools_nonce' ),
                'template' => 'booking',
                'form_id'  => $this->form_id,
                'fields'   => [
                    [ 'label' => 'ส่วนที่ 1', 'name' => 'sec_1', 'type' => 'heading', 'options' => '' ],
                    [ 'label' => 'ชื่อ', 'name' => 'full_name', 'type' => 'text', 'options' => '' ],
                    [ 'label' => 'อีเมล', 'name' => 'email', 'type' => 'email', 'options' => '' ],
                    [ 'label' => 'วันเช็คอิน', 'name' => 'checkin', 'type' => 'check_in', 'options' => '' ],
                    [ 'label' => 'ห้องพัก', 'name' => 'room', 'type' => 'select', 'options' => 'Deluxe, Suite' ],
                ],
            ],
            $overrides
        );
    }

    /**
     * Run an AJAX action and return the decoded JSON response.
     *
     * @param string $action AJAX action name.
     * @return array{success: bool, data: array}
     */
    private function handle_ajax( string $action ): array {
        try {
            $this->_handleAjax( $action );
        } catch ( WPAjaxDieContinueException $e ) {
            // wp_send_json_success() lands here — expected.
        } catch ( WPAjaxDieStopException $e ) {
            // wp_send_json_error() lands here — expected.
        }
        $response = json_decode( $this->_last_response, true );
        $this->assertIsArray( $response, 'Response should be JSON: ' . $this->_last_response );
        return $response;
    }

    public function test_missing_nonce_is_rejected(): void {
        $this->post_valid_request();
        unset( $_POST['nonce'] );

        $caught = null;
        try {
            $this->_handleAjax( 'isxf_preview_email' );
        } catch ( WPAjaxDieStopException $e ) {
            $caught = $e;
        }

        $this->assertNotNull( $caught, 'check_ajax_referer must die on a missing nonce' );
        $this->assertStringContainsString( '-1', $caught->getMessage() );
    }

    public function test_missing_nonce_is_rejected_for_test_send(): void {
        $this->post_valid_request();
        unset( $_POST['nonce'] );

        $caught = null;
        try {
            $this->_handleAjax( 'isxf_send_template_test' );
        } catch ( WPAjaxDieStopException $e ) {
            $caught = $e;
        }

        $this->assertNotNull( $caught, 'check_ajax_referer must die on a missing nonce' );
        $this->assertStringContainsString( '-1', $caught->getMessage() );
    }

    public function test_subscriber_is_rejected(): void {
        $subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber_id );
        $this->post_valid_request(); // nonce created as the subscriber, so the nonce passes

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertFalse( $response['success'], 'Subscribers (no edit_posts) must be rejected' );
        $this->assertSame( 'You do not have permission to perform this action', $response['data']['message'] );
    }

    public function test_booking_preview_contains_sample_data(): void {
        $this->post_valid_request();

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertTrue( $response['success'], 'Preview should succeed: ' . $this->_last_response );
        $html = $response['data']['html'];

        // Full email document through the real template pipeline.
        $this->assertStringContainsString( '<!DOCTYPE html>', $html );
        $this->assertStringContainsString( get_bloginfo( 'name' ), $html );

        // Type-aware sample values, keyed by the POSTed field labels.
        $this->assertStringContainsString( 'somchai@example.com', $html );
        $this->assertStringContainsString( 'Deluxe', $html, 'select fields sample their first option' );
        $this->assertStringContainsString( 'Somchai Jaidee', $html, 'text fields get a sample name' );
        $this->assertStringContainsString( 'ชื่อ', $html );
        $this->assertMatchesRegularExpression( '~\d{2}/\d{2}/\d{4}~', $html, 'date fields get d/m/Y samples' );
        $this->assertStringNotContainsString( 'ส่วนที่ 1', $html, 'Heading fields must be skipped' );

        // Subject mirrors the real booking subject.
        $this->assertStringContainsString( get_bloginfo( 'name' ), $response['data']['subject'] );
    }

    public function test_booking_editable_body_keeps_merge_tags_without_chrome(): void {
        $this->post_valid_request();

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertTrue( $response['success'] );
        $editable = $response['data']['editable_body'];

        $this->assertStringContainsString( '{all_fields}', $editable, 'Editable body must keep merge tags' );
        $this->assertStringNotContainsString( '<!DOCTYPE', $editable, 'Editable body must not include the full-document chrome' );
        $this->assertStringNotContainsString( 'somchai@example.com', $editable, 'Editable body must not contain sample data' );
        $this->assertStringContainsString( '{site_name}', $response['data']['editable_subject'] );
    }

    public function test_inquiry_preview_and_editable_body(): void {
        $this->post_valid_request( [ 'template' => 'inquiry' ] );

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertTrue( $response['success'] );
        $this->assertStringContainsString( 'We Have Received Your Message', $response['data']['html'] );
        $this->assertStringContainsString( 'somchai@example.com', $response['data']['html'] );
        $this->assertStringContainsString( '{all_fields}', $response['data']['editable_body'] );
        $this->assertStringContainsString( '{site_name}', $response['data']['editable_subject'] );
    }

    public function test_custom_preview_resolves_merge_tags_from_posted_fields(): void {
        $this->post_valid_request(
            [
                'template' => 'custom',
                'subject'  => 'Thank you - {site_name}',
                'body'     => "Hello {field:ชื่อ},\n\nYour booking:\n{all_fields}",
            ]
        );

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertTrue( $response['success'], 'Custom preview should succeed: ' . $this->_last_response );

        // Subject merge tags resolved.
        $this->assertSame( 'Thank you - ' . get_bloginfo( 'name' ), $response['data']['subject'] );

        $html = $response['data']['html'];
        // {field:label} resolved to that field's sample value; form title used
        // by the custom layout wrapper.
        $this->assertStringContainsString( 'Hello Somchai Jaidee', $html );
        $this->assertStringContainsString( 'Email Tools Test Form', $html );
        $this->assertStringContainsString( 'somchai@example.com', $html, '{all_fields} renders every sample row' );
        $this->assertStringNotContainsString( '{field:', $html, 'No raw merge tags should remain' );
        $this->assertStringNotContainsString( '{all_fields}', $html );
    }

    public function test_preview_rejects_unknown_template_type_to_booking(): void {
        $this->post_valid_request( [ 'template' => '../../etc/passwd' ] );

        $response = $this->handle_ajax( 'isxf_preview_email' );

        $this->assertTrue( $response['success'] );
        $this->assertStringContainsString( 'Room Booking Confirmed', $response['data']['html'], 'Unknown template types fall back to booking' );
    }

    public function test_send_template_test_calls_wp_mail_with_smtp_hooks(): void {
        $this->post_valid_request();

        $captured = null;
        $hooks    = [];
        add_filter(
            'pre_wp_mail',
            function ( $return, $atts ) use ( &$captured, &$hooks ) {
                $captured = $atts;
                $hooks    = [
                    'phpmailer_init' => has_action( 'phpmailer_init' ),
                    'wp_mail_failed' => has_action( 'wp_mail_failed' ),
                ];
                return true; // short-circuit the actual send
            },
            10,
            2
        );

        $response = $this->handle_ajax( 'isxf_send_template_test' );

        $this->assertTrue( $response['success'], 'Test send should succeed: ' . $this->_last_response );
        $this->assertNotNull( $captured, 'wp_mail must be called' );
        $this->assertSame( wp_get_current_user()->user_email, $captured['to'], 'Test email goes to the current admin user' );
        $this->assertStringStartsWith( '🧪 ', $captured['subject'], 'Subject is prefixed with 🧪' );
        $this->assertStringContainsString( 'somchai@example.com', $captured['message'], 'Body carries the sample-data rendering' );
        $this->assertNotFalse( $hooks['phpmailer_init'], 'configure_smtp must be attached during the send' );
        $this->assertNotFalse( $hooks['wp_mail_failed'], 'capture_mail_error must be attached during the send' );
        // Hooks are cleaned up again after the send.
        $this->assertFalse( has_action( 'phpmailer_init' ), 'SMTP hook must be removed after the send' );
    }

    public function test_send_template_test_reports_failure(): void {
        $this->post_valid_request();

        add_filter( 'pre_wp_mail', '__return_false' );

        $response = $this->handle_ajax( 'isxf_send_template_test' );

        $this->assertFalse( $response['success'], 'A failed wp_mail must surface as a JSON error' );
        $this->assertStringContainsString( 'Sending failed', $response['data']['message'] );
    }
}
