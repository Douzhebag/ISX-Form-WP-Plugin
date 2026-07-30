<?php

namespace ISXF\Ajax;

use ISXF\Template;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email-template tools for the form builder (v0.8.0).
 *
 * Admin-only endpoints behind the builder meta box buttons:
 *
 * - isxf_preview_email       — renders the selected customer-reply template
 *   (booking / inquiry / custom) through the real server-side pipeline with
 *   type-aware sample data generated from the form's CURRENT builder fields
 *   (posted by the JS, so unsaved edits are reflected). The custom branch
 *   uses the subject/body posted from the editor, again without saving.
 *   The response also carries `editable_subject` / `editable_body` for
 *   booking/inquiry: the inner body markup with merge tags intact
 *   (templates render with $body_only = true), which the "edit from this
 *   template" button copies into the custom editor.
 * - isxf_send_template_test  — sends the same sample-data rendering to the
 *   current admin user's email through the plugin's SMTP configuration,
 *   with the subject prefixed by 🧪.
 */
class EmailToolsController extends AbstractAjaxController {

    public function __construct() {
        add_action( 'wp_ajax_isxf_preview_email', [ $this, 'handle_preview_email' ] );
        add_action( 'wp_ajax_isxf_send_template_test', [ $this, 'handle_send_template_test' ] );
    }

    public function handle_preview_email() {
        $this->verify_request();

        $request  = $this->parse_request();
        $rendered = $this->render_template( $request );

        wp_send_json_success( $rendered );
    }

    public function handle_send_template_test() {
        $this->verify_request();

        $to = wp_get_current_user()->user_email;
        if ( empty( $to ) || ! is_email( $to ) ) {
            wp_send_json_error( [ 'message' => __( 'Your user account has no valid email address to send the test to.', 'insightx-form' ) ] );
        }

        $request  = $this->parse_request();
        $rendered = $this->render_template( $request );

        $this->mail_errors = [];
        add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

        /* translators: %s: original email subject. */
        $subject = sprintf( __( '🧪 %s', 'insightx-form' ), $rendered['subject'] );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $to, $subject, $rendered['html'], $headers );

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

    /**
     * Shared nonce + capability gate for both endpoints.
     */
    private function verify_request() {
        check_ajax_referer( 'isxf_email_tools_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action', 'insightx-form' ) ] );
        }
    }

    /**
     * Read and sanitize the shared request payload.
     *
     * @return array{template_type:string, form_id:int, entry_data:array, subject:string, body:string}
     */
    private function parse_request() {
        $template_type = isset( $_POST['template'] ) ? sanitize_key( $_POST['template'] ) : 'booking';
        if ( ! in_array( $template_type, [ 'booking', 'inquiry', 'custom' ], true ) ) {
            $template_type = 'booking';
        }

        $form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
        // Same sanitizer the meta box save handler uses for the custom body.
        $body    = isset( $_POST['body'] ) ? wp_kses_post( $_POST['body'] ) : '';

        return [
            'template_type' => $template_type,
            'form_id'       => $form_id,
            'entry_data'    => $this->build_sample_entry_data( $this->get_posted_fields() ),
            'subject'       => $subject,
            'body'          => $body,
        ];
    }

    /**
     * Sanitize the field definitions posted from the builder DOM
     * (mirrors Admin::save_form_fields() sanitization).
     *
     * @return array<int, array{label:string, name:string, type:string, options:string}>
     */
    private function get_posted_fields() {
        $raw = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : [];

        $fields = [];
        foreach ( $raw as $field ) {
            if ( ! is_array( $field ) ) continue;
            $label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '';
            if ( $label === '' ) continue;
            $fields[] = [
                'label'   => $label,
                'name'    => isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '',
                'type'    => isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text',
                'options' => isset( $field['options'] ) ? sanitize_text_field( $field['options'] ) : '',
            ];
        }
        return $fields;
    }

    /**
     * Build entry_data (label => sample value) for a preview/test send.
     * Heading fields are skipped, exactly like real submissions.
     *
     * @param array $fields Sanitized field definitions.
     * @return array
     */
    private function build_sample_entry_data( array $fields ) {
        $entry_data = [];
        foreach ( $fields as $field ) {
            if ( $field['type'] === 'heading' ) continue;
            $entry_data[ $field['label'] ] = $this->sample_value_for( $field );
        }
        return $entry_data;
    }

    /**
     * Type-aware sample value, run through the same sanitizer a real
     * submission of that field type would get.
     *
     * @param array $field Sanitized field definition.
     * @return string
     */
    private function sample_value_for( array $field ) {
        $now = current_datetime();

        switch ( $field['type'] ) {
            case 'email':
                return sanitize_email( 'somchai@example.com' );
            case 'tel':
                return sanitize_text_field( '081-234-5678' );
            case 'number':
                return sanitize_text_field( '2' );
            case 'date':
                return sanitize_text_field( $now->format( 'd/m/Y' ) );
            case 'check_in':
                return sanitize_text_field( $now->modify( '+7 days' )->format( 'd/m/Y' ) );
            case 'check_out':
                return sanitize_text_field( $now->modify( '+10 days' )->format( 'd/m/Y' ) );
            case 'select':
            case 'radio':
            case 'checkbox':
                return sanitize_text_field( $this->first_option( $field['options'] ) );
            case 'textarea':
                return sanitize_textarea_field( __( 'This is a sample message so you can preview the email content.', 'insightx-form' ) );
            default:
                return sanitize_text_field( __( 'Somchai Jaidee', 'insightx-form' ) );
        }
    }

    /**
     * First option of a comma-separated options string (builder format).
     *
     * @param string $options Raw options string.
     * @return string
     */
    private function first_option( $options ) {
        $options = array_filter( array_map( 'trim', explode( ',', (string) $options ) ) );
        return ! empty( $options ) ? reset( $options ) : __( 'Option 1', 'insightx-form' );
    }

    /**
     * Render the selected template with the sample entry_data.
     *
     * @param array $request Parsed request (see parse_request()).
     * @return array{subject:string, html:string, editable_subject:string, editable_body:string}
     */
    private function render_template( array $request ) {
        $site_name    = get_bloginfo( 'name' );
        $template_type = $request['template_type'];
        $form_id      = $request['form_id'];
        $entry_data   = $request['entry_data'];

        $result = [
            'subject'          => '',
            'html'             => '',
            'editable_subject' => '',
            'editable_body'    => '',
        ];

        if ( $template_type === 'custom' ) {
            $raw_subject = $request['subject'] !== '' ? $request['subject'] : __( 'Thank you for contacting us - {site_name}', 'insightx-form' );
            $raw_body    = $request['body'] !== '' ? $request['body'] : '{all_fields}';
            $result['subject'] = $this->process_merge_tags( $raw_subject, $entry_data, $form_id );
            $result['html']    = $this->wrap_in_email_layout( $this->process_merge_tags( $raw_body, $entry_data, $form_id ), $form_id );
        } elseif ( $template_type === 'inquiry' ) {
            /* translators: %s: site name. */
            $result['subject'] = sprintf( __( 'We have received your message - %s', 'insightx-form' ), $site_name );
            $result['html']    = $this->get_general_inquiry_email_template( $entry_data );
            // Merge-tag source for the "edit from this template" copy action.
            $result['editable_subject'] = __( 'We have received your message - {site_name}', 'insightx-form' );
            $result['editable_body']    = Template::get( 'emails/inquiry', [ 'body_only' => true ] );
        } else {
            /* translators: %s: site name. */
            $result['subject'] = sprintf( __( 'Room booking confirmation - %s', 'insightx-form' ), $site_name );
            $result['html']    = $this->get_hotel_booking_email_template( $entry_data );
            $result['editable_subject'] = __( 'Room booking confirmation - {site_name}', 'insightx-form' );
            $result['editable_body']    = Template::get( 'emails/booking', [ 'body_only' => true ] );
        }

        return $result;
    }
}
