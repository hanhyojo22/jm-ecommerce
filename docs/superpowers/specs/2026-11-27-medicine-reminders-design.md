# Medicine reminders, run-out prediction and reorder — design

Date: 2026-11-27 · Status: approved 2026-11-27

## Intent

After a buyer receives medicine, the Everyday Pharmacy app helps them take it on time and know when it will run out. Before it runs out, the app offers a one-tap reorder. Two goals:

- **Buyers:** take the right dose on time, finish a course, and don't run out of maintenance medicine.
- **The pharmacy:** repeat orders that arrive at the right moment, not as spam.

## Goals (v1)

1. Dose reminders for every kind of medicine, each kind handled its own way (see the table below).
2. Predict the run-out date, ask once how much is left to correct the estimate, then send a reorder reminder that adds the items to the cart.
3. Family profiles: plans for a child or parent, managed on the buyer's phone.
4. Missed-dose nudge 30 minutes after a dose that hasn't been marked as taken.
5. Expiry alerts based on the batch the buyer actually received.
6. Caregiver alerts: another app user is told when doses aren't marked.
7. Dose history the buyer can show a doctor.

## Non-goals (v1)

- Seller dashboard of upcoming refills (idea 6). Deferred.
- Reminders on the website. The app only.
- Auto-reorder or subscriptions, SMS, drug-interaction checks.
- Suggesting a dose. The app never makes up a dose.

## Anti-goals (these make it a failure even if it works)

- A dose shown that neither the pharmacy nor the buyer entered.
- A fixed alarm for an as-needed medicine that nudges people into overuse.
- Medicine names on the lock screen, or shown to a caregiver, without the buyer choosing it.
- Reminder spam: more than one reorder reminder per run-out cycle, or one reminder per item when several run out together.

## Constraints found in the code

- `products` has `generic_name`, `dosage_form` and `strength`, but no pack size and no directions.
- `inventory_allocations` links an order item to `inventory_batches.expires_at`, so the expiry date of what a buyer received is known. Older order items may have no allocation, so skip expiry for those.
- The app already has `firebase_messaging` and `flutter_local_notifications` (currently used only to show foreground push messages) and `POST_NOTIFICATIONS`.
- An existing endpoint, `POST /api/v1/cart/reorder/{order}`, puts a past order's items into the cart. Reorder reuses the cart, and prescription items go through the existing checkout prescription flow.
- **Server scheduler:** `render.yaml` defines a cron service (`schedule:run` hourly), but production only runs the web and chat services. The scheduled jobs (`carts:remind`, `returns:remind` and others) are probably not running in production. Caregiver alerts depend on it (see Owner decisions).
- Medication history is sensitive personal information under the Philippine Data Privacy Act (RA 10173), so it needs prior, specific, recorded consent that can be withdrawn.
- New dependencies need the owner's approval (CLAUDE.md).

## Approaches considered

| Approach | Trade-off | Decision |
|---|---|---|
| **Server stores the plans and computes the dates; the phone sets local alarms** | Alarms are exact to the minute and work offline, with no dependency on the server scheduler. Plans survive a reinstall because they come back from the server. Phone alarms must be refreshed on a rolling window. | **Chosen** |
| Server sends every reminder by push | Needs the hourly scheduler (not running in production), is only accurate to the hour, and needs internet at dose time. | Rejected |
| Plans live only on the phone | Simplest, but they're lost on reinstall or a new phone, there are no caregiver alerts, and the prediction can't be tested on the server. | Rejected |

Dose source: the pharmacy sets label directions per product, a pharmacist can override them on a prescription order, and the buyer confirms or edits before reminders start. The owner accepted this recommendation.

## Design

### Medicine kinds (set by the seller per product)

| Kind | Dose reminders | Run-out and reorder | Notes |
|---|---|---|---|
| `maintenance` (amlodipine, vitamins) | Fixed times every day | Yes | |
| `course` (antibiotics) | Fixed times for `course_days`, then "Course complete" | No | Finishing the course is the goal. |
| `as_needed` (fever, pain) | No alarms. A **"I took one"** button | Only from logged doses | Warn when the label's `min_hours_between` or `max_doses_per_day` would be broken. On day 3 of logging: "Still feverish? Talk to a pharmacist" (opens the existing chat). |
| `none` (not a medicine) | No | No | Default for existing products. |

### Data

**products** (new nullable columns, edited in the Seller Center product form under "Directions & reminders"):
`medicine_kind` (default `none`), `pack_units` (e.g. 30, or 60 for a 60 mL syrup), `dose_quantity` (decimal: 1, 0.5, 5), `dose_unit` (tablet, capsule, mL, sachet…), `doses_per_day`, `course_days`, `min_hours_between`, `max_doses_per_day`, `directions_note` (free text, e.g. "after meals").

**order_items.medication_directions** (json): a snapshot of the product's directions at checkout. A pharmacist can edit it while reviewing a prescription, and it overrides the product's directions for that purchase.

**users.medication_reminders_consent_at** (timestamp, null = not opted in).

**care_profiles**: `user_id`, `name`, `relation`. A plan without a profile belongs to the buyer.

**medication_plans**: `user_id`, `care_profile_id?`, `product_id`, `source_order_item_id?`, `medicine_kind`, `dose_quantity`, `dose_unit`, `dose_times` (json, e.g. `["08:00","20:00"]`, Asia/Manila), `starts_on`, `ends_on?` (course), `units_anchor` + `anchored_at` (the last known amount on hand), `expires_on?`, `show_name_on_lock_screen` (default false), `status` (`active` / `paused` / `finished`), `last_count_prompt_at?`, `last_reorder_reminder_for?` (the run-out date already reminded about).

**medication_dose_logs**: `medication_plan_id`, `scheduled_for?` (null for as-needed), `status` (`taken` / `skipped`), `quantity`, `logged_at`, `client_uuid` (unique, so offline logs sync without duplicates).

**caregiver_links**: `patient_user_id`, `caregiver_user_id?`, `invited_email`, `status` (`pending` / `accepted`), `notify_after_minutes` (default 60), `share_medicine_names` (default false). `medication_plans` / `medication_dose_logs` are visible only to the owner, never to the seller. Caregivers receive alerts only and never read plans in v1.

### Supply and run-out algorithm (server, in one tested class)

```
daily_units   = dose_quantity × count(dose_times)          (maintenance/course)
consumed      = Σ quantity of `taken` logs since anchored_at
              + dose_quantity × scheduled slots since anchored_at that have no log
                and are more than 24 h old          (assume adherence: predicts earlier, which is safer)
units_left    = max(0, units_anchor − consumed)
run_out_on    = today + floor(units_left ÷ daily_units)
reorder_on    = run_out_on − 5 days                        (5 = lead time to order and receive)
count_prompt  = run_out_on − 7 days, once per cycle: "About N left?" → resets the anchor to the answer
```

- **As-needed medicine:** `daily_units` = the average of logged doses over the last 14 days. With fewer than 3 logs there's no prediction.
- **Setting up the plan:** `units_anchor = pack_units × quantity`. If `pack_units` is missing, the buyer enters how many units they received.
- **Buying the same medicine again:** a new delivery of the same product adds its units to the active plan (anchor = units_left + new units) instead of creating a second plan.
- **Batching:** active plans whose `reorder_on` dates fall within 7 days of each other get one reminder: "2 medicines are running low → Reorder".
- **Course:** no run-out prediction. `ends_on = starts_on + course_days − 1`. The plan becomes `finished` after the last dose.

### Expiry

`expires_on` = the earliest `expires_at` among the batches allocated to the source order item, updated when a repurchase merges in. Alerts:

- At set-up, if `run_out_on > expires_on`: "Part of this supply expires before you'll finish it."
- 30 days before expiry.
- On the expiry day: "Don't use after today. Reorder?"

These alerts are skipped once the plan is finished or the supply is used up.

### On the phone

- **Opt-in:** the first "Set reminders?" shows a consent screen (what's stored, who sees it, how to delete it). Accepting records `medication_reminders_consent_at`. Withdrawing it in Account → Medicine reminders hard-deletes the plans, logs, profiles and caregiver links, and cancels all local alarms.
- **Entry points:** an order detail screen with delivered or picked-up medicine shows "Set reminders". A new Account → **Medicine reminders** screen lists plans grouped by profile ("Me", "Lola") and shows today's doses.
- **Set-up form:** prefilled from `medication_directions`, with dose, times, start date, profile, and a lock-screen name toggle. Each reminder shows "Follow your doctor's or pharmacist's directions", plus `directions_note`.
- **Local notifications:**
  - Scheduled on a rolling 14-day window of individual alarms: each dose plus its +30 minute nudge, and the count prompt, reorder, expiry and course-complete dates the server returns.
  - The window is rebuilt on app open, after each sync, and after a plan changes. The last alarm in the window says "Open the app to keep your reminders going."
  - The lock-screen text is "Time for your medicine" (or "Time for Lola's medicine") unless `show_name_on_lock_screen` is on.
- **Notification actions:** **Taken** and **Skip**. Each action writes a local log and cancels that dose's nudge. Logs sync when online, using `client_uuid`.
- **Reorder notification:** opens a sheet with the batched items. "Add to cart" calls the existing cart add (per product and variant, at the current price and branch stock).
- **Dose history:** per profile, the last 30 or 90 days: each slot marked taken, skipped or not marked, plus an adherence percentage (taken ÷ scheduled). It's an in-app screen designed to be shown to a doctor.

### Caregiver alerts (server)

- **Invite:** the buyer invites a caregiver by email. That person accepts in their own app (Account → Medicine reminders → Invitations).
- **The check:** a scheduled command `medications:check-missed` looks at each accepted link. For each patient slot older than `notify_after_minutes` with no log, it sends one push to the caregiver through `PushNotifier`: "Maria hasn't marked her 8:00 AM medicine as taken." The name of the medicine is included only if `share_medicine_names` is on. It sends at most one alert per slot.
- **Wording:** the alert says "hasn't marked", because the patient's phone may simply be offline.

### Seller Center

- **Product form:** a "Directions & reminders" section that writes the new product columns. Validation: maintenance needs `doses_per_day`, course needs `course_days`, and as-needed needs `max_doses_per_day`.
- **Prescription review:** the pharmacist can edit each item's `medication_directions` before approving.

### API (v1, `auth` + `clerk.verified`, buyer only, owner-scoped)

- `PUT /me/medication-consent`, `DELETE /me/medication-consent`
- `GET /medication-plans` (includes `units_left`, `run_out_on`, `reorder_on`, `count_prompt_on`, `expires_on`)
- `POST /medication-plans`, `PATCH /medication-plans/{plan}`, `DELETE /medication-plans/{plan}`
- `GET /medication-plans/suggestions` (delivered medicine items without a plan)
- `POST /medication-plans/{plan}/doses` (batch of logs with `client_uuid`) and `POST /medication-plans/{plan}/count`
- `GET /medication-history?profile=&days=`
- `GET|POST|PATCH|DELETE /care-profiles`
- `GET|POST|DELETE /caregivers`, `POST /caregivers/{link}/accept`

Every endpoint rejects records that belong to another user, and plans can't be created before consent.

## Testing

- **PHPUnit:** the supply algorithm (adherence assumption, skipped doses, count reset, merging a repurchase, as-needed average, batching), expiry from allocations, owner scoping on every endpoint, consent gating and full deletion, the missed-dose command (one alert per slot, names hidden by default), and seller validation.
- **Flutter tests:** building the rolling window (dose + nudge, Taken cancels the nudge, lock-screen text), syncing offline logs without duplicates, and the as-needed limit warning.

## Your calls

Change any of these.

- **Lead time:** 5 days before run-out. **Count prompt:** 7 days before. **Reorder batching window:** 7 days. **Nudge:** +30 minutes. **Caregiver delay:** 60 minutes.
- **Missing taps:** a slot with no log after 24 hours counts as taken for supply, so predictions err early.
- **Lock screen:** medicine names hidden by default. **Caregivers:** names hidden by default.
- **Dose history:** an in-app screen in v1, with no PDF or share link.
- **Time zone:** Asia/Manila only (the buyers are in the Philippines).
- **Alarm window:** rolling 14 days of individual alarms instead of repeating alarms, so each day's nudge can be cancelled on its own.
- **Exact alarms:** request `SCHEDULE_EXACT_ALARM` and fall back to inexact alarms (a few minutes late) if it's refused.
- **Existing products** default to `none` until a seller fills in the directions.

## Owner decisions (2026-11-27)

1. **Server scheduler:** add a **Render Cron Job** that runs `php artisan schedule:run`. The owner creates it in the Render dashboard; don't sync the Blueprint. `medications:check-missed` runs every 15 minutes. This also restores the existing scheduled jobs (`carts:remind`, `returns:remind` and others).
2. **Caregivers need their own Everyday Pharmacy account.** They're invited by email, accept in the app, and get alerts as push notifications only.
3. **`timezone` is approved** as a direct dependency in `mobile/pubspec.yaml`.

Status: approved by the owner on 2026-11-27.
