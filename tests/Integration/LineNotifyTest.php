<?php
/**
 * Integration tests for the LINE OA admin notification (ISXF\LineNotify).
 *
 * The LINE push endpoint is mocked with pre_http_request — no network.
 *
 * @package InsightX_Form\Tests\Integration
 */

use ISXF\Crypto;
use ISXF\LineNotify;

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group line
 */
class LineNotifyTest extends WP_UnitTestCase {

    const USER_ID  = 'U0123456789abcdef0123456789abcdef';
    const GROUP_ID = 'C0123456789abcdef0123456789abcdef';

    /** @var array[] Captured requests: [ url, args ]. */
    private $requests = [];

    /** @var int HTTP status the fake endpoint returns. */
    private $status = 200;

    /** @var int */
    private $form_id;

    public function set_up() {
        parent::set_up();
        $this->requests = [];
        $this->status   = 200;
        $this->form_id  = self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_title' => 'Contact' ] );

        update_option( LineNotify::OPT_ENABLE, 'yes' );
        update_option( LineNotify::OPT_TOKEN, Crypto::encrypt( 'secret-token' ) );
        update_option( LineNotify::OPT_TARGETS, self::USER_ID . ', ' . self::GROUP_ID . ', not-an-id' );

        add_filter( 'pre_http_request', [ $this, 'fake_line' ], 10, 3 );
    }

    public function tear_down() {
        remove_filter( 'pre_http_request', [ $this, 'fake_line' ], 10 );
        delete_option( LineNotify::OPT_ENABLE );
        delete_option( LineNotify::OPT_TOKEN );
        delete_option( LineNotify::OPT_TARGETS );
        parent::tear_down();
    }

    public function fake_line( $pre, $args, $url ) {
        $this->requests[] = [ $url, $args ];
        return [
            'headers'  => [],
            'body'     => $this->status === 200 ? '{}' : '{"message":"Invalid reply token"}',
            'response' => [ 'code' => $this->status, 'message' => '' ],
            'cookies'  => [],
        ];
    }

    private function submit() {
        do_action( 'isxf_form_after_submission', [ 'ชื่อ' => 'สมชาย', 'อีเมล' => 'a@b.co' ], $this->form_id, '127.0.0.1' );
    }

    public function test_pushes_entry_to_each_valid_target() {
        $this->submit();

        $this->assertCount( 2, $this->requests, 'invalid ID must be skipped' );
        [ $url, $args ] = $this->requests[0];
        $this->assertSame( LineNotify::PUSH_URL, $url );
        $this->assertSame( 'Bearer secret-token', $args['headers']['Authorization'] );

        $body = json_decode( $args['body'], true );
        $this->assertSame( self::USER_ID, $body['to'] );
        $this->assertStringContainsString( 'Contact', $body['messages'][0]['text'] );
        $this->assertStringContainsString( 'ชื่อ : สมชาย', $body['messages'][0]['text'] );
        $this->assertSame( self::GROUP_ID, json_decode( $this->requests[1][1]['body'], true )['to'] );
    }

    public function test_disabled_toggle_sends_nothing() {
        update_option( LineNotify::OPT_ENABLE, '' );
        $this->submit();
        $this->assertSame( [], $this->requests );
    }

    public function test_missing_token_sends_nothing() {
        delete_option( LineNotify::OPT_TOKEN );
        $this->submit();
        $this->assertSame( [], $this->requests );
    }

    public function test_api_error_does_not_throw() {
        $this->status = 400;
        $this->submit(); // must not throw; failure is only logged.
        $this->assertCount( 2, $this->requests );
    }

    public function test_message_formats_fields_and_dashes_empty_values() {
        $text = LineNotify::build_message( [ 'ชื่อ' => ' สมชาย ', 'อีเมล' => '', 'รายละเอียด' => "  \n" ], 'Contact' );
        $lines = explode( "\n", $text );
        $this->assertStringContainsString( 'Contact', $lines[0] );
        $this->assertSame( [ '', 'ชื่อ : สมชาย', 'อีเมล : -', 'รายละเอียด : -' ], array_slice( $lines, -4 ) );
        $this->assertStringNotContainsString( 'IP', $text );
    }

    public function test_message_is_truncated_to_line_limit() {
        $text = LineNotify::build_message( [ 'msg' => str_repeat( 'ก', 6000 ) ], 'F' );
        $this->assertSame( LineNotify::MAX_TEXT, mb_strlen( $text ) );
    }
}
