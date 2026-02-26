/**
 * Products Page - Dynamic Products Listing with Filters
 */

let allProducts = [];
let allCategories = []; // full list (parents + children) for hierarchy-aware filtering
let activeFilters = {
    categories: [],
    maxPrice: 0,
    sort: 'featured',
    search: ''
};

document.addEventListener('DOMContentLoaded', function () {
    const urlParams  = new URLSearchParams(window.location.search);
    const shopId     = urlParams.get('shop_id');
    const categoryId = urlParams.get('category_id');
    const searchParam = urlParams.get('search');

    // Pre-fill search box if a ?search= param was passed (e.g. from navbar search)
    if (searchParam) {
        const searchEl = document.getElementById('productSearch');
        if (searchEl) searchEl.value = searchParam;
    }

    loadCategories(categoryId);
    loadProducts(shopId, categoryId);

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

async function loadProducts(shopId, categoryId) {
    const container = document.getElementById('productsGrid');
    if (!container) return;

    container.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const params = new URLSearchParams();
        if (shopId)     params.set('shop_id', shopId);
        if (categoryId) params.set('category_id', categoryId);

        const res  = await fetch('../api/customer/products.php?' + params);
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        // Update shop header
        if (data.shop_info) {
            // Shop-specific view
            const shop = data.shop_info;
            document.title = shop.shop_name + ' Products - TrenCart';

            const defaultHeader = document.getElementById('shopHeaderDefault');
            const shopCard      = document.getElementById('shopHeaderCard');
            if (defaultHeader) defaultHeader.style.display = 'none';
            if (shopCard)      shopCard.style.display = '';

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
                    <span class="text-grey small"><i class="fas fa-box me-1"></i><strong>${shop.total_products || 0}</strong> Products</span>
                    ${city}`;
            }
        } else {
            // All-products view (no shop_id)
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

    if (!products.length) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-grey mb-3"></i>
                <h5>No products found</h5>
                <p class="text-grey">Try adjusting your filters.</p>
            </div>`;
        return;
    }

    container.innerHTML = products.map(p => buildProductCard(p)).join('');
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

    return `
        <div class="col-md-6 col-lg-4">
            <div class="product-card">
                <a href="product-detail.html?id=${p.product_id}" class="d-block">
                    <div class="product-img-wrap">
                        <img src="${img}" alt="${p.product_name}"
                             onerror="this.src='https://placehold.co/400x180/1a1a1a/ffffff?text=Product'">
                        ${discountBadge}
                    </div>
                </a>
                <div class="card-body py-2 px-3">
                    <h6 class="card-title mb-1">
                        <a href="product-detail.html?id=${p.product_id}" class="text-decoration-none text-dark">
                            ${p.product_name}
                        </a>
                    </h6>
                    <div class="mb-1">${shopLine}${categoryLine}</div>
                    <div class="card-price mb-2">
                        ₹${parseFloat(p.price).toLocaleString()}${originalPrice}
                    </div>
                    <button class="btn btn-primary btn-sm w-100"
                        onclick="quickAddToCart(this, '${p.product_id}', '${safeName}', ${p.price}, '${safeImg}', '${safeShop}')">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>`;
}

async function loadCategories(activeCategoryId) {
    const container = document.getElementById('categoryFilters');
    if (!container) return;

    try {
        const res  = await fetch('../api/customer/categories.php');
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

    renderProducts(filtered);
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

    renderProducts(allProducts);
    showToast('Filters reset', 'success');
}
