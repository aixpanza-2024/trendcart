document.addEventListener('DOMContentLoaded', loadSubscription);

async function loadSubscription() {
    const box = document.getElementById('subscriptionBox');
    const badge = document.getElementById('subscriberBadge');
    try {
        const res  = await fetch('../api/customer/subscription.php');
        const data = await res.json();
        if (!data.success) return;

        const { plans, active, paused } = data.data;
        badge.style.display = active ? '' : 'none';
        box.style.display = '';

        if (!active && paused) {
            box.innerHTML = `
                <div class="small mb-2" style="color:#f5c542;"><i class="fas fa-pause-circle me-1"></i> Your subscription is paused. Contact support to resume.</div>`;
        } else if (active) {
            const until = new Date(active.expires_at.replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
            box.innerHTML = `
                <div class="small mb-2" style="color:#f5c542;"><i class="fas fa-crown me-1"></i> Subscriber — free delivery until ${until}</div>
                ${planButtons(plans, 'Renew')}`;
        } else {
            box.innerHTML = `
                <div class="small mb-2" style="color:rgba(255,255,255,0.75);">Subscribe for free delivery on every order</div>
                ${planButtons(plans, 'Subscribe')}`;
        }
    } catch (err) {
        console.error('Subscription load error:', err);
    }
}

function planButtons(plans, verb) {
    return `
        <div class="d-flex flex-column gap-2">
            <button class="btn btn-sm btn-outline-light" onclick="startSubscription('monthly')">
                ${verb} monthly — ₹${plans.monthly}
            </button>
            <button class="btn btn-sm btn-outline-light" onclick="startSubscription('yearly')">
                ${verb} yearly — ₹${plans.yearly}
            </button>
        </div>`;
}

async function startSubscription(plan) {
    if (typeof Razorpay === 'undefined') {
        showToast('Payment gateway failed to load. Please check your connection and try again.', 'error');
        return;
    }

    try {
        const res  = await fetch('../api/customer/create-subscription-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ plan }),
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Could not start payment', 'error');
            return;
        }

        const order = data.data;
        const rzp = new Razorpay({
            key:         order.key_id,
            amount:      order.amount,
            currency:    order.currency,
            order_id:    order.razorpay_order_id,
            name:        'TrenCart',
            description: `Subscription — ${plan}`,
            theme:       { color: '#1a1a1a' },
            handler: async function (response) {
                try {
                    const verifyRes = await fetch('../api/customer/verify-subscription-payment.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({
                            razorpay_order_id:   response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature:  response.razorpay_signature,
                            plan,
                        }),
                    });
                    const verifyData = await verifyRes.json();
                    if (verifyData.success) {
                        showToast('Subscription active — enjoy free delivery!', 'success');
                        loadSubscription();
                    } else {
                        showToast(verifyData.message || 'Payment verification failed', 'error');
                    }
                } catch (err) {
                    console.error('Verify subscription error:', err);
                    showToast('Payment succeeded but activation failed. Please contact support with payment ID: ' + response.razorpay_payment_id, 'error');
                }
            },
        });

        rzp.on('payment.failed', function (response) {
            showToast('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'Please try again'), 'error');
        });

        rzp.open();

    } catch (err) {
        console.error('Create subscription order error:', err);
        showToast('Could not start payment. Please try again.', 'error');
    }
}
