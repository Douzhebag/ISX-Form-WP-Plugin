<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'ISXF_Entries' ) ) {
class ISXF_Entries {

    private $status_map = [
        'new'         => [ 'label' => 'ใหม่',           'color' => '#2271b1', 'bg' => '#e8f0fe', 'icon' => '🔵' ],
        'in_progress' => [ 'label' => 'กำลังดำเนินการ', 'color' => '#996800', 'bg' => '#fff8e5', 'icon' => '🟡' ],
        'done'        => [ 'label' => 'เสร็จสิ้น',      'color' => '#2e7d32', 'bg' => '#edf7ed', 'icon' => '✅' ],
        'junk'        => [ 'label' => 'ขยะ',            'color' => '#a00',    'bg' => '#fef0f0', 'icon' => '🔴' ],
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'isxf_form_after_submission', [ $this, 'save_to_db' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'handle_backend_actions' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_entries_assets' ] );
        add_action( 'wp_ajax_isxf_get_analytics_data', [ $this, 'handle_analytics_ajax' ] );
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
                'nonce'    => wp_create_nonce('isxf_entry_action_nonce')
            ]);
        }
        // Load CSS on dashboard (for widget)
        if ( $hook === 'index.php' ) {
            wp_enqueue_style( 'isxf-entries-css', ISXF_PLUGIN_URL . 'assets/css/isxf-entries.css', [], ISXF_PLUGIN_VERSION );
        }
        // Load on analytics page
        if ( isset($_GET['page']) && $_GET['page'] === 'isxf-analytics' ) {
            wp_enqueue_style( 'isxf-analytics-css', ISXF_PLUGIN_URL . 'assets/css/isxf-analytics.css', [], ISXF_PLUGIN_VERSION );
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true );
            wp_enqueue_script( 'isxf-analytics-js', ISXF_PLUGIN_URL . 'assets/js/isxf-analytics.js', ['chart-js'], ISXF_PLUGIN_VERSION, true );
            wp_localize_script( 'isxf-analytics-js', 'isxf_analytics_env', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('isxf_analytics_nonce')
            ]);
        }
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'isxf_entries_dashboard',
            '📊 InsightX Form — ภาพรวม',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function register_menu() {
        add_submenu_page( 
            'edit.php?post_type=isxf_form', 
            'Entries', 
            '📥 รายการข้อมูล', 
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

        if ( $action === 'delete' && isset( $_GET['entry_id'] ) ) {
            $entry_id = intval( $_GET['entry_id'] );
            check_admin_referer( 'delete_entry_' . $entry_id );
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'isxf_form_entries', [ 'id' => $entry_id ], ['%d'] );
            wp_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=deleted' ) );
            exit;
        }

        if ( isset($_POST['isxf_bulk_action_nonce']) && wp_verify_nonce($_POST['isxf_bulk_action_nonce'], 'isxf_bulk_action') ) {
            $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
            
            if ( ! empty($_POST['entry_ids']) ) {
                global $wpdb;
                $ids = array_map('intval', $_POST['entry_ids']);
                $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));

                if ( $bulk_action === 'delete' ) {
                    $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}isxf_form_entries WHERE id IN ($ids_placeholder)", $ids) );
                    wp_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=bulk_deleted' ) );
                    exit;
                }

                if ( in_array( $bulk_action, ['mark_done', 'mark_in_progress', 'mark_junk'], true ) ) {
                    $status_val = str_replace('mark_', '', $bulk_action);
                    foreach ( $ids as $id ) {
                        $wpdb->update( $wpdb->prefix . 'isxf_form_entries', [ 'entry_status' => $status_val ], [ 'id' => $id ], ['%s'], ['%d'] );
                    }
                    wp_redirect( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&msg=status_updated' ) );
                    exit;
                }
            }
        }

        if ( $action === 'isxf_export_csv' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'คุณไม่มีสิทธิ์ในการส่งออกข้อมูล', 'ข้อผิดพลาดด้านสิทธิ์', 403 );
            }
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'isxf_export_csv_action' ) ) {
                wp_die( 'การยืนยันความปลอดภัยล้มเหลว', 'ข้อผิดพลาดด้านความปลอดภัย', 403 );
            }
            $this->process_csv_export();
        }
    }

    public function save_to_db( $entry_data, $form_id, $user_ip ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'isxf_form_entries', [
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

        $table_name = $wpdb->prefix . 'isxf_form_entries';
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
        $table_name = $wpdb->prefix . 'isxf_form_entries';
        
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

        $forms = get_posts( [ 'post_type' => 'isxf_form', 'numberposts' => -1 ] );
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


        $status_counts = array_fill_keys( array_keys($this->status_map), 0 );
        $status_rows = $wpdb->get_results( "SELECT entry_status, COUNT(id) as cnt FROM $table_name GROUP BY entry_status" );
        foreach ( $status_rows as $row ) {
            if ( isset($status_counts[ $row->entry_status ]) ) {
                $status_counts[ $row->entry_status ] = (int) $row->cnt;
            }
        }
        $status_counts['all'] = array_sum($status_counts);
        ?>

        <div class="wrap ix-wrap">
            <?php if ( isset( $_GET['msg'] ) ) :
                $msgs = [ 'deleted' => 'ลบข้อมูลเรียบร้อยแล้ว!', 'bulk_deleted' => 'ลบข้อมูลที่เลือกเรียบร้อยแล้ว!', 'status_updated' => 'อัพเดตสถานะเรียบร้อยแล้ว!' ];
                $msg_text = $msgs[ sanitize_text_field( $_GET['msg'] ) ] ?? '';
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
                $base_url = admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' );
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
                    <input type="hidden" name="post_type" value="isxf_form">
                    <input type="hidden" name="page" value="isxf-entries">
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
                            <a href="<?php echo admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' ); ?>" class="ix-btn ix-btn-ghost">ล้างค่า</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions & Table -->
            <form method="post" id="isxf-bulk-form">
                <?php wp_nonce_field('isxf_bulk_action', 'isxf_bulk_action_nonce'); ?>

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
                                            <?php $del_url = wp_nonce_url( admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries&action=delete&entry_id=' . $row->id ), 'delete_entry_' . $row->id ); ?>
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
                            $page_url = admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' );
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
        <?php
    }

    /**
     * AJAX handler for analytics data.
     */
    public function handle_analytics_ajax() {
        check_ajax_referer( 'isxf_analytics_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        global $wpdb;
        $table = $wpdb->prefix . 'isxf_form_entries';
        $range = sanitize_text_field( $_POST['range'] ?? '30' );
        $today = current_time('Y-m-d');

        // Calculate date range
        if ( $range === 'custom' ) {
            $start = sanitize_text_field( $_POST['start_date'] ?? '' );
            $end   = sanitize_text_field( $_POST['end_date'] ?? '' );
            if ( empty($start) ) $start = date('Y-m-d', strtotime('-30 days', strtotime($today)));
            if ( empty($end) )   $end = $today;
        } else {
            $days  = intval($range) ?: 30;
            $start = date('Y-m-d', strtotime("-{$days} days", strtotime($today)));
            $end   = $today;
        }

        // Previous period for delta calculation
        $period_days = max(1, (strtotime($end) - strtotime($start)) / 86400);
        $prev_start  = date('Y-m-d', strtotime($start) - ($period_days * 86400));
        $prev_end    = date('Y-m-d', strtotime($start) - 86400);

        // Stats
        $total  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
        $period = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM $table WHERE DATE(created_at) BETWEEN %s AND %s", $start, $end
        ));
        $today_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM $table WHERE DATE(created_at) = %s", $today
        ));
        $prev_period = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM $table WHERE DATE(created_at) BETWEEN %s AND %s", $prev_start, $prev_end
        ));

        $avg = $period_days > 0 ? round($period / $period_days, 1) : 0;
        $delta = $prev_period > 0 ? (($period - $prev_period) / $prev_period) * 100 : ($period > 0 ? 100 : 0);

        // Daily data for line chart
        $daily_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(id) as count FROM $table WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY date ASC",
            $start, $end
        ));

        // Fill missing dates
        $daily = [];
        $date_map = [];
        foreach ( $daily_rows as $row ) {
            $date_map[$row->date] = (int) $row->count;
        }
        $current = $start;
        while ( $current <= $end ) {
            $daily[] = [
                'date'  => date('d/m', strtotime($current)),
                'count' => isset($date_map[$current]) ? $date_map[$current] : 0
            ];
            $current = date('Y-m-d', strtotime('+1 day', strtotime($current)));
        }

        // Status counts
        $status_counts = array_fill_keys( array_keys($this->status_map), 0 );
        $status_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT entry_status, COUNT(id) as cnt FROM $table WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY entry_status",
            $start, $end
        ));
        foreach ( $status_rows as $row ) {
            if ( isset($status_counts[ $row->entry_status ]) ) {
                $status_counts[ $row->entry_status ] = (int) $row->cnt;
            }
        }

        // Top forms
        $top_forms_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT form_title, COUNT(id) as count FROM $table WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY form_id ORDER BY count DESC LIMIT 10",
            $start, $end
        ));
        $top_forms = [];
        foreach ( $top_forms_rows as $row ) {
            $top_forms[] = [ 'title' => $row->form_title, 'count' => (int) $row->count ];
        }

        // Recent 10
        $recent_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT form_title, entry_status, user_ip, created_at FROM $table WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY id DESC LIMIT 10",
            $start, $end
        ));
        $recent = [];
        foreach ( $recent_rows as $row ) {
            $recent[] = [
                'form_title' => $row->form_title,
                'status'     => $row->entry_status,
                'ip'         => $row->user_ip,
                'time_ago'   => human_time_diff( strtotime($row->created_at), current_time('timestamp') ) . ' ที่แล้ว'
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

    /**
     * Render Analytics Dashboard page.
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap ix-analytics-wrap">
            <div class="ix-analytics-header">
                <h1>📊 Analytics Dashboard</h1>
                <div class="ix-date-filter">
                    <label>📅 ช่วงเวลา:</label>
                    <select id="ix-range-select">
                        <option value="7">7 วันล่าสุด</option>
                        <option value="30" selected>30 วันล่าสุด</option>
                        <option value="90">90 วันล่าสุด</option>
                        <option value="365">1 ปี</option>
                        <option value="custom">กำหนดเอง...</option>
                    </select>
                    <div id="ix-custom-dates" class="ix-date-custom">
                        <input type="date" id="ix-start-date">
                        <span style="color:#9CA3AF">→</span>
                        <input type="date" id="ix-end-date">
                        <button type="button" id="ix-apply-dates" class="button button-primary" style="padding:4px 14px;">ตกลง</button>
                    </div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="ix-analytics-stats">
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📦</div>
                    <div class="ix-card-num" id="ix-stat-total">0</div>
                    <div class="ix-card-label">ทั้งหมด</div>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📊</div>
                    <div class="ix-card-num" id="ix-stat-period">0</div>
                    <div class="ix-card-label">ช่วงที่เลือก</div>
                    <span class="ix-card-delta neutral" id="ix-stat-delta">— 0%</span>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📅</div>
                    <div class="ix-card-num" id="ix-stat-today">0</div>
                    <div class="ix-card-label">วันนี้</div>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📈</div>
                    <div class="ix-card-num" id="ix-stat-avg">0</div>
                    <div class="ix-card-label">เฉลี่ย/วัน</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="ix-charts-grid">
                <div class="ix-chart-box">
                    <h3>📈 Submissions ต่อวัน</h3>
                    <div class="ix-chart-canvas-wrap" style="height:300px;">
                        <canvas id="ix-line-chart"></canvas>
                    </div>
                </div>
                <div class="ix-chart-box">
                    <h3>📊 สถานะ</h3>
                    <div class="ix-chart-canvas-wrap" style="height:200px; margin-bottom:16px;">
                        <canvas id="ix-doughnut-chart"></canvas>
                    </div>
                    <ul class="ix-status-list" id="ix-status-list">
                        <li class="ix-loading">กำลังโหลด...</li>
                    </ul>
                </div>
            </div>

            <!-- Top Forms + Recent -->
            <div class="ix-top-forms-grid">
                <div class="ix-chart-box">
                    <h3>🏆 ฟอร์มยอดนิยม</h3>
                    <div id="ix-top-forms">
                        <div class="ix-loading">กำลังโหลด...</div>
                    </div>
                </div>
                <div class="ix-chart-box">
                    <h3>🕐 รายการล่าสุด</h3>
                    <div id="ix-recent-list">
                        <div class="ix-loading">กำลังโหลด...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_dashboard_widget() {
        global $wpdb;
        $table = $wpdb->prefix . 'isxf_form_entries';
        $today = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days', strtotime($today)));
        $month_ago = date('Y-m-d', strtotime('-30 days', strtotime($today)));

        $total     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
        $today_c   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) = %s", $today ) );
        $week_c    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) >= %s", $week_ago ) );
        $month_c   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE DATE(created_at) >= %s", $month_ago ) );

        $status_counts = array_fill_keys( array_keys($this->status_map), 0 );
        $status_rows = $wpdb->get_results( "SELECT entry_status, COUNT(id) as cnt FROM $table GROUP BY entry_status" );
        foreach ( $status_rows as $row ) {
            if ( isset($status_counts[ $row->entry_status ]) ) {
                $status_counts[ $row->entry_status ] = (int) $row->cnt;
            }
        }

        $recent = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 5" );

        $entries_url = admin_url('edit.php?post_type=isxf_form&page=isxf-entries');
        ?>

        <div class="isxf-dash-stats">
            <div class="isxf-dash-card">
                <div class="num" style="color:#2271b1;"><?php echo $today_c; ?></div>
                <div class="lbl">วันนี้</div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#996800;"><?php echo $week_c; ?></div>
                <div class="lbl">7 วันล่าสุด</div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#2e7d32;"><?php echo $month_c; ?></div>
                <div class="lbl">30 วันล่าสุด</div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#50575e;"><?php echo $total; ?></div>
                <div class="lbl">ทั้งหมด</div>
            </div>
        </div>

        <?php if ( $total > 0 ) : ?>
            <div class="isxf-dash-bar">
                <?php foreach ( $this->status_map as $skey => $sinfo ) :
                    $pct = $total > 0 ? round( ($status_counts[$skey] / $total) * 100, 1 ) : 0;
                    if ( $pct <= 0 ) continue;
                ?>
                    <div class="isxf-dash-bar-seg" style="width:<?php echo $pct; ?>%; background:<?php echo $sinfo['color']; ?>;" title="<?php echo $sinfo['label'] . ': ' . $status_counts[$skey]; ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="isxf-dash-legend">
                <?php foreach ( $this->status_map as $skey => $sinfo ) : ?>
                    <div class="isxf-dash-legend-item">
                        <span class="isxf-dash-legend-dot" style="background:<?php echo $sinfo['color']; ?>;"></span>
                        <?php echo $sinfo['icon'] . ' ' . $sinfo['label']; ?>
                        <strong><?php echo $status_counts[$skey]; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="isxf-dash-recent">
            <div class="isxf-dash-recent-title">
                <span>📥 รายการล่าสุด</span>
                <a href="<?php echo esc_url($entries_url); ?>">ดูทั้งหมด →</a>
            </div>
            <?php if ( $recent ) : foreach ( $recent as $row ) :
                $rs = $this->status_map[ $row->entry_status ?? 'new' ] ?? $this->status_map['new'];
                $time_diff = human_time_diff( strtotime($row->created_at), current_time('timestamp') );
            ?>
                <div class="isxf-dash-entry">
                    <div class="isxf-dash-entry-info">
                        <div class="isxf-dash-entry-form"><?php echo esc_html($row->form_title); ?></div>
                        <div class="isxf-dash-entry-meta"><?php echo $time_diff; ?> ที่แล้ว · <?php echo esc_html($row->user_ip); ?></div>
                    </div>
                    <span class="isxf-dash-entry-badge" style="color:<?php echo $rs['color']; ?>; background:<?php echo $rs['bg']; ?>;">
                        <?php echo $rs['icon'] . ' ' . $rs['label']; ?>
                    </span>
                </div>
            <?php endforeach; else : ?>
                <div class="isxf-dash-empty">ยังไม่มีข้อมูล</div>
            <?php endif; ?>
        </div>
        <?php
    }
}
}