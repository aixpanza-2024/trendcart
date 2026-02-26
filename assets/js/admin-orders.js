/**
 * Admin Orders Management
 */

document.addEventListener('DOMContentLoaded', function () {
    loadOrders();
    document.getElementById('searchOrder').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadOrders();
    });
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

    tbody.innerHTML = orders.map(o => `
        <tr>
            <td><strong>#${o.order_number}</strong></td>
            <td>${formatDate(o.order_date)}</td>
            <td>
                <div>${o.customer_name || '-'}</div>
                <small class="text-muted hide-mobile">${o.customer_phone || ''}</small>
            </td>
            <td class="hide-mobile"><small>${o.shop_names || '-'}</small></td>
            <td><strong>${formatINR(o.total_amount)}</strong></td>
            <td>${statusBadge(o.payment_status)}</td>
            <td>${statusBadge(o.order_status)}</td>
            <td>
                <button class="btn btn-sm btn-outline-dark btn-action" onclick="openStatusModal(${o.order_id}, '${o.order_number}', '${o.order_status}')" title="Update Status">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>
    `).join('');
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
