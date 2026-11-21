import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Bundled builds lose Leaflet's guessed image paths, so the pin images are given directly.
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

/** The middle of the Philippines, for a branch that has no pin yet. */
const COUNTRY_VIEW = { center: [12.8797, 121.774], zoom: 5 };

/**
 * A map beside the branch's latitude and longitude boxes: tapping the map or dragging the pin
 * fills the boxes, and typing in the boxes (or "Use my current location") moves the pin.
 */
export function mountBranchPinMap(element) {
    const latitude = document.getElementById(element.dataset.latitudeInput);
    const longitude = document.getElementById(element.dataset.longitudeInput);
    const radiusInput = element.dataset.radiusInput ? document.getElementById(element.dataset.radiusInput) : null;
    const pinFromInputs = () => {
        const lat = Number.parseFloat(latitude.value);
        const lng = Number.parseFloat(longitude.value);

        return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? [lat, lng] : null;
    };
    const radiusMetersFromInput = () => {
        const km = Number.parseFloat(radiusInput?.value);

        return Number.isFinite(km) && km > 0 && km <= 500 ? km * 1000 : null;
    };

    const start = pinFromInputs();
    const map = L.map(element).setView(start ?? COUNTRY_VIEW.center, start ? 17 : COUNTRY_VIEW.zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let marker = null;
    let circle = null;
    const syncRadiusCircle = (latLng) => {
        const meters = radiusMetersFromInput();
        if (! latLng || meters === null) {
            if (circle !== null) {
                circle.remove();
                circle = null;
            }
            return;
        }
        if (circle === null) {
            circle = L.circle(latLng, {
                radius: meters,
                color: '#2563eb',
                weight: 1.5,
                opacity: 0.75,
                fillColor: '#2563eb',
                fillOpacity: 0.1,
                interactive: false,
            }).addTo(map);
        } else {
            circle.setLatLng(latLng);
            circle.setRadius(meters);
        }
    };
    const placeMarker = (latLng) => {
        if (marker === null) {
            marker = L.marker(latLng, { draggable: true, keyboard: true, title: 'Branch location', alt: 'Branch location pin' }).addTo(map);
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                syncRadiusCircle(pos);
                fillInputs(pos);
            });
        } else {
            marker.setLatLng(latLng);
        }
        syncRadiusCircle(latLng);
    };
    const fillInputs = ({ lat, lng }) => {
        latitude.value = lat.toFixed(7);
        longitude.value = lng.toFixed(7);
        // Bubbling input marks the form as changed and keeps Alpine x-model in sync.
        latitude.dispatchEvent(new Event('input', { bubbles: true }));
        longitude.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const clearPin = () => {
        if (marker !== null) {
            marker.remove();
            marker = null;
        }
        if (circle !== null) {
            circle.remove();
            circle = null;
        }
        map.setView(COUNTRY_VIEW.center, COUNTRY_VIEW.zoom);
    };

    if (start) {
        placeMarker(start);
    }

    map.on('click', ({ latlng }) => {
        placeMarker(latlng);
        fillInputs(latlng);
    });

    const followInputs = () => {
        const pin = pinFromInputs();
        if (pin) {
            placeMarker(pin);
            map.setView(pin, Math.max(map.getZoom(), 17));
        } else if (latitude.value.trim() === '' && longitude.value.trim() === '') {
            clearPin();
        }
    };
    const followRadius = () => {
        if (marker !== null) {
            syncRadiusCircle(marker.getLatLng());
        }
    };

    latitude.addEventListener('change', followInputs);
    longitude.addEventListener('change', followInputs);
    radiusInput?.addEventListener('input', followRadius);
    radiusInput?.addEventListener('change', followRadius);
    element.addEventListener('branch-pin:moved', followInputs);
    element.addEventListener('branch-pin:radius', followRadius);
    element.addEventListener('branch-pin:cleared', clearPin);

    let resizeFrame = null;
    const scheduleResize = () => {
        if (resizeFrame !== null) {
            return;
        }
        resizeFrame = window.requestAnimationFrame(() => {
            resizeFrame = null;
            if (element.clientWidth > 0 && element.clientHeight > 0) {
                map.invalidateSize();
            }
        });
    };

    element.addEventListener('branch-pin:resize', scheduleResize);
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(scheduleResize).observe(element);
    }

    const section = element.closest('details');
    section?.addEventListener('toggle', () => {
        if (section.open) {
            scheduleResize();
        }
    });
}
