/**
 * Coupons Page — browse currently available coupons
 */

document.addEventListener('DOMContentLoaded', function () {
    loadCoupons();
});

async function loadCoupons() {
    const grid = document.getElementById('couponsGrid');
    if (!grid) return;

    try {
        const res  = await fetch('../api/customer/coupons.php');
        const data = await res.json();

        if (!data.success) {
            grid.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load coupons.</div>';
            return;
        }

        if (!data.data.length) {
            grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-ticket-alt fa-3x text-grey mb-3"></i>
                    <h5>No coupons available right now</h5>
                    <p class="text-grey">Check back soon — new offers are added regularly.</p>
                </div>`;
            return;
        }

        grid.innerHTML = data.data.map(c => buildCouponCard(c)).join('');

    } catch (err) {
        console.error('Load coupons error:', err);
        grid.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load coupons.</div>';
    }
}

function buildCouponCard(c) {
    const valueLabel = c.discount_type === 'percentage'
        ? `${parseFloat(c.discount_value)}% OFF`
        : c.discount_type === 'free_delivery' ? 'FREE DELIVERY'
        : `₹${parseFloat(c.discount_value).toLocaleString('en-IN')} OFF`;

    const terms = [];
    if (c.first_order_only == 1) terms.push('First order only');
    if (parseFloat(c.min_order_value) > 0) {
        terms.push(`Min order ₹${parseFloat(c.min_order_value).toLocaleString('en-IN')}`);
    }
    if (c.discount_type === 'percentage' && c.max_discount_amount) {
        terms.push(`Up to ₹${parseFloat(c.max_discount_amount).toLocaleString('en-IN')} off`);
    }
    if (c.valid_until) {
        const d = new Date(c.valid_until);
        terms.push('Valid till ' + d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }));
    }

    return `
        <div class="col-md-6 col-lg-4">
            <div class="coupon-card">
                <div class="coupon-value">${valueLabel}</div>
                ${c.description ? `<div class="small text-grey">${escapeCouponHtml(c.description)}</div>` : ''}
                ${terms.length ? `<div class="small text-grey">${terms.join(' · ')}</div>` : ''}
                <div class="coupon-code-row">
                    <div class="coupon-code-box">${c.code}</div>
                    <button class="btn btn-dark btn-sm" onclick="copyCouponCode('${c.code}', this)">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>`;
}

function escapeCouponHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function copyCouponCode(code, btn) {
    const done = () => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied';
        showToast('Coupon code copied — paste it at checkout', 'success');
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done, () => fallbackCopy(code, done));
    } else {
        fallbackCopy(code, done);
    }
}

function fallbackCopy(code, done) {
    const ta = document.createElement('textarea');
    ta.value = code;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { showToast('Could not copy — code is ' + code, 'error'); }
    document.body.removeChild(ta);
}
