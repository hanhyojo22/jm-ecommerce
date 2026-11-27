/**
 * Province → city → barangay dropdowns for an address form, each narrowed by the one before it.
 *
 * Names are compared the way the server's PhilippineLocations::key does, so "Mandaue",
 * "Mandaue City" and "City of Mandaue" are one place. A saved name missing from the list
 * stays as an option, so older addresses still submit what they had.
 */
const placeKey = (name) => (name ?? '')
    .replace(/\(.*?\)/g, '')
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim()
    .replace(/^city of |\bcity$/g, '')
    .replace(/[^a-z0-9]/g, '');

const matchPlace = (names, name) => {
    const key = placeKey(name);

    return key === '' ? null : (names.find((candidate) => placeKey(candidate) === key) ?? null);
};

const withCurrent = (names, current) => (current && !names.includes(current) ? [current, ...names] : names);

const getJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Place list failed: ${response.status}`);

    return (await response.json()).data;
};

window.addressPlaces = (initial = {}) => ({
    province: initial.province ?? '',
    city: initial.city ?? '',
    barangay: initial.barangay ?? '',
    /** @type {Record<string, string[]>} province => cities */
    provinces: {},
    barangays: [],

    async init() {
        try {
            const provinces = await getJson('/api/v1/locations');
            this.provinces = Object.fromEntries(provinces.map((province) => [province.name, province.cities]));
        } catch {
            return; // The saved names stay selectable; only the lists are missing.
        }
        this.matchNames();
        await this.loadBarangays();
    },

    get provinceNames() { return withCurrent(Object.keys(this.provinces), this.province); },
    get cityNames() { return withCurrent(this.provinces[this.province] ?? [], this.city); },
    get barangayNames() { return withCurrent(this.barangays, this.barangay); },

    /** Fills the boxes from a saved address the customer picked. */
    async use(address) {
        this.province = address?.province ?? '';
        this.city = address?.city ?? '';
        this.barangay = address?.barangay ?? '';
        this.matchNames();
        await this.loadBarangays();
    },

    /** Puts a saved province and city in the listed spelling; a city found in one province only names it. */
    matchNames() {
        const named = matchPlace(Object.keys(this.provinces), this.province);
        const homes = Object.keys(this.provinces).filter((province) => matchPlace(this.provinces[province], this.city));
        const province = homes.length === 1 && !homes.includes(named) ? homes[0] : named;
        if (!province) return;
        this.province = province;
        this.city = matchPlace(this.provinces[province], this.city) ?? this.city;
    },

    async pickProvince() {
        const kept = matchPlace(this.provinces[this.province] ?? [], this.city);
        this.city = kept ?? '';
        if (!kept) this.barangay = '';
        await this.loadBarangays();
    },

    async pickCity() {
        this.barangay = '';
        await this.loadBarangays();
    },

    async loadBarangays() {
        const province = this.province;
        const city = this.city;
        this.barangays = [];
        if (!province || !city) return;
        try {
            const barangays = await getJson(`/api/v1/locations/barangays?${new URLSearchParams({ province, city })}`);
            if (province !== this.province || city !== this.city) return;
            this.barangays = barangays;
            this.barangay = matchPlace(barangays, this.barangay) ?? this.barangay;
        } catch {
            // The saved barangay stays selectable.
        }
    },
});
