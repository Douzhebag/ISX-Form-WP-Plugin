<?php
/**
 * Pure unit tests for ISXF\Crypto (no WordPress required — runs on stubs).
 *
 * @package InsightX_Form\Tests\Unit
 */

require_once dirname( __DIR__, 2 ) . '/src/Crypto.php';

use ISXF\Crypto;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ISXF\Crypto
 */
class CryptoTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['isxf_test_log'] = [];
    }

    /**
     * Build a value encrypted with the old ENC2 algorithm (pre-v0.6.1):
     * base64( 'ENC2:' . iv . AES-256-CBC( key = hash('sha256', wp_salt('auth'), true) ) )
     */
    private function make_enc2( string $plain ): string {
        $iv  = random_bytes( 16 );
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $ct  = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        $this->assertNotFalse( $ct, 'Failed to construct ENC2 fixture' );
        return base64_encode( 'ENC2:' . $iv . $ct );
    }

    /**
     * Build a value encrypted with the legacy ENC algorithm (pre-v0.4.1):
     * base64( 'ENC:' . base64_encode( AES-256-CBC( key = wp_salt('auth') raw, deterministic IV ) ) )
     */
    private function make_legacy_enc( string $plain ): string {
        $key = wp_salt( 'auth' );
        $iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
        $ct  = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
        $this->assertNotFalse( $ct, 'Failed to construct legacy ENC fixture' );
        return base64_encode( 'ENC:' . $ct );
    }

    public function test_enc3_round_trip(): void {
        $plain     = 'my-secret-password-123!@#';
        $encrypted = Crypto::encrypt( $plain );

        $this->assertIsString( $encrypted );
        $this->assertStringStartsWith( 'ENC3:', base64_decode( $encrypted, true ) );
        $this->assertSame( $plain, Crypto::decrypt( $encrypted ) );
    }

    public function test_enc3_round_trip_unicode(): void {
        $plain = 'รหัสผ่านภาษาไทย 🔐 ข้อมูลลูกค้า';
        $this->assertSame( $plain, Crypto::decrypt( Crypto::encrypt( $plain ) ) );
    }

    public function test_encrypt_produces_unique_ciphertexts(): void {
        $a = Crypto::encrypt( 'same-input' );
        $b = Crypto::encrypt( 'same-input' );
        $this->assertNotSame( $a, $b, 'Random IV should make identical plaintexts encrypt differently' );
    }

    public function test_encrypt_empty_returns_empty_string(): void {
        $this->assertSame( '', Crypto::encrypt( '' ) );
    }

    public function test_decrypt_empty_returns_empty_string(): void {
        $this->assertSame( '', Crypto::decrypt( '' ) );
    }

    public function test_tampered_ciphertext_is_rejected(): void {
        $encrypted = Crypto::encrypt( 'top-secret' );
        $raw       = base64_decode( $encrypted, true );

        // Flip one byte inside the ciphertext region (after 'ENC3:' + 16-byte IV + 32-byte HMAC).
        $raw[ strlen( $raw ) - 1 ] = chr( ord( $raw[ strlen( $raw ) - 1 ] ) ^ 0x01 );
        $tampered = base64_encode( $raw );

        $this->assertSame( '', Crypto::decrypt( $tampered ), 'Tampered ciphertext must decrypt to empty string' );
        if ( defined( 'ISXF_TEST_ENV' ) && ISXF_TEST_ENV === 'unit' ) {
            $this->assertNotEmpty( $GLOBALS['isxf_test_log'], 'HMAC mismatch should be logged' );
        }
    }

    public function test_tampered_mac_is_rejected(): void {
        $encrypted = Crypto::encrypt( 'top-secret' );
        $raw       = base64_decode( $encrypted, true );

        // Flip one byte inside the HMAC region (bytes 21..52 after the 5-byte prefix).
        $raw[25] = chr( ord( $raw[25] ) ^ 0xFF );
        $tampered = base64_encode( $raw );

        $this->assertSame( '', Crypto::decrypt( $tampered ) );
    }

    public function test_enc2_value_still_decrypts(): void {
        // Values stored by v0.4.1–v0.6.0 must keep working after the ENC3 upgrade.
        $stored = $this->make_enc2( 'smtp-password-from-v060' );
        $this->assertSame( 'smtp-password-from-v060', Crypto::decrypt( $stored ) );
    }

    public function test_legacy_enc_value_still_decrypts(): void {
        // Values stored before v0.4.1 (deterministic IV) must remain decryptable.
        $stored = $this->make_legacy_enc( 'very-old-secret' );
        $this->assertSame( 'very-old-secret', Crypto::decrypt( $stored ) );
    }

    public function test_plaintext_passthrough(): void {
        $this->assertSame( 'not-encrypted-at-all!!', Crypto::decrypt( 'not-encrypted-at-all!!' ) );
    }

    public function test_base64_but_not_encrypted_passthrough(): void {
        // Valid base64 whose payload has no ENC prefix: returned as stored (treated as plaintext).
        $stored = base64_encode( 'just some base64 text' );
        $this->assertSame( $stored, Crypto::decrypt( $stored ) );
    }

    public function test_is_encrypted_detection(): void {
        $this->assertTrue( Crypto::is_encrypted( Crypto::encrypt( 'x' ) ) );
        $this->assertTrue( Crypto::is_encrypted( $this->make_enc2( 'x' ) ) );
        $this->assertTrue( Crypto::is_encrypted( $this->make_legacy_enc( 'x' ) ) );
        $this->assertFalse( Crypto::is_encrypted( 'plain text!!' ) );
        $this->assertFalse( Crypto::is_encrypted( '' ) );
    }

    /**
     * encrypt() must return WP_Error (never plaintext) when OpenSSL is missing.
     *
     * NOT TESTABLE without changing the class: ISXF\Crypto::encrypt() guards
     * with function_exists('openssl_encrypt') in the global namespace, which
     * cannot be overridden or disabled per-test. There is no filter seam.
     * Tracked in docs/KNOWN-ISSUES.md — candidate for a Phase 2 seam.
     */
    public function test_encrypt_failure_returns_wp_error_when_openssl_missing(): void {
        $this->markTestIncomplete(
            'Cannot simulate missing openssl_encrypt without modifying ISXF\Crypto '
            . '(function_exists() in global namespace, no filter seam). See docs/KNOWN-ISSUES.md.'
        );
    }
}
