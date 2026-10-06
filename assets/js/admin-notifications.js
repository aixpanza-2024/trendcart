document.addEventListener('DOMContentLoaded', function () {
    loadOffers();
    document.getElementById('offerForm').addEventListener('submit', publishOffer);
    document.getElementById('offerBody').addEventListener('input', function () {
        document.getElementById('bodyCount').textContent = this.value.length;
    });
});

function escHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function loadOffers() {
    const body = document.getElementById('offersBody');
    try {
        const res  = await fetch('../api/admin/notifications.php');
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escHtml(data.message || 'Failed to load')}</td></tr>`;
            return;
        }
        if (data.data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No offers posted yet</td></tr>';
            return;
        }

        body.innerHTML = data.data.map(o => `
            <tr>
                <td>
                    <div class="fw-semibold">${escHtml(o.title)}</div>
                    <div class="small text-muted">${escHtml(o.body)}</div>
                    ${o.coupon_code ? `<span class="badge bg-dark mt-1">${escHtml(o.coupon_code)}</span>` : ''}
                </td>
                <td>${o.audience === 'subscribers' ? 'Subscribers' : 'All customers'}</td>
                <td class="text-end">${escHtml(o.recipients_count)}</td>
                <td class="text-end">${escHtml(o.push_sent_count)}${Number(o.push_failed_count) ? ` <span class="text-danger small">(${escHtml(o.push_failed_count)} failed)</span>` : ''}</td>
                <td class="small">${escHtml(o.push_status || '')}</td>
                <td class="small">${escHtml(new Date(o.created_at.replace(' ', 'T')).toLocaleString('en-IN'))}</td>
            </tr>`).join('');
    } catch (err) {
        console.error('Load offers error:', err);
        body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Could not load offers</td></tr>';
    }
}

async function publishOffer(e) {
    e.preventDefault();
    const btn = document.getElementById('sendOfferBtn');
    const audience = document.getElementById('offerAudience').value;
    const audienceLabel = audience === 'subscribers' ? 'subscribers only' : 'all active customers';
    if (!confirm(`Publish this offer to ${audienceLabel}? This cannot be undone.`)) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

    try {
        const res  = await fetch('../api/admin/notifications.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                title:       document.getElementById('offerTitle').value.trim(),
                body:        document.getElementById('offerBody').value.trim(),
                coupon_code: document.getElementById('offerCoupon').value.trim(),
                link_url:    document.getElementById('offerLink').value.trim(),
                audience,
            }),
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Could not publish offer');
            return;
        }
        alert(`Offer published. Inbox: ${data.data.recipients}. ${data.data.push_status}`);
        document.getElementById('offerForm').reset();
        document.getElementById('bodyCount').textContent = '0';
        loadOffers();
    } catch (err) {
        console.error('Publish offer error:', err);
        alert('Could not publish offer');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Publish & send';
    }
}
