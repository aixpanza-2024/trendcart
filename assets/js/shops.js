/**
 * Shops Page - Dynamic listing with search, sort, rating, category, price, offers, top-sellers filters
 */

let allShops      = [];
let allCategories = [];

document.addEventListener('DOMContentLoaded', function () {
    loadCategories();
    loadShops();

    document.getElementById('shopSearch')?.addEventListener('input',  debounce(applyFilters, 300));
    document.getElementById('shopSort')?.addEventListener('change',   applyFilters);
    document.getElementById('shopRating')?.addEventListener('change', applyFilters);
    document.getElementById('shopCategory')?.addEventListener('change', applyFilters);
    document.getElementById('shopPrice')?.addEventListener('change',  applyFilters);

    // Toggle buttons - Offers & Top Sellers
    ['shopOffers', 'shopTopSellers'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', function () {
            const isActive = this.dataset.active === 'true';
            this.dataset.active = isActive ? 'false' : 'true';
            this.classList.toggle('btn-outline-secondary', isActive);
            this.classList.toggle('btn-primary', !isActive);
            applyFilters();
        });
    });
});

async function loadCategories() {
    try {
        const res  = await fetch('../api/customer/categories.php');
        const data = await res.json();
        if (!data.success) return;

        allCategories = data.data;
        const select  = document.getElementById('shopCategory');
        if (!select) return;

        // Show parent categories first, then children indented
        const parents  = allCategories.filter(c => !c.parent_category_id);
        const children = allCategories.filter(c =>  c.parent_category_id);

        parents.forEach(p => {
            const opt = document.createElement('option');
            opt.value       = p.category_id;
            opt.textContent = p.category_name;
            select.appendChild(opt);

            children.filter(c => c.parent_category_id == p.category_id).forEach(c => {
                const cOpt = document.createElement('option');
                cOpt.value       = c.category_id;
                cOpt.textContent = '\u00A0\u00A0\u2514 ' + c.category_name;
                select.appendChild(cOpt);
            });
        });
    } catch (e) {
        console.error('Load categories error:', e);
    }
}

async function loadShops() {
    const container = document.getElementById('shopsGrid');
    if (!container) return;

    container.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const res  = await fetch('../api/customer/shops.php?limit=100');
        const data = await res.json();

        if (!data.success || !data.data.length) {
            container.innerHTML = `
                <div class="col-12 text-center text-grey py-5">
                    <i class="fas fa-store fa-3x mb-3"></i>
                    <h5>No shops available yet.</h5>
                </div>`;
            return;
        }

        allShops = data.data;
        applyFilters();

    } catch (err) {
        console.error('Load shops error:', err);
        container.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load shops.</div>';
    }
}

function applyFilters() {
    const container = document.getElementById('shopsGrid');
    if (!container) return;

    const search      = (document.getElementById('shopSearch')?.value   || '').trim().toLowerCase();
    const sort        =  document.getElementById('shopSort')?.value      || 'featured';
    const minRating   = parseFloat(document.getElementById('shopRating')?.value   || '0');
    const categoryId  = parseInt(document.getElementById('shopCategory')?.value   || '0');
    const maxPrice    = parseInt(document.getElementById('shopPrice')?.value      || '0');
    const offersOnly  =  document.getElementById('shopOffers')?.dataset.active    === 'true';
    const topSellers  =  document.getElementById('shopTopSellers')?.dataset.active === 'true';

    let filtered = [...allShops];

    // --- Search (name, description, city) ---
    if (search) {
        filtered = filtered.filter(s =>
            s.shop_name.toLowerCase().includes(search) ||
            (s.shop_description || '').toLowerCase().includes(search) ||
            (s.shop_city || '').toLowerCase().includes(search)
        );
    }

    // --- Rating filter ---
    if (minRating > 0) {
        filtered = filtered.filter(s => parseFloat(s.rating_average || 0) >= minRating);
    }

    // --- Category filter (expand parent → children) ---
    if (categoryId > 0) {
        const matchIds = getCategoryIds(categoryId);
        filtered = filtered.filter(s => {
            if (!s.category_ids) return false;
            const shopCatIds = s.category_ids.split(',').map(Number);
            return shopCatIds.some(id => matchIds.includes(id));
        });
    }

    // --- Price filter (shop must have at least one product at or below max) ---
    if (maxPrice > 0) {
        filtered = filtered.filter(s => s.min_price !== null && parseFloat(s.min_price) <= maxPrice);
    }

    // --- Offers filter ---
    if (offersOnly) {
        filtered = filtered.filter(s => parseInt(s.has_offers) === 1);
    }

    // --- Sort ---
    if (topSellers) {
        // Top Sellers toggle: sort all shops by total_orders descending
        filtered.sort((a, b) => parseInt(b.total_orders || 0) - parseInt(a.total_orders || 0));
    } else if (sort !== 'featured') {
        filtered.sort((a, b) => {
            switch (sort) {
                case 'rating':
                    return parseFloat(b.rating_average || 0) - parseFloat(a.rating_average || 0);
                case 'products':
                    return parseInt(b.total_products || 0) - parseInt(a.total_products || 0);
                case 'newest':
                    return new Date(b.created_at) - new Date(a.created_at);
                case 'name':
                    return a.shop_name.localeCompare(b.shop_name);
                default:
                    return 0;
            }
        });
    }
    // 'featured' keeps the original DB order (total_products DESC, rating DESC)

    const countEl = document.getElementById('shopCount');
    if (countEl) countEl.textContent = filtered.length;

    if (!filtered.length) {
        container.innerHTML = `
            <div class="col-12 text-center text-grey py-5">
                <i class="fas fa-store fa-3x mb-3"></i>
                <h5>No shops found</h5>
                <p>Try adjusting your search or filters.</p>
            </div>`;
        return;
    }

    container.innerHTML = filtered.map(shop => buildShopCard(shop)).join('');
}

/**
 * Returns an array of category IDs including the given ID and all its children.
 */
function getCategoryIds(categoryId) {
    const ids = [categoryId];
    allCategories.forEach(c => {
        if (parseInt(c.parent_category_id) === categoryId) {
            ids.push(parseInt(c.category_id));
        }
    });
    return ids;
}

function buildShopCard(shop) {
    const img  = shop.shop_logo
        ? '../' + shop.shop_logo.replace(/^\//, '')
        : 'https://placehold.co/400x250/1a1a1a/ffffff?text=' + encodeURIComponent(shop.shop_name);
    const rating  = parseFloat(shop.rating_average || 0).toFixed(1);
    const reviews = parseInt(shop.total_ratings  || 0);
    const stars   = buildStars(parseFloat(rating));

    const offersBadge = parseInt(shop.has_offers) === 1
        ? `<span class="badge bg-warning text-dark" style="font-size:11px;"><i class="fas fa-tag me-1"></i>Offers</span>`
        : '';
    const topBadge = parseInt(shop.total_orders || 0) > 0
        ? `<span class="badge bg-success" style="font-size:11px;"><i class="fas fa-fire me-1"></i>Top Seller</span>`
        : '';
    const imgOverlay = (offersBadge || topBadge)
        ? `<div style="position:absolute;top:10px;left:10px;display:flex;flex-direction:column;gap:5px;">${offersBadge}${topBadge}</div>`
        : '';

    const desc = shop.shop_description
        ? `<p class="text-grey mb-2" style="font-size:12px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;">${shop.shop_description}</p>`
        : '';
    const cityIcon = shop.shop_city
        ? `<span><i class="fas fa-map-marker-alt me-1"></i>${shop.shop_city}</span>`
        : '';

    return `
        <div class="col-lg-4 col-md-6">
            <div class="shop-card">
                <div style="position:relative;">
                    <img src="${img}" alt="${shop.shop_name}"
                         onerror="this.src='https://placehold.co/400x250/1a1a1a/ffffff?text=${encodeURIComponent(shop.shop_name)}'">
                    ${imgOverlay}
                </div>
                <div class="card-body" style="padding:12px 14px;">
                    <h6 class="mb-1 fw-semibold" style="font-size:14px;line-height:1.3;">${shop.shop_name}</h6>
                    ${desc}
                    <div class="d-flex align-items-center gap-2 text-grey mb-2" style="font-size:12px;flex-wrap:wrap;">
                        <span title="${rating} out of 5">${stars} <span class="ms-1">${rating}</span></span>
                        <span class="text-muted">·</span>
                        <span><i class="fas fa-box me-1"></i>${shop.total_products || 0}</span>
                        ${cityIcon ? `<span class="text-muted">·</span>${cityIcon}` : ''}
                    </div>
                    <a href="products.html?shop_id=${shop.shop_id}" class="btn btn-primary btn-sm w-100">
                        View Products <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>`;
}

function buildStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (rating >= i) {
            html += '<i class="fas fa-star text-warning" style="font-size:13px;"></i>';
        } else if (rating >= i - 0.5) {
            html += '<i class="fas fa-star-half-alt text-warning" style="font-size:13px;"></i>';
        } else {
            html += '<i class="far fa-star text-warning" style="font-size:13px;"></i>';
        }
    }
    return html;
}

function resetShopFilters() {
    const el = id => document.getElementById(id);

    if (el('shopSearch'))     el('shopSearch').value     = '';
    if (el('shopSort'))       el('shopSort').value       = 'featured';
    if (el('shopRating'))     el('shopRating').value     = '0';
    if (el('shopCategory'))   el('shopCategory').value   = '0';
    if (el('shopPrice'))      el('shopPrice').value      = '0';

    ['shopOffers', 'shopTopSellers'].forEach(id => {
        const btn = el(id);
        if (!btn) return;
        btn.dataset.active = 'false';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
    });

    applyFilters();
}

function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}
