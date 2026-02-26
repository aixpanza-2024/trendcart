/**
 * Product Detail Page
 * - Loads product data from API
 * - Magnifying lens zoom on main image
 * - Thumbnail gallery
 * - Quantity selector
 * - Add to cart
 * - "More from this shop" grid
 */

let currentProduct = null;
let currentQty     = 1;
let selectedSize   = null;   // currently selected size label (null = none)

document.addEventListener('DOMContentLoaded', function () {
    const params    = new URLSearchParams(window.location.search);
    const productId = parseInt(params.get('id') || '0');

    if (!productId) {
        showError();
        return;
    }

    loadProduct(productId);
});

/* ================================================================
   LOAD PRODUCT
   ================================================================ */
async function loadProduct(id) {
    try {
        const res  = await fetch('../api/customer/product-detail.php?id=' + id);
        const data = await res.json();

        if (!data.success) { showError(); return; }

        currentProduct = data.data;
        renderProduct(currentProduct);
        renderReviews(currentProduct.reviews || []);
        loadMoreProducts(currentProduct.shop_id, currentProduct.product_id);

    } catch (e) {
        console.error('Product load error:', e);
        showError();
    }
}

/* ================================================================
   RENDER PRODUCT
   ================================================================ */
function renderProduct(p) {
    // Resolve image helper
    const imgUrl = url => '../' + (url || '').replace(/^\//, '');
    const placeholder = 'https://placehold.co/600x600/1a1a1a/ffffff?text=' + encodeURIComponent(p.product_name);

    // Primary image
    const primaryImg = p.images && p.images.length
        ? imgUrl(p.images[0].image_url)
        : placeholder;

    // ── Breadcrumb ───────────────────────────────────────────────
    const breadShopLink = document.getElementById('breadShopLink');
    const breadProduct  = document.getElementById('breadProductName');
    if (breadShopLink) {
        breadShopLink.innerHTML = `<a href="products.html?shop_id=${p.shop_id}">${p.shop_name}</a>`;
    }
    if (breadProduct) breadProduct.textContent = p.product_name;

    // ── Main image ───────────────────────────────────────────────
    const mainImg = document.getElementById('mainProductImg');
    mainImg.src = primaryImg;
    mainImg.alt = p.product_name;

    // ── Thumbnails ───────────────────────────────────────────────
    const thumbStrip = document.getElementById('thumbStrip');
    if (p.images && p.images.length > 1) {
        thumbStrip.innerHTML = p.images.map((img, i) => `
            <img src="${imgUrl(img.image_url)}"
                 alt="Image ${i + 1}"
                 class="${i === 0 ? 'active' : ''}"
                 onclick="switchImage(this, '${imgUrl(img.image_url)}')"
                 onerror="this.src='${placeholder}'">`
        ).join('');
    }

    // ── Badges ───────────────────────────────────────────────────
    const badgesEl = document.getElementById('productBadges');
    let badges = '';
    if (p.category_name) {
        badges += `<span class="badge bg-light text-dark border"><i class="fas fa-tag me-1"></i>${p.category_name}</span>`;
    }
    if (parseInt(p.is_featured)) {
        badges += `<span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Featured</span>`;
    }
    badgesEl.innerHTML = badges;

    // ── Title ────────────────────────────────────────────────────
    document.getElementById('productName').textContent = p.product_name;
    document.title = p.product_name + ' - TrenCart';

    // ── Price ────────────────────────────────────────────────────
    const price    = parseFloat(p.price);
    const origPrice = parseFloat(p.original_price || 0);
    const discount  = parseFloat(p.discount_percentage || 0);
    let priceHTML  = `<span class="product-detail-price">₹${price.toLocaleString()}</span>`;
    if (origPrice > price) {
        priceHTML += `<span class="product-detail-original ms-2">₹${origPrice.toLocaleString()}</span>`;
    }
    if (discount > 0) {
        priceHTML += `<span class="product-discount-pill ms-1">${Math.round(discount)}% off</span>`;
    }
    document.getElementById('productPriceRow').innerHTML = priceHTML;

    // ── Rating + orders ──────────────────────────────────────────
    const rating  = parseFloat(p.rating_average || 0).toFixed(1);
    const reviews = parseInt(p.total_ratings || 0);
    const orders  = parseInt(p.orders_count || 0);
    const stars   = buildStars(parseFloat(rating));
    document.getElementById('productMeta').innerHTML = `
        <span title="${rating} out of 5">
            ${stars}
            <span class="text-grey small ms-1">${rating} (${reviews} reviews)</span>
        </span>
        ${orders > 0 ? `<span class="text-grey small"><i class="fas fa-fire text-danger me-1"></i>${orders} sold</span>` : ''}
    `;

    // ── Description ──────────────────────────────────────────────
    if (p.product_description && p.product_description.trim()) {
        const descBlock = document.getElementById('productDescBlock');
        document.getElementById('productDescription').textContent = p.product_description.trim();
        descBlock.style.removeProperty('display');
    }

    // ── Shop info ────────────────────────────────────────────────
    const shopLogoHTML = p.shop_logo
        ? `<img src="${imgUrl(p.shop_logo)}" alt="${p.shop_name}" class="shop-logo"
               onerror="this.outerHTML='<div class=\\'shop-logo-placeholder\\'>${p.shop_name.charAt(0).toUpperCase()}</div>'">`
        : `<div class="shop-logo-placeholder">${p.shop_name.charAt(0).toUpperCase()}</div>`;

    document.getElementById('productShopBlock').innerHTML = `
        <h6 class="fw-semibold mb-2">Sold by</h6>
        <div class="shop-info-card">
            ${shopLogoHTML}
            <div class="flex-grow-1">
                <div class="fw-semibold">${p.shop_name}</div>
                ${p.shop_city ? `<div class="text-grey small"><i class="fas fa-map-marker-alt me-1"></i>${p.shop_city}</div>` : ''}
                <div class="text-grey small">
                    ${buildStars(parseFloat(p.rating_average || 0))}
                    <span class="ms-1">${parseFloat(p.rating_average || 0).toFixed(1)}</span>
                    &bull; ${p.total_products || 0} products
                </div>
            </div>
            <a href="products.html?shop_id=${p.shop_id}" class="btn btn-outline-secondary btn-sm">
                Visit <i class="fas fa-chevron-right ms-1"></i>
            </a>
        </div>`;

    // ── View shop button ─────────────────────────────────────────
    document.getElementById('viewShopBtn').href = `products.html?shop_id=${p.shop_id}`;

    // ── Size selector ────────────────────────────────────────────
    selectedSize = null;
    const sizeBlock = document.getElementById('sizeSelectBlock');
    const sizeBtns  = document.getElementById('sizeButtons');

    if (p.sizes && p.sizes.length > 0) {
        sizeBlock.style.display = '';
        sizeBtns.innerHTML = p.sizes.map(s => {
            const outOfStock = parseInt(s.stock_quantity) <= 0;
            const adj        = parseFloat(s.price_adjustment || 0);
            const adjLabel   = adj !== 0 ? `<small class="d-block" style="font-size:9px;margin-top:1px;">${adj > 0 ? '+' : ''}₹${adj}</small>` : '';
            return `<button type="button"
                        class="size-option-btn${outOfStock ? ' disabled' : ''}"
                        data-size="${s.size_label}"
                        data-price-adj="${adj}"
                        data-stock="${s.stock_quantity}"
                        ${outOfStock ? 'disabled title="Out of stock"' : `onclick="selectSize(this)"`}>
                        ${s.size_label}${adjLabel}
                        ${outOfStock ? '<small class="d-block text-danger" style="font-size:9px;">Out of stock</small>' : ''}
                    </button>`;
        }).join('');
    } else {
        sizeBlock.style.display = 'none';
    }

    // ── Add to cart button ───────────────────────────────────────
    document.getElementById('addToCartBtn').onclick = function () {
        const hasSizes = p.sizes && p.sizes.length > 0;

        // Size required if this product has size variants
        if (hasSizes && !selectedSize) {
            document.getElementById('sizeError').classList.remove('d-none');
            document.getElementById('sizeSelectBlock').scrollIntoView({ behavior: 'smooth', block: 'center' });
            showToast('Please select a size first', 'error');
            return;
        }

        // Compute effective price (base + price_adjustment for selected size)
        let effectivePrice = price;
        if (hasSizes && selectedSize) {
            const sv = p.sizes.find(s => s.size_label === selectedSize);
            if (sv) effectivePrice = price + parseFloat(sv.price_adjustment || 0);
        }

        addToCart(
            String(p.product_id),
            p.product_name,
            effectivePrice,
            primaryImg,
            p.shop_name,
            hasSizes ? selectedSize : null
        );

        // Visual feedback
        this.classList.add('btn-success');
        this.innerHTML = '<i class="fas fa-check me-2"></i>Added to Cart';
        setTimeout(() => {
            this.classList.remove('btn-success');
            this.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Add to Cart';
        }, 2000);
    };

    // ── Show content ─────────────────────────────────────────────
    document.getElementById('detailLoader').classList.add('d-none');
    document.getElementById('productDetailContent').classList.remove('d-none');

    // ── Init zoom after image loads ───────────────────────────────
    if (mainImg.complete) {
        initZoom();
    } else {
        mainImg.onload = initZoom;
    }
}

/* ================================================================
   QUANTITY CONTROLS
   ================================================================ */
function changeQty(delta) {
    currentQty = Math.max(1, currentQty + delta);
    document.getElementById('qtyDisplay').textContent = currentQty;
}

/* ================================================================
   SIZE SELECTION
   ================================================================ */
function selectSize(btn) {
    // Toggle off previous
    document.querySelectorAll('.size-option-btn').forEach(b => {
        b.classList.remove('active');
    });
    btn.classList.add('active');
    selectedSize = btn.dataset.size;

    // Hide error hint
    const errEl = document.getElementById('sizeError');
    if (errEl) errEl.classList.add('d-none');

    // Update displayed price if price adjustment exists
    const adj = parseFloat(btn.dataset.priceAdj || 0);
    if (adj !== 0 && currentProduct) {
        const base = parseFloat(currentProduct.price);
        const effective = base + adj;
        const orig = parseFloat(currentProduct.original_price || 0);
        const discount = parseFloat(currentProduct.discount_percentage || 0);
        let priceHTML = `<span class="product-detail-price">₹${effective.toLocaleString()}</span>`;
        if (orig > base) {
            priceHTML += `<span class="product-detail-original ms-2">₹${orig.toLocaleString()}</span>`;
        }
        if (discount > 0) {
            priceHTML += `<span class="product-discount-pill ms-1">${Math.round(discount)}% off</span>`;
        }
        if (adj > 0) {
            priceHTML += `<small class="text-muted ms-2">(+₹${adj} for ${selectedSize})</small>`;
        }
        document.getElementById('productPriceRow').innerHTML = priceHTML;
    }
}

/* ================================================================
   THUMBNAIL SWITCH
   ================================================================ */
function switchImage(thumbEl, newSrc) {
    document.getElementById('mainProductImg').src = newSrc;

    document.querySelectorAll('.thumb-strip img').forEach(t => t.classList.remove('active'));
    thumbEl.classList.add('active');

    // Re-init zoom for the new image
    const mainImg = document.getElementById('mainProductImg');
    if (mainImg.complete) {
        initZoom();
    } else {
        mainImg.onload = initZoom;
    }
}

/* ================================================================
   MAGNIFYING LENS ZOOM
   ================================================================ */
function initZoom() {
    const container = document.getElementById('zoomContainer');
    const mainImg   = document.getElementById('mainProductImg');
    const lens      = document.getElementById('zoomLens');
    const result    = document.getElementById('zoomResult');

    if (!container || !mainImg || !lens || !result) return;

    // Only enable on non-touch (desktop)
    if (window.matchMedia('(hover: none)').matches) return;

    const RESULT_W = 420;
    const RESULT_H = 420;

    container.addEventListener('mouseenter', () => {
        lens.style.display   = 'block';
        result.style.display = 'block';
    });

    container.addEventListener('mouseleave', () => {
        lens.style.display   = 'none';
        result.style.display = 'none';
    });

    container.addEventListener('mousemove', moveLens);

    function moveLens(e) {
        const rect = mainImg.getBoundingClientRect();

        // Cursor position relative to image
        let cursorX = e.clientX - rect.left;
        let cursorY = e.clientY - rect.top;

        // Lens position (centred on cursor, clamped to image bounds)
        let lx = cursorX - lens.offsetWidth  / 2;
        let ly = cursorY - lens.offsetHeight / 2;
        lx = Math.max(0, Math.min(lx, rect.width  - lens.offsetWidth));
        ly = Math.max(0, Math.min(ly, rect.height - lens.offsetHeight));

        lens.style.left = lx + 'px';
        lens.style.top  = ly + 'px';

        // Zoom ratio
        const cx = RESULT_W / lens.offsetWidth;
        const cy = RESULT_H / lens.offsetHeight;

        result.style.width           = RESULT_W + 'px';
        result.style.height          = RESULT_H + 'px';
        result.style.backgroundImage = `url('${mainImg.src}')`;
        result.style.backgroundSize  = `${rect.width * cx}px ${rect.height * cy}px`;
        result.style.backgroundPosition = `-${lx * cx}px -${ly * cy}px`;

        // Position result panel to the right of image (or left if near screen edge)
        const gap     = 12;
        let resLeft   = rect.right + gap;
        let resTop    = rect.top;

        if (resLeft + RESULT_W > window.innerWidth - 10) {
            resLeft = rect.left - RESULT_W - gap; // flip to left side
        }
        if (resTop + RESULT_H > window.innerHeight - 10) {
            resTop = window.innerHeight - RESULT_H - 10;
        }

        result.style.left = resLeft + 'px';
        result.style.top  = resTop  + 'px';
    }
}

/* ================================================================
   CUSTOMER REVIEWS
   ================================================================ */
function renderReviews(reviews) {
    const section   = document.getElementById('reviewsSection');
    const container = document.getElementById('reviewsContainer');
    const summary   = document.getElementById('reviewsSummary');
    if (!section || !container) return;

    if (!reviews || !reviews.length) {
        container.innerHTML = '<div class="text-center py-3 text-grey small">No reviews yet. Purchase and receive your order to be the first to review!</div>';
        section.style.display = '';
        return;
    }

    section.style.display = '';
    if (summary) summary.textContent = `${reviews.length} review${reviews.length !== 1 ? 's' : ''}`;

    container.innerHTML = reviews.map(r => {
        const initial = r.reviewer_name.charAt(0).toUpperCase();
        const date    = new Date(r.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        return `
            <div class="d-flex gap-3 mb-4 pb-3 border-bottom">
                <div class="flex-shrink-0">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:40px;height:40px;background:var(--primary-black);color:#fff;font-weight:700;font-size:1rem;">
                        ${initial}
                    </div>
                </div>
                <div>
                    <div class="fw-semibold">${r.reviewer_name}</div>
                    <div class="d-flex align-items-center gap-2">
                        <span>${buildStars(parseInt(r.rating))}</span>
                        <small class="text-grey">${date}</small>
                        <span class="badge bg-success" style="font-size:10px;">Verified Purchase</span>
                    </div>
                    ${r.review_text ? `<p class="text-grey small mt-1 mb-0">${r.review_text}</p>` : ''}
                </div>
            </div>`;
    }).join('');
}

/* ================================================================
   MORE FROM THIS SHOP
   ================================================================ */
async function loadMoreProducts(shopId, excludeId) {
    const section = document.getElementById('moreFromShopSection');
    const grid    = document.getElementById('moreProductsGrid');
    if (!section || !grid) return;

    try {
        const res  = await fetch(`../api/customer/products.php?shop_id=${shopId}&limit=60`);
        const data = await res.json();

        if (!data.success) return;

        // Exclude current product
        const others = (data.data || []).filter(p => p.product_id != excludeId);
        if (!others.length) return;

        section.style.display = '';

        // Update "more from shop" header
        if (currentProduct) {
            document.getElementById('moreShopName').textContent = currentProduct.shop_name;
            document.getElementById('viewAllShopLink').href = `products.html?shop_id=${shopId}`;
        }

        // Show up to 8 products
        grid.innerHTML = others.slice(0, 8).map(p => buildMoreCard(p)).join('');

    } catch (e) {
        console.error('More products error:', e);
    }
}

function buildMoreCard(p) {
    const img = p.primary_image
        ? '../' + p.primary_image.replace(/^\//, '')
        : 'https://placehold.co/400x180/1a1a1a/ffffff?text=' + encodeURIComponent(p.product_name);

    const discountBadge = parseFloat(p.discount_percentage || 0) > 0
        ? `<span class="product-discount-badge">${Math.round(p.discount_percentage)}% off</span>` : '';

    const originalPrice = parseFloat(p.original_price || 0) > parseFloat(p.price)
        ? `<small class="text-grey text-decoration-line-through ms-2">₹${parseFloat(p.original_price).toLocaleString()}</small>` : '';

    const safeName = p.product_name.replace(/'/g, "\\'");
    const safeShop = (p.shop_name || '').replace(/'/g, "\\'");
    const safeImg  = img.replace(/'/g, "\\'");

    return `
        <div class="col-6 col-md-4 col-lg-3">
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
                    <div class="card-price mb-2">
                        ₹${parseFloat(p.price).toLocaleString()}${originalPrice}
                    </div>
                    <button class="btn btn-primary btn-sm w-100"
                        onclick="quickAddToCart(this,'${p.product_id}','${safeName}',${p.price},'${safeImg}','${safeShop}')">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>`;
}

/* ================================================================
   HELPERS
   ================================================================ */
function buildStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (rating >= i)       html += '<i class="fas fa-star text-warning" style="font-size:12px;"></i>';
        else if (rating >= i - 0.5) html += '<i class="fas fa-star-half-alt text-warning" style="font-size:12px;"></i>';
        else                   html += '<i class="far fa-star text-warning" style="font-size:12px;"></i>';
    }
    return html;
}

function showError() {
    document.getElementById('detailLoader').classList.add('d-none');
    document.getElementById('detailError').classList.remove('d-none');
}
