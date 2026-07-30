<?php
/**
 * ISXF\Crypto — Centralized encryption/decryption utility for InsightX Form.
 *
 * New encryptions use the ENC3 format: AES-256-CBC with a random IV plus
 * HMAC-SHA256 (Encrypt-then-MAC) over iv||ciphertext.
 * Backward-compatible with ENC2 (random IV, no MAC) and legacy ENC
 * (deterministic IV — decrypt only) formats.
 *
 * @since 0.4.1
 */

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

class Crypto {

    private static $cipher = 'AES-256-CBC';

    /**
     * Get the encryption key derived from WordPress auth salt.
     * (Used by the ENC2 format — kept unchanged for backward compatibility.)
     */
    private static function get_key() {
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }

    /**
     * Get the ENC3 encryption key (derived separately from the MAC key).
     */
    private static function get_enc_key() {
        return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'isxf-enc' );
    }

    /**
     * Get the ENC3 HMAC key (derived separately from the encryption key).
     */
    private static function get_mac_key() {
        return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'isxf-mac' );
    }

    /**
     * Get the legacy deterministic IV (for backward compatibility only).
     */
    private static function get_legacy_iv() {
        return substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
    }

    /**
     * Encrypt a plain-text value using AES-256-CBC with a random IV and
     * HMAC-SHA256 authentication (Encrypt-then-MAC).
     *
     * Output format: base64( 'ENC3:' . random_iv_16_bytes . hmac_32_bytes . ciphertext )
     *
     * NEVER returns the plaintext on failure.
     *
     * @param string $plain The plain-text value to encrypt.
     * @return string|\WP_Error The encrypted value, or WP_Error on failure.
     */
    public static function encrypt( $plain ) {
        if ( empty( $plain ) ) return '';

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            isxf_log_error( 'ISXF_Crypto::encrypt failed: openssl_encrypt not available' );
            return new \WP_Error( 'isxf_crypto_no_openssl', __( 'OpenSSL is not available on this server', 'insightx-form' ) );
        }

        try {
            $iv = random_bytes( 16 );
        } catch ( \Exception $e ) {
            isxf_log_error( 'ISXF_Crypto::encrypt failed: ' . $e->getMessage() );
            return new \WP_Error( 'isxf_crypto_no_random', __( 'Could not generate random bytes for encryption', 'insightx-form' ) );
        }

        $ciphertext = openssl_encrypt( $plain, self::$cipher, self::get_enc_key(), OPENSSL_RAW_DATA, $iv );
        if ( $ciphertext === false ) {
            isxf_log_error( 'ISXF_Crypto::encrypt failed: openssl_encrypt returned false' );
            return new \WP_Error( 'isxf_crypto_encrypt_failed', __( 'Data encryption failed', 'insightx-form' ) );
        }

        // HMAC ครอบ iv + ciphertext (Encrypt-then-MAC)
        $mac = hash_hmac( 'sha256', $iv . $ciphertext, self::get_mac_key(), true );

        // ENC3: prefix + raw IV (16 bytes) + raw HMAC (32 bytes) + raw ciphertext
        return base64_encode( 'ENC3:' . $iv . $mac . $ciphertext );
    }

    /**
     * Decrypt a stored value. Supports ENC3, ENC2, and legacy ENC formats.
     * Falls back to returning the value as-is if it is not encrypted.
     *
     * @param string $stored The stored (possibly encrypted) value.
     * @return string The decrypted plain-text value, or empty string on failure.
     */
    public static function decrypt( $stored ) {
        if ( empty( $stored ) ) return '';

        $decoded = base64_decode( $stored, true );
        if ( $decoded === false ) return $stored;

        // ENC3 format: ENC3:<16-byte IV><32-byte HMAC><ciphertext>
        if ( strpos( $decoded, 'ENC3:' ) === 0 ) {
            if ( ! function_exists( 'openssl_decrypt' ) ) return '';
            $payload    = substr( $decoded, 5 ); // remove 'ENC3:'
            $iv         = substr( $payload, 0, 16 );
            $mac        = substr( $payload, 16, 32 );
            $ciphertext = substr( $payload, 48 );

            // ตรวจ HMAC ก่อนถอดรหัสเสมอ (ป้องกัน padding oracle / tampering)
            $expected_mac = hash_hmac( 'sha256', $iv . $ciphertext, self::get_mac_key(), true );
            if ( ! hash_equals( $expected_mac, $mac ) ) {
                isxf_log_error( 'ISXF_Crypto::decrypt failed: HMAC mismatch (tampered or wrong key)' );
                return '';
            }

            $decrypted = openssl_decrypt( $ciphertext, self::$cipher, self::get_enc_key(), OPENSSL_RAW_DATA, $iv );
            return ( $decrypted !== false ) ? $decrypted : '';
        }

        // ENC2 format: ENC2:<16-byte IV><ciphertext>
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
     * Check if a stored value is already encrypted (any known format).
     *
     * @param string $stored The stored value to check.
     * @return bool True if encrypted, false otherwise.
     */
    public static function is_encrypted( $stored ) {
        if ( empty( $stored ) ) return false;
        $decoded = base64_decode( $stored, true );
        if ( $decoded === false ) return false;
        return ( strpos( $decoded, 'ENC3:' ) === 0 || strpos( $decoded, 'ENC2:' ) === 0 || strpos( $decoded, 'ENC:' ) === 0 );
    }
}
