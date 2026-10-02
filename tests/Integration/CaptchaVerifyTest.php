<?php
/**
 * Integration tests for the CAPTCHA verification step of the public form
 * submission (isxf_submit_form) — what happens when the provider says no.
 *
 * Covers: a token Cloudflare rejects (e.g. reused → timeout-or-duplicate)
 * and an unreachable siteverify endpoint both block the submission with the
 * generic message, and the reason is reported via isxf_captcha_failed (and
 * the debug log); a passing check lets the submission through.
 *
 * siteverify is mocked with pre_http_request — no network.
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group ajax
 * @group captcha
 */
class CaptchaVerifyTest extends WP_Ajax_UnitTestCase {

    /** @var int */
    private $form_id;

    /** @var array|WP_Error Canned siteverify reply. */
    private $siteverify;

    /** @var array Reported failures: [ service, codes ]. */
    private $reported = [];

    public function set_up() {
        parent::set_up();
        isxf_create_db_table();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        delete_transient( 'isxf_limit_' . md5( '127.0.0.1' ) );
        delete_option( 'isxf_trusted_proxies' );
        update_option( 'isxf_captcha_service', 'cloudflare' );
        update_option( 'isxf_captcha_required', 'yes' );
        update_option( 'isxf_turnstile_secret_key', '0x4AAAAAAAtestsecret' );

        $this->form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_status' => 'publish' ] );
        update_post_meta(
            $this->form_id,
            '_isxf_form_fields',
            [ [ 'type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => 'yes' ] ]
        );

        add_filter( 'pre_http_request', [ $this, 'fake_siteverify' ], 10, 3 );
        add_action( 'isxf_captcha_failed', [ $this, 'record_failure' ], 10, 2 );
    }

    public function tear_down() {
        remove_filter( 'pre_http_request', [ $this, 'fake_siteverify' ], 10 );
        remove_action( 'isxf_captcha_failed', [ $this, 'record_failure' ], 10 );
        $_POST    = [];
        $_REQUEST = [];
        unset( $_SERVER['REMOTE_ADDR'] );
        delete_transient( 'isxf_limit_' . md5( '127.0.0.1' ) );
        parent::tear_down();
    }

    public function fake_siteverify( $pre, $args, $url ) {
        if ( strpos( $url, 'challenges.cloudflare.com/turnstile/v0/siteverify' ) === false ) {
            return $pre;
        }
        if ( is_wp_error( $this->siteverify ) ) {
            return $this->siteverify;
        }
        return [
            'headers'  => [],
            'body'     => wp_json_encode( $this->siteverify ),
            'response' => [ 'code' => 200, 'message' => 'OK' ],
            'cookies'  => [],
        ];
    }

    public function record_failure( $service, $codes ) {
        $this->reported[] = [ $service, $codes ];
    }

    private function submit(): array {
        $_POST = [
            'isxf_nonce'            => wp_create_nonce( 'isxf_secure_nonce' ),
            'isxf_form_id'          => $this->form_id,
            'full_name'             => 'Tester',
            'cf-turnstile-response' => 'token-from-widget',
        ];
        try {
            $this->_handleAjax( 'isxf_submit_form' );
        } catch ( WPAjaxDieContinueException $e ) {
            // success
        } catch ( WPAjaxDieStopException $e ) {
            // error
        }
        $response = json_decode( $this->_last_response, true );
        $this->assertIsArray( $response, 'Response should be JSON: ' . $this->_last_response );
        return $response;
    }

    public function test_reused_token_is_rejected_and_reason_reported(): void {
        $this->siteverify = [ 'success' => false, 'error-codes' => [ 'timeout-or-duplicate' ] ];

        $response = $this->submit();

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'Turnstile verification failed. Please try again.', $response['data']['message'] );
        $this->assertSame( [ [ 'cloudflare', [ 'timeout-or-duplicate' ] ] ], $this->reported );
    }

    public function test_unreachable_siteverify_is_rejected_and_reported(): void {
        $this->siteverify = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

        $response = $this->submit();

        $this->assertFalse( $response['success'] );
        $this->assertCount( 1, $this->reported );
        $this->assertStringContainsString( 'http-request-failed', $this->reported[0][1][0] );
    }

    public function test_passing_check_lets_the_submission_through(): void {
        $this->siteverify = [ 'success' => true ];

        $response = $this->submit();

        $this->assertTrue( $response['success'], 'Response: ' . wp_json_encode( $response ) );
        $this->assertSame( [], $this->reported );
    }
}
