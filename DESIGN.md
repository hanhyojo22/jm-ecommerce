---
name: JMMart
description: A local-first, multi-vendor marketplace in navy ink and one working blue.
colors:
  accent-blue: "#205bf4"
  accent-blue-deep: "#1d4ed8"
  navy-ink: "#182c48"
  night-navy: "#111f33"
  window-tile: "#2a3f5f"
  cool-canvas: "#f6f8fc"
  surface-white: "#ffffff"
  paper: "#f1f5f9"
  paper-deep: "#e2e8f0"
  slate-muted: "#596b83"
  hairline: "#e3e8f1"
  hairline-strong: "#cbd4e0"
  navy-text-soft: "#c5d1e0"
  navy-text-muted: "#8ea2ba"
  info-wash: "#eef3ff"
  sale-red: "#dc2626"
  cod-amber: "#b45309"
  star-amber: "#fbbf24"
  success-green: "#166534"
typography:
  display:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(32px, 3.4vw, 46px)"
    fontWeight: 800
    lineHeight: 1.02
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(22px, 1.1rem + 0.6vw, 26px)"
    fontWeight: 600
    lineHeight: 1.12
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.025em"
  body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 500
    lineHeight: 1.33
rounded:
  badge: "4px"
  control: "6px"
  surface: "8px"
spacing:
  gutter: "18px"
  gutter-wide: "32px"
  section: "40px"
  section-wide: "48px"
  panel: "28px"
components:
  button-primary:
    backgroundColor: "{colors.accent-blue}"
    textColor: "{colors.surface-white}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "0 20px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.accent-blue-deep}"
  button-secondary:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.navy-ink}"
    rounded: "{rounded.control}"
    padding: "0 20px"
    height: "44px"
  button-quiet:
    textColor: "{colors.accent-blue}"
    height: "44px"
  field:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.navy-ink}"
    rounded: "{rounded.control}"
    height: "44px"
  product-card:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.navy-ink}"
    rounded: "{rounded.surface}"
    padding: "16px"
  navy-panel:
    backgroundColor: "{colors.navy-ink}"
    textColor: "{colors.surface-white}"
    rounded: "{rounded.surface}"
    padding: "28px"
  folder-tab:
    backgroundColor: "{colors.paper-deep}"
    textColor: "{colors.navy-ink}"
    padding: "10px 18px"
    height: "44px"
  folder-tab-selected:
    backgroundColor: "{colors.navy-ink}"
    textColor: "{colors.surface-white}"
  panel-button-light:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.navy-ink}"
    rounded: "{rounded.control}"
    padding: "0 18px"
    height: "44px"
  rank-chip:
    backgroundColor: "{colors.navy-ink}"
    textColor: "{colors.surface-white}"
    size: "28px"
  bordered-panel:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.navy-ink}"
    rounded: "{rounded.surface}"
    padding: "28px"
---

# Design System: JMMart

## Overview

**Creative North Star: "The Navy Shop Window"**

JMMart is a cool, daylight marketplace: white product cards on a pale blue-grey canvas, set in a single typeface (Inter), with deep navy as the ink everything is written in. When something needs real weight (the workspace sidebar, a featured store, a band of price drops), the navy stops being text and becomes a solid panel, and the content sits inside it the way goods sit behind a lit window. One saturated blue does the working jobs: primary buttons, links, focus. It is not used for decoration.

Density is moderate and retail-practical. Product imagery carries the color, and the chrome stays quiet. Depth is shallow: cards rest on a 1px hairline with a faint shadow and lift slightly on hover. Navy panels are flat slabs and never float. Corners are gently curved and consistent (8px surfaces, 6px controls), so the interface reads as one built object rather than a collection of widgets.

These descriptive names were chosen from the evidence in the code and have not been confirmed with the product owner.

**Key Characteristics:**
- Navy ink (#182c48) is both the text color and the material of feature panels.
- One working blue (#205bf4) for actions, links, and focus.
- White hairline cards on a cool canvas (#f6f8fc), shallow at rest, lifting on hover.
- Inter only, with tight negative tracking on headings and tabular figures for prices and counts.
- 8px surface corners, 6px control corners, 44px minimum touch height.

## Colors

A cool navy-and-white palette with one working blue. Red, amber and green appear only as signals.

### Primary
- **Working Blue** (accent-blue): primary buttons, section links, the selected category border, focus outlines and rings, and the store mark when a store has no logo. It deepens to **Deep Working Blue** (accent-blue-deep) on hover.

### Neutral
- **Navy Ink** (navy-ink): all body and heading text. It is also the fill of feature panels (store window, deals band), the selected folder tab, and the trending rank chip.
- **Night Navy** (night-navy): the workspace sidebar, and the scrim at the base of window tiles.
- **Window Tile Navy** (window-tile): the placeholder fill behind product images inside a navy panel.
- **Cool Canvas** (cool-canvas): the page background everywhere.
- **Surface White** (surface-white): cards, panels on canvas, fields, and light buttons placed on navy.
- **Paper** / **Paper Deep** (paper, paper-deep): quiet fills. Paper Deep is the resting folder tab.
- **Slate Muted** (slate-muted): secondary text, subtitles, meta lines, counts.
- **Hairline** / **Strong Hairline** (hairline, hairline-strong): card and section borders. The strong hairline is used for step rules and the tab hover fill.
- **Navy Soft Text** / **Navy Muted Text** (navy-text-soft, navy-text-muted): secondary text on navy panels and in the sidebar.
- **Info Wash** (info-wash): the hover fill for a light button on navy.

### Signals
- **Sale Red** (sale-red): sale prices, discount badges, the "On sale" window flag.
- **COD Amber** (cod-amber): the cash-on-delivery badge strip on product cards.
- **Star Amber** (star-amber): review stars only.
- **Success Green** (success-green): positive status and confirmation states.

### Named Rules
**The Ink Is The Panel Rule.** Navy is the only color that becomes a solid feature panel. Blue never fills a section. It marks the action inside one.

**The One Working Blue Rule.** Working Blue means "you can act here" (button, link, focus, selection). Don't use it for decoration or illustration.

## Typography

**Display Font:** Inter (with ui-sans-serif, system-ui, sans-serif)
**Body Font:** Inter (same stack; the whole app uses one face)

**Character:** One neutral grotesque, given character through weight and tight negative tracking rather than a second face. Every price and count uses tabular figures.

### Hierarchy
- **Display** (800, clamp 32 to 46px, 1.02, -0.03em): only inside navy panels: the store name in a shop window. The deals band's single large discount figure (56px, 800, -0.04em) is part of the same tier.
- **Headline** (600, clamp 22 to 26px, 1.12, -0.025em): page titles on marketplace and workspace pages. This is the top of the shared Tailwind scale (12 / 13 / 14 / 15 / 24 / 26).
- **Title** (600, 24px, 1.25, -0.025em): section headings, paired with a 14px muted subtitle and a 14px semibold blue link at the right.
- **Body** (400, 14px, 1.5): default text. Product names are 14px weight 500, prices 15px weight 700 in tabular figures.
- **Label** (500, 12px): counts, meta lines, and fact labels. Badge strips on product cards use 11px weight 800 uppercase, the system's only uppercase setting.

### Named Rules
**The One Face Rule.** Inter for everything. Hierarchy comes from weight (400 to 800) and tracking, never from a second family.

**The Tabular Money Rule.** Prices, counts, ranks and discount figures always use `font-variant-numeric: tabular-nums`.

## Layout

A single centered column, max 1320px, with an 18px gutter that widens to 32px from 641px up. Marketplace sections are separated by a 1px hairline top border and vertical padding of 40px (48px from 640px up). A section head is a flex row: title and subtitle on the left, a "View all" link at the right, aligned to the baseline, with 20px below it.

Product grids step from 2 columns on phones to 4 columns on desktop. Category grids step 3, 4, then 6. Feature panels pad 28px (20px on phones) and use a two-column grid (a fixed 230 to 300px copy column next to a fluid content area) that stacks below 1021px. Horizontal rails (tabs, category nav) scroll without a visible scrollbar. Every interactive target is at least 44px tall.

## Elevation & Depth

The system is hybrid but shallow. Canvas-level surfaces (cards, section cards) rest on a 1px hairline with an almost invisible navy-tinted shadow. Product and category cards lift on hover with a soft, diffuse shadow, and product cards also rise 2px. Navy panels and Seller Center surfaces are flat: they carry their weight through color, not shadow. Modal dialogs are the only deeply lifted layer.

### Shadow Vocabulary
- **Resting card** (`box-shadow: 0 1px 2px rgba(24,44,72,.04)`): product cards and section cards at rest.
- **Hover lift** (`box-shadow: 0 14px 30px rgba(15,23,42,.10)`): product card hover, with `translateY(-2px)`. Category cards use `0 12px 28px rgba(15,23,42,.10)` without the translate.
- **Focus ring** (`box-shadow: 0 0 0 3px rgb(32 91 244 / 14%)`): form fields on focus.
- **Dialog** (`filter: drop-shadow(0 24px 28px rgb(15 23 42 / 26%))`): the confirmation dialog only.

### Named Rules
**The Flat Slab Rule.** Navy panels never take a shadow. Their depth comes from color contrast with the canvas.

**The Earned Lift Rule.** Shadows grow only in response to hover on a clickable card. Nothing floats at rest.

## Shapes

Corners are gently curved and consistent: 8px on surfaces (cards, panels, image tiles) and 6px on controls (buttons, fields, rank chips). Small flags take 4px. Discount badges on product cards are full pills. A folder tab and its panel form one silhouette: the selected tab rounds only its top corners (8px 8px 0 0), and the panel it opens drops its top-left corner (0 8px 8px 8px), so the two read as one sheet. On phones the panel squares its top edge as well. Images fill their tiles edge to edge with `object-fit: cover` and zoom slightly (scale 1.04) on hover.

## Components

### Buttons
Solid, compact, and always at least 44px tall.
- **Shape:** gently curved (6px).
- **Primary:** Working Blue fill, white 14px semibold text, 20px horizontal padding. Deepens to Deep Working Blue on hover. Disabled is slate grey.
- **Secondary:** white fill, hairline border, navy text. On hover the border and text turn blue.
- **Quiet:** blue semibold text with no fill, used for inline actions and section links.
- **On navy:** a white button with navy text, hovering to Info Wash, with a 2px white focus outline.
- **Focus:** a 2px Working Blue ring or outline with a 2px offset (white on navy).

### Chips
- **Rank chip:** a 28px navy square (6px corners) holding a bold white number, followed by a muted 13px "N sold" line. It ranks trending products.
- **Badge strip:** square-cornered 11px uppercase strips along the bottom edge of a product image (blue, and COD Amber).
- **Window flag:** a 4px-cornered white label ("Sold out"), or Sale Red ("On sale"), at the top-left of a window tile.

### Cards / Containers
- **Corner Style:** 8px.
- **Background:** Surface White on Cool Canvas.
- **Shadow Strategy:** Resting card, and Hover lift for clickable cards (see Elevation & Depth).
- **Border:** 1px Hairline.
- **Internal Padding:** 14px, or 16px from 640px up, on product cards. 20 to 24px on section cards.
- **Product card:** image at a 1.15 aspect ratio, then the store name (12px muted, with a small storefront icon), a 2-line product name, the price (red when on sale, with the struck original price in muted text), a rating and sold line, and a full-width 44px action button that fills navy on hover.
- **Category card:** a centered 64px image or icon, the name, and an optional count, with a minimum height of 144px. Selected is a blue border on a pale blue fill.

### Inputs / Fields
- **Style:** white, 1px hairline, 6px corners, at least 44px tall, 14px text (16px on phones to prevent zoom).
- **Focus:** the border turns Working Blue, plus the 3px translucent blue focus ring. No outline.

### Navigation
- **Folder tabs:** Paper Deep tabs with top-only 8px corners, 14px semibold text, and a muted tabular count. Hover fills Strong Hairline. Selected fills Navy Ink with white text and joins the panel below. The tablist scrolls horizontally with arrow, Home and End key support.
- **Breadcrumb:** 13px muted text, with the current page in semibold navy, truncated with an ellipsis.

### Navy Panel (signature)
A flat Navy Ink slab with 8px corners and 28px padding. White display or title type, Navy Soft Text for secondary copy, and a single light button for the exit action. Product tiles inside it use Window Tile Navy as the image placeholder fill and a Night Navy gradient scrim to keep labels legible over photos. The store window and the deals band are both navy panels. A store window's shelf is one large tile beside four small ones.

### Bordered Panel
The light counterpart to the navy panel: Surface White, 1px Hairline, 8px corners, 28px padding. Steps inside it are separated by a 1px Strong Hairline left rule rather than numbers or icons.

## Do's and Don'ts

### Do:
- **Do** use Navy Ink (#182c48) as the fill for any section that needs feature weight, with white type and one light exit button.
- **Do** reserve Working Blue (#205bf4) for buttons, links, focus and selection.
- **Do** keep surface corners at 8px and control corners at 6px, and join a tab to its panel by squaring the panel corner the tab sits on.
- **Do** set every price, count and rank in tabular figures.
- **Do** give every interactive element a 44px minimum height and a visible 2px focus outline.

### Don't:
- **Don't** put a shadow on a navy panel; its depth is its color.
- **Don't** fill a section or panel with Working Blue.
- **Don't** introduce a second typeface; hierarchy comes from Inter's weights.
- **Don't** use the Display tier (32px and up) outside a navy panel.
