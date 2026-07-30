<?php
/**
 * Template: Analytics Dashboard page (wp-admin → แบบฟอร์ม → 📊 Analytics).
 *
 * Extracted from ISXF\Entries::render_analytics_page() (Phase 2.2 view
 * split). Static markup — all data is fetched client-side via the
 * isxf_get_analytics_data AJAX action.
 */
?>
        <div class="wrap ix-analytics-wrap">
            <div class="ix-analytics-header">
                <h1><?php esc_html_e( '📊 Analytics Dashboard', 'insightx-form' ); ?></h1>
                <div class="ix-date-filter">
                    <label><?php esc_html_e( '📅 Date range:', 'insightx-form' ); ?></label>
                    <select id="ix-range-select">
                        <option value="7"><?php esc_html_e( 'Last 7 days', 'insightx-form' ); ?></option>
                        <option value="30" selected><?php esc_html_e( 'Last 30 days', 'insightx-form' ); ?></option>
                        <option value="90"><?php esc_html_e( 'Last 90 days', 'insightx-form' ); ?></option>
                        <option value="365"><?php esc_html_e( '1 year', 'insightx-form' ); ?></option>
                        <option value="custom"><?php esc_html_e( 'Custom...', 'insightx-form' ); ?></option>
                    </select>
                    <div id="ix-custom-dates" class="ix-date-custom">
                        <input type="date" id="ix-start-date">
                        <span style="color:#9CA3AF">→</span>
                        <input type="date" id="ix-end-date">
                        <button type="button" id="ix-apply-dates" class="button button-primary" style="padding:4px 14px;"><?php esc_html_e( 'OK', 'insightx-form' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="ix-analytics-stats">
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📦</div>
                    <div class="ix-card-num" id="ix-stat-total">0</div>
                    <div class="ix-card-label"><?php esc_html_e( 'Total', 'insightx-form' ); ?></div>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📊</div>
                    <div class="ix-card-num" id="ix-stat-period">0</div>
                    <div class="ix-card-label"><?php esc_html_e( 'Selected period', 'insightx-form' ); ?></div>
                    <span class="ix-card-delta neutral" id="ix-stat-delta">— 0%</span>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📅</div>
                    <div class="ix-card-num" id="ix-stat-today">0</div>
                    <div class="ix-card-label"><?php esc_html_e( 'Today', 'insightx-form' ); ?></div>
                </div>
                <div class="ix-analytics-card">
                    <div class="ix-card-icon">📈</div>
                    <div class="ix-card-num" id="ix-stat-avg">0</div>
                    <div class="ix-card-label"><?php esc_html_e( 'Avg/day', 'insightx-form' ); ?></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="ix-charts-grid">
                <div class="ix-chart-box">
                    <h3><?php esc_html_e( '📈 Submissions per day', 'insightx-form' ); ?></h3>
                    <div class="ix-chart-canvas-wrap" style="height:300px;">
                        <canvas id="ix-line-chart"></canvas>
                    </div>
                </div>
                <div class="ix-chart-box">
                    <h3><?php esc_html_e( '📊 Status', 'insightx-form' ); ?></h3>
                    <div class="ix-chart-canvas-wrap" style="height:200px; margin-bottom:16px;">
                        <canvas id="ix-doughnut-chart"></canvas>
                    </div>
                    <ul class="ix-status-list" id="ix-status-list">
                        <li class="ix-loading"><?php esc_html_e( 'Loading...', 'insightx-form' ); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Top Forms + Recent -->
            <div class="ix-top-forms-grid">
                <div class="ix-chart-box">
                    <h3><?php esc_html_e( '🏆 Top forms', 'insightx-form' ); ?></h3>
                    <div id="ix-top-forms">
                        <div class="ix-loading"><?php esc_html_e( 'Loading...', 'insightx-form' ); ?></div>
                    </div>
                </div>
                <div class="ix-chart-box">
                    <h3><?php esc_html_e( '🕐 Recent entries', 'insightx-form' ); ?></h3>
                    <div id="ix-recent-list">
                        <div class="ix-loading"><?php esc_html_e( 'Loading...', 'insightx-form' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        