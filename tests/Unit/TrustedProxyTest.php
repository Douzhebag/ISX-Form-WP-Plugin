<?php
/**
 * Pure unit tests for the trusted-proxy / CIDR logic of AjaxHandler.
 *
 * Private methods are exercised via reflection with stubbed $_SERVER,
 * get_option() and apply_filters() (see tests/Unit/stubs.php).
 *
 * @package InsightX_Form\Tests\Unit
 */

require_once dirname( __DIR__, 2 ) . '/src/AjaxHandler.php';

use ISXF\AjaxHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ISXF\AjaxHandler
 */
class TrustedProxyTest extends TestCase {

    /** @var AjaxHandler */
    private $handler;

    protected function setUp(): void {
        // These tests depend on the unit stubs (get_option / apply_filters
        // backed by globals). With real WordPress loaded they are meaningless.
        if ( ! defined( 'ISXF_TEST_ENV' ) || ISXF_TEST_ENV !== 'unit' ) {
            $this->markTestSkipped( 'Trusted-proxy unit tests require the stub environment (composer test:unit).' );
        }

        $this->handler = new AjaxHandler();
        $GLOBALS['isxf_test_options'] = [];
        $GLOBALS['isxf_test_filters'] = [];
        $_SERVER = [];
    }

    protected function tearDown(): void {
        unset( $GLOBALS['isxf_test_options'], $GLOBALS['isxf_test_filters'] );
    }

    /**
     * Invoke a private method on the handler.
     *
     * @param string $method Method name.
     * @param mixed  ...$args Arguments.
     * @return mixed
     */
    private function invoke( string $method, ...$args ) {
        $ref = new ReflectionMethod( AjaxHandler::class, $method );
        // Private methods are invokable via reflection since PHP 8.1 (no setAccessible needed).
        return $ref->invokeArgs( $this->handler, $args );
    }

    /**
     * @dataProvider cidrProvider
     */
    public function test_ip_matches_cidr( string $ip, string $cidr, bool $expected ): void {
        $this->assertSame( $expected, $this->invoke( 'ip_matches_cidr', $ip, $cidr ), "ip_matches_cidr('$ip', '$cidr')" );
    }

    public function cidrProvider(): array {
        return [
            // Bare IPs (no slash) — exact string match.
            'IPv4 exact match'        => [ '1.2.3.4', '1.2.3.4', true ],
            'IPv4 exact mismatch'     => [ '1.2.3.4', '1.2.3.5', false ],
            'IPv6 exact match'        => [ '::1', '::1', true ],

            // IPv4 CIDR.
            'IPv4 /24 inside'         => [ '10.0.0.5', '10.0.0.0/24', true ],
            'IPv4 /24 lower boundary' => [ '10.0.0.0', '10.0.0.0/24', true ],
            'IPv4 /24 upper boundary' => [ '10.0.0.255', '10.0.0.0/24', true ],
            'IPv4 /24 outside'        => [ '10.0.1.5', '10.0.0.0/24', false ],
            'IPv4 /0 matches all'     => [ '8.8.8.8', '0.0.0.0/0', true ],
            'IPv4 /32 exact'          => [ '192.168.1.1', '192.168.1.1/32', true ],
            'IPv4 /32 other'          => [ '192.168.1.2', '192.168.1.1/32', false ],
            'IPv4 non-octet /17 in'   => [ '10.0.127.255', '10.0.0.0/17', true ],
            'IPv4 /17 outside'        => [ '10.0.128.1', '10.0.0.0/17', false ],

            // IPv6 CIDR.
            'IPv6 /32 inside'         => [ '2001:db8::1', '2001:db8::/32', true ],
            'IPv6 /32 outside'        => [ '2001:db9::1', '2001:db8::/32', false ],
            'IPv6 /128 exact'         => [ '2001:db8::1', '2001:db8::1/128', true ],
            'IPv6 /0 matches all'     => [ '2001:db8::1', '::/0', true ],
            'IPv6 non-byte /44 in'    => [ '2001:db8:1::1', '2001:db8::/44', true ],
            'IPv6 /44 upper edge'     => [ '2001:db8:f::1', '2001:db8::/44', true ],
            'IPv6 /44 outside'        => [ '2001:db8:10::1', '2001:db8::/44', false ],

            // Garbage / edge cases.
            'garbage IP'              => [ 'not-an-ip', '10.0.0.0/24', false ],
            'garbage CIDR subnet'     => [ '10.0.0.1', 'garbage/24', false ],
            'bits too large (v4)'     => [ '10.0.0.1', '10.0.0.0/33', false ],
            'bits negative'           => [ '10.0.0.1', '10.0.0.0/-1', false ],
            'bits too large (v6)'     => [ '2001:db8::1', '2001:db8::/129', false ],
            'v4 ip vs v6 cidr'        => [ '10.0.0.1', '2001:db8::/32', false ],
            'v6 ip vs v4 cidr'        => [ '2001:db8::1', '10.0.0.0/24', false ],
        ];
    }

    public function test_is_trusted_proxy_reads_option_lines(): void {
        $GLOBALS['isxf_test_options']['isxf_trusted_proxies'] = "10.0.0.0/8\n192.168.1.1, 172.16.0.0/12";

        $this->assertTrue( $this->invoke( 'is_trusted_proxy', '10.20.30.40' ) );
        $this->assertTrue( $this->invoke( 'is_trusted_proxy', '192.168.1.1' ) );
        $this->assertTrue( $this->invoke( 'is_trusted_proxy', '172.16.5.5' ) );
        $this->assertFalse( $this->invoke( 'is_trusted_proxy', '8.8.8.8' ) );
    }

    public function test_default_trusts_no_proxy(): void {
        $this->assertFalse( $this->invoke( 'is_trusted_proxy', '10.0.0.1' ) );
    }

    public function test_filter_can_override_proxy_list(): void {
        $GLOBALS['isxf_test_filters']['isxf_trusted_proxies'] = function ( $proxies ) {
            return [ '203.0.113.0/24' ];
        };
        $GLOBALS['isxf_test_options']['isxf_trusted_proxies'] = '10.0.0.0/8';

        $this->assertTrue( $this->invoke( 'is_trusted_proxy', '203.0.113.9' ) );
        $this->assertFalse( $this->invoke( 'is_trusted_proxy', '10.0.0.1' ), 'Filter should replace the option list' );
    }

    public function test_untrusted_remote_addr_ignores_forwarded_headers(): void {
        $_SERVER['REMOTE_ADDR']          = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '2.2.2.2';

        $this->assertSame( '8.8.8.8', $this->invoke( 'get_client_ip' ) );
    }

    public function test_trusted_proxy_uses_cf_connecting_ip_first(): void {
        $GLOBALS['isxf_test_options']['isxf_trusted_proxies'] = '10.0.0.0/8';
        $_SERVER['REMOTE_ADDR']           = '10.0.0.1'; // trusted
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '2.2.2.2';

        $this->assertSame( '1.1.1.1', $this->invoke( 'get_client_ip' ) );
    }

    public function test_trusted_proxy_takes_first_xff_ip(): void {
        $GLOBALS['isxf_test_options']['isxf_trusted_proxies'] = '10.0.0.0/8';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 10.0.0.1, 10.0.0.2';

        $this->assertSame( '1.1.1.1', $this->invoke( 'get_client_ip' ) );
    }

    public function test_trusted_proxy_skips_invalid_header_ip(): void {
        $GLOBALS['isxf_test_options']['isxf_trusted_proxies'] = '10.0.0.0/8';
        $_SERVER['REMOTE_ADDR']           = '10.0.0.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
        $_SERVER['HTTP_X_REAL_IP']        = '3.3.3.3';

        $this->assertSame( '3.3.3.3', $this->invoke( 'get_client_ip' ) );
    }

    public function test_invalid_remote_addr_falls_back_to_zero(): void {
        $_SERVER['REMOTE_ADDR'] = 'garbage';
        $this->assertSame( '0.0.0.0', $this->invoke( 'get_client_ip' ) );
    }
}
