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

        /* Purchase cards carry the order's journey inline, so "where is it" answers without opening. */
        .purchase-journey { display: flex; margin: 0; padding: 14px 16px 12px; list-style: none; }
        .purchase-step { position: relative; z-index: 1; display: flex; flex: 1; flex-direction: column; align-items: center; gap: 7px; min-width: 0; }
        .purchase-step::before { content: ''; position: absolute; top: 10px; left: 50%; right: -50%; height: 2px; background: var(--color-line-soft); }
        .purchase-step:last-child::before { display: none; }
        .purchase-step.is-done::before { background: #b9cdf2; }
        .purchase-dot { position: relative; z-index: 1; display: grid; place-items: center; width: 21px; height: 21px; border: 2px solid var(--color-border-strong); border-radius: 50%; background: var(--color-surface); color: transparent; }
        .purchase-dot svg { width: 11px; height: 11px; }
        .purchase-step.is-done .purchase-dot { border-color: var(--color-accent); background: var(--color-accent); color: #fff; }
        .purchase-step.is-current .purchase-dot { border-color: var(--color-accent); color: var(--color-accent); box-shadow: 0 0 0 4px rgb(32 91 244 / 12%); }
        .purchase-step.is-current.is-pay .purchase-dot { border-color: #f59e0b; color: var(--color-warning-text); box-shadow: 0 0 0 4px rgb(245 158 11 / 14%); }
        .purchase-step-label { overflow: hidden; max-width: 100%; font-size: 11px; font-weight: 600; line-height: 1.3; color: var(--color-muted); text-align: center; text-overflow: ellipsis; white-space: nowrap; }
        .purchase-step.is-done .purchase-step-label { color: var(--color-fg); }
        .purchase-step.is-current .purchase-step-label { color: var(--color-accent); }
        .purchase-step.is-current.is-pay .purchase-step-label { color: var(--color-warning-text); }

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
        // The mockup's pill: quiet sunken ground, one dot, and colour only where it carries meaning.
        $pillStyles = [
            'neutral' => ['bg-[var(--color-sunken)] text-ink', 'bg-muted'],
            'good' => ['bg-[var(--color-sunken)] text-success', 'bg-success'],
            'moving' => ['bg-[var(--color-sunken)] text-ink', 'bg-brand'],
            'warn' => ['bg-[var(--color-warning-wash)] text-warning-text ring-1 ring-inset ring-[var(--color-warning-line)]', 'bg-warning'],
            'stopped' => ['bg-[var(--color-sunken)] text-danger', 'bg-danger'],
            'muted' => ['bg-[var(--color-sunken)] text-muted', 'bg-muted'],
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
        // Compact sizes only where a fine pointer is likely; phones and tablets keep 44px targets.
        $quietAction = 'inline-flex min-h-11 items-center rounded-md px-1.5 text-meta font-semibold text-brand underline-offset-2 transition-colors hover:text-brand-hover hover:underline lg:min-h-10';
        $primaryAction = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3.5 text-meta font-semibold text-white transition-colors hover:bg-brand-hover lg:min-h-10';
        $outlineAction = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10';
        $inlineLink = 'font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline';
    @endphp

    <x-marketplace.account-shell active="orders">
        <div data-purchases class="min-w-0">
            <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div class="min-w-0">
                    <h1 class="text-3xl font-semibold tracking-tight text-ink">My purchases</h1>
                    <p class="mt-1 text-meta text-muted">
                        @if ($sellerOrders->total() > 0){{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}<span aria-hidden="true" class="px-1.5">·</span>@endif
                        <a href="{{ route('catalog.index') }}" class="{{ $inlineLink }}">Continue shopping <span aria-hidden="true">→</span></a>
                    </p>
                </div>

                <form method="GET" action="{{ route('orders.index') }}" class="w-full sm:w-80" role="search">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <label for="purchase-search" class="sr-only">Search purchases</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input id="purchase-search" name="search" value="{{ $search }}" type="search" maxlength="100" enterkeyhint="search" class="field-control pl-9 text-[16px] sm:text-meta lg:min-h-10 {{ $search !== '' ? 'pr-11' : '' }}" placeholder="Order #, store, or item" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                        @if ($search !== '')
                            <a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="absolute right-0 top-0 grid size-11 place-items-center rounded-lg text-muted hover:text-ink lg:size-10" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></a>
                        @endif
                    </div>
                    <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-2" />
                </form>
            </header>

            @if (session('status'))
                <p role="status" class="mt-3 rounded-md border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
            @endif
            @error('received')
                <p role="alert" class="mt-3 rounded-md border border-[var(--color-danger-line)] bg-[var(--color-danger-wash)] px-3.5 py-2.5 text-meta font-medium text-danger-text">{{ $message }}</p>
            @enderror

            <div class="mt-5">
                {{-- The tabs read as the order's journey, bracketed by All and by what comes after delivery,
                     so a rule sits between those groups. Only a tab where the next move is the buyer's gets a
                     warm count; every other count stays quiet. The track scrolls on a phone, so the selected
                     tab and whichever tab takes focus are kept in view. --}}
                @php
                    $buyerTurn = ['to_pay' => $tabCounts['to_pay'] > 0, 'returns' => isset($attentionCounts['returns'])];
                @endphp
                <nav class="relative flex min-w-0 items-stretch overflow-x-auto border-b border-line [scrollbar-width:none] max-sm:[mask-image:linear-gradient(to_right,transparent,#000_20px,#000_calc(100%-20px),transparent)] [&::-webkit-scrollbar]:hidden" aria-label="Purchase status filters" x-data @focusin="$event.target.scrollIntoView({ block: 'nearest', inline: 'center' })">
                    @foreach ($tabs as $key => $label)
                        @if (in_array($key, ['to_pay', 'to_review'], true))<span aria-hidden="true" class="mx-1.5 h-4 w-px shrink-0 self-center bg-line"></span>@endif
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                            '-mb-px inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 text-meta font-semibold transition-colors focus-visible:outline-offset-[-2px] lg:min-h-10',
                            'border-brand text-ink' => $tab === $key,
                            'border-transparent text-muted hover:border-line hover:text-ink' => $tab !== $key,
                        ]) @if ($tab === $key) aria-current="page" x-init="$el.parentElement.scrollLeft = Math.max(0, $el.offsetLeft - ($el.parentElement.clientWidth - $el.offsetWidth) / 2)" @endif>
                            {{ $label }}
                            @if ($buyerTurn[$key] ?? false)
                                <span class="inline-grid h-5 min-w-5 place-items-center rounded-full border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] px-1.5 text-[11px] font-semibold tabular-nums text-warning-text">{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}, waiting on you</span></span>
                            @elseif ($tabCounts[$key] > 0 || $tab === $key)
                                <span @class(['text-[11px] font-semibold tabular-nums', 'text-brand' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>
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
                 every item in the order, the order total, and one contextual note and action. --}}
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
                        <div class="px-1">
                            <h3 class="text-meta font-semibold text-ink">{{ $groupLabel }}@if ($groupDate->isToday() || $groupDate->isYesterday())<span class="ml-1.5 font-medium text-muted">{{ $groupDate->format('M j') }}</span>@endif</h3>
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
                                        $needsPayment => 'Pay in order',
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
                                    [$pillClass, $pillDot] = $pillStyles[$overlayVariant];
                                @endphp

                                <article data-order-number="{{ $order->number }}" class="min-w-0">
                                    <a
                                        href="{{ route('orders.show', $order) }}"
                                        @click.prevent="open(@js($order->id))"
                                        @pointerenter="warm(@js($order->id))"
                                        aria-label="Order {{ $order->number }} from {{ $store }}, {{ $overlayLabel }}, {{ $itemCount }} {{ Str::plural('item', $itemCount) }}, ₱{{ number_format($sellerOrder->total, 2) }}"
                                        class="block overflow-hidden rounded-[10px] border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] transition duration-150 hover:border-[var(--color-border-strong)] hover:shadow-[0_2px_10px_rgb(24_44_72/9%)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                    >
                                        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                                            <div class="flex min-w-0 items-center gap-2.5">
                                                @if ($sellerOrder->store->logo_path)
                                                    <img src="{{ Storage::disk('public')->url($sellerOrder->store->logo_path) }}" alt="" width="24" height="24" loading="lazy" class="size-6 shrink-0 rounded-md border border-line bg-white object-cover">
                                                @else
                                                    <svg class="size-[18px] shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.67 2.67 0 0 0 5.33 0 2.67 2.67 0 0 0 5.34 0 2.67 2.67 0 0 0 5.33 0"/><path d="M5.5 12.5V20h13v-7.5"/><path d="M10 20v-4h4v4"/></svg>
                                                @endif
                                                <span class="truncate text-[13px] font-semibold text-ink">{{ $store }}</span>
                                            </div>
                                            <span @class(['inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold', $pillClass])>
                                                <span class="size-[7px] rounded-full {{ $pillDot }}" aria-hidden="true"></span>
                                                {{ $overlayLabel }}
                                            </span>
                                        </div>

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

                                        <ul @class(['border-t border-line-soft' => $status !== 'cancelled'])>
                                            @foreach ($items as $item)
                                                @php($itemThumb = $item->image_path ?? $item->product?->thumbnail?->path)
                                                <li class="grid grid-cols-[40px_minmax(0,1fr)] items-center gap-x-3 gap-y-1.5 border-b border-line-soft px-4 py-3 last:border-b-0 sm:grid-cols-[40px_minmax(0,1fr)_64px_96px]">
                                                    <div class="relative size-10 overflow-hidden rounded-md border border-line-soft bg-paper">
                                                        @if ($itemThumb)
                                                            <img src="{{ Storage::disk('product_images')->url($itemThumb) }}" alt="" width="40" height="40" loading="lazy" class="size-full object-cover">
                                                        @else
                                                            <svg class="absolute inset-0 m-auto size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-[13px] font-semibold leading-[17px] text-ink">{{ $item->product_name }}</p>
                                                        @if ($item->variant_label)
                                                            <p class="mt-0.5 truncate text-[11.5px] leading-[15px] text-brand">{{ $item->variant_label }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="col-start-2 flex items-baseline justify-between gap-4 sm:contents">
                                                        <p class="text-[12.5px] tabular-nums text-muted sm:col-start-3 sm:text-right sm:text-[13px] sm:text-ink"><span class="text-muted">Qty: </span>{{ $item->quantity }}<span class="sr-only"> items</span></p>
                                                        <p class="text-[13px] font-semibold tabular-nums text-ink sm:col-start-4 sm:text-right"><span class="sr-only">Line total </span>₱{{ number_format($item->line_total, 2) }}</p>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="flex items-baseline justify-end gap-2 border-t border-line px-4 py-2.5">
                                            <span class="text-[11.5px] font-medium text-muted">Order total</span>
                                            <b class="text-sm font-semibold tabular-nums text-ink">₱{{ number_format($sellerOrder->total, 2) }}</b>
                                        </div>

                                        <div class="flex items-center justify-between gap-3 border-t border-line-soft bg-[#fbfcfe] px-4 py-2.5">
                                            <p class="min-w-0 truncate text-[11.5px] text-muted">{{ $journeyNote }}</p>
                                            <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-brand">{{ $journeyAction }}
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                                            </span>
                                        </div>
                                    </a>
                                </article>
                            @endforeach
                    </section>
                @empty
                    <div class="rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] px-5 py-14 text-center">
                        @if ($hasFilters)
                            <h3 class="text-lg font-semibold text-ink">{{ $search !== '' ? 'No purchases match “'.$search.'”'.($tab !== 'all' ? ' in '.$tabs[$tab] : '') : 'No purchases match those filters' }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Try an order number, a store name, or part of a product name.</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @if ($search !== '')<a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="{{ $outlineAction }}">Clear search</a>@endif
                                @if ($tab !== 'all' || filled($filters['status'] ?? null))<a href="{{ route('orders.index') }}" class="{{ $outlineAction }}">Reset filters</a>@endif
                            </div>
                        @else
                            <h3 class="text-lg font-semibold text-ink">{{ $emptyCopy[$tab][0] }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">{{ $emptyCopy[$tab][1] }}</p>
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
                    <div class="flex flex-col items-stretch gap-3 rounded-lg border border-line bg-surface shadow-[0_1px_2px_rgb(24_44_72/4%)] px-4 py-3.5 text-center sm:flex-row sm:items-center sm:justify-between sm:text-left lg:px-6" x-data="{ loading: false, async more(link) {
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
                        <p class="text-meta text-muted">Showing {{ $sellerOrders->count() }} of {{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}</p>
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
