/**
 * Shop Products Management - JavaScript
 * Handles product listing, filtering, and actions
 */

document.addEventListener('DOMContentLoaded', function() {
    checkShopAuthentication();
    loadCategories();
    loadProducts();

    // Setup search listener
    document.getElementById('searchProduct').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            loadProducts();
        }
    });
});

/**
 * Check if user is authenticated as shop owner
 */
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
            return;
        }
    } catch (error) {
        console.error('Authentication check error:', error);
        window.location.href = '../pages/login.html';
    }
}

/**
 * Load categories for filter
 */
async function loadCategories() {
    try {
        const response = await fetch('../api/shop/categories.php');
        const result = await response.json();

        if (result.success && result.data) {
            const select = document.getElementById('filterCategory');
            result.data.forEach(category => {
                const option = document.createElement('option');
                option.value = category.category_id;
                option.textContent = category.category_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Categories load error:', error);
    }
}

/**
 * Load products
 */
async function loadProducts() {
    const search = document.getElementById('searchProduct').value;
    const status = document.getElementById('filterStatus').value;
    const category = document.getElementById('filterCategory').value;

    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (category) params.append('category', category);

    try {
        const response = await fetch('../api/shop/products.php?' + params.toString());
        const result = await response.json();

        if (result.success && result.data) {
            displayProducts(result.data);
        } else {
            showError('Failed to load products');
        }
    } catch (error) {
        console.error('Products load error:', error);
        showError('Failed to load products');
    }
}

/**
 * Display products
 */
function displayProducts(products) {
    const container = document.getElementById('productsList');
    const countElement = document.getElementById('productCount');

    countElement.textContent = products.length;

    if (products.length === 0) {
        container.innerHTML = `
            <div class="text-center text-grey py-5">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <p>No products found</p>
                <a href="add-product.html" class="btn btn-primary mt-2">
                    <i class="fas fa-plus"></i> Add Your First Product
                </a>
            </div>
        `;
        return;
    }

    let html = '';
    products.forEach(product => {
        const statusClass = product.product_status || 'active';
        const image = product.primary_image || '../assets/images/placeholder-product.jpg';
        const price = parseFloat(product.price) || 0;
        const stock = parseInt(product.stock_quantity) || 0;
        const threshold = parseInt(product.low_stock_threshold) || 10;

        // Stock badge: red = out-of-stock, orange = low stock, grey = normal
        let stockBadge;
        if (stock <= 0) {
            stockBadge = `<span class="badge bg-danger">Out of Stock</span>`;
        } else if (stock <= threshold) {
            stockBadge = `<span class="badge bg-warning text-dark">Low Stock: ${stock}</span>`;
        } else {
            stockBadge = `<span class="badge bg-secondary">Stock: ${stock}</span>`;
        }

        html += `
            <div class="product-item">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <img src="${image}" alt="${product.product_name}" class="product-image">
                    </div>
                    <div class="col">
                        <h6 class="mb-1">${product.product_name}</h6>
                        <p class="text-grey mb-1 small">${product.product_code || 'N/A'}</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="product-status-badge ${statusClass}">
                                ${formatStatus(statusClass)}
                            </span>
                            ${stockBadge}
                            <span class="badge bg-info">₹${price.toLocaleString('en-IN')}</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="editProduct(${product.product_id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteProduct(${product.product_id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="toggleProductStatus(${product.product_id}, '${statusClass}')" title="Toggle Status">
                                <i class="fas fa-toggle-on"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Format status text
 */
function formatStatus(status) {
    const statuses = {
        'active': 'Active',
        'inactive': 'Inactive',
        'out_of_stock': 'Out of Stock'
    };
    return statuses[status] || status;
}

/**
 * Edit product
 */
function editProduct(productId) {
    window.location.href = `add-product.html?id=${productId}`;
}

/**
 * Delete product
 */
async function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('../api/shop/delete-product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Product deleted successfully', 'success');
            loadProducts();
        } else {
            showToast(result.message || 'Failed to delete product', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Failed to delete product', 'error');
    }
}

/**
 * Toggle product status
 */
async function toggleProductStatus(productId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

    try {
        const response = await fetch('../api/shop/update-product-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                status: newStatus
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast(`Product ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully`, 'success');
            loadProducts();
        } else {
            showToast(result.message || 'Failed to update product status', 'error');
        }
    } catch (error) {
        console.error('Status update error:', error);
        showToast('Failed to update product status', 'error');
    }
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('productsList');
    container.innerHTML = `
        <div class="text-center text-danger py-5">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <p>${message}</p>
        </div>
    `;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
