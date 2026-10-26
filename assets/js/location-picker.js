/**
 * Navbar location chooser — "📍 Kazhakootam ▾" on every customer page.
 *
 * The customer picks where they are (current location, a search, the map
 * pin, or a saved address). It is kept on the device and, when logged in,
 * on their profile; the server then shows "X km away" for each shop
 * (api/customer/shops.php, product-detail.php via ?lat=&lng=).
 *
 * Google Maps + Places when api/config/maps_config.php has a key, otherwise
 * free OpenStreetMap (Leaflet + Nominatim). Loaded by main.js.
 */
const TcLocation = (() => {
    const STORE = 'tcLocation';
    const UP = /\/(pages|shop|admin)\//.test(window.location.pathname.replace(/\\/g, '/')) ? '../' : '';
    const DEFAULT_VIEW = { lat: 10.1632, lng: 76.6413 };   // Kerala
    let provider = null;           // { provider: 'google'|'osm', key }
    let modal = null, map = null, marker = null, pending = null, searchTimer = null, sessionToken = null;
    let onPicked = null;           // set by open(callback): checkout uses the pick without reloading

    const isLoggedIn = () => (typeof _isAuthValid === 'function' ? _isAuthValid() : localStorage.getItem('isLoggedIn') === 'true');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function get() {
        try {
            const l = JSON.parse(localStorage.getItem(STORE) || 'null');
            return l && isFinite(l.lat) && isFinite(l.lng) ? l : null;
        } catch (e) { return null; }
    }

    /** "lat=..&lng=.." for API calls (empty when no location is chosen). */
    function query() {
        const l = get();
        return l ? `lat=${encodeURIComponent(l.lat)}&lng=${encodeURIComponent(l.lng)}` : '';
    }

    function shortLabel(l) {
        return l && l.label ? l.label.split(',')[0].trim() : 'Select location';
    }

    function store(loc) {
        localStorage.setItem(STORE, JSON.stringify(loc));
        localStorage.setItem('customerLat', loc.lat);       // older code (checkout, shop page) reads these
        localStorage.setItem('customerLng', loc.lng);
        renderChip();
    }

    /* ── navbar chip ─────────────────────────────────────────────── */
    function renderChip() {
        const text = shortLabel(get());
        document.querySelectorAll('.tc-loc-chip-text').forEach(el => { el.textContent = text; });
    }

    function injectChip() {
        const nav = document.querySelector('nav.navbar');
        if (!nav || document.getElementById('tcLocChip')) return;
        const path = window.location.pathname.replace(/\\/g, '/');
        const helpHref = /\/pages\//.test(path) ? 'help-center.html' : 'pages/help-center.html';
        nav.insertAdjacentHTML('beforebegin', `
            <div class="tc-utility-bar">
                <button type="button" class="tc-loc-chip" id="tcLocChip" onclick="TcLocation.open()" title="Choose your location">
                    <i class="fas fa-location-dot"></i>
                    <span class="tc-loc-chip-text">${esc(shortLabel(get()))}</span>
                    <i class="fas fa-chevron-down tc-loc-caret"></i>
                </button>
                <div class="tc-utility-right">
                    <a href="tel:+918089970014" class="tc-utility-link"><i class="fas fa-phone"></i><span> +91 8089970014</span></a>
                    <a href="${helpHref}" class="tc-utility-link"><i class="fas fa-circle-question"></i><span> Help Center</span></a>
                </div>
            </div>`);
        const css = document.createElement('style');
        css.textContent = `
            .tc-utility-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;
                background:#fff;padding:6px 20px;border-bottom:1px solid #eee}
            .tc-loc-chip{display:inline-flex;align-items:center;gap:6px;max-width:220px;background:transparent;border:0;
                color:#1a1a1a;font-size:.86rem;font-weight:600;padding:4px 6px;border-radius:8px;white-space:nowrap;cursor:pointer}
            .tc-loc-chip:hover{background:rgba(0,0,0,.05)}
            .tc-loc-chip-text{overflow:hidden;text-overflow:ellipsis}
            .tc-loc-caret{font-size:.65rem;opacity:.7}
            .tc-utility-right{display:flex;align-items:center;gap:18px}
            .tc-utility-link{display:inline-flex;align-items:center;gap:5px;color:#1a1a1a;text-decoration:none;font-size:.82rem;font-weight:600}
            .tc-utility-link:hover{color:#8B5E3C}
            @media (max-width:575px){
                .tc-utility-bar{padding:5px 12px}
                .tc-loc-chip{max-width:140px;font-size:.8rem}
                .tc-utility-right{gap:10px}
                .tc-utility-link span{display:none}
            }
            .tc-loc-results{max-height:200px;overflow:auto}
            .tc-loc-results button{display:block;width:100%;text-align:left;border:0;background:none;padding:8px 10px;border-bottom:1px solid #eee;font-size:.88rem}
            .tc-loc-results button:hover{background:#f5f5f5}
            #tcLocMap{height:220px;border-radius:10px;border:1px solid #ddd}
            .tc-loc-km{font-size:.78rem;color:#6b7280}`;
        document.head.appendChild(css);
    }

    /* ── provider (Google if a key is configured, else OpenStreetMap) ── */
    function loadScript(src) {
        return new Promise((ok, fail) => {
            const s = document.createElement('script');
            s.src = src; s.async = true; s.onload = ok; s.onerror = fail;
            document.head.appendChild(s);
        });
    }

    const withTimeout = (p, ms) => Promise.race([p, new Promise((_, no) => setTimeout(() => no(new Error('timed out')), ms))]);

    function sessionGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
    function sessionSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* private mode */ } }

    /** One small Places request: a key without billing / APIs enabled fails here. */
    async function googleWorks() {
        try {
            const { AutocompleteSuggestion } = await withTimeout(google.maps.importLibrary('places'), 5000);
            await withTimeout(AutocompleteSuggestion.fetchAutocompleteSuggestions({ input: 'Kochi', includedRegionCodes: ['in'] }), 5000);
            return true;
        } catch (e) {
            console.warn('Google Maps/Places not usable with this key (billing or APIs not enabled?) — using OpenStreetMap', e);
            return false;
        }
    }

    async function useOsm() {
        provider = { provider: 'osm' };
        if (!window.L) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
            document.head.appendChild(css);
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js');
        }
    }

    async function setupProvider() {
        try { provider = await (await fetch(UP + 'api/maps-config.php')).json(); }
        catch (e) { provider = { provider: 'osm' }; }

        if (provider.provider === 'google') {
            try {
                if (sessionGet('tcGoogleMaps') === 'off') throw new Error('Google failed earlier in this visit');
                await loadScript(`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(provider.key)}&v=weekly&loading=async&region=IN`);
                for (let i = 0; i < 50 && !window.google?.maps?.importLibrary; i++) await new Promise(r => setTimeout(r, 100));
                if (!window.google?.maps?.importLibrary) throw new Error('Google Maps did not load');
                if (sessionGet('tcGoogleMaps') !== 'on') {           // checked once per visit
                    const ok = await googleWorks();
                    sessionSet('tcGoogleMaps', ok ? 'on' : 'off');
                    if (!ok) throw new Error('Google Places check failed');
                }
            } catch (e) {
                await useOsm();
            }
        }
        if (provider.provider === 'osm') await useOsm();
    }

    let providerReady = null;
    async function ensureProvider() {
        if (!providerReady) providerReady = setupProvider();   // shared by the popup and the checkout preview
        await providerReady;
        return provider.provider;
    }

    async function search(text) {
        if ((await ensureProvider()) === 'google') {
            let suggestions = null;
            try {
                const { AutocompleteSuggestion, AutocompleteSessionToken } = await google.maps.importLibrary('places');
                sessionToken = sessionToken || new AutocompleteSessionToken();
                ({ suggestions } = await withTimeout(AutocompleteSuggestion.fetchAutocompleteSuggestions({
                    input: text, includedRegionCodes: ['in'], sessionToken,
                }), 6000));
            } catch (e) {
                console.warn('Google search failed, using OpenStreetMap search', e);
            }
            if (suggestions) return suggestions.filter(s => s.placePrediction).map(s => ({
                label: s.placePrediction.text.toString(),
                resolve: async () => {
                    const place = s.placePrediction.toPlace();
                    await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents'] });
                    sessionToken = null;      // a session ends with the place lookup
                    const part = t => ((place.addressComponents || []).find(c => c.types.includes(t)) || {}).longText;
                    return Object.assign({ lat: place.location.lat(), lng: place.location.lng(), label: s.placePrediction.text.toString() },
                        parts(part('locality') || part('administrative_area_level_3'), part('administrative_area_level_1'), part('postal_code')));
                },
            }));
        }
        const res = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=in&limit=6&addressdetails=1&accept-language=en&q=${encodeURIComponent(text)}`);
        const list = await res.json();
        return list.map(p => ({
            label: p.display_name,
            resolve: async () => Object.assign({ lat: +p.lat, lng: +p.lon, label: p.display_name }, osmParts(p.address || {})),
        }));
    }

    /** City / state / pincode, used by checkout to fill the address form. */
    function parts(city, state, pincode) {
        pincode = String(pincode || '').replace(/\s/g, '');
        return { city: city || '', state: state || '', pincode: /^\d{6}$/.test(pincode) ? pincode : '' };
    }
    function osmParts(a) {
        return parts(a.city || a.town || a.village || a.municipality || a.county || a.state_district, a.state, a.postcode);
    }

    /** Place name (+ city/state/pincode) for a point, e.g. from GPS or a pin drag. */
    async function placeName(lat, lng) {
        if ((await ensureProvider()) === 'google') {
            try {
                const { Geocoder } = await google.maps.importLibrary('geocoding');
                const { results } = await withTimeout(new Geocoder().geocode({ location: { lat, lng } }), 6000);
                const r = results[0];
                if (r) {
                    const part = t => (r.address_components.find(c => c.types.includes(t)) || {}).long_name;
                    const label = [part('sublocality_level_1') || part('sublocality') || part('locality'), part('locality')]
                        .filter((v, i, a) => v && a.indexOf(v) === i).join(', ') || r.formatted_address;
                    return Object.assign({ label }, parts(part('locality') || part('administrative_area_level_3'), part('administrative_area_level_1'), part('postal_code')));
                }
            } catch (e) {
                console.warn('Google geocoding failed (Geocoding API enabled?), using OpenStreetMap', e);
            }
        }
        try {
            const a = (await (await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=en&lat=${lat}&lon=${lng}`)).json()).address || {};
            const label = [a.suburb || a.neighbourhood || a.village || a.town, a.city || a.town || a.county]
                .filter((v, i, arr) => v && arr.indexOf(v) === i).join(', ');
            return Object.assign({ label: label || 'Pinned location' }, osmParts(a));
        } catch (e) { /* the name is only for display */ }
        return { label: 'Pinned location', city: '', state: '', pincode: '' };
    }

    /* ── map with a draggable pin ─────────────────────────────────── */
    async function showMap(loc) {
        const p = await ensureProvider();
        const el = document.getElementById('tcLocMap');
        el.style.display = '';
        if (p === 'google') {
            if (!map) {
                const { Map } = await google.maps.importLibrary('maps');
                map = new Map(el, { center: loc, zoom: 15, streetViewControl: false, mapTypeControl: false, fullscreenControl: false });
                marker = new google.maps.Marker({ map, position: loc, draggable: true });
                marker.addListener('dragend', () => pick(marker.getPosition().lat(), marker.getPosition().lng()));
                map.addListener('click', e => pick(e.latLng.lat(), e.latLng.lng()));
            } else {
                map.setCenter(loc); marker.setPosition(loc);
            }
        } else {
            if (!map) {
                map = L.map(el).setView([loc.lat, loc.lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                marker = L.marker([loc.lat, loc.lng], { draggable: true }).addTo(map);
                marker.on('dragend', () => { const q = marker.getLatLng(); pick(q.lat, q.lng); });
                map.on('click', e => pick(e.latlng.lat, e.latlng.lng));
            } else {
                map.setView([loc.lat, loc.lng], 15); marker.setLatLng([loc.lat, loc.lng]);
            }
            setTimeout(() => map.invalidateSize(), 150);
        }
    }

    /** A point was chosen (GPS, pin drag, map click, search, saved address): name it and show it. */
    async function pick(lat, lng, label, extra) {
        pending = Object.assign({ lat: Math.round(lat * 1e6) / 1e6, lng: Math.round(lng * 1e6) / 1e6, label: label || 'Finding place name…' }, extra || {});
        renderPending();
        showMap(pending);
        if (!label || !pending.pincode) {
            const p = pending, named = await placeName(lat, lng);
            if (p !== pending) return;                 // a newer pick replaced this one
            pending = Object.assign({}, pending, {
                label:   label || named.label,
                city:    pending.city    || named.city,
                state:   pending.state   || named.state,
                pincode: pending.pincode || named.pincode,
            });
            renderPending();
        }
    }

    function renderPending() {
        document.getElementById('tcLocPicked').innerHTML = pending
            ? `<i class="fas fa-location-dot text-danger me-1"></i>${esc(pending.label)}`
            : '<span class="text-muted">Use your current location, search, or pick a saved address</span>';
        document.getElementById('tcLocConfirm').disabled = !pending;
    }

    /* ── the popup ────────────────────────────────────────────────── */
    function buildModal() {
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="tcLocModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold"><i class="fas fa-location-dot me-2"></i>Choose your location</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <button type="button" class="btn btn-outline-dark w-100 mb-2" id="tcLocGps">
                      <i class="fas fa-location-crosshairs me-1"></i> Use my current location
                    </button>
                    <input type="text" class="form-control mb-1" id="tcLocSearch" placeholder="Search area, landmark or pincode" autocomplete="off">
                    <div class="tc-loc-results mb-2" id="tcLocResults"></div>
                    <div id="tcLocSaved" class="mb-2"></div>
                    <div id="tcLocMap" style="display:none;"></div>
                    <div class="small mt-2" id="tcLocPicked"></div>
                    <div class="tc-loc-km mt-1">Drag the pin or tap the map to adjust.</div>
                  </div>
                  <div class="modal-footer py-2">
                    <button type="button" class="btn btn-primary w-100" id="tcLocConfirm" disabled>Confirm location</button>
                  </div>
                </div>
              </div>
            </div>`);
        modal = new bootstrap.Modal(document.getElementById('tcLocModal'));

        document.getElementById('tcLocGps').addEventListener('click', function () {
            if (!navigator.geolocation) { showToast('Your browser does not support location detection', 'error'); return; }
            const btn = this, orig = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Locating...';
            navigator.geolocation.getCurrentPosition(
                pos => { btn.disabled = false; btn.innerHTML = orig; pick(pos.coords.latitude, pos.coords.longitude); },
                err => {
                    btn.disabled = false; btn.innerHTML = orig;
                    showToast(err.code === err.PERMISSION_DENIED ? 'Location permission denied — search for your area instead' : 'Could not get your location', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000 });
        });

        document.getElementById('tcLocSearch').addEventListener('input', function () {
            clearTimeout(searchTimer);
            const text = this.value.trim(), box = document.getElementById('tcLocResults');
            if (text.length < 3) { box.innerHTML = ''; return; }
            searchTimer = setTimeout(async () => {
                try {
                    const list = await search(text);
                    box.innerHTML = list.length ? '' : '<div class="small text-muted p-2">No places found</div>';
                    list.forEach(item => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.innerHTML = `<i class="fas fa-location-dot text-muted me-2"></i>${esc(item.label)}`;
                        b.onclick = async () => {
                            box.innerHTML = '';
                            const loc = await item.resolve();
                            pick(loc.lat, loc.lng, loc.label, loc);
                        };
                        box.appendChild(b);
                    });
                } catch (e) {
                    box.innerHTML = '<div class="small text-muted p-2">Search is unavailable right now</div>';
                }
            }, 350);
        });

        document.getElementById('tcLocConfirm').addEventListener('click', confirmPick);
    }

    async function loadSavedAddresses() {
        const box = document.getElementById('tcLocSaved');
        if (!isLoggedIn()) { box.innerHTML = ''; return; }
        try {
            const d = await (await fetch(UP + 'api/customer/addresses.php')).json();
            const withPin = (d.data || []).filter(a => a.latitude && a.longitude);
            box.innerHTML = withPin.length ? '<div class="small fw-semibold text-muted mb-1">Saved addresses</div>' : '';
            withPin.slice(0, 4).forEach(a => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-light btn-sm w-100 text-start mb-1';
                b.innerHTML = `<i class="fas fa-house me-2 text-muted"></i>${esc(a.address_line1)}, ${esc(a.city)}`;
                b.onclick = () => pick(+a.latitude, +a.longitude, `${a.address_line1}, ${a.city}`, { city: a.city, state: a.state, pincode: a.pincode });
                box.appendChild(b);
            });
        } catch (e) { box.innerHTML = ''; }
    }

    async function confirmPick() {
        if (!pending || pending.label === 'Finding place name…') return;
        store(pending);
        if (isLoggedIn()) {
            try {
                await fetch(UP + 'api/customer/update-location.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ latitude: pending.lat, longitude: pending.lng, label: pending.label }),
                });
            } catch (e) { /* kept on the device anyway */ }
        }
        modal.hide();
        if (onPicked) {                // e.g. checkout: use it in place, keep what was typed
            const cb = onPicked;
            onPicked = null;
            cb(get());
            return;
        }
        window.location.reload();      // every page re-loads its distances for the new location
    }

    /** Open the chooser. callback(loc) instead of the default page reload. */
    function open(callback) {
        onPicked = typeof callback === 'function' ? callback : null;
        if (!modal) buildModal();
        const current = get();
        pending = current;
        document.getElementById('tcLocSearch').value = '';
        document.getElementById('tcLocResults').innerHTML = '';
        renderPending();
        loadSavedAddresses();
        modal.show();
        if (current) showMap(current);
    }

    /** Logged-in customer on a new device: start from the location saved on their profile. */
    async function restoreFromProfile() {
        if (get() || !isLoggedIn()) return;
        try {
            const p = (await (await fetch(UP + 'api/customer/my-profile.php')).json()).data || {};
            if (p.latitude && p.longitude) store({ lat: +p.latitude, lng: +p.longitude, label: p.location_label || 'Saved location' });
        } catch (e) { /* not logged in on the server */ }
    }

    /** Small read-only map with a pin (checkout's "Delivering to" card). */
    const previews = new Map();
    async function preview(el, loc) {
        if (!el || !loc) return;
        const p = await ensureProvider();
        const prev = previews.get(el);
        if (p === 'google') {
            if (prev) { prev.map.setCenter(loc); prev.marker.setPosition(loc); return; }
            const { Map: GMap } = await google.maps.importLibrary('maps');
            const m = new GMap(el, { center: loc, zoom: 15, disableDefaultUI: true, gestureHandling: 'none', clickableIcons: false });
            previews.set(el, { map: m, marker: new google.maps.Marker({ map: m, position: loc }) });
        } else {
            if (prev) { prev.map.setView([loc.lat, loc.lng], 15); prev.marker.setLatLng([loc.lat, loc.lng]); return; }
            const m = L.map(el, { zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
                                  boxZoom: false, keyboard: false, touchZoom: false, attributionControl: false }).setView([loc.lat, loc.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(m);
            previews.set(el, { map: m, marker: L.marker([loc.lat, loc.lng]).addTo(m) });
            setTimeout(() => m.invalidateSize(), 150);
        }
    }

    function init() {
        injectChip();
        restoreFromProfile();
    }

    return { init, open, get, query, preview };
})();

window.TcLocation = TcLocation;   // a top-level const is not a window property; checkout.js looks it up there
TcLocation.init();
