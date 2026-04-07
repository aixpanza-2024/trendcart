/**
 * Admin Shop OTP List
 */

document.addEventListener('DOMContentLoaded', function () {
    loadShopOtps();
});

async function loadShopOtps() {
    const tbody = document.getElementById('shopOtpTable');
    tbody.innerHTML = '<tr><td colspan="7" class="admin-loading"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const result = await adminAPI('../api/admin/shopotp.php');
        if (result.success) {
            renderShopOtps(result.data);
            return;
        }

        adminToast(result.message || 'Failed to load shop OTPs', 'error');
        renderShopOtps([]);
    } catch (e) {
        console.error('Load shop OTPs error:', e);
        adminToast('Failed to load shop OTPs', 'error');
        renderShopOtps([]);
    }
}

function renderShopOtps(otps) {
    const tbody = document.getElementById('shopOtpTable');
    document.getElementById('otpCount').textContent = otps.length;

    if (!otps.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><i class="fas fa-key"></i><p>No shop OTPs found</p></td></tr>';
        return;
    }

    tbody.innerHTML = otps.map(otp => `
        <tr>
            <td><strong>${escapeAdminHtml(otp.shop_name || '-')}</strong></td>
            <td>${escapeAdminHtml(otp.shop_email || otp.email || '-')}</td>
            <td><span class="badge bg-dark fs-6">${escapeAdminHtml(otp.otp_code || '-')}</span></td>
            <td class="hide-mobile">${escapeAdminHtml(otp.purpose || '-')}</td>
            <td>${shopOtpStatusBadge(otp)}</td>
            <td class="hide-mobile">${formatDateTime(otp.created_at)}</td>
            <td class="hide-mobile">${formatDateTime(otp.expires_at)}</td>
        </tr>
    `).join('');
}

function shopOtpStatusBadge(otp) {
    if (Number(otp.is_used) === 1) {
        return '<span class="badge-status badge-inactive">Used</span>';
    }

    if (otp.expires_at && new Date(otp.expires_at.replace(' ', 'T')) < new Date()) {
        return '<span class="badge-status badge-suspended">Expired</span>';
    }

    return '<span class="badge-status badge-active">Active</span>';
}

function escapeAdminHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}
