<?php
/**
 * Pure unit tests for ISXF\Repository\EntryRepository (no WordPress required).
 *
 * Uses a fake $wpdb that mimics prepare()/esc_like() placeholder semantics
 * and captures every SQL string, plus the in-memory wp_cache stubs from
 * tests/Unit/stubs.php.
 *
 * Covers: WHERE-builder golden SQL shapes (pinned against the pre-refactor
 * inline queries), list/pagination SQL shapes, and the cache-version
 * invalidation scheme (cache hit avoids the second query; mutations bump
 * the version so aggregates re-query).
 *
 * @package InsightX_Form\Tests\Unit
 */

require_once dirname( __DIR__, 3 ) . '/src/Repository/EntryRepository.php';

use ISXF\Repository\EntryRepository;
use PHPUnit\Framework\TestCase;

/**
 * Minimal wpdb stand-in: captures SQL, returns canned results.
 */
class ISXF_Test_Fake_WPDB {

    /** @var string */
    public $prefix = 'wp_';

    /** @var string[] Every SQL string passed to get_results/get_var/query. */
    public $queries = [];

    /** @var array Queue of return values for get_results(). */
    public $get_results_queue = [];

    /** @var mixed Return value for get_var(). */
    public $get_var_return = '0';

    /** @var array Captured update() calls. */
    public $updates = [];

    /**
     * Mimics wpdb::esc_like() (addcslashes on _ % \).
     *
     * @param string $text Raw text.
     * @return string
     */
    public function esc_like( $text ) {
        return addcslashes( $text, '_%\\' );
    }

    /**
     * Mimics wpdb::prepare() for the %d/%s subset: %d → intval, %s → quoted.
     *
     * @param string    $query Format string.
     * @param array|mixed $args Placeholder values (array or variadic).
     * @return string
     */
    public function prepare( $query, $args = null ) {
        $args = is_array( $args ) ? $args : array_slice( func_get_args(), 1 );
        $i = 0;
        return preg_replace_callback(
            '/%[sd]/',
            function ( $m ) use ( $args, &$i ) {
                $v = $args[ $i++ ];
                return '%d' === $m[0] ? (string) intval( $v ) : "'" . addslashes( (string) $v ) . "'";
            },
            $query
        );
    }

    /**
     * @param string $query SQL.
     * @return array
     */
    public function get_results( $query ) {
        $this->queries[] = $query;
        $next = array_shift( $this->get_results_queue );
        return is_array( $next ) ? $next : [];
    }

    /**
     * @param string $query SQL.
     * @return mixed
     */
    public function get_var( $query ) {
        $this->queries[] = $query;
        return $this->get_var_return;
    }

    /**
     * @param string $query SQL.
     * @return int
     */
    public function query( $query ) {
        $this->queries[] = $query;
        return 1;
    }

    /**
     * @return int
     */
    public function update() {
        $this->updates[] = func_get_args();
        return 1;
    }

    /**
     * @return int
     */
    public function delete() {
        return 1;
    }
}

/**
 * @covers \ISXF\Repository\EntryRepository
 */
class EntryRepositoryTest extends TestCase {

    /** @var ISXF_Test_Fake_WPDB */
    private $wpdb;

    /** @var EntryRepository */
    private $repo;

    /** @var mixed Previous $GLOBALS['wpdb'] (real wpdb under the integration env). */
    private $previous_wpdb;

    protected function setUp(): void {
        $this->previous_wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
        $this->wpdb = new ISXF_Test_Fake_WPDB();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['isxf_test_cache'] = [];
        // Under the integration environment the REAL wp_cache functions are
        // loaded (stubs.php is not); flush so tests stay isolated there too.
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        $this->repo = new EntryRepository();
    }

    protected function tearDown(): void {
        if ( null !== $this->previous_wpdb ) {
            $GLOBALS['wpdb'] = $this->previous_wpdb;
        } else {
            unset( $GLOBALS['wpdb'] );
        }
        unset( $GLOBALS['isxf_test_cache'] );
    }

    // ------------------------------------------------------------ WHERE builder golden shapes

    /**
     * Golden SQL shapes, pinned to the pre-refactor Entries::build_query_args()
     * output (same $wpdb->prepare + esc_like calls, same clause order).
     */
    public static function where_sql_golden_cases(): array {
        return [
            'no filters' => [
                [],
                'WHERE 1=1',
            ],
            'form filter' => [
                [ 'form_id' => 5 ],
                'WHERE 1=1 AND form_id = 5',
            ],
            'status filter' => [
                [ 'status' => 'done' ],
                "WHERE 1=1 AND entry_status = 'done'",
            ],
            'date range' => [
                [ 'start_date' => '2026-07-01', 'end_date' => '2026-07-31' ],
                "WHERE 1=1 AND DATE(created_at) >= '2026-07-01' AND DATE(created_at) <= '2026-07-31'",
            ],
            'search' => [
                [ 'search' => 'somchai' ],
                "WHERE 1=1 AND (entry_data LIKE '%somchai%' OR user_ip LIKE '%somchai%' OR form_title LIKE '%somchai%')",
            ],
            'search escapes LIKE wildcards' => [
                [ 'search' => '10%_' ],
                // esc_like() adds \ before %/_, then prepare()'s real_escape
                // escapes each \ again — matches production wpdb output.
                "WHERE 1=1 AND (entry_data LIKE '%10\\\\%\\\\_%' OR user_ip LIKE '%10\\\\%\\\\_%' OR form_title LIKE '%10\\\\%\\\\_%')",
            ],
            'all filters combined keep clause order' => [
                [
                    'form_id'    => 7,
                    'status'     => 'in_progress',
                    'start_date' => '2026-07-01',
                    'end_date'   => '2026-07-31',
                    'search'     => 'x',
                ],
                "WHERE 1=1 AND form_id = 7 AND entry_status = 'in_progress' AND DATE(created_at) >= '2026-07-01' AND DATE(created_at) <= '2026-07-31' AND (entry_data LIKE '%x%' OR user_ip LIKE '%x%' OR form_title LIKE '%x%')",
            ],
        ];
    }

    /**
     * @dataProvider where_sql_golden_cases
     */
    public function test_build_where_sql_matches_golden( array $args, string $expected ): void {
        $this->assertSame( $expected, $this->repo->build_where_sql( $args ) );
    }

    // ------------------------------------------------------------ list / pagination SQL shapes

    public function test_count_where_sql_shape(): void {
        $this->repo->count_where( 'WHERE 1=1' );
        $this->assertSame(
            'SELECT COUNT(id) FROM wp_isxf_form_entries WHERE 1=1',
            $this->wpdb->queries[0]
        );
    }

    public function test_get_entries_sql_shape(): void {
        $this->repo->get_entries( 'WHERE 1=1', 50, 100 );
        $this->assertSame(
            'SELECT * FROM wp_isxf_form_entries WHERE 1=1 ORDER BY id DESC LIMIT 50 OFFSET 100',
            $this->wpdb->queries[0]
        );
    }

    public function test_delete_bulk_sql_shape(): void {
        $this->repo->delete_bulk( [ 1, 2, 3 ] );
        $this->assertSame(
            'DELETE FROM wp_isxf_form_entries WHERE id IN (1,2,3)',
            $this->wpdb->queries[0]
        );
    }

    // ------------------------------------------------------------ status counts + caching

    private function seed_status_rows(): void {
        $this->wpdb->get_results_queue[] = [
            (object) [ 'entry_status' => 'done', 'cnt' => '3' ],
            (object) [ 'entry_status' => 'unknown_legacy', 'cnt' => '9' ],
        ];
    }

    public function test_status_counts_zero_fills_and_ignores_unknown_statuses(): void {
        $this->seed_status_rows();
        $counts = $this->repo->get_status_counts( [ 'new', 'done', 'junk' ] );
        $this->assertSame( [ 'new' => 0, 'done' => 3, 'junk' => 0 ], $counts );
        $this->assertSame(
            'SELECT entry_status, COUNT(id) as cnt FROM wp_isxf_form_entries GROUP BY entry_status',
            $this->wpdb->queries[0]
        );
    }

    public function test_status_counts_cache_hit_avoids_second_query(): void {
        $this->seed_status_rows();
        $this->repo->get_status_counts( [ 'new', 'done' ] );
        $this->assertCount( 1, $this->wpdb->queries );

        $cached = $this->repo->get_status_counts( [ 'new', 'done' ] );
        $this->assertCount( 1, $this->wpdb->queries, 'second call must be served from wp_cache' );
        $this->assertSame( [ 'new' => 0, 'done' => 3 ], $cached );
    }

    public function test_cache_version_bump_forces_requery(): void {
        $this->seed_status_rows();
        $this->repo->get_status_counts( [ 'new' ] );
        $this->assertCount( 1, $this->wpdb->queries );

        EntryRepository::bump_cache_version();

        $this->seed_status_rows();
        $this->repo->get_status_counts( [ 'new' ] );
        $this->assertCount( 2, $this->wpdb->queries, 'version bump must invalidate cached aggregates' );
    }

    // ------------------------------------------------------------ mutation invalidation

    public function test_mutations_bump_cache_version(): void {
        $v0 = EntryRepository::cache_version();

        $this->repo->update_status( 1, 'done' );
        $this->assertSame( $v0 + 1, EntryRepository::cache_version(), 'update_status must bump' );

        $this->repo->update_note( 1, 'hello' );
        $this->assertSame( $v0 + 2, EntryRepository::cache_version(), 'update_note must bump' );

        $this->repo->delete( 1 );
        $this->assertSame( $v0 + 3, EntryRepository::cache_version(), 'delete must bump' );

        $this->repo->delete_bulk( [ 1, 2 ] );
        $this->assertSame( $v0 + 4, EntryRepository::cache_version(), 'delete_bulk must bump' );

        $this->repo->update_status_bulk( [ 1, 2 ], 'junk' );
        $this->assertSame( $v0 + 5, EntryRepository::cache_version(), 'update_status_bulk must bump' );
    }

    public function test_update_status_bulk_keeps_per_id_update_semantics(): void {
        $this->repo->update_status_bulk( [ 5, 6 ], 'done' );
        $this->assertCount( 2, $this->wpdb->updates );
        $this->assertSame( [ 'id' => 5 ], $this->wpdb->updates[0][2] );
        $this->assertSame( [ 'id' => 6 ], $this->wpdb->updates[1][2] );
    }

    // ------------------------------------------------------------ cached aggregates

    public function test_count_total_is_cached(): void {
        $this->wpdb->get_var_return = '42';
        $this->assertSame( 42, $this->repo->count_total() );
        $this->assertSame( 42, $this->repo->count_total() );
        $this->assertCount( 1, $this->wpdb->queries );
        $this->assertSame( 'SELECT COUNT(id) FROM wp_isxf_form_entries', $this->wpdb->queries[0] );
    }

    public function test_analytics_count_sql_shapes(): void {
        $this->repo->count_between( '2026-07-01', '2026-07-31' );
        $this->repo->count_on_date( '2026-07-22' );
        $this->repo->count_since( '2026-07-15' );

        $this->assertSame(
            "SELECT COUNT(id) FROM wp_isxf_form_entries WHERE DATE(created_at) BETWEEN '2026-07-01' AND '2026-07-31'",
            $this->wpdb->queries[0]
        );
        $this->assertSame(
            "SELECT COUNT(id) FROM wp_isxf_form_entries WHERE DATE(created_at) = '2026-07-22'",
            $this->wpdb->queries[1]
        );
        $this->assertSame(
            "SELECT COUNT(id) FROM wp_isxf_form_entries WHERE DATE(created_at) >= '2026-07-15'",
            $this->wpdb->queries[2]
        );
    }

    public function test_analytics_aggregate_sql_shapes(): void {
        $this->repo->get_daily_counts( '2026-07-01', '2026-07-31' );
        $this->repo->get_status_counts_between( '2026-07-01', '2026-07-31', [ 'new' ] );
        $this->repo->get_top_forms( '2026-07-01', '2026-07-31' );
        $this->repo->get_recent_between( '2026-07-01', '2026-07-31' );
        $this->repo->get_recent();

        $this->assertSame(
            "SELECT DATE(created_at) as date, COUNT(id) as count FROM wp_isxf_form_entries WHERE DATE(created_at) BETWEEN '2026-07-01' AND '2026-07-31' GROUP BY DATE(created_at) ORDER BY date ASC",
            $this->wpdb->queries[0]
        );
        $this->assertSame(
            "SELECT entry_status, COUNT(id) as cnt FROM wp_isxf_form_entries WHERE DATE(created_at) BETWEEN '2026-07-01' AND '2026-07-31' GROUP BY entry_status",
            $this->wpdb->queries[1]
        );
        $this->assertSame(
            "SELECT form_title, COUNT(id) as count FROM wp_isxf_form_entries WHERE DATE(created_at) BETWEEN '2026-07-01' AND '2026-07-31' GROUP BY form_id ORDER BY count DESC LIMIT 10",
            $this->wpdb->queries[2]
        );
        $this->assertSame(
            "SELECT form_title, entry_status, user_ip, created_at FROM wp_isxf_form_entries WHERE DATE(created_at) BETWEEN '2026-07-01' AND '2026-07-31' ORDER BY id DESC LIMIT 10",
            $this->wpdb->queries[3]
        );
        $this->assertSame(
            'SELECT * FROM wp_isxf_form_entries ORDER BY id DESC LIMIT 5',
            $this->wpdb->queries[4]
        );
    }

    public function test_analytics_aggregates_are_cached_and_invalidated(): void {
        $this->repo->get_daily_counts( '2026-07-01', '2026-07-31' );
        $this->repo->get_daily_counts( '2026-07-01', '2026-07-31' );
        $this->assertCount( 1, $this->wpdb->queries );

        // A mutation anywhere in the entries table invalidates every aggregate.
        $this->repo->update_status( 1, 'done' );

        $this->repo->get_daily_counts( '2026-07-01', '2026-07-31' );
        $this->assertCount( 2, $this->wpdb->queries );
    }
}
