/**
 * Admin Shop Payments Management
 */

document.addEventListener('DOMContentLoaded', function () {
    window.addEventListener('adminReady', () => { loadPayments(); loadShopsFilter(); }, { once: true });
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
    if (!confirm(`Generate daily payment records for today's delivered orders for all shops?`)) return;

    try {
        const result = await adminAPI('../api/admin/generate-payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ period })
        });

        if (result.success) {
            adminToast(result.message || 'Payments generated', 'success');
            loadPayments();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to generate payments', 'error');
    }
}

function openRangeModal() {
    // Max selectable date = yesterday
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const maxDate = yesterday.toISOString().split('T')[0];

    const fromEl = document.getElementById('rangeFrom');
    const toEl   = document.getElementById('rangeTo');

    fromEl.max = maxDate;
    toEl.max   = maxDate;

    // Default: last 7 days (from 7 days ago to yesterday)
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    fromEl.value = sevenDaysAgo.toISOString().split('T')[0];
    toEl.value   = maxDate;

    document.getElementById('rangeWarning').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('rangePaymentModal')).show();
}

async function submitRangePayments() {
    const fromDate = document.getElementById('rangeFrom').value;
    const toDate   = document.getElementById('rangeTo').value;
    const warning  = document.getElementById('rangeWarning');

    warning.classList.add('d-none');

    // Validate
    if (!fromDate || !toDate) {
        warning.textContent = 'Please select both From and To dates.';
        warning.classList.remove('d-none');
        return;
    }

    const from = new Date(fromDate);
    const to   = new Date(toDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (to >= today) {
        warning.textContent = 'To date must be before today. Today\'s orders may still come in.';
        warning.classList.remove('d-none');
        return;
    }
    if (from > to) {
        warning.textContent = 'From date cannot be after To date.';
        warning.classList.remove('d-none');
        return;
    }

    const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24)) + 1;
    if (diffDays > 90) {
        warning.textContent = 'Maximum range is 90 days. Please split into smaller periods.';
        warning.classList.remove('d-none');
        return;
    }

    bootstrap.Modal.getInstance(document.getElementById('rangePaymentModal')).hide();

    try {
        const result = await adminAPI('../api/admin/generate-payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ period: 'range', date_from: fromDate, date_to: toDate })
        });

        if (result.success) {
            adminToast(result.message || 'Range payments generated', 'success');
            loadPayments();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to generate range payments', 'error');
    }
}
