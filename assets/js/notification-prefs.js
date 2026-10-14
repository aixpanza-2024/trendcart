document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('notificationsToggle');
    if (!toggle) return;

    fetch('../api/customer/notification-preferences.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) toggle.checked = data.data.notifications_enabled;
        })
        .catch(() => {});

    toggle.addEventListener('change', function () {
        fetch('../api/customer/notification-preferences.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ notifications_enabled: toggle.checked }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && typeof showToast === 'function') {
                    showToast(toggle.checked ? 'Offer notifications on' : 'Offer notifications off', 'success');
                }
            })
            .catch(() => {});
    });
});
