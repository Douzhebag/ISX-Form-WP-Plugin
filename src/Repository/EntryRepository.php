<?php

namespace ISXF\Repository;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Repository for the {prefix}isxf_form_entries table (Phase 2.3 — see docs/ROADMAP.md).
 *
 * Owns ALL runtime SQL touching the entries table: insert, filtered/paginated
 * reads, status/note updates, single & bulk deletes, and the analytics
 * aggregations. Callers (Entries, AjaxHandler) keep request parsing,
 * formatting and presentation; query shapes are byte-identical to the
 * pre-refactor inline SQL.
 *
 * Caching: aggregate reads (status counts, analytics) go through wp_cache in
 * the non-persistent group 'isxf'. Every cache key embeds a version counter
 * (isxf_entries_cache_version) that is bumped by every entry mutation, so
 * stale aggregates are never served — no TTL-based invalidation.
 */
class EntryRepository {

    const CACHE_GROUP = 'isxf';
    const VERSION_KEY = 'isxf_entries_cache_version';

    /**
     * Centralized entries table name resolution.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'isxf_form_entries';
    }

    /**
     * Current cache version (salt embedded in every cached key).
     *
     * @return int
     */
    public static function cache_version() {
        $version = wp_cache_get( self::VERSION_KEY, self::CACHE_GROUP );
        if ( false === $version ) {
            $version = 1;
            wp_cache_set( self::VERSION_KEY, $version, self::CACHE_GROUP );
        }
        return (int) $version;
    }

    /**
     * Invalidate every cached aggregate. Must be called by every entry
     * mutation (insert, status/note update, delete, bulk delete, import).
     */
    public static function bump_cache_version() {
        wp_cache_set( self::VERSION_KEY, self::cache_version() + 1, self::CACHE_GROUP );
    }

    /**
     * Cache helper: return the cached value for $key (salted with the current
     * cache version), or compute it via $callback and store it.
     *
     * @param string   $key      Base cache key (version is appended).
     * @param callable $callback Computes the value on a cache miss.
     * @return mixed
     */
    private function remember( $key, $callback ) {
        $cache_key = $key . ':v' . self::cache_version();
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }
        $value = call_user_func( $callback );
        wp_cache_set( $cache_key, $value, self::CACHE_GROUP );
        return $value;
    }

    // ---------------------------------------------------------------- mutations

    /**
     * Insert a form submission as a new entry (was Entries::save_to_db).
     *
     * @param array  $entry_data Sanitized field label => value map.
     * @param int    $form_id    Form post ID.
     * @param string $user_ip    Submitter IP.
     * @return int|false Rows inserted (1) or false on failure.
     */
    public function insert_submission( $entry_data, $form_id, $user_ip ) {
        global $wpdb;
        $result = $wpdb->insert( self::table_name(), [
            'form_id'      => $form_id,
            'form_title'   => get_the_title( $form_id ),
            'entry_data'   => wp_json_encode( $entry_data, JSON_UNESCAPED_UNICODE ),
            'user_ip'      => $user_ip,
            'entry_status' => 'new',
            'admin_note'   => '',
            'created_at'   => current_datetime()->format( 'Y-m-d H:i:s' )
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s'] );
        self::bump_cache_version();
        return $result;
    }

    /**
     * Update a single entry's status.
     *
     * @param int    $entry_id Entry ID.
     * @param string $status   New status (validated by the caller).
     * @return int|false Rows updated or false on failure.
     */
    public function update_status( $entry_id, $status ) {
        global $wpdb;
        $result = $wpdb->update( self::table_name(), [ 'entry_status' => $status ], [ 'id' => $entry_id ], ['%s'], ['%d'] );
        self::bump_cache_version();
        return $result;
    }

    /**
     * Update the status of many entries (bulk action). Keeps the original
     * per-id UPDATE semantics.
     *
     * @param int[]  $ids    Entry IDs.
     * @param string $status New status (validated by the caller).
     */
    public function update_status_bulk( $ids, $status ) {
        global $wpdb;
        foreach ( $ids as $id ) {
            $wpdb->update( self::table_name(), [ 'entry_status' => $status ], [ 'id' => $id ], ['%s'], ['%d'] );
        }
        self::bump_cache_version();
    }

    /**
     * Update a single entry's admin note.
     *
     * @param int    $entry_id Entry ID.
     * @param string $note     New note (sanitized by the caller).
     * @return int|false Rows updated or false on failure.
     */
    public function update_note( $entry_id, $note ) {
        global $wpdb;
        $result = $wpdb->update( self::table_name(), [ 'admin_note' => $note ], [ 'id' => $entry_id ], ['%s'], ['%d'] );
        self::bump_cache_version();
        return $result;
    }

    /**
     * Delete a single entry.
     *
     * @param int $entry_id Entry ID.
     * @return int|false Rows deleted or false on failure.
     */
    public function delete( $entry_id ) {
        global $wpdb;
        $result = $wpdb->delete( self::table_name(), [ 'id' => $entry_id ], ['%d'] );
        self::bump_cache_version();
        return $result;
    }

    /**
     * Delete many entries in one query (bulk action).
     *
     * @param int[] $ids Entry IDs (already intval'd by the caller).
     * @return int|false Rows deleted or false on failure.
     */
    public function delete_bulk( $ids ) {
        global $wpdb;
        if ( empty( $ids ) ) {
            return 0;
        }
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $result = $wpdb->query( $wpdb->prepare("DELETE FROM " . self::table_name() . " WHERE id IN ($ids_placeholder)", $ids) );
        self::bump_cache_version();
        return $result;
    }

    // ---------------------------------------------------------------- list page / export

    /**
     * Build the prepared WHERE clause for the entries list / CSV export
     * (moved verbatim from Entries::build_query_args; the caller validates
     * the status against the known status map and sanitizes all input).
     *
     * @param array $args {
     *     @type int    $form_id    Filter by form (0 = all).
     *     @type string $status     Filter by status ('' = all).
     *     @type string $start_date Inclusive lower bound on DATE(created_at).
     *     @type string $end_date   Inclusive upper bound on DATE(created_at).
     *     @type string $search     LIKE match on entry_data/user_ip/form_title.
     * }
     * @return string Prepared WHERE clause (starting with "WHERE").
     */
    public function build_where_sql( $args ) {
        global $wpdb;
        $args = array_merge( [
            'form_id'    => 0,
            'status'     => '',
            'start_date' => '',
            'end_date'   => '',
            'search'     => '',
        ], (array) $args );

        $where_clauses = ["1=1"];
        $where_values = [];

        if ( $args['form_id'] ) {
            $where_clauses[] = "form_id = %d";
            $where_values[] = $args['form_id'];
        }
        if ( $args['status'] ) {
            $where_clauses[] = "entry_status = %s";
            $where_values[] = $args['status'];
        }
        if ( $args['start_date'] ) {
            $where_clauses[] = "DATE(created_at) >= %s";
            $where_values[] = $args['start_date'];
        }
        if ( $args['end_date'] ) {
            $where_clauses[] = "DATE(created_at) <= %s";
            $where_values[] = $args['end_date'];
        }
        if ( $args['search'] ) {
            $where_clauses[] = "(entry_data LIKE %s OR user_ip LIKE %s OR form_title LIKE %s)";
            $like_s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_values[] = $like_s;
            $where_values[] = $like_s;
            $where_values[] = $like_s;
        }

        $where_sql = "WHERE " . implode( " AND ", $where_clauses );

        if ( !empty($where_values) ) {
            $where_sql = $wpdb->prepare( $where_sql, $where_values );
        }

        return $where_sql;
    }

    /**
     * Total number of entries matching a WHERE clause (list pagination).
     *
     * @param string $where_sql Prepared WHERE clause from build_where_sql().
     * @return string|null Raw $wpdb->get_var() result (numeric string).
     */
    public function count_where( $where_sql ) {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(id) FROM " . self::table_name() . " $where_sql" );
    }

    /**
     * One page/batch of entries matching a WHERE clause, newest first.
     * Serves both the list page pagination and the CSV export batches.
     *
     * @param string $where_sql Prepared WHERE clause from build_where_sql().
     * @param int    $limit     Rows to fetch.
     * @param int    $offset    Rows to skip.
     * @return array Entry row objects.
     */
    public function get_entries( $where_sql, $limit, $offset ) {
        global $wpdb;
        $query = "SELECT * FROM " . self::table_name() . " $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        return $wpdb->get_results( $wpdb->prepare( $query, $limit, $offset ) );
    }

    // ---------------------------------------------------------------- cached aggregates

    /**
     * Entry counts per status over ALL entries, zero-filled for every
     * given status (was computed inline in render_page/dashboard widget).
     *
     * @param string[] $statuses Known statuses to include in the result.
     * @return int[] status => count.
     */
    public function get_status_counts( $statuses ) {
        $statuses = (array) $statuses;
        return $this->remember( 'status_counts:' . md5( serialize( $statuses ) ), function () use ( $statuses ) {
            global $wpdb;
            $counts = array_fill_keys( $statuses, 0 );
            $rows = $wpdb->get_results( "SELECT entry_status, COUNT(id) as cnt FROM " . self::table_name() . " GROUP BY entry_status" );
            foreach ( $rows as $row ) {
                if ( isset($counts[ $row->entry_status ]) ) {
                    $counts[ $row->entry_status ] = (int) $row->cnt;
                }
            }
            return $counts;
        } );
    }

    /**
     * Entry counts per status within a date range (analytics).
     *
     * @param string   $start    Inclusive start date (Y-m-d).
     * @param string   $end      Inclusive end date (Y-m-d).
     * @param string[] $statuses Known statuses to include in the result.
     * @return int[] status => count.
     */
    public function get_status_counts_between( $start, $end, $statuses ) {
        $statuses = (array) $statuses;
        return $this->remember( 'status_counts_between:' . $start . ':' . $end . ':' . md5( serialize( $statuses ) ), function () use ( $start, $end, $statuses ) {
            global $wpdb;
            $counts = array_fill_keys( $statuses, 0 );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT entry_status, COUNT(id) as cnt FROM " . self::table_name() . " WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY entry_status",
                $start, $end
            ) );
            foreach ( $rows as $row ) {
                if ( isset($counts[ $row->entry_status ]) ) {
                    $counts[ $row->entry_status ] = (int) $row->cnt;
                }
            }
            return $counts;
        } );
    }

    /**
     * Total number of entries (analytics/dashboard).
     *
     * @return int
     */
    public function count_total() {
        return $this->remember( 'count_total', function () {
            global $wpdb;
            return (int) $wpdb->get_var( "SELECT COUNT(id) FROM " . self::table_name() );
        } );
    }

    /**
     * Number of entries created on a single date (analytics/dashboard).
     *
     * @param string $date Date (Y-m-d).
     * @return int
     */
    public function count_on_date( $date ) {
        return $this->remember( 'count_on:' . $date, function () use ( $date ) {
            global $wpdb;
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::table_name() . " WHERE DATE(created_at) = %s", $date ) );
        } );
    }

    /**
     * Number of entries created on or after a date (dashboard).
     *
     * @param string $date Inclusive start date (Y-m-d).
     * @return int
     */
    public function count_since( $date ) {
        return $this->remember( 'count_since:' . $date, function () use ( $date ) {
            global $wpdb;
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::table_name() . " WHERE DATE(created_at) >= %s", $date ) );
        } );
    }

    /**
     * Number of entries created within a date range (analytics).
     *
     * @param string $start Inclusive start date (Y-m-d).
     * @param string $end   Inclusive end date (Y-m-d).
     * @return int
     */
    public function count_between( $start, $end ) {
        return $this->remember( 'count_between:' . $start . ':' . $end, function () use ( $start, $end ) {
            global $wpdb;
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . self::table_name() . " WHERE DATE(created_at) BETWEEN %s AND %s", $start, $end ) );
        } );
    }

    /**
     * Per-day entry counts within a date range (analytics line chart).
     *
     * @param string $start Inclusive start date (Y-m-d).
     * @param string $end   Inclusive end date (Y-m-d).
     * @return array Row objects with ->date and ->count.
     */
    public function get_daily_counts( $start, $end ) {
        return $this->remember( 'daily:' . $start . ':' . $end, function () use ( $start, $end ) {
            global $wpdb;
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(id) as count FROM " . self::table_name() . " WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY date ASC",
                $start, $end
            ) );
        } );
    }

    /**
     * Top 10 forms by entry count within a date range (analytics).
     *
     * @param string $start Inclusive start date (Y-m-d).
     * @param string $end   Inclusive end date (Y-m-d).
     * @return array Row objects with ->form_title and ->count.
     */
    public function get_top_forms( $start, $end ) {
        return $this->remember( 'top_forms:' . $start . ':' . $end, function () use ( $start, $end ) {
            global $wpdb;
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT form_title, COUNT(id) as count FROM " . self::table_name() . " WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY form_id ORDER BY count DESC LIMIT 10",
                $start, $end
            ) );
        } );
    }

    /**
     * 10 most recent entries within a date range (analytics).
     *
     * @param string $start Inclusive start date (Y-m-d).
     * @param string $end   Inclusive end date (Y-m-d).
     * @return array Row objects with ->form_title, ->entry_status, ->user_ip, ->created_at.
     */
    public function get_recent_between( $start, $end ) {
        return $this->remember( 'recent_between:' . $start . ':' . $end, function () use ( $start, $end ) {
            global $wpdb;
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT form_title, entry_status, user_ip, created_at FROM " . self::table_name() . " WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY id DESC LIMIT 10",
                $start, $end
            ) );
        } );
    }

    /**
     * 5 most recent entries (dashboard widget).
     *
     * @return array Entry row objects.
     */
    public function get_recent() {
        return $this->remember( 'recent', function () {
            global $wpdb;
            return $wpdb->get_results( "SELECT * FROM " . self::table_name() . " ORDER BY id DESC LIMIT 5" );
        } );
    }
}
