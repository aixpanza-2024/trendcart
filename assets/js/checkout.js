/**
 * Checkout Page
 */

let currentDeliveryFee  = 0;
let currentDeliveryZone = '';
let currentHandlingFee  = 0;
let isFirstOrder        = false;
let firstOrderFreeEnabled = false;
let deliveryCheckTimer  = null;
let _profileFromDB      = null;
let appliedCoupon       = null; // { code, discount_amount, description } or null
let customerLat         = null; // set once real GPS coordinates are known (this page or a prior "use my location")
let customerLng         = null;

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    // Reuse coordinates captured earlier (e.g. the "use my location" button
    // on a shop page) so the customer isn't asked for permission twice
    const savedLat = localStorage.getItem('customerLat');
    const savedLng = localStorage.getItem('customerLng');
    if (savedLat && savedLng) {
        customerLat = parseFloat(savedLat);
        customerLng = parseFloat(savedLng);
    }

    loadCheckoutConfig();
    loadCheckoutData();
    loadPaymentMethods();
});

function useCurrentLocationForCheckout() {
    const btn = document.getElementById('useLocationBtnCheckout');
    if (!navigator.geolocation) {
        showToast('Your browser does not support location detection.', 'error');
        return;
    }

    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Locating...';

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            customerLat = pos.coords.latitude;
            customerLng = pos.coords.longitude;
            localStorage.setItem('customerLat', customerLat);
            localStorage.setItem('customerLng', customerLng);

            fetch('../api/customer/update-location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ latitude: customerLat, longitude: customerLng }),
            }).catch(() => {});

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Location set';
            showToast('Location set — delivery fee updated', 'success');

            const pincode = document.getElementById('pincode').value.trim();
            if (pincode.length === 6) checkDeliveryZone(pincode);
        },
        (err) => {
            btn.disabled = false;
            btn.innerHTML = orig;
            showToast(err.code === err.PERMISSION_DENIED
                ? 'Location permission denied — your pincode will be used instead'
                : 'Could not get your location', 'error');
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

async function loadCheckoutConfig() {
    try {
        const res  = await fetch('../api/customer/checkout-config.php');
        const data = await res.json();
        if (!data.success) return;

        currentHandlingFee    = data.handling_fee || 0;
        isFirstOrder          = data.is_first_order || false;
        firstOrderFreeEnabled = data.first_order_free_delivery || false;

        // If first order and free delivery enabled, show banner
        if (isFirstOrder && firstOrderFreeEnabled) {
            const badge = document.getElementById('deliveryZoneBadge');
            if (badge) {
                badge.innerHTML = `<span class="badge bg-success px-3 py-2" style="font-size:13px;">
                    <i class="fas fa-gift me-1"></i> First Order — Free Delivery!
                </span>`;
                badge.style.display = 'block';
            }
            currentDeliveryFee = 0;
        }

        updateCheckoutTotals();
    } catch (e) { /* silent */ }
}

function onPincodeInput(val) {
    clearTimeout(deliveryCheckTimer);
    if (val.length === 6) {
        deliveryCheckTimer = setTimeout(() => checkDeliveryZone(val), 400);
    } else {
        document.getElementById('deliveryZoneBadge').style.display = 'none';
    }
}

async function checkDeliveryZone(pincode) {
    try {
        const cart   = getCart();
        const shopId = cart.length > 0 ? (cart[0].shop_id || '') : '';
        let   url    = `../api/customer/check-delivery.php?pincode=${pincode}&shop_id=${shopId}`;
        if (customerLat !== null && customerLng !== null) {
            url += `&latitude=${customerLat}&longitude=${customerLng}`;
        }
        const res    = await fetch(url);
        const data   = await res.json();
        if (!data.success) return;

        // 'distance' responses carry distance_km instead of zone_name/area_name
        const locationLabel = data.method === 'distance' ? `${data.distance_km}km away` : data.zone_name;
        currentDeliveryZone = locationLabel;

        // First order overrides delivery fee to 0 regardless of zone
        if (isFirstOrder && firstOrderFreeEnabled) {
            currentDeliveryFee = 0;
            const badge = document.getElementById('deliveryZoneBadge');
            badge.innerHTML = `<span class="badge bg-success px-3 py-2" style="font-size:13px;">
                <i class="fas fa-gift me-1"></i> First Order — Free Delivery!
                <span class="opacity-75 ms-1">(${locationLabel})</span>
            </span>`;
            badge.style.display = 'block';
        } else {
            currentDeliveryFee = data.delivery_fee;
            const badge = document.getElementById('deliveryZoneBadge');
            if (data.is_free) {
                badge.innerHTML = `<span class="badge bg-success px-3 py-2" style="font-size:13px;">
                    <i class="fas fa-check-circle me-1"></i> Free Delivery — ${locationLabel}
                    ${data.area_name ? `<span class="opacity-75 ms-1">(${data.area_name})</span>` : ''}
                </span>`;
            } else {
                badge.innerHTML = `<span class="badge bg-warning text-dark px-3 py-2" style="font-size:13px;">
                    <i class="fas fa-truck me-1"></i> Delivery: ₹${data.delivery_fee} — ${locationLabel}
                </span>`;
            }
            badge.style.display = 'block';
        }

        updateCheckoutTotals();
    } catch (e) { /* silent */ }
}

function updateCheckoutTotals() {
    const { totals } = getCartForCheckout();
    const deliveryFee    = (isFirstOrder && firstOrderFreeEnabled) ? 0 : currentDeliveryFee;
    const discountAmount = appliedCoupon ? appliedCoupon.discount_amount : 0;
    const total = Math.max(0, totals.subtotal + deliveryFee + currentHandlingFee - discountAmount);

    document.getElementById('orderSubtotal').textContent = formatCurrency(totals.subtotal);

    const discountRow = document.getElementById('discountRow');
    if (discountRow) {
        if (appliedCoupon) {
            discountRow.style.display = '';
            document.getElementById('orderDiscount').textContent = '-' + formatCurrency(discountAmount);
            document.getElementById('appliedCouponLabel').textContent = '(' + appliedCoupon.code + ')';
        } else {
            discountRow.style.display = 'none';
        }
    }

    // Delivery row
    const shippingEl = document.getElementById('orderShipping');
    if (deliveryFee === 0) {
        shippingEl.innerHTML = '<span class="text-success fw-bold">FREE</span>';
    } else {
        shippingEl.textContent = formatCurrency(deliveryFee);
    }

    // Handling fee row — always sync
    const handlingRow = document.getElementById('handlingFeeRow');
    const handlingEl  = document.getElementById('orderHandling');
    if (currentHandlingFee > 0) {
        if (handlingRow) handlingRow.style.display = '';
        if (handlingEl)  handlingEl.textContent    = formatCurrency(currentHandlingFee);
    } else {
        if (handlingRow) handlingRow.style.display = 'none';
    }

    document.getElementById('orderTotal').textContent = formatCurrency(total);
}

async function applyCoupon() {
    const input = document.getElementById('couponInput');
    const msgEl = document.getElementById('couponMessage');
    const code  = input.value.trim().toUpperCase();

    if (!code) {
        msgEl.innerHTML = '<span class="text-danger">Enter a coupon code</span>';
        return;
    }

    const { totals } = getCartForCheckout();
    const btn  = document.getElementById('applyCouponBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const res  = await fetch('../api/customer/apply-coupon.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ code, subtotal: totals.subtotal }),
        });
        const data = await res.json();

        if (data.success) {
            appliedCoupon = data.data;
            input.disabled = true;
            btn.textContent = 'Remove';
            btn.onclick = removeCoupon;
            msgEl.innerHTML = `<span class="text-success"><i class="fas fa-check-circle"></i> Coupon applied — you saved ${formatCurrency(data.data.discount_amount)}</span>`;
            updateCheckoutTotals();
        } else {
            appliedCoupon = null;
            msgEl.innerHTML = `<span class="text-danger">${data.message}</span>`;
            btn.disabled = false;
            btn.innerHTML = orig;
            updateCheckoutTotals();
        }
    } catch (err) {
        console.error('Apply coupon error:', err);
        msgEl.innerHTML = '<span class="text-danger">Could not apply coupon. Please try again.</span>';
        btn.disabled = false;
        btn.innerHTML = orig;
    } finally {
        if (appliedCoupon) { btn.disabled = false; }
    }
}

function removeCoupon() {
    appliedCoupon = null;
    const input = document.getElementById('couponInput');
    const btn   = document.getElementById('applyCouponBtn');
    input.value = '';
    input.disabled = false;
    btn.textContent = 'Apply';
    btn.onclick = applyCoupon;
    document.getElementById('couponMessage').textContent = '';
    updateCheckoutTotals();
}

async function loadPaymentMethods() {
    const container = document.getElementById('paymentMethodsContainer');
    const placeBtn  = document.getElementById('placeOrderBtn');

    try {
        const res  = await fetch('../api/payment-settings.php');
        const data = await res.json();

        const codEnabled    = data.cod_enabled === true;
        const onlineEnabled = data.online_payment_enabled === true;
        const anyEnabled    = codEnabled || onlineEnabled;

        let html = '';

        if (onlineEnabled) {
            html += `
            <div class="payment-option${!codEnabled ? ' selected' : ''}" id="payment-online" onclick="selectPayment('online')">
                <input type="radio" name="payment" id="online" value="online" ${!codEnabled ? 'checked' : ''}>
                <label for="online" class="mb-0" style="cursor:pointer;">
                    <strong><i class="fas fa-bolt"></i> Pay Online</strong>
                    <p class="text-grey small mb-0 mt-1">UPI, Cards, Netbanking &amp; Wallets — secured by Razorpay</p>
                </label>
            </div>`;
        }

        if (codEnabled) {
            html += `
            <div class="payment-option${codEnabled ? ' selected' : ''}" id="payment-cod" onclick="selectPayment('cod')">
                <input type="radio" name="payment" id="cod" value="cod" ${codEnabled ? 'checked' : ''}>
                <label for="cod" class="mb-0" style="cursor:pointer;">
                    <strong><i class="fas fa-money-bill-wave"></i> Cash on Delivery</strong>
                    <p class="text-grey small mb-0 mt-1">Pay when you receive your order</p>
                </label>
            </div>`;
        }

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

    // Initial totals — delivery/handling update after config+pincode load
    document.getElementById('orderSubtotal').textContent = formatCurrency(totals.subtotal);
    document.getElementById('orderShipping').innerHTML   = '<span class="text-muted small">Enter pincode</span>';
    document.getElementById('orderTotal').textContent    = formatCurrency(totals.subtotal);

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
        if (saved.pincode) {
            document.getElementById('pincode').value = saved.pincode;
            if (saved.pincode.length === 6) checkDeliveryZone(saved.pincode);
        }
    }

    // Override with DB-saved default address from profile API (most up-to-date)
    try {
        const res  = await fetch('../api/customer/my-profile.php');
        const data = await res.json();
        if (data.success && data.data) {
            const p = data.data;
            _profileFromDB = p;
            if (p.phone)    document.getElementById('phone').value   = p.phone;
            if (p.default_address) {
                const a = p.default_address;
                if (a.address_line1) document.getElementById('address').value = a.address_line1;
                if (a.city)          document.getElementById('city').value    = a.city;
                if (a.pincode) {
                    document.getElementById('pincode').value = a.pincode;
                    if (a.pincode.length === 6) checkDeliveryZone(a.pincode);
                }
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
        latitude:  customerLat,
        longitude: customerLng,
    };

    const selectedPayment = document.querySelector('input[name="payment"]:checked');
    if (!selectedPayment) {
        showToast('Please select a payment method.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        return;
    }

    const cart = getCart();
    const cartPayload = {
        shipping,
        items:       cart.map(i => ({ id: i.id, quantity: i.quantity, size: i.size || null, color: i.color || null })),
        coupon_code: appliedCoupon ? appliedCoupon.code : undefined,
    };

    if (selectedPayment.value === 'online') {
        await startOnlinePayment(cartPayload, shipping, btn, orig);
    } else {
        await submitCodOrder({ ...cartPayload, payment_method: 'cod' }, shipping, btn, orig);
    }
}

async function submitCodOrder(payload, shipping, btn, orig) {
    try {
        const res  = await fetch('../api/customer/place-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            handleOrderSuccess(data, shipping);
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

/**
 * Online payment: (1) price the cart and open a Razorpay Order for that amount,
 * (2) let Razorpay Checkout collect payment, (3) send its signed response to
 * verify-razorpay-payment.php, which independently confirms the payment with
 * Razorpay and only then actually creates the order.
 */
async function startOnlinePayment(cartPayload, shipping, btn, orig) {
    if (typeof Razorpay === 'undefined') {
        showToast('Payment gateway failed to load. Please check your connection and try again.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        return;
    }

    try {
        const res  = await fetch('../api/customer/create-razorpay-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(cartPayload),
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Could not start payment', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            return;
        }

        const order = data.data;
        const rzp = new Razorpay({
            key:      order.key_id,
            amount:   order.amount,
            currency: order.currency,
            order_id: order.razorpay_order_id,
            name:     'TrenCart',
            description: 'Order payment',
            prefill: {
                name:    shipping.full_name,
                email:   shipping.email,
                contact: shipping.phone,
            },
            theme: { color: '#1a1a1a' },
            handler: async function (response) {
                if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Confirming Payment...';
                try {
                    const verifyRes = await fetch('../api/customer/verify-razorpay-payment.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({
                            razorpay_order_id:   response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature:  response.razorpay_signature,
                            ...cartPayload,
                        }),
                    });
                    const verifyData = await verifyRes.json();

                    if (verifyData.success) {
                        handleOrderSuccess(verifyData, shipping);
                    } else {
                        showToast(verifyData.message || 'Payment verification failed', 'error');
                        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                    }
                } catch (err) {
                    console.error('Verify payment error:', err);
                    showToast('Payment succeeded but confirmation failed. Please contact support with payment ID: ' + response.razorpay_payment_id, 'error');
                    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                }
            },
            modal: {
                ondismiss: function () {
                    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                }
            },
        });

        rzp.on('payment.failed', function (response) {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            showToast('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'Please try again'), 'error');
        });

        rzp.open();

    } catch (err) {
        console.error('Create razorpay order error:', err);
        showToast('Could not start payment. Please try again.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}

function handleOrderSuccess(data, shipping) {
    // Save address for next checkout (localStorage backup)
    localStorage.setItem('shippingAddress', JSON.stringify({
        phone:   shipping.phone,
        address: shipping.address,
        city:    shipping.city,
        pincode: shipping.pincode,
    }));

    // Silently fill empty profile fields from shipping details
    const dbName  = (_profileFromDB?.full_name || '').trim();
    const dbPhone = (_profileFromDB?.phone      || '').trim();
    const needsUpdate = (!dbName && shipping.full_name) || (!dbPhone && shipping.phone);
    if (needsUpdate) {
        (async () => {
            try {
                const updatePayload = {};
                if (!dbName  && shipping.full_name) updatePayload.full_name = shipping.full_name;
                if (!dbPhone && shipping.phone)     updatePayload.phone     = shipping.phone;
                await fetch('../api/customer/my-profile.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(updatePayload),
                });
                if (!dbName && shipping.full_name) {
                    const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
                    storedUser.full_name = shipping.full_name;
                    localStorage.setItem('user', JSON.stringify(storedUser));
                    localStorage.setItem('userName', shipping.full_name.split(' ')[0] || shipping.full_name);
                }
            } catch (e) { /* non-critical — profile can be filled later */ }
        })();
    }

    // Clear cart from localStorage
    localStorage.removeItem('cart');
    updateCartBadge();
    showOrderSuccessOverlay(data.order_number);
}

function showOrderSuccessOverlay(orderNumber) {
    const overlay = document.getElementById('orderSuccessOverlay');
    const numEl   = document.getElementById('successOrderNum');
    if (numEl)    numEl.textContent = 'Order #' + orderNumber;
    if (overlay)  overlay.style.display = 'flex';
    // Redirect to orders page after animation completes
    setTimeout(() => { window.location.href = 'orders.html'; }, 3200);
}
