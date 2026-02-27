<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'ISXF_AJAX_Handler' ) ) {
    class ISXF_AJAX_Handler {
        
        private $mail_errors = [];

        public function __construct() {
            add_action( 'wp_ajax_isxf_submit_form', [ $this, 'handle_secure_submission' ] );
            add_action( 'wp_ajax_nopriv_isxf_submit_form', [ $this, 'handle_secure_submission' ] );
            add_action( 'wp_ajax_isxf_send_test_email', [ $this, 'handle_test_email' ] );
            add_action( 'wp_ajax_isxf_update_entry_status', [ $this, 'handle_update_status' ] );
            add_action( 'wp_ajax_isxf_update_entry_note', [ $this, 'handle_update_note' ] );
        }



        private function get_client_ip() {
            $headers = [
                'HTTP_CF_CONNECTING_IP',   // Cloudflare
                'HTTP_X_FORWARDED_FOR',    // General proxy
                'HTTP_X_REAL_IP',          // Nginx proxy
                'REMOTE_ADDR'
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
            return '0.0.0.0';
        }

        private function get_email_header_style() {
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

        private function get_hotel_booking_email_template( $entry_data ) {
            $site_name = get_bloginfo( 'name' );
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php echo $this->get_email_header_style(); ?></head>
            <body style="margin:0; padding:0; background-color:#F3F4F6;">
                <br>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr><td align="center"><div class="email-container">
                        <div class="header-bg">
                            <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                            <p class="sub-title">Booking Confirmation</p>
                        </div>
                        <div class="content-body">
                            <h2 class="heading-primary">ยืนยันการจองห้องพัก</h2>
                            <p class="text-body">ขอบคุณที่ไว้วางใจเลือกใช้บริการกับเรา<br>เราได้รับข้อมูลความต้องการจองห้องพักของคุณเรียบร้อยแล้ว รายละเอียดดังนี้:</p>
                            <table class="data-table">
                                <?php foreach ( $entry_data as $label => $value ) : ?>
                                    <tr><td class="data-cell label-cell"><?php echo esc_html($label); ?></td><td class="data-cell value-cell"><?php echo nl2br(esc_html($value)); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                            <div style="margin-top: 25px; padding: 15px; background-color: #FFF8F0; border-left: 4px solid #574319; border-radius: 4px;">
                                <p style="margin:0; color:#574319; font-size:14px;"><strong>หมายเหตุ:</strong> การจองนี้เป็นเพียงการส่งความประสงค์เบื้องต้น เจ้าหน้าที่จะทำการตรวจสอบห้องว่างและติดต่อกลับเพื่อยืนยันอีกครั้ง</p>
                            </div>
                        </div>
                        <div class="footer"><p class="footer-text"><strong><?php echo esc_html($site_name); ?></strong><br>หากมีข้อสงสัยเพิ่มเติม โปรดติดต่อเจ้าหน้าที่<br>&copy; <?php echo wp_date('Y'); ?> All rights reserved.</p></div>
                    </div></td></tr>
                </table><br>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }

        private function get_general_inquiry_email_template( $entry_data ) {
            $site_name = get_bloginfo( 'name' );
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php echo $this->get_email_header_style(); ?></head>
            <body style="margin:0; padding:0; background-color:#F3F4F6;">
                <br>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr><td align="center"><div class="email-container">
                        <div class="header-bg">
                            <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                            <p class="sub-title">General Inquiry</p>
                        </div>
                        <div class="content-body">
                            <h2 class="heading-primary">เราได้รับข้อความของคุณแล้ว</h2>
                            <p class="text-body">ขอบคุณที่ติดต่อสอบถามเข้ามา ทีมงานจะรีบตรวจสอบข้อมูลและติดต่อกลับโดยเร็วที่สุด<br>รายละเอียดที่คุณส่งมามีดังนี้:</p>
                            <table class="data-table">
                                <?php foreach ( $entry_data as $label => $value ) : ?>
                                    <tr><td class="data-cell label-cell"><?php echo esc_html($label); ?></td><td class="data-cell value-cell"><?php echo nl2br(esc_html($value)); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <div class="footer"><p class="footer-text"><strong><?php echo esc_html($site_name); ?></strong><br>ข้อความอัตโนมัติจากระบบเว็บไซต์<br>&copy; <?php echo wp_date('Y'); ?> All rights reserved.</p></div>
                    </div></td></tr>
                </table><br>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }

        private function get_admin_notification_template( $entry_data, $form_title, $user_ip ) {
            $site_name = get_bloginfo( 'name' );
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="margin:0; padding:20px; background-color:#f0f0f1; font-family:sans-serif;">
                <div style="max-width:600px; margin:0 auto; background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px;">
                    <h2 style="color:#23282d; border-bottom:1px solid #eee; padding-bottom:10px;">🔔 New Submission: <?php echo esc_html($form_title); ?></h2>
                    <p>มีรายการข้อมูลใหม่เข้ามาจากเว็บไซต์ <strong><?php echo esc_html($site_name); ?></strong></p>
                    
                    <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                        <?php foreach ( $entry_data as $label => $value ) : ?>
                            <tr>
                                <td style="padding:10px; border:1px solid #ddd; background:#f9f9f9; width:35%; font-weight:bold;"><?php echo esc_html($label); ?></td>
                                <td style="padding:10px; border:1px solid #ddd;"><?php echo nl2br(esc_html($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <div style="margin-top:20px; font-size:12px; color:#666; border-top:1px solid #eee; padding-top:10px;">
                        <p><strong>System Info:</strong></p>
                        <ul>
                            <li><strong>Date:</strong> <?php echo current_time('mysql'); ?></li>
                            <li><strong>IP Address:</strong> <?php echo esc_html($user_ip); ?></li>
                        </ul>
                        <p style="text-align:center; margin-top:20px;">
                            <a href="<?php echo admin_url('edit.php?post_type=isxf_form&page=isxf-entries'); ?>" style="background:#2271b1; color:#fff; padding:10px 20px; text-decoration:none; border-radius:3px;">เข้าสู่ระบบเพื่อจัดการข้อมูล</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }

        /**
         * Process merge tags in custom email templates.
         */
        private function process_merge_tags( $text, $entry_data, $form_id ) {
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
        private function wrap_in_email_layout( $content, $form_id ) {
            $site_name = get_bloginfo( 'name' );
            $form_title = get_the_title( $form_id );
            // Convert newlines to <br> for plain text content
            $content = nl2br( $content );
            ob_start();
            ?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php echo $this->get_email_header_style(); ?></head>
            <body style="margin:0; padding:0; background-color:#F3F4F6;">
                <br>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr><td align="center"><div class="email-container">
                        <div class="header-bg">
                            <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                            <p class="sub-title"><?php echo esc_html($form_title); ?></p>
                        </div>
                        <div class="content-body">
                            <?php echo $content; ?>
                        </div>
                        <div class="footer"><p class="footer-text"><strong><?php echo esc_html($site_name); ?></strong><br>&copy; <?php echo wp_date('Y'); ?> All rights reserved.</p></div>
                    </div></td></tr>
                </table><br>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }

        public function capture_mail_error( $wp_error ) {
            if ( is_wp_error( $wp_error ) ) {
                $this->mail_errors[] = $wp_error->get_error_message();
            }
        }

        public function configure_smtp( $phpmailer ) {
            if ( get_option( 'isxf_smtp_enable' ) !== 'yes' ) return;
            
            $phpmailer->isSMTP();
            $phpmailer->Host       = get_option( 'isxf_smtp_host' );
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = get_option( 'isxf_smtp_user' );
            $phpmailer->Password   = ISXF_Crypto::decrypt( get_option( 'isxf_smtp_pass' ) );
            
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

        public function handle_secure_submission() {
            check_ajax_referer( 'isxf_secure_nonce', 'isxf_nonce' );
            
            if ( ! empty( $_POST['isxf_website_url_trap'] ) ) wp_send_json_success( [ 'message' => 'Success' ] );

            $captcha_service = get_option( 'isxf_captcha_service', 'google' );
            
            if ( $captcha_service === 'google' ) {
                $secret_key = get_option( 'isxf_recaptcha_secret_key' );
                if ( ! empty( $secret_key ) ) {
                    $token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( $_POST['g-recaptcha-response'] ) : '';
                    if ( empty( $token ) ) {
                        wp_send_json_error( [ 'message' => 'กรุณายืนยันตัวตนผ่าน reCAPTCHA' ] );
                    }
                    $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
                        'body' => [ 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ]
                    ]);
                    $verify_body = json_decode( wp_remote_retrieve_body( $verify ), true );
                    if ( empty( $verify_body['success'] ) || ( isset( $verify_body['score'] ) && $verify_body['score'] < 0.5 ) ) {
                        wp_send_json_error( [ 'message' => 'การยืนยัน reCAPTCHA ล้มเหลว กรุณาลองใหม่' ] );
                    }
                }
            } elseif ( $captcha_service === 'cloudflare' ) {
                $secret_key = get_option( 'isxf_turnstile_secret_key' );
                if ( ! empty( $secret_key ) ) {
                    $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
                    if ( empty( $token ) ) {
                        wp_send_json_error( [ 'message' => 'กรุณายืนยันตัวตนผ่าน Turnstile' ] );
                    }
                    $verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                        'body' => [ 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ]
                    ]);
                    $verify_body = json_decode( wp_remote_retrieve_body( $verify ), true );
                    if ( empty( $verify_body['success'] ) ) {
                        wp_send_json_error( [ 'message' => 'การยืนยัน Turnstile ล้มเหลว กรุณาลองใหม่' ] );
                    }
                }
            }

            $user_ip = $this->get_client_ip();
            $limit_key = 'isxf_limit_' . md5($user_ip);
            if ( get_transient( $limit_key ) ) {
                wp_send_json_error( [ 'message' => 'คุณส่งข้อมูลเร็วเกินไป กรุณารอสักครู่' ] );
            }

            $form_id = intval( $_POST['isxf_form_id'] );
            $fields = get_post_meta( $form_id, '_isxf_form_fields', true );
            $entry_data = [];
            $customer_email = ''; 

            if ( empty($fields) || ! is_array($fields) ) {
                wp_send_json_error( [ 'message' => 'ไม่พบข้อมูลฟอร์ม' ] );
            }

            foreach ( $fields as $field ) {
                if ( $field['type'] === 'heading' ) continue;
                $val = $_POST[$field['name']] ?? '';
                
                if ( $field['type'] === 'email' ) {
                    $clean_val = sanitize_email($val);
                    if ( !empty($clean_val) ) {
                        $customer_email = $clean_val;
                    }
                } elseif ( $field['type'] === 'textarea' ) {
                    $clean_val = sanitize_textarea_field($val);
                } else {
                    $clean_val = is_array($val) ? implode(', ', array_map('sanitize_text_field', $val)) : sanitize_text_field($val);
                }
                
                if ( $field['required'] === 'yes' && empty($clean_val) ) {
                    wp_send_json_error( [ 'message' => 'กรุณากรอก: ' . $field['label'] ] );
                }
                
                $entry_data[$field['label']] = $clean_val;
            }

            do_action( 'isxf_form_after_submission', $entry_data, $form_id, $user_ip );
            set_transient( $limit_key, true, 30 );

            add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );
            add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

            $site_name = get_bloginfo( 'name' );
            $customer_sent = false;
            $admin_sent = false;
            $error_message = '';

            if ( ! empty( $customer_email ) && is_email( $customer_email ) ) {
                $email_type = get_post_meta( $form_id, '_isxf_form_email_type', true );
                
                if ( $email_type === 'inquiry' ) {
                    $email_subject = 'เราได้รับข้อความของคุณแล้ว - ' . $site_name;
                    $email_body = $this->get_general_inquiry_email_template( $entry_data );
                } elseif ( $email_type === 'custom' ) {
                    $raw_subject = get_post_meta( $form_id, '_isxf_form_email_subject', true );
                    $raw_body    = get_post_meta( $form_id, '_isxf_form_email_body', true );
                    $email_subject = $this->process_merge_tags( $raw_subject ?: 'ขอบคุณที่ติดต่อ - {site_name}', $entry_data, $form_id );
                    $processed_body = $this->process_merge_tags( $raw_body ?: '{all_fields}', $entry_data, $form_id );
                    $email_body = $this->wrap_in_email_layout( $processed_body, $form_id );
                } else {
                    $email_subject = 'ยืนยันการจองห้องพัก - ' . $site_name;
                    $email_body = $this->get_hotel_booking_email_template( $entry_data );
                }
                
                $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
                $customer_sent = wp_mail( $customer_email, $email_subject, $email_body, $headers );
            } else {
                $error_message = 'ไม่พบอีเมลลูกค้า';
            }

            if ( get_option( 'isxf_admin_notify_enable' ) === 'yes' ) {
                $admin_email = get_option( 'isxf_admin_notify_email' );
                if ( empty( $admin_email ) ) {
                    $admin_email = get_option( 'admin_email' );
                }

                $form_title = get_the_title( $form_id );
                $admin_subject = "[Submission] $form_title - จาก $site_name";
                $admin_body = $this->get_admin_notification_template( $entry_data, $form_title, $user_ip );
                
                $admin_headers = [ 'Content-Type: text/html; charset=UTF-8' ];
                
                if ( ! empty( $customer_email ) && is_email( $customer_email ) ) {
                    $admin_headers[] = 'Reply-To: ' . $customer_email;
                }

                $admin_sent = wp_mail( $admin_email, $admin_subject, $admin_body, $admin_headers );
            }

            remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
            remove_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );

            if ( $customer_sent || $admin_sent ) {
                $msg = 'ส่งข้อมูลสำเร็จ!';
                if ( $customer_sent ) $msg .= ' อีเมลยืนยันถูกส่งไปที่ ' . $customer_email;
                wp_send_json_success( [ 'message' => $msg ] );
            } else {
                if ( ! empty( $this->mail_errors ) ) {
                    $error_message = implode( '; ', $this->mail_errors );
                    isxf_log_error( 'Email failed for form #' . $form_id . ': ' . $error_message );
                    wp_send_json_error( [ 'message' => 'บันทึกข้อมูลแล้ว แต่ส่งอีเมลไม่ผ่าน: ' . esc_html( $error_message ) ] );
                } else {
                    wp_send_json_success( [ 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว' ] );
                }
            }
        }

        public function handle_test_email() {
            check_ajax_referer( 'isxf_test_email_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้' ] );
            }

            $to = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';
            if ( empty( $to ) || ! is_email( $to ) ) {
                wp_send_json_error( [ 'message' => 'กรุณาระบุอีเมลปลายทางที่ถูกต้อง' ] );
            }

            $this->mail_errors = [];
            add_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );
            add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

            $site_name = get_bloginfo( 'name' );
            $subject = '🧪 SMTP Test - ' . $site_name;
            $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
                . '<body style="margin:0;padding:30px;background:#f0f0f1;font-family:sans-serif;">'
                . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;border:1px solid #ccd0d4;text-align:center;">'
                . '<div style="font-size:48px;margin-bottom:15px;">✅</div>'
                . '<h2 style="color:#1d2327;margin:0 0 10px;">SMTP ทำงานปกติ!</h2>'
                . '<p style="color:#50575e;line-height:1.6;">อีเมลนี้ถูกส่งจากระบบ <strong>' . esc_html($site_name) . '</strong><br>เพื่อทดสอบการตั้งค่า SMTP</p>'
                . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
                . '<p style="color:#999;font-size:12px;">ส่งเมื่อ: ' . current_time('d/m/Y H:i:s') . '</p>'
                . '</div></body></html>';

            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $sent = wp_mail( $to, $subject, $body, $headers );

            remove_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
            remove_action( 'wp_mail_failed', [ $this, 'capture_mail_error' ] );

            if ( $sent ) {
                wp_send_json_success( [ 'message' => 'ส่งอีเมลทดสอบไปที่ ' . $to . ' สำเร็จ!' ] );
            } else {
                $err = ! empty( $this->mail_errors ) ? implode( '; ', $this->mail_errors ) : 'ไม่ทราบสาเหตุ';
                wp_send_json_error( [ 'message' => 'ส่งไม่สำเร็จ: ' . $err ] );
            }
        }

        public function handle_update_status() {
            check_ajax_referer( 'isxf_entry_action_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'ไม่มีสิทธิ์' ] );
            }

            $entry_id = intval( $_POST['entry_id'] ?? 0 );
            $status = sanitize_text_field( $_POST['status'] ?? '' );
            $allowed = [ 'new', 'in_progress', 'done', 'junk' ];

            if ( ! $entry_id || ! in_array( $status, $allowed, true ) ) {
                wp_send_json_error( [ 'message' => 'ข้อมูลไม่ถูกต้อง' ] );
            }

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'isxf_form_entries',
                [ 'entry_status' => $status ],
                [ 'id' => $entry_id ],
                [ '%s' ],
                [ '%d' ]
            );

            $labels = [ 'new' => 'ใหม่', 'in_progress' => 'กำลังดำเนินการ', 'done' => 'เสร็จสิ้น', 'junk' => 'ขยะ' ];
            wp_send_json_success( [ 'message' => 'อัพเดตสถานะเป็น "' . $labels[$status] . '" แล้ว', 'status' => $status ] );
        }

        public function handle_update_note() {
            check_ajax_referer( 'isxf_entry_action_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'ไม่มีสิทธิ์' ] );
            }

            $entry_id = intval( $_POST['entry_id'] ?? 0 );
            $note = sanitize_textarea_field( $_POST['note'] ?? '' );

            if ( ! $entry_id ) {
                wp_send_json_error( [ 'message' => 'ข้อมูลไม่ถูกต้อง' ] );
            }

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'isxf_form_entries',
                [ 'admin_note' => $note ],
                [ 'id' => $entry_id ],
                [ '%s' ],
                [ '%d' ]
            );

            wp_send_json_success( [ 'message' => 'บันทึกโน้ตเรียบร้อย' ] );
        }
    }
}