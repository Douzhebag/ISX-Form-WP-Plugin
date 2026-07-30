<?php
/**
 * Template: entries list page (wp-admin → แบบฟอร์ม → 📥 รายการข้อมูล).
 *
 * Extracted from ISXF\Entries::render_page() (Phase 2.2 view split).
 * Data gathering (filters, queries, counts, URLs) stays in the render
 * method; this file receives:
 *
 * @var int    $filter_form_id  Active form filter (0 = all forms).
 * @var string $filter_status   Active status filter ('' = all).
 * @var string $search_query    Active search query.
 * @var string $start_date      Active start-date filter.
 * @var string $end_date        Active end-date filter.
 * @var int    $paged           Current page number.
 * @var int    $total_items     Total matching entries.
 * @var int    $total_pages     Total pages.
 * @var array  $results         Entry rows for the current page.
 * @var array  $forms           All isxf_form posts (for the filter dropdown).
 * @var array  $dynamic_headers Per-field headers when a form is filtered.
 * @var string $export_url      Nonce'd CSV export URL (filters preserved).
 * @var array  $status_counts   Entry counts per status + 'all'.
 * @var array  $status_map      Status key => label/color/bg/icon map.
 * @var string $msg_text        Notice text for ?msg= ('' = no notice).
 * @var array  $stat_cards      Stat card definitions (icon/label/key/color).
 * @var string $base_url        Entries page URL with form/search filters kept.
 * @var string $page_url        Entries page URL with all filters kept (pagination).
 */
?>

        <div class="wrap ix-wrap">
            <?php if ( $msg_text ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo $msg_text; ?></p></div>
            <?php endif; ?>

            <!-- Header -->
            <div class="ix-header">
                <h1><?php esc_html_e( '📥 Entries', 'insightx-form' ); ?> <?php if ($total_items > 0) : ?><span class="ix-count"><?php
                    /* translators: %d: total number of entries. */
                    printf( esc_html__( '(%d entries)', 'insightx-form' ), $total_items );
                ?></span><?php endif; ?></h1>
                <a href="<?php echo esc_url( $export_url ); ?>" class="ix-btn-export"><?php esc_html_e( '📊 Export CSV', 'insightx-form' ); ?></a>
            </div>

            <!-- Stats Cards -->
            <div class="ix-stats">
                <?php foreach ( $stat_cards as $sc ) : ?>
                    <div class="ix-stat-card">
                        <div class="ix-stat-num" style="color:<?php echo $sc['color']; ?>"><?php echo $status_counts[$sc['key']]; ?></div>
                        <div class="ix-stat-label"><?php echo $sc['icon'] . ' ' . $sc['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Status Tabs -->
            <div class="ix-tabs">
                <?php /* $base_url is built in Entries::render_page(). */ ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo empty($filter_status) ? 'active' : ''; ?>"><?php esc_html_e( '📋 All', 'insightx-form' ); ?> <span class="ix-badge"><?php echo $status_counts['all']; ?></span></a>
                <?php foreach ( $status_map as $skey => $sinfo ) : ?>
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
                        <label><?php esc_html_e( '📑 Form', 'insightx-form' ); ?></label>
                        <select name="filter_form">
                            <option value="0"><?php esc_html_e( '-- All forms --', 'insightx-form' ); ?></option>
                            <?php foreach ( $forms as $f ) : ?>
                                <option value="<?php echo $f->ID; ?>" <?php selected( $filter_form_id, $f->ID ); ?>><?php echo esc_html( $f->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ix-filter-group">
                        <label><?php esc_html_e( '📅 Date range', 'insightx-form' ); ?></label>
                        <div class="ix-date-range">
                            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                            <span class="ix-date-sep">—</span>
                            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        </div>
                    </div>

                    <div class="ix-filter-group" style="flex-grow:1; max-width:300px;">
                        <label><?php esc_html_e( '🔍 Search', 'insightx-form' ); ?></label>
                        <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e( 'Name, phone, email, IP...', 'insightx-form' ); ?>">
                    </div>

                    <div class="ix-filter-actions">
                        <button type="submit" class="ix-btn ix-btn-primary"><?php esc_html_e( 'Search', 'insightx-form' ); ?></button>
                        <?php if ( $filter_form_id || $search_query || $start_date || $end_date || $filter_status ) : ?>
                            <a href="<?php echo admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' ); ?>" class="ix-btn ix-btn-ghost"><?php esc_html_e( 'Clear', 'insightx-form' ); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions & Table -->
            <form method="post" id="isxf-bulk-form">
                <?php wp_nonce_field('isxf_bulk_action', 'isxf_bulk_action_nonce'); ?>
                <?php /* Shared nonce for the per-row POST delete buttons below (single delete, not bulk). */ ?>
                <?php wp_nonce_field('isxf_delete_entry', 'isxf_delete_entry_nonce'); ?>

                <div class="ix-bulk-bar">
                    <div class="ix-bulk-left">
                        <select name="bulk_action">
                            <option value="-1"><?php esc_html_e( 'Bulk Actions', 'insightx-form' ); ?></option>
                            <option value="mark_done"><?php esc_html_e( '✅ Mark as "Done"', 'insightx-form' ); ?></option>
                            <option value="mark_in_progress"><?php esc_html_e( '🟡 Mark as "In progress"', 'insightx-form' ); ?></option>
                            <option value="mark_junk"><?php esc_html_e( '🔴 Mark as "Junk"', 'insightx-form' ); ?></option>
                            <option value="delete"><?php esc_html_e( '🗑️ Delete selected', 'insightx-form' ); ?></option>
                        </select>
                        <input type="submit" class="ix-btn ix-btn-ghost" value="<?php esc_attr_e( 'Apply', 'insightx-form' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Confirm action on the selected entries?', 'insightx-form' ) ); ?>');">
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                        <div style="font-size:13px; color:var(--ix-gray-500);">
                            <span><?php
                                /* translators: 1: total number of entries, 2: current page number, 3: total number of pages. */
                                printf( esc_html__( '%1$d entries · Page %2$d/%3$d', 'insightx-form' ), $total_items, $paged, $total_pages );
                            ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ix-table-wrap">
                    <div class="ix-table-scroll">
                        <table class="ix-table">
                            <thead>
                                <tr>
                                    <th class="ix-col-cb"><input id="cb-select-all" type="checkbox"></th>
                                    <th><?php esc_html_e( 'Submitted', 'insightx-form' ); ?></th>
                                    <th><?php esc_html_e( 'From form', 'insightx-form' ); ?></th>
                                    <?php if ( $filter_form_id && !empty($dynamic_headers) ) : ?>
                                        <?php foreach ( $dynamic_headers as $header ) : ?>
                                            <th><?php echo esc_html( $header ); ?></th>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <th><?php esc_html_e( 'Data', 'insightx-form' ); ?></th>
                                    <?php endif; ?>
                                    <th><?php esc_html_e( 'Status', 'insightx-form' ); ?></th>
                                    <th><?php esc_html_e( 'Note', 'insightx-form' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'insightx-form' ); ?></th>
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
                                        <td><span class="ix-date"><?php echo esc_html( wp_date( 'd/m/Y H:i', isxf_local_datetime_to_timestamp( $row->created_at ) ) ); ?></span></td>
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
                                                <?php foreach ( $status_map as $skey => $sinfo ) : ?>
                                                    <option value="<?php echo $skey; ?>" <?php selected($row_status, $skey); ?>><?php echo $sinfo['icon'] . ' ' . $sinfo['label']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <td class="ix-note-cell">
                                            <div class="ix-note-display" data-entry-id="<?php echo $row->id; ?>" title="<?php esc_attr_e( 'Click to edit note', 'insightx-form' ); ?>">
                                                <?php if ( $row_note ) : ?>
                                                    <span class="ix-note-text"><?php echo esc_html($row_note); ?></span>
                                                <?php else : ?>
                                                    <span class="ix-note-placeholder"><?php esc_html_e( '+ Add note', 'insightx-form' ); ?></span>
                                                <?php endif; ?>
                                                <span class="ix-note-edit-icon">✏️</span>
                                            </div>
                                            <div class="ix-note-editor" data-entry-id="<?php echo $row->id; ?>">
                                                <textarea placeholder="<?php esc_attr_e( 'Add a note for this entry...', 'insightx-form' ); ?>"><?php echo esc_textarea($row_note); ?></textarea>
                                                <div class="ix-note-actions">
                                                    <button type="button" class="ix-note-save" data-entry-id="<?php echo $row->id; ?>"><?php esc_html_e( '💾 Save', 'insightx-form' ); ?></button>
                                                    <button type="button" class="ix-note-cancel" data-entry-id="<?php echo $row->id; ?>"><?php esc_html_e( 'Cancel', 'insightx-form' ); ?></button>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <?php /* Single delete is a POST submit inside the shared table form (state-changing GET removed in Phase 2.7). */ ?>
                                            <button type="submit" name="isxf_delete_entry" value="<?php echo (int) $row->id; ?>" class="ix-delete-link" onclick="return confirm('<?php echo esc_js( __( 'Confirm deletion?', 'insightx-form' ) ); ?>')"><?php esc_html_e( '🗑️ Delete', 'insightx-form' ); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="10">
                                            <div class="ix-empty">
                                                <div class="ix-empty-icon">📭</div>
                                                <div class="ix-empty-text"><?php esc_html_e( 'No matching entries found, or no form submissions yet.', 'insightx-form' ); ?></div>
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
        