/*
 * Full-screen photo viewer for chat attachments: wheel, pinch, or double-click
 * to zoom, drag to pan while zoomed, keyboard +/-/0, Esc to close. Opened by a
 * window `chat-image-open` event carrying { src, name }, so the trigger can sit
 * inside Livewire-morphed markup while the dialog itself stays wire:ignore'd.
 */
const MIN_SCALE = 1;
const MAX_SCALE = 5;
const DOUBLE_TAP_SCALE = 2.5;

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

window.imageViewer = () => ({
    src: '',
    name: '',
    scale: 1,
    x: 0,
    y: 0,
    animate: false,
    loaded: false,
    failed: false,
    pointers: new Map(),
    pinch: null,
    pan: null,
    moved: false,
    pressedStage: false,

    open({ src, name }) {
        this.src = src;
        this.name = name || 'Photo';
        this.loaded = false;
        this.failed = false;
        this.reset(false);
        this.$refs.dialog.showModal();
        this.$nextTick(() => this.$refs.close.focus());
    },

    closed() {
        this.src = '';
        this.pointers.clear();
        this.pinch = null;
        this.pan = null;
    },

    get percent() {
        return `${Math.round(this.scale * 100)}%`;
    },

    get transform() {
        return `translate3d(${this.x}px, ${this.y}px, 0) scale(${this.scale})`;
    },

    reset(animate = true) {
        this.animate = animate;
        this.scale = 1;
        this.x = 0;
        this.y = 0;
    },

    /** The point under (clientX, clientY), relative to the stage centre. */
    local(clientX, clientY) {
        const rect = this.$refs.stage.getBoundingClientRect();

        return { cx: clientX - (rect.left + rect.width / 2), cy: clientY - (rect.top + rect.height / 2) };
    },

    /** Keeps the photo from being dragged away from the stage. */
    clampPan() {
        const image = this.$refs.image;
        const maxX = Math.max(0, ((this.scale - 1) * image.offsetWidth) / 2);
        const maxY = Math.max(0, ((this.scale - 1) * image.offsetHeight) / 2);
        this.x = clamp(this.x, -maxX, maxX);
        this.y = clamp(this.y, -maxY, maxY);
    },

    /** Zooms to `next`, keeping the stage point (cx, cy) fixed under the cursor or fingers. */
    zoomTo(next, cx = 0, cy = 0, animate = false) {
        const scale = clamp(next, MIN_SCALE, MAX_SCALE);
        const ratio = scale / this.scale;
        this.animate = animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.x = cx - (cx - this.x) * ratio;
        this.y = cy - (cy - this.y) * ratio;
        this.scale = scale;
        if (scale === MIN_SCALE) {
            this.x = 0;
            this.y = 0;
        }
        this.clampPan();
    },

    step(direction) {
        this.zoomTo(this.scale * (direction > 0 ? 1.5 : 1 / 1.5), 0, 0, true);
    },

    wheel(event) {
        const { cx, cy } = this.local(event.clientX, event.clientY);
        // Trackpad pinches arrive as ctrl+wheel with small deltas; both scale smoothly.
        this.zoomTo(this.scale * Math.exp(-event.deltaY * (event.ctrlKey ? 0.01 : 0.0015)), cx, cy);
    },

    doubleClick(event) {
        const { cx, cy } = this.local(event.clientX, event.clientY);
        this.zoomTo(this.scale > 1.01 ? 1 : DOUBLE_TAP_SCALE, cx, cy, true);
    },

    pointerDown(event) {
        // Pointer capture retargets the later click to the stage, so remember where the press began.
        this.pressedStage = event.target === this.$refs.stage;
        this.$refs.stage.setPointerCapture(event.pointerId);
        this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        this.moved = false;
        this.animate = false;

        if (this.pointers.size === 2) {
            const [a, b] = [...this.pointers.values()];
            const mid = this.local((a.x + b.x) / 2, (a.y + b.y) / 2);
            this.pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), scale: this.scale, ...mid };
            this.pan = null;
        } else if (this.pointers.size === 1) {
            this.pan = { x: event.clientX - this.x, y: event.clientY - this.y };
        }
    },

    pointerMove(event) {
        if (!this.pointers.has(event.pointerId)) {
            return;
        }
        const previous = this.pointers.get(event.pointerId);
        if (Math.hypot(event.clientX - previous.x, event.clientY - previous.y) > 3) {
            this.moved = true;
        }
        this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (this.pinch && this.pointers.size === 2) {
            const [a, b] = [...this.pointers.values()];
            const distance = Math.hypot(a.x - b.x, a.y - b.y);
            this.zoomTo((this.pinch.scale * distance) / this.pinch.distance, this.pinch.cx, this.pinch.cy);
            this.pinch.scale = this.scale;
            this.pinch.distance = distance;
        } else if (this.pan && this.scale > 1) {
            this.x = event.clientX - this.pan.x;
            this.y = event.clientY - this.pan.y;
            this.clampPan();
        }
    },

    pointerUp(event) {
        this.pointers.delete(event.pointerId);
        if (this.pointers.size < 2) {
            this.pinch = null;
        }
        if (this.pointers.size === 1) {
            const [remaining] = [...this.pointers.values()];
            this.pan = { x: remaining.x - this.x, y: remaining.y - this.y };
        } else if (this.pointers.size === 0) {
            this.pan = null;
        }
    },

    /** A plain click on the dark area around the photo closes the viewer. */
    stageClick() {
        if (!this.moved && this.pressedStage && this.scale === 1) {
            this.$refs.dialog.close();
        }
    },

    keydown(event) {
        if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            this.step(1);
        } else if (event.key === '-' || event.key === '_') {
            event.preventDefault();
            this.step(-1);
        } else if (event.key === '0') {
            event.preventDefault();
            this.reset();
        }
    },
});

/** Opens chat photos in the viewer, leaving modified clicks to open a new tab as usual. */
window.openChatImage = (event, src, name) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    event.preventDefault();
    window.dispatchEvent(new CustomEvent('chat-image-open', { detail: { src, name } }));
};
