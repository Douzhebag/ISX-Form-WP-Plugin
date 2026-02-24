<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACF_Frontend {
    public function __construct() {
        add_shortcode( 'advanced_form', [ $this, 'render_dynamic_form' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets_conditionally' ] );
    }

    public function enqueue_assets_conditionally() {
        add_action('wp_head', function() {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">';
        }, 1);

        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'advanced_form' ) ) {
            $service = get_option('acf_captcha_service', 'google');
            if ( $service === 'google' ) {
                $site_key = get_option('acf_recaptcha_site_key');
                if($site_key) wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, [], null, true );
            } else {
                wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
            }

            wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
            wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true );
            wp_enqueue_script( 'flatpickr-th', 'https://npmcdn.com/flatpickr/dist/l10n/th.js', ['flatpickr-js'], '4.6.13', true );

            wp_enqueue_style( 'acf-frontend-style', ACF_PLUGIN_URL . 'assets/css/acf-frontend.css', [], ACF_PLUGIN_VERSION ); 
            wp_enqueue_script( 'acf-frontend-script', ACF_PLUGIN_URL . 'assets/js/acf-frontend.js', ['jquery', 'flatpickr-js'], ACF_PLUGIN_VERSION, true );

            wp_localize_script( 'acf-frontend-script', 'acf_env', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'service'  => $service,
                'site_key' => ($service === 'google') ? get_option('acf_recaptcha_site_key') : get_option('acf_turnstile_site_key')
            ]);
        }
    }

    public function render_dynamic_form( $atts ) {
        $atts = shortcode_atts( [ 'id' => '' ], $atts );
        $form_id = intval( $atts['id'] );
        $fields = get_post_meta( $form_id, '_acf_form_fields', true );
        $service = get_option('acf_captcha_service', 'google');
        
        if ( empty($fields) ) return '<p>ไม่พบฟิลด์ในแบบฟอร์มนี้</p>';

        ob_start(); ?>
        <div class="acf-form-container">
            <form class="advanced-contact-form" method="POST">
                <?php wp_nonce_field( 'acf_secure_nonce', 'acf_nonce' ); ?>
                <input type="hidden" name="action" value="acf_submit_form">
                <input type="hidden" name="acf_form_id" value="<?php echo esc_attr($form_id); ?>">
                <input type="text" name="acf_website_url_trap" value="" style="display:none !important; visibility:hidden;" tabindex="-1" autocomplete="off">
                
                <div class="acf-form-grid">
                    <?php foreach($fields as $f): 
                        if ( !isset($f['type'], $f['name'], $f['label']) ) continue;

                        $width_class = (isset($f['width']) && $f['width'] === '50') ? 'acf-col-50' : 'acf-col-100';
                        $options_array = !empty($f['options']) ? array_map('trim', explode(',', $f['options'])) : [];
                        
                        $input_class = '';
                        if ( $f['type'] === 'date' ) {
                            $input_class = 'acf-modern-date';
                        } elseif ( $f['type'] === 'check_in' ) {
                            $input_class = 'acf-date-check-in';
                        } elseif ( $f['type'] === 'check_out' ) {
                            $input_class = 'acf-date-check-out';
                        }
                    ?>
                        <div class="acf-field-wrapper <?php echo esc_attr($width_class); ?>" data-type="<?php echo esc_attr($f['type']); ?>" data-required="<?php echo esc_attr($f['required']); ?>">
                            
                            <?php if ( $f['type'] === 'heading' ) : ?>
                                <h3 class="acf-form-heading"><?php echo esc_html($f['label']); ?></h3>
                            <?php else : ?>
                                <label class="acf-field-label">
                                    <?php echo esc_html($f['label']); ?>
                                    <?php if ( $f['required'] === 'yes' ) echo '<span class="acf-req-mark">*</span>'; ?>
                                </label>
                                
                                <?php if ( $f['type'] === 'textarea' ) : ?>
                                    <textarea name="<?php echo esc_attr($f['name']); ?>" rows="4" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>"></textarea>
                                
                                <?php elseif ( $f['type'] === 'select' ) : ?>
                                    <select name="<?php echo esc_attr($f['name']); ?>">
                                        <option value="">-- โปรดเลือก --</option>
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ( $f['type'] === 'radio' || $f['type'] === 'checkbox' ) : ?>
                                    <?php 
                                        $group_class = $f['type'] === 'radio' ? 'acf-radio-group' : 'acf-checkbox-group';
                                        $name_attr = $f['type'] === 'checkbox' ? esc_attr($f['name']) . '[]' : esc_attr($f['name']);
                                    ?>
                                    <div class="<?php echo $group_class; ?>">
                                        <?php foreach($options_array as $opt): if(empty($opt)) continue; ?>
                                            <label>
                                                <input type="<?php echo esc_attr($f['type']); ?>" name="<?php echo $name_attr; ?>" value="<?php echo esc_attr($opt); ?>">
                                                <?php echo esc_html($opt); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if(empty($options_array)) echo '<span style="color:red; font-size:12px;">(แอดมินยังไม่ได้ตั้งค่าตัวเลือก)</span>'; ?>
                                    </div>

                                <?php else : ?>
                                    <?php $input_type = in_array($f['type'], ['date', 'check_in', 'check_out']) ? 'text' : $f['type']; ?>
                                    <input type="<?php echo esc_attr($input_type); ?>" class="<?php echo esc_attr($input_class); ?>" name="<?php echo esc_attr($f['name']); ?>" placeholder="<?php echo esc_attr(!empty($f['placeholder']) ? $f['placeholder'] : $f['label']); ?>">
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if($service === 'cloudflare' && get_option('acf_turnstile_site_key')): ?>
                    <div class="cf-turnstile" style="margin-top: 15px;" data-sitekey="<?php echo esc_attr(get_option('acf_turnstile_site_key')); ?>"></div>
                <?php endif; ?>
                
                <button type="submit" class="acf-submit-btn">ยืนยันส่งแบบฟอร์ม</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}