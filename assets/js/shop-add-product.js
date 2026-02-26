/**
 * Shop Add/Edit Product - JavaScript
 * Handles product creation and editing with cascading categories
 */

let isEditMode = false;
let productId = null;
let uploadedImages = [];
let allCategories = []; // cache for cascade logic

// Size variants: array of { size_label, stock_quantity, price_adjustment, display_order }
let sizeVariants = [];

document.addEventListener('DOMContentLoaded', function () {
    checkShopAuthentication();
    loadCategories();
    checkEditMode();
    setupImageUpload();
    setupFormSubmit();
    setupSizeToggles();
});

/* --- Auth --- */
async function checkShopAuthentication() {
    try {
        const response = await fetch('../api/check-auth.php');
        const result = await response.json();
        if (!result.success || !result.data.logged_in) {
            window.location.href = '../pages/login.html';
            return;
        }
        if (result.data.user.user_type !== 'shop') {
            alert('Access denied. This page is for shop owners only.');
            window.location.href = '../index.html';
        }
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = '../pages/login.html';
    }
}

/* --- Categories (cascading) --- */
async function loadCategories() {
    try {
        const response = await fetch('../api/shop/categories.php');
        const result = await response.json();
        if (!result.success || !result.data) return;

        allCategories = result.data;

        const parents = allCategories.filter(c => !c.parent_category_id);
        const parentSel = document.getElementById('parentCategory');

        parents.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.category_id;
            opt.textContent = c.category_name;
            parentSel.appendChild(opt);
        });

        parentSel.addEventListener('change', handleParentChange);
    } catch (error) {
        console.error('Categories load error:', error);
    }
}

function handleParentChange() {
    const parentId = document.getElementById('parentCategory').value;
    const subSel = document.getElementById('subCategory');

    subSel.innerHTML = '<option value="">— None —</option>';

    if (!parentId) {
        subSel.disabled = true;
        return;
    }

    const children = allCategories.filter(c => String(c.parent_category_id) === String(parentId));

    if (children.length === 0) {
        subSel.disabled = true;
    } else {
        children.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.category_id;
            opt.textContent = c.category_name;
            subSel.appendChild(opt);
        });
        subSel.disabled = false;
    }
}

/** Returns the effective category_id: subcategory if chosen, else parent */
function getSelectedCategoryId() {
    const sub = document.getElementById('subCategory').value;
    const parent = document.getElementById('parentCategory').value;
    return sub || parent;
}

/** Sets the category dropdowns based on a category_id (used in edit mode) */
function setCategorySelects(categoryId) {
    if (!categoryId) return;

    const cat = allCategories.find(c => String(c.category_id) === String(categoryId));
    if (!cat) return;

    if (!cat.parent_category_id) {
        document.getElementById('parentCategory').value = categoryId;
        handleParentChange();
    } else {
        document.getElementById('parentCategory').value = cat.parent_category_id;
        handleParentChange();
        document.getElementById('subCategory').value = categoryId;
    }
}

/* --- Edit Mode --- */
function checkEditMode() {
    const urlParams = new URLSearchParams(window.location.search);
    productId = urlParams.get('id');

    if (productId) {
        isEditMode = true;
        document.getElementById('pageTitle').textContent = 'Edit Product';
        document.getElementById('submitBtnText').textContent = 'Update Product';
        document.getElementById('productId').value = productId;
        loadProductData(productId);
    }
}

async function loadProductData(id) {
    try {
        const response = await fetch(`../api/shop/get-product.php?id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const product = result.data;

            document.getElementById('productName').value = product.product_name || '';
            document.getElementById('productCode').value = product.product_code || '';
            document.getElementById('productDescription').value = product.product_description || '';
            document.getElementById('productStatus').value = product.product_status || 'active';

            setCategorySelects(product.category_id);

            document.getElementById('price').value = product.price || '';
            document.getElementById('originalPrice').value = product.original_price || '';
            document.getElementById('discount').value = product.discount_percentage || '';
            document.getElementById('stockQuantity').value = product.stock_quantity || '';
            document.getElementById('lowStockThreshold').value = product.low_stock_threshold || '';

            document.getElementById('color').value = product.color || '';
            document.getElementById('size').value = product.size || '';
            document.getElementById('material').value = product.material || '';
            document.getElementById('fabricType').value = product.fabric_type || '';
            document.getElementById('pattern').value = product.pattern || '';
            document.getElementById('weight').value = product.weight || '';
            document.getElementById('length').value = product.length || '';
            document.getElementById('width').value = product.width || '';

            // Load sizes
            loadSizesFromData(product.sizes || []);

            if (product.images && product.images.length > 0) {
                displayExistingImages(product.images);
            }
        } else {
            showToast('Product not found', 'error');
            setTimeout(() => { window.location.href = 'products.html'; }, 2000);
        }
    } catch (error) {
        console.error('Load product error:', error);
        showToast('Failed to load product data', 'error');
    }
}

/* --- Images --- */
function displayExistingImages(images) {
    const container = document.getElementById('imagePreviewContainer');
    images.forEach((image, index) => {
        const div = document.createElement('div');
        div.className = 'image-preview';
        div.innerHTML = `
            <img src="${image.image_url}" alt="Product Image ${index + 1}">
            ${image.is_primary ? '<span class="primary-badge">Primary</span>' : ''}
            <button type="button" class="remove-image" onclick="removeExistingImage(${image.image_id})">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(div);
    });
}

function setupImageUpload() {
    const input = document.getElementById('productImages');
    input.addEventListener('change', function (e) {
        Array.from(e.target.files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    uploadedImages.push({ file, dataUrl: event.target.result, isPrimary: uploadedImages.length === 0 });
                    displayImagePreview(event.target.result, uploadedImages.length - 1);
                };
                reader.readAsDataURL(file);
            }
        });
        input.value = '';
    });
}

function displayImagePreview(dataUrl, index) {
    const container = document.getElementById('imagePreviewContainer');
    const div = document.createElement('div');
    div.className = 'image-preview';
    div.setAttribute('data-index', index);
    div.innerHTML = `
        <img src="${dataUrl}" alt="Product Image">
        ${index === 0 ? '<span class="primary-badge">Primary</span>' : ''}
        <button type="button" class="remove-image" onclick="removeImage(${index})">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

function removeImage(index) {
    uploadedImages.splice(index, 1);
    const container = document.getElementById('imagePreviewContainer');
    container.innerHTML = '';
    uploadedImages.forEach((img, i) => {
        img.isPrimary = i === 0;
        displayImagePreview(img.dataUrl, i);
    });
}

async function removeExistingImage(imageId) {
    if (!confirm('Remove this image?')) return;
    try {
        const response = await fetch('../api/shop/delete-image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_id: imageId })
        });
        const result = await response.json();
        if (result.success) {
            showToast('Image removed', 'success');
            loadProductData(productId);
        } else {
            showToast(result.message || 'Failed to remove image', 'error');
        }
    } catch (error) {
        showToast('Failed to remove image', 'error');
    }
}

/* --- Form Submit --- */
function setupFormSubmit() {
    document.getElementById('productForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;

        if (!validateForm()) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const formData = new FormData();
        if (isEditMode) formData.append('product_id', productId);

        formData.append('product_name', document.getElementById('productName').value.trim());
        formData.append('product_code', document.getElementById('productCode').value.trim());
        formData.append('product_description', document.getElementById('productDescription').value.trim());
        formData.append('category_id', getSelectedCategoryId());
        formData.append('product_status', document.getElementById('productStatus').value);

        formData.append('price', document.getElementById('price').value);
        formData.append('original_price', document.getElementById('originalPrice').value || '0');
        formData.append('discount_percentage', document.getElementById('discount').value || '0');
        formData.append('stock_quantity', document.getElementById('stockQuantity').value);
        formData.append('low_stock_threshold', document.getElementById('lowStockThreshold').value || '10');

        formData.append('color', document.getElementById('color').value.trim());
        formData.append('material', document.getElementById('material').value.trim());
        formData.append('fabric_type', document.getElementById('fabricType').value.trim());
        formData.append('pattern', document.getElementById('pattern').value.trim());
        formData.append('weight', document.getElementById('weight').value || '0');
        formData.append('length', document.getElementById('length').value || '0');
        formData.append('width', document.getElementById('width').value || '0');

        // Ensure sizesJson is up-to-date then include
        syncSizesJson();
        formData.append('sizes_json', document.getElementById('sizesJson').value || '[]');

        uploadedImages.forEach(img => {
            formData.append('images[]', img.file);
            formData.append('is_primary[]', img.isPrimary ? '1' : '0');
        });

        try {
            const url = isEditMode ? '../api/shop/update-product.php' : '../api/shop/create-product.php';
            const response = await fetch(url, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                showToast(isEditMode ? 'Product updated!' : 'Product added!', 'success');
                setTimeout(() => { window.location.href = 'products.html'; }, 2000);
            } else {
                showToast(result.message || 'Failed to save product', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Submit error:', error);
            showToast('Failed to save product', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

/* --- Validation --- */
function validateForm() {
    if (!document.getElementById('productName').value.trim()) {
        showToast('Please enter product name', 'error');
        return false;
    }
    if (!getSelectedCategoryId()) {
        showToast('Please select a category', 'error');
        return false;
    }
    const price = document.getElementById('price').value;
    if (!price || parseFloat(price) <= 0) {
        showToast('Please enter a valid price', 'error');
        return false;
    }
    const stock = document.getElementById('stockQuantity').value;
    if (stock === '' || parseInt(stock) < 0) {
        showToast('Please enter a valid stock quantity', 'error');
        return false;
    }
    return true;
}

/* --- Category Request --- */
function openRequestCategoryModal() {
    document.getElementById('reqCategoryName').value = '';
    document.getElementById('reqParentName').value = '';
    document.getElementById('reqNote').value = '';
    new bootstrap.Modal(document.getElementById('requestCategoryModal')).show();
}

async function submitCategoryRequest() {
    const name = document.getElementById('reqCategoryName').value.trim();
    if (!name) {
        showToast('Please enter a category name', 'error');
        return;
    }

    const btn = document.getElementById('submitRequestBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
        const res = await fetch('../api/shop/request-category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                category_name: name,
                parent_name:   document.getElementById('reqParentName').value.trim(),
                note:          document.getElementById('reqNote').value.trim()
            })
        });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('requestCategoryModal')).hide();
            showToast('Request sent! Admin will review it shortly.', 'success');
        } else {
            showToast(data.message || 'Failed to send request', 'error');
        }
    } catch (e) {
        showToast('Failed to send request', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = orig;
}

/* ══════════════════════════════════════════════════
   SIZE VARIANTS
   ══════════════════════════════════════════════════ */

function setupSizeToggles() {
    document.querySelectorAll('.size-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const label = this.dataset.size;
            if (this.classList.contains('selected')) {
                // Deselect — remove from list
                removeSize(label);
            } else {
                // Select — add to list
                addSizeVariant(label);
            }
        });
    });
}

/** Add a size (from standard buttons or custom input) */
function addSizeVariant(label) {
    label = label.trim();
    if (!label) return;

    // Prevent duplicates
    if (sizeVariants.find(s => s.size_label.toLowerCase() === label.toLowerCase())) {
        showToast(`Size "${label}" is already added`, 'error');
        return;
    }

    sizeVariants.push({
        size_label: label,
        stock_quantity: 0,
        price_adjustment: 0,
        display_order: sizeVariants.length
    });

    // Mark toggle button as selected (if standard)
    const btn = document.querySelector(`.size-toggle-btn[data-size="${label}"]`);
    if (btn) btn.classList.add('selected');

    renderSizeVariants();
    syncSizesJson();
}

/** Called by the custom size input button */
function addCustomSize() {
    const input = document.getElementById('customSizeInput');
    const label = (input.value || '').trim();
    if (!label) { showToast('Enter a size label', 'error'); return; }
    addSizeVariant(label);
    input.value = '';
}

/** Remove a size by label */
function removeSize(label) {
    sizeVariants = sizeVariants.filter(s => s.size_label !== label);

    // Un-mark toggle button (if standard)
    const btn = document.querySelector(`.size-toggle-btn[data-size="${label}"]`);
    if (btn) btn.classList.remove('selected');

    renderSizeVariants();
    syncSizesJson();
}

/** Re-draw the size rows list */
function renderSizeVariants() {
    const list   = document.getElementById('sizeVariantsList');
    const noMsg  = document.getElementById('noSizesMsg');

    if (sizeVariants.length === 0) {
        list.innerHTML = '<p class="text-muted small" id="noSizesMsg">No sizes added yet. Click the size buttons above or enter a custom size.</p>';
        return;
    }

    list.innerHTML = `
        <div class="row g-1 mb-1 px-2">
            <div class="col-3"><small class="fw-semibold text-muted">Size</small></div>
            <div class="col-4"><small class="fw-semibold text-muted">Stock Qty</small></div>
            <div class="col-4"><small class="fw-semibold text-muted">Price Adj (₹)</small></div>
            <div class="col-1"></div>
        </div>
        ${sizeVariants.map((s, i) => `
        <div class="size-row" data-index="${i}">
            <div class="size-label-badge col-3">${s.size_label}</div>
            <input type="number" class="form-control form-control-sm size-stock-input col-4"
                   value="${s.stock_quantity}" min="0" placeholder="0"
                   onchange="updateSizeField(${i},'stock_quantity', this.value)"
                   oninput="updateSizeField(${i},'stock_quantity', this.value)">
            <input type="number" class="form-control form-control-sm col-4"
                   value="${s.price_adjustment}" step="0.01" placeholder="0"
                   title="Add to base price (can be negative)"
                   onchange="updateSizeField(${i},'price_adjustment', this.value)"
                   oninput="updateSizeField(${i},'price_adjustment', this.value)">
            <button type="button" class="remove-size" onclick="removeSize('${s.size_label}')" title="Remove size">
                <i class="fas fa-times-circle"></i>
            </button>
        </div>`).join('')}
        <small class="text-muted">Price Adj: extra ₹ added to base price for this size (use 0 if same price for all sizes).</small>
    `;
}

/** Update a field on a size variant (called from inputs) */
function updateSizeField(index, field, value) {
    if (!sizeVariants[index]) return;
    sizeVariants[index][field] = field === 'stock_quantity' ? (parseInt(value) || 0) : (parseFloat(value) || 0);
    syncSizesJson();
}

/** Write current sizeVariants to the hidden JSON input */
function syncSizesJson() {
    const input = document.getElementById('sizesJson');
    if (input) input.value = JSON.stringify(sizeVariants);
}

/** Load sizes from API data (edit mode) */
function loadSizesFromData(sizes) {
    sizeVariants = [];
    // Reset all toggle buttons
    document.querySelectorAll('.size-toggle-btn').forEach(b => b.classList.remove('selected'));

    if (!sizes || !sizes.length) {
        renderSizeVariants();
        syncSizesJson();
        return;
    }

    sizes.forEach((s, i) => {
        sizeVariants.push({
            size_label:       s.size_label,
            stock_quantity:   parseInt(s.stock_quantity)   || 0,
            price_adjustment: parseFloat(s.price_adjustment) || 0,
            display_order:    i
        });
        const btn = document.querySelector(`.size-toggle-btn[data-size="${s.size_label}"]`);
        if (btn) btn.classList.add('selected');
    });

    renderSizeVariants();
    syncSizesJson();
}

// showToast is provided globally by main.js
