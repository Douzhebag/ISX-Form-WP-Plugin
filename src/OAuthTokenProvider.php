<?php
/**
 * ISXF\OAuthTokenProvider — PHPMailer token provider for XOAUTH2.
 *
 * The interface lives in WordPress core's bundled PHPMailer but is only loaded
 * lazily when mail is first sent, so make sure it is available before declaring
 * our implementation.
 *
 * @since 0.6.0
 */

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! interface_exists( '\\PHPMailer\\PHPMailer\\OAuthTokenProvider' ) ) {
    $isxf_oauth_iface = ABSPATH . WPINC . '/PHPMailer/OAuthTokenProvider.php';
    if ( file_exists( $isxf_oauth_iface ) ) require_once $isxf_oauth_iface;
}

if ( interface_exists( '\\PHPMailer\\PHPMailer\\OAuthTokenProvider' ) ) {
    class OAuthTokenProvider implements \PHPMailer\PHPMailer\OAuthTokenProvider {

        private $email;
        private $provider;

        public function __construct( $email, $provider ) {
            $this->email    = $email;
            $this->provider = $provider;
        }

        public function getOauth64() {
            $token = OAuth::get_access_token( $this->provider );
            if ( empty( $token ) ) return '';
            return base64_encode( 'user=' . $this->email . "\x01auth=Bearer " . $token . "\x01\x01" );
        }
    }
}
