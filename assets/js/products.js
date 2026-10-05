/**
 * Products Page - Dynamic Products Listing with Filters
 */

let allProducts = [];
let currentShopId = null; // set when viewing a single shop — used by "use my location"
let allCategories = [];
let currentFiltered = [];
const PAGE_SIZE = 24;
let displayLimit = PAGE_SIZE;

document.addEventListener('DOMContentLoaded', function () {
    const urlParams       = new URLSearchParams(window.location.search);
    const shopId          = urlParams.get('shop_id');
    const categoryId      = urlParams.get('category_id');
    const superCategoryId = urlParams.get('super_category_id');
    const onOffer          = urlParams.get('on_offer') === '1';
    const searchParam     = urlParams.get('search');

    // Pre-fill search box if a ?search= param was passed (e.g. from navbar search)
    if (searchParam) {
        const searchEl = document.getElementById('productSearch');
        if (searchEl) searchEl.value = searchParam;
    }

    loadCategories(categoryId, superCategoryId);
    loadProducts(shopId, categoryId, superCategoryId, onOffer);

    // Price range — update label and re-filter on every drag
    const priceRange = document.getElementById('priceRange');
    if (priceRange) {
        priceRange.addEventListener('input', function () {
            document.getElementById('priceValue').textContent = '₹' + parseInt(this.value).toLocaleString();
            applyFilters();
        });
    }

    // Sort
    const sortBy = document.getElementById('sortBy');
    if (sortBy) {
        sortBy.addEventListener('change', applyFilters);
    }

    // Inline product search bar
    const productSearch = document.getElementById('productSearch');
    if (productSearch) {
        productSearch.addEventListener('input', debounce(applyFilters, 300));
    }
});

function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

async function loadProducts(shopId, categoryId, superCategoryId, onOffer) {
    const container = document.getElementById('productsGrid');
    if (!container) return;

    container.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const params = new URLSearchParams();
        if (shopId)          params.set('shop_id', shopId);
        if (categoryId)      params.set('category_id', categoryId);
        if (superCategoryId) params.set('super_category_id', superCategoryId);
        if (onOffer)          params.set('on_offer', '1');

        const res  = await fetch('../api/customer/products.php?' + params);
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        // Update shop header
        if (data.shop_info) {
            // Shop-specific view
            const shop = data.shop_info;
            currentShopId = shop.shop_id;
            document.title = shop.shop_name + ' Products - TrenCart';

            const defaultHeader = document.getElementById('shopHeaderDefault');
            const shopCard      = document.getElementById('shopHeaderCard');
            if (defaultHeader) defaultHeader.style.display = 'none';
            if (shopCard)      shopCard.style.display = '';

            loadShopReviews(shop.shop_id);

            const nameEl = document.getElementById('shopTitle');
            const descEl = document.getElementById('shopDescription');
            if (nameEl) nameEl.textContent = shop.shop_name;
            if (descEl) descEl.textContent = shop.shop_description || 'Premium dress materials and fabrics.';

            // Shop logo
            const logoWrap = document.getElementById('shopLogoWrap');
            if (logoWrap) {
                if (shop.shop_logo) {
                    const logoUrl = '../' + shop.shop_logo.replace(/^\//, '');
                    logoWrap.innerHTML = `<img src="${logoUrl}" alt="${shop.shop_name}"
                        style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:2px solid var(--border-color);"
                        onerror="this.replaceWith(document.getElementById('_logoFallback').content.cloneNode(true))">
                        <template id="_logoFallback"><div style="width:72px;height:72px;border-radius:50%;background:var(--primary-black);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;">${shop.shop_name.charAt(0).toUpperCase()}</div></template>`;
                } else {
                    logoWrap.innerHTML = `<div style="width:72px;height:72px;border-radius:50%;background:var(--primary-black);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;">${shop.shop_name.charAt(0).toUpperCase()}</div>`;
                }
            }

            // Stats row
            const statsEl = document.getElementById('shopStats');
            if (statsEl) {
                const rating  = parseFloat(shop.rating_average || 0).toFixed(1);
                const reviews = parseInt(shop.total_ratings || 0);
                const city    = shop.shop_city ? `<span class="text-grey small"><i class="fas fa-map-marker-alt me-1"></i>${shop.shop_city}</span>` : '';
                statsEl.innerHTML = `
                    <span class="text-grey small"><i class="fas fa-star text-warning me-1"></i><strong>${rating}</strong> <span class="text-grey">(${reviews} reviews)</span></span>
                    ${city}`;
            }
        } else if (data.super_category_info) {
            // Super-category view (e.g. "Cosmetics") — reuse the default header, just retitle it
            const sc = data.super_category_info;
            document.title = sc.super_category_name + ' - TrenCart';
            const titleEl = document.querySelector('#shopHeaderDefault .pb-title');
            const subEl   = document.querySelector('#shopHeaderDefault .pb-sub');
            if (titleEl) titleEl.textContent = sc.super_category_name;
            if (subEl)   subEl.textContent   = `Browse everything in ${sc.super_category_name}`;
        } else if (onOffer) {
            // Offers view — reuse the default header, just retitle it
            document.title = "Today's Offers - TrenCart";
            const titleEl = document.querySelector('#shopHeaderDefault .pb-title');
            const subEl   = document.querySelector('#shopHeaderDefault .pb-sub');
            if (titleEl) titleEl.textContent = "Today's Offers";
            if (subEl)   subEl.textContent   = 'Products currently on a discount, across every shop';
        } else {
            // All-products view (no shop_id / super_category_id / offer filter)
            document.title = 'All Products - TrenCart';
        }

        allProducts = data.data || [];

        // Set price range slider max to the highest product price (rounded up to nearest 500)
        const priceRange = document.getElementById('priceRange');
        if (priceRange && allProducts.length) {
            const maxP = Math.max(...allProducts.map(p => parseFloat(p.price)));
            const sliderMax = Math.ceil(maxP / 500) * 500 || 10000;
            priceRange.max   = sliderMax;
            priceRange.value = sliderMax;
            const priceValue = document.getElementById('priceValue');
            if (priceValue) priceValue.textContent = '₹' + sliderMax.toLocaleString();
        }

        // Use applyFilters() so any pre-filled search/category params take effect
        applyFilters();

    } catch (err) {
        console.error('Load products error:', err);
        container.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load products.</div>';
    }
}

function renderProducts(products) {
    const container = document.getElementById('productsGrid');
    const countEl   = document.getElementById('productCount');
    if (!container) return;

    if (countEl) countEl.textContent = products.length;

    // Remove any existing Load More button
    document.getElementById('loadMoreBtn')?.remove();

    if (!products.length) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-grey mb-3"></i>
                <h5>No products found</h5>
                <p class="text-grey">Try adjusting your filters.</p>
            </div>`;
        return;
    }

    const visible = products.slice(0, displayLimit);
    container.innerHTML = visible.map(p => buildProductCard(p)).join('');
    refreshWishlistHearts();

    if (products.length > displayLimit) {
        const remaining = products.length - displayLimit;
        container.insertAdjacentHTML('beforeend', `
            <div id="loadMoreBtn" class="col-12 text-center mt-4 mb-3">
                <button class="btn btn-outline-dark px-5" onclick="loadMore()">
                    Load More <span class="badge bg-secondary ms-2">${remaining} more</span>
                </button>
            </div>`);
    }
}

function loadMore() {
    document.getElementById('loadMoreBtn')?.remove();

    const prev = displayLimit;
    displayLimit = Math.min(displayLimit + PAGE_SIZE, currentFiltered.length);
    const newCards = currentFiltered.slice(prev, displayLimit);

    const container = document.getElementById('productsGrid');
    newCards.forEach(p => container.insertAdjacentHTML('beforeend', buildProductCard(p)));
    refreshWishlistHearts();

    if (currentFiltered.length > displayLimit) {
        const remaining = currentFiltered.length - displayLimit;
        container.insertAdjacentHTML('beforeend', `
            <div id="loadMoreBtn" class="col-12 text-center mt-4 mb-3">
                <button class="btn btn-outline-dark px-5" onclick="loadMore()">
                    Load More <span class="badge bg-secondary ms-2">${remaining} more</span>
                </button>
            </div>`);
    }
}

function buildProductCard(p) {
    // Image URLs are stored as /uploads/products/... (absolute from server root).
    // From pages/ we need to prefix with ../ to reach the project root.
    const img = p.primary_image
        ? '../' + p.primary_image.replace(/^\//, '')
        : 'https://placehold.co/400x180/1a1a1a/ffffff?text=' + encodeURIComponent(p.product_name);

    const discountBadge = p.discount_percentage && parseFloat(p.discount_percentage) > 0
        ? `<span class="product-discount-badge">${parseFloat(p.discount_percentage).toFixed(0)}% off</span>` : '';

    const originalPrice = p.original_price && parseFloat(p.original_price) > parseFloat(p.price)
        ? `<small class="text-grey text-decoration-line-through ms-2">₹${parseFloat(p.original_price).toLocaleString()}</small>` : '';

    const shopLine = p.shop_name
        ? `<small class="text-grey me-2"><i class="fas fa-store"></i> ${p.shop_name}</small>` : '';

    const categoryLine = p.category_name
        ? `<small class="text-grey"><i class="fas fa-tag"></i> ${p.category_name}</small>` : '';

    // Escape for use in onclick attribute
    const safeName = p.product_name.replace(/'/g, "\\'");
    const safeShop = (p.shop_name || '').replace(/'/g, "\\'");
    const safeImg  = img.replace(/'/g, "\\'");
    const pid      = p.product_id;

    // Build per-size stock map from API field (e.g. "S:5,M:10,L:0")
    const sizesStockMap = {};
    if (p.sizes_stock) {
        p.sizes_stock.split(',').forEach(pair => {
            const idx = pair.lastIndexOf(':');
            if (idx > -1) sizesStockMap[pair.substring(0, idx).trim()] = parseInt(pair.substring(idx + 1)) || 0;
        });
    }

    // Build inline size selector — NO default; user must pick a size (mandatory)
    const sizes    = (p.size && p.size.trim()) ? p.size.split(',').map(s => s.trim()).filter(Boolean) : [];
    const hasSizes = sizes.length > 0;

    const sizeRow = hasSizes
        ? `<div class="card-size-row mb-2">
               <span class="card-size-label">Size:</span>
               ${sizes.map(s => {
                   const stock = sizesStockMap.hasOwnProperty(s) ? sizesStockMap[s] : 99;
                   const oos   = stock <= 0;
                   return `<button type="button"
                       class="card-size-btn${oos ? ' oos' : ''}"
                       ${oos ? 'disabled title="Out of stock"' : `onclick="cardSelectSize(this)"`}
                       data-size="${s}" data-stock="${oos ? 0 : stock}">${s}</button>`;
               }).join('')}
           </div>`
        : '';

    const qtyRow = `
        <div class="card-qty-row mb-2">
            <button type="button" class="card-qty-btn" onclick="cardChangeQty(this,-1)"><i class="fas fa-minus"></i></button>
            <span class="card-qty-display">1</span>
            <button type="button" class="card-qty-btn" onclick="cardChangeQty(this,1)"><i class="fas fa-plus"></i></button>
            <span class="card-qty-amount">₹${parseFloat(p.price).toLocaleString('en-IN')}</span>
        </div>`;

    const cartBtn = hasSizes
        ? `<button class="btn btn-primary btn-sm w-100"
               onclick="cardAddToCart('${pid}','${safeName}',${p.price},'${safeImg}','${safeShop}','${p.shop_id}',this)">
               <i class="fas fa-shopping-cart"></i> Add to Cart
           </button>`
        : `<button class="btn btn-primary btn-sm w-100"
               onclick="cardAddToCartNoSize('${pid}','${safeName}',${p.price},'${safeImg}','${safeShop}','${p.shop_id}',this)">
               <i class="fas fa-shopping-cart"></i> Add to Cart
           </button>`;

    return `
        <div class="col-md-6 col-lg-4">
            <div class="product-card" data-pid="${pid}" data-price="${parseFloat(p.price)}">
                <a href="product-detail.html?id=${pid}" class="d-block">
                    <div class="product-img-wrap">
                        <img src="${img}" alt="${p.product_name}"
                             onerror="this.src='https://placehold.co/400x180/1a1a1a/ffffff?text=Product'">
                        ${discountBadge}
                    </div>
                </a>
                ${wishlistHeartHtml(pid)}
                <div class="card-body py-2 px-3">
                    <h6 class="card-title mb-1">
                        <a href="product-detail.html?id=${pid}" class="text-decoration-none text-dark">
                            ${p.product_name}
                        </a>
                    </h6>
                    <div class="mb-1">${shopLine}${categoryLine}</div>
                    <div class="card-price mb-2">
                        ₹${parseFloat(p.price).toLocaleString()}${originalPrice}
                    </div>
                    ${sizeRow}
                    ${qtyRow}
                    ${cartBtn}
                </div>
            </div>
        </div>`;
}

/* ── Card size / qty helpers ─────────────────────────────────────── */
function cardSelectSize(btn) {
    btn.closest('.card-size-row').querySelectorAll('.card-size-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Reset qty to 1 when size changes
    const card    = btn.closest('.product-card, .na-card');
    const display = card ? card.querySelector('.card-qty-display') : null;
    const amount  = card ? card.querySelector('.card-qty-amount')  : null;
    const price   = parseFloat(card ? card.dataset.price : 0) || 0;
    if (display) display.textContent = '1';
    if (amount && price) amount.textContent = '₹' + price.toLocaleString('en-IN');
}

function cardChangeQty(btn, delta) {
    const row        = btn.closest('.card-qty-row');
    const display    = row.querySelector('.card-qty-display');
    const amount     = row.querySelector('.card-qty-amount');
    const card       = btn.closest('.product-card, .na-card');
    const price      = parseFloat(card ? card.dataset.price : 0) || 0;
    const activeSize = card ? card.querySelector('.card-size-btn.active') : null;
    const maxStock   = activeSize ? (parseInt(activeSize.dataset.stock) || 99) : 99;

    let qty = Math.max(1, Math.min(maxStock, parseInt(display.textContent) + delta));
    display.textContent = qty;
    if (amount && price) amount.textContent = '₹' + (price * qty).toLocaleString('en-IN');
}

function cardAddToCart(productId, productName, productPrice, productImage, shopName, shopId, addBtn) {
    const card       = addBtn.closest('.product-card');
    const activeSize = card ? card.querySelector('.card-size-btn.active') : null;
    const size       = activeSize ? activeSize.dataset.size : null;

    if (!size) { showToast('Please select a size first', 'error'); return; }

    const maxStock = parseInt(activeSize.dataset.stock) || 0;
    const qtyEl    = card ? card.querySelector('.card-qty-display') : null;
    const qty      = qtyEl ? (parseInt(qtyEl.textContent) || 1) : 1;

    if (maxStock > 0 && qty > maxStock) {
        showToast(`Only ${maxStock} left in stock for size ${size}`, 'error');
        return;
    }
    quickAddToCart(addBtn, productId, productName, productPrice, productImage, shopName, shopId, size, qty);
}

function cardAddToCartNoSize(productId, productName, productPrice, productImage, shopName, shopId, addBtn) {
    const card  = addBtn.closest('.product-card');
    const qtyEl = card ? card.querySelector('.card-qty-display') : null;
    const qty   = qtyEl ? (parseInt(qtyEl.textContent) || 1) : 1;
    quickAddToCart(addBtn, productId, productName, productPrice, productImage, shopName, shopId, null, qty);
}

async function loadCategories(activeCategoryId, superCategoryId) {
    const container = document.getElementById('categoryFilters');
    if (!container) return;

    try {
        const url  = superCategoryId
            ? '../api/customer/categories.php?super_category_id=' + superCategoryId
            : '../api/customer/categories.php';
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) return;

        // Store all categories for hierarchy-aware filtering
        allCategories = data.data;

        // Only show parent categories in the sidebar filter
        const parents = data.data.filter(c => !c.parent_category_id);

        container.innerHTML = parents.map(c => `
            <div class="form-check">
                <input class="form-check-input category-filter" type="checkbox"
                       value="${c.category_id}" id="cat${c.category_id}"
                       ${activeCategoryId && String(c.category_id) === String(activeCategoryId) ? 'checked' : ''}
                       onchange="applyFilters()">
                <label class="form-check-label" for="cat${c.category_id}">${c.category_name}</label>
            </div>`).join('');

    } catch (err) {
        console.error('Load categories error:', err);
    }
}

function applyFilters() {
    const priceRange  = document.getElementById('priceRange');
    const sortBy      = document.getElementById('sortBy');
    const maxPrice    = priceRange ? parseInt(priceRange.value) : 0;
    const sort        = sortBy ? sortBy.value : 'featured';
    const search      = (document.getElementById('productSearch')?.value || '').trim().toLowerCase();

    const checkedCats = [...document.querySelectorAll('.category-filter:checked')].map(el => String(el.value));

    let filtered = allProducts.slice();

    // Search filter — matches product name, shop name, or category name
    if (search) {
        filtered = filtered.filter(p =>
            (p.product_name  || '').toLowerCase().includes(search) ||
            (p.shop_name     || '').toLowerCase().includes(search) ||
            (p.category_name || '').toLowerCase().includes(search)
        );
    }

    // Category filter — expand checked parent IDs to also include their subcategories
    if (checkedCats.length > 0) {
        const expandedCats = new Set(checkedCats);
        allCategories.forEach(c => {
            if (c.parent_category_id && checkedCats.includes(String(c.parent_category_id))) {
                expandedCats.add(String(c.category_id));
            }
        });
        filtered = filtered.filter(p => expandedCats.has(String(p.category_id)));
    }

    // Price filter (only apply if slider is < max)
    const sliderMax = priceRange ? parseInt(priceRange.max) : 10000;
    if (maxPrice < sliderMax) {
        filtered = filtered.filter(p => parseFloat(p.price) <= maxPrice);
    }

    // Sort
    switch (sort) {
        case 'price-low':  filtered.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));  break;
        case 'price-high': filtered.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));  break;
        case 'newest':     filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)); break;
        case 'popular':    filtered.sort((a, b) => (b.orders_count || 0) - (a.orders_count || 0)); break;
    }

    currentFiltered = filtered;
    displayLimit = PAGE_SIZE;
    renderProducts(currentFiltered);
}

function resetFilters() {
    const priceRange = document.getElementById('priceRange');
    if (priceRange) {
        priceRange.value = priceRange.max;
        document.getElementById('priceValue').textContent = '₹' + parseInt(priceRange.max).toLocaleString();
    }

    document.querySelectorAll('.category-filter').forEach(cb => cb.checked = false);

    const sortBy = document.getElementById('sortBy');
    if (sortBy) sortBy.value = 'featured';

    currentFiltered = allProducts.slice();
    displayLimit = PAGE_SIZE;
    renderProducts(currentFiltered);
    showToast('Filters reset', 'success');
}

/* ── Shop Reviews (shown only on a single-shop view, ?shop_id=) ────── */
async function loadShopReviews(shopId) {
    const section = document.getElementById('shopReviewsSection');
    if (!section) return;

    try {
        const res  = await fetch('../api/customer/shop-reviews.php?shop_id=' + shopId);
        const data = await res.json();
        if (!data.success || !data.count) return; // no reviews yet — leave the section hidden

        section.style.display = '';
        document.getElementById('shopReviewsSummary').innerHTML =
            `${reviewStars(data.rating_average)} <strong>${data.rating_average.toFixed(1)}</strong>
             <span class="text-grey">(${data.total_ratings} review${data.total_ratings === 1 ? '' : 's'})</span>`;

        document.getElementById('shopReviewsList').innerHTML = data.data.map(r => `
            <div class="col-md-6 col-lg-4">
                <div class="h-100 p-3" style="background:var(--white-secondary,#f8f8f8);border-radius:10px;border:1px solid var(--border-color,#eee);">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>${reviewStars(r.rating)}</div>
                        <small class="text-grey">${new Date(r.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</small>
                    </div>
                    <div class="small fw-semibold mb-1">${r.reviewer_name || 'TrenCart Customer'}</div>
                    <div class="small text-grey mb-2">
                        ${r.product_id ? `on ${r.product_name}` : `on <em>${r.product_name}</em> <span class="text-grey">(no longer available)</span>`}
                    </div>
                    ${r.review_text ? `<div class="small">${r.review_text}</div>` : ''}
                </div>
            </div>`).join('');

    } catch (e) {
        console.error('Load shop reviews error:', e);
    }
}

function reviewStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="${rating >= i ? 'fas' : 'far'} fa-star text-warning" style="font-size:12px;"></i>`;
    }
    return html;
}

/* ── "Use my location" — estimates this shop's delivery fee from the
   customer's real GPS position, using the same distance tiers (under/over
   20km) checkout uses for the real order. Only fetches lat/long — no map UI. */
function useCurrentLocationForShop() {
    const btn = document.getElementById('useLocationBtn');
    const out = document.getElementById('shopDeliveryEstimate');
    if (!currentShopId || !btn || !out) return;

    if (!navigator.geolocation) {
        out.innerHTML = '<span class="text-warning small">Your browser does not support location detection.</span>';
        return;
    }

    const origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Locating...';

    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            // Save for checkout (and anywhere else) to reuse without asking again
            if (typeof _isAuthValid === 'function' ? _isAuthValid() : localStorage.getItem('isLoggedIn') === 'true') {
                fetch('../api/customer/update-location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ latitude: lat, longitude: lng }),
                }).catch(() => {});
            }
            localStorage.setItem('customerLat', lat);
            localStorage.setItem('customerLng', lng);

            try {
                const res  = await fetch(`../api/customer/check-delivery.php?shop_id=${currentShopId}&latitude=${lat}&longitude=${lng}`);
                const data = await res.json();

                if (!data.success) {
                    out.innerHTML = `<span class="text-warning small">${data.message}</span>`;
                } else if (data.method === 'distance') {
                    out.innerHTML = data.is_free
                        ? `<span class="badge bg-success">Free delivery — ${data.distance_km}km away</span>`
                        : `<span class="badge bg-warning text-dark">Delivery: ₹${data.delivery_fee} — ${data.distance_km}km away</span>`;
                } else {
                    out.innerHTML = data.is_free
                        ? `<span class="badge bg-success">Free delivery — ${data.zone_name}</span>`
                        : `<span class="badge bg-warning text-dark">Delivery: ₹${data.delivery_fee} — ${data.zone_name}</span>`;
                }
            } catch (e) {
                out.innerHTML = '<span class="text-warning small">Could not check delivery fee.</span>';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Location used';
            }
        },
        (err) => {
            btn.disabled = false;
            btn.innerHTML = origHTML;
            out.innerHTML = err.code === err.PERMISSION_DENIED
                ? '<span class="text-warning small">Location permission denied — enter your pincode at checkout instead.</span>'
                : '<span class="text-warning small">Could not get your location.</span>';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}
