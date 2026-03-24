/**
 * Customer Profile Page
 */

document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    loadProfile();

    const form = document.getElementById('profileForm');
    if (form) form.addEventListener('submit', saveProfile);
});

async function loadProfile() {
    try {
        const res  = await fetch('../api/customer/my-profile.php');
        const data = await res.json();

        if (!data.success) {
            if (res.status === 401) { window.location.href = 'login.html'; return; }
            showToast(data.message || 'Failed to load profile', 'error');
            return;
        }

        const p = data.data;

        document.getElementById('displayName').textContent  = p.full_name  || 'Customer';
        document.getElementById('displayEmail').textContent = p.email       || '';
        document.getElementById('displayPhone').textContent = p.phone       || '';

        document.getElementById('fullName').value    = p.full_name         || '';
        document.getElementById('email').value       = p.email             || '';
        document.getElementById('phone').value       = p.phone             || '';
        document.getElementById('dateOfBirth').value = p.date_of_birth     || '';
        document.getElementById('gender').value      = p.gender            || '';

    } catch (err) {
        console.error('Load profile error:', err);
        showToast('Failed to load profile', 'error');
    }
}

async function saveProfile(e) {
    e.preventDefault();

    const btn = e.target.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

    try {
        const payload = {
            full_name:     document.getElementById('fullName').value.trim(),
            phone:         document.getElementById('phone').value.trim(),
            date_of_birth: document.getElementById('dateOfBirth').value || null,
            gender:        document.getElementById('gender').value       || null
        };

        const res  = await fetch('../api/customer/my-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast('Profile updated successfully', 'success');
            // Update localStorage name
            if (payload.full_name) localStorage.setItem('userName', payload.full_name);
            document.getElementById('displayName').textContent  = payload.full_name;
            document.getElementById('displayPhone').textContent = payload.phone;
        } else {
            showToast(data.message || 'Update failed', 'error');
        }

    } catch (err) {
        console.error('Save profile error:', err);
        showToast('Failed to save profile', 'error');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
}
