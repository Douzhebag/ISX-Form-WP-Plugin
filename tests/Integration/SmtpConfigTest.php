<?php
/**
 * Integration tests for AbstractAjaxController::configure_smtp() — what the
 * phpmailer_init hook does to PHPMailer for each provider card on the
 * settings page (Resend / Cloudflare / Custom).
 *
 * Covers: SMTP left alone when disabled, host/port/username/password,
 * port 465 → SSL (Cloudflare only accepts implicit TLS), 587 → TLS,
 * the dev-only "disable SSL verification" switch, and that the From
 * address is always a valid mailbox — even when the SMTP username is a
 * literal like "resend" or "api_token".
 *
 * No network: PHPMailer is configured, never asked to send.
 *
 * @package InsightX_Form\Tests\Integration
 */

use ISXF\Ajax\SettingsController;
use ISXF\Crypto;
use PHPMailer\PHPMailer\PHPMailer;

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group smtp
 */
class SmtpConfigTest extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_auth_method', 'password' );
        delete_option( 'isxf_smtp_from_email' );
        delete_option( 'isxf_smtp_disable_ssl_verify' );
    }

    private function preset( string $host, int $port, string $user, string $pass ): void {
        update_option( 'isxf_smtp_host', $host );
        update_option( 'isxf_smtp_port', (string) $port );
        update_option( 'isxf_smtp_user', $user );
        update_option( 'isxf_smtp_pass', Crypto::encrypt( $pass ) );
    }

    private function configure(): PHPMailer {
        $mailer = new PHPMailer( true );
        // What wp_mail() has already set before phpmailer_init fires.
        $mailer->setFrom( 'wordpress@example.org', 'WordPress' );
        ( new SettingsController() )->configure_smtp( $mailer );
        return $mailer;
    }

    public function test_disabled_smtp_leaves_phpmailer_untouched(): void {
        update_option( 'isxf_smtp_enable', '' );
        $this->preset( 'smtp.example.com', 587, 'me@example.com', 'secret' );

        $mailer = $this->configure();

        $this->assertSame( 'mail', $mailer->Mailer );
        $this->assertSame( 'wordpress@example.org', $mailer->From );
    }

    public function test_custom_provider_settings_are_applied(): void {
        $this->preset( 'smtp.example.com', 587, 'me@example.com', 'p@ss w0rd' );

        $mailer = $this->configure();

        $this->assertSame( 'smtp', $mailer->Mailer );
        $this->assertSame( 'smtp.example.com', $mailer->Host );
        $this->assertSame( 587, $mailer->Port );
        $this->assertSame( 'tls', $mailer->SMTPSecure );
        $this->assertTrue( $mailer->SMTPAuth );
        $this->assertSame( 'me@example.com', $mailer->Username );
        $this->assertSame( 'p@ss w0rd', $mailer->Password, 'The stored password is decrypted for PHPMailer' );
        $this->assertSame( 'me@example.com', $mailer->From, 'An email-shaped username doubles as the From address' );
    }

    public function test_cloudflare_preset_uses_implicit_tls_on_465(): void {
        $this->preset( 'smtp.mx.cloudflare.net', 465, 'api_token', 'cf-token' );

        $mailer = $this->configure();

        $this->assertSame( 'smtp.mx.cloudflare.net', $mailer->Host );
        $this->assertSame( 465, $mailer->Port );
        $this->assertSame( 'ssl', $mailer->SMTPSecure, 'Cloudflare only accepts implicit TLS on 465' );
        $this->assertSame( 'api_token', $mailer->Username );
        $this->assertSame( 'cf-token', $mailer->Password );
    }

    public function test_resend_preset_uses_tls_on_587(): void {
        $this->preset( 'smtp.resend.com', 587, 'resend', 're_key' );

        $mailer = $this->configure();

        $this->assertSame( 'tls', $mailer->SMTPSecure );
        $this->assertSame( 'resend', $mailer->Username );
    }

    /**
     * Resend's username is the literal "resend" and Cloudflare's is
     * "api_token" — neither is a mailbox, so neither may become the From.
     *
     * @dataProvider non_email_usernames
     */
    public function test_from_is_always_a_valid_mailbox( string $host, int $port, string $user ): void {
        $this->preset( $host, $port, $user, 'secret' );

        $mailer = $this->configure();

        $this->assertNotFalse( is_email( $mailer->From ), sprintf( 'From must be a valid email, got "%s"', $mailer->From ) );
    }

    public function non_email_usernames(): array {
        return [
            'resend'     => [ 'smtp.resend.com', 587, 'resend' ],
            'cloudflare' => [ 'smtp.mx.cloudflare.net', 465, 'api_token' ],
        ];
    }

    public function test_non_email_username_keeps_wp_mail_default_from(): void {
        $this->preset( 'smtp.resend.com', 587, 'resend', 'secret' );

        $this->assertSame( 'wordpress@example.org', $this->configure()->From );
    }

    public function test_configured_from_email_wins(): void {
        $this->preset( 'smtp.resend.com', 587, 'resend', 'secret' );
        update_option( 'isxf_smtp_from_email', 'hello@example.com' );

        $mailer = $this->configure();

        $this->assertSame( 'hello@example.com', $mailer->From );
    }

    public function test_ssl_verification_stays_on_by_default(): void {
        $this->preset( 'smtp.example.com', 587, 'me@example.com', 'secret' );

        $mailer = $this->configure();

        $this->assertEmpty( $mailer->SMTPOptions );
    }

    public function test_disable_ssl_verification_switch(): void {
        $this->preset( 'smtp.example.com', 587, 'me@example.com', 'secret' );
        update_option( 'isxf_smtp_disable_ssl_verify', 'yes' );

        $mailer = $this->configure();

        $this->assertFalse( $mailer->SMTPOptions['ssl']['verify_peer'] );
    }
}
