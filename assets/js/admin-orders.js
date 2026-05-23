/**
 * Admin Orders Management
 */

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('searchOrder').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadOrders();
    });
    window.addEventListener('adminReady', loadOrders, { once: true });
});

async function loadOrders() {
    const params = new URLSearchParams();
    const search = document.getElementById('searchOrder').value;
    const status = document.getElementById('filterOrderStatus').value;
    const payment = document.getElementById('filterPayment').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;

    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (payment) params.append('payment', payment);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    try {
        const result = await adminAPI('../api/admin/orders.php?' + params);
        if (result.success) {
            renderOrders(result.data);
        }
    } catch (e) {
        console.error('Load orders error:', e);
    }
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersTable');
    document.getElementById('orderCount').textContent = orders.length;

    if (!orders.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="admin-empty-state"><i class="fas fa-shopping-bag"></i><p>No orders found</p></td></tr>';
        return;
    }

    const rows = [];
    orders.forEach(o => {
        const items = o.items || [];
        const hasItems = items.length > 0;

        rows.push(`
        <tr style="cursor:${hasItems ? 'pointer' : 'default'}" onclick="${hasItems ? `toggleOrderItems('items-${o.order_id}')` : ''}">
            <td>
                <strong>#${o.order_number}</strong>
                ${hasItems ? `<i class="fas fa-chevron-down ms-1 text-muted" style="font-size:10px;" id="icon-${o.order_id}"></i>` : ''}
            </td>
            <td>${formatDate(o.order_date)}</td>
            <td>
                <div>${o.customer_name || '-'}</div>
                <small class="text-muted hide-mobile">${o.customer_phone || o.shipping_phone || ''}</small>
                ${(o.shipping_address || o.shipping_city) ? `<small class="text-muted d-block hide-mobile">${[o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_pincode].filter(Boolean).join(', ')}</small>` : ''}
            </td>
            <td class="hide-mobile"><small>${o.shop_names || '-'}</small></td>
            <td><strong>${formatINR(o.total_amount)}</strong></td>
            <td>${statusBadge(o.payment_status)}</td>
            <td>${statusBadge(o.order_status)}</td>
            <td onclick="event.stopPropagation()">
                <button class="btn btn-sm btn-outline-dark btn-action" onclick="openStatusModal(${o.order_id}, '${o.order_number}', '${o.order_status}')" title="Update Status">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>`);

        if (hasItems) {
            const itemsHTML = items.map(item => {
                const colorBadge = item.selected_color
                    ? `<span class="badge bg-light text-dark border me-1" style="font-size:10px;"><i class="fas fa-palette me-1"></i>${item.selected_color}</span>`
                    : '';
                const sizeBadge = item.selected_size
                    ? `<span class="badge bg-light text-dark border me-1" style="font-size:10px;"><i class="fas fa-ruler-combined me-1"></i>${item.selected_size}</span>`
                    : '';
                return `<div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <div class="flex-grow-1">
                        <span style="font-size:13px;font-weight:500;">${item.product_name}</span>
                        <div class="mt-1">${colorBadge}${sizeBadge}</div>
                    </div>
                    <div class="text-muted" style="font-size:12px;">x${item.quantity}</div>
                    <div style="font-size:12px;font-weight:600;">${formatINR(item.subtotal)}</div>
                </div>`;
            }).join('');

            rows.push(`
        <tr id="items-${o.order_id}" style="display:none;background:#f9f9f9;">
            <td colspan="8" class="px-4 py-2">
                ${itemsHTML}
            </td>
        </tr>`);
        }
    });

    tbody.innerHTML = rows.join('');
}

function toggleOrderItems(id) {
    const row = document.getElementById(id);
    if (!row) return;
    const orderId = id.replace('items-', '');
    const icon = document.getElementById('icon-' + orderId);
    const isHidden = row.style.display === 'none';
    row.style.display = isHidden ? 'table-row' : 'none';
    if (icon) icon.style.transform = isHidden ? 'rotate(180deg)' : '';
}

function openStatusModal(orderId, orderNumber, currentStatus) {
    document.getElementById('updateOrderId').value = orderId;
    document.getElementById('updateOrderNumber').value = '#' + orderNumber;
    document.getElementById('updateCurrentStatus').innerHTML = statusBadge(currentStatus);
    document.getElementById('newOrderStatus').value = currentStatus;
    document.getElementById('trackingNumber').value = '';
    document.getElementById('statusNote').value = '';
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

async function submitStatusUpdate() {
    const orderId = document.getElementById('updateOrderId').value;
    const newStatus = document.getElementById('newOrderStatus').value;
    const trackingNumber = document.getElementById('trackingNumber').value.trim();
    const note = document.getElementById('statusNote').value.trim();

    try {
        const result = await adminAPI('../api/admin/update-order-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                status: newStatus,
                tracking_number: trackingNumber,
                note: note
            })
        });

        if (result.success) {
            adminToast('Order status updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
            loadOrders();
        } else {
            adminToast(result.message || 'Failed to update', 'error');
        }
    } catch (e) {
        adminToast('Failed to update order status', 'error');
    }
}
