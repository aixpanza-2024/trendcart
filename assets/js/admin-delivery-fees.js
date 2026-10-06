document.addEventListener('DOMContentLoaded', function () {
    loadFees();
    document.getElementById('deliveryFeeForm').addEventListener('submit', saveFees);
});

async function loadFees() {
    try {
        const res  = await fetch('../api/admin/settings.php');
        const data = await res.json();
        if (!data.success) { alert('Failed to load delivery fees'); return; }
        const s = data.data;
        document.getElementById('feeUnder').value = s.delivery_fee_under_20km?.value ?? 29;
        document.getElementById('feeAbove').value = s.delivery_fee_above_20km?.value ?? 49;
    } catch (err) {
        console.error('Load delivery fees error:', err);
        alert('Failed to load delivery fees');
    }
}

async function saveFees(e) {
    e.preventDefault();
    const btn = document.getElementById('saveFeesBtn');
    btn.disabled = true;

    try {
        const res  = await fetch('../api/admin/settings.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                settings: [
                    { key: 'delivery_fee_under_20km', value: document.getElementById('feeUnder').value },
                    { key: 'delivery_fee_above_20km', value: document.getElementById('feeAbove').value },
                ],
            }),
        });
        const data = await res.json();
        alert(data.success ? 'Delivery fees saved' : (data.message || 'Could not save'));
    } catch (err) {
        console.error('Save delivery fees error:', err);
        alert('Could not save delivery fees');
    } finally {
        btn.disabled = false;
    }
}
