<?php
/**
 * Integration tests for the admin side that has no AJAX endpoint:
 *
 * - Admin::save_form_fields() — nonce / capability gates and how every
 *   builder value is sanitized (fields, colours, radius, advanced CSS,
 *   custom email body).
 * - Frontend::sanitize_custom_css() — no way out of the <style> block.
 * - Admin::maybe_drop_microsoft_oauth() — sites still on the removed
 *   Microsoft 365 provider fall back to Basic Auth.
 * - Admin::render_settings_page() — which SMTP provider card starts
 *   selected and which setup note is shown.
 * - process_merge_tags() — submitted values are escaped in emails.
 * - isxf_asset_ver() — cache-busting version string.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

use ISXF\Admin;
use ISXF\Ajax\SubmissionController;
use ISXF\Frontend;

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group admin
 */
class AdminTest extends WP_UnitTestCase {

    /** @var Admin */
    private $admin;

    /** @var int */
    private $form_id;

    public function set_up() {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
        $this->admin   = new Admin();
        $this->form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_status' => 'publish' ] );
    }

    public function tear_down() {
        $_POST = [];
        $_GET  = [];
        parent::tear_down();
    }

    private function save_form( array $post ): void {
        $_POST = array_merge( [ 'isxf_form_nonce' => wp_create_nonce( 'save_isxf_form' ) ], $post );
        $this->admin->save_form_fields( $this->form_id );
    }

    /* ---------- save_form_fields ---------- */

    public function test_save_without_nonce_changes_nothing(): void {
        $_POST = [ 'isxf_form_button_color' => '#ff0000' ];
        $this->admin->save_form_fields( $this->form_id );

        $this->assertSame( '', get_post_meta( $this->form_id, '_isxf_form_button_color', true ) );
    }

    public function test_save_by_user_who_cannot_edit_the_form_changes_nothing(): void {
        $nonce = wp_create_nonce( 'save_isxf_form' );
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
        $_POST = [ 'isxf_form_nonce' => $nonce, 'isxf_form_button_color' => '#ff0000' ];

        $this->admin->save_form_fields( $this->form_id );

        $this->assertSame( '', get_post_meta( $this->form_id, '_isxf_form_button_color', true ) );
    }

    public function test_fields_are_sanitized_and_empty_labels_dropped(): void {
        $this->save_form(
            [
                'isxf_fields' => [
                    [ 'label' => '<b>ชื่อ</b>', 'name' => 'Full Name!', 'type' => 'text', 'placeholder' => '<i>x</i>', 'options' => '', 'width' => '50', 'required' => 'yes' ],
                    [ 'label' => '', 'name' => 'ghost', 'type' => 'text', 'width' => '100', 'required' => 'no' ],
                ],
            ]
        );

        $fields = get_post_meta( $this->form_id, '_isxf_form_fields', true );
        $this->assertCount( 1, $fields, 'A field without a label is dropped' );
        $this->assertSame( 'ชื่อ', $fields[0]['label'] );
        $this->assertSame( 'fullname', $fields[0]['name'], 'Field names go through sanitize_key' );
        $this->assertSame( 'x', $fields[0]['placeholder'] );
    }

    public function test_button_colours_accept_hex_only(): void {
        $this->save_form(
            [
                'isxf_form_button_color'       => '#0079ff',
                'isxf_form_button_text_color'  => 'red;background:url(x)',
                'isxf_form_button_hover_color' => '#abc',
            ]
        );

        $this->assertSame( '#0079ff', get_post_meta( $this->form_id, '_isxf_form_button_color', true ) );
        $this->assertSame( '', get_post_meta( $this->form_id, '_isxf_form_button_text_color', true ) );
        $this->assertSame( '#abc', get_post_meta( $this->form_id, '_isxf_form_button_hover_color', true ) );
    }

    public function test_radius_and_font_size_are_clamped(): void {
        $this->save_form( [ 'isxf_form_button_radius' => '12', 'isxf_form_button_font_size' => '500' ] );

        $this->assertSame( '12', (string) get_post_meta( $this->form_id, '_isxf_form_button_radius', true ) );
        $this->assertSame( '', get_post_meta( $this->form_id, '_isxf_form_button_font_size', true ), 'Out of range (1-100) → empty = default' );
    }

    public function test_custom_email_body_strips_scripts(): void {
        $this->save_form( [ 'isxf_form_email_body' => '<p>Hi {field:ชื่อ}</p><script>alert(1)</script>' ] );

        $body = get_post_meta( $this->form_id, '_isxf_form_email_body', true );
        $this->assertStringContainsString( '<p>Hi {field:ชื่อ}</p>', $body );
        $this->assertStringNotContainsString( '<script>', $body );
    }

    /* ---------- Frontend::sanitize_custom_css ---------- */

    /**
     * @dataProvider dangerous_css
     */
    public function test_custom_css_cannot_escape_or_run_code( string $input, string $must_not_contain ): void {
        $clean = Frontend::sanitize_custom_css( $input );

        $this->assertStringNotContainsStringIgnoringCase( $must_not_contain, $clean );
    }

    public function dangerous_css(): array {
        return [
            'close style tag'  => [ 'color:red;</style><script>alert(1)</script>', '</style>' ],
            'script tag'       => [ '<script>alert(1)</script>color:red', '<script' ],
            'expression()'     => [ 'width:expression(alert(1))', 'expression(' ],
            'javascript: url'  => [ 'background:url(javascript:alert(1))', 'javascript:' ],
            '@import'          => [ '@import url(//evil.example/x.css);', '@import' ],
            '-moz-binding'     => [ '-moz-binding:url(x.xml#xss)', '-moz-binding' ],
            'escaped sequence' => [ 'background:url(\6a avascript:alert(1))', '\\' ],
        ];
    }

    public function test_custom_css_keeps_normal_rules(): void {
        $this->assertSame( 'letter-spacing: 1px; text-transform: uppercase;', Frontend::sanitize_custom_css( '  letter-spacing: 1px; text-transform: uppercase;  ' ) );
    }

    /* ---------- maybe_drop_microsoft_oauth ---------- */

    public function test_microsoft_oauth_falls_back_to_basic_auth(): void {
        update_option( 'isxf_smtp_auth_method', 'oauth_microsoft' );
        update_option( 'isxf_smtp_oauth_refresh_token', 'old-token' );
        update_option( 'isxf_smtp_oauth_tenant', 'contoso' );

        $this->admin->maybe_drop_microsoft_oauth();

        $this->assertSame( 'password', get_option( 'isxf_smtp_auth_method' ) );
        $this->assertFalse( get_option( 'isxf_smtp_oauth_refresh_token' ) );
        $this->assertFalse( get_option( 'isxf_smtp_oauth_tenant' ) );
    }

    public function test_google_oauth_is_left_alone(): void {
        update_option( 'isxf_smtp_auth_method', 'oauth_google' );
        update_option( 'isxf_smtp_oauth_refresh_token', 'google-token' );

        $this->admin->maybe_drop_microsoft_oauth();

        $this->assertSame( 'oauth_google', get_option( 'isxf_smtp_auth_method' ) );
        $this->assertSame( 'google-token', get_option( 'isxf_smtp_oauth_refresh_token' ) );
    }

    /* ---------- double-encryption repair (v0.9.3 autosave bug) ---------- */

    public function test_double_encrypted_password_is_repaired(): void {
        $once  = \ISXF\Crypto::encrypt( 'real-password' );
        $twice = \ISXF\Crypto::encrypt( $once );
        update_option( 'isxf_smtp_pass', $twice );
        update_option( 'isxf_smtp_oauth_client_secret', \ISXF\Crypto::encrypt( \ISXF\Crypto::encrypt( 'client-secret' ) ) );

        $this->admin->maybe_repair_double_encrypted_secrets();

        $this->assertSame( 'real-password', \ISXF\Crypto::decrypt( get_option( 'isxf_smtp_pass' ) ) );
        $this->assertSame( 'client-secret', \ISXF\Crypto::decrypt( get_option( 'isxf_smtp_oauth_client_secret' ) ) );
    }

    public function test_correctly_encrypted_password_is_left_alone(): void {
        $once = \ISXF\Crypto::encrypt( 'real-password' );
        update_option( 'isxf_smtp_pass', $once );

        $this->admin->maybe_repair_double_encrypted_secrets();

        $this->assertSame( $once, get_option( 'isxf_smtp_pass' ) );
    }

    public function test_secret_sanitizers_never_encrypt_twice(): void {
        $once = \ISXF\Crypto::encrypt( 'real-password' );

        $this->assertSame( $once, $this->admin->sanitize_smtp_password( $once ) );
        $this->assertSame( $once, $this->admin->sanitize_oauth_secret( $once ) );
        $this->assertSame( 'new-password', \ISXF\Crypto::decrypt( $this->admin->sanitize_smtp_password( 'new-password' ) ) );
    }

    public function test_settings_page_has_from_fields(): void {
        update_option( 'isxf_smtp_from_email', 'hello@example.com' );
        update_option( 'isxf_smtp_from_name', 'Acme' );

        $html = $this->render_settings();

        $this->assertStringContainsString( 'name="isxf_smtp_from_email" value="hello@example.com"', $html );
        $this->assertStringContainsString( 'name="isxf_smtp_from_name" value="Acme"', $html );
    }

    /* ---------- settings page: provider cards ---------- */

    private function render_settings(): string {
        ob_start();
        $this->admin->render_settings_page();
        return ob_get_clean();
    }

    private function selected_card( string $html ): string {
        preg_match_all( '/class="isxf-provider-card( is-selected)?"[^>]*data-preset="([a-z_]+)"/', $html, $m, PREG_SET_ORDER );
        $selected = array_values( array_filter( $m, fn( $row ) => $row[1] !== '' ) );
        $this->assertCount( 1, $selected, 'Exactly one provider card is selected' );
        return $selected[0][2];
    }

    /**
     * @dataProvider saved_provider_settings
     */
    public function test_selected_card_follows_saved_settings( string $auth, string $host, string $expected ): void {
        update_option( 'isxf_smtp_auth_method', $auth );
        update_option( 'isxf_smtp_host', $host );

        $this->assertSame( $expected, $this->selected_card( $this->render_settings() ) );
    }

    public function saved_provider_settings(): array {
        return [
            'google oauth'        => [ 'oauth_google', 'smtp.gmail.com', 'oauth_google' ],
            'resend'              => [ 'password', 'smtp.resend.com', 'resend' ],
            'resend upper case'   => [ 'password', ' SMTP.Resend.com ', 'resend' ],
            'cloudflare'          => [ 'password', 'smtp.mx.cloudflare.net', 'cloudflare' ],
            'gmail app password'  => [ 'password', 'smtp.gmail.com', 'custom' ],
            'nothing saved yet'   => [ 'password', '', 'custom' ],
        ];
    }

    public function test_only_the_selected_providers_note_is_visible(): void {
        update_option( 'isxf_smtp_auth_method', 'password' );
        update_option( 'isxf_smtp_host', 'smtp.mx.cloudflare.net' );

        $html = $this->render_settings();

        $this->assertMatchesRegularExpression( '/data-provider-note="cloudflare">/', $html, 'Cloudflare note visible' );
        $this->assertMatchesRegularExpression( '/data-provider-note="resend" hidden>/', $html, 'Resend note hidden' );
    }

    public function test_notes_box_is_hidden_for_custom(): void {
        update_option( 'isxf_smtp_auth_method', 'password' );
        update_option( 'isxf_smtp_host', 'mail.example.com' );

        $this->assertMatchesRegularExpression( '/id="isxf-provider-notes" hidden>/', $this->render_settings() );
    }

    public function test_saved_values_are_escaped_in_the_settings_page(): void {
        update_option( 'isxf_smtp_host', '"><script>alert(1)</script>' );

        $this->assertStringNotContainsString( '<script>alert(1)</script>', $this->render_settings() );
    }

    /* ---------- SMTP connection badge ---------- */

    private function badge_state( string $html ): string {
        $this->assertMatchesRegularExpression( '/id="isxf-smtp-badge" data-state="([a-z]+)"/', $html );
        preg_match( '/id="isxf-smtp-badge" data-state="([a-z]+)"/', $html, $m );
        return $m[1];
    }

    public function test_badge_is_grey_before_any_check(): void {
        delete_option( 'isxf_smtp_last_check' );

        $this->assertSame( 'idle', $this->badge_state( $this->render_settings() ) );
    }

    public function test_badge_shows_last_result_while_settings_are_unchanged(): void {
        update_option( 'isxf_smtp_host', 'smtp.resend.com' );
        update_option( 'isxf_smtp_last_check', [ 'ok' => true, 'message' => 'Connected successfully', 'hash' => \ISXF\Ajax\SettingsController::smtp_config_hash(), 'time' => time() ] );

        $this->assertSame( 'ok', $this->badge_state( $this->render_settings() ) );
    }

    public function test_failed_result_shows_red_badge_and_reason(): void {
        update_option( 'isxf_smtp_last_check', [ 'ok' => false, 'message' => 'Connection failed: <b>bad key</b>', 'hash' => \ISXF\Ajax\SettingsController::smtp_config_hash(), 'time' => time() ] );

        $html = $this->render_settings();

        $this->assertSame( 'error', $this->badge_state( $html ) );
        $this->assertStringContainsString( 'class="isxf-smtp-reason">Connection failed: &lt;b&gt;bad key&lt;/b&gt;</p>', $html, 'Reason shown, escaped' );
    }

    public function test_badge_resets_when_smtp_settings_change(): void {
        update_option( 'isxf_smtp_host', 'smtp.resend.com' );
        update_option( 'isxf_smtp_last_check', [ 'ok' => true, 'message' => 'ok', 'hash' => \ISXF\Ajax\SettingsController::smtp_config_hash(), 'time' => time() ] );
        update_option( 'isxf_smtp_host', 'smtp.mx.cloudflare.net' );

        $this->assertSame( 'idle', $this->badge_state( $this->render_settings() ) );
    }

    /* ---------- merge tags ---------- */

    public function test_merge_tags_escape_submitted_values(): void {
        $controller = new SubmissionController();
        $method     = new ReflectionMethod( $controller, 'process_merge_tags' ); // protected; callable via reflection on PHP 8.1+

        $out = $method->invoke(
            $controller,
            'Hello {field:ชื่อ} — {all_fields}',
            [ 'ชื่อ' => '<img src=x onerror=alert(1)>', 'อีเมล' => 'a@example.com' ],
            $this->form_id
        );

        $this->assertStringNotContainsString( '<img', $out );
        $this->assertStringContainsString( '&lt;img', $out );
        $this->assertStringContainsString( 'a@example.com', $out, '{all_fields} lists every submitted value' );
    }

    /* ---------- isxf_asset_ver ---------- */

    public function test_asset_ver_appends_file_mtime(): void {
        $ver = isxf_asset_ver( 'assets/css/isxf-admin.css' );

        $this->assertSame( ISXF_PLUGIN_VERSION . '.' . filemtime( ISXF_PLUGIN_DIR . 'assets/css/isxf-admin.css' ), $ver );
    }

    public function test_asset_ver_falls_back_to_plugin_version(): void {
        $this->assertSame( ISXF_PLUGIN_VERSION, isxf_asset_ver( 'assets/css/does-not-exist.css' ) );
    }
}
