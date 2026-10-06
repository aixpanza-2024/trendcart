document.addEventListener('DOMContentLoaded', loadSubscriptions);

function escHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function fmtDate(value) {
    return value ? new Date(value.replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

const STATUS_BADGE = {
    active:  '<span class="badge bg-success">Active</span>',
    paused:  '<span class="badge bg-warning text-dark">Paused</span>',
    expired: '<span class="badge bg-secondary">Expired</span>',
};

async function loadSubscriptions() {
    const body = document.getElementById('subscriptionsBody');
    try {
        const res  = await fetch('../api/admin/subscriptions.php');
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escHtml(data.message || 'Failed to load')}</td></tr>`;
            return;
        }
        if (data.data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No subscriptions yet</td></tr>';
            return;
        }

        body.innerHTML = data.data.map(s => {
            const action = s.status === 'active'
                ? `<button class="btn btn-sm btn-outline-warning" onclick="changeSubscription(${s.subscription_id}, 'pause')">Pause</button>`
                : s.status === 'paused'
                    ? `<button class="btn btn-sm btn-outline-success" onclick="changeSubscription(${s.subscription_id}, 'resume')">Resume</button>`
                    : '';
            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${escHtml(s.full_name)}</div>
                        <div class="small text-muted">${escHtml(s.email || s.phone || '')}</div>
                    </td>
                    <td class="text-capitalize">${escHtml(s.plan)}</td>
                    <td>₹${escHtml(Number(s.amount).toFixed(2))}</td>
                    <td class="small">${fmtDate(s.starts_at)} → ${fmtDate(s.expires_at)}</td>
                    <td>${STATUS_BADGE[s.status] || ''}</td>
                    <td class="text-end">${action}</td>
                </tr>`;
        }).join('');
    } catch (err) {
        console.error('Load subscriptions error:', err);
        body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Could not load subscriptions</td></tr>';
    }
}

async function changeSubscription(subscriptionId, action) {
    try {
        const res  = await fetch('../api/admin/subscriptions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ subscription_id: subscriptionId, action }),
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Could not update subscription');
            return;
        }
        loadSubscriptions();
    } catch (err) {
        console.error('Change subscription error:', err);
        alert('Could not update subscription');
    }
}
