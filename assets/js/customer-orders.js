/**
 * Customer Orders Page
 */

document.addEventListener('DOMContentLoaded', function () {
    // Redirect to login if not logged in (local check first)
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }
    loadOrders();
});

async function loadOrders() {
    const container = document.getElementById('ordersContainer');

    try {
        const res  = await fetch('../api/customer/my-orders.php');
        const data = await res.json();

        if (!data.success) {
            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }
            container.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            return;
        }

        const orders = data.data || [];
        const countEl = document.getElementById('orderCount');
        if (countEl) countEl.textContent = orders.length + ' order' + (orders.length !== 1 ? 's' : '');

        if (!orders.length) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-grey mb-3"></i>
                    <h5>No orders yet</h5>
                    <p class="text-grey">Start shopping to see your orders here.</p>
                    <a href="shops.html" class="btn btn-primary mt-2">Browse Shops</a>
                </div>`;
            return;
        }

        container.innerHTML = orders.map(o => buildOrderCard(o)).join('');

    } catch (err) {
        console.error('Load orders error:', err);
        container.innerHTML = '<div class="alert alert-danger">Failed to load orders.</div>';
    }
}

function buildTimeline(status) {
    const STEPS  = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const LABELS = ['Order Placed', 'Confirmed', 'Processing', 'Shipped', 'Delivered'];

    // Cancelled / refunded — show a simple badge, no timeline
    if (status === 'cancelled' || status === 'refunded') {
        const color = status === 'cancelled' ? 'danger' : 'secondary';
        return `<div class="mt-2 mb-1">
            <span class="badge bg-${color}">
                <i class="fas fa-times-circle me-1"></i>
                ${status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        </div>`;
    }

    const stepIdx = Math.max(0, STEPS.indexOf(status));
    const bikePct = 10 + stepIdx * 20;
    const fillPct = stepIdx * 20;

    const stepsHtml = STEPS.map((s, i) => {
        const isDone   = i < stepIdx;
        const isActive = i === stepIdx;
        const cls      = isDone ? 'tl-done' : (isActive ? 'tl-active' : '');
        const circle   = isDone ? '<i class="fas fa-check"></i>' : '';
        return `
            <div class="tl-step ${cls}">
                <div class="tl-icon-slot"></div>
                <div class="tl-circle">${circle}</div>
                <div class="tl-label">${LABELS[i]}</div>
            </div>`;
    }).join('');

    return `
        <div class="order-timeline">
            <div class="tl-steps" style="--bike-to:${bikePct}%; --fill-to:${fillPct}%">
                <div class="tl-fill-bar"></div>
                <div class="tl-bike-rider">
                    <span class="tl-bike-float"><i class="fas fa-motorcycle"></i></span>
                </div>
                ${stepsHtml}
            </div>
        </div>`;
}

function buildOrderCard(order) {
    const date = new Date(order.order_date).toLocaleDateString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric'
    });

    const rateBtn = order.order_status === 'delivered'
        ? `<a href="rate-order.php?order_id=${order.order_id}"
               class="btn btn-sm btn-outline-warning">
               <i class="fas fa-star me-1"></i> Ratings
           </a>`
        : '';

    return `
        <div class="bg-light-grey rounded p-4 mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="mb-1 fw-bold">${order.order_number}</h6>
                    <p class="text-grey small mb-0">Placed on ${date}</p>
                    <p class="text-grey small mb-0">${order.item_count} item(s) &bull; ${order.shipping_city}</p>
                </div>
                <div class="text-end">
                    <strong class="fs-6">₹${parseFloat(order.total_amount).toLocaleString()}</strong>
                </div>
            </div>
            ${buildTimeline(order.order_status)}
            <div class="d-flex gap-2 mt-3 align-items-center flex-wrap">
                <button class="btn btn-sm btn-outline-dark"
                    onclick="viewOrderDetails(${order.order_id}, '${order.order_number}')">
                    <i class="fas fa-eye me-1"></i> View Details
                </button>
                <span class="badge bg-light text-dark border d-flex align-items-center">
                    <i class="fas fa-${order.payment_method === 'cod' ? 'money-bill' : 'credit-card'} me-1"></i>
                    ${order.payment_method.toUpperCase()}
                </span>
                ${rateBtn}
            </div>
        </div>`;
}

async function viewOrderDetails(orderId, orderNum) {
    const modal   = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const bodyEl  = document.getElementById('orderDetailBody');
    const titleEl = document.getElementById('modalOrderNum');

    titleEl.textContent = orderNum;
    bodyEl.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    try {
        const res  = await fetch(`../api/customer/my-orders.php?order_id=${orderId}`);
        const data = await res.json();

        if (!data.success || !data.data.length) {
            bodyEl.innerHTML = '<p class="text-grey text-center">No items found.</p>';
            return;
        }

        const rows = data.data.map(item => {
            const img = item.product_image
                ? '../' + item.product_image.replace(/^\//, '')
                : 'https://placehold.co/60x60/1a1a1a/ffffff?text=Item';
            const statusColors = { pending: 'warning', confirmed: 'info', processing: 'primary', shipped: 'dark', delivered: 'success', cancelled: 'danger' };
            const sc = statusColors[item.item_status] || 'secondary';

            // Show existing rating for delivered items
            let ratingDisplay = '';
            if (item.item_status === 'delivered' && parseInt(item.has_reviewed)) {
                let stars = '';
                const r = parseInt(item.review_rating);
                for (let i = 1; i <= 5; i++) {
                    stars += `<i class="${i <= r ? 'fas' : 'far'} fa-star text-warning" style="font-size:11px;"></i>`;
                }
                ratingDisplay = `<div class="mt-1 small text-grey">${stars} Your rating</div>`;
            }

            return `
                <div class="d-flex align-items-start gap-3 border-bottom pb-3 mb-3">
                    <img src="${img}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"
                         onerror="this.src='https://placehold.co/60x60/1a1a1a/ffffff?text=Item'">
                    <div class="flex-grow-1">
                        <p class="fw-bold mb-0">${item.product_name}</p>
                        <p class="text-grey small mb-0"><i class="fas fa-store"></i> ${item.shop_name}</p>
                        <p class="text-grey small mb-0">Qty: ${item.quantity} &times; ₹${parseFloat(item.price).toLocaleString()}</p>
                        ${ratingDisplay}
                    </div>
                    <div class="text-end">
                        <strong>₹${(item.quantity * parseFloat(item.price)).toLocaleString()}</strong><br>
                        <span class="badge bg-${sc} small">${item.item_status}</span>
                    </div>
                </div>`;
        }).join('');

        bodyEl.innerHTML = `<div>${rows}</div>`;

    } catch (err) {
        bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load order details.</div>';
    }
}
