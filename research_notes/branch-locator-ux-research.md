# Pure Branch Locator UX Research & Flutter Architecture Brief (Everyday Pharmacy)

**Prepared:** 2026-11-26  
**Target Feature:** Standalone Physical Branch Locator (`/branches`, `BranchLocatorScreen`) — separate from Shop tab's ordering branch switcher  
**Target App:** Everyday Pharmacy (`mobile/`, Flutter + `flutter_map: ^8.3.2` + `geolocator: ^14.1.2` + `url_launcher: ^6.3.3`)  
**Companion Mockup:** [flutter-branch-locator-mockup.html](../mockups/flutter-branch-locator-mockup.html)

---

## 1. Executive Summary

A **Pure Branch Locator** exists solely so buyers can discover where physical Everyday Pharmacy branches are located, verify opening hours and on-site facilities, contact the branch, and launch turn-by-turn directions — completely decoupled from shopping cart switching, delivery fees, or inventory selection.

Today, Everyday Pharmacy's Shop header and **Account → Branches** ([account_screen.dart](../mobile/lib/src/screens/account_screen.dart)) both trigger `showBranchSelectorSheet` in [store_locator.dart](../mobile/lib/src/widgets/store_locator.dart), a plain vertical list of `ListTile` rows designed for picking an online ordering branch. Keeping the Shop tab's fast ordering picker separate while launching a dedicated **Branch Locator** screen (`/branches`) gives physical visitors a world-class map-and-directory experience powered by packages already installed in `mobile/pubspec.yaml`.

---

## 2. Industry Benchmarks: Pure Store & Branch Finders

| App / Pattern Leader | Core Locator Pattern | High-Impact UX Details Applied | Anti-Patterns Avoided |
| :--- | :--- | :--- | :--- |
| **Apple Maps / Google Maps** | **3-Detent Bottom Sheet** (`Peek` / `Half` / `Full`) + Route Preview | • Dashed route preview line (`PolylineLayer`) + travel distance/ETA badge (`1.4 km · 6 min`).<br>• Camera auto-offset upward so selected pins never hide beneath the sheet.<br>• Explicit **"Search this area"** pill after dragging the map.<br>• Single-tap **Google Maps / Waze** chooser as primary CTA. | • Drag-only sheet resizing without single-tap controls.<br>• Unrestricted map rotation that disorients visitors. |
| **Airbnb** | **Stateful Pins + Clustering** | • Selected pins scale up in brand navy with subtle pulse; zoom-out collapses dense city pins into numbered cluster badges.<br>• Tapping a cluster zooms directly into that district. | • Overlapping crowded pins in dense Metro hubs.<br>• Jarring abrupt jumps instead of smooth camera moves. |
| **Chase Branch Finder** | **Facility & Accessibility Filters** | • Scannable horizontal filter chips: `Open now`, `24 Hours`, `Parking`, `Accessible`.<br>• Physical on-site facility pills inside Branch Details (`24/7 Night Window`, `Wheelchair Accessible`, `Pharmacist On Duty`). | • Unlabelled icon grids without readable text. |
| **Mercury Drug / Watsons** | **24/7 Clarity, Landmarks & Direct Call** | • High-contrast `Open · Closes 10:00 PM` and `Open 24 hours` badges.<br>• Street **Landmark** callouts (*"Beside Cebu Provincial Capitol"*, *"Across PGH Gate 2"*) + 1-tap `Call`, `Copy`, and `Share`. | • Legacy `Region > Province > City > Barangay` cascaded dropdowns; defaults to GPS proximity + instant search. |
| **Apple Store** | **Muted Cartography & Storefront Cue** | • Muted daylight/dark map canvas so Everyday Pharmacy pins pop immediately.<br>• Storefront visual card + GPS coordinates + arrival context inside the Branch Detail sheet. | • Cluttered street maps competing with branch pins. |

---

## 3. Existing Everyday Pharmacy Stack Audit

### Recommended Separation in Flutter
- **Shop Tab Ordering Picker (Unchanged Role):** Continues using `nearestStoreProvider` / `cartProvider` to choose which branch fulfills an online cart order.
- **Standalone Branch Locator (`/branches`, `BranchLocatorScreen`):** Opened from **Account → Branches** (or a dedicated Branch Locator entry point), focused 100% on physical store discovery:
  - **Primary CTA:** **Get Directions** (Google Maps / Waze / Copy Address / Copy GPS Coordinates via `url_launcher`).
  - **Secondary Actions:** **Branch info** (full detail sheet), **Call** (`tel:` link), **Copy**, and **Share**.
  - **Zero Shopping Friction:** No cart prompts, no delivery fee rules, and no checkout lock-in.

### Installed Packages (`mobile/pubspec.yaml`)
All core capabilities are already present in `mobile/pubspec.yaml`:
- `flutter_map: ^8.3.2` & `latlong2: ^0.10.1` (`TileLayer`, `PolylineLayer`, `MarkerLayer` already proven in `DeliveryPinScreen`)
- `geolocator: ^14.1.2` (distance calculation & GPS permission flow via `LocationService`)
- `url_launcher: ^6.3.3` (Google Maps, Waze, and `tel:` dialing)
- `phosphor_icons: ^3.0.1` & `go_router: ^18.1.2`

### Backend Data Readiness (`/api/v1/store-locations`)
Inspected in [CatalogController.php](../app/Http/Controllers/Api/V1/CatalogController.php), [StoreResource.php](../app/Http/Resources/Api/V1/StoreResource.php), [Store.php](../app/Models/Store.php), and [PharmacySeeder.php](../database/seeders/PharmacySeeder.php):

| Data Need | Currently in `StoreResource.php`? | Action When Implementing in Flutter/Laravel |
| :--- | :--- | :--- |
| `id`, `name`, `slug`, `city`, `province`, `latitude`, `longitude` | Yes | Ready for map pins, clustering, distance/ETA, and deep-link coordinates |
| `opening_time`, `closing_time`, `open_24_hours`, `is_open`, `next_opening_at` | Yes | Ready (parse `opening_time` / `closing_time` in `StoreBranch.fromJson` for *"Closes 10:00 PM"*) |
| `address_line`, `barangay`, `postal_code`, `phone` | Exists on `Store` model, **not yet in `StoreResource`** | Optional 1-line additions to `StoreResource.php` (must deploy to Render per `.ai/rules/general.md`) |
| `landmark`, `has_parking`, `wheelchair_accessible` | Not yet modeled | Optional future fields on `stores` if branch managers curate arrival notes & facility flags |

---

## 4. Design System & UI/UX Pro Max Requirements

Aligned strictly with [mobile/lib/src/theme.dart](../mobile/lib/src/theme.dart):
1. **Palette & Rules:** Brand Navy `#000B88` (primary Directions CTA, active pin, cluster), Sky Cloud `#E4F2FD` (`Nearest` badge & sky header), Sun Yellow `#F8BF0B` (fill-only `24 Hours` accent paired with `#000B88` navy ink — never yellow text on white), Open green `#2F855A` / `#7ADFA0`, Closed red `#BA1A1A` / `#FFB4AB`.
2. **Typography & Tabular Numerals:** `Manrope` family, tight heading tracking, `FontFeature.tabularFigures()` on distances, driving/walking ETAs, hours, and coordinates.
3. **Accessibility (WCAG 2.2 AA):**
   - `48×48dp` Android / `44×44pt` iOS touch targets with `>= 8dp` spacing.
   - **Dragging Alternative:** Sheet drag handle paired with single-tap `Show list` / `Expand` / `Show map` buttons.
   - **Non-Color-Only Status:** `Open`, `Closed`, and `24 Hours` badges always pair icon + label.
   - **Reduced Motion & Dynamic Type:** Honor `MediaQuery.disableAnimationsOf(context)` and text scaling (`1.3×+`), with zero emoji icons.

---

## 5. Four Pure Branch Locator Directions (Evaluated in Mockup)

1. **Variant A — Map + 3-Detent Sheet (`Recommended`):** Full-bleed map with dashed route preview line, search + facility chips, zoom-out clustering, `"Search this area"`, and Peek / Half / Full sheet leading with `Branch info` & `Directions`.
2. **Variant B — Map + Snap Carousel (`Explore-first`):** Maximized map with floating bottom card switcher (`Prev` / `Next` steppers) and `"List"` toggle pill.
3. **Variant C — List-First + Mini-Map (`Quick Directory`):** `skySlab` header with `List | Map` segmented control, collapsible `148dp` map preview, and `Nearest to You` vs `Other Branches` sections.
4. **Variant D — All-Cities Directory (`Nationwide View`):** Cross-city list spanning Cebu City and Metro Manila with city jump pills and 1-tap `View on map` / `Directions` / `Call` actions.
