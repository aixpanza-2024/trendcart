/**
 * Home Page - Dynamic product sections (Offers, Products, Best Sellers)
 * Shared by index.html (Dress) and cosmetics.html (Cosmetics) — the page sets
 * <body data-vertical="dress|cosmetics"> and every fetch here is scoped to it.
 */

const HOME_VERTICAL = document.body.dataset.vertical === 'cosmetics' ? 'cosmetics' : 'dress';

document.addEventListener('DOMContentLoaded', () => {
    loadProductSection('offersTrack', 'on_offer=1', 'offer', 'No offers right now.');
    loadProductSection('productsTrack', '', null, 'No products available yet.');
    loadProductSection('bestSellersTrack', 'sort=popular', 'bestseller', 'No best sellers yet.');
});

async function loadProductSection(trackId, queryParams, badge, emptyMessage) {
    const track = document.getElementById(trackId);
    if (!track) return;

    track.innerHTML = '<div class="text-center py-4 w-100" style="color:#888"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const qs  = queryParams ? `${queryParams}&vertical=${HOME_VERTICAL}` : `vertical=${HOME_VERTICAL}`;
        const res  = await fetch(`api/customer/products.php?${qs}`);
        const data = await res.json();

        if (!data.success || !data.data.length) {
            track.innerHTML = `<div class="text-center py-4 w-100" style="color:rgba(255,255,255,0.4)">${emptyMessage}</div>`;
            return;
        }

        track.innerHTML = data.data.slice(0, 12).map(p => buildArrivalCard(p, badge)).join('');
        if (typeof refreshWishlistHearts === 'function') refreshWishlistHearts();

    } catch (err) {
        console.error(`Load product section error (${trackId}):`, err);
        track.innerHTML = '';
    }
}

function buildArrivalCard(p, badge) {
    const img = p.primary_image
        ? p.primary_image.replace(/^\//, '')
        : 'https://placehold.co/300x400/333/fff?text=' + encodeURIComponent(p.product_name);
    const catBadge = p.category_name
        ? `<span class="na-badge-cat">${tcName(p.category_name)}</span>`
        : '';
    const cornerBadge = badge === 'offer' && parseFloat(p.discount_percentage) > 0
        ? `<span class="na-badge-new" style="background:#dc3545;">${Math.round(p.discount_percentage)}% OFF</span>`
        : badge === 'bestseller'
            ? `<span class="na-badge-new" style="background:#c9995c;">Bestseller</span>`
            : '';

    const safeName = p.product_name.replace(/'/g, "\\'");
    const safeShop = (p.shop_name || '').replace(/'/g, "\\'");
    const safeImg  = img.replace(/'/g, "\\'");
    const pid      = p.product_id;
    const price    = parseFloat(p.price) || 0;

    // Per-size stock map from API
    const sizesStockMap = {};
    if (p.sizes_stock) {
        p.sizes_stock.split(',').forEach(pair => {
            const idx = pair.lastIndexOf(':');
            if (idx > -1) sizesStockMap[pair.substring(0, idx).trim()] = parseInt(pair.substring(idx + 1)) || 0;
        });
    }

    // Size selector — first available (non-OOS) size pre-selected by default
    const sizes    = (p.size && p.size.trim()) ? p.size.split(',').map(s => s.trim()).filter(Boolean) : [];
    const hasSizes = sizes.length > 0;
    let firstAvailableSet = false;

    const sizeRow = hasSizes
        ? `<div class="card-size-row mb-2">
               <span class="card-size-label">Size:</span>
               ${sizes.map(s => {
                   const stock = sizesStockMap.hasOwnProperty(s) ? sizesStockMap[s] : 99;
                   const oos   = stock <= 0;
                   const isDefault = !oos && !firstAvailableSet ? (firstAvailableSet = true, true) : false;
                   return `<button type="button"
                       class="card-size-btn${oos ? ' oos' : isDefault ? ' active' : ''}"
                       ${oos ? 'disabled title="Out of stock"' : `onclick="cardSelectSize(this)"`}
                       data-size="${s}" data-stock="${oos ? 0 : stock}">${s}</button>`;
               }).join('')}
           </div>`
        : '';

    const cartBtn = `<button class="btn btn-primary btn-sm w-100 mt-1"
               onclick="naAddToCart('${pid}','${safeName}',${price},'${safeImg}','${safeShop}','${p.shop_id}',this)">
               <i class="fas fa-cart-plus"></i> Add to Cart
           </button>`;

    return `
        <div class="na-card" data-pid="${pid}">
            <a href="pages/products.html?shop_id=${p.shop_id}" class="d-block">
                <div class="na-img-wrap">
                    <img src="${img}" alt="${p.product_name}"
                         onerror="this.src='https://placehold.co/300x400/333/fff?text=${encodeURIComponent(p.product_name)}'">
                    ${cornerBadge}
                    ${catBadge}
                    ${wishlistHeartHtml(pid)}
                </div>
            </a>
            <div class="na-info">
                <div class="na-name">${p.product_name}</div>
                <div class="na-shop"><i class="fas fa-store"></i>${p.shop_name}</div>
                <div class="na-price">₹${price.toLocaleString('en-IN')}</div>
                ${cardColorRow(p)}
                ${sizeRow}
                ${cartBtn}
            </div>
        </div>`;
}

function naAddToCart(productId, productName, productPrice, productImage, shopName, shopId, btn) {
    const choice = cardChoice(btn.closest('.na-card'));
    if (!choice) return;
    quickAddToCart(btn, productId, productName, productPrice, productImage, shopName, shopId, choice.size, 1, choice.color);
}
