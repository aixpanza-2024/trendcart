/**
 * Admin Coupons Management
 */

let allCoupons = [];
let couponModal = null;

document.addEventListener('DOMContentLoaded', function () {
    couponModal = new bootstrap.Modal(document.getElementById('couponModal'));
    window.addEventListener('adminReady', () => { loadCoupons(); }, { once: true });
});

async function loadCoupons() {
    try {
        const result = await adminAPI('../api/admin/coupons.php');
        if (result.success) {
            allCoupons = result.data;
            renderCoupons(result.data);
        }
    } catch (e) {
        console.error('Load coupons error:', e);
    }
}

function renderCoupons(coupons) {
    const tbody = document.getElementById('couponsTable');
    if (!coupons || !coupons.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><i class="fas fa-ticket-alt"></i><p>No coupons yet</p></td></tr>';
        return;
    }

    const now = new Date();

    tbody.innerHTML = coupons.map(c => {
        const discountLabel = c.discount_type === 'percentage'
            ? `${parseFloat(c.discount_value)}%${c.max_discount_amount ? ' (up to ' + formatINR(c.max_discount_amount) + ')' : ''}`
            : formatINR(c.discount_value);

        const usageLabel = `${c.times_used} / ${c.usage_limit_total ?? '∞'}<br><small class="text-muted">${c.usage_limit_per_user}/customer</small>`;

        const expired = c.valid_until && new Date(c.valid_until) < now;
        const notYet  = c.valid_from && new Date(c.valid_from) > now;
        let validLabel = 'Always';
        if (c.valid_from || c.valid_until) {
            validLabel = `${c.valid_from ? formatDate(c.valid_from) : 'now'} → ${c.valid_until ? formatDate(c.valid_until) : 'forever'}`;
            if (expired) validLabel += ' <span class="text-danger">(expired)</span>';
            else if (notYet) validLabel += ' <span class="text-warning">(upcoming)</span>';
        }

        return `
            <tr>
                <td><code>${c.code}</code>${c.description ? `<br><small class="text-muted">${escapeHtmlAdmin(c.description)}</small>` : ''}</td>
                <td>${discountLabel}</td>
                <td class="hide-mobile">${formatINR(c.min_order_value)}</td>
                <td class="hide-mobile">${usageLabel}</td>
                <td class="hide-mobile small">${validLabel}</td>
                <td>${statusBadge(c.is_active == 1 ? 'active' : 'inactive')}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary btn-action me-1" onclick="editCoupon(${c.coupon_id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-${c.is_active == 1 ? 'warning' : 'success'} btn-action" onclick="toggleCoupon(${c.coupon_id}, ${c.is_active})" title="${c.is_active == 1 ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${c.is_active == 1 ? 'eye-slash' : 'eye'}"></i>
                    </button>
                </td>
            </tr>`;
    }).join('');
}

function escapeHtmlAdmin(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function toLocalInputValue(mysqlDateTime) {
    if (!mysqlDateTime) return '';
    return mysqlDateTime.replace(' ', 'T').slice(0, 16);
}

function openCouponModal() {
    document.getElementById('couponModalTitle').innerHTML = '<i class="fas fa-ticket-alt me-2"></i>Add Coupon';
    document.getElementById('editCouponId').value = '';
    document.getElementById('couponCode').value = '';
    document.getElementById('couponCode').disabled = false;
    document.getElementById('couponCodeHint').textContent = 'Letters, numbers, - and _ only. Cannot be changed after creation.';
    document.getElementById('couponDescription').value = '';
    document.getElementById('couponDiscountType').value = 'percentage';
    document.getElementById('couponDiscountValue').value = '';
    document.getElementById('couponMaxDiscount').value = '';
    document.getElementById('couponMinOrder').value = '0';
    document.getElementById('couponUsageTotal').value = '';
    document.getElementById('couponUsagePerUser').value = '1';
    document.getElementById('couponValidFrom').value = '';
    document.getElementById('couponValidUntil').value = '';
    document.getElementById('couponIsActive').checked = true;
    couponModal.show();
}

function editCoupon(couponId) {
    const c = allCoupons.find(x => x.coupon_id == couponId);
    if (!c) return;

    document.getElementById('couponModalTitle').innerHTML = '<i class="fas fa-ticket-alt me-2"></i>Edit Coupon';
    document.getElementById('editCouponId').value = c.coupon_id;
    document.getElementById('couponCode').value = c.code;
    document.getElementById('couponCode').disabled = true;
    document.getElementById('couponCodeHint').textContent = 'Code cannot be changed after creation.';
    document.getElementById('couponDescription').value = c.description || '';
    document.getElementById('couponDiscountType').value = c.discount_type;
    document.getElementById('couponDiscountValue').value = c.discount_value;
    document.getElementById('couponMaxDiscount').value = c.max_discount_amount ?? '';
    document.getElementById('couponMinOrder').value = c.min_order_value;
    document.getElementById('couponUsageTotal').value = c.usage_limit_total ?? '';
    document.getElementById('couponUsagePerUser').value = c.usage_limit_per_user;
    document.getElementById('couponValidFrom').value = toLocalInputValue(c.valid_from);
    document.getElementById('couponValidUntil').value = toLocalInputValue(c.valid_until);
    document.getElementById('couponIsActive').checked = c.is_active == 1;
    couponModal.show();
}

async function saveCoupon() {
    const couponId = document.getElementById('editCouponId').value;
    const payload = {
        code:                  document.getElementById('couponCode').value.trim().toUpperCase(),
        description:           document.getElementById('couponDescription').value.trim(),
        discount_type:         document.getElementById('couponDiscountType').value,
        discount_value:        parseFloat(document.getElementById('couponDiscountValue').value),
        max_discount_amount:   document.getElementById('couponMaxDiscount').value || null,
        min_order_value:       parseFloat(document.getElementById('couponMinOrder').value) || 0,
        usage_limit_total:     document.getElementById('couponUsageTotal').value || null,
        usage_limit_per_user:  document.getElementById('couponUsagePerUser').value || 1,
        valid_from:            document.getElementById('couponValidFrom').value ? document.getElementById('couponValidFrom').value.replace('T', ' ') + ':00' : null,
        valid_until:           document.getElementById('couponValidUntil').value ? document.getElementById('couponValidUntil').value.replace('T', ' ') + ':00' : null,
        is_active:             document.getElementById('couponIsActive').checked,
    };

    if (!payload.code || !payload.discount_type || !payload.discount_value) {
        adminToast('Code and discount value are required', 'error');
        return;
    }

    try {
        let result;
        if (couponId) {
            payload.coupon_id = parseInt(couponId, 10);
            result = await adminAPI('../api/admin/update-coupon.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
            });
        } else {
            result = await adminAPI('../api/admin/create-coupon.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
            });
        }

        if (result.success) {
            adminToast(result.message || 'Saved', 'success');
            couponModal.hide();
            loadCoupons();
        } else {
            adminToast(result.message || 'Save failed', 'error');
        }
    } catch (e) {
        console.error('Save coupon error:', e);
        adminToast('Save failed', 'error');
    }
}

async function toggleCoupon(couponId, currentlyActive) {
    try {
        const result = await adminAPI('../api/admin/toggle-coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coupon_id: couponId, is_active: currentlyActive == 1 ? 0 : 1 }),
        });
        if (result.success) {
            adminToast(result.message, 'success');
            loadCoupons();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        console.error('Toggle coupon error:', e);
        adminToast('Failed to update coupon', 'error');
    }
}
