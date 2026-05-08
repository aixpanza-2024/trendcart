/**
 * Admin Banners Management
 */

let bannerModal;
document.addEventListener('DOMContentLoaded', () => {
    bannerModal = new bootstrap.Modal(document.getElementById('bannerModal'));
    loadBanners();
    initImageUpload();
});

/* ── Load & render banners ── */
async function loadBanners() {
    try {
        const res  = await fetch('../api/admin/banners.php');
        const data = await res.json();
        renderBanners(data.success ? data.data : []);
    } catch (e) {
        adminToast('Failed to load banners', 'error');
    }
}

function renderBanners(banners) {
    const grid  = document.getElementById('bannersGrid');
    const empty = document.getElementById('emptyState');

    if (!banners.length) {
        grid.innerHTML = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    grid.innerHTML = banners.map(b => {
        const imgSrc = b.image_url.startsWith('/') ? '../' + b.image_url.replace(/^\//, '') : b.image_url;
        const statusBadge = b.is_active == 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';
        return `
        <div class="col-sm-6 col-lg-4" id="bannerCard_${b.banner_id}">
            <div class="banner-card">
                <img src="${imgSrc}" alt="${b.title || 'Banner'}" class="banner-preview-img"
                     onerror="this.src='../assets/images/trencartlogo.png'">
                <div class="banner-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div style="min-width:0;">
                            <div class="banner-card-title">${b.title || '<em class="text-muted">No title</em>'}</div>
                            <div class="banner-card-sub">${b.subtitle || ''}</div>
                        </div>
                        <div class="ms-2">${statusBadge}</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Order: ${b.display_order}</small>
                        <div class="banner-card-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="editBanner(${b.banner_id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBanner(${b.banner_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ── Open modal for new banner ── */
function openBannerModal() {
    document.getElementById('bannerModalTitle').textContent = 'Add Banner';
    document.getElementById('editBannerId').value  = '';
    document.getElementById('bannerImageUrl').value = '';
    document.getElementById('bannerTitle').value    = '';
    document.getElementById('bannerSubtitle').value = '';
    document.getElementById('bannerLinkUrl').value  = '';
    document.getElementById('bannerOrder').value    = '0';
    document.getElementById('bannerActive').value   = '1';
    resetImagePreview();
    bannerModal.show();
}

/* ── Populate modal for editing ── */
async function editBanner(id) {
    try {
        const res  = await fetch('../api/admin/banners.php');
        const data = await res.json();
        const b    = (data.data || []).find(x => x.banner_id == id);
        if (!b) return;

        document.getElementById('bannerModalTitle').textContent = 'Edit Banner';
        document.getElementById('editBannerId').value   = b.banner_id;
        document.getElementById('bannerImageUrl').value = b.image_url;
        document.getElementById('bannerTitle').value    = b.title    || '';
        document.getElementById('bannerSubtitle').value = b.subtitle || '';
        document.getElementById('bannerLinkUrl').value  = b.link_url || '';
        document.getElementById('bannerOrder').value    = b.display_order;
        document.getElementById('bannerActive').value   = b.is_active;

        // Show existing image
        const preview = document.getElementById('imagePreview');
        const dropText = document.getElementById('dropText');
        const imgSrc = b.image_url.startsWith('/') ? '../' + b.image_url.replace(/^\//, '') : b.image_url;
        preview.src = imgSrc;
        preview.style.display = 'block';
        dropText.style.display = 'none';

        bannerModal.show();
    } catch (e) {
        adminToast('Failed to load banner', 'error');
    }
}

/* ── Save (create or update) ── */
async function saveBanner() {
    const bannerId = document.getElementById('editBannerId').value;
    const imageUrl = document.getElementById('bannerImageUrl').value.trim();

    if (!imageUrl) {
        adminToast('Please upload a banner image first', 'error');
        return;
    }

    const payload = {
        image_url:     imageUrl,
        title:         document.getElementById('bannerTitle').value.trim(),
        subtitle:      document.getElementById('bannerSubtitle').value.trim(),
        link_url:      document.getElementById('bannerLinkUrl').value.trim(),
        display_order: parseInt(document.getElementById('bannerOrder').value) || 0,
        is_active:     parseInt(document.getElementById('bannerActive').value),
    };

    const btn = document.getElementById('saveBannerBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        const method = bannerId ? 'PUT' : 'POST';
        if (bannerId) payload.banner_id = parseInt(bannerId);

        const res  = await fetch('../api/admin/banners.php', {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            adminToast(data.message, 'success');
            bannerModal.hide();
            loadBanners();
        } else {
            adminToast(data.message || 'Save failed', 'error');
        }
    } catch (e) {
        adminToast('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Banner';
    }
}

/* ── Delete ── */
async function deleteBanner(id) {
    if (!confirm('Delete this banner?')) return;
    try {
        const res  = await fetch('../api/admin/banners.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ banner_id: id })
        });
        const data = await res.json();
        if (data.success) {
            adminToast('Banner deleted', 'success');
            document.getElementById('bannerCard_' + id)?.remove();
            if (!document.querySelector('#bannersGrid .banner-card')) loadBanners();
        } else {
            adminToast(data.message || 'Delete failed', 'error');
        }
    } catch (e) {
        adminToast('Network error', 'error');
    }
}

/* ── Image upload (click or drag-drop) ── */
function initImageUpload() {
    const input   = document.getElementById('bannerImageInput');
    const dropArea = document.getElementById('dropArea');

    input.addEventListener('change', () => { if (input.files[0]) uploadBannerImage(input.files[0]); });

    dropArea.addEventListener('dragover',  e => { e.preventDefault(); dropArea.classList.add('dragover'); });
    dropArea.addEventListener('dragleave', () => dropArea.classList.remove('dragover'));
    dropArea.addEventListener('drop', e => {
        e.preventDefault();
        dropArea.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) uploadBannerImage(file);
    });
}

async function uploadBannerImage(file) {
    const progress = document.getElementById('uploadProgress');
    progress.style.display = 'block';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res  = await fetch('../api/admin/upload-banner.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            document.getElementById('bannerImageUrl').value = data.image_url;
            const preview  = document.getElementById('imagePreview');
            const dropText = document.getElementById('dropText');
            preview.src    = '../' + data.image_url.replace(/^\//, '');
            preview.style.display = 'block';
            dropText.style.display = 'none';
            adminToast('Image uploaded', 'success');
        } else {
            adminToast(data.message || 'Upload failed', 'error');
        }
    } catch (e) {
        adminToast('Upload error', 'error');
    } finally {
        progress.style.display = 'none';
    }
}

function resetImagePreview() {
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('imagePreview').src = '';
    document.getElementById('dropText').style.display = '';
}
