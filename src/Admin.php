<?php

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

        private $captcha_service_opt  = 'isxf_captcha_service';
        private $captcha_required_opt = 'isxf_captcha_required';
        private $trusted_proxies_opt  = 'isxf_trusted_proxies';
        private $recaptcha_site_opt   = 'isxf_recaptcha_site_key';
        private $recaptcha_secret_opt = 'isxf_recaptcha_secret_key';
        private $turnstile_site_opt   = 'isxf_turnstile_site_key';
        private $turnstile_secret_opt = 'isxf_turnstile_secret_key';
        
        private $smtp_enable = 'isxf_smtp_enable';
        private $smtp_host   = 'isxf_smtp_host';
        private $smtp_port   = 'isxf_smtp_port';
        private $smtp_user   = 'isxf_smtp_user';
        private $smtp_pass   = 'isxf_smtp_pass';
        private $smtp_secure = 'isxf_smtp_secure';
        private $smtp_from_e = 'isxf_smtp_from_email';
        private $smtp_from_n = 'isxf_smtp_from_name';

        private $admin_notify_enable = 'isxf_admin_notify_enable';
        private $admin_notify_email  = 'isxf_admin_notify_email';
        private $smtp_ssl_verify_off = 'isxf_smtp_disable_ssl_verify';

        // OAuth2 (XOAUTH2) settings
        private $smtp_auth_method   = 'isxf_smtp_auth_method';
        private $oauth_client_id    = 'isxf_smtp_oauth_client_id';
        private $oauth_client_secret = 'isxf_smtp_oauth_client_secret';
        private $oauth_tenant       = 'isxf_smtp_oauth_tenant';



        public function __construct() {
            add_action( 'init', [ $this, 'register_form_cpt' ] );
            add_action( 'admin_menu', [ $this, 'setup_admin_menus' ] );
            add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] ); 
            add_action( 'save_post_isxf_form', [ $this, 'save_form_fields' ] );
            add_action( 'admin_init', [ $this, 'register_global_settings' ] );
            add_filter( 'manage_isxf_form_posts_columns', [ $this, 'custom_form_columns' ] );
            add_action( 'manage_isxf_form_posts_custom_column', [ $this, 'fill_form_columns' ], 10, 2 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
            add_action( 'admin_init', [ $this, 'maybe_migrate_smtp_password' ], 5 );
            add_action( 'admin_notices', [ $this, 'captcha_missing_notice' ] );
            add_action( 'admin_notices', [ $this, 'legacy_deprecation_notice' ] );
            add_action( 'admin_init', [ $this, 'maybe_dismiss_legacy_deprecation_notice' ] );
        }

        public function enqueue_admin_scripts( $hook ) {
            global $post_type, $post;

            // Load on isxf_form post type screens, settings page, and docs page
            $is_isxf_screen = ( $post_type === 'isxf_form' ) || ( $hook === 'isxf_form_page_isxf-global-settings' ) || ( $hook === 'isxf_form_page_isxf-docs' );
            if ( ! $is_isxf_screen ) return;

            wp_enqueue_script( 'jquery-ui-sortable' );

            // Color picker for the submit-button color field in the builder.
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            wp_enqueue_style(
                'isxf-admin-css',
                plugin_dir_url( __DIR__ ) . 'assets/css/isxf-admin.css',
                [],
                ISXF_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'isxf-admin-js',
                plugin_dir_url( __DIR__ ) . 'assets/js/isxf-admin.js',
                [ 'jquery', 'jquery-ui-sortable' ],
                ISXF_PLUGIN_VERSION,
                true
            );

            // Localize PHP data for the JS
            $fields = ( $post && $post->ID ) ? get_post_meta( $post->ID, '_isxf_form_fields', true ) : [];
            if ( ! is_array( $fields ) ) $fields = [];

            wp_localize_script( 'isxf-admin-js', 'isxf_admin_env', [
                'ajax_url'          => admin_url( 'admin-ajax.php' ),
                'test_email_nonce'  => wp_create_nonce( 'isxf_test_email_nonce' ),
                'email_tools_nonce' => wp_create_nonce( 'isxf_email_tools_nonce' ),
                'field_count'       => count( $fields ),
                'i18n'             => [
                    'sending'            => __( '⏳ Sending...', 'insightx-form' ),
                    'conn_error'         => __( '❌ Connection error', 'insightx-form' ),
                    'send_test_email'    => __( '📨 Send Test Email', 'insightx-form' ),
                    'loading_preview'    => __( '⏳ Loading preview...', 'insightx-form' ),
                    'preview_failed'     => __( '❌ Failed to load the preview', 'insightx-form' ),
                    'confirm_overwrite'  => __( 'The current custom email body is not empty. Overwrite it with the content of this template?', 'insightx-form' ),
                    'copied_to_editor'   => __( '✅ Template content copied into the custom editor — remember to save the form.', 'insightx-form' ),
                    'options_example'    => __( 'e.g. Red,Green,Blue', 'insightx-form' ),
                    'options_none'       => __( 'No options used', 'insightx-form' ),
                    'placeholder_sample' => __( 'Type sample text', 'insightx-form' ),
                    'placeholder_none'   => __( 'No placeholder used', 'insightx-form' ),
                    'drag_title'         => __( 'Click and hold to drag to reorder', 'insightx-form' ),
                    'remove_title'       => __( 'Delete this field', 'insightx-form' ),
                    'type_text'          => __( 'Text (short text)', 'insightx-form' ),
                    'type_textarea'      => __( 'Textarea (long text)', 'insightx-form' ),
                    'type_number'        => __( 'Number', 'insightx-form' ),
                    'type_date'          => __( 'Date (standard calendar)', 'insightx-form' ),
                    'type_check_in'      => __( 'Check-in Date', 'insightx-form' ),
                    'type_check_out'     => __( 'Check-out Date', 'insightx-form' ),
                    'type_radio'         => __( 'Radio (choose 1 option)', 'insightx-form' ),
                    'type_checkbox'      => __( 'Checkbox (choose multiple)', 'insightx-form' ),
                    'type_heading'       => __( 'Heading (form heading)', 'insightx-form' ),
                ],
            ]);
        }

        public function register_form_cpt() {
            register_post_type( 'isxf_form', [
                'labels' => [
                    'name' => __( 'Forms', 'insightx-form' ),
                    'singular_name' => __( 'Form', 'insightx-form' ),
                    'add_new' => __( 'Create New Form', 'insightx-form' ),
                    'edit_item' => __( 'Edit Form', 'insightx-form' ),
                    'all_items' => __( 'All Forms', 'insightx-form' )
                ],
                'public' => false, 
                'show_ui' => true, 
                'show_in_menu' => true,
                'menu_icon' => 'dashicons-feedback', 
                'supports' => [ 'title' ], 
                'menu_position' => 25.5
            ]);
        }

        public function custom_form_columns( $columns ) {
            $columns['shortcode'] = __( 'Shortcode (copy and paste)', 'insightx-form' );
            return $columns;
        }

        public function fill_form_columns( $column, $post_id ) {
            if ( $column === 'shortcode' ) {
                echo '<input type="text" readonly="readonly" value="[isxf_form id=&quot;' . $post_id . '&quot;]" style="width: 100%; max-width: 250px; background: #f0f0f1; border-color: #8c8f94; cursor: text;" onclick="this.select();">';
            }
        }

        public function setup_admin_menus() {
            add_submenu_page( 'edit.php?post_type=isxf_form', __( 'Settings', 'insightx-form' ), __( '⚙️ Settings', 'insightx-form' ), 'manage_options', 'isxf-global-settings', [ $this, 'render_settings_page' ] );
            add_submenu_page( 'edit.php?post_type=isxf_form', __( 'Documentation', 'insightx-form' ), __( '📖 Documentation', 'insightx-form' ), 'manage_options', 'isxf-docs', [ $this, 'render_docs_page' ] );
        }

        public function register_global_settings() {
            $options = [
                $this->captcha_service_opt, $this->captcha_required_opt, $this->recaptcha_site_opt, $this->recaptcha_secret_opt,
                $this->turnstile_site_opt, $this->turnstile_secret_opt, $this->trusted_proxies_opt,
                $this->smtp_enable, $this->smtp_host, $this->smtp_port, $this->smtp_user, $this->smtp_pass, $this->smtp_secure, $this->smtp_from_e, $this->smtp_from_n,
                $this->admin_notify_enable, $this->admin_notify_email,
                $this->smtp_ssl_verify_off,
                $this->smtp_auth_method, $this->oauth_client_id, $this->oauth_client_secret, $this->oauth_tenant
            ];
            // Checkbox options submit nothing when unchecked → the sanitize
            // callback receives null. They get a dedicated null-safe callback
            // (KNOWN-ISSUES A1); stored values stay 'yes' (checked) / '' (unchecked).
            $checkbox_options = [ $this->smtp_enable, $this->captcha_required_opt, $this->admin_notify_enable, $this->smtp_ssl_verify_off ];
            foreach ( $options as $opt ) {
                if ( $opt === $this->smtp_pass ) {
                    $callback = [ $this, 'sanitize_smtp_password' ];
                } elseif ( $opt === $this->oauth_client_secret ) {
                    $callback = [ $this, 'sanitize_oauth_secret' ];
                } elseif ( $opt === $this->trusted_proxies_opt ) {
                    $callback = 'sanitize_textarea_field'; // เก็บขึ้นบรรทัดใหม่ไว้ (IP/CIDR บรรทัดละ 1 ค่า)
                } elseif ( in_array( $opt, $checkbox_options, true ) ) {
                    $callback = [ $this, 'sanitize_checkbox' ];
                } else {
                    $callback = 'sanitize_text_field';
                }
                register_setting( 'isxf_global_group', $opt, [ 'sanitize_callback' => $callback ] );
            }
        }

        /**
         * Null-safe sanitize callback for checkbox options: an unchecked box
         * submits nothing (null), a checked one submits 'yes'. Stored values
         * are identical to the old sanitize_text_field behavior ('yes' / '').
         *
         * @param mixed $value Raw option value ('yes', '', or null).
         * @return string 'yes' or ''.
         */
        public function sanitize_checkbox( $value ) {
            return $value === 'yes' ? 'yes' : '';
        }

        public function render_settings_page() {
            $service = get_option($this->captcha_service_opt, 'google');

            // CAPTCHA warning
            $captcha_configured = false;
            if ( $service === 'google' ) {
                $captcha_configured = ! empty( get_option( $this->recaptcha_site_opt ) ) && ! empty( get_option( $this->recaptcha_secret_opt ) );
            } else {
                $captcha_configured = ! empty( get_option( $this->turnstile_site_opt ) ) && ! empty( get_option( $this->turnstile_secret_opt ) );
            }
            $oauth_notice    = isset( $_GET['isxf_oauth'] ) ? sanitize_key( $_GET['isxf_oauth'] ) : '';
            $oauth_error_msg = isset( $_GET['msg'] ) ? esc_html( rawurldecode( wp_unslash( $_GET['msg'] ) ) ) : __( 'An error occurred', 'insightx-form' );

            $auth_method    = get_option( $this->smtp_auth_method, 'password' );
            $is_oauth       = in_array( $auth_method, [ 'oauth_google', 'oauth_microsoft' ], true );
            $oauth_provider = ( $auth_method === 'oauth_microsoft' ) ? 'microsoft' : 'google';

            Template::render( 'admin/settings-page', [
                'opt' => [
                    'admin_notify_enable' => $this->admin_notify_enable,
                    'admin_notify_email'  => $this->admin_notify_email,
                    'captcha_service'     => $this->captcha_service_opt,
                    'recaptcha_site'      => $this->recaptcha_site_opt,
                    'recaptcha_secret'    => $this->recaptcha_secret_opt,
                    'turnstile_site'      => $this->turnstile_site_opt,
                    'turnstile_secret'    => $this->turnstile_secret_opt,
                    'captcha_required'    => $this->captcha_required_opt,
                    'trusted_proxies'     => $this->trusted_proxies_opt,
                    'smtp_enable'         => $this->smtp_enable,
                    'smtp_auth_method'    => $this->smtp_auth_method,
                    'smtp_host'           => $this->smtp_host,
                    'smtp_port'           => $this->smtp_port,
                    'smtp_user'           => $this->smtp_user,
                    'smtp_pass'           => $this->smtp_pass,
                    'oauth_client_id'     => $this->oauth_client_id,
                    'oauth_client_secret' => $this->oauth_client_secret,
                    'oauth_tenant'        => $this->oauth_tenant,
                    'smtp_ssl_verify_off' => $this->smtp_ssl_verify_off,
                ],
                'v' => [
                    'admin_notify_enable' => get_option($this->admin_notify_enable),
                    'admin_notify_email'  => get_option($this->admin_notify_email),
                    'recaptcha_site'      => get_option($this->recaptcha_site_opt),
                    'recaptcha_secret'    => get_option($this->recaptcha_secret_opt),
                    'turnstile_site'      => get_option($this->turnstile_site_opt),
                    'turnstile_secret'    => get_option($this->turnstile_secret_opt),
                    'captcha_required'    => get_option($this->captcha_required_opt, 'yes'),
                    'trusted_proxies'     => get_option($this->trusted_proxies_opt, ''),
                    'smtp_enable'         => get_option($this->smtp_enable),
                    'smtp_host'           => get_option($this->smtp_host),
                    'smtp_port'           => get_option($this->smtp_port),
                    'smtp_user'           => get_option($this->smtp_user),
                    'smtp_pass'           => get_option($this->smtp_pass),
                    'oauth_client_id'     => get_option($this->oauth_client_id),
                    'oauth_client_secret' => get_option($this->oauth_client_secret),
                    'oauth_tenant'        => get_option($this->oauth_tenant),
                    'smtp_ssl_verify_off' => get_option($this->smtp_ssl_verify_off),
                ],
                'service'            => $service,
                'captcha_configured' => $captcha_configured,
                'oauth_notice'       => $oauth_notice,
                'oauth_error_msg'    => $oauth_error_msg,
                'admin_email'        => get_option( 'admin_email' ),
                'auth_method'        => $auth_method,
                'is_oauth'           => $is_oauth,
                'redirect_uri'       => OAuth::redirect_uri(),
                'connected_email'    => OAuth::connected_email(),
                'is_connected'       => OAuth::is_connected(),
                'connect_url'        => wp_nonce_url( admin_url( 'admin-post.php?action=isxf_oauth_connect&provider=' . $oauth_provider ), 'isxf_oauth_connect' ),
                'disconnect_url'     => wp_nonce_url( admin_url( 'admin-post.php?action=isxf_oauth_disconnect' ), 'isxf_oauth_disconnect' ),
            ] );
        }

        /**
         * แจ้งเตือนค้างในหน้าแอดมินของปลั๊กอิน เมื่อเลือกบริการ CAPTCHA ไว้แต่ยังไม่ได้ตั้งค่า key
         */
        public function captcha_missing_notice() {
            if ( ! current_user_can( 'manage_options' ) ) return;

            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( ! $screen || strpos( (string) $screen->id, 'isxf' ) === false ) return;
            if ( $screen->id === 'isxf_form_page_isxf-global-settings' ) return; // หน้าตั้งค่ามี warning ของตัวเองแล้ว

            $service = get_option( $this->captcha_service_opt, 'google' );
            if ( $service === 'google' ) {
                $configured = ! empty( get_option( $this->recaptcha_site_opt ) ) && ! empty( get_option( $this->recaptcha_secret_opt ) );
            } else {
                $configured = ! empty( get_option( $this->turnstile_site_opt ) ) && ! empty( get_option( $this->turnstile_secret_opt ) );
            }
            if ( $configured ) return;

            $settings_url = admin_url( 'edit.php?post_type=isxf_form&page=isxf-global-settings' );
            echo '<div class="notice notice-warning"><p>⚠️ <strong>InsightX Form:</strong> ' . sprintf(
                /* translators: %s: URL of the plugin settings page. */
                __( 'CAPTCHA is not configured yet — form submissions may be blocked or have no bot protection. <a href="%s">Go to the settings page</a>', 'insightx-form' ),
                esc_url( $settings_url )
            ) . '</p></div>';
        }

        /**
         * Whether the v1.0 legacy-removal notice should be shown.
         *
         * Shown only to administrators, only on plugin screens, only on sites
         * that came from the legacy "acf" era (flag isxf_legacy_acf_migration_done —
         * clean installs never see it), and only until dismissed (per user meta).
         *
         * @param string|null $screen_id Screen id to check; resolved via
         *                               get_current_screen() when null.
         * @return bool
         */
        public function should_show_legacy_deprecation_notice( $screen_id = null ) {
            if ( ! current_user_can( 'manage_options' ) ) return false;
            if ( ! get_option( 'isxf_legacy_acf_migration_done' ) ) return false;
            if ( get_user_meta( get_current_user_id(), 'isxf_dismissed_legacy_deprecation_notice', true ) ) return false;

            if ( null === $screen_id ) {
                $screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                $screen_id = $screen ? (string) $screen->id : '';
            }
            return strpos( $screen_id, 'isxf' ) !== false;
        }

        /**
         * Dismissible admin notice announcing the v1.0 legacy removal.
         */
        public function legacy_deprecation_notice() {
            if ( ! $this->should_show_legacy_deprecation_notice() ) return;

            $changelog_url = 'https://github.com/Douzhebag/ISX-Form-WP-Plugin/blob/main/CHANGELOG.md';
            $dismiss_url   = wp_nonce_url( add_query_arg( 'isxf_dismiss_legacy_notice', '1' ), 'isxf_dismiss_legacy_notice' );

            echo '<div class="notice notice-warning"><p>⚠️ <strong>InsightX Form:</strong> ' . sprintf(
                /* translators: %s: URL of the plugin changelog. */
                __( 'This site still relies on legacy compatibility layers (the [advanced_form] shortcode alias, the acf→isxf auto-migration, and the legacy ENC: encryption format). These will be removed in v1.0 — upgrading to v1.0 requires passing through v0.8.x first. <a href="%s">See the changelog</a>', 'insightx-form' ),
                esc_url( $changelog_url )
            ) . ' <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'insightx-form' ) . '</a></p></div>';
        }

        /**
         * Handle the one-time, per-user dismissal of the legacy deprecation notice.
         */
        public function maybe_dismiss_legacy_deprecation_notice() {
            if ( empty( $_GET['isxf_dismiss_legacy_notice'] ) ) return;
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'isxf_dismiss_legacy_notice' ) ) return;

            update_user_meta( get_current_user_id(), 'isxf_dismissed_legacy_deprecation_notice', 1 );

            wp_safe_redirect( remove_query_arg( [ 'isxf_dismiss_legacy_notice', '_wpnonce' ] ) );
            exit;
        }

        public function register_meta_boxes() {
            add_meta_box( 'isxf_builder_meta', __( '📝 Form Builder', 'insightx-form' ), [ $this, 'render_builder_meta_box' ], 'isxf_form', 'normal', 'high' );
        }

        public function render_builder_meta_box( $post ) {
            wp_nonce_field( 'save_isxf_form', 'isxf_form_nonce' );
            
            $fields = get_post_meta( $post->ID, '_isxf_form_fields', true );
            $email_type = get_post_meta( $post->ID, '_isxf_form_email_type', true );
            $custom_subject = get_post_meta( $post->ID, '_isxf_form_email_subject', true );
            $custom_body = get_post_meta( $post->ID, '_isxf_form_email_body', true );
            $button_color = get_post_meta( $post->ID, '_isxf_form_button_color', true );
            if ( ! is_string( $button_color ) ) $button_color = '';
            $btn_text_color  = get_post_meta( $post->ID, '_isxf_form_button_text_color', true );
            $btn_hover_color = get_post_meta( $post->ID, '_isxf_form_button_hover_color', true );
            $btn_radius      = get_post_meta( $post->ID, '_isxf_form_button_radius', true );
            $btn_font_size   = get_post_meta( $post->ID, '_isxf_form_button_font_size', true );
            $btn_custom_css  = get_post_meta( $post->ID, '_isxf_form_button_css', true );
            
            if ( ! is_array($fields) || empty($fields) ) {
                $fields = [[ 'name' => 'your_name', 'label' => __( 'Your Name', 'insightx-form' ), 'type' => 'text', 'width' => '100', 'options' => '', 'required' => 'yes' ]];
            }
            if ( empty($email_type) ) $email_type = 'booking';
            if ( empty($custom_subject) ) $custom_subject = __( 'Thank you for contacting us - {site_name}', 'insightx-form' );
            if ( empty($custom_body) ) $custom_body = __( "Hello,\n\nWe have received your submission. Details:\n\n{all_fields}\n\nThank you,\n{site_name}", 'insightx-form' );
            Template::render( 'admin/builder-meta-box', [
                'fields'         => $fields,
                'email_type'     => $email_type,
                'custom_subject' => $custom_subject,
                'custom_body'    => $custom_body,
                'button_color'   => $button_color,
                'btn_text_color'  => is_string( $btn_text_color ) ? $btn_text_color : '',
                'btn_hover_color' => is_string( $btn_hover_color ) ? $btn_hover_color : '',
                'btn_radius'      => $btn_radius,
                'btn_font_size'   => $btn_font_size,
                'btn_custom_css'  => is_string( $btn_custom_css ) ? $btn_custom_css : '',
            ] );
        }

        public function sanitize_smtp_password( $value ) {
            $value = wp_strip_all_tags( trim( $value ) );
            if ( empty( $value ) ) {
                // ถ้าเว้นว่าง ให้ใช้ค่าเดิม (ไม่เปลี่ยน password)
                return get_option( $this->smtp_pass, '' );
            }
            $encrypted = Crypto::encrypt( $value );
            if ( is_wp_error( $encrypted ) || empty( $encrypted ) ) {
                // เข้ารหัสไม่สำเร็จ — ห้ามบันทึก plaintext เด็ดขาด ให้คงค่าเดิมไว้
                add_settings_error( $this->smtp_pass, 'isxf_encrypt_failed', __( '❌ Failed to encrypt the SMTP password — the new value was not saved (the previous value is kept). Please check your server\'s OpenSSL.', 'insightx-form' ) );
                return get_option( $this->smtp_pass, '' );
            }
            return $encrypted;
        }

        public function sanitize_oauth_secret( $value ) {
            $value = wp_strip_all_tags( trim( $value ) );
            if ( empty( $value ) ) {
                // เว้นว่าง = ใช้ค่าเดิม (ไม่เปลี่ยน Client Secret)
                return get_option( $this->oauth_client_secret, '' );
            }
            $encrypted = Crypto::encrypt( $value );
            if ( is_wp_error( $encrypted ) || empty( $encrypted ) ) {
                // เข้ารหัสไม่สำเร็จ — ห้ามบันทึก plaintext เด็ดขาด ให้คงค่าเดิมไว้
                add_settings_error( $this->oauth_client_secret, 'isxf_encrypt_failed', __( '❌ Failed to encrypt the Client Secret — the new value was not saved (the previous value is kept). Please check your server\'s OpenSSL.', 'insightx-form' ) );
                return get_option( $this->oauth_client_secret, '' );
            }
            return $encrypted;
        }

        /**
         * Auto-migrate existing plain text password to encrypted format.
         */
        public function maybe_migrate_smtp_password() {
            $stored = get_option( $this->smtp_pass, '' );
            if ( empty( $stored ) ) return;

            // ตรวจว่าเป็น encrypted แล้วหรือยัง
            if ( Crypto::is_encrypted( $stored ) ) return;

            // ยังเป็น plain text → encrypt ด้วย format ใหม่
            $encrypted = Crypto::encrypt( $stored );
            if ( is_wp_error( $encrypted ) || empty( $encrypted ) ) {
                // เข้ารหัสไม่สำเร็จ — ปล่อยค่าเดิมไว้ (decrypt() อ่าน plaintext ได้) แล้วลองใหม่รอบหน้า
                isxf_log_error( 'SMTP password migration skipped: encryption failed' );
                return;
            }
            update_option( $this->smtp_pass, $encrypted );
        }

        public function save_form_fields( $post_id ) {
            if ( ! isset( $_POST['isxf_form_nonce'] ) || ! wp_verify_nonce( $_POST['isxf_form_nonce'], 'save_isxf_form' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if ( isset( $_POST['isxf_form_email_type'] ) ) {
                update_post_meta( $post_id, '_isxf_form_email_type', sanitize_text_field( $_POST['isxf_form_email_type'] ) );
            }

            // Save custom email template fields
            if ( isset( $_POST['isxf_form_email_subject'] ) ) {
                update_post_meta( $post_id, '_isxf_form_email_subject', sanitize_text_field( $_POST['isxf_form_email_subject'] ) );
            }
            if ( isset( $_POST['isxf_form_email_body'] ) ) {
                update_post_meta( $post_id, '_isxf_form_email_body', wp_kses_post( $_POST['isxf_form_email_body'] ) );
            }

            // สีปุ่ม submit (ว่าง = ใช้สี default ของธีมปลั๊กอิน)
            if ( isset( $_POST['isxf_form_button_color'] ) ) {
                $color = sanitize_hex_color( wp_unslash( $_POST['isxf_form_button_color'] ) );
                update_post_meta( $post_id, '_isxf_form_button_color', $color ? $color : '' );
            }

            // Button style extras: text/hover colors, radius, font size, advanced CSS.
            foreach ( [ 'isxf_form_button_text_color' => '_isxf_form_button_text_color', 'isxf_form_button_hover_color' => '_isxf_form_button_hover_color' ] as $field => $meta ) {
                if ( isset( $_POST[ $field ] ) ) {
                    $color = sanitize_hex_color( wp_unslash( $_POST[ $field ] ) );
                    update_post_meta( $post_id, $meta, $color ? $color : '' );
                }
            }
            foreach ( [ 'isxf_form_button_radius' => '_isxf_form_button_radius', 'isxf_form_button_font_size' => '_isxf_form_button_font_size' ] as $field => $meta ) {
                if ( isset( $_POST[ $field ] ) ) {
                    $v = absint( $_POST[ $field ] );
                    update_post_meta( $post_id, $meta, ( $v >= 1 && $v <= 100 ) ? $v : '' );
                }
            }
            if ( isset( $_POST['isxf_form_button_css'] ) ) {
                update_post_meta( $post_id, '_isxf_form_button_css', \ISXF\Frontend::sanitize_custom_css( wp_unslash( $_POST['isxf_form_button_css'] ) ) );
            }

            if ( isset( $_POST['isxf_fields'] ) && is_array( $_POST['isxf_fields'] ) ) {
                $sanitized_fields = [];
                foreach ( $_POST['isxf_fields'] as $field ) {
                    if ( empty( $field['label'] ) ) continue;
                    $sanitized_fields[] = [
                        'label'    => sanitize_text_field( $field['label'] ),
                        'name'     => sanitize_key( $field['name'] ),
                        'type'     => sanitize_text_field( $field['type'] ),
                        'placeholder' => isset($field['placeholder']) ? sanitize_text_field( $field['placeholder'] ) : '',
                        'options'  => isset($field['options']) ? sanitize_text_field( $field['options'] ) : '',
                        'width'    => sanitize_text_field( $field['width'] ),
                        'required' => sanitize_text_field( $field['required'] )
                    ];
                }
                update_post_meta( $post_id, '_isxf_form_fields', $sanitized_fields );
            }
        }

        public function render_docs_page() {
            Template::render( 'admin/docs-page' );
        }
}