/**
 * Admin Shop Payments Management
 */

document.addEventListener('DOMContentLoaded', function () {
    loadPayments();
    loadShopsFilter();
});

async function loadShopsFilter() {
    try {
        const result = await adminAPI('../api/admin/shops.php');
        if (result.success && result.data) {
            const select = document.getElementById('filterShop');
            result.data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.shop_id;
                opt.textContent = s.shop_name;
                select.appendChild(opt);
            });
        }
    } catch (e) { /* ignore */ }
}

async function loadPayments() {
    const status = document.getElementById('filterPaymentStatus').value;
    const shop = document.getElementById('filterShop').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (shop) params.append('shop_id', shop);

    try {
        const result = await adminAPI('../api/admin/payments.php?' + params);
        if (result.success && result.data) {
            renderPayments(result.data.payments);
            renderPaymentStats(result.data.stats);
        }
    } catch (e) {
        console.error('Load payments error:', e);
    }
}

function renderPaymentStats(s) {
    if (!s) return;
    document.getElementById('totalUnpaid').textContent = formatINR(s.total_unpaid);
    document.getElementById('totalPaid').textContent = formatINR(s.total_paid);
    document.getElementById('totalCommission').textContent = formatINR(s.total_commission);
    document.getElementById('pendingShops').textContent = s.pending_shops || 0;
}

function renderPayments(payments) {
    const tbody = document.getElementById('paymentsTable');
    if (!payments || !payments.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><i class="fas fa-rupee-sign"></i><p>No payment records found</p></td></tr>';
        return;
    }

    tbody.innerHTML = payments.map(p => `
        <tr>
            <td><strong>${p.shop_name}</strong></td>
            <td>
                <small>${formatDate(p.period_start)}</small><br>
                <small class="text-muted">to ${formatDate(p.period_end)}</small>
            </td>
            <td>${formatINR(p.total_sales)}</td>
            <td class="hide-mobile text-muted">${formatINR(p.commission_amount)} (${p.commission_rate}%)</td>
            <td><strong>${formatINR(p.payable_amount)}</strong></td>
            <td>${statusBadge(p.payment_status)}</td>
            <td>
                ${p.payment_status === 'unpaid' ?
                    `<button class="btn btn-sm btn-success btn-action" onclick="openMarkPaidModal(${p.payment_id}, '${p.shop_name}', ${p.payable_amount})">
                        <i class="fas fa-check"></i> Pay
                    </button>` :
                    `<small class="text-muted">${formatDate(p.paid_at)}</small>`
                }
            </td>
        </tr>
    `).join('');
}

function openMarkPaidModal(paymentId, shopName, amount) {
    document.getElementById('paymentId').value = paymentId;
    document.getElementById('payShopName').value = shopName;
    document.getElementById('payAmount').value = formatINR(amount);
    document.getElementById('payReference').value = '';
    document.getElementById('payNotes').value = '';
    new bootstrap.Modal(document.getElementById('markPaidModal')).show();
}

async function submitMarkPaid() {
    const paymentId = document.getElementById('paymentId').value;
    const method = document.getElementById('payMethod').value;
    const reference = document.getElementById('payReference').value.trim();
    const notes = document.getElementById('payNotes').value.trim();

    try {
        const result = await adminAPI('../api/admin/mark-paid.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                payment_id: paymentId,
                payment_method: method,
                transaction_reference: reference,
                notes: notes
            })
        });

        if (result.success) {
            adminToast('Payment marked as paid', 'success');
            bootstrap.Modal.getInstance(document.getElementById('markPaidModal')).hide();
            loadPayments();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to mark payment', 'error');
    }
}

async function generatePayments(period) {
    const label = period === 'daily' ? "today's" : "this week's";
    if (!confirm(`Generate ${period} payment records for ${label} delivered orders for all shops?`)) return;

    try {
        const result = await adminAPI('../api/admin/generate-payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ period })
        });

        if (result.success) {
            adminToast(result.message || `${period} payments generated`, 'success');
            loadPayments();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to generate payments', 'error');
    }
}

// Backward-compatibility alias
function generateWeeklyPayments() { generatePayments('weekly'); }
