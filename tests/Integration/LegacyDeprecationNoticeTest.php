<?php
/**
 * Integration tests for the v1.0 legacy-deprecation admin notice (Phase 3).
 *
 * The notice must be shown only to administrators, only on plugin screens,
 * and only on sites that actually came from the legacy "acf" era (detected
 * via the isxf_legacy_acf_migration_done flag) — clean installs must never
 * be nagged. Dismissal is one-time and stored per user.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group admin-notices
 */
class LegacyDeprecationNoticeTest extends WP_UnitTestCase {

    /** @var \ISXF\Admin */
    private $admin;

    /** @var int */
    private $admin_user_id;

    public function set_up() {
        parent::set_up();
        $this->admin         = new \ISXF\Admin();
        $this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $this->admin_user_id );
        delete_option( 'isxf_legacy_acf_migration_done' );
        delete_user_meta( $this->admin_user_id, 'isxf_dismissed_legacy_deprecation_notice' );
    }

    public function tear_down() {
        delete_option( 'isxf_legacy_acf_migration_done' );
        delete_user_meta( $this->admin_user_id, 'isxf_dismissed_legacy_deprecation_notice' );
        wp_set_current_user( 0 );
        parent::tear_down();
    }

    public function test_notice_hidden_on_clean_install(): void {
        $this->assertFalse(
            $this->admin->should_show_legacy_deprecation_notice( 'edit-isxf_form' ),
            'Clean installs (no legacy migration flag) must never see the notice'
        );
    }

    public function test_notice_shown_for_legacy_site_on_plugin_screens(): void {
        update_option( 'isxf_legacy_acf_migration_done', 1 );

        $this->assertTrue( $this->admin->should_show_legacy_deprecation_notice( 'edit-isxf_form' ) );
        $this->assertTrue( $this->admin->should_show_legacy_deprecation_notice( 'isxf_form_page_isxf-global-settings' ) );
        $this->assertTrue( $this->admin->should_show_legacy_deprecation_notice( 'isxf_form_page_isxf-docs' ) );
    }

    public function test_notice_hidden_on_non_plugin_screen(): void {
        update_option( 'isxf_legacy_acf_migration_done', 1 );

        $this->assertFalse(
            $this->admin->should_show_legacy_deprecation_notice( 'dashboard' ),
            'The notice must be limited to plugin screens'
        );
    }

    public function test_notice_hidden_for_users_without_manage_options(): void {
        update_option( 'isxf_legacy_acf_migration_done', 1 );

        $subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $subscriber_id );

        $this->assertFalse( $this->admin->should_show_legacy_deprecation_notice( 'edit-isxf_form' ) );
    }

    public function test_notice_hidden_after_dismissal(): void {
        update_option( 'isxf_legacy_acf_migration_done', 1 );
        update_user_meta( $this->admin_user_id, 'isxf_dismissed_legacy_deprecation_notice', 1 );

        $this->assertFalse(
            $this->admin->should_show_legacy_deprecation_notice( 'edit-isxf_form' ),
            'A dismissed notice must stay hidden'
        );
    }

    public function test_dismissal_is_per_user(): void {
        update_option( 'isxf_legacy_acf_migration_done', 1 );
        update_user_meta( $this->admin_user_id, 'isxf_dismissed_legacy_deprecation_notice', 1 );

        $other_admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $other_admin_id );

        $this->assertTrue(
            $this->admin->should_show_legacy_deprecation_notice( 'edit-isxf_form' ),
            'Another admin who has not dismissed must still see the notice'
        );

        delete_user_meta( $other_admin_id, 'isxf_dismissed_legacy_deprecation_notice' );
    }
}
