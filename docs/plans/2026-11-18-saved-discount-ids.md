# Plan: saved senior citizen / PWD IDs

Branch: `new-direction`. Pull first; other agents commit here too. Do not merge to `master`.

## Goal

A customer types their senior citizen or PWD ID once. Next time, checkout offers it and they tap it instead of typing. That is the whole feature.

Out of scope for this plan (do not build): OCR or camera scanning, verification statuses, review queues, duplicate/velocity detection, hashing or peppers, PhilID QR. Revisit only once prepaid payment (GCash) ships or data shows a need.

## How the discount works today (read before changing anything)

- `app/Support/SeniorPwdDiscount.php`: `rules()` validates `discount_card.{type,id_number,name}`, `cardFrom($validated)` turns it into `['type' => 'senior'|'pwd', 'id_number' => ..., 'name' => ...]` or null, `mask()` shows only the last 4.
- `app/Actions/CheckoutCart.php::handle(..., ?array $discountCard)` prices the order and copies the card onto `orders.discount_card_type/number/name` (number uses the `encrypted` cast and is `$hidden`). The order keeps that copy forever. **Do not change `CheckoutCart`.**
- Callers: `app/Http/Controllers/Api/V1/CheckoutController.php` (`show` ~L91, `store` ~L155–172) and `app/Http/Controllers/Marketplace/CheckoutController.php` (`create` ~L70, `store` ~L116–132).
- `app/Actions/RemoveSeniorPwdDiscount.php`: staff remove the discount when the customer can't show a valid ID at handover.
- Flutter: `mobile/lib/src/screens/checkout_screen.dart` (`_discountCardSection`, `_useCard`, `_cardType`, `_cardNumber`), `CheckoutQuote` in `mobile/lib/src/models/models.dart`, `placeOrder` in `mobile/lib/src/core/api_client.dart`.
- Copy the shape of saved delivery addresses for the CRUD parts: `Api/V1/DeliveryAddressController.php`, `DeliveryAddressPolicy.php`, `DeliveryAddressResource.php`, `tests/Feature/DeliveryAddressTest.php`.

## Rules

- A saved ID is a convenience only. The discount never depends on saving, and staff still check the physical ID at handover.
- Saving is opt-in: a "Save this ID for next time" checkbox, unticked by default.
- Up to **3** saved IDs per account (a child may buy for both parents). At the limit, the checkbox is hidden and checkout still works.
- A customer only ever sees and uses their own saved IDs. Another user's ID is a 404.
- The order keeps its own copy of the card. Deleting or changing a saved ID never changes past orders.
- Encrypt both the ID number and the name (PWD status is sensitive personal information under the Data Privacy Act). API and pages only ever show the masked number (`SeniorPwdDiscount::mask`).

## Step 1 — table and model

`php artisan make:model DiscountCard -m --no-interaction` (add a factory for tests).

Table `discount_cards`:

| Column | Type |
|---|---|
| `id` | ULID primary (`HasUlids`, like other models) |
| `user_id` | `foreignUlid`, `constrained()->cascadeOnDelete()` |
| `type` | string(10): `senior` or `pwd` |
| `id_number` | text, `encrypted` cast, `$hidden` |
| `name` | text, `encrypted` cast |
| `last4` | string(4), for display without decrypting |
| timestamps | |

Index `user_id`. No unique index on the number (it's encrypted, and the same ID on two accounts is legitimate).

- `DiscountCard`: `user()` BelongsTo; a `toCard(): array` returning the same shape as `SeniorPwdDiscount::cardFrom()`.
- `User::discountCards(): HasMany`.
- `DiscountCardPolicy`: `delete` only by the owner.

## Step 2 — checkout uses or saves an ID (API and web)

Request fields added to both checkout `store` validations:

- `discount_card_id`: nullable string. When present, load it with `$request->user()->discountCards()->findOrFail($id)` (scoping by user gives 404 for anyone else's) and use `$card->toCard()` as the discount card. Ignore any `discount_card.*` fields sent with it.
- `save_discount_card`: nullable boolean. Only meaningful with typed `discount_card.*` fields.

Order of precedence: `discount_card_id` → typed `discount_card` → no discount. Pass the result to `CheckoutCart` exactly as today.

After `CheckoutCart::handle` returns successfully, if `save_discount_card` is true and a typed card was used, save it unless:
- the user already has 3, or
- they already saved the same type and number. Compare by decrypting their ≤3 rows in PHP; this also keeps an idempotent checkout replay from saving twice.

Put the save in a small action, `app/Actions/SaveDiscountCard.php`, called by both controllers.

Checkout quote:
- `GET /api/v1/checkout` adds `discount_cards: [{id, type, name, id_number_masked}]`.
- The web `create` passes `$discountCards` to the view.

## Step 3 — manage saved IDs

- **API**: `GET /api/v1/discount-cards` and `DELETE /api/v1/discount-cards/{discountCard}`, in the existing `auth, clerk.verified, account:buyer,seller` group in `routes/api.php`. Use `DiscountCardResource` (masked number, never the full one).
- **Web**: a "Saved discount IDs" section on `resources/views/profile/edit.blade.php` listing each (type, name, `•••• 1234`) with a Delete button. Route `DELETE /profile/discount-cards/{discountCard}` → `profile.discount-cards.destroy`.

There's no separate "add" form. Saving happens at checkout.

## Step 4 — staff removing the discount also drops the saved ID

In `RemoveSeniorPwdDiscount`, after the discount is removed, delete the customer's saved ID matching the order's card (same type + number, decrypt-compare their ≤3 rows). Keep the existing audit log free of the number.

## Step 5 — screens

**Web checkout** (`resources/views/marketplace/checkout/create.blade.php`):
- When the customer has saved IDs, show them as choices (type · name · `•••• 1234`) plus "Use a different ID".
- Picking a saved one sends `discount_card_id` and hides the typed fields.
- The typed fields get the "Save this ID for next time" checkbox, hidden at 3 saved.

**Flutter** (`checkout_screen.dart`):
- Same behaviour inside `_discountCardSection`. `CheckoutQuote` parses `discount_cards`.
- `placeOrder` sends `discount_card_id` or the typed card plus `save_discount_card`.
- On a 404/422 for a saved card (e.g. deleted on the web), drop back to the typed fields with a short message.
- Account screen: a "Saved discount IDs" list with delete, calling the new API.
- The app needs a new build; keep old builds working, since every new field is optional.

## Tests

Use the `jmmart-feature-tests` and `jmmart-money-and-orders` skills. Add a `tests/Feature/DiscountCardTest.php` (PHPUnit, SQLite) covering:

1. Checkout with typed card + `save_discount_card` saves it. The next checkout with `discount_card_id` gives the same discount and order copy.
2. Without the checkbox, nothing is saved.
3. A 4th save is skipped and the checkout still succeeds. Re-saving the same card doesn't duplicate.
4. User B using or deleting user A's saved ID gets 404, and no order is created.
5. Deleting a saved ID leaves the earlier order's `discount_card_*` unchanged.
6. The API and quote never expose the full number.
7. `RemoveSeniorPwdDiscount` deletes the matching saved ID.

Also add Flutter widget tests in `mobile/test/checkout_screen_test.dart`:
- the picker shows only when saved IDs exist;
- picking one sends only its id;
- the checkbox sends `save_discount_card`;
- a failed saved ID falls back to typed fields.

Run:

```bash
php artisan test --compact tests/Feature/DiscountCardTest.php tests/Feature/SeniorPwdDiscountTest.php tests/Feature/CheckoutTest.php tests/Feature/CheckoutApiTest.php
vendor/bin/pint --dirty --format agent
cd mobile && dart format lib test && flutter analyze && flutter test test/checkout_screen_test.dart
```

## Guardrails

- Never run `migrate` or tinker against the local `.env` database: it is the live Neon DB. Tests use SQLite. Staging (`jmmart-pharmacy-staging`, deploys from `new-direction`) runs migrations on start.
- Stage only your own files when committing; `reports/` and `research_notes/` belong to someone else.
- No new dependencies.

## Before production (owner's job, not code)

Add the saved-ID purpose and retention to the privacy notice, and get consent wording reviewed.

## Open question (do not change in this plan)

Seller Center order pages show the full ID number to staff (`resources/views/seller/orders/show.blade.php:98`, `_modal.blade.php:298`). Masking it is tempting. But BIR rules on SC/PWD sales may require the full ID number on the invoice or sales record, so confirm with the accountant first.

## Done when

- A customer can save, reuse and delete an ID on web and app, and nobody else can see or use it.
- Checkout works the same with no saved IDs.
- Past orders never change.
- All listed tests pass; Pint, `flutter analyze` and `flutter test` are clean.
