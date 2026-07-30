<?php
/**
 * Integration tests for the frontend form render (Phase 2.6 accessibility).
 *
 * Renders the [isxf_form] shortcode and asserts the accessibility markup:
 * label[for]/input[id] association, aria-required, inline error elements
 * linked via aria-describedby, fieldset/legend for choice groups, the
 * submit hint linked from the button, and novalidate on the form (native
 * browser validation must not fight the JS submit flow).
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group frontend
 */
class FrontendRenderTest extends WP_UnitTestCase {

    /** @var int */
    private $form_id;

    /** @var string Rendered shortcode markup. */
    private $html;

    public function set_up() {
        parent::set_up();

        update_option( 'isxf_captcha_service', 'google' );
        delete_option( 'isxf_turnstile_site_key' );

        $this->form_id = self::factory()->post->create(
            [
                'post_type'   => 'isxf_form',
                'post_title'  => 'A11y Test Form',
                'post_status' => 'publish',
            ]
        );
        update_post_meta(
            $this->form_id,
            '_isxf_form_fields',
            [
                [ 'type' => 'heading', 'name' => 'sec_1', 'label' => 'Section', 'required' => 'no' ],
                [ 'type' => 'text', 'name' => 'full_name', 'label' => 'Full Name', 'required' => 'yes' ],
                [ 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => 'yes' ],
                [ 'type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'required' => 'no' ],
                [ 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => 'yes' ],
                [ 'type' => 'select', 'name' => 'topic', 'label' => 'Topic', 'required' => 'yes', 'options' => 'Sales, Support' ],
                [ 'type' => 'radio', 'name' => 'channel', 'label' => 'Channel', 'required' => 'yes', 'options' => 'Email, Phone' ],
                [ 'type' => 'checkbox', 'name' => 'interests', 'label' => 'Interests', 'required' => 'no', 'options' => 'News, Promo' ],
            ]
        );

        $this->html = do_shortcode( '[isxf_form id="' . $this->form_id . '"]' );
    }

    public function test_labels_are_associated_with_inputs(): void {
        foreach ( [ 'full_name', 'email', 'phone', 'message', 'topic' ] as $name ) {
            $id = 'isxf-field-' . $this->form_id . '-' . $name;
            $this->assertStringContainsString(
                '<label class="isxf-field-label" for="' . $id . '">',
                $this->html,
                "Label for {$name} must point at the input id"
            );
            $this->assertMatchesRegularExpression(
                '/<(input|textarea|select)[^>]*\bid="' . preg_quote( $id, '/' ) . '"/',
                $this->html,
                "Field {$name} must carry the matching id"
            );
        }
    }

    public function test_required_fields_have_aria_required(): void {
        $this->assertStringContainsString( 'name="full_name" placeholder="Full Name" aria-required="true"', $this->html );
        $this->assertStringContainsString( 'aria-required="true" aria-describedby="isxf-error-' . $this->form_id . '-message"', $this->html );
        $this->assertStringNotContainsString( 'name="phone" placeholder="Phone" aria-required="true"', $this->html, 'Optional fields must not be aria-required' );
        // data-required stays: the JS submit gating keys off it.
        $this->assertStringContainsString( 'data-type="text" data-required="yes"', $this->html );
    }

    public function test_no_native_required_attribute_and_form_is_novalidate(): void {
        $this->assertStringContainsString( '<form class="advanced-contact-form" method="POST" novalidate>', $this->html );
        $this->assertDoesNotMatchRegularExpression( '/<input[^>]*\srequired(\s|=|>)/', $this->html, 'Native required would trigger browser popups that fight the JS flow' );
    }

    public function test_inline_error_elements_linked_via_aria_describedby(): void {
        foreach ( [ 'full_name', 'email', 'phone', 'message', 'topic', 'channel', 'interests' ] as $name ) {
            $error_id = 'isxf-error-' . $this->form_id . '-' . $name;
            $this->assertStringContainsString(
                '<p class="isxf-field-error" id="' . $error_id . '" role="alert" hidden></p>',
                $this->html,
                "Inline error element for {$name} must exist, hidden and announced"
            );
            $this->assertStringContainsString(
                'aria-describedby="' . $error_id . '"',
                $this->html,
                "Field {$name} must reference its error element"
            );
        }
    }

    public function test_choice_groups_use_fieldset_and_legend(): void {
        $this->assertSame(
            2,
            substr_count( $this->html, '<fieldset class="isxf-choice-fieldset">' ),
            'Radio and checkbox groups must each render a fieldset'
        );
        $this->assertStringContainsString( '<legend class="isxf-field-label isxf-choice-legend">', $this->html );
        $this->assertStringContainsString( 'aria-required="true" aria-describedby="isxf-error-' . $this->form_id . '-channel"', $this->html );
    }

    public function test_submit_button_has_hint_and_describedby(): void {
        $hint_id = 'isxf-submit-hint-' . $this->form_id;
        $this->assertStringContainsString( '<p class="isxf-submit-hint" id="' . $hint_id . '">', $this->html );
        $this->assertStringContainsString( 'aria-describedby="' . $hint_id . '"', $this->html );
    }

    public function test_heading_fields_render_no_label_or_error(): void {
        $this->assertStringContainsString( '<h3 class="isxf-form-heading">Section</h3>', $this->html );
        $this->assertStringNotContainsString( 'isxf-field-' . $this->form_id . '-sec_1', $this->html );
        $this->assertStringNotContainsString( 'isxf-error-' . $this->form_id . '-sec_1', $this->html );
    }

    public function test_field_names_unchanged(): void {
        // Submitted field names are the contract with SubmissionController.
        foreach ( [ 'full_name', 'email', 'phone', 'message', 'topic', 'channel' ] as $name ) {
            $this->assertStringContainsString( 'name="' . $name . '"', $this->html );
        }
        $this->assertStringContainsString( 'name="interests[]"', $this->html );
    }

    public function test_default_form_has_no_custom_button_style(): void {
        $this->assertStringContainsString( '<div class="isxf-form-container isxf-form-' . $this->form_id . '">', $this->html );
        $this->assertStringNotContainsString( '--isxf-btn-bg', $this->html );
        $this->assertStringNotContainsString( '<style>', $this->html );
    }

    public function test_custom_button_color_outputs_css_vars(): void {
        update_post_meta( $this->form_id, '_isxf_form_button_color', '#e11d48' );
        $html = do_shortcode( '[isxf_form id="' . $this->form_id . '"]' );

        $this->assertStringContainsString( '--isxf-btn-bg:#e11d48', $html );
        // Hover shade = base mixed 15% toward white (#e63f63).
        $this->assertStringContainsString( '--isxf-btn-bg-hover:#e63f63', $html );
    }

    public function test_invalid_button_color_is_ignored(): void {
        update_post_meta( $this->form_id, '_isxf_form_button_color', 'not-a-color' );
        $html = do_shortcode( '[isxf_form id="' . $this->form_id . '"]' );

        $this->assertStringNotContainsString( '--isxf-btn-bg', $html );
    }

    public function test_structured_button_style_fields_output_vars(): void {
        update_post_meta( $this->form_id, '_isxf_form_button_color', '#0ea5e9' );
        update_post_meta( $this->form_id, '_isxf_form_button_text_color', '#111111' );
        update_post_meta( $this->form_id, '_isxf_form_button_hover_color', '#0284c7' );
        update_post_meta( $this->form_id, '_isxf_form_button_radius', '24' );
        update_post_meta( $this->form_id, '_isxf_form_button_font_size', '18' );
        $html = do_shortcode( '[isxf_form id="' . $this->form_id . '"]' );

        $this->assertStringContainsString( '--isxf-btn-bg:#0ea5e9', $html );
        // Explicit hover color wins over the auto-computed shade.
        $this->assertStringContainsString( '--isxf-btn-bg-hover:#0284c7', $html );
        $this->assertStringContainsString( '--isxf-btn-color:#111111', $html );
        $this->assertStringContainsString( '--isxf-btn-radius:24px', $html );
        $this->assertStringContainsString( '--isxf-btn-font-size:18px', $html );
    }

    public function test_advanced_button_css_is_scoped_and_sanitized(): void {
        update_post_meta( $this->form_id, '_isxf_form_button_css', 'box-shadow: 0 4px 12px rgba(0,0,0,.25); </style><script>alert(1)</script> letter-spacing: 1px; @import url(evil.css);' );
        $html = do_shortcode( '[isxf_form id="' . $this->form_id . '"]' );

        $this->assertStringContainsString( '.isxf-form-container.isxf-form-' . $this->form_id . ' .isxf-submit-btn', $html );
        $this->assertStringContainsString( 'box-shadow: 0 4px 12px rgba(0,0,0,.25);', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringNotContainsString( '@import', $html );
    }
}
