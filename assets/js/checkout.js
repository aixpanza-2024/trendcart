/**
 * Checkout Page
 */

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }
    loadCheckoutData();
});

async function loadCheckoutData() {
    const { items, totals } = getCartForCheckout();

    // Redirect back to cart if empty
    if (!items.length) {
        window.location.href = 'cart.html';
        return;
    }

    // Render order summary items
    const orderItemsEl = document.getElementById('orderItems');
    if (orderItemsEl) {
        orderItemsEl.innerHTML = items.map(item => `
            <div class="order-item">
                <div>
                    <strong>${item.name}</strong>
                    ${item.size ? `<span class="badge bg-light text-dark border ms-1" style="font-size:11px;">${item.size}</span>` : ''}
                    <div class="text-grey small">${item.shop || ''}</div>
                </div>
                <div class="text-end">
                    <div>Qty: ${item.quantity}</div>
                    <strong>${formatCurrency(item.price * item.quantity)}</strong>
                </div>
            </div>`).join('');
    }

    // Update totals
    document.getElementById('orderSubtotal').textContent = formatCurrency(totals.subtotal);
    document.getElementById('orderTax').textContent      = formatCurrency(totals.tax);
    document.getElementById('orderShipping').innerHTML = '<s class="text-grey me-1">₹50</s><span class="text-success fw-bold">FREE</span>';
    document.getElementById('orderTotal').textContent    = formatCurrency(totals.total);

    // Pre-fill name + email from localStorage
    const user = getCurrentUser();
    if (user) {
        const names = (user.name || '').split(' ');
        document.getElementById('firstName').value = names[0] || '';
        document.getElementById('lastName').value  = names.slice(1).join(' ') || '';
        document.getElementById('email').value     = user.email || '';
    }

    // Pre-fill from localStorage saved address (instant, works offline)
    const saved = JSON.parse(localStorage.getItem('shippingAddress') || 'null');
    if (saved) {
        if (saved.phone)   document.getElementById('phone').value   = saved.phone;
        if (saved.address) document.getElementById('address').value = saved.address;
        if (saved.city)    document.getElementById('city').value    = saved.city;
        if (saved.pincode) document.getElementById('pincode').value = saved.pincode;
    }

    // Override with DB-saved default address from profile API (most up-to-date)
    try {
        const res  = await fetch('../api/customer/my-profile.php');
        const data = await res.json();
        if (data.success && data.data) {
            const p = data.data;
            if (p.phone)    document.getElementById('phone').value   = p.phone;
            if (p.default_address) {
                const a = p.default_address;
                if (a.address_line1) document.getElementById('address').value = a.address_line1;
                if (a.city)          document.getElementById('city').value    = a.city;
                if (a.pincode)       document.getElementById('pincode').value = a.pincode;
            }
        }
    } catch (e) { /* pre-fill is optional */ }
}

async function placeOrder() {
    const form = document.getElementById('checkoutForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const btn  = document.getElementById('placeOrderBtn');
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Placing Order...'; }

    const firstName = document.getElementById('firstName').value.trim();
    const lastName  = document.getElementById('lastName').value.trim();

    const shipping = {
        full_name: (firstName + ' ' + lastName).trim(),
        email:     document.getElementById('email').value.trim(),
        phone:     document.getElementById('phone').value.trim(),
        address:   document.getElementById('address').value.trim(),
        city:      document.getElementById('city').value.trim(),
        state:     document.getElementById('state').value.trim(),
        pincode:   document.getElementById('pincode').value.trim(),
    };

    const cart = getCart();
    const payload = {
        shipping,
        items:          cart.map(i => ({ id: i.id, quantity: i.quantity, size: i.size || null })),
        payment_method: 'cod',
    };

    try {
        const res  = await fetch('../api/customer/place-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            // Save address for next checkout (localStorage backup)
            localStorage.setItem('shippingAddress', JSON.stringify({
                phone:   shipping.phone,
                address: shipping.address,
                city:    shipping.city,
                pincode: shipping.pincode,
            }));
            // Clear cart from localStorage
            localStorage.removeItem('cart');
            updateCartBadge();
            showOrderSuccessOverlay(data.order_number);
        } else {
            showToast(data.message || 'Failed to place order', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        }

    } catch (err) {
        console.error('Place order error:', err);
        showToast('Failed to place order. Please try again.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}

function showOrderSuccessOverlay(orderNumber) {
    const overlay = document.getElementById('orderSuccessOverlay');
    const numEl   = document.getElementById('successOrderNum');
    if (numEl)    numEl.textContent = 'Order #' + orderNumber;
    if (overlay)  overlay.style.display = 'flex';
    // Redirect to orders page after animation completes
    setTimeout(() => { window.location.href = 'orders.html'; }, 3200);
}
