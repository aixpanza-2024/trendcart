/**
 * Admin Shops Management
 */

document.addEventListener('DOMContentLoaded', function () {
    loadShops();
    document.getElementById('searchShop').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadShops();
    });
});

async function loadShops() {
    const search = document.getElementById('searchShop').value;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);

    try {
        const result = await adminAPI('../api/admin/shops.php?' + params);
        if (result.success) {
            renderShops(result.data);
        }
    } catch (e) {
        console.error('Load shops error:', e);
    }
}

function renderShops(shops) {
    const tbody = document.getElementById('shopsTable');
    document.getElementById('shopCount').textContent = shops.length;

    if (!shops.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="admin-empty-state"><i class="fas fa-store"></i><p>No shops found</p></td></tr>';
        return;
    }

    tbody.innerHTML = shops.map(s => `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    ${s.shop_logo
                        ? `<img src="../${s.shop_logo.replace(/^\//, '')}" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">`
                        : `<div style="width:32px;height:32px;border-radius:6px;background:#e9ecef;display:flex;align-items:center;justify-content:center;"><i class="fas fa-store text-muted" style="font-size:12px;"></i></div>`
                    }
                    <strong>${s.shop_name}</strong>
                </div>
            </td>
            <td>${s.email || '-'}</td>
            <td class="hide-mobile">${s.total_products || 0}</td>
            <td class="hide-mobile">${s.total_orders || 0}</td>
            <td>${formatINR(s.total_sales)}</td>
            <td>${statusBadge(s.shop_status)}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-dark btn-action dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="openLogoModal(${s.shop_id}, '${s.shop_name.replace(/'/g, "\\'")}', '${s.shop_logo || ''}')"><i class="fas fa-image me-2"></i>Upload Logo</a></li>
                        <li><hr class="dropdown-divider"></li>
                        ${s.shop_status !== 'suspended' ?
                            `<li><a class="dropdown-item text-danger" href="#" onclick="updateShopStatus(${s.shop_id}, 'suspended')"><i class="fas fa-ban me-2"></i>Suspend</a></li>` :
                            `<li><a class="dropdown-item text-success" href="#" onclick="updateShopStatus(${s.shop_id}, 'open')"><i class="fas fa-check me-2"></i>Activate</a></li>`
                        }
                        ${s.shop_status === 'open' ?
                            `<li><a class="dropdown-item" href="#" onclick="updateShopStatus(${s.shop_id}, 'closed')"><i class="fas fa-door-closed me-2"></i>Mark Closed</a></li>` : ''
                        }
                        ${s.shop_status === 'closed' ?
                            `<li><a class="dropdown-item" href="#" onclick="updateShopStatus(${s.shop_id}, 'open')"><i class="fas fa-door-open me-2"></i>Mark Open</a></li>` : ''
                        }
                    </ul>
                </div>
            </td>
        </tr>
    `).join('');
}

async function addShop() {
    const btn = document.getElementById('submitAddShop');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

    const data = {
        full_name: document.getElementById('shopOwnerName').value.trim(),
        email: document.getElementById('shopOwnerEmail').value.trim(),
        phone: document.getElementById('shopOwnerPhone').value.trim(),
        shop_name: document.getElementById('shopName').value.trim(),
        shop_description: document.getElementById('shopDescription').value.trim(),
        shop_city: document.getElementById('shopCity').value.trim(),
        shop_phone: document.getElementById('shopPhone').value.trim()
    };

    if (!data.full_name || !data.email || !data.phone || !data.shop_name) {
        adminToast('Please fill all required fields', 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
        return;
    }

    try {
        const result = await adminAPI('../api/admin/add-shop.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if (result.success) {
            adminToast('Shop created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addShopModal')).hide();
            document.getElementById('addShopForm').reset();
            loadShops();
        } else {
            adminToast(result.message || 'Failed to create shop', 'error');
        }
    } catch (e) {
        console.error('Add shop error:', e);
        adminToast('Failed to create shop', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = orig;
}

async function updateShopStatus(shopId, status) {
    const label = status === 'suspended' ? 'suspend' : status === 'open' ? 'activate' : 'close';
    if (!confirm(`Are you sure you want to ${label} this shop?`)) return;

    try {
        const result = await adminAPI('../api/admin/update-shop-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shop_id: shopId, status: status })
        });

        if (result.success) {
            adminToast('Shop status updated', 'success');
            loadShops();
        } else {
            adminToast(result.message || 'Failed to update', 'error');
        }
    } catch (e) {
        adminToast('Failed to update shop status', 'error');
    }
}

/* --- Logo Upload --- */
function openLogoModal(shopId, shopName, logoPath) {
    document.getElementById('logoShopId').value       = shopId;
    document.getElementById('logoShopName').textContent = shopName;

    const preview     = document.getElementById('adminLogoPreview');
    const placeholder = document.getElementById('adminLogoPlaceholder');
    if (logoPath) {
        preview.src            = '../' + logoPath.replace(/^\//, '');
        preview.style.display  = '';
        placeholder.style.display = 'none';
    } else {
        preview.style.display  = 'none';
        placeholder.style.display = '';
    }

    document.getElementById('adminLogoInput').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('uploadLogoModal')).show();
}

function previewAdminLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview     = document.getElementById('adminLogoPreview');
        const placeholder = document.getElementById('adminLogoPlaceholder');
        preview.src            = e.target.result;
        preview.style.display  = '';
        placeholder.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

async function uploadAdminLogo() {
    const input = document.getElementById('adminLogoInput');
    if (!input.files || !input.files[0]) {
        adminToast('Please choose an image first', 'error');
        return;
    }

    const shopId = document.getElementById('logoShopId').value;
    const btn    = document.getElementById('adminUploadLogoBtn');
    const orig   = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';

    const formData = new FormData();
    formData.append('logo',    input.files[0]);
    formData.append('shop_id', shopId);

    try {
        const res  = await fetch('../api/admin/upload-shop-logo.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            adminToast('Logo uploaded successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('uploadLogoModal')).hide();
            loadShops();
        } else {
            adminToast(data.message || 'Upload failed', 'error');
        }
    } catch (e) {
        adminToast('Upload failed', 'error');
    }

    btn.disabled  = false;
    btn.innerHTML = orig;
}
