/**
 * Shop Profile Management
 */

let shopMap, shopMarker;

document.addEventListener('DOMContentLoaded', function () {
    checkShopAuth();
    initShopLocationMap();
    loadProfile();

    document.getElementById('profileForm').addEventListener('submit', saveProfile);
});

/* ---- Location Map ---- */

function initShopLocationMap() {
    shopMap = L.map('shopLocationMap').setView([20.5937, 78.9629], 5); // India-wide default view
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(shopMap);

    shopMap.on('click', function (e) {
        setShopLocationMarker(e.latlng.lat, e.latlng.lng);
    });
}

function setShopLocationMarker(lat, lng, recenter) {
    document.getElementById('shopLatitude').value  = lat;
    document.getElementById('shopLongitude').value = lng;
    document.getElementById('shopLocationStatus').textContent =
        `Location set: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;

    if (shopMarker) {
        shopMarker.setLatLng([lat, lng]);
    } else {
        shopMarker = L.marker([lat, lng], { draggable: true }).addTo(shopMap);
        shopMarker.on('dragend', function () {
            const pos = shopMarker.getLatLng();
            setShopLocationMarker(pos.lat, pos.lng);
        });
    }

    if (recenter) {
        shopMap.setView([lat, lng], 16);
    }
}

function useCurrentLocation() {
    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by this browser', 'error');
        return;
    }
    document.getElementById('shopLocationStatus').textContent = 'Getting your location...';
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            setShopLocationMarker(pos.coords.latitude, pos.coords.longitude, true);
        },
        function () {
            document.getElementById('shopLocationStatus').textContent = 'No location set yet.';
            showToast('Could not get your current location', 'error');
        }
    );
}

async function checkShopAuth() {
    try {
        const res = await fetch('../api/check-auth.php');
        const data = await res.json();
        if (!data.success || !data.data.logged_in) {
            window.location.href = '../pages/login.html';
            return;
        }
        if (data.data.user.user_type !== 'shop') {
            alert('Access denied. Shop owners only.');
            window.location.href = '../index.html';
        }
    } catch (e) {
        window.location.href = '../pages/login.html';
    }
}

async function loadProfile() {
    try {
        const res = await fetch('../api/shop/profile.php');
        const data = await res.json();

        if (data.success && data.data) {
            fillProfile(data.data);
        } else {
            showToast('Failed to load profile', 'error');
        }
    } catch (e) {
        console.error('Load profile error:', e);
        showToast('Failed to load profile', 'error');
    }
}

function fillProfile(p) {
    // Logo
    const logoPreview     = document.getElementById('logoPreview');
    const logoPlaceholder = document.getElementById('logoPlaceholder');
    if (p.shop_logo) {
        logoPreview.src            = '../' + p.shop_logo.replace(/^\//, '');
        logoPreview.style.display  = '';
        logoPlaceholder.style.display = 'none';
    } else {
        logoPreview.style.display  = 'none';
        logoPlaceholder.style.display = '';
    }

    // Shop details
    document.getElementById('shopName').value        = p.shop_name || '';
    document.getElementById('shopEmail').value       = p.shop_email || '';
    document.getElementById('shopPhone').value       = p.shop_phone || '';
    document.getElementById('gstNumber').value       = p.gst_number || '';
    document.getElementById('shopDescription').value = p.shop_description || p.profile_description || '';

    // Address
    document.getElementById('shopAddress').value = p.shop_address || '';
    document.getElementById('shopCity').value    = p.shop_city || '';
    document.getElementById('shopState').value   = p.shop_state || '';
    document.getElementById('shopPincode').value = p.shop_pincode || '';

    // Owner
    document.getElementById('ownerName').value  = p.full_name || '';
    document.getElementById('ownerEmail').value = p.email || '';
    document.getElementById('ownerPhone').value = p.phone || '';

    // Location
    if (p.latitude !== null && p.latitude !== undefined && p.longitude !== null && p.longitude !== undefined) {
        setShopLocationMarker(parseFloat(p.latitude), parseFloat(p.longitude), true);
    }

    // Stats
    document.getElementById('statProducts').textContent = p.total_products || 0;
    document.getElementById('statOrders').textContent   = p.total_orders || 0;
    document.getElementById('statSales').textContent    = '₹' + (parseFloat(p.total_sales) || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    document.getElementById('statRating').textContent   = parseFloat(p.rating_average || 0).toFixed(1);

    // Status badge
    const badge = document.getElementById('shopStatusBadge');
    const s = p.shop_status || 'open';
    badge.textContent = s.charAt(0).toUpperCase() + s.slice(1);
    badge.className = 'badge rounded-pill px-3 py-2 shop-status-' + s;
}

function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    document.getElementById('logoFileName').textContent = file.name;
    document.getElementById('uploadLogoBtn').style.display = '';
    const reader = new FileReader();
    reader.onload = e => {
        const preview     = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        preview.src            = e.target.result;
        preview.style.display  = '';
        placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

async function uploadLogo() {
    const input = document.getElementById('logoInput');
    if (!input.files || !input.files[0]) return;

    const btn  = document.getElementById('uploadLogoBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';

    const formData = new FormData();
    formData.append('logo', input.files[0]);

    try {
        const res  = await fetch('../api/shop/upload-logo.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast('Logo updated successfully!', 'success');
            input.value = '';
            document.getElementById('logoFileName').textContent = '';
            btn.style.display = 'none';
        } else {
            showToast(data.message || 'Upload failed', 'error');
        }
    } catch (e) {
        showToast('Upload failed', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = orig;
}

async function saveProfile(e) {
    e.preventDefault();

    const btn = document.getElementById('saveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const data = {
        shop_name:        document.getElementById('shopName').value.trim(),
        shop_email:       document.getElementById('shopEmail').value.trim(),
        shop_phone:       document.getElementById('shopPhone').value.trim(),
        gst_number:       document.getElementById('gstNumber').value.trim(),
        shop_description: document.getElementById('shopDescription').value.trim(),
        shop_address:     document.getElementById('shopAddress').value.trim(),
        shop_city:        document.getElementById('shopCity').value.trim(),
        shop_state:       document.getElementById('shopState').value.trim(),
        shop_pincode:     document.getElementById('shopPincode').value.trim(),
        owner_phone:      document.getElementById('ownerPhone').value.trim(),
        latitude:         document.getElementById('shopLatitude').value || null,
        longitude:        document.getElementById('shopLongitude').value || null,
    };

    if (!data.shop_name) {
        showToast('Shop name is required', 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
        return;
    }

    try {
        const res = await fetch('../api/shop/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            showToast('Profile saved successfully!', 'success');
        } else {
            showToast(result.message || 'Failed to save', 'error');
        }
    } catch (err) {
        console.error('Save profile error:', err);
        showToast('Failed to save profile', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = orig;
}
