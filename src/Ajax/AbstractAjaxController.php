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
        return "
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap');
            body, table, td, p, a, li, blockquote {
                -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;
                font-family: 'Noto Sans Thai', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
            }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
            .header-bg { background-color: #0F1E32; padding: 40px 20px; text-align: center; }
            .site-title { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 1px; }
            .sub-title { color: #A0AEC0; margin: 5px 0 0 0; font-size: 14px; font-weight: 300; }
            .content-body { padding: 40px 30px; }
            .heading-primary { color: #0F1E32; margin: 0 0 15px 0; font-size: 22px; font-weight: 700; line-height: 1.4; }
            .text-body { color: #4A5568; line-height: 1.6; margin: 0 0 20px 0; font-size: 16px; }
            .data-table { width: 100%; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; border-collapse: separate; border-spacing: 0; }
            .data-cell { padding: 15px; border-bottom: 1px solid #EDF2F7; }
            .label-cell { width: 35%; color: #574319; font-weight: 600; font-size: 14px; vertical-align: top; }
            .value-cell { width: 65%; color: #0F1E32; font-size: 15px; font-weight: 400; vertical-align: top; }
            .footer { background-color: #F1F5F9; padding: 20px; text-align: center; border-top: 3px solid #574319; }
            .footer-text { margin: 0; color: #574319; font-size: 13px; line-height: 1.5; opacity: 0.8; }
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
            $table = '<table style="width:100%; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; border-collapse:separate; border-spacing:0;">';
            foreach ( $entry_data as $label => $value ) {
                $table .= '<tr>';
                $table .= '<td style="padding:12px 15px; border-bottom:1px solid #EDF2F7; width:35%; color:#574319; font-weight:600; font-size:14px; vertical-align:top;">' . esc_html( $label ) . '</td>';
                $table .= '<td style="padding:12px 15px; border-bottom:1px solid #EDF2F7; width:65%; color:#0F1E32; font-size:15px; vertical-align:top;">' . nl2br( esc_html( $value ) ) . '</td>';
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

    public function configure_smtp( $phpmailer ) {
        if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) return;

        // === OAuth2 (XOAUTH2) — Google / Microsoft 365 ===
        $auth_method = get_option( 'isxf_smtp_auth_method', 'password' );
        if ( ( $auth_method === 'oauth_google' || $auth_method === 'oauth_microsoft' )
            && class_exists( OAuth::class ) && class_exists( OAuthTokenProvider::class )
            && OAuth::is_connected() ) {

            $provider = ( $auth_method === 'oauth_google' ) ? 'google' : 'microsoft';
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

        $from_email = get_option( 'isxf_smtp_from_email' );
        if ( empty( $from_email ) || !is_email($from_email) ) {
            $from_email = get_option( 'isxf_smtp_user' );
        }

        $phpmailer->From     = $from_email;
        $phpmailer->FromName = get_option( 'isxf_smtp_from_name' ) ?: get_bloginfo('name');
    }
}
