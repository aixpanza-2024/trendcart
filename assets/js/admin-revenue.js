/**
 * Admin Revenue & Analytics
 */

let currentPeriod = 'daily';

document.addEventListener('DOMContentLoaded', function () {
    loadRevenue('daily');
});

async function loadRevenue(period) {
    currentPeriod = period;

    // Toggle button active state
    document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active', 'btn-dark'));
    document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.add('btn-outline-dark'));
    const btn = document.getElementById('btn' + period.charAt(0).toUpperCase() + period.slice(1));
    if (btn) { btn.classList.add('active', 'btn-dark'); btn.classList.remove('btn-outline-dark'); }

    const labels = { daily: "Today's Summary", weekly: "This Week's Summary", monthly: "This Month's Summary" };
    document.getElementById('revenuePeriodLabel').textContent = labels[period] || '';

    try {
        const result = await adminAPI('../api/admin/revenue.php?period=' + period);
        if (result.success && result.data) {
            renderSummary(result.data.summary);
            renderShopRevenue(result.data.shop_revenue);
            renderRevenueLog(result.data.revenue_log);
        }
    } catch (e) {
        console.error('Revenue load error:', e);
    }
}

function renderSummary(s) {
    if (!s) return;
    document.getElementById('revTotal').textContent = formatINR(s.total_revenue);
    document.getElementById('revOrders').textContent = s.total_orders || 0;
    document.getElementById('revDelivered').textContent = formatINR(s.delivered_revenue);
    document.getElementById('revCancelled').textContent = formatINR(s.cancelled_revenue);
}

function renderShopRevenue(shops) {
    const tbody = document.getElementById('shopRevenueTable');
    if (!shops || !shops.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><p>No shop data available</p></td></tr>';
        return;
    }

    tbody.innerHTML = shops.map(s => `
        <tr>
            <td><strong>${s.shop_name}</strong></td>
            <td>${formatINR(s.today_sales)}</td>
            <td>${formatINR(s.weekly_sales)}</td>
            <td>${formatINR(s.monthly_sales)}</td>
            <td><strong>${formatINR(s.total_sales)}</strong></td>
            <td class="text-warning fw-bold">${formatINR(s.total_pending)}</td>
            <td>${statusBadge(s.shop_status)}</td>
        </tr>
    `).join('');
}

function renderRevenueLog(log) {
    const tbody = document.getElementById('revenueLogTable');
    if (!log || !log.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="admin-empty-state"><p>No revenue data</p></td></tr>';
        return;
    }

    tbody.innerHTML = log.map(r => `
        <tr>
            <td><strong>${formatDate(r.order_day)}</strong></td>
            <td>${r.total_orders}</td>
            <td><strong>${formatINR(r.total_revenue)}</strong></td>
            <td class="text-success">${formatINR(r.delivered_revenue)}</td>
            <td class="text-danger">${formatINR(r.cancelled_revenue)}</td>
            <td class="hide-mobile">${r.unique_customers}</td>
        </tr>
    `).join('');
}
