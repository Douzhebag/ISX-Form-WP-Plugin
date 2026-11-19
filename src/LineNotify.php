<?php

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LINE OA admin notification via the LINE Messaging API push endpoint
 * (LINE Notify was shut down on 2025-03-31).
 *
 * Runs on isxf_form_after_submission, independent of the email channel:
 * the admin picks Email, LINE, or both with the two toggles on the settings
 * page. Failures are logged only — they never block the submission.
 */
class LineNotify {

    const OPT_ENABLE  = 'isxf_line_enable';
    const OPT_TOKEN   = 'isxf_line_token';
    const OPT_TARGETS = 'isxf_line_targets';

    const PUSH_URL = 'https://api.line.me/v2/bot/message/push';
    const MAX_TEXT = 5000; // LINE text message limit.

    public function __construct() {
        add_action( 'isxf_form_after_submission', [ $this, 'notify' ], 20, 3 );
    }

    /**
     * Push the submission to every configured LINE target.
     *
     * @param array  $entry_data label => sanitized value.
     * @param int    $form_id    Form post ID.
     * @param string $user_ip    Submitter IP.
     */
    public function notify( $entry_data, $form_id, $user_ip ) {
        if ( get_option( self::OPT_ENABLE ) !== 'yes' ) return;

        $token   = Crypto::decrypt( get_option( self::OPT_TOKEN, '' ) );
        $targets = self::parse_targets( (string) get_option( self::OPT_TARGETS, '' ) );
        if ( ! is_string( $token ) || $token === '' || ! $targets ) return;

        $text = self::build_message( (array) $entry_data, get_the_title( $form_id ), (string) $user_ip );

        // ponytail: one push per target; switch to /multicast if lists grow past a handful.
        foreach ( $targets as $to ) {
            $response = wp_remote_post( self::PUSH_URL, [
                'timeout' => 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'to'       => $to,
                    'messages' => [ [ 'type' => 'text', 'text' => $text ] ],
                ] ),
            ] );

            if ( is_wp_error( $response ) ) {
                isxf_log_error( 'LINE push failed for form #' . $form_id . ': ' . $response->get_error_message() );
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                isxf_log_error( sprintf( 'LINE push failed for form #%d: HTTP %d %s', $form_id, $code, wp_remote_retrieve_body( $response ) ) );
            }
        }
    }

    /**
     * Comma/whitespace separated userId (U...) / groupId (C...) / roomId (R...).
     *
     * @return string[]
     */
    public static function parse_targets( $raw ) {
        $ids = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        return array_values( array_unique( array_filter( $ids, static function ( $id ) {
            return (bool) preg_match( '/^[UCR][0-9a-f]{32}$/', $id );
        } ) ) );
    }

    public static function build_message( array $entry_data, $form_title, $user_ip ) {
        /* translators: %s: form title. */
        $lines = [ sprintf( __( '📩 New submission: %s', 'insightx-form' ), $form_title ) ];
        foreach ( $entry_data as $label => $value ) {
            $lines[] = $label . ': ' . $value;
        }
        $lines[] = 'IP: ' . $user_ip;
        $text = implode( "\n", $lines );
        return mb_strlen( $text ) > self::MAX_TEXT ? mb_substr( $text, 0, self::MAX_TEXT - 1 ) . '…' : $text;
    }
}
