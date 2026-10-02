<?php

namespace ISXF\Ajax;

use ISXF\Crypto;
use ISXF\OAuth;
use ISXF\OAuthTokenProvider;
use ISXF\Template;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared base for the AJAX controllers (Phase 2.4 — see docs/ROADMAP.md).
 *
 * Holds the pieces every endpoint relies on: client-IP resolution with the
 * trusted-proxy list, the shared email header style, the email template
 * builders, and the wp_mail/PHPMailer SMTP plumbing (Basic + XOAUTH2).
 * Code moved verbatim out of the former ISXF\AjaxHandler — no behavior change.
 */
abstract class AbstractAjaxController {

    protected $mail_errors = [];

    protected function get_client_ip() {
        $remote_addr = ! empty( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';
        if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            $remote_addr = '0.0.0.0';
        }

        // เชื่อ proxy headers เฉพาะเมื่อ REMOTE_ADDR เป็น trusted proxy เท่านั้น
        // (ป้องกัน IP spoofing ทะลุ rate limit — default = เชื่อ REMOTE_ADDR อย่างเดียว)
        if ( ! $this->is_trusted_proxy( $remote_addr ) ) {
            return $remote_addr;
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',    // General proxy
            'HTTP_X_REAL_IP',          // Nginx proxy
        ];
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                // X-Forwarded-For may contain comma-separated IPs; take the first (client)
                $ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return $remote_addr;
    }

    /**
     * รายการ trusted proxy (IP หรือ CIDR) — อ่านจาก option 'isxf_trusted_proxies'
     * (บรรทัดละ 1 ค่า) และปรับแต่งได้ผ่าน filter 'isxf_trusted_proxies'
     * default = ว่าง = ไม่เชื่อ proxy ใดๆ
     */
    protected function get_trusted_proxies() {
        $option  = get_option( 'isxf_trusted_proxies', '' );
        $proxies = ! empty( $option ) ? preg_split( '/[\r\n,]+/', $option ) : [];
        $proxies = array_filter( array_map( 'trim', (array) $proxies ) );
        return apply_filters( 'isxf_trusted_proxies', $proxies );
    }

    protected function is_trusted_proxy( $ip ) {
        foreach ( $this->get_trusted_proxies() as $proxy ) {
            if ( $this->ip_matches_cidr( $ip, $proxy ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * ตรวจว่า IP อยู่ในช่วง CIDR หรือไม่ (รองรับ IPv4 และ IPv6, หรือ IP เปล่าๆ)
     */
    protected function ip_matches_cidr( $ip, $cidr ) {
        if ( strpos( $cidr, '/' ) === false ) {
            return $ip === $cidr;
        }
        list( $subnet, $bits ) = explode( '/', $cidr, 2 );
        $bits       = intval( $bits );
        $ip_bin     = @inet_pton( $ip );
        $subnet_bin = @inet_pton( $subnet );
        if ( $ip_bin === false || $subnet_bin === false || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
            return false;
        }

        if ( strlen( $ip_bin ) === 4 ) {
            // IPv4
            if ( $bits < 0 || $bits > 32 ) return false;
            $mask = ( $bits === 0 ) ? 0 : ( 0xFFFFFFFF << ( 32 - $bits ) );
            return ( ip2long( $ip ) & $mask ) === ( ip2long( $subnet ) & $mask );
        }

        // IPv6 — เทียบทีละ byte
        if ( $bits < 0 || $bits > 128 ) return false;
        $full_bytes = intdiv( $bits, 8 );
        $rem_bits   = $bits % 8;
        if ( substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
            return false;
        }
        if ( $rem_bits === 0 ) return true;
        $mask_byte = ( 0xFF << ( 8 - $rem_bits ) ) & 0xFF;
        return ( ord( $ip_bin[ $full_bytes ] ) & $mask_byte ) === ( ord( $subnet_bin[ $full_bytes ] ) & $mask_byte );
    }

    protected function get_email_header_style() {
        // Classes used inside custom email bodies (and the "edit from this
        // template" copies). The layout itself is inline-styled
        // (templates/emails/layout.php) so it survives clients that drop <style>.
        return "
        <style>
            body, table, td, p, a, li, blockquote {
                -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;
                font-family: 'Noto Sans Thai', 'Sarabun', 'Prompt', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            }
            .heading-primary { color: #0f172a; margin: 0 0 12px 0; font-size: 20px; font-weight: 700; line-height: 1.4; }
            .text-body { color: #5a6881; line-height: 1.6; margin: 0 0 20px 0; font-size: 15px; }
            .data-table { width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; border-collapse: separate; border-spacing: 0; }
            .data-cell { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
            .label-cell { width: 35%; color: #5a6881; font-weight: 600; font-size: 14px; vertical-align: top; }
            .value-cell { width: 65%; color: #0f172a; font-size: 15px; font-weight: 400; vertical-align: top; }
        </style>";
    }

    protected function get_hotel_booking_email_template( $entry_data ) {
        return Template::get( 'emails/booking', [
            'entry_data'   => $entry_data,
            'site_name'    => get_bloginfo( 'name' ),
            'header_style' => $this->get_email_header_style(),
        ] );
    }

    protected function get_general_inquiry_email_template( $entry_data ) {
        return Template::get( 'emails/inquiry', [
            'entry_data'   => $entry_data,
            'site_name'    => get_bloginfo( 'name' ),
            'header_style' => $this->get_email_header_style(),
        ] );
    }

    protected function get_admin_notification_template( $entry_data, $form_title, $user_ip ) {
        return Template::get( 'emails/admin-notification', [
            'entry_data' => $entry_data,
            'site_name'  => get_bloginfo( 'name' ),
            'form_title' => $form_title,
            'user_ip'    => $user_ip,
        ] );
    }

    /**
     * Process merge tags in custom email templates.
     */
    protected function process_merge_tags( $text, $entry_data, $form_id ) {
        $site_name = get_bloginfo( 'name' );
        $form_title = get_the_title( $form_id );

        // Basic tags
        $text = str_replace( '{site_name}', esc_html( $site_name ), $text );
        $text = str_replace( '{form_title}', esc_html( $form_title ), $text );

        // {all_fields} → HTML table
        if ( strpos( $text, '{all_fields}' ) !== false ) {
            $table = '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="width:100%; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; border-spacing:0;">';
            foreach ( $entry_data as $label => $value ) {
                $table .= '<tr>';
                $table .= '<td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:35%; color:#5a6881; font-weight:600; font-size:14px; vertical-align:top;">' . esc_html( $label ) . '</td>';
                $table .= '<td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:65%; color:#0f172a; font-size:15px; vertical-align:top;">' . nl2br( esc_html( $value ) ) . '</td>';
                $table .= '</tr>';
            }
            $table .= '</table>';
            $text = str_replace( '{all_fields}', $table, $text );
        }

        // {field:LABEL} → value
        if ( preg_match_all( '/\{field:(.+?)\}/', $text, $matches ) ) {
            foreach ( $matches[1] as $i => $label ) {
                $value = isset( $entry_data[ $label ] ) ? esc_html( $entry_data[ $label ] ) : '';
                $text = str_replace( $matches[0][$i], $value, $text );
            }
        }

        return $text;
    }

    /**
     * Wrap custom email content in the standard email layout.
     */
    protected function wrap_in_email_layout( $content, $form_id ) {
        $site_name = get_bloginfo( 'name' );
        $form_title = get_the_title( $form_id );
        // Convert newlines to <br> for plain text content
        $content = nl2br( $content );
        return Template::get( 'emails/custom-layout', [
            'content'      => $content,
            'site_name'    => $site_name,
            'form_title'   => $form_title,
            'header_style' => $this->get_email_header_style(),
        ] );
    }

    public function capture_mail_error( $wp_error ) {
        if ( is_wp_error( $wp_error ) ) {
            $this->mail_errors[] = $wp_error->get_error_message();
        }
    }

    /**
     * Attach the SMTP config to the next wp_mail() calls.
     *
     * The From address goes through wp_mail_from — wp_mail() validates its
     * From before phpmailer_init runs, so on a host whose default
     * (wordpress@<host>) is not a valid address (e.g. http://localhost)
     * setting it in configure_smtp() alone came too late.
     */
    protected function hook_smtp() {
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
        add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );
    }

    /** Undo hook_smtp(). */
    protected function unhook_smtp() {
        remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
        remove_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
        remove_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );
    }

    /**
     * Sender mailbox for SMTP: the configured From Email, else the SMTP
     * username (Basic Auth) or the connected Google account (OAuth) — but
     * only if it is a real address ('' otherwise).
     *
     * @return string
     */
    protected function smtp_sender_email() {
        if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) {
            return '';
        }
        $from = get_option( 'isxf_smtp_from_email' );
        if ( ! empty( $from ) && is_email( $from ) ) {
            return $from;
        }
        $fallback = get_option( 'isxf_smtp_auth_method', 'password' ) === 'oauth_google' && OAuth::is_connected()
            ? OAuth::connected_email()
            : get_option( 'isxf_smtp_user' );
        return is_email( $fallback ) ? $fallback : '';
    }

    /**
     * Filter for wp_mail_from: the SMTP sender mailbox when there is one.
     *
     * @param string $from From address wp_mail() would use.
     * @return string
     */
    public function filter_mail_from( $from ) {
        $sender = $this->smtp_sender_email();
        return $sender !== '' ? $sender : $from;
    }

    /**
     * Filter for wp_mail_from_name: the configured From Name or site name.
     *
     * @param string $name From name wp_mail() would use.
     * @return string
     */
    public function filter_mail_from_name( $name ) {
        if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) {
            return $name;
        }
        return get_option( 'isxf_smtp_from_name' ) ?: get_bloginfo( 'name' );
    }

    public function configure_smtp( $phpmailer ) {
        if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) return;

        // === OAuth2 (XOAUTH2) — Google ===
        $auth_method = get_option( 'isxf_smtp_auth_method', 'password' );
        if ( $auth_method === 'oauth_google'
            && class_exists( OAuth::class ) && class_exists( OAuthTokenProvider::class )
            && OAuth::is_connected() ) {

            $provider = 'google';
            $email    = OAuth::connected_email();
            if ( ! is_email( $email ) ) {
                // id_token ไม่ได้คืน email — fallback ไปที่ช่อง Username เดิม
                $email = get_option( 'isxf_smtp_user' );
            }
            $smtp     = OAuth::smtp_config( $provider );

            $phpmailer->isSMTP();
            $phpmailer->Host       = $smtp['host'];
            $phpmailer->Port       = $smtp['port'];
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->SMTPAuth   = true;
            $phpmailer->AuthType   = 'XOAUTH2';
            $phpmailer->setOAuth( new OAuthTokenProvider( $email, $provider ) );

            if ( get_option( 'isxf_smtp_disable_ssl_verify' ) === 'yes' ) {
                $phpmailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            // For OAuth the From address must be the authenticated mailbox.
            $from_email = get_option( 'isxf_smtp_from_email' );
            if ( empty( $from_email ) || ! is_email( $from_email ) ) {
                $from_email = $email;
            }
            $phpmailer->From     = $from_email;
            $phpmailer->FromName = get_option( 'isxf_smtp_from_name' ) ?: get_bloginfo('name');
            return;
        }

        // === Basic authentication (username + password) ===
        $phpmailer->isSMTP();
        $phpmailer->Host       = get_option( 'isxf_smtp_host' );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = get_option( 'isxf_smtp_user' );
        $phpmailer->Password   = Crypto::decrypt( get_option( 'isxf_smtp_pass' ) );

        $port = intval( get_option( 'isxf_smtp_port' ) );
        $phpmailer->Port = $port;

        if ( $port === 465 ) {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ( $port === 587 ) {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = get_option( 'isxf_smtp_secure' );
        }

        // Only disable SSL verification if explicitly set (for development)
        if ( get_option( 'isxf_smtp_disable_ssl_verify' ) === 'yes' ) {
            $phpmailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        // From: the configured From Email, else the username when it is a
        // mailbox. Resend ("resend") and Cloudflare ("api_token") usernames are
        // not, so keep wp_mail()'s own From (wordpress@<site domain>) then.
        $from_email = get_option( 'isxf_smtp_from_email' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = get_option( 'isxf_smtp_user' );
        }
        if ( is_email( $from_email ) ) {
            $phpmailer->From = $from_email;
        }
        $phpmailer->FromName = get_option( 'isxf_smtp_from_name' ) ?: get_bloginfo('name');
    }
}
