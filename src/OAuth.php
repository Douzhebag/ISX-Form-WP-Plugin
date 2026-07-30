<?php
/**
 * ISXF\OAuth — OAuth2 (XOAUTH2) support for SMTP sending.
 *
 * Handles the OAuth2 authorization-code flow and access-token refresh for
 * Google (Gmail) and Microsoft 365 (Outlook/Exchange Online), so the plugin
 * can send mail via XOAUTH2 instead of basic SMTP authentication.
 *
 * No external library is required — WordPress already bundles PHPMailer with
 * the OAuthTokenProvider interface and setOAuth()/AuthType = 'XOAUTH2' support.
 *
 * @since 0.6.0
 */

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

class OAuth {

        const OPT_AUTH_METHOD   = 'isxf_smtp_auth_method';   // password | oauth_google | oauth_microsoft
        const OPT_CLIENT_ID     = 'isxf_smtp_oauth_client_id';
        const OPT_CLIENT_SECRET = 'isxf_smtp_oauth_client_secret'; // encrypted
        const OPT_REFRESH_TOKEN = 'isxf_smtp_oauth_refresh_token'; // encrypted
        const OPT_TENANT        = 'isxf_smtp_oauth_tenant';        // microsoft only
        const OPT_CONNECTED     = 'isxf_smtp_oauth_connected';      // connected account email

        const TOKEN_TRANSIENT   = 'isxf_oauth_access_token';
        const STATE_TRANSIENT   = 'isxf_oauth_state_';

        public function __construct() {
            add_action( 'admin_post_isxf_oauth_connect',    [ $this, 'handle_connect' ] );
            add_action( 'admin_post_isxf_oauth_callback',   [ $this, 'handle_callback' ] );
            add_action( 'admin_post_isxf_oauth_disconnect', [ $this, 'handle_disconnect' ] );
        }

        /**
         * Provider configuration map.
         */
        public static function providers() {
            return [
                'google' => [
                    'label'     => 'Google / Gmail',
                    'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                    'token'     => 'https://oauth2.googleapis.com/token',
                    'scope'     => 'https://mail.google.com/ openid email',
                    'extra'     => [ 'access_type' => 'offline', 'prompt' => 'consent' ],
                    'host'      => 'smtp.gmail.com',
                    'port'      => 587,
                ],
                'microsoft' => [
                    'label'     => 'Microsoft 365 / Outlook',
                    'authorize' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
                    'token'     => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
                    'scope'     => 'https://outlook.office.com/SMTP.Send offline_access openid email',
                    'extra'     => [ 'prompt' => 'consent' ],
                    'host'      => 'smtp.office365.com',
                    'port'      => 587,
                ],
            ];
        }

        /**
         * Map the stored auth method to a provider key (or null for basic auth).
         */
        public static function current_provider() {
            $method = get_option( self::OPT_AUTH_METHOD, 'password' );
            if ( $method === 'oauth_google' )    return 'google';
            if ( $method === 'oauth_microsoft' ) return 'microsoft';
            return null;
        }

        /**
         * SMTP host/port for a given provider.
         */
        public static function smtp_config( $provider ) {
            $providers = self::providers();
            if ( ! isset( $providers[ $provider ] ) ) return [ 'host' => '', 'port' => 587 ];
            return [ 'host' => $providers[ $provider ]['host'], 'port' => $providers[ $provider ]['port'] ];
        }

        /**
         * The email address of the connected account, or '' if not connected.
         */
        public static function connected_email() {
            return (string) get_option( self::OPT_CONNECTED, '' );
        }

        /**
         * Whether OAuth is fully connected (refresh token + email present).
         */
        public static function is_connected() {
            return self::connected_email() !== '' && get_option( self::OPT_REFRESH_TOKEN, '' ) !== '';
        }

        /**
         * The redirect URI to register in the Google/Azure console.
         */
        public static function redirect_uri() {
            return admin_url( 'admin-post.php?action=isxf_oauth_callback' );
        }

        private function settings_url( $args = [] ) {
            $base = admin_url( 'edit.php?post_type=isxf_form&page=isxf-global-settings' );
            return $args ? add_query_arg( $args, $base ) : $base;
        }

        private static function tenant() {
            $tenant = trim( (string) get_option( self::OPT_TENANT, '' ) );
            return $tenant !== '' ? $tenant : 'common';
        }

        private static function endpoint( $provider, $which ) {
            $providers = self::providers();
            $url = isset( $providers[ $provider ][ $which ] ) ? $providers[ $provider ][ $which ] : '';
            return str_replace( '{tenant}', rawurlencode( self::tenant() ), $url );
        }

        /**
         * Step 1 — redirect the admin to the provider's consent screen.
         */
        public function handle_connect() {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to perform this action.', 'insightx-form' ) );
            check_admin_referer( 'isxf_oauth_connect' );

            $provider  = isset( $_GET['provider'] ) ? sanitize_key( $_GET['provider'] ) : '';
            $providers = self::providers();
            if ( ! isset( $providers[ $provider ] ) ) wp_die( esc_html__( 'Invalid provider.', 'insightx-form' ) );

            $client_id = get_option( self::OPT_CLIENT_ID, '' );
            if ( empty( $client_id ) ) {
                wp_safe_redirect( $this->settings_url( [ 'isxf_oauth' => 'error', 'msg' => rawurlencode( __( 'Please enter and save your Client ID / Client Secret before connecting.', 'insightx-form' ) ) ] ) );
                exit;
            }

            $state = wp_generate_password( 24, false );
            set_transient( self::STATE_TRANSIENT . get_current_user_id(), [ 'state' => $state, 'provider' => $provider ], 10 * MINUTE_IN_SECONDS );

            $args = array_merge( [
                'client_id'     => $client_id,
                'redirect_uri'  => self::redirect_uri(),
                'response_type' => 'code',
                'scope'         => $providers[ $provider ]['scope'],
                'state'         => $state,
            ], $providers[ $provider ]['extra'] );

            $authorize_url = self::endpoint( $provider, 'authorize' ) . '?' . http_build_query( $args );
            // The consent screen is an external URL (accounts.google.com /
            // login.microsoftonline.com) — whitelist its host so
            // wp_safe_redirect() accepts it for this redirect.
            add_filter( 'allowed_redirect_hosts', function ( $hosts ) use ( $authorize_url ) {
                $host = wp_parse_url( $authorize_url, PHP_URL_HOST );
                if ( $host ) $hosts[] = $host;
                return $hosts;
            } );
            wp_safe_redirect( $authorize_url );
            exit;
        }

        /**
         * Step 2 — handle the provider callback, exchange code for tokens.
         */
        public function handle_callback() {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to perform this action.', 'insightx-form' ) );

            $saved = get_transient( self::STATE_TRANSIENT . get_current_user_id() );
            delete_transient( self::STATE_TRANSIENT . get_current_user_id() );

            $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
            if ( empty( $saved ) || ! is_array( $saved ) || ! hash_equals( (string) $saved['state'], $state ) ) {
                $this->redirect_error( __( 'State verification failed. Please try connecting again.', 'insightx-form' ) );
            }

            if ( isset( $_GET['error'] ) ) {
                $desc = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : sanitize_text_field( wp_unslash( $_GET['error'] ) );
                /* translators: %s: error description returned by the OAuth provider. */
                $this->redirect_error( sprintf( __( 'The provider denied the request: %s', 'insightx-form' ), $desc ) );
            }

            $provider = $saved['provider'];
            $code     = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
            if ( empty( $code ) ) $this->redirect_error( __( 'Authorization code not received.', 'insightx-form' ) );

            $providers = self::providers();
            $response  = wp_remote_post( self::endpoint( $provider, 'token' ), [
                'timeout' => 20,
                'body'    => [
                    'client_id'     => get_option( self::OPT_CLIENT_ID, '' ),
                    'client_secret' => Crypto::decrypt( get_option( self::OPT_CLIENT_SECRET, '' ) ),
                    'code'          => $code,
                    'redirect_uri'  => self::redirect_uri(),
                    'grant_type'    => 'authorization_code',
                    'scope'         => $providers[ $provider ]['scope'],
                ],
            ] );

            if ( is_wp_error( $response ) ) {
                /* translators: %s: connection error message. */
                $this->redirect_error( sprintf( __( 'Could not connect to the token endpoint: %s', 'insightx-form' ), $response->get_error_message() ) );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
                $err = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : __( 'Refresh token not received.', 'insightx-form' ) );
                isxf_log_error( 'OAuth token exchange failed: ' . wp_remote_retrieve_body( $response ) );
                /* translators: %s: error message returned by the OAuth provider. */
                $this->redirect_error( sprintf( __( 'Token exchange failed: %s', 'insightx-form' ), $err ) );
            }

            $email = $this->extract_email( isset( $body['id_token'] ) ? $body['id_token'] : '' );

            $encrypted_token = Crypto::encrypt( $body['refresh_token'] );
            if ( is_wp_error( $encrypted_token ) || empty( $encrypted_token ) ) {
                // เข้ารหัสไม่สำเร็จ — ห้ามเก็บ refresh token เป็น plaintext
                isxf_log_error( 'OAuth connect aborted: refresh token encryption failed' );
                $this->redirect_error( __( 'Encryption failed; the connection was not saved. Please check the server\'s OpenSSL.', 'insightx-form' ) );
            }

            update_option( self::OPT_REFRESH_TOKEN, $encrypted_token );
            update_option( self::OPT_CONNECTED, $email !== '' ? sanitize_email( $email ) : 'connected' );

            // Cache the access token we just received.
            $expires = isset( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3600;
            set_transient( self::TOKEN_TRANSIENT, $body['access_token'], max( 60, $expires - 60 ) );

            wp_safe_redirect( $this->settings_url( [ 'isxf_oauth' => 'connected' ] ) );
            exit;
        }

        /**
         * Disconnect — clear stored tokens.
         */
        public function handle_disconnect() {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to perform this action.', 'insightx-form' ) );
            check_admin_referer( 'isxf_oauth_disconnect' );

            delete_option( self::OPT_REFRESH_TOKEN );
            delete_option( self::OPT_CONNECTED );
            delete_transient( self::TOKEN_TRANSIENT );

            wp_safe_redirect( $this->settings_url( [ 'isxf_oauth' => 'disconnected' ] ) );
            exit;
        }

        private function redirect_error( $message ) {
            wp_safe_redirect( $this->settings_url( [ 'isxf_oauth' => 'error', 'msg' => rawurlencode( $message ) ] ) );
            exit;
        }

        /**
         * Decode the email claim from an OIDC id_token (no signature verification —
         * the token came directly from the provider over TLS in the code exchange).
         */
        private function extract_email( $id_token ) {
            if ( empty( $id_token ) ) return '';
            $parts = explode( '.', $id_token );
            if ( count( $parts ) < 2 ) return '';
            $payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
            if ( ! is_array( $payload ) ) return '';
            foreach ( [ 'email', 'preferred_username', 'upn' ] as $claim ) {
                if ( ! empty( $payload[ $claim ] ) && is_email( $payload[ $claim ] ) ) {
                    return $payload[ $claim ];
                }
            }
            return '';
        }

        /**
         * Return a valid access token for the given provider, refreshing if needed.
         *
         * @return string Access token, or '' on failure.
         */
        public static function get_access_token( $provider ) {
            $cached = get_transient( self::TOKEN_TRANSIENT );
            if ( ! empty( $cached ) ) return $cached;

            $refresh = Crypto::decrypt( get_option( self::OPT_REFRESH_TOKEN, '' ) );
            if ( empty( $refresh ) ) return '';

            $providers = self::providers();
            if ( ! isset( $providers[ $provider ] ) ) return '';

            $response = wp_remote_post( self::endpoint( $provider, 'token' ), [
                'timeout' => 20,
                'body'    => [
                    'client_id'     => get_option( self::OPT_CLIENT_ID, '' ),
                    'client_secret' => Crypto::decrypt( get_option( self::OPT_CLIENT_SECRET, '' ) ),
                    'refresh_token' => $refresh,
                    'grant_type'    => 'refresh_token',
                    'scope'         => $providers[ $provider ]['scope'],
                ],
            ] );

            if ( is_wp_error( $response ) ) {
                isxf_log_error( 'OAuth refresh failed: ' . $response->get_error_message() );
                return '';
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $body['access_token'] ) ) {
                isxf_log_error( 'OAuth refresh response error: ' . wp_remote_retrieve_body( $response ) );
                return '';
            }

            // Some providers rotate the refresh token — store the new one if present.
            if ( ! empty( $body['refresh_token'] ) ) {
                $encrypted_token = Crypto::encrypt( $body['refresh_token'] );
                if ( is_wp_error( $encrypted_token ) || empty( $encrypted_token ) ) {
                    // เข้ารหัสไม่สำเร็จ — คง token เดิมไว้ ห้ามเขียน plaintext ทับ
                    isxf_log_error( 'OAuth refresh token rotation skipped: encryption failed' );
                } else {
                    update_option( self::OPT_REFRESH_TOKEN, $encrypted_token );
                }
            }

            $expires = isset( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3600;
            set_transient( self::TOKEN_TRANSIENT, $body['access_token'], max( 60, $expires - 60 ) );

            return $body['access_token'];
        }
}
