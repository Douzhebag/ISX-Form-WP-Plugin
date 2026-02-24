<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACF_Entries {

    private $status_map = [
        'new'         => [ 'label' => 'ใหม่',           'color' => '#2271b1', 'bg' => '#e8f0fe', 'icon' => '🔵' ],
        'in_progress' => [ 'label' => 'กำลังดำเนินการ', 'color' => '#996800', 'bg' => '#fff8e5', 'icon' => '🟡' ],
        'done'        => [ 'label' => 'เสร็จสิ้น',      'color' => '#2e7d32', 'bg' => '#edf7ed', 'icon' => '✅' ],
        'junk'        => [ 'label' => 'ขยะ',            'color' => '#a00',    'bg' => '#fef0f0', 'icon' => '🔴' ],
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'acf_form_after_submission', [ $this, 'save_to_db' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'handle_backend_actions' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'acf_entries_dashboard',
            '📊 InsightX Form — ภาพรวม',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function register_menu() {
        add_submenu_page( 
            'edit.php?post_type=acf_form', 
            'Entries', 
            '📥 รายการข้อมูล', 
            'manage_options', 
            'acf-entries', 
            [ $this, 'render_page' ] 
        );
    }

    public function handle_backend_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
        
        $action = isset( $_GET['action'] ) ? sanitize_text_field($_GET['action']) : '';

        if ( $action === 'delete' && isset( $_GET['entry_id'] ) ) {
            $entry_id = intval( $_GET['entry_id'] );
            check_admin_referer( 'delete_entry_' . $entry_id );
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'acf_form_entries', [ 'id' => $entry_id ], ['%d'] );
            wp_redirect( admin_url( 'edit.php?post_type=acf_form&page=acf-entries&msg=deleted' ) );
            exit;
        }

        if ( isset($_POST['acf_bulk_action_nonce']) && wp_verify_nonce($_POST['acf_bulk_action_nonce'], 'acf_bulk_action') ) {
            $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
            
            if ( ! empty($_POST['entry_ids']) ) {
                global $wpdb;
                $ids = array_map('intval', $_POST['entry_ids']);
                $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));

                if ( $bulk_action === 'delete' ) {
                    $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}acf_form_entries WHERE id IN ($ids_placeholder)", $ids) );
                    wp_redirect( admin_url( 'edit.php?post_type=acf_form&page=acf-entries&msg=bulk_deleted' ) );
                    exit;
                }

                if ( in_array( $bulk_action, ['mark_done', 'mark_in_progress', 'mark_junk'], true ) ) {
                    $status_val = str_replace('mark_', '', $bulk_action);
                    foreach ( $ids as $id ) {
                        $wpdb->update( $wpdb->prefix . 'acf_form_entries', [ 'entry_status' => $status_val ], [ 'id' => $id ], ['%s'], ['%d'] );
                    }
                    wp_redirect( admin_url( 'edit.php?post_type=acf_form&page=acf-entries&msg=status_updated' ) );
                    exit;
                }
            }
        }

        if ( $action === 'acf_export_csv' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'คุณไม่มีสิทธิ์ในการส่งออกข้อมูล', 'ข้อผิดพลาดด้านสิทธิ์', 403 );
            }
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'acf_export_csv_action' ) ) {
                wp_die( 'การยืนยันความปลอดภัยล้มเหลว', 'ข้อผิดพลาดด้านความปลอดภัย', 403 );
            }
            $this->process_csv_export();
        }
    }

    public function save_to_db( $entry_data, $form_id, $user_ip ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'acf_form_entries', [
            'form_id'      => $form_id,
            'form_title'   => get_the_title( $form_id ),
            'entry_data'   => wp_json_encode( $entry_data, JSON_UNESCAPED_UNICODE ),
            'user_ip'      => $user_ip,
            'entry_status' => 'new',
            'admin_note'   => '',
            'created_at'   => current_time( 'mysql' )
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s'] );
    }

    private function get_form_headers( $form_id ) {
        $headers = [];
        if ( $form_id ) {
            $fields = get_post_meta( $form_id, '_acf_form_fields', true );
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
        global $wpdb;
        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        $search_query   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $start_date     = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
        $end_date       = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
        $filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

        $where_clauses = ["1=1"];
        $where_values = [];

        if ( $filter_form_id ) {
            $where_clauses[] = "form_id = %d";
            $where_values[] = $filter_form_id;
        }
        if ( $filter_status && array_key_exists( $filter_status, $this->status_map ) ) {
            $where_clauses[] = "entry_status = %s";
            $where_values[] = $filter_status;
        }
        if ( $start_date ) {
            $where_clauses[] = "DATE(created_at) >= %s";
            $where_values[] = $start_date;
        }
        if ( $end_date ) {
            $where_clauses[] = "DATE(created_at) <= %s";
            $where_values[] = $end_date;
        }
        if ( $search_query ) {
            $where_clauses[] = "(entry_data LIKE %s OR user_ip LIKE %s OR form_title LIKE %s)";
            $like_s = '%' . $wpdb->esc_like( $search_query ) . '%';
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

    private function render_status_badge( $status ) {
        $s = isset( $this->status_map[$status] ) ? $this->status_map[$status] : $this->status_map['new'];
        return '<span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; color:' . $s['color'] . '; background:' . $s['bg'] . '; white-space:nowrap;">' . $s['icon'] . ' ' . $s['label'] . '</span>';
    }

    private function process_csv_export() {
        global $wpdb;
        if ( function_exists( 'set_time_limit' ) ) set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $table_name = $wpdb->prefix . 'acf_form_entries';
        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        
        $filename = 'entries-' . date('Y-m-d-His') . '.csv';
        
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

        $dynamic_headers = $filter_form_id ? $this->get_form_headers($filter_form_id) : ['ข้อมูลทั้งหมด (JSON)'];
        fputcsv( $output, array_merge( ['วันที่', 'ฟอร์ม'], $dynamic_headers, ['สถานะ', 'โน้ตแอดมิน', 'IP Address'] ) );

        $where_sql = $this->build_query_args();

        $batch_size = 1000;
        $offset = 0;
        $status_labels = [];
        foreach ( $this->status_map as $k => $v ) { $status_labels[$k] = $v['label']; }
        
        while ( true ) {
            $query = "SELECT * FROM $table_name $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
            $results = $wpdb->get_results( $wpdb->prepare( $query, $batch_size, $offset ) );
            
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
                
                $row_data[] = $status_labels[ $row->entry_status ?? 'new' ] ?? 'ใหม่';
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'acf_form_entries';
        
        $filter_form_id = isset( $_GET['filter_form'] ) ? intval( $_GET['filter_form'] ) : 0;
        $filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $search_query   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $start_date     = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
        $end_date       = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
        
        $per_page = 50;
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $paged - 1 ) * $per_page;

        $where_sql = $this->build_query_args();
        
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name $where_sql" );
        $total_pages = ceil( $total_items / $per_page );

        $query = "SELECT * FROM $table_name $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        $results = $wpdb->get_results( $wpdb->prepare( $query, $per_page, $offset ) );

        $forms = get_posts( [ 'post_type' => 'acf_form', 'numberposts' => -1 ] );
        $dynamic_headers = $filter_form_id ? $this->get_form_headers($filter_form_id) : [];

        $export_url = wp_nonce_url(
            admin_url( 'edit.php?post_type=acf_form&page=acf-entries&action=acf_export_csv' ),
            'acf_export_csv_action'
        );
        if ( $filter_form_id ) $export_url = add_query_arg( 'filter_form', $filter_form_id, $export_url );
        if ( $filter_status )  $export_url = add_query_arg( 'filter_status', $filter_status, $export_url );
        if ( $search_query )   $export_url = add_query_arg( 's', $search_query, $export_url );
        if ( $start_date )     $export_url = add_query_arg( 'start_date', $start_date, $export_url );
        if ( $end_date )       $export_url = add_query_arg( 'end_date', $end_date, $export_url );

        $entry_nonce = wp_create_nonce( 'acf_entry_action_nonce' );

        $status_counts = [];
        foreach ( array_keys($this->status_map) as $sk ) {
            $status_counts[$sk] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE entry_status = %s", $sk ) );
        }
        $status_counts['all'] = array_sum($status_counts);
        ?>
        <style>
            /* === InsightX Entries — Modern UI === */
            :root {
                --ix-primary: #4F46E5;
                --ix-primary-light: #EEF2FF;
                --ix-primary-hover: #4338CA;
                --ix-success: #059669;
                --ix-success-bg: #ECFDF5;
                --ix-warning: #D97706;
                --ix-warning-bg: #FFFBEB;
                --ix-danger: #DC2626;
                --ix-danger-bg: #FEF2F2;
                --ix-info: #2563EB;
                --ix-info-bg: #EFF6FF;
                --ix-gray-50: #F9FAFB;
                --ix-gray-100: #F3F4F6;
                --ix-gray-200: #E5E7EB;
                --ix-gray-300: #D1D5DB;
                --ix-gray-500: #6B7280;
                --ix-gray-700: #374151;
                --ix-gray-900: #111827;
                --ix-radius: 12px;
                --ix-radius-sm: 8px;
                --ix-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
                --ix-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
                --ix-font: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            }
            .ix-wrap { font-family: var(--ix-font); color: var(--ix-gray-900); }
            .ix-wrap * { box-sizing: border-box; }

            /* Header */
            .ix-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
            .ix-header h1 { font-size:22px; font-weight:700; margin:0; color:var(--ix-gray-900); display:flex; align-items:center; gap:8px; }
            .ix-header h1 .ix-count { font-size:14px; font-weight:500; color:var(--ix-gray-500); }
            .ix-btn-export { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; background:var(--ix-success); color:#fff; border:none; border-radius:var(--ix-radius-sm); font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; transition:all 0.2s; box-shadow:var(--ix-shadow); }
            .ix-btn-export:hover { background:#047857; transform:translateY(-1px); box-shadow:var(--ix-shadow-md); color:#fff; }

            /* Stats Cards */
            .ix-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
            .ix-stat-card { background:#fff; border-radius:var(--ix-radius); padding:16px 20px; border:1px solid var(--ix-gray-200); transition:all 0.25s; cursor:default; text-align:center; }
            .ix-stat-card:hover { transform:translateY(-2px); box-shadow:var(--ix-shadow-md); }
            .ix-stat-card .ix-stat-num { font-size:28px; font-weight:800; line-height:1.2; }
            .ix-stat-card .ix-stat-label { font-size:12px; color:var(--ix-gray-500); margin-top:4px; font-weight:500; letter-spacing:0.3px; }

            /* Status Tabs */
            .ix-tabs { display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap; }
            .ix-tabs a { display:inline-flex; align-items:center; gap:6px; padding:7px 16px; border-radius:100px; text-decoration:none; font-size:13px; font-weight:500; color:var(--ix-gray-500); background:var(--ix-gray-100); transition:all 0.2s; border:1px solid transparent; }
            .ix-tabs a:hover { background:var(--ix-gray-200); color:var(--ix-gray-700); }
            .ix-tabs a.active { background:#fff; border-color:var(--ix-primary); color:var(--ix-primary); font-weight:600; box-shadow:var(--ix-shadow); }
            .ix-tabs .ix-badge { font-size:11px; background:rgba(0,0,0,0.06); padding:2px 8px; border-radius:100px; font-weight:600; }
            .ix-tabs a.active .ix-badge { background:var(--ix-primary-light); color:var(--ix-primary); }

            /* Filter Card */
            .ix-filter-card { background:#fff; padding:18px 22px; border:1px solid var(--ix-gray-200); border-radius:var(--ix-radius); margin-bottom:18px; box-shadow:var(--ix-shadow); }
            .ix-filter-form { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; }
            .ix-filter-group { display:flex; flex-direction:column; gap:5px; }
            .ix-filter-group label { font-size:12px; font-weight:600; color:var(--ix-gray-700); letter-spacing:0.2px; }
            .ix-filter-group select,
            .ix-filter-group input[type="date"],
            .ix-filter-group input[type="search"] { padding:8px 12px; border:1px solid var(--ix-gray-300); border-radius:var(--ix-radius-sm); font-size:13px; background:#fff; transition:all 0.2s; font-family:var(--ix-font); color:var(--ix-gray-900); }
            .ix-filter-group select:focus,
            .ix-filter-group input:focus { outline:none; border-color:var(--ix-primary); box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
            .ix-filter-group .ix-date-range { display:flex; align-items:center; gap:6px; }
            .ix-filter-group .ix-date-sep { color:var(--ix-gray-300); font-weight:600; }
            .ix-filter-actions { display:flex; gap:8px; align-items:flex-end; }
            .ix-btn { padding:8px 18px; border-radius:var(--ix-radius-sm); font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; border:none; font-family:var(--ix-font); }
            .ix-btn-primary { background:var(--ix-primary); color:#fff; }
            .ix-btn-primary:hover { background:var(--ix-primary-hover); }
            .ix-btn-ghost { background:transparent; color:var(--ix-gray-500); border:1px solid var(--ix-gray-300); }
            .ix-btn-ghost:hover { background:var(--ix-gray-100); color:var(--ix-gray-700); }

            /* Bulk Actions */
            .ix-bulk-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:12px; }
            .ix-bulk-left { display:flex; align-items:center; gap:8px; }
            .ix-bulk-left select { padding:7px 12px; border:1px solid var(--ix-gray-300); border-radius:var(--ix-radius-sm); font-size:13px; background:#fff; font-family:var(--ix-font); }
            .ix-bulk-left select:focus { outline:none; border-color:var(--ix-primary); }

            /* Table */
            .ix-table-wrap { background:#fff; border:1px solid var(--ix-gray-200); border-radius:var(--ix-radius); overflow:hidden; box-shadow:var(--ix-shadow); }
            .ix-table-scroll { overflow-x:auto; }
            .ix-table { width:100%; border-collapse:collapse; font-size:13px; }
            .ix-table thead { background:var(--ix-gray-50); border-bottom:2px solid var(--ix-gray-200); }
            .ix-table thead th { padding:12px 14px; text-align:left; font-weight:600; color:var(--ix-gray-700); font-size:12px; text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap; }
            .ix-table thead th.ix-col-cb { width:44px; text-align:center; }
            .ix-table tbody tr { border-bottom:1px solid var(--ix-gray-100); transition:background 0.15s; }
            .ix-table tbody tr:last-child { border-bottom:none; }
            .ix-table tbody tr:hover { background:var(--ix-primary-light); }
            .ix-table tbody td { padding:12px 14px; vertical-align:middle; color:var(--ix-gray-700); }
            .ix-table tbody td.ix-col-cb { text-align:center; }
            .ix-table .ix-form-name { font-weight:600; color:var(--ix-gray-900); }
            .ix-table .ix-date { color:var(--ix-gray-500); font-size:12px; white-space:nowrap; }
            .ix-table .ix-data-preview { max-height:70px; overflow-y:auto; font-size:12px; line-height:1.6; }
            .ix-table .ix-data-preview strong { color:var(--ix-gray-900); }

            /* Status Select */
            .ix-status-select { padding:6px 10px; border-radius:100px; border:1px solid var(--ix-gray-200); font-size:12px; cursor:pointer; background:#fff; transition:all 0.2s; font-family:var(--ix-font); font-weight:500; }
            .ix-status-select:hover { border-color:var(--ix-primary); }
            .ix-status-select:focus { outline:none; border-color:var(--ix-primary); box-shadow:0 0 0 3px rgba(79,70,229,0.1); }

            /* Notes */
            .ix-note-cell { position:relative; min-width:160px; }
            .ix-note-display { display:flex; align-items:center; gap:6px; cursor:pointer; padding:6px 10px; border-radius:var(--ix-radius-sm); transition:all 0.2s; min-height:32px; }
            .ix-note-display:hover { background:var(--ix-gray-100); }
            .ix-note-text { font-size:12px; color:var(--ix-gray-700); max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .ix-note-placeholder { font-size:12px; color:var(--ix-gray-300); font-style:italic; }
            .ix-note-edit-icon { font-size:13px; opacity:0; transition:opacity 0.2s; flex-shrink:0; }
            .ix-note-display:hover .ix-note-edit-icon { opacity:0.7; }
            .ix-note-editor { display:none; }
            .ix-note-editor.active { display:flex; flex-direction:column; gap:8px; }
            .ix-note-editor textarea { width:100%; min-height:56px; padding:10px; border:1.5px solid var(--ix-primary); border-radius:var(--ix-radius-sm); font-size:12px; resize:vertical; font-family:var(--ix-font); transition:box-shadow 0.2s; }
            .ix-note-editor textarea:focus { outline:none; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
            .ix-note-actions { display:flex; gap:6px; }
            .ix-note-save { padding:5px 14px; background:var(--ix-primary); color:#fff; border:none; border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.2s; font-weight:600; }
            .ix-note-save:hover { background:var(--ix-primary-hover); }
            .ix-note-cancel { padding:5px 14px; background:var(--ix-gray-100); color:var(--ix-gray-500); border:1px solid var(--ix-gray-200); border-radius:6px; font-size:12px; cursor:pointer; transition:all 0.2s; }
            .ix-note-cancel:hover { background:var(--ix-gray-200); }

            /* Delete Link */
            .ix-delete-link { color:var(--ix-danger); text-decoration:none; font-size:12px; font-weight:500; opacity:0.6; transition:all 0.2s; }
            .ix-delete-link:hover { opacity:1; color:var(--ix-danger); }

            /* Empty State */
            .ix-empty { text-align:center; padding:60px 20px; }
            .ix-empty-icon { font-size:48px; margin-bottom:12px; opacity:0.4; }
            .ix-empty-text { font-size:15px; color:var(--ix-gray-500); }

            /* Pagination */
            .ix-pagination { display:flex; align-items:center; justify-content:flex-end; gap:8px; padding:14px 18px; border-top:1px solid var(--ix-gray-100); font-size:13px; color:var(--ix-gray-500); }
            .ix-pagination .page-numbers { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px; border-radius:6px; text-decoration:none; font-weight:500; color:var(--ix-gray-700); background:var(--ix-gray-100); transition:all 0.2s; }
            .ix-pagination .page-numbers:hover { background:var(--ix-primary-light); color:var(--ix-primary); }
            .ix-pagination .page-numbers.current { background:var(--ix-primary); color:#fff; }

            /* Toast */
            .ix-toast { position:fixed; bottom:24px; right:24px; padding:14px 22px; border-radius:var(--ix-radius-sm); font-size:13px; font-weight:500; color:#fff; z-index:99999; animation:ixSlideIn 0.35s cubic-bezier(0.16,1,0.3,1); box-shadow:0 8px 24px rgba(0,0,0,0.15); backdrop-filter:blur(8px); }
            .ix-toast.success { background:rgba(5,150,105,0.95); }
            .ix-toast.error { background:rgba(220,38,38,0.95); }
            @keyframes ixSlideIn { from { transform:translateY(20px) scale(0.95); opacity:0; } to { transform:translateY(0) scale(1); opacity:1; } }

            /* Responsive */
            @media (max-width:1024px) {
                .ix-stats { grid-template-columns:repeat(2,1fr); }
                .ix-filter-group { flex:1; min-width:180px; }
            }
            @media (max-width:768px) {
                .ix-header { flex-direction:column; align-items:flex-start; }
                .ix-stats { grid-template-columns:repeat(2,1fr); gap:8px; }
                .ix-stat-card { padding:12px 14px; }
                .ix-stat-card .ix-stat-num { font-size:22px; }
                .ix-tabs { gap:4px; overflow-x:auto; flex-wrap:nowrap; padding-bottom:4px; -webkit-overflow-scrolling:touch; }
                .ix-tabs a { white-space:nowrap; flex-shrink:0; padding:6px 12px; font-size:12px; }
                .ix-filter-form { flex-direction:column; }
                .ix-filter-group { width:100%; }
                .ix-filter-group select,
                .ix-filter-group input[type="date"],
                .ix-filter-group input[type="search"] { width:100%; }
                .ix-bulk-bar { flex-direction:column; align-items:flex-start; }
            }
            @media (max-width:480px) {
                .ix-header h1 { font-size:18px; }
                .ix-stats { grid-template-columns:1fr 1fr; gap:6px; }
                .ix-stat-card .ix-stat-num { font-size:20px; }
                .ix-stat-card .ix-stat-label { font-size:11px; }
                .ix-tabs a { padding:5px 10px; font-size:11px; }
                .ix-table { font-size:12px; }
                .ix-table thead th, .ix-table tbody td { padding:10px 10px; }
            }
        </style>

        <div class="wrap ix-wrap">
            <?php if ( isset( $_GET['msg'] ) ) :
                $msgs = [ 'deleted' => 'ลบข้อมูลเรียบร้อยแล้ว!', 'bulk_deleted' => 'ลบข้อมูลที่เลือกเรียบร้อยแล้ว!', 'status_updated' => 'อัพเดตสถานะเรียบร้อยแล้ว!' ];
                $msg_text = $msgs[$_GET['msg']] ?? '';
                if ( $msg_text ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo $msg_text; ?></p></div>
            <?php endif; endif; ?>

            <!-- Header -->
            <div class="ix-header">
                <h1>📥 รายการข้อมูล <?php if ($total_items > 0) : ?><span class="ix-count">(<?php echo $total_items; ?> รายการ)</span><?php endif; ?></h1>
                <a href="<?php echo esc_url( $export_url ); ?>" class="ix-btn-export">📊 ส่งออก CSV</a>
            </div>

            <!-- Stats Cards -->
            <div class="ix-stats">
                <?php
                $stat_cards = [
                    ['icon' => '🔵', 'label' => 'ใหม่',           'key' => 'new',         'color' => 'var(--ix-info)'],
                    ['icon' => '🟡', 'label' => 'กำลังดำเนินการ', 'key' => 'in_progress', 'color' => 'var(--ix-warning)'],
                    ['icon' => '✅', 'label' => 'เสร็จสิ้น',      'key' => 'done',        'color' => 'var(--ix-success)'],
                    ['icon' => '🔴', 'label' => 'ขยะ',            'key' => 'junk',        'color' => 'var(--ix-danger)'],
                ];
                foreach ( $stat_cards as $sc ) : ?>
                    <div class="ix-stat-card">
                        <div class="ix-stat-num" style="color:<?php echo $sc['color']; ?>"><?php echo $status_counts[$sc['key']]; ?></div>
                        <div class="ix-stat-label"><?php echo $sc['icon'] . ' ' . $sc['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Status Tabs -->
            <div class="ix-tabs">
                <?php
                $base_url = admin_url( 'edit.php?post_type=acf_form&page=acf-entries' );
                if ( $filter_form_id ) $base_url = add_query_arg( 'filter_form', $filter_form_id, $base_url );
                if ( $search_query ) $base_url = add_query_arg( 's', $search_query, $base_url );
                ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo empty($filter_status) ? 'active' : ''; ?>">📋 ทั้งหมด <span class="ix-badge"><?php echo $status_counts['all']; ?></span></a>
                <?php foreach ( $this->status_map as $skey => $sinfo ) : ?>
                    <a href="<?php echo esc_url( add_query_arg('filter_status', $skey, $base_url) ); ?>" class="<?php echo $filter_status === $skey ? 'active' : ''; ?>">
                        <?php echo $sinfo['icon'] . ' ' . $sinfo['label']; ?> <span class="ix-badge"><?php echo $status_counts[$skey]; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filter Card -->
            <div class="ix-filter-card">
                <form method="get" class="ix-filter-form">
                    <input type="hidden" name="post_type" value="acf_form">
                    <input type="hidden" name="page" value="acf-entries">
                    <?php if ( $filter_status ) : ?><input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>"><?php endif; ?>

                    <div class="ix-filter-group">
                        <label>📑 ฟอร์ม</label>
                        <select name="filter_form">
                            <option value="0">-- ทุกฟอร์ม --</option>
                            <?php foreach ( $forms as $f ) : ?>
                                <option value="<?php echo $f->ID; ?>" <?php selected( $filter_form_id, $f->ID ); ?>><?php echo esc_html( $f->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ix-filter-group">
                        <label>📅 ช่วงวันที่</label>
                        <div class="ix-date-range">
                            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                            <span class="ix-date-sep">—</span>
                            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        </div>
                    </div>

                    <div class="ix-filter-group" style="flex-grow:1; max-width:300px;">
                        <label>🔍 ค้นหา</label>
                        <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="ชื่อ, เบอร์, อีเมล, IP...">
                    </div>

                    <div class="ix-filter-actions">
                        <button type="submit" class="ix-btn ix-btn-primary">ค้นหา</button>
                        <?php if ( $filter_form_id || $search_query || $start_date || $end_date || $filter_status ) : ?>
                            <a href="<?php echo admin_url( 'edit.php?post_type=acf_form&page=acf-entries' ); ?>" class="ix-btn ix-btn-ghost">ล้างค่า</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions & Table -->
            <form method="post" id="acf-bulk-form">
                <?php wp_nonce_field('acf_bulk_action', 'acf_bulk_action_nonce'); ?>

                <div class="ix-bulk-bar">
                    <div class="ix-bulk-left">
                        <select name="bulk_action">
                            <option value="-1">จัดการหมู่ (Bulk Actions)</option>
                            <option value="mark_done">✅ เปลี่ยนเป็น "เสร็จสิ้น"</option>
                            <option value="mark_in_progress">🟡 เปลี่ยนเป็น "กำลังดำเนินการ"</option>
                            <option value="mark_junk">🔴 เปลี่ยนเป็น "ขยะ"</option>
                            <option value="delete">🗑️ ลบข้อมูลที่เลือก</option>
                        </select>
                        <input type="submit" class="ix-btn ix-btn-ghost" value="นำไปใช้" onclick="return confirm('ยืนยันดำเนินการกับรายการที่เลือก?');">
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                        <div style="font-size:13px; color:var(--ix-gray-500);">
                            <span><?php echo $total_items; ?> รายการ · หน้า <?php echo $paged; ?>/<?php echo $total_pages; ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ix-table-wrap">
                    <div class="ix-table-scroll">
                        <table class="ix-table">
                            <thead>
                                <tr>
                                    <th class="ix-col-cb"><input id="cb-select-all" type="checkbox"></th>
                                    <th>วันที่ส่ง</th>
                                    <th>จากฟอร์ม</th>
                                    <?php if ( $filter_form_id && !empty($dynamic_headers) ) : ?>
                                        <?php foreach ( $dynamic_headers as $header ) : ?>
                                            <th><?php echo esc_html( $header ); ?></th>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <th>ข้อมูล</th>
                                    <?php endif; ?>
                                    <th>สถานะ</th>
                                    <th>โน้ต</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( $results ) : foreach ( $results as $row ) :
                                    $data = json_decode( $row->entry_data, true );
                                    $row_status = $row->entry_status ?? 'new';
                                    $row_note = $row->admin_note ?? '';
                                ?>
                                    <tr id="entry-row-<?php echo $row->id; ?>">
                                        <td class="ix-col-cb"><input type="checkbox" name="entry_ids[]" value="<?php echo $row->id; ?>"></td>
                                        <td><span class="ix-date"><?php echo date( 'd/m/Y H:i', strtotime( $row->created_at ) ); ?></span></td>
                                        <td><span class="ix-form-name"><?php echo esc_html( $row->form_title ); ?></span></td>

                                        <?php if ( $filter_form_id && !empty($dynamic_headers) ) : ?>
                                            <?php foreach ( $dynamic_headers as $header ) : ?>
                                                <td><?php echo isset( $data[$header] ) ? nl2br( esc_html( $data[$header] ) ) : '-'; ?></td>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <td>
                                                <div class="ix-data-preview">
                                                    <?php
                                                    if ( is_array($data) ) {
                                                        foreach($data as $k => $v) echo "<strong>".esc_html($k).":</strong> ".esc_html($v)."<br>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>

                                        <td>
                                            <select class="ix-status-select" data-entry-id="<?php echo $row->id; ?>" data-original="<?php echo esc_attr($row_status); ?>">
                                                <?php foreach ( $this->status_map as $skey => $sinfo ) : ?>
                                                    <option value="<?php echo $skey; ?>" <?php selected($row_status, $skey); ?>><?php echo $sinfo['icon'] . ' ' . $sinfo['label']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <td class="ix-note-cell">
                                            <div class="ix-note-display" data-entry-id="<?php echo $row->id; ?>" title="คลิกเพื่อแก้ไขโน้ต">
                                                <?php if ( $row_note ) : ?>
                                                    <span class="ix-note-text"><?php echo esc_html($row_note); ?></span>
                                                <?php else : ?>
                                                    <span class="ix-note-placeholder">+ เพิ่มโน้ต</span>
                                                <?php endif; ?>
                                                <span class="ix-note-edit-icon">✏️</span>
                                            </div>
                                            <div class="ix-note-editor" data-entry-id="<?php echo $row->id; ?>">
                                                <textarea placeholder="บันทึกโน้ตสำหรับรายการนี้..."><?php echo esc_textarea($row_note); ?></textarea>
                                                <div class="ix-note-actions">
                                                    <button type="button" class="ix-note-save" data-entry-id="<?php echo $row->id; ?>">💾 บันทึก</button>
                                                    <button type="button" class="ix-note-cancel" data-entry-id="<?php echo $row->id; ?>">ยกเลิก</button>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <?php $del_url = wp_nonce_url( admin_url( 'edit.php?post_type=acf_form&page=acf-entries&action=delete&entry_id=' . $row->id ), 'delete_entry_' . $row->id ); ?>
                                            <a href="<?php echo $del_url; ?>" class="ix-delete-link" onclick="return confirm('ยืนยันการลบ?')">🗑️ ลบ</a>
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="10">
                                            <div class="ix-empty">
                                                <div class="ix-empty-icon">📭</div>
                                                <div class="ix-empty-text">ไม่พบข้อมูลที่ค้นหา หรือยังไม่มีผู้ส่งฟอร์ม</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="ix-pagination">
                            <?php
                            $page_url = admin_url( 'edit.php?post_type=acf_form&page=acf-entries' );
                            if ( $filter_form_id ) $page_url = add_query_arg( 'filter_form', $filter_form_id, $page_url );
                            if ( $filter_status )  $page_url = add_query_arg( 'filter_status', $filter_status, $page_url );
                            if ( $search_query )   $page_url = add_query_arg( 's', $search_query, $page_url );
                            if ( $start_date )     $page_url = add_query_arg( 'start_date', $start_date, $page_url );
                            if ( $end_date )       $page_url = add_query_arg( 'end_date', $end_date, $page_url );

                            echo paginate_links( [
                                'base' => add_query_arg( 'paged', '%#%', $page_url ),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $paged
                            ]);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <script>
        (function(){
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            var nonce = '<?php echo $entry_nonce; ?>';

            function showToast(msg, type) {
                var t = document.createElement('div');
                t.className = 'ix-toast ' + type;
                t.textContent = msg;
                document.body.appendChild(t);
                setTimeout(function() { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; }, 2500);
                setTimeout(function() { t.remove(); }, 3000);
            }

            document.getElementById('cb-select-all').addEventListener('change', function(e) {
                var cbs = document.querySelectorAll('input[name="entry_ids[]"]');
                for (var i = 0; i < cbs.length; i++) { cbs[i].checked = e.target.checked; }
            });

            document.querySelectorAll('.ix-status-select').forEach(function(sel) {
                sel.addEventListener('change', function() {
                    var entryId = this.dataset.entryId;
                    var status = this.value;
                    var selectEl = this;
                    selectEl.disabled = true;

                    var fd = new FormData();
                    fd.append('action', 'acf_update_entry_status');
                    fd.append('nonce', nonce);
                    fd.append('entry_id', entryId);
                    fd.append('status', status);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                showToast(data.data.message, 'success');
                                selectEl.dataset.original = status;
                            } else {
                                showToast(data.data.message, 'error');
                                selectEl.value = selectEl.dataset.original;
                            }
                        })
                        .catch(function() {
                            showToast('เกิดข้อผิดพลาด', 'error');
                            selectEl.value = selectEl.dataset.original;
                        })
                        .finally(function() { selectEl.disabled = false; });
                });
            });

            document.querySelectorAll('.ix-note-display').forEach(function(disp) {
                disp.addEventListener('click', function() {
                    var id = this.dataset.entryId;
                    this.style.display = 'none';
                    var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
                    editor.classList.add('active');
                    editor.querySelector('textarea').focus();
                });
            });

            document.querySelectorAll('.ix-note-cancel').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.dataset.entryId;
                    var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
                    editor.classList.remove('active');
                    document.querySelector('.ix-note-display[data-entry-id="' + id + '"]').style.display = 'flex';
                });
            });

            document.querySelectorAll('.ix-note-save').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.dataset.entryId;
                    var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
                    var textarea = editor.querySelector('textarea');
                    var note = textarea.value;
                    var saveBtn = this;
                    saveBtn.disabled = true;
                    saveBtn.textContent = '⏳ กำลังบันทึก...';

                    var fd = new FormData();
                    fd.append('action', 'acf_update_entry_note');
                    fd.append('nonce', nonce);
                    fd.append('entry_id', id);
                    fd.append('note', note);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                showToast(data.data.message, 'success');
                                editor.classList.remove('active');
                                var display = document.querySelector('.ix-note-display[data-entry-id="' + id + '"]');
                                display.style.display = 'flex';
                                if (note.trim()) {
                                    display.innerHTML = '<span class="ix-note-text">' + note.replace(/</g,'&lt;') + '</span><span class="ix-note-edit-icon">✏️</span>';
                                } else {
                                    display.innerHTML = '<span class="ix-note-placeholder">+ เพิ่มโน้ต</span><span class="ix-note-edit-icon">✏️</span>';
                                }
                            } else {
                                showToast(data.data.message, 'error');
                            }
                        })
                        .catch(function() { showToast('เกิดข้อผิดพลาด', 'error'); })
                        .finally(function() { saveBtn.disabled = false; saveBtn.textContent = '💾 บันทึก'; });
                });
            });
        })();
        </script>
        <?php
    }

    public function render_dashboard_widget() {
        global $wpdb;
        $table = $wpdb->prefix . 'acf_form_entries';
        $today = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days', strtotime($today)));
        $month_ago = date('Y-m-d', strtotime('-30 days', strtotime($today)));

        $total     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
        $today_c   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) = %s", $today ) );
        $week_c    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) >= %s", $week_ago ) );
        $month_c   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) >= %s", $month_ago ) );

        $status_counts = [];
        foreach ( array_keys($this->status_map) as $sk ) {
            $status_counts[$sk] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE entry_status = %s", $sk ) );
        }

        $recent = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 5" );

        $entries_url = admin_url('edit.php?post_type=acf_form&page=acf-entries');
        ?>
        <style>
            .acf-dash-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px; }
            .acf-dash-card { background:#f8f9fa; border-radius:8px; padding:14px; text-align:center; border:1px solid #e2e4e7; transition:all 0.2s; }
            .acf-dash-card:hover { transform:translateY(-2px); box-shadow:0 4px 8px rgba(0,0,0,0.06); }
            .acf-dash-card .num { font-size:28px; font-weight:700; line-height:1.2; }
            .acf-dash-card .lbl { font-size:11px; color:#757575; margin-top:4px; font-weight:500; }
            .acf-dash-bar { display:flex; height:10px; border-radius:5px; overflow:hidden; margin-bottom:10px; background:#e2e4e7; }
            .acf-dash-bar-seg { transition:width 0.5s ease; min-width:0; }
            .acf-dash-legend { display:flex; flex-wrap:wrap; gap:8px 16px; margin-bottom:16px; font-size:12px; }
            .acf-dash-legend-item { display:flex; align-items:center; gap:5px; }
            .acf-dash-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
            .acf-dash-recent { border-top:1px solid #eee; padding-top:12px; }
            .acf-dash-recent-title { font-weight:600; font-size:13px; color:#1d2327; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
            .acf-dash-recent-title a { font-size:12px; font-weight:400; text-decoration:none; }
            .acf-dash-entry { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f0f0f1; }
            .acf-dash-entry:last-child { border-bottom:none; }
            .acf-dash-entry-info { flex:1; min-width:0; }
            .acf-dash-entry-form { font-size:13px; font-weight:600; color:#1d2327; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .acf-dash-entry-meta { font-size:11px; color:#999; margin-top:2px; }
            .acf-dash-entry-badge { flex-shrink:0; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
            .acf-dash-empty { text-align:center; padding:20px; color:#999; font-size:13px; }
        </style>

        <div class="acf-dash-stats">
            <div class="acf-dash-card">
                <div class="num" style="color:#2271b1;"><?php echo $today_c; ?></div>
                <div class="lbl">วันนี้</div>
            </div>
            <div class="acf-dash-card">
                <div class="num" style="color:#996800;"><?php echo $week_c; ?></div>
                <div class="lbl">7 วันล่าสุด</div>
            </div>
            <div class="acf-dash-card">
                <div class="num" style="color:#2e7d32;"><?php echo $month_c; ?></div>
                <div class="lbl">30 วันล่าสุด</div>
            </div>
            <div class="acf-dash-card">
                <div class="num" style="color:#50575e;"><?php echo $total; ?></div>
                <div class="lbl">ทั้งหมด</div>
            </div>
        </div>

        <?php if ( $total > 0 ) : ?>
            <div class="acf-dash-bar">
                <?php foreach ( $this->status_map as $skey => $sinfo ) :
                    $pct = $total > 0 ? round( ($status_counts[$skey] / $total) * 100, 1 ) : 0;
                    if ( $pct <= 0 ) continue;
                ?>
                    <div class="acf-dash-bar-seg" style="width:<?php echo $pct; ?>%; background:<?php echo $sinfo['color']; ?>;" title="<?php echo $sinfo['label'] . ': ' . $status_counts[$skey]; ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="acf-dash-legend">
                <?php foreach ( $this->status_map as $skey => $sinfo ) : ?>
                    <div class="acf-dash-legend-item">
                        <span class="acf-dash-legend-dot" style="background:<?php echo $sinfo['color']; ?>;"></span>
                        <?php echo $sinfo['icon'] . ' ' . $sinfo['label']; ?>
                        <strong><?php echo $status_counts[$skey]; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="acf-dash-recent">
            <div class="acf-dash-recent-title">
                <span>📥 รายการล่าสุด</span>
                <a href="<?php echo esc_url($entries_url); ?>">ดูทั้งหมด →</a>
            </div>
            <?php if ( $recent ) : foreach ( $recent as $row ) :
                $rs = $this->status_map[ $row->entry_status ?? 'new' ] ?? $this->status_map['new'];
                $time_diff = human_time_diff( strtotime($row->created_at), current_time('timestamp') );
            ?>
                <div class="acf-dash-entry">
                    <div class="acf-dash-entry-info">
                        <div class="acf-dash-entry-form"><?php echo esc_html($row->form_title); ?></div>
                        <div class="acf-dash-entry-meta"><?php echo $time_diff; ?> ที่แล้ว · <?php echo esc_html($row->user_ip); ?></div>
                    </div>
                    <span class="acf-dash-entry-badge" style="color:<?php echo $rs['color']; ?>; background:<?php echo $rs['bg']; ?>;">
                        <?php echo $rs['icon'] . ' ' . $rs['label']; ?>
                    </span>
                </div>
            <?php endforeach; else : ?>
                <div class="acf-dash-empty">ยังไม่มีข้อมูล</div>
            <?php endif; ?>
        </div>
        <?php
    }
}