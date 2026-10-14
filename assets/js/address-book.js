/**
 * Address Book — add/edit address modal (profile page and checkout)
 *
 * The map pin (assets/js/address-map.js) is saved with the address and is
 * what the distance-based delivery fee is calculated from at checkout.
 * Pages may define onAddressSaved(addressId) to react after a save.
 */

let _addressBookModal = null;
let _addressCache     = {};   // address_id -> address, for Edit buttons
let _pendingPin       = null; // pin to show once the modal is visible
let _accountContact   = null; // logged-in user's name/phone, pre-fills new addresses

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') return; // the page itself redirects; nothing to do here

    const modalEl = document.getElementById('addressModal');
    if (modalEl) {
        _addressBookModal = new bootstrap.Modal(modalEl);
        // Leaflet can only size itself once the modal is actually on screen
        modalEl.addEventListener('shown.bs.modal', () => {
            AddressMap.open(_pendingPin ? _pendingPin.latitude : null, _pendingPin ? _pendingPin.longitude : null);
        });
    }

    const phone = document.getElementById('addrPhone');
    if (phone) phone.addEventListener('input', () => { phone.value = normalizeMobile(phone.value); });
    const pin = document.getElementById('addrPincode');
    if (pin) pin.addEventListener('input', () => { pin.value = pin.value.replace(/\D/g, '').slice(0, 6); });

    loadAddresses();

    const form = document.getElementById('addressForm');
    if (form) form.addEventListener('submit', saveAddress);
});

/**
 * "+91 98765 43210", "098765-43210" -> "9876543210". Shared with checkout.
 */
function normalizeMobile(value) {
    let d = String(value || '').replace(/\D/g, '');
    if (d.length === 12 && d.startsWith('91')) d = d.slice(2);
    if (d.length === 11 && d.startsWith('0'))  d = d.slice(1);
    return d.slice(0, 10);
}

const ADDRESS_TYPE_ICON = { home: 'fa-house', work: 'fa-briefcase', other: 'fa-location-dot' };

function hasPin(a) {
    return a && a.latitude !== null && a.latitude !== undefined && a.latitude !== '' &&
           a.longitude !== null && a.longitude !== undefined && a.longitude !== '';
}

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

        _addressCache = {};
        data.data.forEach(a => { _addressCache[a.address_id] = a; });

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
    const pinLine = hasPin(a)
        ? '<div class="small text-success mb-2"><i class="fas fa-location-dot me-1"></i>Location pinned on map</div>'
        : `<div class="small mb-2"><a href="#" class="text-warning-emphasis" onclick="editAddress(${a.address_id}); return false;">
               <i class="fas fa-triangle-exclamation me-1"></i>Pin this address on the map for an exact delivery charge</a></div>`;

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
                <div class="small text-grey mb-1"><i class="fas fa-phone me-1"></i>${escapeHtml(a.phone)}</div>
                ${pinLine}
                <div class="d-flex gap-2 flex-wrap">
                    ${parseInt(a.is_default) ? '' : `<button class="btn btn-outline-dark btn-sm" onclick="setDefaultAddress(${a.address_id})">Set as Default</button>`}
                    <button class="btn btn-outline-secondary btn-sm" onclick="editAddress(${a.address_id})">Edit</button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteAddress(${a.address_id})">Delete</button>
                </div>
            </div>
        </div>`;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Edit by id — avoids inlining the address JSON into onclick (broke on names like "St. Mary's"). */
function editAddress(addressId) {
    const a = _addressCache[addressId];
    if (a) openAddressModal(a);
}

async function openAddressModal(address) {
    const form = document.getElementById('addressForm');
    form.reset();
    const search = document.getElementById('addrMapSearch');
    if (search) search.value = '';

    if (address) {
        document.getElementById('addressModalTitle').innerHTML = '<i class="fas fa-map-marker-alt me-2"></i>Edit Address';
        document.getElementById('addrId').value        = address.address_id;
        document.getElementById('addrType').value      = address.address_type || 'home';
        document.getElementById('addrFullName').value  = address.full_name || '';
        document.getElementById('addrPhone').value     = normalizeMobile(address.phone);
        document.getElementById('addrLine1').value     = address.address_line1 || '';
        document.getElementById('addrLine2').value     = address.address_line2 || '';
        document.getElementById('addrCity').value      = address.city || '';
        document.getElementById('addrState').value     = address.state || '';
        document.getElementById('addrPincode').value   = address.pincode || '';
        document.getElementById('addrIsDefault').checked = !!parseInt(address.is_default);
        _pendingPin = hasPin(address) ? { latitude: address.latitude, longitude: address.longitude } : null;
    } else {
        document.getElementById('addressModalTitle').innerHTML = '<i class="fas fa-map-marker-alt me-2"></i>Add Address';
        document.getElementById('addrId').value = '';
        _pendingPin = null;
    }

    if (_addressBookModal) _addressBookModal.show();

    // New address: start from the account's own name and mobile number
    if (!address) {
        const contact = await getAccountContact();
        if (contact && document.getElementById('addrId').value === '') {
            const name  = document.getElementById('addrFullName');
            const phone = document.getElementById('addrPhone');
            if (!name.value  && contact.full_name) name.value  = contact.full_name;
            if (!phone.value && contact.phone)     phone.value = normalizeMobile(contact.phone);
        }
    }
}

async function getAccountContact() {
    if (_accountContact) return _accountContact;
    try {
        const res  = await fetch('../api/customer/my-profile.php');
        const data = await res.json();
        if (data.success && data.data) _accountContact = { full_name: data.data.full_name, phone: data.data.phone };
    } catch (e) { /* pre-fill is optional */ }
    return _accountContact;
}

async function saveAddress(e) {
    e.preventDefault();

    const btn  = document.getElementById('addressSaveBtn');
    const orig = btn.innerHTML;

    const phone = normalizeMobile(document.getElementById('addrPhone').value);
    if (!/^[6-9]\d{9}$/.test(phone)) {
        showToast('Enter a valid 10-digit mobile number', 'error');
        document.getElementById('addrPhone').focus();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const addressId = document.getElementById('addrId').value;
    const pin = AddressMap.get();
    const payload = {
        address_type:  document.getElementById('addrType').value,
        full_name:     document.getElementById('addrFullName').value.trim(),
        phone,
        address_line1: document.getElementById('addrLine1').value.trim(),
        address_line2: document.getElementById('addrLine2').value.trim(),
        city:          document.getElementById('addrCity').value.trim(),
        state:         document.getElementById('addrState').value.trim(),
        pincode:       document.getElementById('addrPincode').value.trim(),
        is_default:    document.getElementById('addrIsDefault').checked,
        latitude:      pin.latitude,
        longitude:     pin.longitude,
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
            const savedId = addressId ? parseInt(addressId, 10) : (data.data && data.data.address_id);
            if (typeof onAddressSaved === 'function') onAddressSaved(savedId);
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
