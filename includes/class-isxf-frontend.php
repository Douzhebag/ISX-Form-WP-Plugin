<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'ISXF_Frontend' ) ) {
    class ISXF_Frontend {
        public function __construct() {
            add_shortcode( 'advanced_form', [ $this, 'render_dynamic_form' ] );
            add_shortcode( 'isxf_form', [ $this, 'render_dynamic_form' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets_conditionally' ] );
        }

    public function enqueue_assets_conditionally() {
        add_action('wp_head', function() {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">';
        }, 1);

        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'advanced_form' ) || has_shortcode( $post->post_content, 'isxf_form' ) ) ) {
            $service = get_option('isxf_captcha_service', 'google');
            if ( $service === 'google' ) {
                $site_key = get_option('isxf_recaptcha_site_key');
                if($site_key) wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, [], null, true );
            } else {
                wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
            }

            wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
            wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true );
            wp_add_inline_script( 'flatpickr-js', "window.flatpickr || document.write('<script src=\"" . esc_url( ISXF_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.js' ) . "\"><\/script><script src=\"" . esc_url( ISXF_PLUGIN_URL . 'assets/libs/flatpickr/th.js' ) . "\"><\/script><link rel=\"stylesheet\" href=\"" . esc_url( ISXF_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.css' ) . "\">')" );
            wp_enqueue_script( 'flatpickr-th', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js', ['flatpickr-js'], '4.6.13', true );

            wp_enqueue_style( 'isxf-frontend-style', ISXF_PLUGIN_URL . 'assets/css/isxf-frontend.css', [], ISXF_PLUGIN_VERSION ); 
            wp_enqueue_script( 'isxf-frontend-script', ISXF_PLUGIN_URL . 'assets/js/isxf-frontend.js', ['jquery', 'flatpickr-js'], ISXF_PLUGIN_VERSION, true );

            wp_localize_script( 'isxf-frontend-script', 'isxf_env', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'service'  => $service,
                'site_key' => ($service === 'google') ? get_option('isxf_recaptcha_site_key') : get_option('isxf_turnstile_site_key'),
                'i18n'     => [
                    'checking_security' => __( 'กำลังตรวจสอบความปลอดภัย...', 'insightx-form' ),
                    'recaptcha_error'   => __( 'เกิดข้อผิดพลาดในการตรวจสอบความปลอดภัย (reCAPTCHA)', 'insightx-form' ),
                    'please_wait'       => __( 'กรุณารอสักครู่ ระบบกำลังตรวจสอบความปลอดภัย...', 'insightx-form' ),
                    'processing'        => __( 'กำลังประมวลผล...', 'insightx-form' ),
                    'conn_error'        => __( 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'insightx-form' )
                ]
            ]);
        }
    }

    public function render_dynamic_form( $atts ) {
        $atts = shortcode_atts( [ 'id' => '' ], $atts );
        $form_id = intval( $atts['id'] );
        $fields = get_post_meta( $form_id, '_isxf_form_fields', true );
        $service = get_option('isxf_captcha_service', 'google');
        
        if ( empty($fields) ) return '<p>' . esc_html__( 'ไม่พบฟิลด์ในแบบฟอร์มนี้', 'insightx-form' ) . '</p>';

        ob_start(); ?>
        <div class="isxf-form-container">
            <form class="advanced-contact-form" method="POST">
                <?php wp_nonce_field( 'isxf_secure_nonce', 'isxf_nonce' ); ?>
                <input type="hidden" name="action" value="isxf_submit_form">
                <input type="hidden" name="isxf_form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="text" name="isxf_website_url_trap" value="" style="display:none !important; visibility:hidden;" tabindex="-1" autocomplete="off">
                
                <div class="isxf-form-grid">
                    <?php foreach($fields as $f): 
                        if ( !isset($f['type'], $f['name'], $f['label']) ) continue;

                        $width_class = (isset($f['width']) && $f['width'] === '50') ? 'isxf-col-50' : 'isxf-col-100';
                        $options_array = !empty($f['options']) ? array_map('trim', explode(',', $f['options'])) : [];
                        
                        $input_class = '';
                        if ( $f['type'] === 'date' ) {
                            $input_class = 'isxf-modern-date';
                        } elseif ( $f['type'] === 'check_in' ) {
                            $input_class = 'isxf-date-check-in';
                        } elseif ( $f['type'] === 'check_out' ) {
                            $input_class = 'isxf-date-check-out';
                        }
                    ?>
                        <div class="isxf-field-wrapper <?php echo esc_attr($width_class); ?>" data-type="<?php echo esc_attr($f['type']); ?>" data-required="<?php echo esc_attr($f['required']); ?>">
                            
                            <?php if ( $f['type'] === 'heading' ) : ?>
                                <h3 class="isxf-form-heading"><?php echo esc_html($f['label']); ?></h3>
                            <?php else : ?>
                                <label class="isxf-field-label">
                                    <?php echo esc_html($f['label']); ?>
                                    <?php if ( $f['required'] === 'yes' ) echo '<span class="isxf-req-mark">*</span>'; ?>
                                </label>
                                
                                <?php if ( $f['type'] === 'textarea' ) : ?>
                                    <textarea name="<?php echo esc_attr($f['name']); ?>" rows="4" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>"></textarea>
                                
                                <?php elseif ( $f['type'] === 'select' ) : ?>
                                    <select name="<?php echo esc_attr($f['name']); ?>">
                                        <option value="">-- <?php echo esc_html__( 'โปรดเลือก', 'insightx-form' ); ?> --</option>
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ( $f['type'] === 'radio' || $f['type'] === 'checkbox' ) : ?>
                                    <?php 
                                        $group_class = $f['type'] === 'radio' ? 'isxf-radio-group' : 'isxf-checkbox-group';
                                        $name_attr = $f['type'] === 'checkbox' ? esc_attr($f['name']) . '[]' : esc_attr($f['name']);
                                    ?>
                                    <div class="<?php echo $group_class; ?>">
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <label>
                                                <input type="<?php echo esc_attr($f['type']); ?>" name="<?php echo $name_attr; ?>" value="<?php echo esc_attr($opt); ?>">
                                                <?php echo esc_html($opt); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if(empty($options_array)) echo '<span style="color:red; font-size:12px;">(' . esc_html__( 'แอดมินยังไม่ได้ตั้งค่าตัวเลือก', 'insightx-form' ) . ')</span>'; ?>
                                    </div>

                                <?php else : ?>
                                    <?php $input_type = in_array($f['type'], ['date', 'check_in', 'check_out']) ? 'text' : $f['type']; ?>
                                    <input type="<?php echo esc_attr($input_type); ?>" class="<?php echo esc_attr($input_class); ?>" name="<?php echo esc_attr($f['name']); ?>" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>">
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if($service === 'cloudflare' && get_option('isxf_turnstile_site_key')): ?>
                    <div class="cf-turnstile" style="margin-top: 15px;" data-sitekey="<?php echo esc_attr(get_option('isxf_turnstile_site_key')); ?>"></div>
                <?php endif; ?>
                
                <button type="submit" class="isxf-submit-btn" aria-label="<?php esc_attr_e( 'ยืนยันส่งแบบฟอร์ม', 'insightx-form' ); ?>" aria-busy="false"><?php esc_html_e( 'ยืนยันส่งแบบฟอร์ม', 'insightx-form' ); ?></button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
}