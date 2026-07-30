<?php
/**
 * Integration test for isxf_plugin_uninstall() — every plugin artifact must go.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group uninstall
 */
class UninstallTest extends WP_UnitTestCase {

    /** @var wpdb */
    private $db;

    public function set_up() {
        parent::set_up();
        global $wpdb;
        $this->db = $wpdb;

        // Use a REAL table (not the test suite's temporary-table rewrite) so
        // the SHOW TABLES assertion after DROP is meaningful.
        remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
        remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}isxf_form_entries" );

        // Seed plugin artifacts.
        isxf_create_db_table();
        $this->db->insert(
            $wpdb->prefix . 'isxf_form_entries',
            [
                'form_id'      => 1,
                'form_title'   => 'Form',
                'entry_data'   => '{}',
                'user_ip'      => '1.2.3.4',
                'entry_status' => 'new',
                'admin_note'   => '',
                'created_at'   => current_time( 'mysql' ),
            ]
        );

        update_option( 'isxf_smtp_enable', 'yes' );
        update_option( 'isxf_smtp_host', 'smtp.example.com' );
        update_option( 'isxf_recaptcha_secret_key', 'secret' );
        update_option( 'isxf_captcha_required', 'yes' );
        update_option( 'isxf_trusted_proxies', '10.0.0.0/8' );
        update_option( 'isxf_db_version', ISXF_DB_VERSION );
        update_option( 'isxf_legacy_acf_migration_done', 1 );

        $form_post = self::factory()->post->create(
            [
                'post_type'   => 'isxf_form',
                'post_title'  => 'Form to delete',
                'post_status' => 'publish',
            ]
        );
        update_post_meta( $form_post, '_isxf_form_fields', [ [ 'label' => 'ชื่อ' ] ] );
        update_post_meta( $form_post, 'other_plugin_meta', 'must-survive' );

        set_transient( 'isxf_limit_' . md5( '1.2.3.4' ), true, 30 );
        set_transient( 'isxf_oauth_state_teststate', 'state-value', 300 );
        set_transient( 'isxf_oauth_access_token', 'token-value', 3600 );

        // Foreign artifacts that must NOT be touched.
        update_option( 'other_plugin_option', 'keep' );
        set_transient( 'other_plugin_transient', 'keep', 300 );
    }

    public function tear_down() {
        // Uninstall drops the entries table — restore it for other tests.
        isxf_create_db_table();
        delete_option( 'other_plugin_option' );
        delete_transient( 'other_plugin_transient' );
        parent::tear_down();
    }

    public function test_uninstall_removes_all_plugin_artifacts(): void {
        isxf_plugin_uninstall();

        // Entries table dropped.
        $table = $this->db->get_var( "SHOW TABLES LIKE '{$this->db->prefix}isxf_form_entries'" );
        $this->assertNull( $table, 'Entries table must be dropped' );

        // All plugin options deleted.
        foreach (
            [
                'isxf_smtp_enable', 'isxf_smtp_host', 'isxf_recaptcha_secret_key',
                'isxf_captcha_required', 'isxf_trusted_proxies', 'isxf_db_version',
                'isxf_legacy_acf_migration_done',
            ] as $option
        ) {
            $this->assertFalse( get_option( $option ), "Option {$option} must be deleted" );
        }

        // CPT posts deleted (any status).
        $remaining = get_posts(
            [
                'post_type'      => 'isxf_form',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]
        );
        $this->assertSame( [], $remaining, 'All isxf_form posts must be deleted' );

        // Plugin post meta deleted, foreign meta preserved.
        $isxf_meta = (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->db->postmeta} WHERE meta_key LIKE '_isxf_%'" );
        $this->assertSame( 0, $isxf_meta, 'All _isxf_* post meta must be deleted' );

        // Plugin transients deleted (checked at DB level to bypass caches).
        $transients = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->options}
             WHERE option_name LIKE '_transient_isxf_limit_%'
                OR option_name LIKE '_transient_timeout_isxf_limit_%'
                OR option_name LIKE '_transient_isxf_oauth_state_%'
                OR option_name LIKE '_transient_timeout_isxf_oauth_state_%'"
        );
        $this->assertSame( 0, $transients, 'Rate-limit and OAuth-state transients must be deleted' );
        $this->assertFalse( get_transient( 'isxf_oauth_access_token' ), 'OAuth token transient must be deleted' );
    }

    public function test_uninstall_preserves_foreign_artifacts(): void {
        isxf_plugin_uninstall();

        $this->assertSame( 'keep', get_option( 'other_plugin_option' ) );
        $this->assertSame( 'keep', get_transient( 'other_plugin_transient' ) );
    }
}
