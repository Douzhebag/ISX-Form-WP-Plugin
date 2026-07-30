<?php
/**
 * Integration tests for the form submission AJAX flow (isxf_submit_form).
 *
 * Covers: nonce, honeypot, CAPTCHA fail-close, rate limit, required-field
 * validation, and the happy path (entry inserted with sanitized data).
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group submission
 */
class SubmissionTest extends WP_Ajax_UnitTestCase {

    /** @var int */
    private $form_id;

    public function set_up() {
        parent::set_up();

        // Phase 2.5: user-facing strings are English msgids + a bundled th
        // translation. Load the shipped languages/insightx-form-th.mo so the
        // Thai message assertions below verify exactly what Thai-locale sites
        // render. (switch_to_locale() cannot be used here: it refuses th
        // because the test WP install ships no th core language pack.)
        load_textdomain( 'insightx-form', ISXF_PLUGIN_ROOT . '/languages/insightx-form-th.mo' );

        isxf_create_db_table();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        delete_transient( 'isxf_limit_' . md5( '127.0.0.1' ) );

        // Default: CAPTCHA not enforced, no secrets configured.
        update_option( 'isxf_captcha_service', 'google' );
        update_option( 'isxf_captcha_required', 'no' );
        delete_option( 'isxf_recaptcha_secret_key' );
        delete_option( 'isxf_turnstile_secret_key' );
        delete_option( 'isxf_trusted_proxies' );

        $this->form_id = self::factory()->post->create(
            [
                'post_type'   => 'isxf_form',
                'post_title'  => 'Test Contact Form',
                'post_status' => 'publish',
            ]
        );
        update_post_meta(
            $this->form_id,
            '_isxf_form_fields',
            [
                [ 'type' => 'heading', 'name' => 'sec_1', 'label' => 'ส่วนที่ 1', 'required' => 'no' ],
                [ 'type' => 'text', 'name' => 'full_name', 'label' => 'ชื่อ', 'required' => 'yes' ],
                [ 'type' => 'email', 'name' => 'email', 'label' => 'อีเมล', 'required' => 'yes' ],
                [ 'type' => 'textarea', 'name' => 'message', 'label' => 'ข้อความ', 'required' => 'no' ],
            ]
        );
    }

    public function tear_down() {
        $_POST    = [];
        $_GET     = [];
        $_REQUEST = [];
        unset( $_SERVER['REMOTE_ADDR'] );
        delete_transient( 'isxf_limit_' . md5( '127.0.0.1' ) );
        unload_textdomain( 'insightx-form' );
        parent::tear_down();
    }

    /**
     * Populate $_POST with a valid submission (nonce included).
     *
     * @param array $overrides Values merged over the valid defaults.
     */
    private function post_valid_submission( array $overrides = [] ): void {
        $_POST = array_merge(
            [
                'isxf_nonce'   => wp_create_nonce( 'isxf_secure_nonce' ),
                'isxf_form_id' => $this->form_id,
                'full_name'    => 'สมชาย ใจดี',
                'email'        => 'customer@example.com',
                'message'      => 'สนใจสินค้า',
            ],
            $overrides
        );
    }

    /**
     * Run the AJAX action and return the decoded JSON response.
     *
     * @return array{success: bool, data: array}
     */
    private function handle_ajax(): array {
        try {
            $this->_handleAjax( 'isxf_submit_form' );
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
        $this->post_valid_submission();
        unset( $_POST['isxf_nonce'] );

        $caught = null;
        try {
            $this->_handleAjax( 'isxf_submit_form' );
        } catch ( WPAjaxDieStopException $e ) {
            $caught = $e;
        }

        $this->assertNotNull( $caught, 'check_ajax_referer must die on a missing nonce' );
        $this->assertStringContainsString( '-1', $caught->getMessage() );
    }

    public function test_filled_honeypot_gets_fake_success(): void {
        $this->post_valid_submission( [ 'isxf_website_url_trap' => 'http://spam.example' ] );

        $response = $this->handle_ajax();

        $this->assertTrue( $response['success'] );
        $this->assertSame( 'Success', $response['data']['message'] );

        // ...but nothing may be stored.
        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}isxf_form_entries" );
        $this->assertSame( 0, $count, 'Honeypot submissions must not create entries' );
    }

    public function test_rate_limit_blocks_second_submission(): void {
        set_transient( 'isxf_limit_' . md5( '127.0.0.1' ), true, 30 );
        $this->post_valid_submission();

        $response = $this->handle_ajax();

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'คุณส่งข้อมูลเร็วเกินไป กรุณารอสักครู่', $response['data']['message'] );
    }

    public function test_captcha_required_without_secret_fails_closed(): void {
        update_option( 'isxf_captcha_required', 'yes' );
        delete_option( 'isxf_recaptcha_secret_key' ); // not configured
        $this->post_valid_submission();

        $response = $this->handle_ajax();

        $this->assertFalse( $response['success'], 'Fail-close option must block submissions when no CAPTCHA secret is set' );
        $this->assertSame( 'ระบบยืนยันตัวตน (CAPTCHA) ยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลเว็บไซต์', $response['data']['message'] );
    }

    public function test_missing_required_field_is_rejected(): void {
        $this->post_valid_submission();
        unset( $_POST['full_name'] );

        $response = $this->handle_ajax();

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'กรุณากรอก: ชื่อ', $response['data']['message'] );
    }

    public function test_missing_required_email_is_rejected(): void {
        $this->post_valid_submission( [ 'email' => '' ] );

        $response = $this->handle_ajax();

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'กรุณากรอก: อีเมล', $response['data']['message'] );
    }

    public function test_happy_path_inserts_sanitized_entry(): void {
        $this->post_valid_submission(
            [
                'full_name' => 'สมชาย <script>alert(1)</script>',
                'message'   => "สนใจสินค้า\nโทรกลับด้วย",
            ]
        );

        $response = $this->handle_ajax();

        $this->assertTrue( $response['success'], 'Happy path should succeed: ' . $this->_last_response );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}isxf_form_entries ORDER BY id DESC LIMIT 1", ARRAY_A );
        $this->assertNotNull( $row, 'An entry row must be inserted' );
        $this->assertSame( (string) $this->form_id, (string) $row['form_id'] );
        $this->assertSame( 'Test Contact Form', $row['form_title'] );
        $this->assertSame( '127.0.0.1', $row['user_ip'] );
        $this->assertSame( 'new', $row['entry_status'] );

        $data = json_decode( $row['entry_data'], true );
        $this->assertSame( 'customer@example.com', $data['อีเมล'] );
        $this->assertStringContainsString( 'สมชาย', $data['ชื่อ'] );
        $this->assertStringNotContainsString( '<script>', $data['ชื่อ'], 'HTML must be stripped by sanitize_text_field' );
        $this->assertArrayNotHasKey( 'ส่วนที่ 1', $data, 'Heading fields must be skipped' );

        // Rate-limit transient must be armed after a successful submission.
        $this->assertNotFalse( get_transient( 'isxf_limit_' . md5( '127.0.0.1' ) ) );
    }
}
