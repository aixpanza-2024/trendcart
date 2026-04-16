/**
 * Checkout Page
 */

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }
    loadCheckoutData();
    loadPaymentMethods();
});

async function loadPaymentMethods() {
    const container = document.getElementById('paymentMethodsContainer');
    const placeBtn  = document.getElementById('placeOrderBtn');

    try {
        const res  = await fetch('../api/payment-settings.php');
        const data = await res.json();

        const codEnabled = data.cod_enabled === true;
        const anyEnabled = codEnabled;

        let html = '';

        if (codEnabled) {
            html += `
            <div class="payment-option selected" id="payment-cod" onclick="selectPayment('cod')">
                <input type="radio" name="payment" id="cod" value="cod" checked>
                <label for="cod" class="mb-0" style="cursor:pointer;">
                    <strong><i class="fas fa-money-bill-wave"></i> Cash on Delivery</strong>
                    <p class="text-grey small mb-0 mt-1">Pay when you receive your order</p>
                </label>
            </div>`;
        }

        // Placeholder future options (always shown as coming soon / disabled)
        html += `
        <div class="payment-option" style="opacity:0.5;cursor:not-allowed;">
            <input type="radio" name="payment" id="card" value="card" disabled>
            <label for="card" class="mb-0">
                <strong><i class="fas fa-credit-card"></i> Credit/Debit Card</strong>
                <p class="text-grey small mb-0 mt-1">Coming soon</p>
            </label>
        </div>
        <div class="payment-option" style="opacity:0.5;cursor:not-allowed;">
            <input type="radio" name="payment" id="upi" value="upi" disabled>
            <label for="upi" class="mb-0">
                <strong><i class="fas fa-mobile-alt"></i> UPI</strong>
                <p class="text-grey small mb-0 mt-1">Coming soon</p>
            </label>
        </div>`;

        if (!anyEnabled) {
            html = `<div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No payment method is currently available. Please check back later.
            </div>`;
            if (placeBtn) placeBtn.disabled = true;
        }

        container.innerHTML = html;

    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger mb-0">
            <i class="fas fa-times-circle me-2"></i> Failed to load payment options.
        </div>`;
        if (placeBtn) placeBtn.disabled = true;
    }
}

function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    const radio = document.getElementById(method);
    if (radio) {
        radio.checked = true;
        radio.closest('.payment-option').classList.add('selected');
    }
}

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
                    ${item.color ? `<span class="badge bg-light text-dark border ms-1" style="font-size:11px;"><i class="fas fa-palette me-1"></i>${item.color}</span>` : ''}
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
    document.getElementById('orderShipping').innerHTML = '<s class="text-grey me-1">₹40</s><span class="text-success fw-bold">FREE</span>';
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

    const selectedPayment = document.querySelector('input[name="payment"]:checked');
    if (!selectedPayment) {
        showToast('Please select a payment method.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        return;
    }

    const cart = getCart();
    const payload = {
        shipping,
        items:          cart.map(i => ({ id: i.id, quantity: i.quantity, size: i.size || null, color: i.color || null })),
        payment_method: selectedPayment.value,
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

            // If profile name is empty, silently save name+phone from shipping details
            const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
            if (!storedUser.full_name || storedUser.full_name.trim() === '') {
                try {
                    await fetch('../api/customer/my-profile.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({
                            full_name: shipping.full_name,
                            phone:     shipping.phone,
                        }),
                    });
                    // Update localStorage so navbar shows the real name immediately
                    storedUser.full_name = shipping.full_name;
                    localStorage.setItem('user', JSON.stringify(storedUser));
                    localStorage.setItem('userName', shipping.full_name.split(' ')[0] || shipping.full_name);
                } catch (e) { /* non-critical — profile can be filled later */ }
            }

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
