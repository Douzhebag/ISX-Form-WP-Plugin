/**
 * InsightX Analytics Dashboard — JavaScript
 * Chart.js rendering + date range filtering via AJAX
 */
(function () {
    'use strict';

    var env = acf_analytics_env || {};
    var lineChart = null;
    var doughnutChart = null;

    // Color palette
    var colors = {
        primary: '#4F46E5',
        primaryLight: 'rgba(79,70,229,0.1)',
        new: '#2271b1',
        in_progress: '#996800',
        done: '#2e7d32',
        junk: '#aa0000'
    };

    /**
     * Initialize date range filter
     */
    function initDateFilter() {
        var rangeSelect = document.getElementById('ix-range-select');
        var customWrap = document.getElementById('ix-custom-dates');
        var startInput = document.getElementById('ix-start-date');
        var endInput = document.getElementById('ix-end-date');

        if (!rangeSelect) return;

        rangeSelect.addEventListener('change', function () {
            if (this.value === 'custom') {
                customWrap.classList.add('active');
            } else {
                customWrap.classList.remove('active');
                fetchAnalyticsData(this.value, '', '');
            }
        });

        // Custom date apply
        var applyBtn = document.getElementById('ix-apply-dates');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                fetchAnalyticsData('custom', startInput.value, endInput.value);
            });
        }

        // Initial load
        fetchAnalyticsData(rangeSelect.value, '', '');
    }

    /**
     * Fetch analytics data via AJAX
     */
    function fetchAnalyticsData(range, startDate, endDate) {
        var fd = new FormData();
        fd.append('action', 'acf_get_analytics_data');
        fd.append('nonce', env.nonce);
        fd.append('range', range);
        fd.append('start_date', startDate);
        fd.append('end_date', endDate);

        // Show loading
        var statsWrap = document.querySelector('.ix-analytics-stats');
        if (statsWrap) statsWrap.style.opacity = '0.5';

        fetch(env.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (response) {
                if (response.success) {
                    renderDashboard(response.data);
                }
            })
            .catch(function (err) {
                console.error('Analytics fetch error:', err);
            })
            .finally(function () {
                if (statsWrap) statsWrap.style.opacity = '1';
            });
    }

    /**
     * Render all dashboard components
     */
    function renderDashboard(data) {
        renderStatCards(data.stats);
        renderLineChart(data.daily);
        renderDoughnutChart(data.status_counts);
        renderTopForms(data.top_forms);
        renderRecent(data.recent);
    }

    /**
     * Update stat cards with animation
     */
    function renderStatCards(stats) {
        animateNumber('ix-stat-total', stats.total);
        animateNumber('ix-stat-period', stats.period);
        animateNumber('ix-stat-today', stats.today);
        animateNumber('ix-stat-avg', stats.avg_per_day);

        // Delta indicator
        var deltaEl = document.getElementById('ix-stat-delta');
        if (deltaEl && stats.delta !== undefined) {
            var delta = parseFloat(stats.delta);
            if (delta > 0) {
                deltaEl.className = 'ix-card-delta up';
                deltaEl.textContent = '↑ ' + delta.toFixed(0) + '%';
            } else if (delta < 0) {
                deltaEl.className = 'ix-card-delta down';
                deltaEl.textContent = '↓ ' + Math.abs(delta).toFixed(0) + '%';
            } else {
                deltaEl.className = 'ix-card-delta neutral';
                deltaEl.textContent = '— 0%';
            }
        }
    }

    function animateNumber(id, target) {
        var el = document.getElementById(id);
        if (!el) return;
        var current = parseInt(el.textContent) || 0;
        target = parseInt(target) || 0;
        if (current === target) { el.textContent = target; return; }

        var duration = 400;
        var start = performance.now();
        function step(now) {
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(current + (target - current) * eased);
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    /**
     * Line chart — submissions per day
     */
    function renderLineChart(dailyData) {
        var ctx = document.getElementById('ix-line-chart');
        if (!ctx) return;

        var labels = dailyData.map(function (d) { return d.date; });
        var values = dailyData.map(function (d) { return d.count; });

        if (lineChart) lineChart.destroy();

        lineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Submissions',
                    data: values,
                    borderColor: colors.primary,
                    backgroundColor: colors.primaryLight,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: colors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17,24,39,0.9)',
                        titleFont: { size: 12 },
                        bodyFont: { size: 13, weight: '600' },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) { return ctx.parsed.y + ' รายการ'; }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#9CA3AF', maxRotation: 45 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            font: { size: 11 },
                            color: '#9CA3AF',
                            stepSize: 1,
                            callback: function (v) { return Number.isInteger(v) ? v : ''; }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    /**
     * Doughnut chart — status distribution
     */
    function renderDoughnutChart(statusCounts) {
        var ctx = document.getElementById('ix-doughnut-chart');
        if (!ctx) return;

        var statusMap = {
            'new': { label: 'ใหม่', color: colors.new },
            'in_progress': { label: 'กำลังดำเนินการ', color: colors.in_progress },
            'done': { label: 'เสร็จสิ้น', color: colors.done },
            'junk': { label: 'ขยะ', color: colors.junk }
        };

        var labels = [];
        var values = [];
        var bgColors = [];

        for (var key in statusMap) {
            labels.push(statusMap[key].label);
            values.push(statusCounts[key] || 0);
            bgColors.push(statusMap[key].color);
        }

        // Update status list
        var listEl = document.getElementById('ix-status-list');
        if (listEl) {
            var html = '';
            for (var k in statusMap) {
                html += '<li>' +
                    '<span class="ix-status-label"><span class="ix-status-dot" style="background:' + statusMap[k].color + '"></span>' + statusMap[k].label + '</span>' +
                    '<span class="ix-status-count">' + (statusCounts[k] || 0) + '</span>' +
                    '</li>';
            }
            listEl.innerHTML = html;
        }

        if (doughnutChart) doughnutChart.destroy();

        doughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17,24,39,0.9)',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Top forms ranking
     */
    function renderTopForms(topForms) {
        var container = document.getElementById('ix-top-forms');
        if (!container || !topForms) return;

        if (topForms.length === 0) {
            container.innerHTML = '<div class="ix-loading">ยังไม่มีข้อมูล</div>';
            return;
        }

        var maxCount = topForms[0].count;
        var html = '';
        topForms.forEach(function (form, i) {
            var rankClass = i === 0 ? '' : (i === 1 ? 'r2' : (i === 2 ? 'r3' : 'rn'));
            var pct = maxCount > 0 ? ((form.count / maxCount) * 100) : 0;
            html += '<div class="ix-top-form-item">' +
                '<div class="ix-top-form-rank ' + rankClass + '">' + (i + 1) + '</div>' +
                '<div class="ix-top-form-info">' +
                '<div class="ix-top-form-name">' + escapeHtml(form.title) + '</div>' +
                '<div class="ix-top-form-bar"><div class="ix-top-form-bar-fill" style="width:' + pct + '%"></div></div>' +
                '</div>' +
                '<div class="ix-top-form-count">' + form.count + '</div>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    /**
     * Recent entries
     */
    function renderRecent(recent) {
        var container = document.getElementById('ix-recent-list');
        if (!container || !recent) return;

        if (recent.length === 0) {
            container.innerHTML = '<div class="ix-loading">ยังไม่มีข้อมูล</div>';
            return;
        }

        var statusIcons = { 'new': '🔵', 'in_progress': '🟡', 'done': '✅', 'junk': '🔴' };
        var statusLabels = { 'new': 'ใหม่', 'in_progress': 'กำลังดำเนินการ', 'done': 'เสร็จสิ้น', 'junk': 'ขยะ' };
        var statusColors = { 'new': '#2271b1', 'in_progress': '#996800', 'done': '#2e7d32', 'junk': '#aa0000' };
        var statusBgs = { 'new': '#e8f0fe', 'in_progress': '#fff8e5', 'done': '#edf7ed', 'junk': '#fef0f0' };

        var html = '';
        recent.forEach(function (entry) {
            var st = entry.status || 'new';
            html += '<div class="ix-recent-item">' +
                '<div class="ix-recent-icon" style="background:' + (statusBgs[st] || '#f3f4f6') + '">' + (statusIcons[st] || '🔵') + '</div>' +
                '<div class="ix-recent-info">' +
                '<div class="ix-recent-title">' + escapeHtml(entry.form_title) + '</div>' +
                '<div class="ix-recent-meta">' + entry.time_ago + ' · ' + escapeHtml(entry.ip) + '</div>' +
                '</div>' +
                '<span class="ix-recent-badge" style="color:' + (statusColors[st] || '#6B7280') + '; background:' + (statusBgs[st] || '#f3f4f6') + '">' +
                (statusIcons[st] || '') + ' ' + (statusLabels[st] || st) +
                '</span>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDateFilter);
    } else {
        initDateFilter();
    }
})();
