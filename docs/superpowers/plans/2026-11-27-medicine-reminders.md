# Medicine Reminders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After buyers receive medicine, the app reminds them to take each dose and predicts when the medicine will run out. It offers a reorder before that date, and adds family profiles, missed-dose nudges, expiry alerts, caregiver alerts and dose history.

**Architecture:** Laravel stores the plans and dose logs, and computes the supply, run-out and expiry dates in one tested support class. The Flutter app schedules local notifications from those plans on a rolling 14-day window, and dose actions post logs back. A scheduled Laravel command pushes caregiver alerts through the existing `PushNotifier`.

**Tech Stack:** Laravel 12, PHP 8.5, PHPUnit, Postgres (prod) and SQLite (tests); Flutter, Riverpod 3, go_router, flutter_local_notifications 18, timezone.

**Spec:** `docs/superpowers/specs/2026-11-27-medicine-reminders-design.md`

## Global Constraints

- Asia/Manila only. `dose_times` are `"HH:MM"` strings in Asia/Manila.
- Lead time is 5 days, the count prompt 7 days, the reorder batching window 7 days, the nudge +30 min, and the default caregiver delay 60 min.
- A slot with no log that is more than 24 h old counts as taken for supply.
- `medicine_kind` ∈ `maintenance`, `course`, `as_needed`, `none` (default `none`).
- Never invent a dose. Plans can only be created with a dose the pharmacy or buyer entered.
- The lock screen text is "Time for your medicine" / "Time for {profile}'s medicine" unless `show_name_on_lock_screen`. Caregivers see medicine names only when `share_medicine_names`.
- Every endpoint is owner-scoped (another user's record → 404). No plan endpoint works before consent (→ 403). Withdrawing consent hard-deletes plans, logs, profiles and caregiver links.
- Every reminder body ends with "Follow your doctor's or pharmacist's directions."
- New dependency: only `timezone` (approved). No other packages.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.

## Review Focus

1. **A syrup or half-tablet dose (decimal `dose_quantity`, mL units):** supply math must not truncate. Pinned in Task 2 (`test_decimal_doses_in_millilitres`).
2. **A repurchase while a plan is active:** it adds to the same plan and must not create a second one; the same order item can never be added twice. Pinned in Task 3 (`test_a_repurchase_tops_up_the_active_plan_once`).
3. **An order item that isn't delivered, or belongs to another buyer:** it must not be accepted as a plan source. Pinned in Task 3 (`test_only_the_buyers_delivered_items_can_start_a_plan`).
4. **The caregiver command running again in the same slot:** it must never alert twice. Pinned in Task 5 (`test_one_alert_per_missed_slot`).
5. **A dose time near midnight in Manila while the server runs in UTC:** the slots must be computed in Asia/Manila. Pinned in Task 2 (`test_slots_are_computed_in_manila_time`).
6. **A family with many plans:** Android allows about 500 pending alarms per app, so `buildAlarms` caps at 400 by shortening the window, and the "open the app" alarm stays last. Pinned in Task 7 (`caps alarms at 400`).
7. **A Taken tap while the app is killed:** it arrives through `getNotificationAppLaunchDetails()` and must still log. Pinned in Task 7/8 (the launch-details route test).
8. **Account deletion anonymises users (no row delete), so FK cascades never fire:** `DeleteCustomerAccount` must delete medication data. Pinned in Task 3 (`test_deleting_the_account_deletes_medication_data`).

---

### Task 1: Schema and models

**Files:**
- Create migration `database/migrations/2026_11_27_100000_add_medicine_reminders.php`
- Create models `app/Models/CareProfile.php`, `MedicationPlan.php`, `MedicationDoseLog.php`, `CaregiverLink.php`, `CaregiverAlert.php`
- Modify `app/Models/Product.php` (fillable and casts), `app/Models/OrderItem.php` (fillable, cast, `medicationPlans()`), `app/Models/User.php` (cast, relations)

**Schema (ULID primary keys like the siblings):**
- `products` (all nullable): `medicine_kind` string(20) default `none` not null, `pack_units` decimal(8,2), `dose_quantity` decimal(6,2), `dose_unit` string(20), `doses_per_day` unsignedTinyInteger, `course_days` unsignedSmallInteger, `min_hours_between` unsignedTinyInteger, `max_doses_per_day` unsignedTinyInteger, `directions_note` string(200)
- `order_items.medication_directions` json nullable
- `users.medication_reminders_consent_at` timestamp nullable
- `care_profiles`: id, user_id (fk cascade), name string(60), relation string(30) nullable, timestamps
- `medication_plans`: id, user_id (fk cascade), care_profile_id (nullable fk cascade), product_id (fk, nullOnDelete, nullable), product_name string(160), product_variant_id nullable ulid, medicine_kind, dose_quantity decimal(6,2), dose_unit string(20), dose_times json, min_hours_between, max_doses_per_day nullable, directions_note nullable, starts_on date, ends_on date nullable, units_anchor decimal(10,2), anchored_at timestamp, expires_on date nullable, show_name_on_lock_screen bool default false, status string(12) default `active`, last_count_prompt_at timestamp nullable, timestamps
- `medication_plan_order_items`: medication_plan_id (fk cascade), order_item_id (fk cascade, **unique**), units decimal(10,2)
- `medication_dose_logs`: id, medication_plan_id (fk cascade), scheduled_for timestamp nullable, status string(8), quantity decimal(6,2), logged_at timestamp, client_uuid uuid **unique**, timestamps
- `caregiver_links`: id, patient_user_id (fk users cascade), caregiver_user_id nullable (fk users cascade), invited_email string, status string(10) default `pending`, notify_after_minutes unsignedSmallInteger default 60, share_medicine_names bool default false, timestamps, unique(patient_user_id, invited_email)
- `caregiver_alerts`: caregiver_link_id (fk cascade), medication_plan_id (fk cascade), slot_at timestamp, created_at; **unique(caregiver_link_id, medication_plan_id, slot_at)**

**Interfaces (produces):**
- `User::careProfiles(): HasMany`, `User::medicationPlans(): HasMany`, `User::caregiverLinks(): HasMany` (as patient), `User::hasMedicationConsent(): bool`
- `MedicationPlan`: casts `dose_times` array, decimals as `decimal:2`, dates; `doseLogs(): HasMany`, `orderItems(): BelongsToMany` (pivot `units`), `careProfile(): BelongsTo`, `product(): BelongsTo`, `user(): BelongsTo`
- `Product::medicationDirections(): ?array` → null when `medicine_kind === 'none'` or `dose_quantity` is null; otherwise `{medicine_kind, pack_units, dose_quantity, dose_unit, doses_per_day, course_days, min_hours_between, max_doses_per_day, directions_note}`
- `OrderItem::medicationDirections(): ?array` → `medication_directions ?? product?->medicationDirections()`. Deviation from the spec: there's no snapshot at checkout, so checkout and money code stay untouched. The pharmacist's override is the only stored copy, and otherwise the product's current directions are used. They're only a prefill the buyer confirms.

- [ ] Write the migration and models. **Never run `php artisan migrate` (or `--pretend`) from this checkout: it connects to the live Neon DB.** Verify through PHPUnit (SQLite in memory): `php artisan test --compact tests/Feature/ExampleTest.php`. Expected: PASS. CI's PostgreSQL job checks the migration on Postgres.
- [ ] Pint.

### Task 2: `MedicationSupply` calculator

**Files:** Create `app/Support/MedicationSupply.php`; Test `tests/Unit/MedicationSupplyTest.php` (unit, no DB: build unsaved `MedicationPlan` models with `setRelation('doseLogs', collect([...]))`).

**Interfaces (produces):**
- `MedicationSupply::slots(MedicationPlan $plan, CarbonInterface $from, CarbonInterface $to): list<CarbonImmutable>`: scheduled dose instants in UTC from `dose_times` interpreted in Asia/Manila, limited to `[starts_on, ends_on]`. Empty for `as_needed`.
- `MedicationSupply::summary(MedicationPlan $plan, CarbonInterface $now): array{units_left: float, daily_units: ?float, run_out_on: ?string, reorder_on: ?string, count_prompt_on: ?string}` (dates as `Y-m-d` in Manila)

**Algorithm** (as in the spec):
```
consumed  = Σ quantity(taken logs with logged_at ≥ anchored_at)
          + dose_quantity × |slots(anchored_at, now − 24h) without any log for that slot|
units_left = max(0, units_anchor − consumed)
daily     = maintenance/course: dose_quantity × count(dose_times)
            as_needed: (Σ taken quantity in last 14 days) ÷ 14, null if fewer than 3 taken logs
course    → run_out_on/reorder_on/count_prompt_on = null
run_out_on = today(Manila) + floor(units_left ÷ daily) days; reorder = run_out − 5; count_prompt = run_out − 7
```
A log "covers" a slot when `scheduled_for` equals the slot instant.

- [ ] **Tests** (each asserts exact values):
  - `test_maintenance_run_out_counts_assumed_doses`: now = 2026-12-10 12:00 Manila, anchored_at = 2026-12-05 12:00 Manila, anchor 30, dose 1 at `["08:00","20:00"]`, no logs. 8 slots are older than 24 h, so units_left 22.0, run_out_on "2026-12-21", reorder_on "2026-12-16", count_prompt_on "2026-12-14".
  - `test_skipped_doses_are_not_consumed`: the same, with 2 slots logged `skipped` → units_left 24.
  - `test_decimal_doses_in_millilitres`: anchor 60 mL, 5 mL × 3/day, 2 full days of taken logs (6 × 5) → units_left 30.0, run_out_on = today + 2.
  - `test_as_needed_uses_logged_average_and_needs_three_logs`: 2 logs → run_out_on null; 7 taken logs of 1 within 14 days, anchor 20 → daily 0.5, units_left 13, run_out_on today + 26.
  - `test_course_has_no_run_out`: course → run_out_on null.
  - `test_slots_are_computed_in_manila_time`: dose time `"00:30"` on 2026-12-01 (Manila) → slot `2026-11-30T16:30:00Z`.
- [ ] Run `php artisan test --compact tests/Unit/MedicationSupplyTest.php`. Expected: FAIL, then implement, then PASS.

### Task 3: Buyer API (consent, plans, suggestions, doses, count, history, profiles)

**Files:** Create `app/Http/Controllers/Api/V1/MedicationConsentController.php`, `MedicationPlanController.php`, `MedicationDoseController.php`, `MedicationHistoryController.php`, `CareProfileController.php`; `app/Http/Resources/Api/V1/MedicationPlanResource.php`; `app/Actions/StartMedicationPlan.php`; modify `routes/api.php` (a new group with the same middleware as `delivery-addresses`, plus an `EnsureMedicationConsent` check written as an inline `abort_unless($request->user()->hasMedicationConsent(), 403)` in a controller constructor helper or in each action, following sibling style); Test `tests/Feature/MedicationPlanApiTest.php`.

**Routes (names `api.v1.…`):**
- `PUT /me/medication-consent` → `medication-consent.store` (sets now); `DELETE` → `medication-consent.destroy` (hard-deletes plans, profiles, caregiver links as patient, and clears the consent)
- `GET /medication-plans` → index (active and paused, with `summary`, `expires_on`, `profile`, `pending_logs` not needed)
- `GET /medication-plans/suggestions` → order items of this buyer from `delivered` seller orders in the last 120 days that aren't in `medication_plan_order_items`. Items with directions are listed first. Returns `{order_item_id, product_id, product_name, quantity, directions|null}`
- `POST /medication-plans` body `{order_item_id?, product_id?, care_profile_id?, dose_quantity, dose_unit, dose_times[], starts_on, units_on_hand?, medicine_kind, course_days?, min_hours_between?, max_doses_per_day?, directions_note?, show_name_on_lock_screen?}`
- `PATCH /medication-plans/{plan}` (dose, times, profile, lock-screen flag, status `active|paused`); `DELETE /medication-plans/{plan}`
- `POST /medication-plans/{plan}/doses` body `{logs: [{client_uuid, status: taken|skipped, scheduled_for?, logged_at, quantity?}] max 50}`. Duplicate `client_uuid` values are ignored (insertOrIgnore). For `as_needed` taken logs, the response includes a `warning`: `"too_soon"` when the last taken log was less than `min_hours_between` ago, or `"daily_max"` when the count in the last 24 h exceeds `max_doses_per_day`.
- `POST /medication-plans/{plan}/count` body `{units_left}` → anchor = units_left, anchored_at = now, last_count_prompt_at = now
- `GET /medication-history?care_profile_id=&days=30|90` → per plan: `slots: [{scheduled_for, status: taken|skipped|not_marked}]` for past slots, plus `adherence` = taken ÷ past slots (null when there are 0 slots)
- `GET|POST /care-profiles`, `PATCH|DELETE /care-profiles/{careProfile}`

**`StartMedicationPlan::handle(User $buyer, array $data): MedicationPlan`**: in a transaction.
- With `order_item_id`: the item must belong to `$buyer` (through `sellerOrder.order.user_id`) and its seller order must be `delivered`, otherwise 404. Units = `pack_units × quantity`, or `units_on_hand` when `pack_units` is null (if both are missing → validation error `units_on_hand`).
- An active plan with the same `product_id` and `care_profile_id` → top it up (anchor = `summary.units_left` + units, anchored_at = now) and attach the pivot. Otherwise create a plan with `ends_on = starts_on + course_days − 1` for a course.
- `expires_on` = min(existing expires_on, min `inventory_batches.expires_at` over the item's `inventoryAllocations`).

**Resource fields:** `id, product_id, product_name, care_profile {id,name}|null, medicine_kind, dose_quantity (string), dose_unit, dose_times, starts_on, ends_on, status, show_name_on_lock_screen, min_hours_between, max_doses_per_day, directions_note, units_left (float), run_out_on, reorder_on, count_prompt_on, expires_on, expires_before_run_out (bool)`.

- [ ] **Tests:**
  - `test_plans_need_consent`: POST without consent → 403; PUT consent → 204; POST → 201.
  - `test_suggestions_list_delivered_medicine_once`: a delivered item appears (with directions when its product has them); after a plan is created from it, it's gone; an item from a `pending` seller order never appears.
  - `test_starting_a_plan_from_an_order_item_uses_pack_units_and_batch_expiry`: pack 30 × qty 2 → `units_left` 60.0; allocation batch expires 2027-01-10 → `expires_on` "2027-01-10".
  - `test_a_repurchase_tops_up_the_active_plan_once`: a second delivered item → the same plan id, units added; reposting the same order item → 422 or 404, and the count is unchanged.
  - `test_only_the_buyers_delivered_items_can_start_a_plan`: another buyer's item → 404; an undelivered item → 404.
  - `test_dose_logs_are_idempotent_and_warn_for_as_needed_limits`: the same client_uuid twice → 1 row; as-needed with min 4 h, a second dose after 1 h → `warning` `too_soon`.
  - `test_count_resets_the_supply`.
  - `test_history_marks_unlogged_slots_and_computes_adherence`: 2 days × 1 slot, 1 taken → adherence 0.5.
  - `test_another_users_plan_is_not_found` (PATCH, DELETE, doses → 404).
  - `test_withdrawing_consent_deletes_everything`.
  - `test_deleting_the_account_deletes_medication_data`: `DeleteCustomerAccount` (modify `app/Actions/DeleteCustomerAccount.php`) removes plans, profiles and caregiver links (as patient and as caregiver), and clears consent.
  - `test_a_buyer_adds_a_delivered_medicine_without_directions_manually`: a delivered item whose product has `medicine_kind` `none` → POST with `order_item_id`, the buyer's own dose, `medicine_kind`, and `units_on_hand` 20 → 201, units_left 20.0. Suggestions return **every** delivered item not yet in a plan, with `directions` null when the product has none, so the app offers "Add a medicine" with an empty form. This covers the spec's buyer-entered path, because all live products start at `none`.
- [ ] Run `php artisan test --compact tests/Feature/MedicationPlanApiTest.php`: FAIL, implement, PASS. Pint.

### Task 4: Seller directions and pharmacist override

> Do this task **last**. Codex is running in this tree, and HEAD `86524e99` is its Seller Center redesign. Check `git status` and ask the owner before editing Seller Center views.

**Files:** Modify `app/Http/Requests/StoreProductRequest.php` and `UpdateProductRequest.php` (rules + `after` checks), `resources/views/seller/products/form.blade.php` (a "Directions & reminders" block next to dosage form/strength, using existing `pe-label`/`pe-input` classes), `app/Http/Controllers/Seller/SellerOrderController.php` (or a new invokable `Seller/OrderItemDirectionsController.php`) + route `seller.orders.items.directions` (PATCH), and the seller order show view (a small form per medicine item, shown only when the order has a prescription); Test: add to `tests/Feature/SellerProductTest.php` and `tests/Feature/PrescriptionTest.php`.

**Rules:** `medicine_kind` nullable in:maintenance,course,as_needed,none; `pack_units` nullable numeric 0.01–9999; `dose_quantity` nullable numeric 0.01–999; `dose_unit` nullable string max 20; `doses_per_day` nullable int 1–12; `course_days` nullable int 1–365; `min_hours_between` nullable int 1–48; `max_doses_per_day` nullable int 1–24; `directions_note` nullable string max 200. In `after`: maintenance needs `doses_per_day` and `dose_quantity`; course needs `course_days`, `doses_per_day` and `dose_quantity`; as_needed needs `max_doses_per_day` and `dose_quantity`.
**Override:** `PATCH seller/orders/{sellerOrder}/items/{orderItem}/directions`, the same fields minus `pack_units`, saved to `order_items.medication_directions`. Tenant-scoped: another tenant's order → 404. Only `pharmacist` or `owner` members (match the existing `ReviewPrescription` permission check).

- [ ] Tests: `test_seller_saves_medicine_directions`, `test_maintenance_directions_need_doses_per_day` (session error), `test_pharmacist_overrides_item_directions`, `test_other_tenant_cannot_override_directions` (404).
- [ ] Run both test files: PASS. Pint.

### Task 5: Caregivers and the missed-dose command

**Files:** Create `app/Http/Controllers/Api/V1/CaregiverController.php`; routes `GET /caregivers` (links where I'm the patient, plus pending invitations to my email), `POST /caregivers {email, notify_after_minutes?, share_medicine_names?}`, `POST /caregivers/{link}/accept` (only the invited email's user; sets caregiver_user_id), `DELETE /caregivers/{link}` (patient or caregiver); add `medications:check-missed` to `routes/console.php` `->everyFifteenMinutes()`; Test `tests/Feature/CaregiverAlertTest.php`.

**Command:** for each accepted link whose patient has consent, for each active non-as-needed plan of the patient **without** a care profile (the patient's own medicine), check slots in `[now − 6h, now − notify_after_minutes]` without a log. For each, `insertOrIgnore` into `caregiver_alerts`. When inserted, call `PushNotifier::sendToUser($caregiver, 'Medicine reminder', "{$patient->name} hasn't marked their {time} medicine as taken.", ['type' => 'medication'])`. The text uses `"{$patient->name} hasn't marked {product_name} ({time}) as taken."` when `share_medicine_names`. `{time}` = `g:i A` in Manila.

- [ ] Tests (fake `PushNotifier` by binding a spy, as `PushNotificationsTest` does): `test_invite_accept_and_remove`, `test_only_the_invited_user_can_accept` (404), `test_one_alert_per_missed_slot` (run the command twice → 1 push), `test_medicine_name_hidden_unless_shared`, `test_logged_or_recent_slots_do_not_alert`.
- [ ] PASS. Pint.

### Task 6: App data layer

**Files:** Create `mobile/lib/src/models/medication.dart` (`MedicationPlan`, `MedicationSuggestion`, `CareProfile`, `CaregiverLink`, `DoseHistory` with `fromJson`); modify `mobile/lib/src/core/api_client.dart` (one method per Task 3/5 route: `setMedicationConsent(bool)`, `medicationPlans()`, `medicationSuggestions()`, `startMedicationPlan(Map)`, `updateMedicationPlan(id, Map)`, `deleteMedicationPlan(id)`, `logDoses(planId, List<Map>) → String? warning`, `countMedication(planId, double)`, `medicationHistory({profileId, days})`, `careProfiles()`, `saveCareProfile(...)`, `deleteCareProfile(id)`, `caregivers()`, `inviteCaregiver(...)`, `acceptCaregiver(id)`, `removeCaregiver(id)`); add `medication_reminders_consent` (bool) to `Account` (and to `UserResource` server-side); create `mobile/lib/src/providers/medication_providers.dart` (`medicationPlansProvider`, `medicationSuggestionsProvider`, `careProfilesProvider`, `caregiversProvider` as `FutureProvider.autoDispose`, following `order_providers.dart`).
- [ ] Test `mobile/test/medication_models_test.dart`: parsing a plan JSON with decimal strings and null dates.
- [ ] Run `php artisan test --compact tests/Feature/MobileApiContractTest.php` after the `UserResource` change: PASS.

### Task 7: Alarm scheduler (pure builder plus the platform adapter)

**Files:** Create `mobile/lib/src/core/medication_alarms.dart`; modify `mobile/pubspec.yaml` (add `timezone`), `android/app/src/main/AndroidManifest.xml` (`SCHEDULE_EXACT_ALARM`, `RECEIVE_BOOT_COMPLETED`, the flutter_local_notifications `ScheduledNotificationReceiver` and `ScheduledNotificationBootReceiver`), `core/push_notifications.dart` (a `medicine` channel; `_handleResponse` appends `&action=<actionId>` for `taken`/`skip`; expose the plugin initialization for reuse).

**Interfaces:**
- `List<PlannedAlarm> buildAlarms(List<MedicationPlan> plans, DateTime nowManila, {int days = 14})`, pure. `PlannedAlarm {int id; DateTime at; String title; String body; String payload; bool withDoseActions}`. Its contents:
  - Per dose slot: a dose alarm, plus a nudge at +30 min with title "Did you take your medicine?".
  - `count_prompt_on` 09:00: "About how many {unit}s of {name} are left?"
  - The reorder alarm at 09:00 on `reorder_on`. Plans whose `reorder_on` falls within 7 days of the earliest one are grouped into one alarm, "{n} medicines are running low", payload `/medicines/reorder?plans=a,b`.
  - Expiry at 09:00 on `expires_on − 30` and on `expires_on`.
  - Course complete on `ends_on` 21:00.
  - The last alarm at window end: "Open the app to keep your reminders going."
  - Titles use the lock-screen rule. Bodies end with "Follow your doctor's or pharmacist's directions."
  - Payload for doses: `/medicines/dose?plan=<id>&slot=<iso>`.
  - The id is a stable hash of (plan id, kind, at), masked to 31 bits.
- `buildAlarms` caps the total at 400 by trimming the window day by day, and the "open the app" alarm moves to the last kept day.
- `class MedicationAlarms { static Future<void> init(); static Future<String?> launchRoute(); static Future<void> sync(List<MedicationPlan> plans); static Future<void> cancelNudge(String planId, DateTime slot); static Future<void> cancelMedicineAlarms(); }`
  - `init()` has its own initialization path, called from `startup.dart`, not from `PushNotifications.register()`. It sets the timezone to Asia/Manila and creates the `medicine` channel. If the plugin is already initialized by push, it reuses the same response callback (move the plugin initialization into one shared function in `push_notifications.dart` that both call).
  - `launchRoute()` reads `getNotificationAppLaunchDetails()` (with the action id appended), so a tap from a killed app opens `/medicines/dose?...&action=taken`.
  - Never call the plugin's `cancelAll()`, because it would dismiss chat and order notifications. `cancelMedicineAlarms` cancels only the ids from `pendingNotificationRequests()` whose payload starts with `/medicines`.
  - Before writing this, read the installed flutter_local_notifications 18.0.1 API in the pub cache (the `zonedSchedule` params such as `uiLocalNotificationDateInterpretation`, and whether `canScheduleExactNotifications` exists).
  - `sync` calls `cancelMedicineAlarms()` and schedules `buildAlarms` with `zonedSchedule`, using `AndroidScheduleMode.exactAllowWhileIdle` when `canScheduleExactNotifications()` and `inexactAllowWhileIdle` otherwise.
  - Dose alarms get actions `taken` ("Taken") and `skip` ("Skip"), both with `showsUserInterface: true`.
  - shortcut: the actions open the app to post the log, so there's no background isolate. Upgrade if users want silent Taken.
- [ ] Tests `mobile/test/medication_alarms_test.dart`:
  - two daily times × 14 days → 28 dose and 28 nudge alarms;
  - a hidden name → title "Time for your medicine"; a profile → "Time for Lola's medicine";
  - two plans with reorder dates 3 days apart → one reorder alarm;
  - an as_needed plan → no dose alarms;
  - course alarms stop at `ends_on`;
  - alarm ids are unique;
  - `caps alarms at 400`: 6 plans × 4 daily times → total ≤ 400, and the last alarm is "Open the app…".

### Task 8: App screens

**Files:** Create `mobile/lib/src/screens/medicines_screen.dart` (the list grouped by profile, today's doses with Taken/Skip, as-needed "I took one" with the warning dialog, suggestions banner, entry points to history, profiles and caregivers), `medicine_setup_screen.dart` (consent step when not consented, then the form prefilled from the suggestion's directions), `medicine_history_screen.dart`, `medicine_people_screen.dart` (care profiles plus caregiver invites and invitations), `medicine_reorder_sheet.dart` (adds each plan's product to the cart through the existing cart add, then opens `/cart`). Modify `router.dart` (`/medicines`, `/medicines/setup`, `/medicines/history`, `/medicines/people`, `/medicines/dose`, `/medicines/reorder`), `account_screen.dart` (a "Medicine reminders" row), `order_detail_screen.dart` (a "Set reminders" button on delivered medicine items with directions).
- The `/medicines/dose` route: posts the log for the `action` query value (`taken`/`skip`; with no action, it shows the dose card), cancels the nudge, then shows `/medicines`. The as-needed day-3 nudge appears as a card on the medicines screen ("Still feverish? Talk to a pharmacist" → the existing support chat) when an as_needed plan has taken logs on 3 different days in the last 4.
- After any plan change, sign-in or app resume: `MedicationAlarms.sync(plans)`. Withdrawing consent (on the medicines screen) → `cancelAll`.
- [ ] Widget tests `mobile/test/medicines_screen_test.dart`: the consent screen appears before the form; a prefilled form saves; as-needed "I took one" shows the `too_soon` warning text "It's been less than 4 hours since your last dose."; the empty state.
- [ ] Run `flutter test` (the whole suite): PASS. Run `flutter analyze` on the changed files: no issues.

### Task 9: Ship

- [ ] `php artisan test --compact` (full) and `flutter test`: all pass. Pint.
- [ ] Ask the owner before committing, pushing and opening the PR (release flow: push `new-direction`, PR to master, required-check green).
- [ ] Remind the owner to create the Render Cron Job (`php artisan schedule:run`, hourly is fine but `*/15` for caregiver alerts) and to fill in directions on medicine products.
