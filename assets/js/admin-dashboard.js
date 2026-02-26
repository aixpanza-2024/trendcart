/**
 * Admin Dashboard - JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    loadDashboardData();
});

async function loadDashboardData() {
    try {
        const result = await adminAPI('../api/admin/dashboard.php');

        if (result.success && result.data) {
            renderStats(result.data.stats);
            renderRevenue(result.data.revenue);
            renderPendingOrders(result.data.pending_orders);
            renderTopShops(result.data.top_shops);
            renderRecentCustomers(result.data.recent_customers);
            renderOrderStatusBreakdown(result.data.order_status_counts);

            // Sidebar badge
            const badge = document.getElementById('sidebarPendingOrders');
            if (badge && result.data.stats.pending_orders > 0) {
                badge.textContent = result.data.stats.pending_orders;
            }
        }
    } catch (e) {
        console.error('Dashboard load error:', e);
    }
}

function renderStats(s) {
    if (!s) return;
    document.getElementById('statTotalOrders').textContent = s.total_orders || '0';
    document.getElementById('statTotalRevenue').textContent = formatINR(s.total_revenue);
    document.getElementById('statTotalShops').textContent = s.active_shops || '0';
    document.getElementById('statTotalCustomers').textContent = s.total_customers || '0';
}

function renderRevenue(r) {
    if (!r) return;
    document.getElementById('statTodayRevenue').textContent = formatINR(r.today);
    document.getElementById('statWeeklyRevenue').textContent = formatINR(r.weekly);
    document.getElementById('statMonthlyRevenue').textContent = formatINR(r.monthly);
}

function renderPendingOrders(orders) {
    const tbody = document.getElementById('pendingOrdersTable');
    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="admin-empty-state"><i class="fas fa-check-circle"></i><p>No pending orders</p></td></tr>';
        return;
    }

    tbody.innerHTML = orders.map(o => `
        <tr>
            <td><strong>#${o.order_number}</strong></td>
            <td>${o.customer_name || '-'}</td>
            <td>${formatINR(o.total_amount)}</td>
            <td>${statusBadge(o.order_status)}</td>
            <td>
                <a href="orders.html?id=${o.order_id}" class="btn btn-sm btn-outline-dark btn-action">
                    <i class="fas fa-eye"></i>
                </a>
            </td>
        </tr>
    `).join('');
}

function renderTopShops(shops) {
    const tbody = document.getElementById('topShopsTable');
    if (!shops || shops.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="admin-empty-state"><p>No shops yet</p></td></tr>';
        return;
    }

    tbody.innerHTML = shops.map(s => `
        <tr>
            <td><strong>${s.shop_name}</strong></td>
            <td>${formatINR(s.total_sales)}</td>
            <td>${statusBadge(s.shop_status)}</td>
        </tr>
    `).join('');
}

function renderRecentCustomers(customers) {
    const tbody = document.getElementById('recentCustomersTable');
    if (!customers || customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="admin-empty-state"><p>No customers yet</p></td></tr>';
        return;
    }

    tbody.innerHTML = customers.map(c => `
        <tr>
            <td>${c.full_name || '-'}</td>
            <td class="hide-mobile">${c.email}</td>
            <td>${formatDate(c.created_at)}</td>
        </tr>
    `).join('');
}

function renderOrderStatusBreakdown(counts) {
    const container = document.getElementById('orderStatusBreakdown');
    if (!counts) {
        container.innerHTML = '<p class="text-muted">No data available</p>';
        return;
    }

    const statuses = [
        { key: 'pending', label: 'Pending', color: '#ffc107' },
        { key: 'confirmed', label: 'Confirmed', color: '#0d6efd' },
        { key: 'processing', label: 'Processing', color: '#6c757d' },
        { key: 'shipped', label: 'Shipped', color: '#17a2b8' },
        { key: 'delivered', label: 'Delivered', color: '#28a745' },
        { key: 'cancelled', label: 'Cancelled', color: '#dc3545' },
        { key: 'returned', label: 'Returned', color: '#6c757d' }
    ];

    const total = statuses.reduce((sum, s) => sum + (parseInt(counts[s.key]) || 0), 0);

    container.innerHTML = statuses.map(s => {
        const count = parseInt(counts[s.key]) || 0;
        const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
        return `
            <div class="d-flex align-items-center mb-2">
                <div style="width: 12px; height: 12px; border-radius: 3px; background:${s.color}; margin-right: 10px; flex-shrink:0;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold">${s.label}</small>
                        <small class="text-muted">${count} (${pct}%)</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: ${pct}%; background: ${s.color};"></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}
