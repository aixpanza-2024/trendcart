/**
 * Admin Common - Shared utilities for all admin pages
 */

// ── Mobile-safe Audio Context ─────────────────────────────────────────
// Unlock on first user interaction so polling callbacks can play sound
// on mobile (iOS/Android) where AudioContext starts suspended.
let _adminAudioCtx = null;
let _adminKnownOrderIds = null; // null = first poll (baseline only)

(function _setupAdminAudioUnlock() {
    const unlock = () => {
        try {
            if (!_adminAudioCtx) _adminAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (_adminAudioCtx.state === 'suspended') _adminAudioCtx.resume();
            // iOS unlock trick: play a silent 1-frame buffer
            const buf = _adminAudioCtx.createBuffer(1, 1, 22050);
            const src = _adminAudioCtx.createBufferSource();
            src.buffer = buf;
            src.connect(_adminAudioCtx.destination);
            src.start(0);
        } catch (e) {}
    };
    document.addEventListener('click',      unlock);
    document.addEventListener('touchstart', unlock, { passive: true });
})();

function playAdminOrderSound() {
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
        if (!_adminAudioCtx) _adminAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (_adminAudioCtx.state === 'suspended') {
            _adminAudioCtx.resume().then(() => _play(_adminAudioCtx));
        } else {
            _play(_adminAudioCtx);
        }
    } catch (e) {
        console.warn('Admin order sound failed:', e);
    }
}

function startAdminOrderNotifications() {
    pollAdminNewOrders();
    setInterval(pollAdminNewOrders, 30000);
}

async function pollAdminNewOrders() {
    try {
        const res  = await fetch('../api/admin/orders.php?status=pending');
        const data = await res.json();
        if (!data.success) return;

        const ids = (data.data || []).map(o => Number(o.order_id));

        if (_adminKnownOrderIds === null) {
            // First poll — just record baseline, no notification
            _adminKnownOrderIds = new Set(ids);
            return;
        }

        const newOnes = ids.filter(id => !_adminKnownOrderIds.has(id));
        if (newOnes.length) {
            newOnes.forEach(id => _adminKnownOrderIds.add(id));
            playAdminOrderSound();
            adminToast(
                `<i class="fas fa-bell me-2"></i>${newOnes.length} new order${newOnes.length > 1 ? 's' : ''} received!`,
                'success'
            );
            // Refresh the pending orders sidebar badge
            loadSidebarBadges();
        }
    } catch (e) { /* silent */ }
}

// Render sidebar immediately — script is at bottom of body so #sidebar exists now
renderAdminSidebar();

document.addEventListener('DOMContentLoaded', function () {
    initAdminSidebar();
    checkAdminAuth();
    setTodayDate();
});

/* --- Sidebar Renderer (single source of truth) --- */
function renderAdminSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    // Detect current page filename
    const page = window.location.pathname.split('/').pop() || 'dashboard.html';

    const nav = [
        { section: 'Main' },
        { href: 'dashboard.html', icon: 'fa-tachometer-alt', label: 'Dashboard' },
        { section: 'Management' },
        { href: 'shops.html',     icon: 'fa-store',          label: 'Shops' },
        { href: 'orders.html',    icon: 'fa-shopping-bag',   label: 'Orders', badge: 'sidebarPendingOrders' },
        { href: 'customers.html', icon: 'fa-users',          label: 'Customers' },
        { href: 'categories.html',icon: 'fa-tags',           label: 'Categories', badge: 'sidebarCategoryReqs' },
        { section: 'Finance' },
        { href: 'revenue.html',   icon: 'fa-chart-line',     label: 'Revenue' },
        { href: 'payments.html',  icon: 'fa-rupee-sign',     label: 'Shop Payments' },
        { href: 'reports.html',   icon: 'fa-file-alt',       label: 'Reports' },
        { section: 'Config' },
        { href: 'settings.html',  icon: 'fa-cog',            label: 'Settings' },
        { divider: true },
        { href: '../index.html',  icon: 'fa-globe',          label: 'View Website' },
        { href: '#', icon: 'fa-sign-out-alt', label: 'Logout', onclick: 'adminLogout()' },
    ];

    const linksHTML = nav.map(item => {
        if (item.section) return `<div class="nav-section">${item.section}</div>`;
        if (item.divider) return `<div class="admin-sidebar-divider"></div>`;
        const active  = item.href === page ? ' active' : '';
        const onclick = item.onclick ? ` onclick="${item.onclick}"` : '';
        const badge   = item.badge   ? `<span class="badge bg-danger" id="${item.badge}"></span>` : '';
        return `<a href="${item.href}" class="nav-link${active}"${onclick}>
                    <i class="fas ${item.icon}"></i> <span>${item.label}</span>${badge}
                </a>`;
    }).join('\n');

    sidebar.innerHTML = `
        <div class="admin-sidebar-header">
            <img src="../assets/images/trencartlogo.png" alt="TrenCart" style="height: 32px;">
            <div class="admin-badge">Admin Panel</div>
        </div>
        <div class="admin-sidebar-nav">
            ${linksHTML}
        </div>`;
}

/* --- Sidebar Toggle --- */
function initAdminSidebar() {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
}

/* --- Auth Check --- */
async function checkAdminAuth() {
    try {
        const res = await fetch('../api/check-auth.php');
        const data = await res.json();

        if (!data.success || !data.data.logged_in) {
            window.location.href = '../pages/login.html';
            return;
        }

        if (data.data.user.user_type !== 'admin') {
            alert('Access denied. Admin only.');
            window.location.href = '../index.html';
            return;
        }

        // Set avatar initial
        const avatar = document.getElementById('adminAvatar');
        if (avatar && data.data.user.full_name) {
            avatar.textContent = data.data.user.full_name.charAt(0).toUpperCase();
        }

        // Load sidebar badge counts + start new-order notifications
        loadSidebarBadges();
        startAdminOrderNotifications();
    } catch (e) {
        console.error('Auth check failed:', e);
        window.location.href = '../pages/login.html';
    }
}

async function loadSidebarBadges() {
    try {
        const res  = await fetch('../api/admin/category-requests.php?status=pending');
        const data = await res.json();
        if (data.success && data.pending_count > 0) {
            const badge = document.getElementById('sidebarCategoryReqs');
            if (badge) badge.textContent = data.pending_count;
        }
    } catch (e) { /* silent */ }
}

/* --- Logout --- */
function adminLogout() {
    if (!confirm('Are you sure you want to logout?')) return;
    fetch('../api/logout.php', { method: 'POST' })
        .then(() => window.location.href = '../pages/login.html')
        .catch(() => window.location.href = '../pages/login.html');
}

/* --- Date Display --- */
function setTodayDate() {
    const el = document.getElementById('todayDate');
    if (el) {
        el.textContent = new Date().toLocaleDateString('en-IN', {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
        });
    }
}

/* --- Format Currency --- */
function formatINR(amount) {
    const num = parseFloat(amount) || 0;
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

/* --- Format Date --- */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
}

/* --- Status Badge HTML --- */
function statusBadge(status) {
    return `<span class="badge-status badge-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

/* --- Toast --- */
function adminToast(message, type = 'success') {
    const bg = type === 'success' ? 'bg-dark' : 'bg-danger';
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white ${bg} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>`;
    container.appendChild(el);
    const toast = new bootstrap.Toast(el, { autohide: true, delay: 5000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/* --- Admin API Helper --- */
async function adminAPI(url, options) {
    const res = await fetch(url, options);
    return await res.json();
}
