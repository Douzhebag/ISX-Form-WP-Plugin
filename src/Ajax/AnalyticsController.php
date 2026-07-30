<?php

namespace ISXF\Ajax;

use ISXF\Repository\EntryRepository;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Analytics endpoint (Phase 2.4 — see docs/ROADMAP.md).
 *
 * Action: isxf_get_analytics_data — stats/charts payload for the analytics
 * dashboard page. Code moved verbatim out of ISXF\Entries — no behavior
 * change.
 */
class AnalyticsController extends AbstractAjaxController {

    /**
     * Status keys, same set and order as array_keys( Entries::$status_map )
     * (the order is part of the repository cache-key salt).
     */
    const STATUS_KEYS = [ 'new', 'in_progress', 'done', 'junk' ];

    /**
     * Entries table repository.
     *
     * @var EntryRepository
     */
    private $repo;

    public function __construct() {
        $this->repo = new EntryRepository();
        add_action( 'wp_ajax_isxf_get_analytics_data', [ $this, 'handle_analytics_ajax' ] );
    }

    /**
     * AJAX handler for analytics data.
     */
    public function handle_analytics_ajax() {
        check_ajax_referer( 'isxf_analytics_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $range = sanitize_text_field( $_POST['range'] ?? '30' );
        // Site-local "today" (wp_date() uses the WP timezone).
        $today = wp_date('Y-m-d');

        // Calculate date range
        if ( $range === 'custom' ) {
            $start = sanitize_text_field( $_POST['start_date'] ?? '' );
            $end   = sanitize_text_field( $_POST['end_date'] ?? '' );
            if ( empty($start) ) $start = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify('-30 days')->format('Y-m-d');
            if ( empty($end) )   $end = $today;
        } else {
            $days  = intval($range) ?: 30;
            $start = ( new DateTimeImmutable( $today, wp_timezone() ) )->modify("-{$days} days")->format('Y-m-d');
            $end   = $today;
        }

        // Previous period for delta calculation
        $period_days = max(1, (isxf_local_datetime_to_timestamp($end) - isxf_local_datetime_to_timestamp($start)) / 86400);
        $prev_start  = ( new DateTimeImmutable( $start, wp_timezone() ) )->modify("-{$period_days} days")->format('Y-m-d');
        $prev_end    = ( new DateTimeImmutable( $start, wp_timezone() ) )->modify('-1 day')->format('Y-m-d');

        // Stats
        $total  = $this->repo->count_total();
        $period = $this->repo->count_between( $start, $end );
        $today_count = $this->repo->count_on_date( $today );
        $prev_period = $this->repo->count_between( $prev_start, $prev_end );

        $avg = $period_days > 0 ? round($period / $period_days, 1) : 0;
        $delta = $prev_period > 0 ? (($period - $prev_period) / $prev_period) * 100 : ($period > 0 ? 100 : 0);

        // Daily data for line chart
        $daily_rows = $this->repo->get_daily_counts( $start, $end );

        // Fill missing dates
        $daily = [];
        $date_map = [];
        foreach ( $daily_rows as $row ) {
            $date_map[$row->date] = (int) $row->count;
        }
        $current = $start;
        while ( $current <= $end ) {
            $daily[] = [
                'date'  => wp_date( 'd/m', isxf_local_datetime_to_timestamp( $current ) ),
                'count' => isset($date_map[$current]) ? $date_map[$current] : 0
            ];
            $current = ( new DateTimeImmutable( $current, wp_timezone() ) )->modify('+1 day')->format('Y-m-d');
        }

        // Status counts
        $status_counts = $this->repo->get_status_counts_between( $start, $end, self::STATUS_KEYS );

        // Top forms
        $top_forms_rows = $this->repo->get_top_forms( $start, $end );
        $top_forms = [];
        foreach ( $top_forms_rows as $row ) {
            $top_forms[] = [ 'title' => $row->form_title, 'count' => (int) $row->count ];
        }

        // Recent 10
        $recent_rows = $this->repo->get_recent_between( $start, $end );
        $recent = [];
        foreach ( $recent_rows as $row ) {
            $recent[] = [
                'form_title' => $row->form_title,
                'status'     => $row->entry_status,
                'ip'         => $row->user_ip,
                /* translators: %s: human-readable time difference, e.g. "5 mins". */
                'time_ago'   => sprintf( __( '%s ago', 'insightx-form' ), human_time_diff( isxf_local_datetime_to_timestamp( $row->created_at ), current_datetime()->getTimestamp() ) )
            ];
        }

        wp_send_json_success([
            'stats' => [
                'total'       => $total,
                'period'      => $period,
                'today'       => $today_count,
                'avg_per_day' => $avg,
                'delta'       => round($delta, 1)
            ],
            'daily'         => $daily,
            'status_counts' => $status_counts,
            'top_forms'     => $top_forms,
            'recent'        => $recent
        ]);
    }
}
