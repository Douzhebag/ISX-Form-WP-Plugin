<?php

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

class Frontend {
        public function __construct() {
            add_shortcode( 'advanced_form', [ $this, 'render_dynamic_form' ] );
            add_shortcode( 'isxf_form', [ $this, 'render_dynamic_form' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets_conditionally' ] );
        }

    public function enqueue_assets_conditionally() {
        add_action('wp_head', function() {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
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

            // Bundled flatpickr v4.6.13 (source: https://cdn.jsdelivr.net/npm/flatpickr@4.6.13) —
            // served locally so the frontend has no CDN dependency.
            wp_enqueue_style( 'flatpickr-css', ISXF_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.css', [], '4.6.13' );
            wp_enqueue_script( 'flatpickr-js', ISXF_PLUGIN_URL . 'assets/libs/flatpickr/flatpickr.min.js', [], '4.6.13', true );
            wp_enqueue_script( 'flatpickr-th', ISXF_PLUGIN_URL . 'assets/libs/flatpickr/th.js', ['flatpickr-js'], '4.6.13', true );

            wp_enqueue_style( 'isxf-frontend-style', ISXF_PLUGIN_URL . 'assets/css/isxf-frontend.css', [], ISXF_PLUGIN_VERSION );
            // isxf-frontend.js is vanilla JS — no jQuery dependency.
            wp_enqueue_script( 'isxf-frontend-script', ISXF_PLUGIN_URL . 'assets/js/isxf-frontend.js', ['flatpickr-js'], ISXF_PLUGIN_VERSION, true );

            wp_localize_script( 'isxf-frontend-script', 'isxf_env', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'service'  => $service,
                'site_key' => ($service === 'google') ? get_option('isxf_recaptcha_site_key') : get_option('isxf_turnstile_site_key'),
                'i18n'     => [
                    'checking_security' => __( 'Checking security...', 'insightx-form' ),
                    'recaptcha_error'   => __( 'Security check failed (reCAPTCHA)', 'insightx-form' ),
                    'please_wait'       => __( 'Please wait, verifying your security...', 'insightx-form' ),
                    'processing'        => __( 'Processing...', 'insightx-form' ),
                    'conn_error'        => __( 'Connection error', 'insightx-form' ),
                    'submit_form'       => __( 'Submit Form', 'insightx-form' ),
                    'field_required'    => __( 'This field is required', 'insightx-form' ),
                    'email_invalid'     => __( 'Please enter a valid email address', 'insightx-form' ),
                    'form_has_errors'   => __( 'Please correct the highlighted fields and try again', 'insightx-form' )
                ]
            ]);
        }
    }

    public function render_dynamic_form( $atts ) {
        $atts = shortcode_atts( [ 'id' => '' ], $atts );
        $form_id = intval( $atts['id'] );
        $fields = get_post_meta( $form_id, '_isxf_form_fields', true );
        $service = get_option('isxf_captcha_service', 'google');

        if ( empty($fields) ) return '<p>' . esc_html__( 'No fields found in this form', 'insightx-form' ) . '</p>';

        $button = self::button_styles( $form_id );

        return Template::get( 'frontend/form', [
            'form_id'            => $form_id,
            'fields'             => $fields,
            'service'            => $service,
            'turnstile_site_key' => get_option('isxf_turnstile_site_key'),
            'button_style'       => $button['vars'],
            'button_css'         => $button['css'],
        ] );
    }

    /**
     * Collect the per-form submit-button style settings.
     *
     * Structured fields become CSS custom properties on the form container
     * (empty fields fall back to the stylesheet defaults); the Advanced CSS
     * box is sanitized and scoped in the template to
     * `.isxf-form-{id} .isxf-submit-btn`.
     *
     * @return array{vars: string, css: string}
     */
    private static function button_styles( $form_id ) {
        $hex = function ( $key ) use ( $form_id ) {
            $v = get_post_meta( $form_id, $key, true );
            return is_string( $v ) ? ( sanitize_hex_color( $v ) ?: '' ) : '';
        };
        $px = function ( $key ) use ( $form_id ) {
            $v = absint( get_post_meta( $form_id, $key, true ) );
            return ( $v >= 1 && $v <= 100 ) ? $v . 'px' : '';
        };

        $bg    = $hex( '_isxf_form_button_color' );
        $hover = $hex( '_isxf_form_button_hover_color' );
        $text  = $hex( '_isxf_form_button_text_color' );

        $vars = [];
        if ( $bg )    $vars[] = '--isxf-btn-bg:' . $bg;
        if ( $bg )    $vars[] = '--isxf-btn-bg-hover:' . ( $hover ?: self::hover_shade( $bg ) );
        if ( $text )  $vars[] = '--isxf-btn-color:' . $text;
        if ( $px( '_isxf_form_button_radius' ) )    $vars[] = '--isxf-btn-radius:' . $px( '_isxf_form_button_radius' );
        if ( $px( '_isxf_form_button_font_size' ) ) $vars[] = '--isxf-btn-font-size:' . $px( '_isxf_form_button_font_size' );

        $css = get_post_meta( $form_id, '_isxf_form_button_css', true );
        $css = is_string( $css ) ? self::sanitize_custom_css( $css ) : '';

        return [ 'vars' => implode( ';', $vars ), 'css' => $css ];
    }

    /**
     * Strip anything that could break out of a <style> block or execute —
     * the box is admin-only, but defense in depth (applied on save AND output).
     */
    public static function sanitize_custom_css( $css ) {
        $css = wp_strip_all_tags( $css );
        $css = preg_replace( '/(expression\s*\(|javascript\s*:|-moz-binding|@import|\\\\)/i', '', $css );
        return trim( (string) $css );
    }

    /**
     * Hover shade = the base color mixed 15% toward white (deterministic).
     */
    private static function hover_shade( $hex ) {
        $rgb = array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
        $hover = '#';
        foreach ( $rgb as $c ) {
            $hover .= str_pad( dechex( min( 255, (int) round( $c + ( 255 - $c ) * 0.15 ) ) ), 2, '0', STR_PAD_LEFT );
        }
        return $hover;
    }
}