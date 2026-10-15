/**
 * Cart item selection.
 *
 * The server is the source of truth: every tick is persisted so the choice
 * survives the full page reload that a quantity change causes. Totals update
 * optimistically from integer centavos held in the DOM, so no float rounding
 * creeps into money the buyer reads.
 */
window.cartSelection = (config) => ({
    selected: new Set(config.selected),
    saving: false,
    error: '',
    saveTimer: null,

    /**
     * `$el` points at whichever element fired the handler, so the component
     * root is captured once here and used for every later DOM lookup.
     */
    root: null,

    init() {
        this.root = this.$el;
        this.$nextTick(() => this.syncGroupCheckboxes());
    },

    /** Every item that can still be bought. Unavailable rows are never selectable. */
    get selectableItems() {
        return config.items.filter((item) => item.available);
    },

    get selectedItems() {
        return this.selectableItems.filter((item) => this.selected.has(item.id));
    },

    get selectedCount() {
        return this.selectedItems.length;
    },

    get selectedQuantity() {
        return this.selectedItems.reduce((total, item) => total + item.quantity, 0);
    },

    get selectedQuantityLabel() {
        const quantity = this.selectedQuantity;
        return `${quantity} ${quantity === 1 ? 'item' : 'items'}`;
    },

    get allSelected() {
        return this.selectableItems.length > 0 && this.selectedCount === this.selectableItems.length;
    },

    get subtotalLabel() {
        const centavos = this.selectedItems.reduce((total, item) => total + item.centavos, 0);
        return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            .format(centavos / 100);
    },

    isSelected(id) {
        return this.selected.has(id);
    },

    toggleItem(id) {
        if (this.selected.has(id)) {
            this.selected.delete(id);
        } else {
            this.selected.add(id);
        }
        this.afterChange();
    },

    storeItems(storeId) {
        return this.selectableItems.filter((item) => item.storeId === storeId);
    },

    storeSelectedCount(storeId) {
        return this.storeItems(storeId).filter((item) => this.selected.has(item.id)).length;
    },

    toggleStore(storeId) {
        const items = this.storeItems(storeId);
        const shouldSelect = this.storeSelectedCount(storeId) < items.length;

        for (const item of items) {
            if (shouldSelect) {
                this.selected.add(item.id);
            } else {
                this.selected.delete(item.id);
            }
        }
        this.afterChange();
    },

    toggleAll() {
        if (this.allSelected) {
            this.selected.clear();
        } else {
            for (const item of this.selectableItems) {
                this.selected.add(item.id);
            }
        }
        this.afterChange();
    },

    /**
     * A partly-ticked group shows the native indeterminate dash, which no
     * attribute can express — it has to be set on the element itself.
     */
    syncGroupCheckboxes() {
        for (const box of (this.root ?? document).querySelectorAll('[data-select-all]')) {
            box.indeterminate = this.selectedCount > 0 && !this.allSelected;
        }

        for (const box of (this.root ?? document).querySelectorAll('[data-store-checkbox]')) {
            const count = this.storeSelectedCount(box.dataset.storeCheckbox);
            const total = this.storeItems(box.dataset.storeCheckbox).length;
            box.indeterminate = count > 0 && count < total;
            box.checked = total > 0 && count === total;
        }
    },

    afterChange() {
        this.syncGroupCheckboxes();
        this.persist();
    },

    persist() {
        clearTimeout(this.saveTimer);
        this.saving = true;
        this.error = '';
        this.saveTimer = setTimeout(() => this.send(), 350);
    },

    async send() {
        try {
            const response = await fetch(config.endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ selected: [...this.selected] }),
            });

            if (!response.ok) {
                throw new Error('Selection could not be saved');
            }
        } catch {
            this.error = 'Your selection could not be saved. Check your connection.';
        } finally {
            this.saving = false;
        }
    },
});
