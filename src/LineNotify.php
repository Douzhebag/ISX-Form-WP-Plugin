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
    const MAX_TEXT     = 5000; // LINE text message limit.
    const MAX_ALT_TEXT = 400;  // Flex altText limit (shown in the push notification / chat list).
    const MAX_VALUE    = 500;  // Per field in the card; keeps the bubble under LINE's 10 KB cap.
    const HEADER_COLOR = '#1e3a8a'; // Card header when the form has no button colour.

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

        $message = self::build_flex( (array) $entry_data, get_the_title( $form_id ), (int) $form_id );

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
                    'messages' => [ $message ],
                ], JSON_UNESCAPED_UNICODE ), // Thai as UTF-8 (3 bytes), not \uXXXX (6), for the size cap.
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

    /**
     * Flex card: coloured header (form button colour), one label/value block per
     * field ("-" when empty), submit time and a button to the Entries screen.
     * The plain-text version becomes altText, which is what the phone notification shows.
     */
    public static function build_flex( array $entry_data, $form_title, $form_id ) {
        $color = sanitize_hex_color( (string) get_post_meta( $form_id, '_isxf_form_button_color', true ) ) ?: self::HEADER_COLOR;

        $rows = [];
        foreach ( $entry_data as $label => $value ) {
            $value = trim( (string) $value );
            $empty = $value === '';
            if ( mb_strlen( $value ) > self::MAX_VALUE ) {
                $value = mb_substr( $value, 0, self::MAX_VALUE - 1 ) . '…';
            }
            if ( $rows ) {
                $rows[] = [ 'type' => 'separator', 'margin' => 'lg', 'color' => '#eeeeee' ];
            }
            $rows[] = [
                'type'     => 'box',
                'layout'   => 'vertical',
                'margin'   => 'lg',
                'spacing'  => 'xs',
                'contents' => [
                    [ 'type' => 'text', 'text' => trim( (string) $label ) !== '' ? (string) $label : '-', 'size' => 'xs', 'color' => '#8c8c8c', 'wrap' => true ],
                    [ 'type' => 'text', 'text' => $empty ? '-' : $value, 'size' => 'sm', 'weight' => $empty ? 'regular' : 'bold', 'color' => $empty ? '#aaaaaa' : '#111111', 'wrap' => true ],
                ],
            ];
        }
        if ( ! $rows ) { // Flex boxes must not be empty.
            $rows[] = [ 'type' => 'text', 'text' => '-', 'size' => 'sm', 'color' => '#aaaaaa' ];
        }

        $header = [
            [ 'type' => 'text', 'text' => __( '🔔 New submission', 'insightx-form' ), 'weight' => 'bold', 'size' => 'md', 'color' => '#ffffff', 'wrap' => true ],
        ];
        if ( trim( (string) $form_title ) !== '' ) {
            $header[] = [ 'type' => 'text', 'text' => (string) $form_title, 'size' => 'xs', 'color' => '#ffffffcc', 'margin' => 'sm', 'wrap' => true ];
        }

        $alt = self::build_message( $entry_data, $form_title );

        return [
            'type'     => 'flex',
            'altText'  => mb_strlen( $alt ) > self::MAX_ALT_TEXT ? mb_substr( $alt, 0, self::MAX_ALT_TEXT - 1 ) . '…' : $alt,
            'contents' => [
                'type'   => 'bubble',
                'header' => [ 'type' => 'box', 'layout' => 'vertical', 'backgroundColor' => $color, 'paddingAll' => 'lg', 'contents' => $header ],
                'body'   => [ 'type' => 'box', 'layout' => 'vertical', 'paddingTop' => 'sm', 'contents' => $rows ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'md',
                    'contents' => [
                        [ 'type' => 'text', 'text' => wp_date( 'j M Y · H:i' ), 'size' => 'xxs', 'color' => '#aaaaaa', 'align' => 'center' ],
                        [
                            'type'   => 'button',
                            'style'  => 'primary',
                            'height' => 'sm',
                            'color'  => $color,
                            'action' => [ 'type' => 'uri', 'label' => __( 'View entries', 'insightx-form' ), 'uri' => admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' ) ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** "label : value" per field, "-" for fields left empty. */
    public static function build_message( array $entry_data, $form_title ) {
        /* translators: %s: form title. */
        $lines = [ sprintf( __( '📩 New submission: %s', 'insightx-form' ), $form_title ), '' ];
        foreach ( $entry_data as $label => $value ) {
            $value   = trim( (string) $value );
            $lines[] = $label . ' : ' . ( $value === '' ? '-' : $value );
        }
        $text = implode( "\n", $lines );
        return mb_strlen( $text ) > self::MAX_TEXT ? mb_substr( $text, 0, self::MAX_TEXT - 1 ) . '…' : $text;
    }
}
