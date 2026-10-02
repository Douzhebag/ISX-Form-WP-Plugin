<?php

namespace ISXF\Ajax;

use ISXF\Crypto;
use ISXF\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Settings endpoints (Phase 2.4 — see docs/ROADMAP.md).
 *
 * Action: isxf_send_test_email — sends the SMTP test email from the settings
 * page, through the same phpmailer SMTP configuration submissions use.
 * Code moved verbatim out of the former ISXF\AjaxHandler — no behavior change.
 */
class SettingsController extends AbstractAjaxController {

    public function __construct() {
        add_action( 'wp_ajax_isxf_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'wp_ajax_isxf_send_test_email', [ $this, 'handle_test_email' ] );
        add_action( 'wp_ajax_isxf_test_smtp_connection', [ $this, 'handle_test_smtp_connection' ] );
    }

    /** Save the global settings form through the registered WordPress sanitizers. */
    public function handle_save_settings() {
        check_ajax_referer( 'isxf_save_settings_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'insightx-form' ) ] );
        }

        $allowed = [
            'isxf_admin_notify_enable', 'isxf_admin_notify_email',
            'isxf_smtp_enable', 'isxf_smtp_host', 'isxf_smtp_port', 'isxf_smtp_user', 'isxf_smtp_pass',
            'isxf_smtp_secure', 'isxf_smtp_from_email', 'isxf_smtp_from_name', 'isxf_smtp_disable_ssl_verify',
            'isxf_smtp_auth_method', 'isxf_smtp_oauth_client_id', 'isxf_smtp_oauth_client_secret',
            'isxf_captcha_service', 'isxf_recaptcha_site_key', 'isxf_recaptcha_secret_key',
            'isxf_turnstile_site_key', 'isxf_turnstile_secret_key', 'isxf_captcha_required',
            'isxf_trusted_proxies',
        ];
        $checkboxes = [ 'isxf_admin_notify_enable', 'isxf_smtp_enable', 'isxf_smtp_disable_ssl_verify', 'isxf_captcha_required' ];
        $posted = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : [];
        $saved = false;

        foreach ( $allowed as $option ) {
            if ( ! in_array( $option, $checkboxes, true ) && ! array_key_exists( $option, $posted ) ) {
                continue;
            }
            $value = in_array( $option, $checkboxes, true )
                ? ( isset( $posted[ $option ] ) && $posted[ $option ] === 'yes' ? 'yes' : '' )
                : $posted[ $option ];
            // update_option() runs sanitize_option() itself — calling it here as
            // well ran the password/secret callbacks twice (double encryption).
            update_option( $option, $value );
            $saved = true;
        }

        if ( ! $saved ) {
            wp_send_json_error( [ 'message' => __( 'No settings were received.', 'insightx-form' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'บันทึกการตั้งค่าสำเร็จ', 'insightx-form' ) ] );
    }

    /** Check the saved SMTP settings without sending an email. */
    public function handle_test_smtp_connection() {
        check_ajax_referer( 'isxf_smtp_connection_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'insightx-form' ) ] );
        }
        if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) {
            $this->finish_smtp_check( false, __( 'SMTP is disabled. Enable it and save the settings first.', 'insightx-form' ) );
        }
        if ( get_option( 'isxf_smtp_auth_method', 'password' ) === 'oauth_google' && ! OAuth::is_connected() ) {
            $this->finish_smtp_check( false, __( 'Google is not connected. Connect the account and save the settings first.', 'insightx-form' ) );
        }
        if ( get_option( 'isxf_smtp_auth_method', 'password' ) !== 'oauth_google' && empty( Crypto::decrypt( get_option( 'isxf_smtp_pass', '' ) ) ) ) {
            $this->finish_smtp_check( false, __( 'SMTP password or API key is missing. Enter it and save the settings first.', 'insightx-form' ) );
        }

        $mailer = null;
        $result = null;
        try {
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';

            $mailer = new PHPMailer( true );
            $mailer->Timeout = 10;
            $mailer->SMTPDebug = 0;
            $this->configure_smtp( $mailer );

            if ( $mailer->Mailer !== 'smtp' || empty( $mailer->Host ) || empty( $mailer->Port ) ) {
                throw new RuntimeException( __( 'SMTP host or port is missing.', 'insightx-form' ) );
            }
            if ( ! $mailer->smtpConnect() ) {
                throw new RuntimeException( $mailer->ErrorInfo ?: __( 'Could not connect to the SMTP server.', 'insightx-form' ) );
            }
            $result = [ 'success' => true, 'message' => __( 'Connected successfully', 'insightx-form' ) ];
        } catch ( Throwable $error ) {
            $message = $mailer && $mailer->ErrorInfo ? $mailer->ErrorInfo : $error->getMessage();
            $secret = Crypto::decrypt( get_option( 'isxf_smtp_pass', '' ) );
            if ( is_string( $secret ) && $secret !== '' ) {
                $message = str_replace( $secret, '[hidden]', $message );
            }
            /* translators: %s: SMTP connection error details returned by the mail server. */
            $result = [ 'success' => false, 'message' => sprintf( __( 'Connection failed: %s', 'insightx-form' ), self::explain_smtp_error( $message ) ) ];
        } finally {
            if ( $mailer instanceof PHPMailer ) {
                $mailer->smtpClose();
            }
        }
        $this->finish_smtp_check( $result['success'], $result['message'] );
    }

    /**
     * Remember the outcome for the status badge on the settings page (shown
     * again after a reload while the SMTP settings stay the same), then reply.
     *
     * @param bool   $ok      Whether the SMTP server accepted the login.
     * @param string $message Success text or the reason it failed.
     */
    private function finish_smtp_check( $ok, $message ) {
        update_option(
            'isxf_smtp_last_check',
            [
                'ok'      => (bool) $ok,
                'message' => (string) $message,
                'hash'    => self::smtp_config_hash(),
                'time'    => time(),
            ],
            false
        );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => $message ] );
        }
        wp_send_json_error( [ 'message' => $message ] );
    }

    /**
     * Fingerprint of everything the connection depends on — a stored result
     * only counts while this still matches.
     *
     * @return string
     */
    public static function smtp_config_hash() {
        $keys = [ 'isxf_smtp_enable', 'isxf_smtp_auth_method', 'isxf_smtp_host', 'isxf_smtp_port', 'isxf_smtp_user', 'isxf_smtp_pass', 'isxf_smtp_oauth_connected' ];
        return md5( (string) wp_json_encode( array_map( 'get_option', $keys ) ) );
    }

    /**
     * Put the usual cause in front of PHPMailer's raw error.
     *
     * @param string $raw Error text from PHPMailer / the SMTP server.
     * @return string
     */
    public static function explain_smtp_error( $raw ) {
        $checks = [
            'authenticat'        => __( 'The username or password / API key is incorrect.', 'insightx-form' ),
            'certificate'        => __( 'SSL certificate problem — check the port (465 = SSL, 587 = TLS).', 'insightx-form' ),
            'failed to connect'  => __( 'Cannot reach the SMTP server — check the host and port.', 'insightx-form' ),
            'connection refused' => __( 'Cannot reach the SMTP server — check the host and port.', 'insightx-form' ),
            'timed out'          => __( 'Cannot reach the SMTP server — check the host and port.', 'insightx-form' ),
            'getaddrinfo'        => __( 'Cannot reach the SMTP server — check the host and port.', 'insightx-form' ),
        ];
        foreach ( $checks as $needle => $reason ) {
            if ( stripos( $raw, $needle ) !== false ) {
                return $reason . ' (' . $raw . ')';
            }
        }
        return $raw;
    }

    public function handle_test_email() {
        check_ajax_referer( 'isxf_test_email_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action', 'insightx-form' ) ] );
        }

        $to = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';
        if ( empty( $to ) || ! is_email( $to ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a valid destination email address', 'insightx-form' ) ] );
        }

        $this->mail_errors = [];
        add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );
        $this->hook_smtp();

        $site_name = get_bloginfo( 'name' );
        /* translators: %s: site name. */
        $subject = sprintf( __( '🧪 SMTP Test - %s', 'insightx-form' ), $site_name );
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:30px;background:#f0f0f1;font-family:sans-serif;">'
            . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;border:1px solid #ccd0d4;text-align:center;">'
            . '<div style="font-size:48px;margin-bottom:15px;">✅</div>'
            . '<h2 style="color:#1d2327;margin:0 0 10px;">' . __( 'SMTP is working!', 'insightx-form' ) . '</h2>'
            /* translators: %s: site name. */
            . '<p style="color:#50575e;line-height:1.6;">' . sprintf( __( 'This email was sent from <strong>%s</strong><br>to test the SMTP settings', 'insightx-form' ), esc_html($site_name) ) . '</p>'
            . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
            /* translators: %s: date and time the test email was sent. */
            . '<p style="color:#999;font-size:12px;">' . sprintf( __( 'Sent at: %s', 'insightx-form' ), wp_date('d/m/Y H:i:s') ) . '</p>'
            . '</div></body></html>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $to, $subject, $body, $headers );

        $this->unhook_smtp();
        remove_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );

        if ( $sent ) {
            /* translators: %s: destination email address. */
            wp_send_json_success( [ 'message' => sprintf( __( 'Test email sent to %s successfully!', 'insightx-form' ), $to ) ] );
        } else {
            $err = ! empty( $this->mail_errors ) ? implode( '; ', $this->mail_errors ) : __( 'Unknown reason', 'insightx-form' );
            /* translators: %s: error details. */
            wp_send_json_error( [ 'message' => sprintf( __( 'Sending failed: %s', 'insightx-form' ), $err ) ] );
        }
    }
}
