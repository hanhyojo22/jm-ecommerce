<x-app-layout>
    <style>
        /* Quiet dossier order-details dialog. Tokens come from resources/css/app.css. */
        dialog.buyer-order-dialog { position: fixed; inset: 0; margin: auto; width: min(760px, calc(100vw - 40px)); max-height: calc(100dvh - 40px); display: flex; flex-direction: column; border: 0; border-radius: 16px; padding: 0; background: var(--color-surface); color: var(--color-fg); box-shadow: 0 36px 90px rgb(15 23 42 / 24%), 0 4px 16px rgb(15 23 42 / 8%); overflow: hidden; }
        dialog.buyer-order-dialog:not([open]) { display: none; }
        dialog.buyer-order-dialog[open] { animation: buyer-dialog-in .2s cubic-bezier(.2, .8, .2, 1); }
        @keyframes buyer-dialog-in { from { opacity: 0; transform: translateY(10px) scale(.986); } to { opacity: 1; transform: none; } }
        dialog.buyer-order-dialog::backdrop { background: rgb(15 23 42 / 44%); backdrop-filter: blur(2px); }
        @media (max-width: 720px) {
            dialog.buyer-order-dialog { width: 100vw; max-width: 100vw; height: 100dvh; max-height: 100dvh; border-radius: 0; margin: 0; }
            dialog.buyer-order-dialog::backdrop { background: transparent; backdrop-filter: none; }
        }
        @media (prefers-reduced-motion: reduce) { dialog.buyer-order-dialog[open] { animation: none; } }

        dialog.buyer-order-dialog .buyer-modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 20px 24px 24px; overscroll-behavior: contain; }

        dialog.buyer-order-dialog .dialog-head { flex: none; padding: 20px 24px 0; background: linear-gradient(180deg, #fff 0%, #fbfcff 100%); border-bottom: 1px solid var(--color-line-soft); }
        dialog.buyer-order-dialog .dialog-head.no-tracker { padding-bottom: 18px; }
        dialog.buyer-order-dialog .head-top { display: flex; align-items: flex-start; gap: 12px; }
        dialog.buyer-order-dialog .head-id { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; min-width: 0; }
        dialog.buyer-order-dialog .eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; color: var(--color-muted); white-space: nowrap; }
        dialog.buyer-order-dialog .eyebrow svg { width: 14px; height: 14px; flex: none; }
        dialog.buyer-order-dialog .order-ref { font-size: 12px; font-weight: 650; letter-spacing: -.01em; color: var(--color-fg); white-space: nowrap; }
        dialog.buyer-order-dialog .copy-ref { display: inline-grid; place-items: center; width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--color-muted); cursor: pointer; touch-action: manipulation; transition: background .15s, color .15s; }
        dialog.buyer-order-dialog .copy-ref:hover { background: var(--color-sunken); color: var(--color-fg); }
        dialog.buyer-order-dialog .copy-ref.is-copied { color: var(--color-success); background: var(--color-success-wash); }
        dialog.buyer-order-dialog .copy-ref svg { width: 14px; height: 14px; }
        dialog.buyer-order-dialog .head-actions { display: flex; align-items: center; gap: 2px; margin-left: auto; flex: none; }
        dialog.buyer-order-dialog .icon-btn { display: grid; place-items: center; width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--color-muted); cursor: pointer; touch-action: manipulation; transition: background .15s, color .15s; }
        dialog.buyer-order-dialog .icon-btn:hover { background: var(--color-sunken); color: var(--color-fg); }
        dialog.buyer-order-dialog .icon-btn svg { width: 18px; height: 18px; }

        dialog.buyer-order-dialog .head-status { display: flex; align-items: flex-start; gap: 12px; margin-top: 12px; }
        dialog.buyer-order-dialog .status-icon { display: grid; place-items: center; width: 42px; height: 42px; flex: none; border-radius: 12px; }
        dialog.buyer-order-dialog .status-icon svg { width: 21px; height: 21px; }
        dialog.buyer-order-dialog .status-icon.moving { background: var(--color-info-wash); color: var(--color-accent); }
        dialog.buyer-order-dialog .status-icon.good { background: var(--color-success-wash); color: var(--color-success); }
        dialog.buyer-order-dialog .status-icon.warn { background: var(--color-warning-wash); color: var(--color-warning-text); }
        dialog.buyer-order-dialog .status-icon.danger { background: var(--color-danger-wash); color: var(--color-danger); }
        dialog.buyer-order-dialog .status-copy { min-width: 0; flex: 1; }
        dialog.buyer-order-dialog .status-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        dialog.buyer-order-dialog .status-title { font-size: 22px; font-weight: 650; line-height: 1.18; letter-spacing: -.025em; color: var(--color-fg); text-wrap: balance; }
        dialog.buyer-order-dialog .status-sub { max-width: 58ch; margin-top: 4px; font-size: 12.5px; line-height: 1.5; color: var(--color-muted); }
        dialog.buyer-order-dialog .head-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 2px 0; margin-top: 7px; font-size: 11.5px; color: var(--color-muted); }
        dialog.buyer-order-dialog .meta-dot { padding: 0 6px; color: var(--color-border-strong); }

        dialog.buyer-order-dialog .pill { display: inline-flex; align-items: center; gap: 5px; flex: none; max-width: 100%; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 650; line-height: 1.4; white-space: nowrap; }
        dialog.buyer-order-dialog .pill i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        dialog.buyer-order-dialog .pill-neutral { background: var(--color-sunken); color: var(--color-muted); box-shadow: inset 0 0 0 1px var(--color-line-soft); }
        dialog.buyer-order-dialog .pill-moving { background: var(--color-info-wash); color: var(--color-accent-hover); box-shadow: inset 0 0 0 1px var(--color-info-line); }
        dialog.buyer-order-dialog .pill-good { background: var(--color-success-wash); color: var(--color-success); box-shadow: inset 0 0 0 1px var(--color-success-line); }
        dialog.buyer-order-dialog .pill-warn { background: var(--color-warning-wash); color: var(--color-warning-text); box-shadow: inset 0 0 0 1px var(--color-warning-line); }
        dialog.buyer-order-dialog .pill-danger { background: var(--color-danger-wash); color: var(--color-danger); box-shadow: inset 0 0 0 1px var(--color-danger-line); }

        dialog.buyer-order-dialog .tracker { display: grid; grid-template-columns: repeat(var(--steps, 4), minmax(0, 1fr)); margin: 15px 0 0; padding: 0; list-style: none; }
        dialog.buyer-order-dialog .tracker-step { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 0; text-align: center; }
        dialog.buyer-order-dialog .tracker-step::before { content: ''; position: absolute; top: 11px; right: 50%; z-index: 0; width: 100%; height: 2px; background: var(--color-line-soft); }
        dialog.buyer-order-dialog .tracker-step:first-child::before { display: none; }
        dialog.buyer-order-dialog .tracker-step.done::before, dialog.buyer-order-dialog .tracker-step.current::before { background: #b9cdf2; }
        dialog.buyer-order-dialog .tracker-step.partial::before { background: linear-gradient(90deg, #b9cdf2 50%, var(--color-line-soft) 50%); }
        dialog.buyer-order-dialog .node { position: relative; z-index: 1; display: grid; place-items: center; width: 24px; height: 24px; border: 2px solid var(--color-border-strong); border-radius: 50%; background: var(--color-surface); color: transparent; transition: background .18s, border-color .18s, color .18s; }
        dialog.buyer-order-dialog .node svg { width: 12px; height: 12px; }
        dialog.buyer-order-dialog .tracker-step.done .node { border-color: var(--color-accent); background: var(--color-accent); color: #fff; }
        dialog.buyer-order-dialog .tracker-step.current .node { border-color: var(--color-accent); color: var(--color-accent); box-shadow: 0 0 0 4px var(--color-info-wash); }
        dialog.buyer-order-dialog .tracker-step.current .node::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        dialog.buyer-order-dialog .tracker-step.partial .node { border-color: var(--color-success); background: conic-gradient(var(--color-success) 0 50%, #fff 50% 100%); box-shadow: inset 0 0 0 3px #fff; }
        dialog.buyer-order-dialog .tracker-step.done.final .node { border-color: var(--color-success); background: var(--color-success); }
        dialog.buyer-order-dialog .tracker-step.done.final .step-label { color: var(--color-success); }
        dialog.buyer-order-dialog .step-label { overflow: hidden; max-width: 100%; font-size: 10.5px; font-weight: 650; line-height: 1.25; color: var(--color-muted); text-overflow: ellipsis; white-space: nowrap; }
        dialog.buyer-order-dialog .tracker-step.done .step-label { color: var(--color-fg); }
        dialog.buyer-order-dialog .tracker-step.current .step-label { color: var(--color-accent-hover); }
        dialog.buyer-order-dialog .step-date { font-size: 9.5px; font-weight: 500; line-height: 1.2; color: var(--color-muted); white-space: nowrap; }

        dialog.buyer-order-dialog .dialog-body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 24px 22px; background: var(--color-surface); overscroll-behavior: contain; }
        dialog.buyer-order-dialog .dialog-foot { display: flex; align-items: center; gap: 8px; flex: none; padding: 12px 24px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid var(--color-border); background: #fff; }
        dialog.buyer-order-dialog .foot-left { display: flex; align-items: center; gap: 7px; min-width: 0; margin-right: auto; }
        dialog.buyer-order-dialog .foot-actions { display: flex; align-items: center; gap: 8px; flex: none; }
        dialog.buyer-order-dialog .foot-help { font-size: 11.5px; color: var(--color-muted); white-space: nowrap; }
        dialog.buyer-order-dialog .foot-help a { font-weight: 600; color: var(--color-accent); text-underline-offset: 2px; }
        dialog.buyer-order-dialog .foot-help a:hover { text-decoration: underline; }

        dialog.buyer-order-dialog .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 13px; border: 1px solid transparent; border-radius: 8px; font-size: 12.5px; font-weight: 650; line-height: 1; white-space: nowrap; text-decoration: none; cursor: pointer; touch-action: manipulation; transition: background .15s, border-color .15s, color .15s, transform .1s; }
        dialog.buyer-order-dialog .btn svg { width: 15px; height: 15px; flex: none; }
        dialog.buyer-order-dialog .btn:active { transform: translateY(1px); }
        dialog.buyer-order-dialog .btn-primary { background: var(--color-accent); color: #fff; box-shadow: 0 1px 2px rgb(32 91 244 / 22%); }
        dialog.buyer-order-dialog .btn-primary:hover { background: var(--color-accent-hover); }
        dialog.buyer-order-dialog .btn-outline { border-color: var(--color-border); background: #fff; color: var(--color-fg); }
        dialog.buyer-order-dialog .btn-outline:hover { border-color: var(--color-border-strong); background: var(--color-sunken); }
        dialog.buyer-order-dialog .btn-ghost { background: transparent; color: var(--color-muted); }
        dialog.buyer-order-dialog .btn-ghost:hover { background: var(--color-sunken); color: var(--color-fg); }
        dialog.buyer-order-dialog .btn:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

        dialog.buyer-order-dialog .notice { display: flex; align-items: flex-start; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; padding: 11px 13px; border: 1px solid; border-radius: 10px; }
        dialog.buyer-order-dialog .notice-warn { border-color: var(--color-warning-line); background: var(--color-warning-wash); }
        dialog.buyer-order-dialog .notice-danger { border-color: var(--color-danger-line); background: var(--color-danger-wash); }
        dialog.buyer-order-dialog .notice-info { border-color: var(--color-info-line); background: var(--color-info-wash); }
        dialog.buyer-order-dialog .notice-icon { display: grid; place-items: center; width: 20px; height: 20px; flex: none; margin-top: 1px; }
        dialog.buyer-order-dialog .notice-icon svg { width: 18px; height: 18px; }
        dialog.buyer-order-dialog .notice-warn .notice-icon { color: var(--color-warning-text); }
        dialog.buyer-order-dialog .notice-danger .notice-icon { color: var(--color-danger); }
        dialog.buyer-order-dialog .notice-info .notice-icon { color: var(--color-accent); }
        dialog.buyer-order-dialog .notice-body { flex: 1; min-width: 180px; }
        dialog.buyer-order-dialog .notice-title { font-size: 12.5px; font-weight: 650; line-height: 1.4; color: var(--color-fg); }
        dialog.buyer-order-dialog .notice-copy { margin-top: 2px; font-size: 12px; line-height: 1.5; color: var(--color-muted); }
        dialog.buyer-order-dialog .notice-copy a { font-weight: 650; color: var(--color-accent); text-underline-offset: 2px; }
        dialog.buyer-order-dialog .notice-copy a:hover { text-decoration: underline; }
        dialog.buyer-order-dialog .notice-action { margin-left: 30px; }

        dialog.buyer-order-dialog .cancelled-line { display: flex; align-items: center; gap: 7px; margin-bottom: 14px; padding: 9px 12px; border-radius: 8px; background: var(--color-danger-wash); color: var(--color-danger); font-size: 12px; font-weight: 600; }
        dialog.buyer-order-dialog .cancelled-line svg { width: 15px; height: 15px; flex: none; }

        dialog.buyer-order-dialog .shipments { display: flex; flex-direction: column; gap: 10px; }
        dialog.buyer-order-dialog .shipment { overflow: hidden; border: 1px solid var(--color-border); border-radius: 12px; background: #fff; }
        dialog.buyer-order-dialog .shipment-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 13px; background: var(--color-sunken); border-bottom: 1px solid var(--color-line-soft); }
        dialog.buyer-order-dialog .store-mark { display: grid; place-items: center; width: 30px; height: 30px; flex: none; border-radius: 8px; background: var(--mark-bg, #e8eef8); color: var(--mark-ink, #31507c); font-size: 10.5px; font-weight: 700; letter-spacing: .02em; }
        dialog.buyer-order-dialog .store-copy { min-width: 0; flex: 1; }
        dialog.buyer-order-dialog .store-name { overflow: hidden; font-size: 12.5px; font-weight: 650; line-height: 1.3; color: var(--color-fg); text-overflow: ellipsis; white-space: nowrap; }
        dialog.buyer-order-dialog .store-meta { margin-top: 1px; font-size: 11px; color: var(--color-muted); }
        dialog.buyer-order-dialog .shipment .pill { margin-left: auto; }
        dialog.buyer-order-dialog .item { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; align-items: center; gap: 11px; padding: 11px 13px; }
        dialog.buyer-order-dialog .item + .item { border-top: 1px solid var(--color-line-soft); }
        dialog.buyer-order-dialog .thumb { position: relative; display: block; width: 44px; height: 44px; overflow: hidden; border: 1px solid var(--color-line-soft); border-radius: 8px; background: var(--color-paper); }
        dialog.buyer-order-dialog .thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
        dialog.buyer-order-dialog .thumb svg { display: block; width: 100%; height: 100%; color: #94a3b8; }
        dialog.buyer-order-dialog .item-copy { min-width: 0; }
        dialog.buyer-order-dialog .item-title { font-size: 12.5px; font-weight: 600; line-height: 1.35; color: var(--color-fg); overflow-wrap: anywhere; }
        dialog.buyer-order-dialog .item-meta { margin-top: 1px; font-size: 11px; line-height: 1.45; color: var(--color-muted); overflow-wrap: anywhere; }
        dialog.buyer-order-dialog .item-amount { align-self: start; padding-top: 1px; font-size: 12.5px; font-weight: 650; text-align: right; white-space: nowrap; color: var(--color-fg); }
        dialog.buyer-order-dialog .shipment-foot { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 9px 13px; border-top: 1px solid var(--color-line-soft); background: #fcfdff; }
        dialog.buyer-order-dialog .subtotal-label { font-size: 11px; color: var(--color-muted); }
        dialog.buyer-order-dialog .subtotal-value { font-size: 12.5px; font-weight: 650; color: var(--color-fg); }
        dialog.buyer-order-dialog .shipment-action { margin-left: auto; }

        dialog.buyer-order-dialog .facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
        dialog.buyer-order-dialog .fact { min-width: 0; padding: 13px; border: 1px solid var(--color-border); border-radius: 12px; }
        dialog.buyer-order-dialog .fact-label { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 700; letter-spacing: .11em; text-transform: uppercase; color: var(--color-muted); }
        dialog.buyer-order-dialog .fact-label svg { width: 14px; height: 14px; }
        dialog.buyer-order-dialog .fact-main { margin-top: 7px; font-size: 12.5px; font-weight: 650; line-height: 1.4; color: var(--color-fg); }
        dialog.buyer-order-dialog .fact-line { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 2px; font-size: 11.5px; line-height: 1.5; color: var(--color-muted); overflow-wrap: anywhere; }
        dialog.buyer-order-dialog .fact-note { margin-top: 8px; padding: 7px 9px; border-radius: 7px; background: var(--color-sunken); border-left: 2px solid var(--color-border-strong); font-size: 11.5px; line-height: 1.5; color: var(--color-muted); }
        dialog.buyer-order-dialog .dot { display: inline-block; width: 6px; height: 6px; flex: none; border-radius: 50%; background: currentColor; }
        dialog.buyer-order-dialog .dot-good { color: var(--color-success); background: var(--color-success); }
        dialog.buyer-order-dialog .dot-warn { color: var(--color-warning-text); background: var(--color-warning); }
        dialog.buyer-order-dialog .dot-neutral { color: var(--color-muted); background: var(--color-muted); }
        dialog.buyer-order-dialog .dot-danger { color: var(--color-danger); background: var(--color-danger); }
        dialog.buyer-order-dialog .copy-mini { display: inline-grid; place-items: center; width: 24px; height: 24px; border: 0; border-radius: 5px; background: transparent; color: var(--color-muted); cursor: pointer; touch-action: manipulation; }
        dialog.buyer-order-dialog .copy-mini:hover { background: var(--color-sunken); color: var(--color-fg); }
        dialog.buyer-order-dialog .copy-mini.is-copied { color: var(--color-success); }
        dialog.buyer-order-dialog .copy-mini svg { width: 12px; height: 12px; }

        dialog.buyer-order-dialog .receipt { margin-top: 14px; overflow: hidden; border: 1px solid var(--color-border); border-radius: 12px; }
        dialog.buyer-order-dialog .receipt-rows { margin: 0; padding: 12px 14px; }
        dialog.buyer-order-dialog .row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-size: 12px; }
        dialog.buyer-order-dialog .row + .row { margin-top: 6px; }
        dialog.buyer-order-dialog .row dt { color: var(--color-muted); }
        dialog.buyer-order-dialog .row dd { margin: 0; font-weight: 600; color: var(--color-fg); }
        dialog.buyer-order-dialog .receipt-total { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 12px 14px; border-top: 1px solid var(--color-border); background: var(--color-sunken); }
        dialog.buyer-order-dialog .receipt-total .label { font-size: 12.5px; font-weight: 600; color: var(--color-fg); }
        dialog.buyer-order-dialog .receipt-total .value { font-size: 24px; font-weight: 650; line-height: 1; letter-spacing: -.035em; color: var(--color-fg); }
        dialog.buyer-order-dialog .receipt-note { display: flex; align-items: center; gap: 6px; padding: 0 14px 12px; background: var(--color-sunken); font-size: 11px; color: var(--color-muted); }
        dialog.buyer-order-dialog .receipt-note svg { width: 13px; height: 13px; flex: none; }
        dialog.buyer-order-dialog .num { font-variant-numeric: tabular-nums; }

        @media (max-width: 720px) {
            dialog.buyer-order-dialog .dialog-head { padding: 44px 16px 0; }
            dialog.buyer-order-dialog .dialog-head.no-tracker { padding-bottom: 16px; }
            dialog.buyer-order-dialog .dialog-body { padding: 14px 16px 16px; }
            dialog.buyer-order-dialog .dialog-foot { flex-direction: column; align-items: stretch; gap: 8px; padding: 10px 16px 20px; }
            dialog.buyer-order-dialog .foot-left { margin-right: 0; width: 100%; }
            dialog.buyer-order-dialog .foot-actions { width: 100%; }
            dialog.buyer-order-dialog .foot-actions .btn { flex: 1; min-height: 44px; }
            dialog.buyer-order-dialog .foot-help { display: none; }
            dialog.buyer-order-dialog .icon-btn { width: 44px; height: 44px; }
            dialog.buyer-order-dialog .copy-ref { width: 32px; height: 32px; }
            dialog.buyer-order-dialog .status-icon { width: 40px; height: 40px; border-radius: 11px; }
            dialog.buyer-order-dialog .status-title { font-size: 20px; }
            dialog.buyer-order-dialog .status-sub { font-size: 12px; }
            dialog.buyer-order-dialog .step-label { font-size: 9.5px; }
            dialog.buyer-order-dialog .step-date { display: none; }
            dialog.buyer-order-dialog .tracker-step::before { top: 10px; }
            dialog.buyer-order-dialog .node { width: 22px; height: 22px; }
            dialog.buyer-order-dialog .node svg { width: 11px; height: 11px; }
            dialog.buyer-order-dialog .facts { grid-template-columns: 1fr; }
            dialog.buyer-order-dialog .notice-action { margin-left: 0; width: 100%; min-height: 44px; }
            dialog.buyer-order-dialog .head-meta { font-size: 11px; }
        }
        @media (max-width: 400px) {
            dialog.buyer-order-dialog .shipment-action { width: 100%; margin-left: 0; }
            dialog.buyer-order-dialog .shipment-action .btn { width: 100%; min-height: 40px; }
        }
        @media (prefers-reduced-motion: reduce) {
            dialog.buyer-order-dialog .copy-ref, dialog.buyer-order-dialog .icon-btn, dialog.buyer-order-dialog .btn, dialog.buyer-order-dialog .node { transition: none; }
        }

        /* Purchases read as a command center: the page says what needs you, each card says where it stands. */
        .purchase-hero { position: relative; display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 18px 28px; overflow: hidden; padding: 20px 22px; border: 1px solid var(--color-border); border-radius: 16px; background: linear-gradient(135deg, #f4f8ff 0%, #ffffff 56%); }
        .purchase-hero::before { content: ''; position: absolute; top: -80px; right: -20px; width: 230px; height: 230px; border-radius: 50%; background: radial-gradient(circle, rgb(32 91 244 / 8%) 0%, transparent 68%); pointer-events: none; }
        .purchase-hero-copy { position: relative; min-width: 0; }
        .purchase-eyebrow { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 5px; font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--color-muted); }
        .purchase-eyebrow svg { width: 14px; height: 14px; color: var(--color-accent); }
        .purchase-hero-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 5px; font-size: 12.5px; color: var(--color-muted); }
        .purchase-hero-count { font-weight: 600; color: var(--color-fg); }
        .purchase-hero-sep { color: var(--color-border-strong); }
        .purchase-search-group { width: 100%; max-width: 420px; }
        .purchase-search { display: flex; align-items: center; gap: 8px; width: 100%; }
        .purchase-search-field { position: relative; flex: 1; min-width: 0; }
        .purchase-search-submit { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border: 1px solid transparent; border-radius: 8px; background: var(--color-accent); color: #fff; font-size: 12.5px; font-weight: 650; cursor: pointer; touch-action: manipulation; transition: background .15s; }
        .purchase-search-submit:hover { background: var(--color-accent-hover); }
        .purchase-search-submit:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
        @media (min-width: 640px) { .purchase-search-group { width: 360px; } }
        @media (min-width: 1024px) { .purchase-search-submit { min-height: 40px; } }

        .purchase-attention { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-top: 14px; }
        .purchase-attention-title { display: inline-flex; align-items: center; gap: 6px; margin-right: 2px; font-size: 10.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--color-muted); }
        .purchase-attention-title svg { width: 14px; height: 14px; color: var(--color-warning-text); }
        .purchase-attention-chip { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 0 10px 0 11px; border: 1px solid var(--color-border); border-radius: 999px; background: var(--color-surface); color: var(--color-fg); font-size: 11.5px; font-weight: 600; text-decoration: none; transition: box-shadow .15s; }
        .purchase-attention-chip:hover { box-shadow: 0 2px 6px rgb(24 44 72 / 9%); }
        .purchase-attention-chip:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
        .purchase-attention-dot { width: 6px; height: 6px; flex: none; border-radius: 50%; background: var(--color-muted); }
        .purchase-attention-count { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--color-sunken); color: var(--color-muted); font-size: 10px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .purchase-attention-chip.is-warm { border-color: var(--color-warning-line); background: var(--color-warning-wash); color: var(--color-warning-text); }
        .purchase-attention-chip.is-warm .purchase-attention-dot { background: var(--color-warning); }
        .purchase-attention-chip.is-warm .purchase-attention-count { background: #fff; color: var(--color-warning-text); }
        .purchase-attention-chip.is-info { border-color: var(--color-info-line); background: var(--color-info-wash); color: var(--color-accent-hover); }
        .purchase-attention-chip.is-info .purchase-attention-dot { background: var(--color-accent); }
        .purchase-attention-chip.is-info .purchase-attention-count { background: #fff; color: var(--color-accent-hover); }
        @media (max-width: 639px) { .purchase-attention-chip { min-height: 44px; } }

        .purchase-tabs-wrap { min-width: 0; margin-top: 18px; padding: 4px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-sunken); }
        /* Relative keeps mobile Chrome from widening the layout viewport to the scroll track's full width. */
        .purchase-tabs { position: relative; display: flex; align-items: center; gap: 4px; min-width: 0; overflow-x: auto; scrollbar-width: none; }
        .purchase-tabs::-webkit-scrollbar { display: none; }
        .purchase-tab { display: inline-flex; flex: none; align-items: center; gap: 6px; min-height: 38px; padding: 0 12px; border-radius: 8px; color: var(--color-muted); font-size: 12.5px; font-weight: 600; line-height: 1; white-space: nowrap; text-decoration: none; transition: background .15s, color .15s, box-shadow .15s; }
        .purchase-tab:hover { background: rgb(255 255 255 / 65%); color: var(--color-fg); }
        .purchase-tab[aria-current='page'] { background: var(--color-surface); color: var(--color-fg); box-shadow: 0 1px 2px rgb(24 44 72 / 8%), inset 0 0 0 1px var(--color-border); }
        .purchase-tab:focus-visible { outline: 2px solid var(--color-accent); outline-offset: -2px; }
        .purchase-tab-count { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--color-paper); color: var(--color-muted); font-size: 10.5px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .purchase-tab[aria-current='page'] .purchase-tab-count { background: var(--color-info-wash); color: var(--color-accent-hover); }
        .purchase-tab-count.is-warm { background: var(--color-warning-wash); color: var(--color-warning-text); box-shadow: inset 0 0 0 1px var(--color-warning-line); }
        @media (max-width: 639px) { .purchase-tab { min-height: 44px; padding: 0 13px; } }

        .purchase-day { display: flex; align-items: center; gap: 10px; padding: 0 2px; }
        .purchase-day-title { font-size: 12px; font-weight: 650; color: var(--color-fg); }
        .purchase-day-date { font-size: 11px; font-weight: 500; color: var(--color-muted); }
        .purchase-day-rule { flex: 1; height: 1px; background: var(--color-border); }
        .purchase-day-count { font-size: 10.5px; font-weight: 600; color: var(--color-muted); font-variant-numeric: tabular-nums; }

        /* One card per store order: identity and state, the journey, every item, then the receipt. */
        .purchase-card { display: grid; grid-template-columns: minmax(0, 1fr) 228px; overflow: hidden; border: 1px solid var(--color-border); border-radius: 14px; background: var(--color-surface); box-shadow: 0 1px 2px rgb(24 44 72 / 4%); color: inherit; text-decoration: none; transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
        .purchase-card:hover { border-color: var(--color-border-strong); box-shadow: 0 10px 28px rgb(24 44 72 / 9%); transform: translateY(-1px); }
        .purchase-card:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

        .purchase-card-head { grid-column: 1 / -1; display: flex; align-items: center; gap: 11px; padding: 13px 16px; border-bottom: 1px solid var(--color-line-soft); background: linear-gradient(180deg, #fff 0%, #fbfcff 100%); }
        .purchase-store-mark { display: grid; place-items: center; width: 34px; height: 34px; flex: none; border-radius: 9px; background: var(--mark-bg, #e8effc); color: var(--mark-ink, #1e3a6e); font-size: 11px; font-weight: 700; letter-spacing: .02em; }
        img.purchase-store-mark { border: 1px solid var(--color-line-soft); background: #fff; object-fit: cover; }
        .purchase-store-copy { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .purchase-store-name { overflow: hidden; font-size: 13.5px; font-weight: 650; line-height: 1.25; color: var(--color-fg); text-overflow: ellipsis; white-space: nowrap; }
        .purchase-store-meta { display: flex; align-items: center; min-width: 0; margin-top: 2px; overflow: hidden; font-size: 11.5px; line-height: 1.3; color: var(--color-muted); white-space: nowrap; }
        .purchase-meta-sep { padding: 0 5px; color: var(--color-border-strong); }
        .purchase-order-number { overflow: hidden; font-variant-numeric: tabular-nums; text-overflow: ellipsis; }

        .purchase-status { display: inline-flex; align-items: center; gap: 6px; flex: none; max-width: 100%; padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 650; line-height: 1.4; white-space: nowrap; }
        .purchase-status-dot { width: 6px; height: 6px; flex: none; border-radius: 50%; background: currentColor; }
        .purchase-status.is-neutral { background: var(--color-sunken); color: var(--color-fg); box-shadow: inset 0 0 0 1px var(--color-border); }
        .purchase-status.is-good { background: var(--color-success-wash); color: var(--color-success); box-shadow: inset 0 0 0 1px var(--color-success-line); }
        .purchase-status.is-moving { background: var(--color-info-wash); color: var(--color-accent-hover); box-shadow: inset 0 0 0 1px var(--color-info-line); }
        .purchase-status.is-warn { background: var(--color-warning-wash); color: var(--color-warning-text); box-shadow: inset 0 0 0 1px var(--color-warning-line); }
        .purchase-status.is-muted { background: var(--color-sunken); color: var(--color-muted); box-shadow: inset 0 0 0 1px var(--color-line-soft); }
        .purchase-status.is-stopped { background: var(--color-danger-wash); color: var(--color-danger-text); box-shadow: inset 0 0 0 1px var(--color-danger-line); }

        .purchase-card-body { min-width: 0; }
        .purchase-journey { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0; padding: 15px 20px 13px; border-bottom: 1px solid var(--color-line-soft); background: #fbfcff; list-style: none; }
        .purchase-step { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 7px; min-width: 0; }
        .purchase-step::before { content: ''; position: absolute; top: 10px; left: 50%; right: -50%; z-index: 0; height: 2px; background: var(--color-line-soft); }
        .purchase-step:last-child::before { display: none; }
        .purchase-step.is-done::before { background: #b9cdf2; }
        .purchase-dot { position: relative; z-index: 1; display: grid; place-items: center; width: 22px; height: 22px; border: 2px solid var(--color-border-strong); border-radius: 50%; background: var(--color-surface); color: transparent; transition: background .18s, border-color .18s, color .18s; }
        .purchase-dot svg { width: 11px; height: 11px; }
        .purchase-step.is-done .purchase-dot { border-color: var(--color-accent); background: var(--color-accent); color: #fff; }
        .purchase-step.is-current .purchase-dot { border-color: var(--color-accent); color: var(--color-accent); box-shadow: 0 0 0 4px rgb(32 91 244 / 12%); }
        .purchase-step.is-current .purchase-dot::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .purchase-step.is-current.is-pay .purchase-dot { border-color: var(--color-warning); color: var(--color-warning-text); box-shadow: 0 0 0 4px rgb(245 158 11 / 14%); }
        .purchase-step.is-done.is-final .purchase-dot { border-color: var(--color-success); background: var(--color-success); }
        .purchase-step-label { overflow: hidden; max-width: 100%; font-size: 10.5px; font-weight: 600; line-height: 1.3; color: var(--color-muted); text-align: center; text-overflow: ellipsis; white-space: nowrap; }
        .purchase-step.is-done .purchase-step-label { color: var(--color-fg); }
        .purchase-step.is-current .purchase-step-label { color: var(--color-accent-hover); }
        .purchase-step.is-current.is-pay .purchase-step-label { color: var(--color-warning-text); }
        .purchase-step.is-done.is-final .purchase-step-label { color: var(--color-success); }

        .purchase-items { margin: 0; padding: 0; list-style: none; }
        .purchase-item { display: grid; grid-template-columns: 46px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 11px 18px; }
        .purchase-item + .purchase-item { border-top: 1px solid var(--color-line-soft); }
        .purchase-thumb { position: relative; display: block; width: 46px; height: 46px; overflow: hidden; border: 1px solid var(--color-line-soft); border-radius: 9px; background: var(--color-paper); }
        .purchase-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .purchase-thumb svg { position: absolute; inset: 0; width: 17px; height: 17px; margin: auto; color: #94a3b8; }
        .purchase-item-copy { min-width: 0; }
        .purchase-item-title { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; font-size: 13px; font-weight: 600; line-height: 1.35; color: var(--color-fg); overflow-wrap: anywhere; }
        .purchase-item-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 2px 0; margin-top: 2px; font-size: 11.5px; line-height: 1.4; color: var(--color-muted); }
        .purchase-variant { font-weight: 600; color: var(--color-accent-hover); }
        .purchase-meta-dot { padding: 0 5px; color: var(--color-border-strong); }
        .purchase-item-amount { align-self: center; font-size: 13px; font-weight: 650; color: var(--color-fg); font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }

        .purchase-card-rail { display: flex; flex-direction: column; justify-content: center; gap: 12px; padding: 16px 18px; border-left: 1px solid var(--color-line-soft); background: linear-gradient(180deg, #fbfcff 0%, var(--color-sunken) 100%); }
        .purchase-total-block { display: flex; flex-direction: column; gap: 2px; }
        .purchase-total-label { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--color-muted); }
        .purchase-total-value { font-size: 22px; font-weight: 650; line-height: 1.05; letter-spacing: -.035em; color: var(--color-fg); font-variant-numeric: tabular-nums; }
        .purchase-facts { display: grid; gap: 5px; margin: 0; padding: 10px 0 0; border-top: 1px solid var(--color-border); }
        .purchase-fact { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; font-size: 11px; line-height: 1.35; }
        .purchase-fact dt { color: var(--color-muted); }
        .purchase-fact dd { overflow: hidden; margin: 0; font-weight: 600; color: var(--color-fg); text-align: right; text-overflow: ellipsis; white-space: nowrap; }

        .purchase-card-foot { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 11px 16px; border-top: 1px solid var(--color-line-soft); background: var(--color-surface); }
        .purchase-note { min-width: 0; overflow: hidden; font-size: 11.5px; line-height: 1.4; color: var(--color-muted); text-overflow: ellipsis; white-space: nowrap; }
        .purchase-cta { display: inline-flex; align-items: center; gap: 5px; flex: none; font-size: 12px; font-weight: 650; color: var(--color-accent-hover); }
        .purchase-cta svg { width: 14px; height: 14px; transition: transform .16s ease; }
        .purchase-card:hover .purchase-cta svg { transform: translateX(2px); }

        .purchase-empty { display: flex; flex-direction: column; align-items: center; padding: 52px 20px 46px; border: 1px solid var(--color-border); border-radius: 16px; background: linear-gradient(180deg, #fff 0%, #fbfcff 100%); text-align: center; }
        .purchase-empty-icon { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 14px; background: var(--color-info-wash); color: var(--color-accent); box-shadow: inset 0 0 0 1px var(--color-info-line); }
        .purchase-empty-icon svg { width: 24px; height: 24px; }
        .purchase-empty h3 { margin-top: 14px; font-size: 16px; font-weight: 650; letter-spacing: -.015em; color: var(--color-fg); }
        .purchase-empty p { max-width: 40ch; margin-top: 6px; font-size: 12.5px; line-height: 1.6; color: var(--color-muted); }
        .purchase-empty-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 18px; }

        .purchase-loadmore { display: flex; flex-direction: column; align-items: stretch; gap: 12px; padding: 14px 16px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-surface); text-align: center; }

        @media (max-width: 900px) {
            .purchase-card { grid-template-columns: minmax(0, 1fr); }
            .purchase-card-head { grid-column: 1; }
            .purchase-card-rail { grid-column: 1; flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: flex-start; gap: 6px 16px; padding: 11px 16px; border-top: 1px solid var(--color-line-soft); border-left: 0; background: var(--color-sunken); }
            .purchase-total-block { flex-direction: row; align-items: baseline; gap: 7px; flex: none; }
            .purchase-total-label { font-size: 10.5px; letter-spacing: .06em; }
            .purchase-total-value { font-size: 17px; }
            .purchase-facts { display: flex; flex-wrap: wrap; gap: 2px 10px; margin: 0 0 0 auto; padding: 0; border: 0; }
            .purchase-fact { display: inline-flex; gap: 3px; }
            .purchase-card-foot { grid-column: 1; }
            .purchase-loadmore { text-align: left; }
        }
        @media (min-width: 640px) { .purchase-loadmore { flex-direction: row; align-items: center; justify-content: space-between; } }
        @media (max-width: 639px) {
            .purchase-hero { padding: 16px; }
            .purchase-hero-meta { font-size: 12px; }
            .purchase-card-head { padding: 12px 14px; }
            .purchase-journey { padding: 13px 12px 11px; }
            .purchase-step-label { font-size: 10px; }
            .purchase-item { padding: 10px 14px; }
            .purchase-card-foot { padding: 10px 14px; }
            .purchase-card-rail { padding: 11px 14px; }
            .purchase-empty { padding: 42px 18px 38px; }
        }
        @media (max-width: 480px) {
            .purchase-card-head { flex-wrap: wrap; row-gap: 8px; }
            .purchase-store-copy { flex: 1 1 140px; }
            .purchase-status { margin-left: auto; }
        }
        @media (prefers-reduced-motion: reduce) {
            .purchase-card, .purchase-card:hover, .purchase-cta svg, .purchase-dot, .purchase-tab { transition: none; transform: none; }
        }

        .sk { display: grid; gap: 12px; }
        .sk-line, .sk-block { border-radius: 6px; background: var(--color-line-soft); animation: sk-pulse 1.2s ease-in-out infinite; }
        .sk-line { height: 12px; }
        .sk-line.is-title { height: 20px; width: 55%; }
        .sk-line.is-short { width: 38%; }
        .sk-journey { height: 48px; border-radius: 8px; background: var(--color-sunken); animation: sk-pulse 1.2s ease-in-out infinite; }
        .sk-block { height: 104px; border-radius: 8px; }
        @keyframes sk-pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) { .sk-line, .sk-block, .sk-journey { animation: none; } }
    </style>


    @php

        $statusStyles = [
            'pending' => ['Awaiting store', 'neutral'],
            'confirmed' => ['Preparing', 'neutral'],
            'processing' => ['Preparing', 'neutral'],
            'shipped' => ['On the way', 'moving'],
            'delivered' => ['Delivered', 'good'],
            'cancelled' => ['Cancelled', 'stopped'],
        ];
        // Store marks rotate through four quiet palettes so neighbouring shops stay distinguishable.
        $markPalettes = [
            ['#e8effc', '#1e3a6e'],
            ['#e9f7f0', '#166534'],
            ['#fdf2e4', '#9a5b12'],
            ['#f1ecfd', '#5b3a8e'],
        ];
        $emptyCopy = [
            'all' => ['Your first purchase starts here', 'Your orders and delivery updates will appear here.'],
            'to_pay' => ['Nothing to pay', 'Orders you pay a store for directly, by GCash, Maya, or bank transfer, wait here until you send the payment.'],
            'awaiting_confirmation' => ['Nothing waiting on a store', 'Orders a store has not confirmed yet appear here.'],
            'to_dispatch' => ['Nothing being prepared', 'Orders a store has confirmed and is getting ready to ship appear here.'],
            'to_receive' => ['Nothing on the way', 'Shipped orders appear here until you confirm they arrived.'],
            'delivered' => ['No delivered orders yet', 'Orders appear here once they arrive.'],
            'to_review' => ['You’re all caught up', 'Delivered items you haven’t reviewed appear here.'],
            'returns' => ['No returns or refund requests', 'Open a return from a delivered order within '.(int) config('marketplace.orders.return_window_days').' days of delivery. Requests you open appear here.'],
        ];
        $search = trim($filters['search'] ?? '');
        $hasFilters = $search !== '' || filled($filters['status'] ?? null);
        $primaryAction = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3.5 text-meta font-semibold text-white transition-colors hover:bg-brand-hover lg:min-h-10';
        $outlineAction = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10';
        $inlineLink = 'font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline';
        // What waits on the buyer, surfaced before any tab is opened.
        $attentionItems = array_values(array_filter([
            ($attentionCounts['to_pay'] ?? 0) > 0 ? ['label' => 'Payment due', 'count' => $attentionCounts['to_pay'], 'tab' => 'to_pay', 'tone' => 'warm'] : null,
            ($attentionCounts['to_receive'] ?? 0) > 0 ? ['label' => 'Delivery to confirm', 'count' => $attentionCounts['to_receive'], 'tab' => 'to_receive', 'tone' => 'info'] : null,
            ($attentionCounts['returns'] ?? 0) > 0 ? ['label' => 'Return needs you', 'count' => $attentionCounts['returns'], 'tab' => 'returns', 'tone' => 'warm'] : null,
            ($attentionCounts['refunds'] ?? 0) > 0 ? ['label' => 'Refund on the way', 'count' => $attentionCounts['refunds'], 'tab' => 'all', 'tone' => 'neutral'] : null,
        ]));
    @endphp

    <x-marketplace.account-shell active="orders">
        <div data-purchases class="min-w-0">
            <section class="purchase-hero" aria-labelledby="purchases-title">
                <div class="purchase-hero-copy">
                    <p class="purchase-eyebrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                        Purchase history
                    </p>
                    <h1 id="purchases-title">My purchases</h1>
                    <p class="purchase-hero-meta">
                        @if ($sellerOrders->total() > 0)
                            <span class="purchase-hero-count">{{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}</span>
                            <span class="purchase-hero-sep" aria-hidden="true">·</span>
                        @endif
                        <a href="{{ route('catalog.index') }}" class="{{ $inlineLink }}">Continue shopping <span aria-hidden="true">→</span></a>
                    </p>
                </div>

                <div class="purchase-search-group">
                    <form method="GET" action="{{ route('orders.index') }}" class="purchase-search" role="search">
                        @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                        @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <label for="purchase-search" class="sr-only">Search purchases</label>
                        <div class="purchase-search-field">
                            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input id="purchase-search" name="search" value="{{ $search }}" type="search" maxlength="100" enterkeyhint="search" class="field-control pl-9 text-[16px] sm:text-meta lg:min-h-10 {{ $search !== '' ? 'pr-11' : '' }}" placeholder="Order #, store, or item" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                            @if ($search !== '')
                                <a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="absolute right-0 top-0 grid size-11 place-items-center rounded-lg text-muted hover:text-ink lg:size-10" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></a>
                            @endif
                        </div>
                        <button type="submit" class="purchase-search-submit">Search</button>
                    </form>
                    <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-2" />
                </div>
            </section>

            @if (session('status'))
                <p role="status" class="mt-3 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
            @endif
            @error('received')
                <p role="alert" class="mt-3 rounded-md border border-[var(--color-danger-line)] bg-[var(--color-danger-wash)] px-3.5 py-2.5 text-meta font-medium text-danger-text">{{ $message }}</p>
            @enderror

            @if ($attentionItems !== [])
                <section class="purchase-attention" aria-label="Needs your attention">
                    <p class="purchase-attention-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5 21 20H3L12 3.5Z"/><path d="M12 9.5v4.5M12 17h.01"/></svg>
                        Needs your attention
                    </p>
                    @foreach ($attentionItems as $item)
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $item['tab']]) }}" class="purchase-attention-chip is-{{ $item['tone'] }}">
                            <span class="purchase-attention-dot" aria-hidden="true"></span>
                            <span>{{ $item['label'] }}</span>
                            <span class="purchase-attention-count">{{ $item['count'] }}<span class="sr-only"> {{ Str::plural('order', $item['count']) }}</span></span>
                        </a>
                    @endforeach
                </section>
            @endif

            <div class="purchase-tabs-wrap">
                {{-- The tabs read as the order's journey, bracketed by All and by what comes after delivery.
                     Only a tab where the next move is the buyer's gets a warm count; every other count stays quiet.
                     The track scrolls on a phone, so the selected tab and whichever tab takes keyboard focus are kept
                     in view; a pointer click must not recenter the track mid-click. --}}
                @php
                    $buyerTurn = ['to_pay' => $tabCounts['to_pay'] > 0, 'returns' => isset($attentionCounts['returns'])];
                @endphp
                <nav class="purchase-tabs" aria-label="Purchase status filters" x-data @focusin="if ($event.target.matches(':focus-visible')) $event.target.scrollIntoView({ block: 'nearest', inline: 'center' })">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" class="purchase-tab" @if ($tab === $key) aria-current="page" x-init="$el.parentElement.scrollLeft = Math.max(0, $el.offsetLeft - ($el.parentElement.clientWidth - $el.offsetWidth) / 2)" @endif>
                            <span>{{ $label }}</span>
                            @if ($buyerTurn[$key] ?? false)
                                <span class="purchase-tab-count is-warm">{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>
                            @elseif ($tabCounts[$key] > 0 || $tab === $key)
                                <span class="purchase-tab-count">{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>
            @if (filled($filters['status'] ?? null) && isset($statusStyles[$filters['status']]))
                <p class="mt-2 text-meta text-muted">Filtered to checkouts marked {{ $statusStyles[$filters['status']][0] }} <span aria-hidden="true">·</span> <a href="{{ route('orders.index', request()->except('status', 'page')) }}" class="{{ $inlineLink }}">Show all</a></p>
            @endif
            <x-input-error :messages="$errors->get('tab')" class="mt-2" />
            <x-input-error :messages="$errors->get('status')" class="mt-2" />

            {{-- Each order is its own card: a quiet store bar with the status, then the order's journey,
                 every item in the order, the receipt, and one contextual note and action. --}}
            <div x-data="{
                current: null, shown: null, html: '', loading: false, failed: false, pushed: false, cache: {}, pending: {}, warmTimer: null, trigger: null,
                ids: @js($sellerOrders->pluck('order.id')->unique()->values()),
                showUrl: @js(route('orders.show', ['order' => '__ORDER__'])),
                init() {
                    const id = new URLSearchParams(location.search).get('order');
                    if (id) { this.open(id, 'replace') }
                    window.addEventListener('popstate', () => { const id = new URLSearchParams(location.search).get('order'); this.pushed = false; id ? this.open(id, 'none') : this.close() });
                },
                load(id) {
                    return this.pending[id] ??= fetch(this.showUrl.replace('__ORDER__', id), { credentials: 'same-origin', headers: { 'X-Order-Modal': '1', 'X-Requires-Session': '1', Accept: 'text/html' } })
                        .then(async (response) => { if (! response.ok || response.redirected) { throw new Error('Order unavailable') } return this.cache[id] = await response.text() })
                        .finally(() => delete this.pending[id]);
                },
                preload(id) { if (id && ! this.cache[id]) { this.load(id).catch(() => {}) } },
                warm(id) { clearTimeout(this.warmTimer); this.warmTimer = setTimeout(() => this.preload(id), 100) },
                async open(id, history = 'push') {
                    clearTimeout(this.warmTimer);
                    if (! this.trigger) { this.trigger = document.activeElement }
                    this.current = id; this.loading = true; this.failed = false;
                    if (this.cache[id]) { this.html = this.cache[id]; this.shown = id }
                    if (! this.$refs.modal.open) { this.$refs.modal.showModal(); document.documentElement.style.overflow = 'hidden'; document.documentElement.style.scrollbarGutter = 'stable'; }
                    const url = new URL(location.href); url.searchParams.set('order', id);
                    if (history === 'push' && ! this.pushed) { window.history.pushState({ order: id }, '', url); this.pushed = true } else if (history !== 'none') { window.history.replaceState({ order: id }, '', url) }
                    try {
                        const html = await this.load(id);
                        if (this.current !== id) { return }
                        if (html !== this.html) { this.html = html }
                        this.shown = id;
                    } catch (error) {
                        if (this.current === id && this.shown !== id) { this.failed = true; this.html = '' }
                    } finally {
                        if (this.current === id) { this.loading = false; this.$nextTick(() => { if (! this.$refs.modal.contains(document.activeElement)) { this.$refs.modal.querySelector('[data-modal-focus]')?.focus() } }) }
                    }
                },
                close() { if (this.$refs.modal.open) { this.$refs.modal.close() } },
                closed() {
                    this.loading = false; this.current = null; this.shown = null; this.html = '';
                    document.documentElement.style.overflow = '';
                    document.documentElement.style.scrollbarGutter = '';
                    const trigger = this.trigger; this.trigger = null;
                    this.$nextTick(() => trigger?.focus?.());
                    const url = new URL(location.href);
                    if (! url.searchParams.has('order')) { return }
                    if (this.pushed) { this.pushed = false; window.history.back(); return }
                    url.searchParams.delete('order'); window.history.replaceState({}, '', url);
                },
            }" class="min-w-0">
            <section id="purchases" class="mt-5 scroll-mt-24 flex flex-col gap-6" aria-labelledby="purchase-results-heading">
                <h2 id="purchase-results-heading" class="sr-only">{{ $tabs[$tab] }} purchases{{ $search !== '' ? ' matching “'.$search.'”' : '' }} ({{ $sellerOrders->total() }})</h2>

                @php
                    $orderGroups = $sellerOrders->getCollection()
                        ->groupBy(fn ($sellerOrder): string => $sellerOrder->order->created_at->toDateString())
                        ->sortKeysDesc();
                @endphp

                @forelse ($orderGroups as $groupOrders)
                    @php
                        $groupDate = $groupOrders->first()->order->created_at->copy()->startOfDay();
                        $groupLabel = $groupDate->isToday() ? 'Today' : ($groupDate->isYesterday() ? 'Yesterday' : $groupDate->format($groupDate->isCurrentYear() ? 'M j' : 'M j, Y'));
                    @endphp
                    <section class="space-y-3" aria-label="Orders from {{ $groupDate->format('M j, Y') }}">
                        <div class="purchase-day">
                            <h3 class="purchase-day-title">{{ $groupLabel }}@if ($groupDate->isToday() || $groupDate->isYesterday())<span class="purchase-day-date"> {{ $groupDate->format('M j') }}</span>@endif</h3>
                            <span class="purchase-day-rule" aria-hidden="true"></span>
                            <span class="purchase-day-count">{{ $groupOrders->count() }} {{ Str::plural('order', $groupOrders->count()) }}</span>
                        </div>
                            @foreach ($groupOrders as $sellerOrder)
                                @php
                                    $order = $sellerOrder->order;
                                    $store = $sellerOrder->store->name;
                                    $cancelRequest = $sellerOrder->issues->first(fn ($issue): bool => $issue->type === 'cancellation' && $issue->status === 'open');
                                    $returnCase = $sellerOrder->returnCases->first();
                                    // A missed deadline flips the pill to the turn that matters now.
                                    $returnOverdue = $returnCase?->isOverdue() ?? false;
                                    $storeLate = $returnOverdue && $returnCase->status === 'awaiting_seller';
                                    // Only answering an offer or rejection has a hard buyer cutoff; a late send-back can still be recorded.
                                    $buyerLate = $returnOverdue && in_array($returnCase->status, ['offered', 'rejected'], true);
                                    $storeMissed = $returnOverdue && in_array($returnCase->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'], true);
                                    $isDirect = $order->payment?->isOnline() ?? false;
                                    $needsPayment = $isDirect && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null;
                                    [$statusLabel, $statusVariant] = $statusStyles[$sellerOrder->status];
                                    if ($needsPayment) {
                                        [$statusLabel, $statusVariant] = ['To pay', 'warn'];
                                    } elseif ($cancelRequest) {
                                        [$statusLabel, $statusVariant] = ['Cancel requested', 'warn'];
                                    }
                                    $items = $sellerOrder->items;
                                    $lineCount = $items->count();
                                    $itemCount = $items->sum('quantity');
                                    $status = $sellerOrder->status;
                                    $reached = match ($status) {
                                        'delivered' => 4,
                                        'shipped' => 3,
                                        'confirmed', 'processing' => 2,
                                        'pending' => 1,
                                        default => 0,
                                    };
                                    $journeySteps = $needsPayment
                                        ? ['Payment due', 'Confirmed', 'Shipped', 'Delivered']
                                        : ['Confirmed', 'Packed', 'Shipped', 'Delivered'];
                                    $journeyNote = match (true) {
                                        $needsPayment => 'Pay before the store can prepare it',
                                        $status === 'pending' => 'Waiting for the store to confirm',
                                        in_array($status, ['confirmed', 'processing'], true) => 'The store is preparing your order',
                                        $status === 'shipped' => 'On the way to you',
                                        $status === 'delivered' => 'Review the items you received',
                                        $status === 'cancelled' => 'This order was cancelled',
                                        default => '',
                                    };
                                    $journeyAction = match (true) {
                                        $needsPayment => 'Complete payment',
                                        $status === 'shipped' => 'Track order',
                                        default => 'View order',
                                    };
                                    // The pill is the order's own state; buyer/store lateness replaces it with the turn that matters now.
                                    $overlayLabel = $statusLabel;
                                    $overlayVariant = $statusVariant;
                                    if ($buyerLate) {
                                        [$overlayLabel, $overlayVariant] = ['Response window passed', 'muted'];
                                    } elseif ($storeMissed) {
                                        [$overlayLabel, $overlayVariant] = [$storeLate ? 'Store missed its deadline' : 'Store is running late', $storeLate ? 'warn' : 'neutral'];
                                    }
                                    $storeInitials = Str::of($store)->squish()->explode(' ')->filter()->take(2)->map(fn ($word): string => Str::upper(Str::substr($word, 0, 1)))->implode('') ?: 'S';
                                    [$markBg, $markInk] = $markPalettes[abs(crc32($store)) % count($markPalettes)];
                                    $paymentLabel = $order->payment ? (\App\Models\Payment::METHOD_LABELS[$order->payment->method] ?? ucfirst($order->payment->method)) : '—';
                                @endphp

                                <article data-order-number="{{ $order->number }}" class="min-w-0">
                                    <a
                                        href="{{ route('orders.show', $order) }}"
                                        @click.prevent="open(@js($order->id))"
                                        @pointerenter="warm(@js($order->id))"
                                        aria-label="Order {{ $order->number }} from {{ $store }}, {{ $overlayLabel }}, {{ $itemCount }} {{ Str::plural('item', $itemCount) }}, ₱{{ number_format($sellerOrder->total, 2) }}"
                                        class="purchase-card"
                                    >
                                        <div class="purchase-card-head">
                                            @if ($sellerOrder->store->logo_path)
                                                <img src="{{ Storage::disk('public')->url($sellerOrder->store->logo_path) }}" alt="" width="34" height="34" loading="lazy" class="purchase-store-mark">
                                            @else
                                                <span class="purchase-store-mark" style="--mark-bg: {{ $markBg }}; --mark-ink: {{ $markInk }}" aria-hidden="true">{{ $storeInitials }}</span>
                                            @endif
                                            <span class="purchase-store-copy">
                                                <span class="purchase-store-name">{{ $store }}</span>
                                                {{-- Time first: the long order number is the part allowed to truncate on a phone. --}}
                                                <span class="purchase-store-meta">
                                                    <span>{{ $order->created_at->format('g:i A') }}</span>
                                                    @if ($order->payment)
                                                        <span class="max-sm:hidden"><span class="purchase-meta-sep" aria-hidden="true">·</span>{{ \App\Models\Payment::METHOD_LABELS[$order->payment->method] ?? ucfirst($order->payment->method) }}</span>
                                                    @endif
                                                    <span class="purchase-meta-sep" aria-hidden="true">·</span>
                                                    <span class="purchase-order-number">{{ $order->number }}</span>
                                                </span>
                                            </span>
                                            <span class="purchase-status is-{{ $overlayVariant }}">
                                                <span class="purchase-status-dot" aria-hidden="true"></span>
                                                {{ $overlayLabel }}
                                            </span>
                                        </div>

                                        <div class="purchase-card-body">
                                            {{-- Where the order is, in one glance. Cancelled orders have no journey to show. --}}
                                            @if ($status !== 'cancelled')
                                                <ol class="purchase-journey" aria-label="Order progress: {{ implode(', ', $journeySteps) }}">
                                                    @foreach ($journeySteps as $index => $stepLabel)
                                                        @php
                                                            $step = $index + 1;
                                                            $stepDone = $step < $reached || ($status === 'delivered' && $step === $reached);
                                                            $stepCurrent = $step === $reached && $status !== 'delivered';
                                                        @endphp
                                                        <li @class([
                                                            'purchase-step',
                                                            'is-done' => $stepDone,
                                                            'is-current' => $stepCurrent,
                                                            'is-pay' => $stepCurrent && $needsPayment,
                                                            'is-final' => $status === 'delivered' && $index === count($journeySteps) - 1,
                                                        ])>
                                                            <span class="purchase-dot">
                                                                @if ($stepDone)
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                                                @endif
                                                            </span>
                                                            <span class="purchase-step-label">{{ $stepLabel }}</span>
                                                            <span class="sr-only">{{ $stepDone ? 'done' : ($stepCurrent ? 'current step' : 'not yet') }}</span>
                                                        </li>
                                                    @endforeach
                                                </ol>
                                            @endif

                                            <ul class="purchase-items">
                                                @foreach ($items as $item)
                                                    @php($itemThumb = $item->image_path ?? $item->product?->thumbnail?->path)
                                                    <li class="purchase-item">
                                                        <span class="purchase-thumb">
                                                            @if ($itemThumb)
                                                                <img src="{{ Storage::disk('product_images')->url($itemThumb) }}" alt="" width="46" height="46" loading="lazy">
                                                            @else
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                                            @endif
                                                        </span>
                                                        <span class="purchase-item-copy">
                                                            <span class="purchase-item-title">{{ $item->product_name }}</span>
                                                            <span class="purchase-item-meta">
                                                                @if ($item->variant_label)
                                                                    <span class="purchase-variant">{{ $item->variant_label }}</span>
                                                                    <span class="purchase-meta-dot" aria-hidden="true">·</span>
                                                                @endif
                                                                @if ($item->quantity > 1)
                                                                    <span class="tabular-nums">₱{{ number_format($item->unit_price, 2) }} each</span>
                                                                    <span class="purchase-meta-dot" aria-hidden="true">·</span>
                                                                @endif
                                                                <span class="tabular-nums">Qty: {{ $item->quantity }}</span>
                                                            </span>
                                                        </span>
                                                        <span class="purchase-item-amount"><span class="sr-only">Line total </span>₱{{ number_format($item->line_total, 2) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>

                                        <div class="purchase-card-rail">
                                            <span class="purchase-total-block">
                                                <span class="purchase-total-label">Order total</span>
                                                <span class="purchase-total-value">₱{{ number_format($sellerOrder->total, 2) }}</span>
                                            </span>
                                            <dl class="purchase-facts">
                                                <div class="purchase-fact"><dt>Products</dt><dd>{{ $lineCount }}</dd></div>
                                                <div class="purchase-fact"><dt>Payment</dt><dd>{{ $paymentLabel }}</dd></div>
                                                <div class="purchase-fact"><dt>Placed</dt><dd>{{ $order->created_at->format('M j') }}</dd></div>
                                                @if ($sellerOrder->delivered_at)
                                                    <div class="purchase-fact"><dt>Delivered</dt><dd>{{ $sellerOrder->delivered_at->format('M j') }}</dd></div>
                                                @endif
                                            </dl>
                                        </div>

                                        <div class="purchase-card-foot">
                                            <p class="purchase-note">{{ $journeyNote }}</p>
                                            <span class="purchase-cta">{{ $journeyAction }}
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                                            </span>
                                        </div>
                                    </a>
                                </article>
                            @endforeach
                    </section>
                @empty
                    <div class="purchase-empty">
                        <span class="purchase-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                        </span>
                        @if ($hasFilters)
                            <h3>{{ $search !== '' ? 'No purchases match “'.$search.'”'.($tab !== 'all' ? ' in '.$tabs[$tab] : '') : 'No purchases match those filters' }}</h3>
                            <p>Try an order number, a store name, or part of a product name.</p>
                            <div class="purchase-empty-actions">
                                @if ($search !== '')<a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="{{ $outlineAction }}">Clear search</a>@endif
                                @if ($tab !== 'all' || filled($filters['status'] ?? null))<a href="{{ route('orders.index') }}" class="{{ $outlineAction }}">Reset filters</a>@endif
                            </div>
                        @else
                            <h3>{{ $emptyCopy[$tab][0] }}</h3>
                            <p>{{ $emptyCopy[$tab][1] }}</p>
                            @php($showAll = $tab !== 'all' && $tabCounts['all'] > 0)
                            @if ($tab === 'returns' && $tabCounts['delivered'] > 0)
                                <a href="{{ route('orders.index', ['tab' => 'delivered']) }}" class="{{ $outlineAction }} mt-4">View delivered orders</a>
                            @else
                                <a href="{{ $showAll ? route('orders.index') : route('catalog.index') }}" class="{{ $showAll ? $outlineAction : $primaryAction }} mt-4">{{ $showAll ? 'View all purchases' : 'Browse products' }}</a>
                            @endif
                        @endif
                    </div>
                @endforelse

                {{-- Load more swaps in the whole longer list rather than appending rows, because orders are
                     grouped by day and a day can straddle two batches. The link still works without script. --}}
                @if ($sellerOrders->total() > $sellerOrders->count() || $sellerOrders->count() > 10)
                    <div class="purchase-loadmore" x-data="{ loading: false, async more(link) {
                        if (this.loading) return;
                        this.loading = true;
                        const shown = document.querySelectorAll('#purchases article').length;
                        try {
                            const response = await fetch(link.href, { headers: { Accept: 'text/html' } });
                            if (! response.ok) throw new Error(response.status);
                            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('purchases');
                            document.getElementById('purchases').replaceWith(fresh);
                            history.replaceState(history.state, '', link.href);
                            const first = fresh.querySelectorAll('article')[shown];
                            first?.setAttribute('tabindex', '-1');
                            first?.focus({ preventScroll: true });
                        } catch {
                            window.location.assign(link.href);
                        }
                    } }">
                        <div class="min-w-0">
                            <p class="text-meta font-semibold text-ink">Showing {{ $sellerOrders->count() }} of {{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}</p>
                            <p class="mt-0.5 text-xs text-muted">Older orders stay grouped by the day you placed them.</p>
                        </div>
                        @if ($loadMoreUrl)
                            <a href="{{ $loadMoreUrl }}" @click.prevent="more($el)" :aria-busy="loading" :class="loading && 'pointer-events-none opacity-60'" class="{{ $outlineAction }} sm:w-[152px]">
                                <span x-show="! loading">Load more</span>
                                <span x-cloak x-show="loading">Loading…</span>
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            <dialog x-ref="modal" class="buyer-order-dialog" aria-labelledby="dlgTitle" aria-describedby="dlgMeta" aria-label="Order details" @close="closed()" @click="if ($event.target === $refs.modal) close()">
                <div x-show="loading && ! html" class="buyer-modal-body" role="status" aria-live="polite" aria-busy="true">
                    <span class="sr-only">Loading order details</span>
                    <div class="sk">
                        <div class="sk-line is-short"></div>
                        <div class="sk-line is-title"></div>
                        <div class="sk-journey"></div>
                        <div class="sk-block"></div>
                        <div class="sk-block"></div>
                    </div>
                </div>
                <div x-show="failed" x-cloak class="buyer-modal-body" role="alert">
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <span class="grid size-12 place-items-center rounded-full bg-[var(--color-danger-wash)] text-danger" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg></span>
                        <p class="mt-4 text-base font-semibold text-ink">We couldn’t load this order</p>
                        <p class="mt-1 max-w-xs text-sm leading-6 text-muted">Your connection may have dropped. Try again, or open the full order page.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink" @click="close()">Close</button>
                            <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 text-sm font-semibold text-ink" @click="open(current, 'none')">Try again</button>
                            <a :href="showUrl.replace('__ORDER__', current)" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white">Open full page</a>
                        </div>
                    </div>
                </div>
                <div x-show="html" x-html="html" class="contents" :data-stale="String(shown !== current)"></div>
            </dialog>
            </div>
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
