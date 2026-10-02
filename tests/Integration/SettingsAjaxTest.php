<?php
/**
 * Integration tests for the settings AJAX endpoints
 * (isxf_save_settings / isxf_test_smtp_connection / isxf_send_test_email).
 *
 * Covers: nonce and manage_options gates, the option allow-list (nothing
 * outside it can be written), checkbox handling, that the SMTP password is
 * stored encrypted and decrypts back to exactly what was typed, and the
 * pre-flight errors of the SMTP connection test (no network involved).
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

use ISXF\Crypto;

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group ajax
 * @group settings
 */
class SettingsAjaxTest extends WP_Ajax_UnitTestCase {

    public function set_up() {
        parent::set_up();
        isxf_create_db_table();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    public function tear_down() {
        $_POST    = [];
        $_REQUEST = [];
        parent::tear_down();
    }

    private function handle_ajax( string $action ): array {
        $this->_last_response = ''; // several requests per test: keep only this one's output
        try {
            $this->_handleAjax( $action );
        } catch ( WPAjaxDieContinueException $e ) {
            // wp_send_json_success()
        } catch ( WPAjaxDieStopException $e ) {
            // wp_send_json_error()
        }
        $response = json_decode( $this->_last_response, true );
        $this->assertIsArray( $response, 'Response should be JSON: ' . $this->_last_response );
        return $response;
    }

    private function save( array $settings ): array {
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_save_settings_nonce' ), 'settings' => $settings ];
        return $this->handle_ajax( 'isxf_save_settings' );
    }

    public function test_save_requires_nonce(): void {
        $_POST = [ 'settings' => [ 'isxf_smtp_host' => 'evil.example' ] ];

        $caught = null;
        try {
            $this->_handleAjax( 'isxf_save_settings' );
        } catch ( WPAjaxDieStopException $e ) {
            $caught = $e;
        }

        $this->assertNotNull( $caught );
        $this->assertFalse( get_option( 'isxf_smtp_host' ) );
    }

    public function test_save_rejects_non_admin(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

        $response = $this->save( [ 'isxf_smtp_host' => 'evil.example' ] );

        $this->assertFalse( $response['success'] );
        $this->assertFalse( get_option( 'isxf_smtp_host' ) );
    }

    public function test_only_allow_listed_options_are_written(): void {
        $before = get_option( 'siteurl' );

        $response = $this->save(
            [
                'isxf_smtp_host' => 'smtp.resend.com',
                'siteurl'        => 'https://attacker.example',
                'users_can_register' => '1',
            ]
        );

        $this->assertTrue( $response['success'] );
        $this->assertSame( 'smtp.resend.com', get_option( 'isxf_smtp_host' ) );
        $this->assertSame( $before, get_option( 'siteurl' ), 'Options outside the allow-list must not be touched' );
        $this->assertNotSame( '1', get_option( 'users_can_register' ) );
    }

    public function test_text_values_are_sanitized(): void {
        $this->save( [ 'isxf_smtp_host' => '<b>smtp.example.com</b>' ] );

        $this->assertSame( 'smtp.example.com', get_option( 'isxf_smtp_host' ) );
    }

    public function test_unchecked_checkboxes_are_cleared(): void {
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_captcha_required', 'yes' );

        // A save that does not send the checkbox keys = they are unchecked.
        $this->save( [ 'isxf_smtp_host' => 'smtp.example.com' ] );

        $this->assertSame( '', get_option( 'isxf_smtp_enable' ) );
        $this->assertSame( '', get_option( 'isxf_captcha_required' ) );
    }

    public function test_checked_checkbox_is_stored_as_yes(): void {
        $this->save( [ 'isxf_smtp_enable' => 'yes' ] );

        $this->assertSame( 'yes', get_option( 'isxf_smtp_enable' ) );
    }

    public function test_smtp_password_is_stored_encrypted_and_decrypts_back(): void {
        $this->save( [ 'isxf_smtp_pass' => 're_test_api_key_123' ] );

        $stored = get_option( 'isxf_smtp_pass' );
        $this->assertNotSame( 're_test_api_key_123', $stored, 'The password must never be stored in plain text' );
        $this->assertSame( 're_test_api_key_123', Crypto::decrypt( $stored ), 'Decrypting the stored value must give back exactly what was typed' );
    }

    public function test_empty_password_keeps_the_existing_one(): void {
        $this->save( [ 'isxf_smtp_pass' => 'first-secret' ] );
        $this->save( [ 'isxf_smtp_pass' => '' ] );

        $this->assertSame( 'first-secret', Crypto::decrypt( get_option( 'isxf_smtp_pass' ) ) );
    }

    public function test_smtp_test_reports_disabled_smtp(): void {
        update_option( 'isxf_smtp_enable', '' );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_smtp_connection_nonce' ) ];

        $response = $this->handle_ajax( 'isxf_test_smtp_connection' );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'SMTP is disabled', $response['data']['message'] );
    }

    public function test_smtp_test_reports_missing_password(): void {
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_auth_method', 'password' );
        delete_option( 'isxf_smtp_pass' );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_smtp_connection_nonce' ) ];

        $response = $this->handle_ajax( 'isxf_test_smtp_connection' );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'password or API key is missing', $response['data']['message'] );
    }

    public function test_smtp_test_reports_unconnected_google(): void {
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_auth_method', 'oauth_google' );
        delete_option( 'isxf_smtp_oauth_refresh_token' );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_smtp_connection_nonce' ) ];

        $response = $this->handle_ajax( 'isxf_test_smtp_connection' );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Google is not connected', $response['data']['message'] );
    }

    public function test_test_email_rejects_invalid_address(): void {
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_test_email_nonce' ), 'test_email' => 'not-an-email' ];

        $response = $this->handle_ajax( 'isxf_send_test_email' );

        $this->assertFalse( $response['success'] );
    }

    /* ---------- connection badge: stored result + reason ---------- */

    public function test_failed_check_is_stored_for_the_badge(): void {
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_auth_method', 'password' );
        delete_option( 'isxf_smtp_pass' );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_smtp_connection_nonce' ) ];

        $response = $this->handle_ajax( 'isxf_test_smtp_connection' );

        $last = get_option( 'isxf_smtp_last_check' );
        $this->assertFalse( $last['ok'] );
        $this->assertSame( $response['data']['message'], $last['message'] );
        $this->assertSame( \ISXF\Ajax\SettingsController::smtp_config_hash(), $last['hash'] );
    }

    public function test_unreachable_server_gets_a_readable_reason(): void {
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_auth_method', 'password' );
        update_option( 'isxf_smtp_host', '127.0.0.1' );
        update_option( 'isxf_smtp_port', '1' ); // nothing listens here → refused at once
        update_option( 'isxf_smtp_user', 'me@example.com' );
        update_option( 'isxf_smtp_pass', Crypto::encrypt( 'secret' ) );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_smtp_connection_nonce' ) ];

        $response = $this->handle_ajax( 'isxf_test_smtp_connection' );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'check the host and port', $response['data']['message'] );
    }

    /**
     * @dataProvider smtp_errors
     */
    public function test_explain_smtp_error( string $raw, string $expected ): void {
        $this->assertStringContainsString( $expected, \ISXF\Ajax\SettingsController::explain_smtp_error( $raw ) );
        $this->assertStringContainsString( $raw, \ISXF\Ajax\SettingsController::explain_smtp_error( $raw ), 'The raw server error is kept for details' );
    }

    public function smtp_errors(): array {
        return [
            'bad login'   => [ 'SMTP Error: Could not authenticate.', 'API key is incorrect' ],
            'unreachable' => [ 'SMTP Error: Failed to connect to server', 'check the host and port' ],
            'tls'         => [ 'stream_socket_enable_crypto(): certificate verify failed', '465 = SSL' ],
            'unknown'     => [ '554 something else', '554 something else' ],
        ];
    }
}
