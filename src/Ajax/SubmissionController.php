<?php

namespace ISXF\Ajax;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Public form-submission endpoint (Phase 2.4 — see docs/ROADMAP.md).
 *
 * Action: isxf_submit_form (wp_ajax + wp_ajax_nopriv).
 * Honeypot, CAPTCHA (google/turnstile) with fail-close, rate limit,
 * sanitization, customer/admin emails and the phpmailer SMTP configuration
 * (Basic + XOAUTH2, inherited from AbstractAjaxController).
 * Code moved verbatim out of the former ISXF\AjaxHandler — no behavior change.
 */
class SubmissionController extends AbstractAjaxController {

    public function __construct() {
        add_action( 'wp_ajax_isxf_submit_form', [ $this, 'handle_secure_submission' ] );
        add_action( 'wp_ajax_nopriv_isxf_submit_form', [ $this, 'handle_secure_submission' ] );
    }

    public function handle_secure_submission() {
        check_ajax_referer( 'isxf_secure_nonce', 'isxf_nonce' );

        if ( ! empty( $_POST['isxf_website_url_trap'] ) ) wp_send_json_success( [ 'message' => 'Success' ] );

        $captcha_service = get_option( 'isxf_captcha_service', 'google' );
        $captcha_required = ( get_option( 'isxf_captcha_required', 'yes' ) === 'yes' );

        if ( $captcha_service === 'google' ) {
            $secret_key = get_option( 'isxf_recaptcha_secret_key' );
            if ( empty( $secret_key ) && $captcha_required ) {
                // fail-close: บังคับใช้ CAPTCHA แต่ยังไม่ได้ตั้งค่า → บล็อกการส่ง
                isxf_log_error( 'Form submission blocked: CAPTCHA required but reCAPTCHA secret key is not configured' );
                wp_send_json_error( [ 'message' => __( 'The CAPTCHA verification system is not ready. Please contact the site administrator.', 'insightx-form' ) ] );
            }
            if ( ! empty( $secret_key ) ) {
                $token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( $_POST['g-recaptcha-response'] ) : '';
                if ( empty( $token ) ) {
                    wp_send_json_error( [ 'message' => __( 'Please verify your identity with reCAPTCHA', 'insightx-form' ) ] );
                }
                $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
                    'body' => [ 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '' ]
                ]);
                $verify_body = json_decode( wp_remote_retrieve_body( $verify ), true );
                if ( empty( $verify_body['success'] ) || ( isset( $verify_body['score'] ) && $verify_body['score'] < 0.5 ) ) {
                    wp_send_json_error( [ 'message' => __( 'reCAPTCHA verification failed. Please try again.', 'insightx-form' ) ] );
                }
            }
        } elseif ( $captcha_service === 'cloudflare' ) {
            $secret_key = get_option( 'isxf_turnstile_secret_key' );
            if ( empty( $secret_key ) && $captcha_required ) {
                // fail-close: บังคับใช้ CAPTCHA แต่ยังไม่ได้ตั้งค่า → บล็อกการส่ง
                isxf_log_error( 'Form submission blocked: CAPTCHA required but Turnstile secret key is not configured' );
                wp_send_json_error( [ 'message' => __( 'The CAPTCHA verification system is not ready. Please contact the site administrator.', 'insightx-form' ) ] );
            }
            if ( ! empty( $secret_key ) ) {
                $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
                if ( empty( $token ) ) {
                    wp_send_json_error( [ 'message' => __( 'Please verify your identity with Turnstile', 'insightx-form' ) ] );
                }
                $verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'body' => [ 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '' ]
                ]);
                $verify_body = json_decode( wp_remote_retrieve_body( $verify ), true );
                if ( empty( $verify_body['success'] ) ) {
                    wp_send_json_error( [ 'message' => __( 'Turnstile verification failed. Please try again.', 'insightx-form' ) ] );
                }
            }
        }

        $user_ip = $this->get_client_ip();
        $limit_key = 'isxf_limit_' . md5($user_ip);
        if ( get_transient( $limit_key ) ) {
            wp_send_json_error( [ 'message' => __( 'You are submitting too quickly. Please wait a moment.', 'insightx-form' ) ] );
        }

        $form_id = intval( $_POST['isxf_form_id'] ?? 0 );
        $fields = get_post_meta( $form_id, '_isxf_form_fields', true );
        $entry_data = [];
        $customer_email = '';

        if ( empty($fields) || ! is_array($fields) ) {
            wp_send_json_error( [ 'message' => __( 'Form data not found.', 'insightx-form' ) ] );
        }

        foreach ( $fields as $field ) {
            if ( $field['type'] === 'heading' ) continue;
            $val = $_POST[$field['name']] ?? '';

            if ( $field['type'] === 'email' ) {
                $clean_val = sanitize_email($val);
                if ( !empty($clean_val) ) {
                    $customer_email = $clean_val;
                }
            } elseif ( $field['type'] === 'textarea' ) {
                $clean_val = sanitize_textarea_field($val);
            } else {
                $clean_val = is_array($val) ? implode(', ', array_map('sanitize_text_field', $val)) : sanitize_text_field($val);
            }

            if ( $field['required'] === 'yes' && empty($clean_val) ) {
                /* translators: %s: field label. */
                wp_send_json_error( [ 'message' => sprintf( __( 'Please fill in: %s', 'insightx-form' ), $field['label'] ) ] );
            }

            $entry_data[$field['label']] = $clean_val;
        }

        do_action( 'isxf_form_after_submission', $entry_data, $form_id, $user_ip );
        set_transient( $limit_key, true, 30 );

        add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

        $site_name = get_bloginfo( 'name' );
        $customer_sent = false;
        $admin_sent = false;
        $error_message = '';

        if ( ! empty( $customer_email ) && is_email( $customer_email ) ) {
            $email_type = get_post_meta( $form_id, '_isxf_form_email_type', true );

            if ( $email_type === 'inquiry' ) {
                /* translators: %s: site name. */
                $email_subject = sprintf( __( 'We have received your message - %s', 'insightx-form' ), $site_name );
                $email_body = $this->get_general_inquiry_email_template( $entry_data );
            } elseif ( $email_type === 'custom' ) {
                $raw_subject = get_post_meta( $form_id, '_isxf_form_email_subject', true );
                $raw_body    = get_post_meta( $form_id, '_isxf_form_email_body', true );
                $email_subject = $this->process_merge_tags( $raw_subject ?: __( 'Thank you for contacting us - {site_name}', 'insightx-form' ), $entry_data, $form_id );
                $processed_body = $this->process_merge_tags( $raw_body ?: '{all_fields}', $entry_data, $form_id );
                $email_body = $this->wrap_in_email_layout( $processed_body, $form_id );
            } else {
                /* translators: %s: site name. */
                $email_subject = sprintf( __( 'Room booking confirmation - %s', 'insightx-form' ), $site_name );
                $email_body = $this->get_hotel_booking_email_template( $entry_data );
            }

            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $customer_sent = wp_mail( $customer_email, $email_subject, $email_body, $headers );
        } else {
            $error_message = __( 'Customer email not found.', 'insightx-form' );
        }

        if ( get_option( 'isxf_admin_notify_enable' ) === 'yes' ) {
            $admin_email = get_option( 'isxf_admin_notify_email' );
            if ( empty( $admin_email ) ) {
                $admin_email = get_option( 'admin_email' );
            }

            $form_title = get_the_title( $form_id );
            /* translators: 1: form title, 2: site name. */
            $admin_subject = sprintf( __( '[Submission] %1$s - from %2$s', 'insightx-form' ), $form_title, $site_name );
            $admin_body = $this->get_admin_notification_template( $entry_data, $form_title, $user_ip );

            $admin_headers = [ 'Content-Type: text/html; charset=UTF-8' ];

            if ( ! empty( $customer_email ) && is_email( $customer_email ) ) {
                $admin_headers[] = 'Reply-To: ' . $customer_email;
            }

            $admin_sent = wp_mail( $admin_email, $admin_subject, $admin_body, $admin_headers );
        }

        remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
        remove_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );

        if ( $customer_sent || $admin_sent ) {
            $msg = __( 'Submission successful!', 'insightx-form' );
            /* translators: %s: customer email address. */
            if ( $customer_sent ) $msg = sprintf( __( 'Submission successful! A confirmation email has been sent to %s', 'insightx-form' ), $customer_email );
            wp_send_json_success( [ 'message' => $msg ] );
        } else {
            if ( ! empty( $this->mail_errors ) ) {
                $error_message = implode( '; ', $this->mail_errors );
                isxf_log_error( 'Email failed for form #' . $form_id . ': ' . $error_message );
                /* translators: %s: email error details. */
                wp_send_json_error( [ 'message' => sprintf( __( 'Your data has been saved, but the email could not be sent: %s', 'insightx-form' ), esc_html( $error_message ) ) ] );
            } else {
                wp_send_json_success( [ 'message' => __( 'Your data has been saved successfully.', 'insightx-form' ) ] );
            }
        }
    }
}
