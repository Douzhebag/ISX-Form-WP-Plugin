<?php

namespace ISXF\Ajax;

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
        add_action( 'wp_ajax_isxf_send_test_email', [ $this, 'handle_test_email' ] );
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
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

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

        remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
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
