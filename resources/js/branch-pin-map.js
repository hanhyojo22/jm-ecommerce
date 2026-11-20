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
    const pinFromInputs = () => {
        const lat = Number.parseFloat(latitude.value);
        const lng = Number.parseFloat(longitude.value);

        return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? [lat, lng] : null;
    };

    const start = pinFromInputs();
    const map = L.map(element).setView(start ?? COUNTRY_VIEW.center, start ? 17 : COUNTRY_VIEW.zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let marker = null;
    const placeMarker = (latLng) => {
        if (marker === null) {
            marker = L.marker(latLng, { draggable: true, keyboard: true, title: 'Branch location', alt: 'Branch location pin' }).addTo(map);
            marker.on('dragend', () => fillInputs(marker.getLatLng()));
        } else {
            marker.setLatLng(latLng);
        }
    };
    const fillInputs = ({ lat, lng }) => {
        latitude.value = lat.toFixed(7);
        longitude.value = lng.toFixed(7);
        // Bubbling input marks the form as changed, the same as typing would.
        latitude.dispatchEvent(new Event('input', { bubbles: true }));
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
        }
    };
    latitude.addEventListener('change', followInputs);
    longitude.addEventListener('change', followInputs);
    element.addEventListener('branch-pin:moved', followInputs);
}
