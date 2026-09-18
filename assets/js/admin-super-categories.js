/**
 * Admin Super Categories Management
 */

let allSuperCategories = [];
let allParentCategories = [];

document.addEventListener('DOMContentLoaded', function () {
    window.addEventListener('adminReady', () => { loadSuperCategories(); }, { once: true });
});

async function loadSuperCategories() {
    try {
        const result = await adminAPI('../api/admin/super-categories.php');
        if (result.success) {
            allSuperCategories = result.data;
            renderSuperCategories(result.data);
        }
    } catch (e) {
        console.error('Load super categories error:', e);
    }
}

function renderSuperCategories(superCategories) {
    const tbody = document.getElementById('superCategoriesTable');
    if (!superCategories || !superCategories.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="admin-empty-state"><i class="fas fa-layer-group"></i><p>No super categories yet</p></td></tr>';
        return;
    }

    tbody.innerHTML = superCategories.map(sc => {
        const imgSrc = sc.super_category_image ? sc.super_category_image.replace(/^\//, '../') : '';
        const thumb = imgSrc
            ? `<img src="${imgSrc}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;margin-right:8px;vertical-align:middle;" onerror="this.style.display='none'">`
            : `<span style="display:inline-block;width:32px;height:32px;border-radius:6px;background:#f0f0f0;margin-right:8px;vertical-align:middle;text-align:center;line-height:32px;font-size:13px;color:#999;"><i class="fas fa-layer-group"></i></span>`;
        return `
            <tr>
                <td>${thumb}<strong>${sc.super_category_name}</strong></td>
                <td>${sc.linked_count || 0} categor${sc.linked_count == 1 ? 'y' : 'ies'}</td>
                <td>${statusBadge(sc.is_active == 1 ? 'active' : 'inactive')}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary btn-action me-1" onclick="openLinkCategoriesModal(${sc.super_category_id})" title="Link categories">
                        <i class="fas fa-link"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary btn-action me-1" onclick="editSuperCategory(${sc.super_category_id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-${sc.is_active == 1 ? 'warning' : 'success'} btn-action me-1" onclick="toggleSuperCategory(${sc.super_category_id}, ${sc.is_active})" title="${sc.is_active == 1 ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${sc.is_active == 1 ? 'eye-slash' : 'eye'}"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteSuperCategory(${sc.super_category_id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/* ---- Add / Edit ---- */

function openSuperCategoryModal(superCategory) {
    document.getElementById('superCategoryModalTitle').innerHTML = superCategory ?
        '<i class="fas fa-edit me-2"></i>Edit Super Category' :
        '<i class="fas fa-plus me-2"></i>Add Super Category';

    const isEditing = !!superCategory;
    document.getElementById('editSuperCategoryId').value  = isEditing ? superCategory.super_category_id : '';
    document.getElementById('superCategoryName').value    = isEditing ? superCategory.super_category_name : '';
    document.getElementById('superCategoryDisplayOrder').value = isEditing ? (superCategory.display_order || 0) : 0;
    document.getElementById('superCatImageFile').value    = '';
    document.getElementById('superCatImageUrl').value     = isEditing ? (superCategory.super_category_image || '') : '';

    const previewWrap = document.getElementById('superCatImgPreviewWrap');
    const previewImg  = document.getElementById('superCatImgPreview');
    if (isEditing && superCategory.super_category_image) {
        previewImg.src = superCategory.super_category_image.replace(/^\//, '../');
        previewWrap.style.display = 'flex';
    } else {
        previewWrap.style.display = 'none';
        previewImg.src = '';
    }

    new bootstrap.Modal(document.getElementById('superCategoryModal')).show();
}

function previewSuperCatImage(input) {
    const previewWrap = document.getElementById('superCatImgPreviewWrap');
    const previewImg  = document.getElementById('superCatImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeSuperCatImage() {
    document.getElementById('superCatImageFile').value = '';
    document.getElementById('superCatImageUrl').value  = '';
    document.getElementById('superCatImgPreview').src  = '';
    document.getElementById('superCatImgPreviewWrap').style.display = 'none';
}

function editSuperCategory(superCategoryId) {
    const sc = allSuperCategories.find(s => s.super_category_id == superCategoryId);
    if (sc) openSuperCategoryModal(sc);
}

async function saveSuperCategory() {
    const id = document.getElementById('editSuperCategoryId').value;

    if (!document.getElementById('superCategoryName').value.trim()) {
        adminToast('Name is required', 'error');
        return;
    }

    const saveBtn = document.querySelector('#superCategoryModal .btn-primary');
    const origLabel = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...'; }

    try {
        let imageUrl = document.getElementById('superCatImageUrl').value || null;
        const fileInput = document.getElementById('superCatImageFile');
        if (fileInput.files && fileInput.files[0]) {
            const fd = new FormData();
            fd.append('image', fileInput.files[0]);
            const upRes = await fetch('../api/admin/upload-super-category-image.php', { method: 'POST', body: fd });
            const upData = await upRes.json();
            if (!upData.success) {
                adminToast(upData.message || 'Image upload failed', 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origLabel; }
                return;
            }
            imageUrl = upData.image_url;
        }

        const data = {
            super_category_name:  document.getElementById('superCategoryName').value.trim(),
            display_order:        document.getElementById('superCategoryDisplayOrder').value || 0,
            super_category_image: imageUrl,
        };
        if (id) data.super_category_id = id;

        const url = id ? '../api/admin/update-super-category.php' : '../api/admin/create-super-category.php';
        const result = await adminAPI(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(data),
        });

        if (result.success) {
            adminToast(id ? 'Super category updated' : 'Super category created', 'success');
            bootstrap.Modal.getInstance(document.getElementById('superCategoryModal')).hide();
            loadSuperCategories();
        } else {
            adminToast(result.message || 'Failed to save', 'error');
        }
    } catch (e) {
        adminToast('Failed to save super category', 'error');
    } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origLabel; }
    }
}

async function toggleSuperCategory(superCategoryId, isActive) {
    const action = isActive == 1 ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} this super category?`)) return;

    try {
        const result = await adminAPI('../api/admin/toggle-super-category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ super_category_id: superCategoryId, is_active: isActive == 1 ? 0 : 1 })
        });

        if (result.success) {
            adminToast(`Super category ${action}d`, 'success');
            loadSuperCategories();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to update super category', 'error');
    }
}

async function deleteSuperCategory(superCategoryId) {
    if (!confirm('Delete this super category? Linked categories and their products are not affected — only the grouping is removed.')) return;

    try {
        const result = await adminAPI('../api/admin/delete-super-category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ super_category_id: superCategoryId })
        });

        if (result.success) {
            adminToast('Super category deleted', 'success');
            loadSuperCategories();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to delete super category', 'error');
    }
}

/* ---- Link Categories ---- */

async function openLinkCategoriesModal(superCategoryId) {
    const sc = allSuperCategories.find(s => s.super_category_id == superCategoryId);
    if (!sc) return;

    document.getElementById('linkSuperCategoryId').value = superCategoryId;
    document.getElementById('linkModalSuperCatName').textContent  = sc.super_category_name;
    document.getElementById('linkModalSuperCatName2').textContent = sc.super_category_name;

    const listEl = document.getElementById('linkCategoriesList');
    listEl.innerHTML = '<div class="admin-loading"><div class="spinner-border spinner-border-sm"></div></div>';

    new bootstrap.Modal(document.getElementById('linkCategoriesModal')).show();

    try {
        // Load all parent (top-level) categories, if not already cached
        if (!allParentCategories.length) {
            const catResult = await adminAPI('../api/admin/categories.php');
            if (catResult.success) {
                allParentCategories = catResult.data.filter(c => !c.parent_category_id);
            }
        }

        const linksResult = await adminAPI(`../api/admin/super-category-links.php?super_category_id=${superCategoryId}`);
        const linkedIds = linksResult.success ? linksResult.data : [];

        if (!allParentCategories.length) {
            listEl.innerHTML = '<p class="text-muted mb-0">No parent categories found. Add categories first.</p>';
            return;
        }

        listEl.innerHTML = allParentCategories.map(c => `
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="${c.category_id}" id="linkCat${c.category_id}" ${linkedIds.includes(c.category_id) ? 'checked' : ''}>
                <label class="form-check-label" for="linkCat${c.category_id}">${c.category_name}</label>
            </div>
        `).join('');
    } catch (e) {
        listEl.innerHTML = '<p class="text-danger mb-0">Failed to load categories.</p>';
    }
}

async function saveCategoryLinks() {
    const superCategoryId = document.getElementById('linkSuperCategoryId').value;
    const checked = Array.from(document.querySelectorAll('#linkCategoriesList input[type=checkbox]:checked'))
        .map(el => parseInt(el.value, 10));

    const saveBtn = document.querySelector('#linkCategoriesModal .btn-primary');
    const origLabel = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...'; }

    try {
        const result = await adminAPI('../api/admin/super-category-links.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ super_category_id: superCategoryId, category_ids: checked }),
        });

        if (result.success) {
            adminToast('Links saved', 'success');
            bootstrap.Modal.getInstance(document.getElementById('linkCategoriesModal')).hide();
            loadSuperCategories();
        } else {
            adminToast(result.message || 'Failed to save links', 'error');
        }
    } catch (e) {
        adminToast('Failed to save links', 'error');
    } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origLabel; }
    }
}
