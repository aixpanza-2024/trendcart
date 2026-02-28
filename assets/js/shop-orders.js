/**
 * Shop Orders Management
 */

// Track known pending order IDs for new-order detection
// null = first poll (populate silently, no notification)
let _knownOrderIds = null;

// ── Mobile-safe Audio Context ─────────────────────────────────────────
// Browsers (iOS in particular) suspend AudioContext until a user gesture.
// We create/unlock it on the first interaction so polling callbacks can
// play sound even without a simultaneous user gesture.
let _audioCtx = null;
(function _setupAudioUnlock() {
    const unlock = () => {
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (_audioCtx.state === 'suspended') _audioCtx.resume();
            // iOS trick: play a silent 1-frame buffer to fully unlock
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

document.addEventListener('DOMContentLoaded', function () {
    checkShopAuth();
    loadOrders();
    startOrderNotifications();

    document.getElementById('searchOrder').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadOrders();
    });
});

async function checkShopAuth() {
    try {
        const res = await fetch('../api/check-auth.php');
        const data = await res.json();
        if (!data.success || !data.data.logged_in) {
            window.location.href = '../pages/login.html';
            return;
        }
        if (data.data.user.user_type !== 'shop') {
            alert('Access denied. Shop owners only.');
            window.location.href = '../index.html';
        }
    } catch (e) {
        window.location.href = '../pages/login.html';
    }
}

async function loadOrders() {
    const search = document.getElementById('searchOrder').value.trim();
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);

    const tbody = document.getElementById('ordersTable');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>Loading...</td></tr>';

    try {
        const res = await fetch('../api/shop/orders.php?' + params);
        const data = await res.json();

        if (data.success) {
            renderOrders(data.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">' + (data.message || 'Failed to load orders') + '</td></tr>';
        }
    } catch (e) {
        console.error('Load orders error:', e);
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load orders</td></tr>';
    }
}

function renderOrders(orders) {
    const tbody = document.getElementById('ordersTable');

    if (!orders.length) {
        document.getElementById('orderCount').textContent = '0';
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-grey py-5"><i class="fas fa-shopping-bag fa-3x mb-3 d-block"></i>No orders found</td></tr>';
        return;
    }

    // Group items by order_id, preserving date order
    const grouped = {};
    const orderKeys = [];
    orders.forEach(item => {
        if (!grouped[item.order_id]) {
            grouped[item.order_id] = {
                order_number:  item.order_number,
                order_date:    item.order_date,
                customer_name: item.customer_name,
                items: []
            };
            orderKeys.push(item.order_id);
        }
        grouped[item.order_id].items.push(item);
    });

    document.getElementById('orderCount').textContent = orderKeys.length;

    let html = '';
    orderKeys.forEach(orderId => {
        const order = grouped[orderId];
        const date  = new Date(order.order_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const count = order.items.length;

        // Order header row spanning all columns
        html += `<tr style="background:#f0f4f8;">
            <td colspan="8" style="padding:8px 14px;border-top:2px solid #dee2e6;">
                <strong>#${esc(order.order_number)}</strong>
                &nbsp;&mdash;&nbsp;${esc(order.customer_name)}
                &nbsp;&mdash;&nbsp;<small class="text-muted">${date}</small>
                &nbsp;&mdash;&nbsp;<small class="text-muted">${count} item${count > 1 ? 's' : ''}</small>
            </td>
        </tr>`;

        // One row per item under this order
        order.items.forEach(item => {
            const rawImg  = item.product_image ? item.product_image.replace(/^\//, '') : null;
            const imgSrc  = rawImg ? '../' + rawImg : null;
            const img     = imgSrc
                ? `<img src="${imgSrc}" class="product-thumb me-2" alt="" onerror="this.style.display='none'">`
                : `<div class="product-thumb-placeholder me-2"><i class="fas fa-image text-grey"></i></div>`;
            const sizeTag = item.selected_size
                ? `<span class="badge bg-light text-dark border ms-1" style="font-size:10px;">${esc(item.selected_size)}</span>`
                : '';
            const actions = nextActions(item.item_status);

            html += `<tr>
                <td colspan="3">
                    <div class="d-flex align-items-center ps-3">
                        ${img}
                        <span style="font-size:13px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${esc(item.product_name)}">${esc(item.product_name)}${sizeTag}</span>
                    </div>
                </td>
                <td>${item.quantity}</td>
                <td>₹${parseFloat(item.subtotal).toLocaleString('en-IN')}</td>
                <td></td>
                <td><span class="order-badge badge-${item.item_status}">${capitalize(item.item_status)}</span></td>
                <td>${actions ? `<select class="form-select form-select-sm" style="min-width:120px" onchange="updateStatus(${item.order_item_id}, this.value, this)">
                    <option value="">Change...</option>
                    ${actions}
                </select>` : '<span class="text-muted small">—</span>'}</td>
            </tr>`;
        });
    });

    tbody.innerHTML = html;
}

function nextActions(status) {
    const flow = {
        pending:    ['confirmed'],
        confirmed:  ['processing', 'cancelled'],
        processing: ['shipped', 'cancelled'],
        shipped:    ['delivered'],
    };
    const opts = flow[status];
    if (!opts) return '';
    return opts.map(s => `<option value="${s}">${capitalize(s)}</option>`).join('');
}

async function updateStatus(itemId, status, selectEl) {
    if (!status) return;

    const label = capitalize(status);
    if (!confirm(`Mark this order as "${label}"?`)) {
        selectEl.value = '';
        return;
    }

    try {
        const res = await fetch('../api/shop/update-item-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_item_id: itemId, status: status })
        });
        const data = await res.json();

        if (data.success) {
            showToast('Order status updated', 'success');
            loadOrders();
        } else {
            showToast(data.message || 'Failed to update', 'error');
            selectEl.value = '';
        }
    } catch (e) {
        showToast('Failed to update status', 'error');
        selectEl.value = '';
    }
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

/* ===================================
   NEW ORDER NOTIFICATIONS
   Polls every 30s for pending orders.
   Plays a sound + shows toast when
   a new one arrives.
   =================================== */
function startOrderNotifications() {
    // First snapshot (silent — no notification for already-existing orders)
    pollNewOrders();
    setInterval(pollNewOrders, 30000);
}

async function pollNewOrders() {
    try {
        const res  = await fetch('../api/shop/orders.php?status=pending');
        if (!res.ok) { console.warn('Shop poll HTTP error:', res.status); return; }
        const data = await res.json();
        if (!data.success) { console.warn('Shop poll API error:', data.message); return; }

        const ids = (data.data || []).map(o => Number(o.order_item_id));

        if (_knownOrderIds === null) {
            // Baseline — just remember current IDs, do not notify
            _knownOrderIds = new Set(ids);
            return;
        }

        const newOnes = ids.filter(id => !_knownOrderIds.has(id));
        if (newOnes.length) {
            newOnes.forEach(id => _knownOrderIds.add(id));
            playOrderSound();
            _shopToast(`<i class="fas fa-bell me-2"></i>${newOnes.length} new order${newOnes.length > 1 ? 's' : ''} received!`);
            loadOrders(); // refresh table
        }
    } catch (e) {
        console.warn('Shop poll error:', e);
    }
}

function _shopToast(message) {
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
    const toast = new bootstrap.Toast(el, { autohide: true, delay: 6000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function playOrderSound() {
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
        // Resume first (mobile browsers suspend context when idle)
        if (_audioCtx.state === 'suspended') {
            _audioCtx.resume().then(() => _play(_audioCtx));
        } else {
            _play(_audioCtx);
        }
    } catch (e) {
        console.warn('Order sound playback failed:', e);
    }
}
