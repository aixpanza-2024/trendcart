/**
 * My Wishlist Page
 */

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }
    loadWishlistPage();
});

async function loadWishlistPage() {
    const grid = document.getElementById('wishlistGrid');
    if (!grid) return;

    try {
        const res  = await fetch('../api/customer/wishlist.php');
        const data = await res.json();

        if (!data.success) {
            if (res.status === 401) { window.location.href = 'login.html'; return; }
            grid.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load your wishlist.</div>';
            return;
        }

        if (!data.data.length) {
            grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-heart fa-3x text-grey mb-3"></i>
                    <h5>Your wishlist is empty</h5>
                    <p class="text-grey">Tap the heart on any product to save it here.</p>
                    <a href="products.html" class="btn btn-primary mt-2">Browse Products</a>
                </div>`;
            return;
        }

        grid.innerHTML = data.data.map(p => buildWishlistCard(p)).join('');
        refreshWishlistHearts();

    } catch (err) {
        console.error('Load wishlist error:', err);
        grid.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load your wishlist.</div>';
    }
}

function buildWishlistCard(p) {
    const img = p.primary_image
        ? '../' + p.primary_image.replace(/^\//, '')
        : 'https://placehold.co/400x180/1a1a1a/ffffff?text=' + encodeURIComponent(p.product_name);

    const discountBadge = parseFloat(p.discount_percentage || 0) > 0
        ? `<span class="product-discount-badge">${Math.round(p.discount_percentage)}% off</span>` : '';

    const originalPrice = parseFloat(p.original_price || 0) > parseFloat(p.price)
        ? `<small class="text-grey text-decoration-line-through ms-2">₹${parseFloat(p.price).toLocaleString()}</small>` : '';

    const safeName = p.product_name.replace(/'/g, "\\'");
    const safeShop = (p.shop_name || '').replace(/'/g, "\\'");
    const safeImg  = img.replace(/'/g, "\\'");

    const actionBtn = p.is_available
        ? `<button class="btn btn-primary btn-sm w-100"
               onclick="quickAddToCart(this,'${p.product_id}','${safeName}',${p.price},'${safeImg}','${safeShop}','${p.shop_id}',null)">
               <i class="fas fa-shopping-cart"></i> Add to Cart
           </button>`
        : `<button class="btn btn-outline-secondary btn-sm w-100" disabled>
               <i class="fas fa-ban"></i> Currently Unavailable
           </button>`;

    return `
        <div class="col-md-6 col-lg-4">
            <div class="product-card" data-pid="${p.product_id}">
                <a href="product-detail.html?id=${p.product_id}" class="d-block">
                    <div class="product-img-wrap${p.is_available ? '' : ' opacity-50'}">
                        <img src="${img}" alt="${p.product_name}"
                             onerror="this.src='https://placehold.co/400x180/1a1a1a/ffffff?text=Product'">
                        ${discountBadge}
                    </div>
                </a>
                ${wishlistHeartHtml(p.product_id)}
                <div class="card-body py-2 px-3">
                    <h6 class="card-title mb-1">
                        <a href="product-detail.html?id=${p.product_id}" class="text-decoration-none text-dark">
                            ${p.product_name}
                        </a>
                    </h6>
                    <div class="mb-1 small text-grey"><i class="fas fa-store me-1"></i>${p.shop_name}</div>
                    <div class="card-price mb-2">
                        ₹${parseFloat(p.price).toLocaleString()}${originalPrice}
                    </div>
                    ${actionBtn}
                </div>
            </div>
        </div>`;
}
