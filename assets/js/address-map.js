/**
 * Address map picker — Leaflet + OpenStreetMap, same as the shop location
 * picker. The pin is what the distance-based delivery fee is calculated
 * from; moving it also fills city / state / pincode via Nominatim.
 *
 * Expects in the page: #addrMap, #addrMapStatus, #addrMapSearch and the
 * address form fields #addrLine1, #addrCity, #addrState, #addrPincode.
 */
const AddressMap = (() => {
    const DEFAULT_VIEW = [10.1632, 76.6413]; // Kerala
    const NOMINATIM    = 'https://nominatim.openstreetmap.org';

    let map = null, marker = null;
    let lat = null, lng = null;
    let geocodeSeq = 0;

    function ensureMap() {
        if (map) return;
        map = L.map('addrMap').setView(DEFAULT_VIEW, 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        map.on('click', e => setPin(e.latlng.lat, e.latlng.lng, { fill: true }));
    }

    /** Call after the modal is visible (Leaflet needs real dimensions). */
    function open(initialLat, initialLng) {
        ensureMap();
        map.invalidateSize();
        if (marker) { map.removeLayer(marker); marker = null; }
        lat = lng = null;

        if (initialLat != null && initialLng != null && initialLat !== '' && initialLng !== '') {
            setPin(parseFloat(initialLat), parseFloat(initialLng), { recenter: true });
        } else {
            map.setView(DEFAULT_VIEW, 7);
            renderStatus();
        }
    }

    function setPin(newLat, newLng, opts = {}) {
        lat = Math.round(newLat * 1e7) / 1e7;
        lng = Math.round(newLng * 1e7) / 1e7;

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                setPin(p.lat, p.lng, { fill: true });
            });
        }
        if (opts.recenter) map.setView([lat, lng], 16);
        renderStatus();
        if (opts.fill) fillFromPin(lat, lng);
    }

    function clearPin() {
        if (marker) { map.removeLayer(marker); marker = null; }
        lat = lng = null;
        renderStatus();
    }

    function renderStatus() {
        const el = document.getElementById('addrMapStatus');
        if (!el) return;
        el.innerHTML = lat !== null
            ? `<span class="text-success"><i class="fas fa-circle-check me-1"></i>Location pinned — delivery charge will be calculated from this point.</span>
               <a href="#" class="ms-1 text-grey" onclick="AddressMap.clearPin(); return false;">Remove</a>`
            : `<span class="text-warning-emphasis"><i class="fas fa-triangle-exclamation me-1"></i>No location pinned — delivery charge will only be estimated from the pincode. Tap the map, search, or use your current location.</span>`;
    }

    // Reverse-geocode the pin and fill the form. City/state/pincode follow the
    // pin; address line 1 is only filled when empty (house/flat is the customer's).
    async function fillFromPin(pinLat, pinLng) {
        const seq = ++geocodeSeq;
        try {
            const res  = await fetch(`${NOMINATIM}/reverse?format=jsonv2&addressdetails=1&accept-language=en&lat=${pinLat}&lon=${pinLng}`);
            const data = await res.json();
            if (seq !== geocodeSeq || !data || !data.address) return; // a newer pin superseded this one
            const a = data.address;

            const city    = a.city || a.town || a.village || a.municipality || a.suburb || a.county || a.state_district || '';
            const state   = a.state || '';
            const pincode = (a.postcode || '').replace(/\s/g, '');
            const street  = [a.house_number, a.road, a.neighbourhood || a.suburb].filter(Boolean).join(', ');

            if (city)                      setField('addrCity', city);
            if (state)                     setField('addrState', state);
            if (/^\d{6}$/.test(pincode))   setField('addrPincode', pincode);
            const line1 = document.getElementById('addrLine1');
            if (line1 && !line1.value.trim() && street) line1.value = street;
        } catch (e) { /* lookup is a convenience — the pin itself is what matters */ }
    }

    function setField(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

    async function search() {
        const input = document.getElementById('addrMapSearch');
        const q = input ? input.value.trim() : '';
        if (!q) return;
        try {
            const res  = await fetch(`${NOMINATIM}/search?format=jsonv2&countrycodes=in&limit=1&accept-language=en&q=${encodeURIComponent(q)}`);
            const list = await res.json();
            if (!list.length) {
                showToast('Place not found — try a nearby landmark or area name', 'error');
                return;
            }
            setPin(parseFloat(list[0].lat), parseFloat(list[0].lon), { recenter: true, fill: true });
            showToast('Found it — drag the pin to your exact building', 'success');
        } catch (e) {
            showToast('Search is unavailable right now — tap the map instead', 'error');
        }
    }

    function useMyLocation(btn) {
        if (!navigator.geolocation) {
            showToast('Your browser does not support location detection', 'error');
            return;
        }
        const orig = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Locating...'; }
        navigator.geolocation.getCurrentPosition(
            pos => {
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                setPin(pos.coords.latitude, pos.coords.longitude, { recenter: true, fill: true });
            },
            err => {
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                showToast(err.code === err.PERMISSION_DENIED
                    ? 'Location permission denied — search or tap the map instead'
                    : 'Could not get your location — search or tap the map instead', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    return {
        open, search, useMyLocation, clearPin,
        get: () => ({ latitude: lat, longitude: lng }),
    };
})();
