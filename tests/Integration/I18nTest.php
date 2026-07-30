<?php
/**
 * Integration tests for the i18n setup (Phase 2.5).
 *
 * Verifies the bundled Thai translation actually loads: known strings render
 * in Thai from languages/insightx-form-th.mo and fall back to the English
 * source strings when the translation is not loaded.
 *
 * Note: load_textdomain()/unload_textdomain() are used directly instead of
 * switch_to_locale() because the latter refuses th when the test WP
 * install ships no th core language pack (WP_Locale_Switcher checks
 * available_languages).
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group i18n
 */
class I18nTest extends WP_UnitTestCase {

    private const MO_FILE = ISXF_PLUGIN_ROOT . '/languages/insightx-form-th.mo';

    public function tear_down() {
        unload_textdomain( 'insightx-form' );
        parent::tear_down();
    }

    public function test_thai_mo_file_is_shipped(): void {
        $this->assertFileExists( self::MO_FILE, 'The bundled th translation must be shipped with the plugin' );
    }

    public function test_known_string_renders_thai_from_bundled_mo(): void {
        $this->assertTrue( load_textdomain( 'insightx-form', self::MO_FILE ), 'The bundled .mo must load' );

        $this->assertSame(
            'ยืนยันส่งแบบฟอร์ม',
            __( 'Submit Form', 'insightx-form' ),
            'The bundled translation must render Thai'
        );
        $this->assertSame(
            'กรุณากรอก: %s',
            __( 'Please fill in: %s', 'insightx-form' ),
            'Placeholder strings must translate with the placeholder intact'
        );
    }

    public function test_known_string_falls_back_to_english_source(): void {
        unload_textdomain( 'insightx-form' );

        $this->assertSame(
            'Submit Form',
            __( 'Submit Form', 'insightx-form' ),
            'Without the translation the English source string must be returned'
        );
        $this->assertSame(
            'Please fill in: %s',
            __( 'Please fill in: %s', 'insightx-form' )
        );
    }
}
