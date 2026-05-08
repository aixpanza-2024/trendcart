/**
 * Admin Delivery Zones Management
 */

let zoneModal, pincodeModal, bulkModal;
let allZones = [];

document.addEventListener('DOMContentLoaded', () => {
    zoneModal    = new bootstrap.Modal(document.getElementById('zoneModal'));
    pincodeModal = new bootstrap.Modal(document.getElementById('pincodeModal'));
    bulkModal    = new bootstrap.Modal(document.getElementById('bulkModal'));
    loadZones();
    loadPincodes();
});

/* ── Zones ── */
async function loadZones() {
    const res  = await fetch('../api/admin/delivery-zones.php?type=zones');
    const data = await res.json();
    allZones = data.success ? data.data : [];
    renderZones();
    populateZoneSelects();
}

function renderZones() {
    const grid = document.getElementById('zonesGrid');
    if (!allZones.length) { grid.innerHTML = '<div class="col-12 text-muted text-center py-3">No zones yet.</div>'; return; }

    grid.innerHTML = allZones.map(z => {
        const feeBadge = z.delivery_fee == 0
            ? '<span class="badge bg-success">FREE</span>'
            : `<span class="badge bg-warning text-dark">₹${parseFloat(z.delivery_fee).toFixed(0)}</span>`;
        const defaultBadge = z.is_default_zone == 1 ? '<span class="badge bg-secondary ms-1">Default</span>' : '';
        const activeBadge  = z.is_active == 1
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Active</span>'
            : '<span class="badge bg-light text-muted border ms-1">Inactive</span>';
        return `
        <div class="col-md-4 col-sm-6">
            <div class="admin-card h-100">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold mb-1">${z.zone_name}</div>
                            <div class="mb-2">${feeBadge}${defaultBadge}${activeBadge}</div>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="editZone(${z.zone_id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger"  onclick="deleteZone(${z.zone_id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

function populateZoneSelects() {
    const opts = allZones.map(z => `<option value="${z.zone_id}">${z.zone_name}</option>`).join('');
    ['filterZone', 'pincodeZone', 'bulkZone'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const prefix = id === 'filterZone' ? '<option value="">All Zones</option>' : '';
        el.innerHTML = prefix + opts;
    });
}

function openZoneModal(zone = null) {
    document.getElementById('zoneModalTitle').textContent = zone ? 'Edit Zone' : 'Add Zone';
    document.getElementById('editZoneId').value        = zone ? zone.zone_id : '';
    document.getElementById('zoneName').value          = zone ? zone.zone_name : '';
    document.getElementById('zoneDeliveryFee').value   = zone ? zone.delivery_fee : '0';
    document.getElementById('zoneIsDefault').checked   = zone ? zone.is_default_zone == 1 : false;
    document.getElementById('zoneIsActive').checked    = zone ? zone.is_active == 1 : true;
    zoneModal.show();
}

function editZone(id) {
    const z = allZones.find(z => z.zone_id == id);
    if (z) openZoneModal(z);
}

async function saveZone() {
    const zone_id = document.getElementById('editZoneId').value;
    const payload = {
        action:          'save_zone',
        zone_id:         zone_id ? parseInt(zone_id) : 0,
        zone_name:       document.getElementById('zoneName').value.trim(),
        delivery_fee:    parseFloat(document.getElementById('zoneDeliveryFee').value) || 0,
        is_default_zone: document.getElementById('zoneIsDefault').checked ? 1 : 0,
        is_active:       document.getElementById('zoneIsActive').checked ? 1 : 0,
    };
    if (!payload.zone_name) { adminToast('Zone name is required', 'error'); return; }

    const res  = await fetch('../api/admin/delivery-zones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.success) { adminToast(data.message, 'success'); zoneModal.hide(); loadZones(); }
    else adminToast(data.message, 'error');
}

async function deleteZone(id) {
    if (!confirm('Delete this zone? All its pincodes will also be removed.')) return;
    const res  = await fetch('../api/admin/delivery-zones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_zone', zone_id: id }) });
    const data = await res.json();
    if (data.success) { adminToast(data.message, 'success'); loadZones(); loadPincodes(); }
    else adminToast(data.message, 'error');
}

/* ── Pincodes ── */
async function loadPincodes() {
    const zoneFilter = document.getElementById('filterZone')?.value || '';
    const url = '../api/admin/delivery-zones.php?type=pincodes' + (zoneFilter ? '&zone_id=' + zoneFilter : '');
    const res  = await fetch(url);
    const data = await res.json();
    renderPincodes(data.success ? data.data : []);
}

function renderPincodes(pincodes) {
    const tbody = document.getElementById('pincodesTable');
    if (!pincodes.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No pincodes found.</td></tr>'; return; }

    tbody.innerHTML = pincodes.map(p => `
        <tr>
            <td><strong>${p.pincode}</strong></td>
            <td>${p.area_name || '<em class="text-muted">—</em>'}</td>
            <td><span class="badge bg-light text-dark border">${p.zone_name}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editPincode(${JSON.stringify(p).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger"  onclick="deletePincode(${p.pincode_id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function openPincodeModal() {
    document.getElementById('pincodeModalTitle').textContent = 'Add Pincode';
    document.getElementById('editPincodeId').value = '';
    document.getElementById('pincodeValue').value  = '';
    document.getElementById('pincodeArea').value   = '';
    if (allZones.length) document.getElementById('pincodeZone').value = allZones[0].zone_id;
    pincodeModal.show();
}

function editPincode(p) {
    document.getElementById('pincodeModalTitle').textContent = 'Edit Pincode';
    document.getElementById('editPincodeId').value = p.pincode_id;
    document.getElementById('pincodeValue').value  = p.pincode;
    document.getElementById('pincodeArea').value   = p.area_name || '';
    document.getElementById('pincodeZone').value   = p.zone_id;
    pincodeModal.show();
}

async function savePincode() {
    const payload = {
        action:     'save_pincode',
        pincode_id: parseInt(document.getElementById('editPincodeId').value) || 0,
        pincode:    document.getElementById('pincodeValue').value.trim(),
        area_name:  document.getElementById('pincodeArea').value.trim(),
        zone_id:    parseInt(document.getElementById('pincodeZone').value),
    };
    const res  = await fetch('../api/admin/delivery-zones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.success) { adminToast(data.message, 'success'); pincodeModal.hide(); loadPincodes(); }
    else adminToast(data.message, 'error');
}

async function deletePincode(id) {
    if (!confirm('Remove this pincode?')) return;
    const res  = await fetch('../api/admin/delivery-zones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_pincode', pincode_id: id }) });
    const data = await res.json();
    if (data.success) { adminToast(data.message, 'success'); loadPincodes(); }
    else adminToast(data.message, 'error');
}

function openBulkModal() {
    document.getElementById('bulkPincodes').value = '';
    if (allZones.length) document.getElementById('bulkZone').value = allZones[0].zone_id;
    bulkModal.show();
}

async function saveBulkPincodes() {
    const payload = {
        action:   'bulk_pincodes',
        zone_id:  parseInt(document.getElementById('bulkZone').value),
        pincodes: document.getElementById('bulkPincodes').value.trim(),
    };
    const res  = await fetch('../api/admin/delivery-zones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.success) { adminToast(data.message, 'success'); bulkModal.hide(); loadPincodes(); }
    else adminToast(data.message, 'error');
}
