/**
 * Shop Dashboard - JavaScript
 * Handles shop dashboard functionality
 */

// Track known pending order IDs for new-order notifications
let _dashKnownOrderIds = null;

// ── Mobile-safe Audio Context ─────────────────────────────────────────
let _audioCtx = null;
(function _setupAudioUnlock() {
    const unlock = () => {
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (_audioCtx.state === 'suspended') _audioCtx.resume();
            const buf = _audioCtx.createBuffer(1, 1, 22050);
            const src = _audioCtx.createBufferSource();
            src.buffer = buf;
            src.connect(_audioCtx.destination);
            src.start(0);
        } catch (e) {}
    };
    document.addEventListener('click',      unlock);
    document.addEventListener('touchstart', unlock, { passive: true });
})();

document.addEventListener('DOMContentLoaded', function() {
    checkShopAuthentication();
    loadShopDashboard();
    startDashOrderNotifications();
});

/**
 * Check if user is authenticated as shop owner
 */
async function checkShopAuthentication() {
    try {
        const response = await fetch('../api/check-auth.php');
        const result = await response.json();

        if (!result.success || !result.data.logged_in) {
            // Not logged in
            window.location.href = '../pages/login.html';
            return;
        }

        if (result.data.user.user_type !== 'shop') {
            // Not a shop owner
            alert('Access denied. This page is for shop owners only.');
            window.location.href = '../index.html';
            return;
        }

        // Set owner name
        if (result.data.user.full_name) {
            document.getElementById('ownerName').textContent = result.data.user.full_name;
        }
    } catch (error) {
        console.error('Authentication check error:', error);
        window.location.href = '../pages/login.html';
    }
}

/**
 * Load shop dashboard data
 */
async function loadShopDashboard() {
    try {
        const response = await fetch('../api/shop/dashboard.php');
        const result = await response.json();

        if (result.success && result.data) {
            updateShopInfo(result.data.shop);
            updateStatistics(result.data.stats);
            updateRecentOrders(result.data.recent_orders);
            renderLowStockAlerts(result.data.low_stock || []);
        } else {
            showError('Failed to load dashboard data');
        }
    } catch (error) {
        console.error('Dashboard load error:', error);
        showError('Failed to load dashboard data');
    }
}

/**
 * Update shop information
 */
function updateShopInfo(shop) {
    if (!shop) return;

    // Shop name
    if (shop.shop_name) {
        document.getElementById('shopName').textContent = shop.shop_name;
    }

    // Shop status
    const statusBadge = document.getElementById('shopStatusBadge');
    const statusText = document.getElementById('shopStatusText');
    const statusToggleIcon = document.getElementById('statusToggleIcon');
    const statusToggleText = document.getElementById('statusToggleText');

    if (shop.shop_status === 'open') {
        statusBadge.className = 'shop-status-badge open';
        statusText.textContent = 'Open';
        statusToggleIcon.className = 'fas fa-toggle-off text-danger';
        statusToggleText.textContent = 'Close Shop';
    } else {
        statusBadge.className = 'shop-status-badge closed';
        statusText.textContent = 'Closed';
        statusToggleIcon.className = 'fas fa-toggle-on text-success';
        statusToggleText.textContent = 'Open Shop';
    }
}

/**
 * Update statistics
 */
function updateStatistics(stats) {
    if (!stats) {
        // Show default values
        document.getElementById('totalProducts').textContent = '0';
        document.getElementById('pendingOrders').textContent = '0';
        document.getElementById('totalSales').textContent = '₹0';
        document.getElementById('shopRating').textContent = '0.0';
        return;
    }

    // Total Products
    document.getElementById('totalProducts').textContent = stats.total_products || '0';

    // Pending Orders
    document.getElementById('pendingOrders').textContent = stats.pending_orders || '0';

    // Total Sales
    const sales = parseFloat(stats.total_sales) || 0;
    document.getElementById('totalSales').textContent = '₹' + sales.toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });

    // Shop Rating
    const rating = parseFloat(stats.rating_average) || 0;
    document.getElementById('shopRating').textContent = rating.toFixed(1);
}

/**
 * Update recent orders table
 */
function updateRecentOrders(orders) {
    const tableBody = document.getElementById('recentOrdersTable');

    if (!orders || orders.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-grey py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No recent orders</p>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    orders.forEach(order => {
        const statusClass = getStatusClass(order.item_status);
        html += `
            <tr>
                <td><strong>#${order.order_number}</strong></td>
                <td>${order.customer_name || 'N/A'}</td>
                <td>${order.items_count} item(s)</td>
                <td>₹${parseFloat(order.item_total).toLocaleString('en-IN')}</td>
                <td><span class="badge ${statusClass}">${formatStatus(order.item_status)}</span></td>
                <td>
                    ${order.item_status === 'pending' ?
                        `<button class="btn btn-sm btn-success" onclick="confirmOrder(${order.order_item_id})">
                            <i class="fas fa-check"></i> Accept
                        </button>` :
                        `<a href="orders.html?order=${order.order_id}" class="btn btn-sm btn-outline-dark">View</a>`
                    }
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

/**
 * Get status badge class
 */
function getStatusClass(status) {
    const classes = {
        'pending': 'bg-warning text-dark badge-status',
        'confirmed': 'bg-info text-white badge-status',
        'processing': 'bg-primary text-white badge-status',
        'shipped': 'bg-success text-white badge-status',
        'delivered': 'bg-success text-white badge-status',
        'cancelled': 'bg-danger text-white badge-status'
    };
    return classes[status] || 'bg-secondary text-white badge-status';
}

/**
 * Format status text
 */
function formatStatus(status) {
    if (!status) return 'N/A';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

/**
 * Toggle shop status (open/closed)
 */
async function toggleShopStatus() {
    const statusText = document.getElementById('shopStatusText').textContent.toLowerCase();
    const newStatus = statusText === 'open' ? 'closed' : 'open';

    const confirmMsg = `Are you sure you want to ${newStatus === 'closed' ? 'close' : 'open'} your shop?`;
    if (!confirm(confirmMsg)) {
        return;
    }

    try {
        const response = await fetch('../api/shop/update-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: newStatus
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast(`Shop ${newStatus === 'open' ? 'opened' : 'closed'} successfully`, 'success');
            // Reload dashboard to update status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to update shop status', 'error');
        }
    } catch (error) {
        console.error('Status toggle error:', error);
        showToast('Failed to update shop status', 'error');
    }
}

/**
 * Confirm order
 */
async function confirmOrder(orderItemId) {
    if (!confirm('Confirm this order?')) {
        return;
    }

    try {
        const response = await fetch('../api/shop/confirm-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_item_id: orderItemId
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Order confirmed successfully', 'success');
            // Reload orders
            loadShopDashboard();
        } else {
            showToast(result.message || 'Failed to confirm order', 'error');
        }
    } catch (error) {
        console.error('Order confirmation error:', error);
        showToast('Failed to confirm order', 'error');
    }
}

/**
 * Render low stock / out-of-stock alerts
 */
function renderLowStockAlerts(items) {
    const section = document.getElementById('lowStockSection');
    const list    = document.getElementById('lowStockList');
    const count   = document.getElementById('lowStockCount');
    if (!section || !list) return;

    if (!items || items.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = '';
    count.textContent = items.length;

    list.innerHTML = items.map(p => {
        const stock     = parseInt(p.stock_quantity);
        const threshold = parseInt(p.low_stock_threshold);
        const isOos     = stock <= 0;
        const badge     = isOos
            ? '<span class="low-stock-badge-oos">Out of Stock</span>'
            : '<span class="low-stock-badge-low">Low Stock</span>';
        const imgSrc = p.primary_image
            ? '../' + p.primary_image.replace(/^\//, '')
            : 'https://placehold.co/40x40/eee/999?text=P';

        return `
            <div class="low-stock-item">
                <img src="${imgSrc}" class="low-stock-thumb"
                     onerror="this.src='https://placehold.co/40x40/eee/999?text=P'">
                <div class="flex-grow-1">
                    <div class="fw-semibold small">${p.product_name}</div>
                    <div class="text-muted" style="font-size:12px;">
                        Stock: <strong>${stock}</strong> / Threshold: ${threshold}
                    </div>
                </div>
                ${badge}
                <a href="add-product.html?id=${p.product_id}" class="btn btn-xs btn-outline-secondary btn-sm ms-2" style="font-size:11px;">
                    Edit
                </a>
            </div>`;
    }).join('');
}

/**
 * Show error message
 */
function showError(message) {
    const tableBody = document.getElementById('recentOrdersTable');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <p>${message}</p>
                </td>
            </tr>
        `;
    }
}

// showToast() is provided by main.js (loaded before this file).
// Do NOT redefine it here — doing so causes infinite recursion.

/* ===================================
   NEW ORDER NOTIFICATIONS (Dashboard)
   Polls every 30s; plays sound + toast
   when a new pending order arrives.
   =================================== */
function startDashOrderNotifications() {
    pollDashNewOrders();
    setInterval(pollDashNewOrders, 30000);
}

async function pollDashNewOrders() {
    try {
        const res  = await fetch('../api/shop/orders.php?status=pending');
        if (!res.ok) { console.warn('Shop dash poll HTTP error:', res.status); return; }
        const data = await res.json();
        if (!data.success) { console.warn('Shop dash poll API error:', data.message); return; }

        const ids = (data.data || []).map(o => Number(o.order_item_id));

        if (_dashKnownOrderIds === null) {
            _dashKnownOrderIds = new Set(ids);
            return;
        }

        const newOnes = ids.filter(id => !_dashKnownOrderIds.has(id));
        if (newOnes.length) {
            newOnes.forEach(id => _dashKnownOrderIds.add(id));
            playDashOrderSound();
            _dashToast(`<i class="fas fa-bell me-2"></i>${newOnes.length} new order${newOnes.length > 1 ? 's' : ''} received!`);
            loadShopDashboard();
        }
    } catch (e) {
        console.warn('Shop dash poll error:', e);
    }
}

function _dashToast(message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-white bg-dark border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>`;
    container.appendChild(el);
    new bootstrap.Toast(el, { autohide: true, delay: 6000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function playDashOrderSound() {
    const _play = (ctx) => {
        [[880, 0], [1100, 0.22], [880, 0.44]].forEach(([freq, delay]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            const t = ctx.currentTime + delay;
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(0.4, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
            osc.start(t);
            osc.stop(t + 0.35);
        });
    };
    try {
        if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (_audioCtx.state === 'suspended') {
            _audioCtx.resume().then(() => _play(_audioCtx));
        } else {
            _play(_audioCtx);
        }
    } catch (e) {
        console.warn('Order sound playback failed:', e);
    }
}
