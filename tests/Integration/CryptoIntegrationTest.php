<?php
/**
 * Integration tests for ISXF\Crypto against the REAL WordPress salts.
 *
 * The pure unit tests (tests/Unit/CryptoTest.php) run on a stub wp_salt();
 * these verify the same contracts with production-like keys, so a WP core
 * change to wp_salt() cannot silently break stored secrets.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

use ISXF\Crypto;

/**
 * @group crypto
 */
class CryptoIntegrationTest extends WP_UnitTestCase {

    public function test_real_salt_is_in_use(): void {
        $this->assertStringNotContainsString( 'isxf-unit-test-salt', wp_salt( 'auth' ) );
        $this->assertNotEmpty( wp_salt( 'auth' ) );
    }

    public function test_enc3_round_trip_with_real_salt(): void {
        $plain     = 'prod-smtp-password-€-ภาษาไทย';
        $encrypted = Crypto::encrypt( $plain );

        $this->assertIsString( $encrypted );
        $this->assertSame( $plain, Crypto::decrypt( $encrypted ) );
    }

    public function test_enc2_value_from_v060_still_decrypts_with_real_salt(): void {
        // Constructed with the exact pre-v0.6.1 algorithm and the real salt.
        $iv  = random_bytes( 16 );
        $key = hash( 'sha256', wp_salt( 'auth' ), true );
        $ct  = openssl_encrypt( 'oauth-refresh-token', 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        $this->assertNotFalse( $ct );

        $stored = base64_encode( 'ENC2:' . $iv . $ct );
        $this->assertSame( 'oauth-refresh-token', Crypto::decrypt( $stored ) );
    }

    public function test_tamper_rejected_with_real_salt(): void {
        $encrypted = Crypto::encrypt( 'sensitive' );
        $raw       = base64_decode( $encrypted, true );
        $raw[30]   = chr( ord( $raw[30] ) ^ 0x01 );

        $this->assertSame( '', Crypto::decrypt( base64_encode( $raw ) ) );
    }
}
