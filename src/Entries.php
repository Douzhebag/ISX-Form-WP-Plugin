<?php

namespace ISXF;

use ISXF\Repository\EntryRepository;

if ( ! defined( 'ABSPATH' ) ) exit;

class Entries {

    /**
     * Entries table repository.
     *
     * @var EntryRepository
     */
    private $repo;

    /**
     * Status map, built lazily on first use (get_status_map()).
     *
     * MUST NOT be built in the constructor: the plugin bootstraps on
     * plugins_loaded (before init), and calling __() that early makes
     * WordPress cache a NOOP translation for the whole domain before the
     * textdomain path is registered at init — everything renders English.
     *
     * @var array|null
     */
    private $status_map = null;

    public function __construct() {
        $this->repo = new EntryRepository();
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'isxf_form_after_submission', [ $this, 'save_to_db' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'handle_backend_actions' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_entries_assets' ] );
        // Invalidate the cached form list (filter dropdown) on any form change.
        add_action( 'save_post_isxf_form', [ $this, 'invalidate_form_list_cache' ] );
        add_action( 'deleted_post', [ $this, 'invalidate_form_list_cache' ] );
        add_action( 'trashed_post', [ $this, 'invalidate_form_list_cache' ] );
        add_action( 'untrashed_post', [ $this, 'invalidate_form_list_cache' ] );
    }

    /**
     * Entry statuses with Thai-translated labels, colors and icons.
     * Built lazily — see the $status_map property note.
     *
     * @return array
     */
    private function get_status_map() {
        if ( null === $this->status_map ) {
            $this->status_map = [
                'new'         => [ 'label' => __( 'New', 'insightx-form' ),         'color' => '#2271b1', 'bg' => '#e8f0fe', 'icon' => '🔵' ],
                'in_progress' => [ 'label' => __( 'In Progress', 'insightx-form' ), 'color' => '#996800', 'bg' => '#fff8e5', 'icon' => '🟡' ],
                'done'        => [ 'label' => __( 'Done', 'insightx-form' ),        'color' => '#2e7d32', 'bg' => '#edf7ed', 'icon' => '✅' ],
                'junk'        => [ 'label' => __( 'Junk', 'insightx-form' ),        'color' => '#a00',    'bg' => '#fef0f0', 'icon' => '🔴' ],
            ];
        }
        return $this->status_map;
    }

    /**
     * Drop the cached form list when a form is saved/trashed/deleted.
     */
    public function invalidate_form_list_cache() {
        wp_cache_delete( 'isxf_form_list', 'isxf' );
    }

    /**
     * ID => title map of all isxf_form posts for the filter dropdown.
     * Cached in the 'isxf' group; sites have few forms so the -1 bound
     * (kept from the original get_posts call) is intentional.
     *
     * @return array ID => post_title.
     */
    private function get_form_list() {
        $list = wp_cache_get( 'isxf_form_list', 'isxf' );
        if ( false === $list ) {
            $list = [];
            foreach ( get_posts( [ 'post_type' => 'isxf_form', 'numberposts' => -1 ] ) as $form_post ) {
                $list[ $form_post->ID ] = $form_post->post_title;
            }
            wp_cache_set( 'isxf_form_list', $list, 'isxf' );
        }
        return $list;
    }

    /**
     * Enqueue CSS/JS only on entries page and dashboard.
     */
    public function enqueue_entries_assets( $hook ) {
        // Load on entries page
        if ( isset($_GET['page']) && $_GET['page'] === 'isxf-entries' ) {
            wp_enqueue_style( 'isxf-entries-css', ISXF_PLUGIN_URL . 'assets/css/isxf-entries.css', [], ISXF_PLUGIN_VERSION );
            wp_enqueue_script( 'isxf-entries-js', ISXF_PLUGIN_URL . 'assets/js/isxf-entries.js', [], ISXF_PLUGIN_VERSION, true );
            wp_localize_script( 'isxf-entries-js', 'isxf_entries_env', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('isxf_entry_action_nonce'),
                'i18n'     => [
                    'error_generic' => __( 'An error occurred', 'insightx-form' ),
                    'saving'        => __( '⏳ Saving...', 'insightx-form' ),
                    'add_note'      => __( '+ Add note', 'insightx-form' ),
                    'save'          => __( '💾 Save', 'insightx-form' ),
                ],
            ]);
        }
        // Load CSS on dashboard (for widget)
        if ( $hook === 'index.php' ) {
            wp_enqueue_style( 'isxf-entries-css', ISXF_PLUGIN_URL . 'assets/css/isxf-entries.css', [], ISXF_PLUGIN_VERSION );
        }
        // Load on analytics page
        if ( isset($_GET['page']) && $_GET['page'] === 'isxf-analytics' ) {
            wp_enqueue_style( 'isxf-analytics-css', ISXF_PLUGIN_URL . 'assets/css/isxf-analytics.css', [], ISXF_PLUGIN_VERSION );
            // Bundled Chart.js v4.4.1 UMD build (source: https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js).
            wp_enqueue_script( 'chart-js', ISXF_PLUGIN_URL . 'assets/libs/chartjs/chart.umd.min.js', [], '4.4.1', true );
            wp_enqueue_script( 'isxf-analytics-js', ISXF_PLUGIN_URL . 'assets/js/isxf-analytics.js', ['chart-js'], ISXF_PLUGIN_VERSION, true );
            wp_localize_script( 'isxf-analytics-js', 'isxf_analytics_env', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('isxf_analytics_nonce'),
                'i18n'     => [
                    'entries'           => __( 'entries', 'insightx-form' ),
                    'no_data'           => __( 'No entries yet', 'insightx-form' ),
                    'status_new'        => __( 'New', 'insightx-form' ),
                    'status_in_progress' => __( 'In Progress', 'insightx-form' ),
                    'status_done'       => __( 'Done', 'insightx-form' ),
                    'status_junk'       => __( 'Junk', 'insightx-form' ),
                ],
            ]);
        }
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'isxf_entries_dashboard',
            __( '📊 InsightX Form — Overview', 'insightx-form' ),
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function register_menu() {
        add_submenu_page( 
            'edit.php?post_type=isxf_form', 
            'Entries', 
            __( '📥 Entries', 'insightx-form' ),
            'manage_options', 
            'isxf-entries', 
            [ $this, 'render_page' ] 
        );
        add_submenu_page( 
            'edit.php?post_type=isxf_form', 
            'Analytics', 
            '📊 Analytics', 
            'manage_options', 
            'isxf-analytics', 
            [ $this, 'render_analytics_page' ] 
        );
    }

    public function handle_backend_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;

        $action = isset( $_GET['action'] ) ? sanitize_text_field($_GET['action']) : '';

        // Single-entry delete: POST only (a state-changing action must not be
        // a GET link). The per-row submit button in the entries table carries
        // the entry ID; the shared form nonce authorizes the request.
        if ( isset( $_POST['isxf_delete_entry'] ) ) {
            $entry_id = intval( $_POST['isxf_delete_entry'] );
            check_admin_referer( 'isxf_delete_entry', 'isxf_delete_entry_nonce' );
            $this->repo->delete( $entry_id );
            wp_safe_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=deleted' ) );
            exit;
        }

        if ( isset($_POST['isxf_bulk_action_nonce']) && wp_verify_nonce($_POST['isxf_bulk_action_nonce'], 'isxf_bulk_action') ) {
            $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

            if ( ! empty($_POST['entry_ids']) ) {
                $ids = array_map('intval', $_POST['entry_ids']);

                if ( $bulk_action === 'delete' ) {
                    $this->repo->delete_bulk( $ids );
                    wp_safe_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=bulk_deleted' ) );
                    exit;
                }

                if ( in_array( $bulk_action, ['mark_done', 'mark_in_progress', 'mark_junk'], true ) ) {
                    $status_val = str_replace('mark_', '', $bulk_action);
                    $this->repo->update_status_bulk( $ids, $status_val );
                    wp_safe_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=status_updated' ) );
                    exit;
                }
            }
        }

        if ( $action === 'isxf_export_csv' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'You do not have permission to export data.', 'insightx-form' ), __( 'Permission Error', 'insightx-form' ), 403 );
            }
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'isxf_export_csv_action' ) ) {
                wp_die( __( 'Security check failed.', 'insightx-form' ), __( 'Security Error', 'insightx-form' ), 403 );
            }
            $this->process_csv_export();
        }
    }

    public function save_to_db( $entry_data, $form_id, $user_ip ) {
        $this->repo->insert_submission( $entry_data, $form_id, $user_ip );
    }

    private function get_form_headers( $form_id ) {
        $headers = [];
        if ( $form_id ) {
            $fields = get_post_meta( $form_id, '_isxf_form_fields', true );
            if ( is_array( $fields ) ) {
                foreach ( $fields as $f ) {
                    if ( isset($f['type']) && $f['type'] !== 'heading' ) {
                        $headers[] = $f['label'];
                    }
                }
            }
        }
        return $headers;
    }

    private function build_query_args() {
        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        $search_query   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $start_date     = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
        $end_date       = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
        $filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

        if ( $filter_status && ! array_key_exists( $filter_status, $this->get_status_map() ) ) {
            $filter_status = '';
        }

        return $this->repo->build_where_sql( [
            'form_id'    => $filter_form_id,
            'status'     => $filter_status,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'search'     => $search_query,
        ] );
    }

    private function render_status_badge( $status ) {
        $map = $this->get_status_map();
        $s = isset( $map[$status] ) ? $map[$status] : $map['new'];
        return '<span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; color:' . $s['color'] . '; background:' . $s['bg'] . '; white-space:nowrap;">' . $s['icon'] . ' ' . $s['label'] . '</span>';
    }

    private function process_csv_export() {
        if ( function_exists( 'set_time_limit' ) ) set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        
        // Site-local timestamp for the export filename (was server-tz date()).
        $filename = 'entries-' . wp_date('Y-m-d-His') . '.csv';
        
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

        $dynamic_headers = $filter_form_id ? $this->get_form_headers($filter_form_id) : [ __( 'All Data (JSON)', 'insightx-form' ) ];
        fputcsv( $output, array_merge( [ __( 'Date', 'insightx-form' ), _x( 'Form', 'table column header', 'insightx-form' ) ], $dynamic_headers, [ __( 'Status', 'insightx-form' ), __( 'Admin Note', 'insightx-form' ), 'IP Address' ] ) );

        $where_sql = $this->build_query_args();

        $batch_size = 1000;
        $offset = 0;
        $status_labels = [];
        foreach ( $this->get_status_map() as $k => $v ) { $status_labels[$k] = $v['label']; }
        
        while ( true ) {
            $results = $this->repo->get_entries( $where_sql, $batch_size, $offset );
            
            if ( empty( $results ) ) break;

            foreach ( $results as $row ) {
                $data = json_decode( $row->entry_data, true );
                $row_data = [ $row->created_at, $row->form_title ];
                
                if ( $filter_form_id ) {
                    foreach ( $dynamic_headers as $header ) {
                        $val = isset( $data[$header] ) ? $data[$header] : '';
                        if ( is_string($val) && preg_match( '/^[\=\+\-\@]/', $val ) ) {
                            $val = "'" . $val;
                        }
                        $row_data[] = $val;
                    }
                } else {
                    $safe_data = $row->entry_data;
                    if ( is_string($safe_data) && preg_match( '/^[\=\+\-\@]/', $safe_data ) ) {
                        $safe_data = "'" . $safe_data;
                    }
                    $row_data[] = $safe_data;
                }
                
                $row_data[] = $status_labels[ $row->entry_status ?? 'new' ] ?? __( 'New', 'insightx-form' );
                $row_data[] = $row->admin_note ?? '';
                $row_data[] = $row->user_ip;
                fputcsv( $output, $row_data );
            }

            if ( function_exists( 'ob_flush' ) ) ob_flush();
            flush();
            unset( $results );
            $offset += $batch_size;
        }

        fclose( $output );
        exit;
    }

    public function render_page() {
        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        $filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $search_query   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $start_date     = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
        $end_date       = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
        
        $per_page = 50;
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $paged - 1 ) * $per_page;

        $where_sql = $this->build_query_args();
        
        $total_items = $this->repo->count_where( $where_sql );
        $total_pages = ceil( $total_items / $per_page );

        $results = $this->repo->get_entries( $where_sql, $per_page, $offset );

        $forms = [];
        foreach ( $this->get_form_list() as $form_post_id => $form_post_title ) {
            $forms[] = (object) [ 'ID' => $form_post_id, 'post_title' => $form_post_title ];
        }
        $dynamic_headers = $filter_form_id ? $this->get_form_headers($filter_form_id) : [];

        $export_url = wp_nonce_url(
            admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&action=isxf_export_csv' ),
            'isxf_export_csv_action'
        );
        if ( $filter_form_id ) $export_url = add_query_arg( 'filter_form', $filter_form_id, $export_url );
        if ( $filter_status )  $export_url = add_query_arg( 'filter_status', $filter_status, $export_url );
        if ( $search_query )   $export_url = add_query_arg( 's', $search_query, $export_url );
        if ( $start_date )     $export_url = add_query_arg( 'start_date', $start_date, $export_url );
        if ( $end_date )       $export_url = add_query_arg( 'end_date', $end_date, $export_url );


        $status_counts = $this->repo->get_status_counts( array_keys($this->get_status_map()) );
        $status_counts['all'] = array_sum($status_counts);
        $msg_text = '';
        if ( isset( $_GET['msg'] ) ) {
            $msgs = [ 'deleted' => __( 'Entry deleted successfully!', 'insightx-form' ), 'bulk_deleted' => __( 'Selected entries deleted successfully!', 'insightx-form' ), 'status_updated' => __( 'Status updated successfully!', 'insightx-form' ) ];
            $msg_text = $msgs[ sanitize_text_field( $_GET['msg'] ) ] ?? '';
        }

        $stat_cards = [
            ['icon' => '🔵', 'label' => __( 'New', 'insightx-form' ),         'key' => 'new',         'color' => 'var(--ix-info)'],
            ['icon' => '🟡', 'label' => __( 'In Progress', 'insightx-form' ), 'key' => 'in_progress', 'color' => 'var(--ix-warning)'],
            ['icon' => '✅', 'label' => __( 'Done', 'insightx-form' ),        'key' => 'done',        'color' => 'var(--ix-success)'],
            ['icon' => '🔴', 'label' => __( 'Junk', 'insightx-form' ),        'key' => 'junk',        'color' => 'var(--ix-danger)'],
        ];

        $base_url = admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' );
        if ( $filter_form_id ) $base_url = add_query_arg( 'filter_form', $filter_form_id, $base_url );
        if ( $search_query ) $base_url = add_query_arg( 's', $search_query, $base_url );

        $page_url = admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' );
        if ( $filter_form_id ) $page_url = add_query_arg( 'filter_form', $filter_form_id, $page_url );
        if ( $filter_status )  $page_url = add_query_arg( 'filter_status', $filter_status, $page_url );
        if ( $search_query )   $page_url = add_query_arg( 's', $search_query, $page_url );
        if ( $start_date )     $page_url = add_query_arg( 'start_date', $start_date, $page_url );
        if ( $end_date )       $page_url = add_query_arg( 'end_date', $end_date, $page_url );

        Template::render( 'admin/entries-page', [
            'filter_form_id'  => $filter_form_id,
            'filter_status'   => $filter_status,
            'search_query'    => $search_query,
            'start_date'      => $start_date,
            'end_date'        => $end_date,
            'paged'           => $paged,
            'total_items'     => $total_items,
            'total_pages'     => $total_pages,
            'results'         => $results,
            'forms'           => $forms,
            'dynamic_headers' => $dynamic_headers,
            'export_url'      => $export_url,
            'status_counts'   => $status_counts,
            'status_map'      => $this->get_status_map(),
            'msg_text'        => $msg_text,
            'stat_cards'      => $stat_cards,
            'base_url'        => $base_url,
            'page_url'        => $page_url,
        ] );
    }

    /**
     * Render Analytics Dashboard page.
     */
    public function render_analytics_page() {
        Template::render( 'admin/analytics-page' );
    }

    public function render_dashboard_widget() {
        // Site-local calendar dates (current_datetime() uses the WP timezone).
        $today    = current_datetime()->format('Y-m-d');
        $week_ago = current_datetime()->modify('-7 days')->format('Y-m-d');
        $month_ago = current_datetime()->modify('-30 days')->format('Y-m-d');

        $total     = $this->repo->count_total();
        $today_c   = $this->repo->count_on_date( $today );
        $week_c    = $this->repo->count_since( $week_ago );
        $month_c   = $this->repo->count_since( $month_ago );

        $status_counts = $this->repo->get_status_counts( array_keys($this->get_status_map()) );

        $recent = $this->repo->get_recent();

        $entries_url = admin_url('edit.php?post_type=isxf_form&page=isxf-entries');
        Template::render( 'admin/dashboard-widget', [
            'today_c'       => $today_c,
            'week_c'        => $week_c,
            'month_c'       => $month_c,
            'total'         => $total,
            'status_counts' => $status_counts,
            'status_map'    => $this->get_status_map(),
            'recent'        => $recent,
            'entries_url'   => $entries_url,
        ] );
    }
}