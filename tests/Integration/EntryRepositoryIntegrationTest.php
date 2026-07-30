<?php
/**
 * Integration tests for ISXF\Repository\EntryRepository + its caching layer.
 *
 * Runs against the real WordPress test suite + MySQL. Covers:
 * - insert/read round-trip and filtered/paginated reads on seeded data
 * - cache hits avoiding a second SQL query (asserted via the 'query' filter)
 * - invalidation (cache version bump) on insert/update/delete/bulk-delete
 * - analytics aggregate results
 * - the Entries form-list dropdown cache + its save_post invalidation
 *
 * Requires the WordPress test suite (skipped otherwise — see tests/bootstrap.php).
 *
 * @package InsightX_Form\Tests\Integration
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

use ISXF\Repository\EntryRepository;

/**
 * @group repository
 */
class EntryRepositoryIntegrationTest extends WP_UnitTestCase {

    /** @var EntryRepository */
    private $repo;

    /** @var int Number of SQL queries seen via the 'query' filter. */
    private $query_count = 0;

    public function set_up() {
        parent::set_up();

        isxf_create_db_table();
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . EntryRepository::table_name() );
        wp_cache_flush();

        $this->repo = new EntryRepository();
        $this->query_count = 0;
        add_filter( 'query', [ $this, 'count_query' ] );
    }

    public function tear_down() {
        remove_filter( 'query', [ $this, 'count_query' ] );
        parent::tear_down();
    }

    /**
     * 'query' filter callback — counts every SQL statement wpdb executes.
     *
     * @param string $query SQL.
     * @return string
     */
    public function count_query( $query ) {
        $this->query_count++;
        return $query;
    }

    /**
     * Insert an entry row directly (fixture setup; not the code under test).
     *
     * @param array $overrides Column overrides.
     * @return int Entry ID.
     */
    private function seed_entry( $overrides = [] ) {
        global $wpdb;
        $wpdb->insert(
            EntryRepository::table_name(),
            array_merge(
                [
                    'form_id'      => 1,
                    'form_title'   => 'Test Form',
                    'entry_data'   => wp_json_encode( [ 'Name' => 'Tester' ] ),
                    'user_ip'      => '192.0.2.1',
                    'entry_status' => 'new',
                    'admin_note'   => '',
                    'created_at'   => '2026-07-20 12:00:00',
                ],
                $overrides
            )
        );
        return (int) $wpdb->insert_id;
    }

    // ------------------------------------------------------------ reads

    public function test_insert_submission_round_trip(): void {
        $form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_title' => 'Booking Form' ] );

        $result = $this->repo->insert_submission( [ 'ชื่อ' => 'สมชาย' ], $form_id, '203.0.113.7' );
        $this->assertSame( 1, $result );

        $rows = $this->repo->get_entries( 'WHERE 1=1', 50, 0 );
        $this->assertCount( 1, $rows );
        $this->assertSame( 'Booking Form', $rows[0]->form_title );
        $this->assertSame( '203.0.113.7', $rows[0]->user_ip );
        $this->assertSame( 'new', $rows[0]->entry_status );
        $this->assertSame( [ 'ชื่อ' => 'สมชาย' ], json_decode( $rows[0]->entry_data, true ) );
    }

    public function test_filtered_reads_and_pagination(): void {
        for ( $i = 1; $i <= 5; $i++ ) {
            $this->seed_entry( [ 'form_id' => ( $i <= 3 ? 10 : 20 ), 'entry_status' => ( $i <= 3 ? 'done' : 'new' ) ] );
        }

        $where_all = $this->repo->build_where_sql( [] );
        $this->assertSame( '5', $this->repo->count_where( $where_all ) );

        $where_form = $this->repo->build_where_sql( [ 'form_id' => 10 ] );
        $this->assertSame( '3', $this->repo->count_where( $where_form ) );

        $where_status = $this->repo->build_where_sql( [ 'status' => 'done' ] );
        $this->assertSame( '3', $this->repo->count_where( $where_status ) );

        $where_search = $this->repo->build_where_sql( [ 'search' => 'Tester' ] );
        $this->assertSame( '5', $this->repo->count_where( $where_search ) );

        // Pagination: 2 per page, newest first → pages of 2,2,1.
        $page1 = $this->repo->get_entries( $where_all, 2, 0 );
        $page3 = $this->repo->get_entries( $where_all, 2, 4 );
        $this->assertCount( 2, $page1 );
        $this->assertCount( 1, $page3 );
        $this->assertGreaterThan( $page1[1]->id, $page1[0]->id, 'ORDER BY id DESC' );
    }

    public function test_csv_export_batches_cover_all_rows(): void {
        for ( $i = 1; $i <= 5; $i++ ) {
            $this->seed_entry();
        }
        $where = $this->repo->build_where_sql( [] );

        $seen = [];
        $offset = 0;
        while ( true ) {
            $batch = $this->repo->get_entries( $where, 2, $offset );
            if ( empty( $batch ) ) {
                break;
            }
            foreach ( $batch as $row ) {
                $seen[] = $row->id;
            }
            $offset += 2;
        }
        $this->assertCount( 5, $seen );
        $this->assertSame( array_unique( $seen ), $seen, 'no row exported twice' );
    }

    // ------------------------------------------------------------ caching + invalidation

    public function test_status_counts_cache_hit_avoids_second_query(): void {
        $this->seed_entry( [ 'entry_status' => 'done' ] );
        $statuses = [ 'new', 'in_progress', 'done', 'junk' ];

        $before = $this->query_count;
        $first = $this->repo->get_status_counts( $statuses );
        $queries_first = $this->query_count - $before;

        $before = $this->query_count;
        $second = $this->repo->get_status_counts( $statuses );
        $queries_second = $this->query_count - $before;

        $this->assertSame( 1, $queries_first, 'first call queries the DB' );
        $this->assertSame( 0, $queries_second, 'second call must be served from wp_cache' );
        $this->assertSame( $first, $second );
        $this->assertSame( 1, $first['done'] );
        $this->assertSame( 0, $first['new'] );
    }

    public function test_invalidation_on_insert(): void {
        $form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form' ] );
        $this->assertSame( 0, $this->repo->count_total() );

        $before = $this->query_count;
        $this->repo->insert_submission( [ 'Name' => 'A' ], $form_id, '192.0.2.1' );

        $this->assertSame( 1, $this->repo->count_total(), 'stale cached count must not be served' );
        $this->assertGreaterThan( 0, $this->query_count - $before );
    }

    public function test_invalidation_on_status_update(): void {
        $id = $this->seed_entry( [ 'entry_status' => 'new' ] );
        $statuses = [ 'new', 'done' ];

        $counts = $this->repo->get_status_counts( $statuses );
        $this->assertSame( [ 'new' => 1, 'done' => 0 ], $counts );

        $this->repo->update_status( $id, 'done' );

        $before = $this->query_count;
        $counts = $this->repo->get_status_counts( $statuses );
        $this->assertSame( [ 'new' => 0, 'done' => 1 ], $counts, 'updated counts must be served after mutation' );
        $this->assertGreaterThan( 0, $this->query_count - $before, 'mutation must have invalidated the cache' );
    }

    public function test_invalidation_on_note_update_and_delete(): void {
        $id = $this->seed_entry();
        $this->assertSame( 1, $this->repo->count_total() );

        $v = EntryRepository::cache_version();
        $this->repo->update_note( $id, 'note text' );
        $this->assertSame( $v + 1, EntryRepository::cache_version() );

        $row = $this->repo->get_entries( 'WHERE 1=1', 1, 0 )[0];
        $this->assertSame( 'note text', $row->admin_note );

        $this->repo->delete( $id );
        $this->assertSame( 0, $this->repo->count_total(), 'delete must invalidate cached count' );
    }

    public function test_invalidation_on_bulk_actions(): void {
        $ids = [
            $this->seed_entry( [ 'entry_status' => 'new' ] ),
            $this->seed_entry( [ 'entry_status' => 'new' ] ),
            $this->seed_entry( [ 'entry_status' => 'new' ] ),
        ];
        $statuses = [ 'new', 'junk' ];
        $this->assertSame( [ 'new' => 3, 'junk' => 0 ], $this->repo->get_status_counts( $statuses ) );

        $this->repo->update_status_bulk( [ $ids[0], $ids[1] ], 'junk' );
        $this->assertSame( [ 'new' => 1, 'junk' => 2 ], $this->repo->get_status_counts( $statuses ) );

        $this->repo->delete_bulk( $ids );
        $this->assertSame( 0, $this->repo->count_total() );
    }

    // ------------------------------------------------------------ analytics aggregates

    public function test_analytics_aggregates(): void {
        $this->seed_entry( [ 'form_id' => 1, 'form_title' => 'Form A', 'created_at' => '2026-07-20 10:00:00', 'entry_status' => 'done' ] );
        $this->seed_entry( [ 'form_id' => 1, 'form_title' => 'Form A', 'created_at' => '2026-07-21 11:00:00', 'entry_status' => 'new' ] );
        $this->seed_entry( [ 'form_id' => 2, 'form_title' => 'Form B', 'created_at' => '2026-07-21 12:00:00', 'entry_status' => 'new' ] );
        $this->seed_entry( [ 'form_id' => 3, 'form_title' => 'Form C', 'created_at' => '2026-06-01 12:00:00', 'entry_status' => 'junk' ] );

        $this->assertSame( 4, $this->repo->count_total() );
        $this->assertSame( 3, $this->repo->count_between( '2026-07-01', '2026-07-31' ) );
        $this->assertSame( 2, $this->repo->count_on_date( '2026-07-21' ) );
        $this->assertSame( 3, $this->repo->count_since( '2026-07-01' ) );

        $daily = $this->repo->get_daily_counts( '2026-07-01', '2026-07-31' );
        $this->assertCount( 2, $daily );
        $this->assertSame( '2026-07-20', $daily[0]->date );
        $this->assertSame( '1', (string) $daily[0]->count );
        $this->assertSame( '2026-07-21', $daily[1]->date );
        $this->assertSame( '2', (string) $daily[1]->count );

        $status = $this->repo->get_status_counts_between( '2026-07-01', '2026-07-31', [ 'new', 'done', 'junk' ] );
        $this->assertSame( [ 'new' => 2, 'done' => 1, 'junk' => 0 ], $status );

        $top = $this->repo->get_top_forms( '2026-07-01', '2026-07-31' );
        $this->assertSame( 'Form A', $top[0]->form_title );
        $this->assertSame( '2', (string) $top[0]->count );

        $recent = $this->repo->get_recent_between( '2026-07-01', '2026-07-31' );
        $this->assertCount( 3, $recent );
        $this->assertSame( 'Form B', $recent[0]->form_title, 'newest first' );

        $this->assertCount( 4, $this->repo->get_recent() );
    }

    public function test_analytics_cache_hit_and_invalidation(): void {
        $this->seed_entry( [ 'created_at' => '2026-07-21 11:00:00' ] );

        $before = $this->query_count;
        $this->assertSame( 1, $this->repo->count_between( '2026-07-01', '2026-07-31' ) );
        $this->assertSame( 1, $this->query_count - $before );

        $before = $this->query_count;
        $this->assertSame( 1, $this->repo->count_between( '2026-07-01', '2026-07-31' ) );
        $this->assertSame( 0, $this->query_count - $before, 'cached aggregate must not re-query' );

        $this->seed_and_invalidate_via_repo();
        $this->assertSame( 2, $this->repo->count_between( '2026-07-01', '2026-07-31' ) );
    }

    /**
     * Mutate through the repository so the cache version is bumped.
     */
    private function seed_and_invalidate_via_repo(): void {
        $form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form' ] );
        $this->repo->insert_submission( [ 'Name' => 'B' ], $form_id, '192.0.2.2' );
    }

    // ------------------------------------------------------------ form list dropdown cache

    public function test_form_list_cache_and_save_post_invalidation(): void {
        self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_title' => 'Form One' ] );

        $entries = new \ISXF\Entries();
        $method = new ReflectionMethod( $entries, 'get_form_list' );
        $method->setAccessible( true );

        $list = $method->invoke( $entries );
        $this->assertSame( [ 'Form One' ], array_values( $list ) );
        $this->assertNotFalse( wp_cache_get( 'isxf_form_list', 'isxf' ), 'list must be cached after first read' );

        // Saving a form must invalidate the cached list via save_post_isxf_form.
        self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_title' => 'Form Two' ] );
        $this->assertFalse( wp_cache_get( 'isxf_form_list', 'isxf' ), 'save_post must invalidate the form list cache' );

        $list = $method->invoke( $entries );
        // get_posts default order is post_date DESC → newest form first.
        $this->assertSame( [ 'Form Two', 'Form One' ], array_values( $list ) );

        // Deleting a form must invalidate too.
        foreach ( $list as $id => $title ) {
            wp_delete_post( $id, true );
        }
        $this->assertFalse( wp_cache_get( 'isxf_form_list', 'isxf' ), 'deleted_post must invalidate the form list cache' );
        $this->assertSame( [], $method->invoke( $entries ) );
    }
}
