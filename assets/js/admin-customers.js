/**
 * Admin Customers Management
 */

document.addEventListener('DOMContentLoaded', function () {
    loadCustomers();
    document.getElementById('searchCustomer').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadCustomers();
    });
});

async function loadCustomers() {
    const search = document.getElementById('searchCustomer').value;
    const active = document.getElementById('filterActive').value;
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (active) params.append('status', active);

    try {
        const result = await adminAPI('../api/admin/customers.php?' + params);
        if (result.success) {
            renderCustomers(result.data.customers);
            renderStats(result.data.stats);
        }
    } catch (e) {
        console.error('Load customers error:', e);
    }
}

function renderStats(s) {
    if (!s) return;
    document.getElementById('totalCustomers').textContent = s.total || 0;
    document.getElementById('activeCustomers').textContent = s.active || 0;
    document.getElementById('newThisMonth').textContent = s.new_this_month || 0;
}

function renderCustomers(customers) {
    const tbody = document.getElementById('customersTable');
    if (!customers || !customers.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><i class="fas fa-users"></i><p>No customers found</p></td></tr>';
        return;
    }

    tbody.innerHTML = customers.map(c => `
        <tr>
            <td><strong>${c.full_name || '-'}</strong></td>
            <td>${c.email}</td>
            <td class="hide-mobile">${c.phone || '-'}</td>
            <td class="hide-mobile">${c.order_count || 0}</td>
            <td class="hide-mobile">${formatINR(c.total_spent)}</td>
            <td>${formatDate(c.created_at)}</td>
            <td>${statusBadge(c.is_active == 1 ? 'active' : 'inactive')}</td>
        </tr>
    `).join('');
}
