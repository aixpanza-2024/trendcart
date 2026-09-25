/**
 * Wishlist Widget — shared heart-button logic for product cards and product detail.
 * Include after main.js (needs _isAuthValid) and before any script that renders a heart button.
 */

let _wishlistIds = new Set();
let _wishlistLoaded = false;
let _wishlistLoadPromise = null;

/**
 * Fetch the logged-in customer's wishlisted product IDs once and cache them.
 * Safe to call repeatedly — later calls reuse the same in-flight/completed request.
 */
function loadWishlistIds() {
    if (_wishlistLoadPromise) return _wishlistLoadPromise;

    if (typeof _isAuthValid === 'function' ? !_isAuthValid() : localStorage.getItem('isLoggedIn') !== 'true') {
        _wishlistLoaded = true;
        _wishlistLoadPromise = Promise.resolve(_wishlistIds);
        return _wishlistLoadPromise;
    }

    _wishlistLoadPromise = fetch(apiPathFromCurrentPage('customer/wishlist.php'))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                _wishlistIds = new Set(data.data.map(row => String(row.product_id)));
            }
            _wishlistLoaded = true;
            return _wishlistIds;
        })
        .catch(() => { _wishlistLoaded = true; return _wishlistIds; });

    return _wishlistLoadPromise;
}

// pages/*.html calls the API as ../api/..., index.html at the site root calls it as api/...
function apiPathFromCurrentPage(path) {
    return (window.location.pathname.includes('/pages/') ? '../api/' : 'api/') + path;
}

/**
 * HTML for a heart toggle button. Call refreshWishlistHearts() after the markup
 * is in the DOM (and after loadWishlistIds() resolves) so the filled/outline
 * state reflects what's actually saved.
 */
function wishlistHeartHtml(productId) {
    return `<button type="button" class="wishlist-heart-btn" data-wishlist-pid="${productId}"
                aria-label="Save to wishlist" onclick="onWishlistHeartClick(event, this)">
                <i class="fa-regular fa-heart"></i>
            </button>`;
}

/** Call once after rendering a batch of cards, to paint the correct heart state. */
async function refreshWishlistHearts() {
    await loadWishlistIds();
    document.querySelectorAll('[data-wishlist-pid]').forEach(btn => {
        setHeartState(btn, _wishlistIds.has(String(btn.dataset.wishlistPid)));
    });
}

function setHeartState(btn, active) {
    btn.classList.toggle('active', active);
    const icon = btn.querySelector('i');
    if (icon) icon.className = active ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
}

async function onWishlistHeartClick(event, btn) {
    event.preventDefault();
    event.stopPropagation();

    const isLoggedIn = typeof _isAuthValid === 'function' ? _isAuthValid() : localStorage.getItem('isLoggedIn') === 'true';
    if (!isLoggedIn) {
        showToast('Please login to use your wishlist', 'error');
        setTimeout(() => {
            window.location.href = window.location.pathname.includes('/pages/') ? 'login.html' : 'pages/login.html';
        }, 1200);
        return;
    }

    const productId = btn.dataset.wishlistPid;
    if (btn.disabled) return;
    btn.disabled = true;

    try {
        const res  = await fetch(apiPathFromCurrentPage('customer/wishlist-toggle.php'), {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: productId }),
        });
        const data = await res.json();

        if (data.success) {
            const active = data.data.wishlisted;
            if (active) _wishlistIds.add(String(productId)); else _wishlistIds.delete(String(productId));
            // Every heart for this product on the page stays in sync (e.g. listing + a "more from this shop" card)
            document.querySelectorAll(`[data-wishlist-pid="${productId}"]`).forEach(b => setHeartState(b, active));
            showToast(active ? 'Added to wishlist' : 'Removed from wishlist', 'success');
        } else {
            showToast(data.message || 'Could not update wishlist', 'error');
        }
    } catch (err) {
        console.error('Wishlist toggle error:', err);
        showToast('Could not update wishlist. Please try again.', 'error');
    } finally {
        btn.disabled = false;
    }
}
