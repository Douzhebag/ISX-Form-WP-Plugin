<?php
/**
 * Integration tests for the legacy acf → isxf migration and DB upgrade path.
 *
 * Simulates a pre-v0.5.0 database (acf_form_entries table, acf_form CPT,
 * _acf_* meta, acf_* options, isxf_db_version 1.0) and verifies that
 * isxf_maybe_upgrade_db() migrates everything exactly once, touches only
 * whitelisted keys, and is idempotent.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group migration
 */
class MigrationTest extends WP_UnitTestCase {

    /** @var wpdb */
    private $db;

    /** @var string */
    private $old_table;

    /** @var string */
    private $new_table;

    public function set_up() {
        parent::set_up();
        global $wpdb;
        $this->db        = $wpdb;
        $this->old_table = $wpdb->prefix . 'acf_form_entries';
        $this->new_table = $wpdb->prefix . 'isxf_form_entries';

        // The WP test suite rewrites CREATE/DROP TABLE to TEMPORARY tables,
        // which are invisible to SHOW TABLES — but the migration code (and
        // dbDelta) discovers tables via SHOW TABLES. Use real tables here.
        remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
        remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );

        // Simulate "old site" state: no new table, legacy table with data.
        $this->db->query( "DROP TABLE IF EXISTS {$this->new_table}" );
        $this->db->query( "DROP TABLE IF EXISTS {$this->old_table}" );
        $this->create_legacy_table();
        $this->assertSame(
            $this->old_table,
            $this->db->get_var( "SHOW TABLES LIKE '{$this->old_table}'" ),
            'Legacy fixture table must be created (check schema compatibility). DB error: ' . $this->db->last_error
        );
        delete_option( 'isxf_legacy_acf_migration_done' );
        update_option( 'isxf_db_version', '1.0' );
    }

    public function tear_down() {
        $this->db->query( "DROP TABLE IF EXISTS {$this->old_table}" );
        delete_option( 'isxf_legacy_acf_migration_done' );
        delete_option( 'isxf_smtp_host' );
        delete_option( 'isxf_recaptcha_site_key' );
        delete_option( 'acf_smtp_host' );
        delete_option( 'acf_recaptcha_site_key' );
        // Restore a healthy state for other tests.
        isxf_create_db_table();
        parent::tear_down();
    }

    private function create_legacy_table(): void {
        $charset_collate = $this->db->get_charset_collate();
        // NOTE: no DEFAULT on the text column — MySQL 5.7+/8.0 in strict mode
        // rejects literal defaults on TEXT (error 1101), and this matches what
        // dbDelta actually creates for the live table.
        $result = $this->db->query(
            "CREATE TABLE {$this->old_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                form_id bigint(20) NOT NULL,
                form_title text NOT NULL,
                entry_data longtext NOT NULL,
                user_ip varchar(100) DEFAULT '' NOT NULL,
                entry_status varchar(20) DEFAULT 'new' NOT NULL,
                admin_note text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;"
        );
        if ( false === $result ) {
            $this->fail( 'Legacy fixture CREATE TABLE failed: ' . $this->db->last_error );
        }
    }

    private function seed_legacy_rows( int $count = 2 ): void {
        for ( $i = 1; $i <= $count; $i++ ) {
            $this->db->insert(
                $this->old_table,
                [
                    'form_id'      => 100 + $i,
                    'form_title'   => 'Legacy Form ' . $i,
                    'entry_data'   => wp_json_encode( [ 'ชื่อ' => 'ลูกค้า ' . $i ] ),
                    'user_ip'      => '10.0.0.' . $i,
                    'entry_status' => 'new',
                    'admin_note'   => '',
                    'created_at'   => '2026-01-0' . $i . ' 10:00:00',
                ]
            );
        }
    }

    private function table_count( string $table ): int {
        return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public function test_legacy_table_data_is_copied_and_preserved(): void {
        $this->seed_legacy_rows( 2 );

        isxf_maybe_upgrade_db();

        $this->assertSame( 2, $this->table_count( $this->new_table ), 'Legacy rows must be copied to the new table' );

        $row = $this->db->get_row( "SELECT * FROM {$this->new_table} ORDER BY id ASC LIMIT 1", ARRAY_A );
        $this->assertSame( 'Legacy Form 1', $row['form_title'] );
        $this->assertSame( '10.0.0.1', $row['user_ip'] );
        $this->assertSame( '2026-01-01 10:00:00', $row['created_at'] );
        $decoded = json_decode( $row['entry_data'], true );
        $this->assertSame( 'ลูกค้า 1', $decoded['ชื่อ'], 'Unicode entry data must survive the migration' );

        // Old table must NOT be dropped — rollback path stays intact.
        $this->assertSame(
            $this->old_table,
            $this->db->get_var( "SHOW TABLES LIKE '{$this->old_table}'" ),
            'Legacy table must be kept after migration'
        );
    }

    public function test_existing_new_table_data_is_never_overwritten(): void {
        $this->seed_legacy_rows( 2 );

        // New table already has live data (e.g. entries submitted since v0.5.0).
        isxf_create_db_table();
        $this->db->insert(
            $this->new_table,
            [
                'form_id'      => 999,
                'form_title'   => 'Current Form',
                'entry_data'   => '{}',
                'user_ip'      => '192.168.1.1',
                'entry_status' => 'done',
                'admin_note'   => 'keep me',
                'created_at'   => '2026-06-01 12:00:00',
            ]
        );

        isxf_run_legacy_acf_migration();

        $this->assertSame( 1, $this->table_count( $this->new_table ), 'Copy must be skipped when the new table already has rows' );
        $note = $this->db->get_var( "SELECT admin_note FROM {$this->new_table} WHERE form_id = 999" );
        $this->assertSame( 'keep me', $note );
    }

    public function test_cpt_and_whitelisted_meta_are_renamed(): void {
        $post_id = self::factory()->post->create(
            [
                'post_type'   => 'acf_form',
                'post_title'  => 'Legacy Contact Form',
                'post_status' => 'publish',
            ]
        );
        update_post_meta( $post_id, '_acf_form_fields', [ [ 'label' => 'ชื่อ' ] ] );
        update_post_meta( $post_id, '_acf_form_email_type', 'inquiry' );
        update_post_meta( $post_id, '_acf_form_email_subject', 'Hi' );
        update_post_meta( $post_id, '_acf_form_email_body', 'Body' );
        // Meta key with the same prefix but belonging to another plugin.
        update_post_meta( $post_id, '_acf_other_plugin_field', 'do-not-touch' );

        isxf_run_legacy_acf_migration();

        $this->assertSame( 'isxf_form', $this->db->get_var( "SELECT post_type FROM {$this->db->posts} WHERE ID = {$post_id}" ) );

        // The 4 whitelisted keys are renamed...
        $renamed = [ '_isxf_form_fields', '_isxf_form_email_type', '_isxf_form_email_subject', '_isxf_form_email_body' ];
        foreach ( $renamed as $key ) {
            $count = (int) $this->db->get_var(
                $this->db->prepare( "SELECT COUNT(*) FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key )
            );
            $this->assertSame( 1, $count, "Expected meta key {$key} to exist" );
        }
        $old = [ '_acf_form_fields', '_acf_form_email_type', '_acf_form_email_subject', '_acf_form_email_body' ];
        foreach ( $old as $key ) {
            $count = (int) $this->db->get_var(
                $this->db->prepare( "SELECT COUNT(*) FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key )
            );
            $this->assertSame( 0, $count, "Old meta key {$key} should be gone" );
        }

        // ...but a foreign _acf_* key must survive untouched.
        $foreign = $this->db->get_var(
            $this->db->prepare( "SELECT meta_value FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key = '_acf_other_plugin_field'", $post_id )
        );
        $this->assertSame( 'do-not-touch', $foreign, 'Meta keys outside the whitelist must not be renamed' );
    }

    public function test_legacy_options_are_renamed(): void {
        add_option( 'acf_smtp_host', 'smtp.legacy.example' );
        add_option( 'acf_recaptcha_site_key', 'site-key-123' );
        add_option( 'unrelated_option', 'keep' );

        isxf_run_legacy_acf_migration();

        // Options are renamed via raw SQL — assert at the DB level to avoid stale option cache.
        $new_host = $this->db->get_var( "SELECT option_value FROM {$this->db->options} WHERE option_name = 'isxf_smtp_host'" );
        $this->assertSame( 'smtp.legacy.example', $new_host );
        $old_host = $this->db->get_var( "SELECT option_value FROM {$this->db->options} WHERE option_name = 'acf_smtp_host'" );
        $this->assertNull( $old_host, 'Old option name should be gone' );

        $new_key = $this->db->get_var( "SELECT option_value FROM {$this->db->options} WHERE option_name = 'isxf_recaptcha_site_key'" );
        $this->assertSame( 'site-key-123', $new_key );

        $unrelated = $this->db->get_var( "SELECT option_value FROM {$this->db->options} WHERE option_name = 'unrelated_option'" );
        $this->assertSame( 'keep', $unrelated );
    }

    public function test_upgrade_sets_flag_and_db_version_and_is_idempotent(): void {
        $this->seed_legacy_rows( 2 );

        isxf_maybe_upgrade_db();

        $this->assertEquals( 1, (int) get_option( 'isxf_legacy_acf_migration_done' ), 'One-shot flag must be set' );
        $this->assertSame( ISXF_DB_VERSION, get_option( 'isxf_db_version' ), 'DB version must be bumped to the plugin constant' );

        $count_after_first = $this->table_count( $this->new_table );

        // Second run: flag is set, so the migration must not run again.
        isxf_maybe_upgrade_db();
        $this->assertSame( $count_after_first, $this->table_count( $this->new_table ), 'Second run must be a no-op' );

        // Even a direct re-run of the migration itself must not duplicate rows.
        isxf_run_legacy_acf_migration();
        $this->assertSame( $count_after_first, $this->table_count( $this->new_table ), 'Migration itself must be idempotent' );
    }

    public function test_upgrade_creates_db_11_indexes(): void {
        isxf_maybe_upgrade_db();

        $indexes = $this->db->get_col( "SHOW INDEX FROM {$this->new_table}", 2 );
        $indexes = array_unique( $indexes );
        foreach ( [ 'form_id', 'entry_status', 'created_at' ] as $expected ) {
            $this->assertContains( $expected, $indexes, "Missing index {$expected} (DB version 1.1)" );
        }
    }
}
