/**
 * Address Book — profile page
 */

let _addressBookModal = null;

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') return; // profile.js already redirects; nothing to do here

    const modalEl = document.getElementById('addressModal');
    if (modalEl) _addressBookModal = new bootstrap.Modal(modalEl);

    loadAddresses();

    const form = document.getElementById('addressForm');
    if (form) form.addEventListener('submit', saveAddress);
});

const ADDRESS_TYPE_ICON = { home: 'fa-house', work: 'fa-briefcase', other: 'fa-location-dot' };

async function loadAddresses() {
    const list = document.getElementById('addressList');
    if (!list) return;

    try {
        const res  = await fetch('../api/customer/addresses.php');
        const data = await res.json();

        if (!data.success) {
            list.innerHTML = '<div class="col-12 text-grey">Could not load addresses.</div>';
            return;
        }

        if (!data.data.length) {
            list.innerHTML = `
                <div class="col-12 text-center text-grey py-3">
                    <i class="fas fa-map-marker-alt fa-2x mb-2 d-block"></i>
                    No saved addresses yet. Add one to speed up checkout.
                </div>`;
            return;
        }

        list.innerHTML = data.data.map(a => addressCardHtml(a)).join('');

    } catch (err) {
        console.error('Load addresses error:', err);
        list.innerHTML = '<div class="col-12 text-grey">Could not load addresses.</div>';
    }
}

function addressCardHtml(a) {
    const icon = ADDRESS_TYPE_ICON[a.address_type] || 'fa-location-dot';
    const line2 = a.address_line2 ? `, ${escapeHtml(a.address_line2)}` : '';

    return `
        <div class="col-md-6">
            <div class="border rounded-3 p-3 h-100 position-relative" style="border-color:var(--border-color)!important;">
                ${parseInt(a.is_default) ? '<span class="badge bg-dark position-absolute top-0 end-0 m-2">Default</span>' : ''}
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas ${icon} text-grey"></i>
                    <span class="text-uppercase small fw-semibold text-grey">${escapeHtml(a.address_type)}</span>
                </div>
                <div class="fw-semibold">${escapeHtml(a.full_name)}</div>
                <div class="small text-grey mb-1">${escapeHtml(a.address_line1)}${line2}, ${escapeHtml(a.city)}, ${escapeHtml(a.state)} - ${escapeHtml(a.pincode)}</div>
                <div class="small text-grey mb-2"><i class="fas fa-phone me-1"></i>${escapeHtml(a.phone)}</div>
                <div class="d-flex gap-2 flex-wrap">
                    ${parseInt(a.is_default) ? '' : `<button class="btn btn-outline-dark btn-sm" onclick="setDefaultAddress(${a.address_id})">Set as Default</button>`}
                    <button class="btn btn-outline-secondary btn-sm" onclick='openAddressModal(${JSON.stringify(a)})'>Edit</button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteAddress(${a.address_id})">Delete</button>
                </div>
            </div>
        </div>`;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function openAddressModal(address) {
    const form = document.getElementById('addressForm');
    form.reset();

    if (address) {
        document.getElementById('addressModalTitle').innerHTML = '<i class="fas fa-map-marker-alt me-2"></i>Edit Address';
        document.getElementById('addrId').value        = address.address_id;
        document.getElementById('addrType').value      = address.address_type || 'home';
        document.getElementById('addrFullName').value  = address.full_name || '';
        document.getElementById('addrPhone').value     = address.phone || '';
        document.getElementById('addrLine1').value     = address.address_line1 || '';
        document.getElementById('addrLine2').value     = address.address_line2 || '';
        document.getElementById('addrCity').value      = address.city || '';
        document.getElementById('addrState').value     = address.state || '';
        document.getElementById('addrPincode').value   = address.pincode || '';
        document.getElementById('addrIsDefault').checked = !!parseInt(address.is_default);
    } else {
        document.getElementById('addressModalTitle').innerHTML = '<i class="fas fa-map-marker-alt me-2"></i>Add Address';
        document.getElementById('addrId').value = '';
    }

    if (_addressBookModal) _addressBookModal.show();
}

async function saveAddress(e) {
    e.preventDefault();

    const btn  = document.getElementById('addressSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const addressId = document.getElementById('addrId').value;
    const payload = {
        address_type:  document.getElementById('addrType').value,
        full_name:     document.getElementById('addrFullName').value.trim(),
        phone:         document.getElementById('addrPhone').value.trim(),
        address_line1: document.getElementById('addrLine1').value.trim(),
        address_line2: document.getElementById('addrLine2').value.trim(),
        city:          document.getElementById('addrCity').value.trim(),
        state:         document.getElementById('addrState').value.trim(),
        pincode:       document.getElementById('addrPincode').value.trim(),
        is_default:    document.getElementById('addrIsDefault').checked,
    };
    if (addressId) payload.address_id = parseInt(addressId, 10);

    try {
        const url  = addressId ? '../api/customer/update-address.php' : '../api/customer/addresses.php';
        const res  = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            showToast(addressId ? 'Address updated' : 'Address saved', 'success');
            if (_addressBookModal) _addressBookModal.hide();
            loadAddresses();
        } else {
            showToast(data.message || 'Could not save address', 'error');
        }
    } catch (err) {
        console.error('Save address error:', err);
        showToast('Could not save address. Please try again.', 'error');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
}

async function setDefaultAddress(addressId) {
    try {
        const res  = await fetch('../api/customer/set-default-address.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ address_id: addressId }),
        });
        const data = await res.json();
        if (data.success) {
            showToast('Default address updated', 'success');
            loadAddresses();
        } else {
            showToast(data.message || 'Could not update default address', 'error');
        }
    } catch (err) {
        console.error('Set default address error:', err);
        showToast('Could not update default address', 'error');
    }
}

async function deleteAddress(addressId) {
    if (!confirm('Delete this address?')) return;

    try {
        const res  = await fetch('../api/customer/delete-address.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ address_id: addressId }),
        });
        const data = await res.json();
        if (data.success) {
            showToast('Address deleted', 'success');
            loadAddresses();
        } else {
            showToast(data.message || 'Could not delete address', 'error');
        }
    } catch (err) {
        console.error('Delete address error:', err);
        showToast('Could not delete address', 'error');
    }
}
