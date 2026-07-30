<?php
/**
 * Template: WP-Admin dashboard widget ("📊 InsightX Form — ภาพรวม").
 *
 * Extracted from ISXF\Entries::render_dashboard_widget() (Phase 2.2 view
 * split). Data gathering stays in the render method; this file receives:
 *
 * @var int    $today_c       Entries submitted today.
 * @var int    $week_c        Entries from the last 7 days.
 * @var int    $month_c       Entries from the last 30 days.
 * @var int    $total         Total entries.
 * @var array  $status_counts Entry counts per status.
 * @var array  $status_map    Status key => label/color/bg/icon map.
 * @var array  $recent        5 most recent entry rows.
 * @var string $entries_url   URL of the entries page.
 */
?>

        <div class="isxf-dash-stats">
            <div class="isxf-dash-card">
                <div class="num" style="color:#2271b1;"><?php echo $today_c; ?></div>
                <div class="lbl"><?php esc_html_e( 'Today', 'insightx-form' ); ?></div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#996800;"><?php echo $week_c; ?></div>
                <div class="lbl"><?php esc_html_e( 'Last 7 days', 'insightx-form' ); ?></div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#2e7d32;"><?php echo $month_c; ?></div>
                <div class="lbl"><?php esc_html_e( 'Last 30 days', 'insightx-form' ); ?></div>
            </div>
            <div class="isxf-dash-card">
                <div class="num" style="color:#50575e;"><?php echo $total; ?></div>
                <div class="lbl"><?php esc_html_e( 'Total', 'insightx-form' ); ?></div>
            </div>
        </div>

        <?php if ( $total > 0 ) : ?>
            <div class="isxf-dash-bar">
                <?php foreach ( $status_map as $skey => $sinfo ) :
                    $pct = $total > 0 ? round( ($status_counts[$skey] / $total) * 100, 1 ) : 0;
                    if ( $pct <= 0 ) continue;
                ?>
                    <div class="isxf-dash-bar-seg" style="width:<?php echo $pct; ?>%; background:<?php echo $sinfo['color']; ?>;" title="<?php echo $sinfo['label'] . ': ' . $status_counts[$skey]; ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="isxf-dash-legend">
                <?php foreach ( $status_map as $skey => $sinfo ) : ?>
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
                <span><?php esc_html_e( '📥 Recent entries', 'insightx-form' ); ?></span>
                <a href="<?php echo esc_url($entries_url); ?>"><?php esc_html_e( 'View all →', 'insightx-form' ); ?></a>
            </div>
            <?php if ( $recent ) : foreach ( $recent as $row ) :
                $rs = $status_map[ $row->entry_status ?? 'new' ] ?? $status_map['new'];
                $time_diff = human_time_diff( isxf_local_datetime_to_timestamp( $row->created_at ), current_datetime()->getTimestamp() );
            ?>
                <div class="isxf-dash-entry">
                    <div class="isxf-dash-entry-info">
                        <div class="isxf-dash-entry-form"><?php echo esc_html($row->form_title); ?></div>
                        <div class="isxf-dash-entry-meta">
                            <?php
                            /* translators: %s: human-readable time difference (e.g. "2 hours"). */
                            printf( esc_html__( '%s ago', 'insightx-form' ), esc_html( $time_diff ) );
                            ?> · <?php echo esc_html($row->user_ip); ?>
                        </div>
                    </div>
                    <span class="isxf-dash-entry-badge" style="color:<?php echo $rs['color']; ?>; background:<?php echo $rs['bg']; ?>;">
                        <?php echo $rs['icon'] . ' ' . $rs['label']; ?>
                    </span>
                </div>
            <?php endforeach; else : ?>
                <div class="isxf-dash-empty"><?php esc_html_e( 'No entries yet', 'insightx-form' ); ?></div>
            <?php endif; ?>
        </div>
        