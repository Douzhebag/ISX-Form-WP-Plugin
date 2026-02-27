<?php
/**
 * ISXF_Crypto — Centralized encryption/decryption utility for InsightX Form.
 *
 * Uses AES-256-CBC with random IV (ENC2 format) for new encryptions.
 * Backward-compatible with legacy deterministic IV format (ENC format).
 *
 * @since 0.4.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class ISXF_Crypto {

    private static $cipher = 'AES-256-CBC';

    /**
     * Get the encryption key derived from WordPress auth salt.
     */
    private static function get_key() {
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }

    /**
     * Get the legacy deterministic IV (for backward compatibility only).
     */
    private static function get_legacy_iv() {
        return substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
    }

    /**
     * Encrypt a plain-text value using AES-256-CBC with a random IV.
     *
     * Output format: base64( 'ENC2:' . random_iv_16_bytes . ciphertext )
     *
     * @param string $plain The plain-text value to encrypt.
     * @return string The encrypted value, or empty string on failure.
     */
    public static function encrypt( $plain ) {
        if ( empty( $plain ) ) return '';
        if ( ! function_exists( 'openssl_encrypt' ) ) return $plain;

        $key = self::get_key();
        $iv  = openssl_random_pseudo_bytes( 16 );

        $encrypted = openssl_encrypt( $plain, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );
        if ( $encrypted === false ) return $plain;

        // ENC2: prefix + raw IV (16 bytes) + raw ciphertext
        return base64_encode( 'ENC2:' . $iv . $encrypted );
    }

    /**
     * Decrypt a stored value. Supports both new (ENC2) and legacy (ENC) formats.
     * Falls back to returning the value as-is if it is not encrypted.
     *
     * @param string $stored The stored (possibly encrypted) value.
     * @return string The decrypted plain-text value, or empty string on failure.
     */
    public static function decrypt( $stored ) {
        if ( empty( $stored ) ) return '';

        $decoded = base64_decode( $stored, true );
        if ( $decoded === false ) return $stored;

        // New format: ENC2:<16-byte IV><ciphertext>
        if ( strpos( $decoded, 'ENC2:' ) === 0 ) {
            if ( ! function_exists( 'openssl_decrypt' ) ) return '';
            $payload = substr( $decoded, 5 ); // remove 'ENC2:'
            $iv         = substr( $payload, 0, 16 );
            $ciphertext = substr( $payload, 16 );
            $key = self::get_key();
            $decrypted = openssl_decrypt( $ciphertext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );
            return ( $decrypted !== false ) ? $decrypted : '';
        }

        // Legacy format: ENC:<base64-encoded ciphertext with deterministic IV>
        if ( strpos( $decoded, 'ENC:' ) === 0 ) {
            if ( ! function_exists( 'openssl_decrypt' ) ) return '';
            $encrypted = substr( $decoded, 4 );
            $key = wp_salt( 'auth' );
            $iv  = self::get_legacy_iv();
            $decrypted = openssl_decrypt( $encrypted, self::$cipher, $key, 0, $iv );
            return ( $decrypted !== false ) ? $decrypted : '';
        }

        // Not encrypted — return as-is (plain text)
        return $stored;
    }

    /**
     * Check if a stored value is already encrypted (either format).
     *
     * @param string $stored The stored value to check.
     * @return bool True if encrypted, false otherwise.
     */
    public static function is_encrypted( $stored ) {
        if ( empty( $stored ) ) return false;
        $decoded = base64_decode( $stored, true );
        if ( $decoded === false ) return false;
        return ( strpos( $decoded, 'ENC2:' ) === 0 || strpos( $decoded, 'ENC:' ) === 0 );
    }
}
