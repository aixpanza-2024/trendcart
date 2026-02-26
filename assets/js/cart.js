/* ===================================
   TRENCART - Shopping Cart JavaScript
   Cart management and functionality
   =================================== */

/* ===================================
   CART KEY HELPER
   Each cart slot is uniquely identified by productId + size.
   Same product in different sizes = different cart entries.
   =================================== */
function makeCartKey(productId, size) {
    return String(productId) + '__' + (size || '');
}

/* ===================================
   ADD TO CART
   =================================== */
function addToCart(productId, productName, productPrice, productImage, shopName, size) {
    // Check if user is logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (!isLoggedIn) {
        showToast('Please login to add items to cart', 'error');
        setTimeout(() => {
            const loginPath = window.location.pathname.includes('/pages/') ? 'login.html' : 'pages/login.html';
            window.location.href = loginPath;
        }, 1500);
        return;
    }

    const cartKey = makeCartKey(productId, size);

    // Get current cart
    let cart = getCart();

    // Check if same product+size already in cart
    const existingItemIndex = cart.findIndex(item => item.cartKey === cartKey);

    if (existingItemIndex > -1) {
        // Increment quantity if item exists
        cart[existingItemIndex].quantity += 1;
        showToast('Item quantity updated in cart', 'success');
    } else {
        // Add new item to cart
        const newItem = {
            cartKey,
            id: productId,
            size: size || null,
            name: productName,
            price: parseFloat(productPrice),
            image: productImage,
            shop: shopName,
            quantity: 1
        };
        cart.push(newItem);
        showToast('Item added to cart successfully', 'success');
    }

    // Save cart to localStorage
    saveCart(cart);

    // Update cart badge
    updateCartBadge();
}

/* ===================================
   REMOVE FROM CART
   =================================== */
function removeFromCart(cartKey) {
    let cart = getCart();

    // Support legacy carts that used plain productId as key
    cart = cart.filter(item => (item.cartKey || item.id) !== cartKey);

    // Save updated cart
    saveCart(cart);

    // Update UI
    updateCartBadge();
    renderCartItems();

    showToast('Item removed from cart', 'success');
}

/* ===================================
   UPDATE QUANTITY
   =================================== */
function updateQuantity(cartKey, change) {
    let cart = getCart();

    // Support legacy carts that used plain productId as key
    const itemIndex = cart.findIndex(item => (item.cartKey || item.id) === cartKey);

    if (itemIndex > -1) {
        cart[itemIndex].quantity += change;

        // Remove item if quantity is 0 or less
        if (cart[itemIndex].quantity <= 0) {
            removeFromCart(cartKey);
            return;
        }

        // Save updated cart
        saveCart(cart);

        // Update UI
        updateCartBadge();
        renderCartItems();
    }
}

/* ===================================
   GET CART
   =================================== */
function getCart() {
    const cart = localStorage.getItem('cart');
    return cart ? JSON.parse(cart) : [];
}

/* ===================================
   SAVE CART
   =================================== */
function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
}

/* ===================================
   CLEAR CART
   =================================== */
function clearCart() {
    localStorage.removeItem('cart');
    updateCartBadge();
    renderCartItems();
    showToast('Cart cleared', 'success');
}

/* ===================================
   CALCULATE CART TOTALS
   =================================== */
function calculateCartTotals() {
    const cart = getCart();

    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });

    const tax = subtotal * 0.18; // 18% GST
    const shipping = 0; // Free shipping always (was ₹50)
    const total = subtotal + tax + shipping;

    return {
        subtotal: subtotal,
        tax: tax,
        shipping: shipping,
        total: total,
        itemCount: cart.reduce((sum, item) => sum + item.quantity, 0)
    };
}

/* ===================================
   RENDER CART ITEMS (for cart.html)
   =================================== */
function renderCartItems() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cart = getCart();

    if (!cartItemsContainer) return;

    // Clear existing items
    cartItemsContainer.innerHTML = '';

    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-grey mb-3"></i>
                <h4>Your cart is empty</h4>
                <p class="text-grey">Start shopping to add items to your cart</p>
                <a href="../index.html" class="btn btn-primary mt-3">Continue Shopping</a>
            </div>
        `;
        updateCartSummary();
        return;
    }

    // Render each cart item as a grid card
    cart.forEach(item => {
        // Ensure cartKey exists even for legacy items
        const key = item.cartKey || makeCartKey(item.id, item.size);
        const sizeTag = item.size
            ? `<span class="badge bg-light text-dark border me-1"><i class="fas fa-ruler-combined me-1"></i>${item.size}</span>`
            : '';
        const itemHTML = `
            <div class="col-12 col-md-6">
                <div class="cart-item" data-cart-key="${key}">
                    <div class="cart-item-top">
                        <img src="${item.image}" alt="${item.name}">
                        <div class="cart-item-details">
                            <h5 class="cart-item-title">${item.name}</h5>
                            <p class="cart-item-shop mb-1"><i class="fas fa-store"></i> ${item.shop}</p>
                            ${sizeTag}
                            <p class="cart-item-price fw-bold mb-0 mt-1">${formatCurrency(item.price)}</p>
                        </div>
                    </div>
                    <div class="cart-item-bottom">
                        <div class="quantity-controls">
                            <button onclick="updateQuantity('${key}', -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button onclick="updateQuantity('${key}', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <strong class="cart-item-subtotal ms-auto">${formatCurrency(item.price * item.quantity)}</strong>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart('${key}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        cartItemsContainer.innerHTML += itemHTML;
    });

    // Update cart summary
    updateCartSummary();
}

/* ===================================
   UPDATE CART SUMMARY (for cart.html)
   =================================== */
function updateCartSummary() {
    const totals = calculateCartTotals();

    const subtotalEl = document.getElementById('cartSubtotal');
    const taxEl = document.getElementById('cartTax');
    const shippingEl = document.getElementById('cartShipping');
    const totalEl = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');

    if (subtotalEl) subtotalEl.textContent = formatCurrency(totals.subtotal);
    if (taxEl) taxEl.textContent = formatCurrency(totals.tax);
    if (shippingEl) {
        shippingEl.innerHTML = '<s class="text-grey me-1">₹50</s><span class="text-success fw-bold">FREE</span>';
    }
    if (totalEl) totalEl.textContent = formatCurrency(totals.total);

    // Disable checkout button if cart is empty
    if (checkoutBtn) {
        if (totals.itemCount === 0) {
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Cart is Empty';
        } else {
            checkoutBtn.disabled = false;
            checkoutBtn.textContent = `Proceed to Checkout (${totals.itemCount} items)`;
        }
    }
}

/* ===================================
   PROCEED TO CHECKOUT
   =================================== */
function proceedToCheckout() {
    const cart = getCart();

    if (cart.length === 0) {
        showToast('Your cart is empty', 'error');
        return;
    }

    // Check if user is logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (!isLoggedIn) {
        showToast('Please login to continue', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }

    // Redirect to checkout page
    window.location.href = 'checkout.html';
}

/* ===================================
   INITIALIZE CART PAGE
   =================================== */
if (window.location.pathname.includes('cart.html')) {
    document.addEventListener('DOMContentLoaded', function() {
        renderCartItems();
    });
}

/* ===================================
   QUICK ADD TO CART (with animation)
   =================================== */
function quickAddToCart(button, productId, productName, productPrice, productImage, shopName) {
    // Add loading state to button
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    // Simulate API delay (remove when integrating with backend)
    setTimeout(() => {
        // size = null for quick-add (no size selection prompt available here)
        addToCart(productId, productName, productPrice, productImage, shopName, null);

        // Reset button
        button.disabled = false;
        button.innerHTML = originalHTML;

        // Add success animation
        button.classList.add('btn-success');
        button.innerHTML = '<i class="fas fa-check"></i> Added';

        setTimeout(() => {
            button.classList.remove('btn-success');
            button.innerHTML = originalHTML;
        }, 2000);
    }, 500);
}

/* ===================================
   EXPORT FUNCTIONS FOR CHECKOUT
   =================================== */
function getCartForCheckout() {
    return {
        items: getCart(),
        totals: calculateCartTotals()
    };
}
