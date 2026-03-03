/**
 * Home Page - Dynamic Featured Shops + New Arrivals
 */

document.addEventListener('DOMContentLoaded', () => {
    loadFeaturedShops();
    loadNewArrivals();
});

async function loadFeaturedShops() {
    const container = document.getElementById('featuredShops');
    if (!container) return;

    container.innerHTML = '<div class="col-12 text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const res  = await fetch('api/customer/shops.php?limit=6');
        const data = await res.json();

        if (!data.success || !data.data.length) {
            container.innerHTML = '<div class="col-12 text-center text-grey py-4">No shops available yet.</div>';
            return;
        }

        container.innerHTML = data.data.map(shop => buildShopCard(shop, true)).join('');

    } catch (err) {
        console.error('Load featured shops error:', err);
        container.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load shops.</div>';
    }
}

async function loadNewArrivals() {
    const track = document.getElementById('newArrivalsTrack');
    if (!track) return;

    track.innerHTML = '<div class="text-center py-4 w-100" style="color:#888"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const res  = await fetch('api/customer/products.php?sort=newest');
        const data = await res.json();

        if (!data.success || !data.data.length) {
            track.innerHTML = '<div class="text-center py-4 w-100" style="color:rgba(255,255,255,0.4)">No products available yet.</div>';
            return;
        }

        track.innerHTML = data.data.slice(0, 12).map(p => buildArrivalCard(p)).join('');

    } catch (err) {
        console.error('Load new arrivals error:', err);
        track.innerHTML = '';
    }
}

function buildArrivalCard(p) {
    const img = p.primary_image
        ? p.primary_image.replace(/^\//, '')
        : 'https://placehold.co/300x400/333/fff?text=' + encodeURIComponent(p.product_name);
    const catBadge = p.category_name
        ? `<span class="na-badge-cat">${p.category_name}</span>`
        : '';

    const safeName = p.product_name.replace(/'/g, "\\'");
    const safeShop = (p.shop_name || '').replace(/'/g, "\\'");
    const safeImg  = img.replace(/'/g, "\\'");
    const pid      = p.product_id;
    const price    = parseFloat(p.price) || 0;

    // Size selector — first size pre-selected by default
    const sizes    = (p.size && p.size.trim()) ? p.size.split(',').map(s => s.trim()).filter(Boolean) : [];
    const hasSizes = sizes.length > 0;

    const sizeRow = hasSizes
        ? `<div class="card-size-row mb-2">
               <span class="card-size-label">Size:</span>
               ${sizes.map((s, i) => `<button type="button"
                   class="card-size-btn${i === 0 ? ' active' : ''}"
                   onclick="cardSelectSize(this)"
                   data-size="${s}">${s}</button>`).join('')}
           </div>`
        : '';

    const cartBtn = hasSizes
        ? `<button class="btn btn-primary btn-sm w-100 mt-1"
               onclick="naAddToCart('${pid}','${safeName}',${price},'${safeImg}','${safeShop}',this)">
               <i class="fas fa-cart-plus"></i> Add to Cart
           </button>`
        : `<button class="btn btn-primary btn-sm w-100 mt-1"
               onclick="quickAddToCart(this,'${pid}','${safeName}',${price},'${safeImg}','${safeShop}')">
               <i class="fas fa-cart-plus"></i> Add to Cart
           </button>`;

    return `
        <div class="na-card" data-pid="${pid}">
            <a href="pages/products.html?shop_id=${p.shop_id}" class="d-block">
                <div class="na-img-wrap">
                    <img src="${img}" alt="${p.product_name}"
                         onerror="this.src='https://placehold.co/300x400/333/fff?text=${encodeURIComponent(p.product_name)}'">
                    <span class="na-badge-new">New</span>
                    ${catBadge}
                </div>
            </a>
            <div class="na-info">
                <div class="na-name">${p.product_name}</div>
                <div class="na-shop"><i class="fas fa-store"></i>${p.shop_name}</div>
                <div class="na-price">₹${price.toLocaleString('en-IN')}</div>
                ${sizeRow}
                ${cartBtn}
            </div>
        </div>`;
}

function naAddToCart(productId, productName, productPrice, productImage, shopName, btn) {
    const card       = btn.closest('.na-card');
    const activeSize = card ? card.querySelector('.card-size-btn.active') : null;
    const size       = activeSize ? activeSize.dataset.size : null;
    quickAddToCart(btn, productId, productName, productPrice, productImage, shopName, size);
}

function buildShopCard(shop, showStats) {
    const img   = shop.shop_logo
        ? shop.shop_logo.replace(/^\//, '')
        : 'https://placehold.co/400x250/1a1a1a/ffffff?text=' + encodeURIComponent(shop.shop_name);
    const city  = shop.shop_city ? ` &mdash; ${shop.shop_city}` : '';
    const stats = showStats ? `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-grey"><i class="fas fa-star text-warning"></i>
                ${parseFloat(shop.rating_average || 0).toFixed(1)}
                (${shop.total_ratings || 0} reviews)
            </span>
            <span class="text-grey"><i class="fas fa-box"></i> ${shop.total_products || 0} Products</span>
        </div>` : '';

    return `
        <div class="col-lg-4 col-md-6">
            <div class="shop-card">
                <img src="${img}" alt="${shop.shop_name}"
                     onerror="this.src='https://placehold.co/400x250/1a1a1a/ffffff?text=${encodeURIComponent(shop.shop_name)}'">
                <div class="card-body">
                    <h5 class="card-title">${shop.shop_name}</h5>
                    <p class="card-text">${shop.shop_description || ''}${city}</p>
                    ${stats}
                    <a href="pages/products.html?shop_id=${shop.shop_id}" class="btn btn-primary w-100">
                        View Products <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>`;
}
