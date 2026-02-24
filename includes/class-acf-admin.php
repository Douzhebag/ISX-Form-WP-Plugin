<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'ACF_Admin' ) ) {
    class ACF_Admin {

        private $captcha_service_opt  = 'acf_captcha_service'; 
        private $recaptcha_site_opt   = 'acf_recaptcha_site_key';
        private $recaptcha_secret_opt = 'acf_recaptcha_secret_key';
        private $turnstile_site_opt   = 'acf_turnstile_site_key';
        private $turnstile_secret_opt = 'acf_turnstile_secret_key';
        
        private $smtp_enable = 'acf_smtp_enable';
        private $smtp_host   = 'acf_smtp_host';
        private $smtp_port   = 'acf_smtp_port';
        private $smtp_user   = 'acf_smtp_user';
        private $smtp_pass   = 'acf_smtp_pass';
        private $smtp_secure = 'acf_smtp_secure';
        private $smtp_from_e = 'acf_smtp_from_email';
        private $smtp_from_n = 'acf_smtp_from_name';

        private $admin_notify_enable = 'acf_admin_notify_enable';
        private $admin_notify_email  = 'acf_admin_notify_email';

        public function __construct() {
            add_action( 'init', [ $this, 'register_form_cpt' ] );
            add_action( 'admin_menu', [ $this, 'setup_admin_menus' ] );
            add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] ); 
            add_action( 'save_post_acf_form', [ $this, 'save_form_fields' ] );
            add_action( 'admin_init', [ $this, 'register_global_settings' ] );
            add_filter( 'manage_acf_form_posts_columns', [ $this, 'custom_form_columns' ] );
            add_action( 'manage_acf_form_posts_custom_column', [ $this, 'fill_form_columns' ], 10, 2 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        }

        public function enqueue_admin_scripts( $hook ) {
            global $post_type;
            if ( $post_type === 'acf_form' ) {
                wp_enqueue_script( 'jquery-ui-sortable' );
            }
        }

        public function register_form_cpt() {
            register_post_type( 'acf_form', [
                'labels' => [
                    'name' => 'แบบฟอร์ม (Forms)', 
                    'singular_name' => 'แบบฟอร์ม',
                    'add_new' => 'สร้างฟอร์มใหม่', 
                    'edit_item' => 'แก้ไขฟอร์ม', 
                    'all_items' => 'ฟอร์มทั้งหมด'
                ],
                'public' => false, 
                'show_ui' => true, 
                'show_in_menu' => true,
                'menu_icon' => 'dashicons-feedback', 
                'supports' => [ 'title' ], 
                'menu_position' => 25
            ]);
        }

        public function custom_form_columns( $columns ) {
            $columns['shortcode'] = 'Shortcode (คัดลอกไปวาง)';
            return $columns;
        }

        public function fill_form_columns( $column, $post_id ) {
            if ( $column === 'shortcode' ) {
                echo '<input type="text" readonly="readonly" value="[advanced_form id=&quot;' . $post_id . '&quot;]" style="width: 100%; max-width: 250px; background: #f0f0f1; border-color: #8c8f94; cursor: text;" onclick="this.select();">';
            }
        }

        public function setup_admin_menus() {
            add_submenu_page( 'edit.php?post_type=acf_form', 'ตั้งค่าระบบ', '⚙️ ตั้งค่าระบบ', 'manage_options', 'acf-global-settings', [ $this, 'render_settings_page' ] );
            add_submenu_page( 'edit.php?post_type=acf_form', 'คู่มือการใช้งาน', '📖 คู่มือการใช้งาน', 'manage_options', 'acf-docs', [ $this, 'render_docs_page' ] );
        }

        public function register_global_settings() {
            $options = [ 
                $this->captcha_service_opt, $this->recaptcha_site_opt, $this->recaptcha_secret_opt, 
                $this->turnstile_site_opt, $this->turnstile_secret_opt, 
                $this->smtp_enable, $this->smtp_host, $this->smtp_port, $this->smtp_user, $this->smtp_pass, $this->smtp_secure, $this->smtp_from_e, $this->smtp_from_n,
                $this->admin_notify_enable, $this->admin_notify_email 
            ];
            foreach ( $options as $opt ) {
                $callback = ( $opt === $this->smtp_pass ) ? [ $this, 'sanitize_smtp_password' ] : 'sanitize_text_field';
                register_setting( 'acf_global_group', $opt, [ 'sanitize_callback' => $callback ] );
            }
        }

        public function render_settings_page() {
            $service = get_option($this->captcha_service_opt, 'google');
            ?>
            <div class="wrap">
                <h1>⚙️ ตั้งค่าระบบส่วนกลาง (Global Settings)</h1>
                <form method="post" action="options.php">
                    <?php settings_fields( 'acf_global_group' ); ?>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                        
                        <div style="flex: 1; min-width: 400px; display: flex; flex-direction: column; gap: 20px;">
                            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px; border-left: 4px solid #2271b1;">
                                <h3>🔔 การแจ้งเตือนผู้ดูแลระบบ (Admin Notification)</h3>
                                <label style="display:block; margin-bottom:10px;">
                                    <input type="checkbox" name="<?php echo $this->admin_notify_enable; ?>" value="yes" <?php checked(get_option($this->admin_notify_enable), 'yes'); ?>> 
                                    <strong>เปิดใช้งานการส่งอีเมลแจ้งเตือน Admin</strong>
                                </label>
                                <p class="description" style="margin-bottom:5px;">อีเมลผู้รับ (หากเว้นว่าง จะส่งไปที่: <code><?php echo get_option('admin_email'); ?></code>)</p>
                                <input type="text" name="<?php echo $this->admin_notify_email; ?>" value="<?php echo esc_attr(get_option($this->admin_notify_email)); ?>" class="regular-text" placeholder="เช่น admin@example.com">
                            </div>

                            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px;">
                                <h3>🛡️ ตั้งค่าระบบความปลอดภัย (Captcha)</h3>
                                <select name="<?php echo $this->captcha_service_opt; ?>" id="captcha_select" style="width: 100%; margin-bottom: 20px;">
                                    <option value="google" <?php selected($service, 'google'); ?>>Google reCAPTCHA v3</option>
                                    <option value="cloudflare" <?php selected($service, 'cloudflare'); ?>>Cloudflare Turnstile</option>
                                </select>
                                <div id="settings_google" style="<?php echo $service !== 'google' ? 'display:none;' : ''; ?>">
                                    <table class="form-table">
                                        <tr><th>Site Key</th><td><input type="text" name="<?php echo $this->recaptcha_site_opt; ?>" value="<?php echo esc_attr(get_option($this->recaptcha_site_opt)); ?>" class="regular-text"></td></tr>
                                        <tr><th>Secret Key</th><td><input type="password" name="<?php echo $this->recaptcha_secret_opt; ?>" value="<?php echo esc_attr(get_option($this->recaptcha_secret_opt)); ?>" class="regular-text"></td></tr>
                                    </table>
                                </div>
                                <div id="settings_cloudflare" style="<?php echo $service !== 'cloudflare' ? 'display:none;' : ''; ?>">
                                    <table class="form-table">
                                        <tr><th>Site Key</th><td><input type="text" name="<?php echo $this->turnstile_site_opt; ?>" value="<?php echo esc_attr(get_option($this->turnstile_site_opt)); ?>" class="regular-text"></td></tr>
                                        <tr><th>Secret Key</th><td><input type="password" name="<?php echo $this->turnstile_secret_opt; ?>" value="<?php echo esc_attr(get_option($this->turnstile_secret_opt)); ?>" class="regular-text"></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px;">
                            <h3>📧 ตั้งค่าระบบส่งอีเมล (SMTP)</h3>
                            <label><input type="checkbox" name="<?php echo $this->smtp_enable; ?>" value="yes" <?php checked(get_option($this->smtp_enable), 'yes'); ?>> เปิดใช้งาน SMTP</label>
                            
                            <table class="form-table">
                                <tr><th>Host</th><td><input type="text" name="<?php echo $this->smtp_host; ?>" value="<?php echo esc_attr(get_option($this->smtp_host)); ?>" class="regular-text" placeholder="smtp.gmail.com"></td></tr>
                                <tr><th>Port</th><td><input type="number" name="<?php echo $this->smtp_port; ?>" value="<?php echo esc_attr(get_option($this->smtp_port)); ?>" class="small-text" placeholder="587"></td></tr>
                                <tr><th>Username</th><td><input type="text" name="<?php echo $this->smtp_user; ?>" value="<?php echo esc_attr(get_option($this->smtp_user)); ?>" class="regular-text" placeholder="your-email@gmail.com"></td></tr>
                                <tr><th>Password</th><td><input type="password" name="<?php echo $this->smtp_pass; ?>" value="<?php echo esc_attr(get_option($this->smtp_pass)); ?>" class="regular-text" placeholder="รหัสผ่านแอป 16 หลัก"></td></tr>
                            </table>

                            <div style="background: #f0f7ff; border: 1px solid #cce5ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                                <strong style="color: #004085; display:block; margin-bottom:5px;">💡 วิธีขอรหัสผ่านแอป (App Password) สำหรับ Gmail:</strong>
                                <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #333; line-height: 1.6;">
                                    <li>ไปที่ <a href="https://myaccount.google.com/security" target="_blank" style="text-decoration:none;">Google Account > Security</a> (ความปลอดภัย)</li>
                                    <li>เปิดใช้งาน <strong>2-Step Verification</strong> (การยืนยันแบบ 2 ขั้นตอน)</li>
                                    <li>ค้นหาคำว่า <strong>"App passwords"</strong> (รหัสผ่านสำหรับแอป)</li>
                                    <li>ตั้งชื่อ (เช่น "Website SMTP") แล้วกด Create</li>
                                    <li>คัดลอกรหัส 16 หลักมาใส่ช่อง <strong>Password</strong> ด้านล่าง (ไม่ต้องเว้นวรรค)</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <?php submit_button('บันทึกการตั้งค่าทั้งหมด'); ?>
                </form>

                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px; margin-top: 20px; border-left: 4px solid #dba617;">
                    <h3 style="margin-top:0;">📨 ทดสอบส่งอีเมล (SMTP Test)</h3>
                    <p style="color:#50575e; margin-bottom:15px;">ทดสอบว่าการตั้งค่า SMTP ถูกต้องหรือไม่ โดยส่งอีเมลทดสอบไปยังอีเมลที่ระบุ</p>
                    <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#1d2327;">อีเมลปลายทาง:</label>
                            <input type="email" id="acf-test-email-to" value="<?php echo esc_attr( get_option('admin_email') ); ?>" class="regular-text" style="width:100%;" placeholder="your@email.com">
                        </div>
                        <button type="button" id="acf-test-email-btn" class="button button-primary" style="height:36px; min-width:160px;">
                            📨 ส่งอีเมลทดสอบ
                        </button>
                    </div>
                    <div id="acf-test-email-result" style="margin-top:12px; display:none; padding:12px 16px; border-radius:4px; font-size:13px; line-height:1.5; transition: all 0.3s ease;"></div>
                </div>
            </div>
            <script>
                document.getElementById('captcha_select').onchange = function() {
                    document.getElementById('settings_google').style.display = (this.value === 'google') ? 'block' : 'none';
                    document.getElementById('settings_cloudflare').style.display = (this.value === 'cloudflare') ? 'block' : 'none';
                };

                (function(){
                    var btn = document.getElementById('acf-test-email-btn');
                    var input = document.getElementById('acf-test-email-to');
                    var result = document.getElementById('acf-test-email-result');
                    var nonce = '<?php echo wp_create_nonce("acf_test_email_nonce"); ?>';

                    btn.addEventListener('click', function() {
                        var email = input.value.trim();
                        if (!email) { input.focus(); return; }

                        btn.disabled = true;
                        btn.textContent = '⏳ กำลังส่ง...';
                        result.style.display = 'none';

                        var fd = new FormData();
                        fd.append('action', 'acf_send_test_email');
                        fd.append('nonce', nonce);
                        fd.append('test_email', email);

                        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                result.style.display = 'block';
                                if (data.success) {
                                    result.style.background = '#edfaef';
                                    result.style.border = '1px solid #46b450';
                                    result.style.color = '#2e7d32';
                                    result.innerHTML = '✅ ' + data.data.message;
                                } else {
                                    result.style.background = '#fef0f0';
                                    result.style.border = '1px solid #dc3232';
                                    result.style.color = '#a00';
                                    result.innerHTML = '❌ ' + data.data.message;
                                }
                            })
                            .catch(function() {
                                result.style.display = 'block';
                                result.style.background = '#fef0f0';
                                result.style.border = '1px solid #dc3232';
                                result.style.color = '#a00';
                                result.innerHTML = '❌ เกิดข้อผิดพลาดในการเชื่อมต่อ';
                            })
                            .finally(function() {
                                btn.disabled = false;
                                btn.textContent = '📨 ส่งอีเมลทดสอบ';
                            });
                    });
                })();
            </script>
            <?php
        }

        public function register_meta_boxes() {
            add_meta_box( 'acf_builder_meta', '📝 จัดการโครงสร้างแบบฟอร์ม', [ $this, 'render_builder_meta_box' ], 'acf_form', 'normal', 'high' );
        }

        public function render_builder_meta_box( $post ) {
            wp_nonce_field( 'save_acf_form', 'acf_form_nonce' );
            
            $fields = get_post_meta( $post->ID, '_acf_form_fields', true );
            $email_type = get_post_meta( $post->ID, '_acf_form_email_type', true );
            
            if ( ! is_array($fields) || empty($fields) ) {
                $fields = [[ 'name' => 'your_name', 'label' => 'ชื่อของคุณ', 'type' => 'text', 'width' => '100', 'options' => '', 'required' => 'yes' ]];
            }
            if ( empty($email_type) ) $email_type = 'booking';
            ?>
            <style>
                .drag-handle { cursor: grab; font-size: 20px; color: #8c8f94; text-align: center; vertical-align: middle !important; width: 3%; }
                .drag-handle:active { cursor: grabbing; }
                .ui-sortable-helper { display: table-row; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
                .ui-sortable-placeholder { visibility: visible !important; background: #f0f0f1; border: 1px dashed #b4b9be; }
                .acf-settings-panel { background: #f6f7f7; border: 1px solid #dcdcde; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
            </style>
            
            <div class="acf-settings-panel">
                <label style="font-weight:bold; font-size:14px; margin-right:10px;">✉️ รูปแบบอีเมลตอบกลับลูกค้า (Email Template):</label>
                <select name="acf_form_email_type" style="min-width: 250px;">
                    <option value="booking" <?php selected($email_type, 'booking'); ?>>ยืนยันการจองห้องพัก (Booking Confirmation)</option>
                    <option value="inquiry" <?php selected($email_type, 'inquiry'); ?>>สอบถามข้อมูลทั่วไป (General Inquiry)</option>
                </select>
                <p class="description" style="margin-top:5px;">เลือกรูปแบบเนื้อหาอีเมลที่จะส่งกลับหาลูกค้าเมื่อกดส่งฟอร์มนี้</p>
            </div>

            <div id="field-list-wrapper" style="margin-top: 10px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;">ย้าย</th>
                            <th style="width: 15%;">Label (ข้อความ)</th>
                            <th style="width: 15%;">Field Name</th>
                            <th style="width: 12%;">Type</th>
                            <th style="width: 15%;">Placeholder (เฉพาะ Text)</th>
                            <th style="width: 20%;">ตัวเลือก (คั่นด้วยลูกน้ำ ",")</th>
                            <th style="width: 8%;">Width</th>
                            <th style="width: 5%;">บังคับ?</th>
                            <th style="width: 5%; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="field-list">
                        <?php foreach ( $fields as $index => $field ) : ?>
                            <tr class="field-row">
                                <td class="drag-handle" title="คลิกค้างเพื่อเลื่อนสลับตำแหน่ง">☰</td>
                                <td><input type="text" name="acf_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" required style="width:100%;"></td>
                                <td><input type="text" name="acf_fields[<?php echo $index; ?>][name]" value="<?php echo esc_attr( $field['name'] ); ?>" style="width:100%;"></td>
                                <td>
                                    <select name="acf_fields[<?php echo $index; ?>][type]" class="field-type-select" style="width:100%;">
                                        <option value="text" <?php selected($field['type'], 'text'); ?>>Text (ข้อความสั้น)</option>
                                        <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Textarea (ข้อความยาว)</option>
                                        <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                        <option value="tel" <?php selected($field['type'], 'tel'); ?>>Telephone</option>
                                        <option value="number" <?php selected($field['type'], 'number'); ?>>Number (ตัวเลข)</option>
                                        <option value="date" <?php selected($field['type'], 'date'); ?>>Date (ปฏิทินทั่วไป)</option>
                                        <option value="check_in" <?php selected($field['type'], 'check_in'); ?>>Check-in Date (วันเช็คอิน)</option>
                                        <option value="check_out" <?php selected($field['type'], 'check_out'); ?>>Check-out Date (วันเช็คเอาท์)</option>
                                        <option value="select" <?php selected($field['type'], 'select'); ?>>Select (Dropdown)</option>
                                        <option value="radio" <?php selected($field['type'], 'radio'); ?>>Radio (เลือกได้ 1 ข้อ)</option>
                                        <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>Checkbox (เลือกได้หลายข้อ)</option>
                                        <option value="heading" <?php selected($field['type'], 'heading'); ?>>Heading (หัวข้อฟอร์ม)</option>
                                    </select>
                                </td>
                                <td><input type="text" name="acf_fields[<?php echo $index; ?>][placeholder]" class="field-placeholder-input" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" placeholder="พิมพ์ข้อความตัวอย่าง" style="width:100%;"></td>
                                <td><input type="text" name="acf_fields[<?php echo $index; ?>][options]" class="field-options-input" value="<?php echo esc_attr( $field['options'] ?? '' ); ?>" placeholder="เช่น แดง,เขียว,น้ำเงิน" style="width:100%;"></td>
                                <td><select name="acf_fields[<?php echo $index; ?>][width]" style="width:100%"><option value="100" <?php selected($field['width'] ?? '100', '100'); ?>>100%</option><option value="50" <?php selected($field['width'] ?? '100', '50'); ?>>50%</option></select></td>
                                <td><select name="acf_fields[<?php echo $index; ?>][required]" class="field-required-select"><option value="yes" <?php selected($field['required'], 'yes'); ?>>Yes</option><option value="no" <?php selected($field['required'], 'no'); ?>>No</option></select></td>
                                <td style="text-align: center;"><button type="button" class="button remove-row" style="color:red;" title="ลบฟิลด์นี้">❌</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br><button type="button" class="button button-primary" id="add-row">+ เพิ่มฟิลด์ใหม่</button>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#field-list').sortable({
                        handle: '.drag-handle', axis: 'y', cursor: 'grabbing', opacity: 0.8,
                        helper: function(e, ui) { ui.children().each(function() { $(this).width($(this).width()); }); return ui; }
                    });

                    function updateFieldState(selectElement) {
                        const tr = $(selectElement).closest('tr');
                        const type = $(selectElement).val();
                        const optionsInput = tr.find('.field-options-input');
                        const placeholderInput = tr.find('.field-placeholder-input');
                        const requiredSelect = tr.find('.field-required-select');

                        if (['select', 'radio', 'checkbox'].includes(type)) {
                            optionsInput.prop('disabled', false).prop('placeholder', 'เช่น แดง,เขียว,น้ำเงิน').css('opacity', '1');
                        } else {
                            optionsInput.prop('disabled', true).prop('placeholder', 'ไม่ใช้ตัวเลือก').val('').css('opacity', '0.3');
                        }

                        if (['text', 'textarea', 'email', 'tel', 'number', 'date', 'check_in', 'check_out'].includes(type)) {
                            placeholderInput.prop('disabled', false).prop('placeholder', 'พิมพ์ข้อความตัวอย่าง').css('opacity', '1');
                        } else {
                            placeholderInput.prop('disabled', true).prop('placeholder', 'ไม่ใช้ placeholder').val('').css('opacity', '0.3');
                        }

                        if (type === 'heading') {
                            requiredSelect.val('no').prop('disabled', true).css('opacity', '0.3');
                        } else {
                            requiredSelect.prop('disabled', false).css('opacity', '1');
                        }
                    }

                    $('.field-type-select').each(function() { updateFieldState(this); });
                    $('#field-list').on('change', '.field-type-select', function() { updateFieldState(this); });

                    let r = <?php echo count($fields); ?> + 100;
                    $('#add-row').on('click', function() {
                        const tr = `
                            <tr class="field-row">
                                <td class="drag-handle" title="คลิกค้างเพื่อเลื่อนสลับตำแหน่ง">☰</td>
                                <td><input type="text" name="acf_fields[${r}][label]" required style="width:100%;"></td>
                                <td><input type="text" name="acf_fields[${r}][name]" style="width:100%;"></td>
                                <td>
                                    <select name="acf_fields[${r}][type]" class="field-type-select" style="width:100%">
                                        <option value="text">Text (ข้อความสั้น)</option>
                                        <option value="textarea">Textarea (ข้อความยาว)</option>
                                        <option value="email">Email</option>
                                        <option value="tel">Telephone</option>
                                        <option value="number">Number (ตัวเลข)</option>
                                        <option value="date">Date (ปฏิทินทั่วไป)</option>
                                        <option value="check_in">📅 Check-in Date (วันเช็คอิน)</option>
                                        <option value="check_out">📅 Check-out Date (วันเช็คเอาท์)</option>
                                        <option value="select">Select (Dropdown)</option>
                                        <option value="radio">Radio (เลือกได้ 1 ข้อ)</option>
                                        <option value="checkbox">Checkbox (เลือกได้หลายข้อ)</option>
                                        <option value="heading">Heading (หัวข้อฟอร์ม)</option>
                                    </select>
                                </td>
                                <td><input type="text" name="acf_fields[${r}][placeholder]" class="field-placeholder-input" placeholder="พิมพ์ข้อความตัวอย่าง" style="width:100%;"></td>
                                <td><input type="text" name="acf_fields[${r}][options]" class="field-options-input" placeholder="ไม่ใช้ตัวเลือก" disabled style="width:100%; opacity:0.3;"></td>
                                <td><select name="acf_fields[${r}][width]" style="width:100%"><option value="100">100%</option><option value="50">50%</option></select></td>
                                <td><select name="acf_fields[${r}][required]" class="field-required-select"><option value="yes">Yes</option><option value="no">No</option></select></td>
                                <td style="text-align: center;"><button type="button" class="button remove-row" style="color:red;" title="ลบฟิลด์นี้">❌</button></td>
                            </tr>
                        `;
                        $('#field-list').append(tr);
                        updateFieldState($('#field-list tr:last .field-type-select'));
                        r++;
                    });

                    $('#field-list').on('click', '.remove-row', function() { $(this).closest('tr').remove(); });
                });
            </script>
            <?php
        }

        public function sanitize_smtp_password( $value ) {
            return wp_strip_all_tags( trim( $value ) );
        }

        public function save_form_fields( $post_id ) {
            if ( ! isset( $_POST['acf_form_nonce'] ) || ! wp_verify_nonce( $_POST['acf_form_nonce'], 'save_acf_form' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if ( isset( $_POST['acf_form_email_type'] ) ) {
                update_post_meta( $post_id, '_acf_form_email_type', sanitize_text_field( $_POST['acf_form_email_type'] ) );
            }

            if ( isset( $_POST['acf_fields'] ) && is_array( $_POST['acf_fields'] ) ) {
                $sanitized_fields = [];
                foreach ( $_POST['acf_fields'] as $field ) {
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
                update_post_meta( $post_id, '_acf_form_fields', $sanitized_fields );
            }
        }

        public function render_docs_page() {
            ?>
            <style>
                .acf-docs-wrap { max-width:900px; }
                .acf-docs-wrap h1 { display:flex; align-items:center; gap:10px; }
                .acf-docs-ver { font-size:12px; background:#2271b1; color:#fff; padding:2px 10px; border-radius:12px; font-weight:400; }
                .acf-docs-section { background:#fff; border:1px solid #ccd0d4; border-radius:6px; margin-bottom:12px; overflow:hidden; }
                .acf-docs-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; cursor:pointer; user-select:none; transition:background 0.2s; font-weight:600; font-size:14px; color:#1d2327; }
                .acf-docs-header:hover { background:#f6f7f7; }
                .acf-docs-arrow { transition:transform 0.3s; font-size:12px; color:#999; }
                .acf-docs-section.open .acf-docs-arrow { transform:rotate(180deg); }
                .acf-docs-body { display:none; padding:0 20px 20px; color:#50575e; font-size:13px; line-height:1.8; }
                .acf-docs-section.open .acf-docs-body { display:block; }
                .acf-docs-body h4 { color:#1d2327; margin:18px 0 8px; font-size:14px; }
                .acf-docs-body table { width:100%; border-collapse:collapse; margin:10px 0; font-size:13px; }
                .acf-docs-body th { background:#f0f0f1; text-align:left; padding:8px 12px; border:1px solid #ddd; font-weight:600; }
                .acf-docs-body td { padding:8px 12px; border:1px solid #eee; }
                .acf-docs-body code { background:#f0f0f1; padding:2px 6px; border-radius:3px; font-size:12px; }
                .acf-docs-body .step { display:flex; gap:12px; margin:8px 0; }
                .acf-docs-body .step-num { flex-shrink:0; width:24px; height:24px; background:#2271b1; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; margin-top:1px; }
                .acf-docs-body .step-text { flex:1; }
                .acf-docs-body .tip-box { background:#f0f7ff; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; font-size:12px; }
                .acf-docs-body .warn-box { background:#fff8e5; border-left:4px solid #dba617; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; font-size:12px; }
            </style>

            <div class="wrap acf-docs-wrap">
                <h1>📖 คู่มือการใช้งาน <span class="acf-docs-ver">v<?php echo ACF_PLUGIN_VERSION; ?></span></h1>
                <p style="color:#50575e; margin-bottom:20px;">InsightX Form — ระบบฟอร์มและจัดการข้อมูลลูกค้าสำหรับธุรกิจ</p>

                <div class="acf-docs-section open">
                    <div class="acf-docs-header">📝 การสร้างฟอร์ม <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text">ไปที่ <strong>แบบฟอร์ม → สร้างฟอร์มใหม่</strong></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text">ตั้ง <strong>ชื่อฟอร์ม</strong> (เช่น "แบบฟอร์มจองห้องพัก")</span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text">เพิ่มฟิลด์ตามต้องการจากตารางด้านล่าง</span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text">เลือก <strong>รูปแบบอีเมลตอบกลับ</strong> (Booking / Inquiry)</span></div>
                        <div class="step"><span class="step-num">5</span><span class="step-text">กด <strong>เผยแพร่ (Publish)</strong></span></div>

                        <h4>ประเภทฟิลด์ที่รองรับ</h4>
                        <table>
                            <tr><th>ประเภท</th><th>คำอธิบาย</th></tr>
                            <tr><td>Text</td><td>ข้อความสั้น (ชื่อ, ที่อยู่)</td></tr>
                            <tr><td>Textarea</td><td>ข้อความยาว</td></tr>
                            <tr><td>Email</td><td>อีเมล — ระบบจะส่งอีเมลยืนยันให้ลูกค้าอัตโนมัติ</td></tr>
                            <tr><td>Telephone</td><td>เบอร์โทร (จำกัดตัวเลข)</td></tr>
                            <tr><td>Number</td><td>ตัวเลข</td></tr>
                            <tr><td>Date / Check-in / Check-out</td><td>ปฏิทินเลือกวันที่ (Check-in/out เชื่อมกันอัตโนมัติ)</td></tr>
                            <tr><td>Select / Radio / Checkbox</td><td>ตัวเลือก — กรอกคั่นด้วยลูกน้ำ เช่น <code>ห้อง A, ห้อง B</code></td></tr>
                            <tr><td>Heading</td><td>หัวข้อแบ่งกลุ่ม (ไม่ส่งข้อมูล)</td></tr>
                        </table>
                        <div class="tip-box">💡 ลากไอคอน ☰ เพื่อเรียงลำดับฟิลด์ ตั้ง Width 50% เพื่อวางฟิลด์คู่กัน</div>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">🖥️ การแสดงฟอร์มบนหน้าเว็บ <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text">ไปที่ <strong>แบบฟอร์ม → ฟอร์มทั้งหมด</strong></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text">คัดลอก Shortcode เช่น <code>[advanced_form id="123"]</code></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text">วางในหน้า (Page) หรือโพสต์ (Post) ที่ต้องการ</span></div>
                        <div class="tip-box">💡 Shortcode เดียวกันสามารถวางได้หลายหน้า</div>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">📧 ตั้งค่า SMTP & ทดสอบอีเมล <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text">ไปที่ <strong>แบบฟอร์ม → ⚙️ ตั้งค่าระบบ</strong></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text">ติ๊ก <strong>เปิดใช้งาน SMTP</strong></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text">กรอก Host, Port, Username, Password</span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text">กด <strong>บันทึก</strong> แล้วเลื่อนลงกด <strong>"📨 ส่งอีเมลทดสอบ"</strong></span></div>

                        <h4>ค่าตัวอย่างสำหรับ Gmail</h4>
                        <table>
                            <tr><th>ช่อง</th><th>ค่า</th></tr>
                            <tr><td>Host</td><td><code>smtp.gmail.com</code></td></tr>
                            <tr><td>Port</td><td><code>587</code></td></tr>
                            <tr><td>Username</td><td>อีเมล Gmail ของคุณ</td></tr>
                            <tr><td>Password</td><td>App Password 16 หลัก</td></tr>
                        </table>
                        <div class="warn-box">⚠️ ต้องเปิด 2-Step Verification ใน Google Account แล้วสร้าง App Password จึงจะใช้งานได้</div>

                        <h4>การแจ้งเตือนแอดมิน</h4>
                        <p>ติ๊ก "เปิดใช้งานการส่งอีเมลแจ้งเตือน Admin" แล้วระบุอีเมลผู้รับ — ทุกครั้งที่มีคนส่งฟอร์ม แอดมินจะได้รับ Email แจ้งเตือนทันที</p>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">🛡️ ตั้งค่า Captcha <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <p>รองรับ 2 บริการ:</p>
                        <table>
                            <tr><th>บริการ</th><th>วิธีขอ Key</th></tr>
                            <tr><td>Google reCAPTCHA v3</td><td>สร้างที่ <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a></td></tr>
                            <tr><td>Cloudflare Turnstile</td><td>สร้างที่ <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">Cloudflare Dashboard</a></td></tr>
                        </table>
                        <p>เลือกบริการจาก Dropdown → กรอก Site Key + Secret Key → บันทึก</p>
                        <div class="tip-box">💡 ระบบจะตรวจสอบ Token ทั้งฝั่ง Frontend และ Server-side อัตโนมัติ</div>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">📥 จัดการรายการข้อมูล (Entries) <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <p>ไปที่ <strong>แบบฟอร์ม → 📥 รายการข้อมูล</strong></p>

                        <h4>ตัวกรอง</h4>
                        <table>
                            <tr><th>ตัวกรอง</th><th>คำอธิบาย</th></tr>
                            <tr><td>ฟอร์ม</td><td>เลือกดูเฉพาะฟอร์มที่ต้องการ (แสดงข้อมูลแยกคอลัมน์)</td></tr>
                            <tr><td>ช่วงวันที่</td><td>กรองตามวันที่ส่ง (ตั้งแต่ - ถึง)</td></tr>
                            <tr><td>ค้นหา</td><td>พิมพ์ชื่อ / เบอร์ / อีเมล / IP</td></tr>
                            <tr><td>แถบสถานะ</td><td>คลิกแถบด้านบนเพื่อกรองตามสถานะ</td></tr>
                        </table>

                        <h4>ระบบสถานะ</h4>
                        <table>
                            <tr><th>สถานะ</th><th>ความหมาย</th></tr>
                            <tr><td>🔵 ใหม่</td><td>ข้อมูลเข้ามาใหม่ ยังไม่ได้ดำเนินการ</td></tr>
                            <tr><td>🟡 กำลังดำเนินการ</td><td>อยู่ระหว่างติดต่อ/จัดการ</td></tr>
                            <tr><td>✅ เสร็จสิ้น</td><td>จัดการเสร็จเรียบร้อยแล้ว</td></tr>
                            <tr><td>🔴 ขยะ</td><td>Spam หรือข้อมูลไม่เกี่ยวข้อง</td></tr>
                        </table>
                        <p>เปลี่ยนสถานะจาก Dropdown ในแต่ละแถว หรือเลือกหลายรายการแล้วใช้ Bulk Actions</p>

                        <h4>โน้ตแอดมิน</h4>
                        <p>คลิก <strong>"✏️ + เพิ่มโน้ต"</strong> เพื่อบันทึกข้อความ เช่น "โทรหาลูกค้าแล้ว" — บันทึกทันทีไม่ต้อง reload</p>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">📊 ส่งออก CSV & Dashboard <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <h4>ส่งออก CSV</h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text">ตั้งค่าตัวกรองที่ต้องการ (ฟอร์ม / วันที่ / สถานะ)</span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text">กดปุ่ม <strong>"📊 ส่งออก CSV"</strong> ด้านบนขวา</span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text">ไฟล์ CSV ดาวน์โหลดอัตโนมัติ (รองรับภาษาไทย)</span></div>

                        <h4>Dashboard Widget</h4>
                        <p>เมื่อเข้า <strong>WP-Admin → Dashboard</strong> จะเห็น Widget "📊 InsightX Form" แสดง:</p>
                        <ul style="margin:8px 0; padding-left:20px;">
                            <li>สถิติ: วันนี้ / 7 วัน / 30 วัน / ทั้งหมด</li>
                            <li>กราฟแท่งสัดส่วนสถานะ</li>
                            <li>5 รายการล่าสุดพร้อมลิงก์ "ดูทั้งหมด"</li>
                        </ul>
                    </div>
                </div>

                <div class="acf-docs-section">
                    <div class="acf-docs-header">❓ คำถามที่พบบ่อย (FAQ) <span class="acf-docs-arrow">▼</span></div>
                    <div class="acf-docs-body">
                        <h4>ฟอร์มไม่ส่งอีเมล ทำอย่างไร?</h4>
                        <p>ตรวจสอบการตั้งค่า SMTP → ใช้ปุ่ม "ทดสอบส่งอีเมล" เพื่อดู Error Message</p>

                        <h4>ลูกค้ากรอก Email แล้วไม่ได้รับอีเมลยืนยัน?</h4>
                        <p>ตรวจสอบว่ามีฟิลด์ประเภท <code>Email</code> ในฟอร์ม — ระบบจะส่งอีเมลไปยังที่อยู่นั้นอัตโนมัติ</p>

                        <h4>ใช้ฟอร์มเดียวกันหลายหน้าได้ไหม?</h4>
                        <p>ได้ คัดลอก Shortcode เดียวกันไปวางได้ไม่จำกัด</p>

                        <h4>อัพเดตปลั๊กอินแล้วข้อมูลเก่าหายไหม?</h4>
                        <p>ไม่หาย ข้อมูลเก็บในฐานข้อมูล WordPress แยกจากไฟล์ปลั๊กอิน</p>

                        <h4>Uninstall ปลั๊กอินแล้วข้อมูลจะหายไหม?</h4>
                        <p>จะหาย — เมื่อ Uninstall (ลบ) ปลั๊กอิน ระบบจะลบตารางข้อมูลและการตั้งค่าทั้งหมด หากต้องการเก็บข้อมูลไว้ ให้ Export CSV ก่อน</p>
                    </div>
                </div>

                <p style="text-align:center; color:#999; font-size:12px; margin-top:20px;">InsightX Form v<?php echo ACF_PLUGIN_VERSION; ?> — Made by <a href="https://www.insightx.in.th" target="_blank">InsightX</a></p>
            </div>

            <script>
                document.querySelectorAll('.acf-docs-header').forEach(function(h) {
                    h.addEventListener('click', function() {
                        this.parentElement.classList.toggle('open');
                    });
                });
            </script>
            <?php
        }
    }
}