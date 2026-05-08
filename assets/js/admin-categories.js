/**
 * Admin Categories Management
 */

let allCategories = [];

document.addEventListener('DOMContentLoaded', function () {
    loadCategories();
    loadCategoryRequests();
});

async function loadCategories() {
    try {
        const result = await adminAPI('../api/admin/categories.php');
        if (result.success) {
            allCategories = result.data;
            renderCategories(result.data);
            populateParentDropdown(result.data);
        }
    } catch (e) {
        console.error('Load categories error:', e);
    }
}

function renderCategories(categories) {
    const tbody = document.getElementById('categoriesTable');
    if (!categories || !categories.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="admin-empty-state"><i class="fas fa-tags"></i><p>No categories found</p></td></tr>';
        return;
    }

    tbody.innerHTML = categories.map(c => {
        const parent = c.parent_category_id ? allCategories.find(p => p.category_id == c.parent_category_id) : null;
        const isParent = !c.parent_category_id;
        const imgSrc = c.category_image ? c.category_image.replace(/^\//, '../') : '';
        const thumb = imgSrc
            ? `<img src="${imgSrc}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;margin-right:8px;vertical-align:middle;" onerror="this.style.display='none'">`
            : `<span style="display:inline-block;width:32px;height:32px;border-radius:6px;background:#f0f0f0;margin-right:8px;vertical-align:middle;text-align:center;line-height:32px;font-size:13px;color:#999;"><i class="fas fa-image"></i></span>`;
        return `
            <tr>
                <td>
                    ${c.parent_category_id ? '<span class="text-muted me-2">└</span>' : ''}
                    ${thumb}<strong>${c.category_name}</strong>
                </td>
                <td>${parent ? parent.category_name : '-'}</td>
                <td class="hide-mobile">${c.product_count || 0}</td>
                <td>${statusBadge(c.is_active == 1 ? 'active' : 'inactive')}</td>
                <td>
                    ${isParent ? `<button class="btn btn-sm btn-outline-secondary btn-action me-1" onclick="addSubcategory(${c.category_id})" title="Add subcategory">
                        <i class="fas fa-plus"></i>
                    </button>` : ''}
                    <button class="btn btn-sm btn-outline-primary btn-action me-1" onclick="editCategory(${c.category_id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-${c.is_active == 1 ? 'warning' : 'success'} btn-action" onclick="toggleCategory(${c.category_id}, ${c.is_active})" title="${c.is_active == 1 ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${c.is_active == 1 ? 'eye-slash' : 'eye'}"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function populateParentDropdown(categories) {
    const select = document.getElementById('parentCategory');
    select.innerHTML = '<option value="">Select Parent...</option>';
    categories.filter(c => !c.parent_category_id).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.category_id;
        opt.textContent = c.category_name;
        select.appendChild(opt);
    });
}

function setCategoryType(isSub) {
    document.getElementById('parentCategoryRow').style.display = isSub ? '' : 'none';
    document.getElementById(isSub ? 'typeSub' : 'typeParent').checked = true;
}

// Wire up the radio buttons
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('typeParent').addEventListener('change', () => setCategoryType(false));
    document.getElementById('typeSub').addEventListener('change', () => setCategoryType(true));
});

function openCategoryModal(category) {
    document.getElementById('categoryModalTitle').innerHTML = category ?
        '<i class="fas fa-edit me-2"></i>Edit Category' :
        '<i class="fas fa-plus me-2"></i>Add Category';

    const isEditing = !!category;
    const hasSub = isEditing && !!category.parent_category_id;

    document.getElementById('categoryTypeRow').style.display = isEditing ? 'none' : '';
    document.getElementById('editCategoryId').value       = isEditing ? category.category_id : '';
    document.getElementById('categoryName').value         = isEditing ? category.category_name : '';
    document.getElementById('categoryDescription').value  = isEditing ? (category.category_description || '') : '';
    document.getElementById('displayOrder').value         = isEditing ? (category.display_order || 0) : 0;
    document.getElementById('catImageFile').value         = '';
    document.getElementById('catImageUrl').value          = isEditing ? (category.category_image || '') : '';

    // Show existing image preview when editing
    const previewWrap = document.getElementById('catImgPreviewWrap');
    const previewImg  = document.getElementById('catImgPreview');
    if (isEditing && category.category_image) {
        previewImg.src = category.category_image.replace(/^\//, '../');
        previewWrap.style.display = 'flex';
    } else {
        previewWrap.style.display = 'none';
        previewImg.src = '';
    }

    setCategoryType(hasSub);
    if (hasSub) document.getElementById('parentCategory').value = category.parent_category_id;

    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function previewCatImage(input) {
    const previewWrap = document.getElementById('catImgPreviewWrap');
    const previewImg  = document.getElementById('catImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeCatImage() {
    document.getElementById('catImageFile').value = '';
    document.getElementById('catImageUrl').value  = '';
    document.getElementById('catImgPreview').src  = '';
    document.getElementById('catImgPreviewWrap').style.display = 'none';
}

function editCategory(categoryId) {
    const cat = allCategories.find(c => c.category_id == categoryId);
    if (cat) openCategoryModal(cat);
}

function addSubcategory(parentId) {
    document.getElementById('categoryModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add Subcategory';
    document.getElementById('editCategoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDescription').value = '';
    document.getElementById('displayOrder').value = 0;
    document.getElementById('categoryTypeRow').style.display = 'none'; // type is fixed to sub
    setCategoryType(true);
    document.getElementById('parentCategory').value = parentId;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

async function saveCategory() {
    const id      = document.getElementById('editCategoryId').value;
    const isSub   = document.getElementById('parentCategoryRow').style.display !== 'none';
    const parentVal = document.getElementById('parentCategory').value;

    if (!document.getElementById('categoryName').value.trim()) {
        adminToast('Category name is required', 'error');
        return;
    }
    if (isSub && !parentVal) {
        adminToast('Please select a parent category', 'error');
        return;
    }

    const saveBtn = document.querySelector('#categoryModal .btn-primary');
    const origLabel = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...'; }

    try {
        // Upload new image if one was selected
        let imageUrl = document.getElementById('catImageUrl').value || null;
        const fileInput = document.getElementById('catImageFile');
        if (fileInput.files && fileInput.files[0]) {
            const fd = new FormData();
            fd.append('image', fileInput.files[0]);
            const upRes = await fetch('../api/admin/upload-category-image.php', { method: 'POST', body: fd });
            const upData = await upRes.json();
            if (!upData.success) {
                adminToast(upData.message || 'Image upload failed', 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origLabel; }
                return;
            }
            imageUrl = upData.image_url;
        }

        const data = {
            category_name:        document.getElementById('categoryName').value.trim(),
            parent_category_id:   isSub ? parentVal : null,
            category_description: document.getElementById('categoryDescription').value.trim(),
            display_order:        document.getElementById('displayOrder').value || 0,
            category_image:       imageUrl,
        };
        if (id) data.category_id = id;

        const url = id ? '../api/admin/update-category.php' : '../api/admin/create-category.php';
        const result = await adminAPI(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(data),
        });

        if (result.success) {
            adminToast(id ? 'Category updated' : 'Category created', 'success');
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
            loadCategories();
        } else {
            adminToast(result.message || 'Failed to save', 'error');
        }
    } catch (e) {
        adminToast('Failed to save category', 'error');
    } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = origLabel; }
    }
}

async function toggleCategory(categoryId, isActive) {
    const action = isActive == 1 ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} this category?`)) return;

    try {
        const result = await adminAPI('../api/admin/toggle-category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category_id: categoryId, is_active: isActive == 1 ? 0 : 1 })
        });

        if (result.success) {
            adminToast(`Category ${action}d`, 'success');
            loadCategories();
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to update category', 'error');
    }
}

/* ---- Category Requests ---- */
async function loadCategoryRequests() {
    try {
        const result = await adminAPI('../api/admin/category-requests.php?status=pending');
        if (!result.success) return;

        const card  = document.getElementById('requestsCard');
        const tbody = document.getElementById('requestsTable');
        const badge = document.getElementById('pendingBadge');

        badge.textContent = result.pending_count;
        card.style.display = result.data.length ? '' : 'none';

        if (!result.data.length) return;

        tbody.innerHTML = result.data.map(r => {
            const date = new Date(r.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
            return `<tr>
                <td><strong>${r.shop_name}</strong><br><small class="text-muted">${r.owner_name}</small></td>
                <td>${r.category_name}</td>
                <td>${r.parent_name || '<span class="text-muted">—</span>'}</td>
                <td><small>${r.note || '—'}</small></td>
                <td><small>${date}</small></td>
                <td>
                    <button class="btn btn-sm btn-success btn-action me-1" onclick="reviewRequest(${r.request_id}, 'approve')" title="Approve & Create">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger btn-action" onclick="reviewRequest(${r.request_id}, 'reject')" title="Reject">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

    } catch (e) {
        console.error('Load requests error:', e);
    }
}

async function reviewRequest(requestId, action) {
    const label = action === 'approve' ? 'approve and create this category' : 'reject this request';
    if (!confirm(`Are you sure you want to ${label}?`)) return;

    try {
        const result = await adminAPI('../api/admin/approve-category-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, action })
        });

        if (result.success) {
            adminToast(result.message, 'success');
            loadCategoryRequests();
            if (action === 'approve') loadCategories(); // refresh list to show new category
        } else {
            adminToast(result.message || 'Failed', 'error');
        }
    } catch (e) {
        adminToast('Failed to process request', 'error');
    }
}
