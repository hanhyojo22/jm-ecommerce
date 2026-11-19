# Capturing, OCR-ing, Verifying, Storing and Reusing Senior Citizen / PWD IDs

Research notes for JMMart (Laravel + Flutter marketplace, Philippines), 18 November 2026.

**How to read this.** Every bullet is tagged:

- **[Fact]** — verified in a primary document (law, regulator, official docs) or directly in this repository.
- **[Vendor claim]** — published by a vendor about its own product, accuracy or price. Directionally useful, not independently verified.
- **[Inference]** — my analysis or recommendation from the cited facts.
- **[Assumption]** — a planning number I chose so the arithmetic can be shown. Replace with real values.

**Baseline in this repository (repo evidence, checked 18 Nov 2026).** Laravel 13.33, PHP 8.4 reported by Laravel Boost (the brief says 8.5 — confirm with `php -v` before using 8.5-only syntax), PostgreSQL, queue connection `database` (`config/queue.php`), local + S3 disks configured (`config/filesystems.php`), `league/flysystem-aws-s3-v3` installed, no Horizon, Pennant, Sentry or Flare. Orders already store `discount_amount`, `discount_card_type`, an `encrypted` cast `discount_card_number`, and `discount_card_name` (`app/Models/Order.php`, `database/migrations/2026_11_17_210442_add_senior_pwd_discount_to_orders.php`). The number is in `$hidden` and masked in the API resource, but the seller order page prints it in full for handover (`resources/views/seller/orders/show.blade.php:98`). `RemoveSeniorPwdDiscount` only works while the order is unpaid COD and not yet delivered; for prepaid orders it tells staff to cancel instead (`app/Actions/RemoveSeniorPwdDiscount.php:32-38`). Checkout already fingerprints the discount card into `client_request_hash`, so idempotency covers card data (`app/Actions/CheckoutCart.php:347-364`). The Flutter app uses Riverpod 3, go_router, Dio, Clerk auth and Firebase Messaging, and has no camera, OCR or image packages yet (`mobile/pubspec.yaml`).

---

## What is the current baseline, and what does it imply for this project?

### Takeaway
JMMart already has the legally important part: a per-order snapshot of the full ID number, encrypted, shown only to the fulfilling branch, plus a staff action that removes the discount when the physical ID fails. A reusable "verified ID" is therefore an **optimisation and a prepaid-risk control**, not a new compliance requirement — and the highest-value version is much smaller than an eKYC programme.

### Cited Findings
- **[Fact]** The discount is medicines-only: `SeniorPwdDiscount::appliesTo()` returns true only when the product has a `generic_name` (`app/Support/SeniorPwdDiscount.php:19-22`). Volume is therefore bounded by pharmacy orders, not total marketplace orders.
- **[Fact]** VAT-registered computation removes 12% VAT then takes 20% of the net; otherwise 20% of the line (`app/Support/SeniorPwdDiscount.php:28-40`). The 20% + VAT-exemption structure matches RA 9994/RA 10754 practice as implemented here.
- **[Fact]** The order keeps its own snapshot. Changing a customer-level vault later must not rewrite `orders.discount_card_*` or `discount_amount`.
- **[Fact]** `RemoveSeniorPwdDiscount` rejects prepaid and delivered orders: "This order is already paid, so it cannot be charged more. Cancel it instead if the ID is not valid" (`app/Actions/RemoveSeniorPwdDiscount.php:32-34`).
- **[Fact]** The seller page already displays name + full number to staff, and the removal is written to `AuditLog` without the card number (`app/Actions/RemoveSeniorPwdDiscount.php:59-68`).
- **[Fact]** Mobile checkout collects type, number and name client-side and posts them as `discountCard` (`mobile/lib/src/screens/checkout_screen.dart:230-270`); the web checkout does the same through Blade (`app/Http/Controllers/Marketplace/CheckoutController.php:116-132`).
- **[Fact]** BIR's digest of RR 11-2015 requires a business giving senior discounts to keep "a separate and accurate record of sales, which shall include the name of the Senior Citizen, Identification Document, gross sales/receipts, sales discount granted, dates of transactions and invoice numbers for every sale transaction" — [BIR digest RR 11-2015](https://bir-cdn.bir.gov.ph/BIR/pdf/Digest%20RR%2011-2015.pdf).
- **[Fact]** The Data Privacy Act treats "age" and "health" as sensitive personal information, and government-issued identifiers "peculiar to an individual" as sensitive too — [RA 10173 §3(l)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** Sensitive personal information may only be processed on a §13 basis: specific prior consent, an existing law/regulation that provides protection, medical treatment, legal claims, and similar — [RA 10173 §13](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).

### Inferences
- A senior ID reveals age; a PWD ID reveals disability. Both are **sensitive personal information** under RA 10173, not ordinary account data. That raises the bar for a reusable vault: specific consent, a Privacy Impact Assessment, access logging, and a deletion path.
- The order record has a clear legal basis (tax compliance). The **account vault does not** — it is a convenience feature, so consent must be explicit and separable from checkout. Do not make saving the ID a condition of getting the discount.
- Because COD already has a physical check and a charge-full-price remedy, the incremental fraud value of a verified vault is concentrated in **prepaid** orders, where the current remedy is cancellation/refund. That is where review effort should go.
- At pharmacy-only volumes, a full eKYC contract is almost certainly disproportionate.

### Gaps
- No source found stating how long BIR requires discounted-sale records to be kept. Confirm with the company accountant before writing a retention policy; do not guess "10 years".
- No source found on whether JMMart's contracts with branches make the branch or the platform the "establishment" for BIR record-keeping. This affects who must retain the record.

---

## What end-to-end architecture options exist, and how do they compare?

### Takeaway
Six options exist, but only two are credible for this MVP: **save-on-account with manual entry** and **OCR-assisted entry with server-side checks and a small human review queue**. PhilSys eVerify cannot verify SC/PWD entitlement, and full eKYC buys identity assurance JMMart does not need for a 20% medicine discount.

### Cited Findings
- **[Fact]** PhilSys eVerify has three service tiers: Tier 0 QR check (free, browser-based), Tier 1 basic authentication (verified/not verified + registered full name), Tier 2 eKYC (verified demographics + registration portrait) — [Didit, "PhilSys verification", 6 Oct 2026](https://didit.me/blog/philsys-verification/), summarising PSA's eVerify FAQ.
- **[Fact]** Tier 0 QR check returns name, suffix, sex, date and place of birth, PhilSys Card Number, issuance date and best fingerprint; it verifies the QR's signature and the data in it, and the verifier compares that with the card in hand — [Didit](https://didit.me/blog/philsys-verification/) citing [PSA PhilSys Check](https://verify.philsys.gov.ph/).
- **[Fact]** The PhilSys Number (PSN) "never" may be stored by agencies or private firms; a relying party may retain only data its agreement expressly authorises, and should store the **backend token** issued after a Tier 1/2 authentication — [Didit](https://didit.me/blog/philsys-verification/) citing PSA eVerify FAQ.
- **[Fact]** Private companies can be relying parties, but the business itself must contract with PSA; technology providers cannot be the relying party on their own — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** Onboarding has a regulatory phase (Letter of Intent + Business Process Map, typically 7–10 working days), a technical phase (temporary API key valid 30 days, security assessment), a contract (1 or 3 years), and a Vulnerability Assessment and Penetration Test before go-live; end to end "several weeks to a few months" — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** Published service level: 99.5% monthly availability, 1,000 authentication transactions/minute per system, responses typically 1–5 seconds — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** Online authentication pricing was **not confirmed** in the sources reviewed as of 5 October 2026; the QR check is free — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** 90,290,024 people were registered and verified by 31 October 2025, about 80% of the population — [Didit](https://didit.me/blog/philsys-verification/), citing PSA's 1 Dec 2025 release.
- **[Fact]** The National Commission of Senior Citizens now issues a **Digital National Senior Citizens ID** inside the eGovPH app, generated automatically for verified users aged 60+ — [NCSC, "Digital NSCID"](https://www.ncsc.gov.ph/ncsid). The page documents no API and no merchant verification endpoint.
- **[Fact]** The PWD ID is LGU-issued; no national public verification API for PWD status was found (see Gaps).
- **[Vendor claim]** A generic OCR/ID vendor markets document + liveness + face match as "full KYC" at $0.33 per check, NFC at $0.15, AML screening at $0.20 — [Didit pricing](https://didit.me/pricing/), as quoted in its PhilSys article.

### Option comparison

| Option | What it actually proves | Build effort | Marginal cost | Latency | Fraud resistance | Privacy exposure | Fit for JMMart |
|---|---|---|---|---|---|---|---|
| **0. Status quo**: manual entry + physical check at handover | Customer typed a number; staff later sees a card | 0 (built) | 0 | Instant | Medium for COD; weak for prepaid | Full number on every order | Baseline |
| **1. Save-on-account, no OCR** | Same as 0, but number/name are reused | 5–8 dev-days | ~0 | Instant | Medium; adds device/account linkage and velocity signals | Credential vault (new) | **Strong** |
| **2. On-device OCR + user confirmation** | The photo contains text matching what the user confirms | 8–12 dev-days | ~0 API | 1–3 s on device | Low–medium: reads fakes perfectly | Images on device; optional upload | **Strong, phase 2** |
| **3. OCR + server checks + human review** | Text is plausible, not duplicated, and a human saw the image | +10–15 dev-days | Review labour (₱8–12/case) | Minutes–1 day | Medium–high | Images + reviewer access | **Later, selective** |
| **4. Full eKYC vendor** (document + liveness + face match) | A live person holds a document that matches a face | 20–30 dev-days + contract | $0.15–0.33/check | 5–30 s | High for identity, not for entitlement | Highest: biometrics, third party | **Disproportionate now** |
| **5. PhilSys eVerify** | Name + age (Tier 1/2), or QR contents (Tier 0) | 20+ dev-days + PSA onboarding + VAPT | Fee unconfirmed; QR free | 1–5 s | Good for identity/age; **none for PWD/OSCA status** | High; strict storage limits | **Not a solution for this discount** |
| **6. Risk-based hybrid** (1 + 2 + selective 3) | Layered signals; humans only on anomalies | ~25–35 dev-days total | OCR ~₱0–1/scan + review | Instant for most | Medium–high, proportionate | Controlled | **Recommended** |

### Inferences
- **[Inference]** The comparison collapses to three questions: does the check happen before or after the goods leave, who sees the ID, and what does the platform pay per claim. Only options 1–3 answer all three in JMMart's favour.
- **[Inference]** Option 6 is not a fourth technology; it is a selection policy over options 1–3. Build the cheap layers first and let measured fraud decide whether the expensive one is ever needed.
- **[Inference]** Any option that requires the customer to install or trust another app (eGovPH, a vendor SDK) adds a failure mode for the demographic that struggles most. Prefer the camera already in JMMart's own app.
- **[Inference]** PhilSys proves *who someone is* and *that they are 60+*. It does not prove the person holds a valid OSCA senior ID, nor that a PWD ID is genuine or belongs to them. PWD status is not in PhilSys at all.
- **[Inference]** The Digital NSCID in eGovPH is a display credential. Without a documented merchant verification API, the only integration is "customer shows phone, staff looks at it" — the same manual check JMMart already does, with a screenshot-forgery risk.
- **[Inference]** Even if JMMart onboarded to eVerify, the result would be a boolean "this name is a real 60+ person", which is weaker than the branch's physical card check for the actual entitlement, at the cost of a VAPT and a PSA contract.
- **[Inference]** Full eKYC would verify the *customer's identity*, but the discount is claimed for whoever presents the card at handover. If a daughter orders for her mother, face match on the daughter proves nothing about the mother's entitlement. This is the central mismatch.

### Gaps
- No public, documented API to verify an OSCA senior ID number or an LGU PWD ID number. The only leads are a low-confidence, AI-assisted law-firm commentary claiming an NCSC "Verify Here" portal with RRN/PKN fields — [Respicio, 16 Mar 2026](https://www.respicio.ph/commentaries/how-to-verify-a-senior-citizen-id-number-in-the-philippines) — which the page itself disclaims as potentially inaccurate. Treat as a research lead, not a fact.
- No source found on whether NCSC or NCDA plan a merchant verification API.
- No public accuracy benchmark for OCR on Philippine SC/PWD cards.

---

## Where should OCR run, and how should capture work?

### Takeaway
Run OCR **on the device** with ML Kit for the MVP: zero marginal cost, no image leaves the phone unless the customer chooses to save, and it works on the low-end Android phones this audience uses. Add a server-side OCR fallback only if field accuracy on real cards proves inadequate — and never make OCR a gate.

### Cited Findings
- **[Fact]** ML Kit text recognition ships in two forms: unbundled via Google Play Services (~260 KB size increase per script architecture, model downloaded on demand) or bundled (~4 MB per script per architecture, available immediately) — [Google ML Kit, Text Recognition v2 Android](https://developers.google.com/ml-kit/vision/text-recognition/v2/android).
- **[Fact]** ML Kit text recognition requires Android API 23+ — [Google ML Kit](https://developers.google.com/ml-kit/vision/text-recognition/v2/android). The Flutter wrapper still documents `minSdkVersion 21`, so the effective floor is 23 — [pub.dev google_mlkit_text_recognition 1.0.3](https://pub.dev/packages/google_mlkit_text_recognition).
- **[Fact]** Accuracy guidance: each character should be at least 16×16 px; there is generally no benefit above 24×24 px; poor focus requires recapture — [Google ML Kit](https://developers.google.com/ml-kit/vision/text-recognition/v2/android).
- **[Fact]** ML Kit Document Scanner provides its own viewfinder and preview UI, edge detection, cropping, gallery import, page limits and JPEG/PDF output; it adds ~300 KB download, requires API 21+, and requires **1.7 GB total device RAM** or it throws `UNSUPPORTED` — [Google ML Kit, Document Scanner Android](https://developers.google.com/ml-kit/vision/doc-scanner/android).
- **[Fact]** Google Cloud Vision Text Detection is $1.50 per 1,000 units after the first 1,000 free units per month, up to 5,000,000 units — [Google Cloud Vision pricing](https://cloud.google.com/vision/pricing).
- **[Fact]** AWS Textract `DetectDocumentText` is $0.0015 per page for the first 1,000,000 pages/month; `Analyze ID` is $0.025 per page for the first 100,000 pages/month — [AWS Textract pricing](https://aws.amazon.com/textract/pricing/).
- **[Fact]** AWS describes `Analyze ID` as understanding "U.S. passports, driver's licenses, and other IDs" — [AWS Textract pricing](https://aws.amazon.com/textract/pricing/). No Philippine SC/PWD support is documented.
- **[Vendor claim]** Most eKYC vendors bill per attempt, including failed attempts, and retries plus manual review are the hidden costs that dominate the headline rate — [Verihubs, "eKYC Pricing Philippines", 30 Oct 2026](https://verihubs.com/ph/blog/ekyc-pricing-philippines).

### Trade-off table

| | On-device ML Kit | Laravel queue + cloud OCR | Self-hosted (Tesseract/PaddleOCR) |
|---|---|---|---|
| Marginal cost | **$0** | ~$0.0015–0.0015/scan (Vision/Textract) | Server CPU only |
| Latency | 0.5–3 s | 2–10 s + queue wait | 1–5 s per core, worse on shared CPU |
| Offline | Works if model bundled/downloaded | No | Works if same network |
| Privacy | Image stays on phone | Image leaves PH unless region pinned | Image stays in VPC |
| Accuracy on LGU cards | Generic text model; layout-agnostic parsing needed | Same model class, easier to iterate | Noticeably worse on photos/glare without tuning |
| Maintenance | Flutter plugin + Android API floor | API keys, retries, cost caps, PH data transfer | Model tuning, OCR ops, fonts |
| App size | +0.26 MB (unbundled) to ~4 MB/script/arch (bundled) | 0 | 0 |
| Best use | MVP, assisted entry | Batch re-checks, no-app web checkout | Regulated data-residency cases |

### Capture UX that actually works for this audience
- **[Inference]** Use ML Kit Document Scanner (or an equivalent auto-capture view) rather than a raw camera preview: it gives edge detection, perspective correction and cropping for free, and users do not have to frame perfectly.
- **[Inference]** Before OCR, check blur and glare client-side: reject frames with low Laplacian variance or a large clipped-highlight region, and say "Hold steady — too blurry" rather than silently failing. Google's own guidance is that poor focus requires recapture ([ML Kit](https://developers.google.com/ml-kit/vision/text-recognition/v2/android)).
- **[Inference]** Downscale to ~1280 px on the long edge and JPEG quality ~80 before any upload. A 300 KB image is enough for a card; a 4 MB original adds cost and breach impact with no accuracy gain.
- **[Inference]** Always offer "Type it instead" on the same screen, not behind a menu. OCR failure must never block checkout.
- **[Inference]** Show the parsed fields for confirmation with the photo beside them; never auto-apply the discount from OCR alone. The user is the cheapest accurate reviewer.
- **[Inference]** For the Blade web checkout, a file input + `capture="environment"` is the pragmatic path; desktop users can upload a photo. Do not build a WebAssembly OCR pipeline for MVP.

### Inferences
- At 1,000 verifications/month, cloud OCR costs about $1.50 (₱87 at the planning rate below). The decision is therefore about **privacy and maintenance**, not price. Keeping images off the server is the stronger default.
- The 1.7 GB RAM requirement of the document scanner is a real exclusion for hand-me-down phones; test on a 2 GB Android Go device before committing. If it fails, fall back to `camera` + manual crop.
- Server-side OCR is still worth having as a **second opinion** on review cases, behind an interface, not in the happy path.

### Gaps
- No published accuracy figures for ML Kit on OSCA/PWD cards, which vary by LGU in layout, font, lamination and wear.
- No Philippine-specific benchmark dataset was found.

---

## What data model should back a reusable verified ID?

### Takeaway
One table, `discount_credentials`, one active row per (user, type), encrypted number plus a blind-index hash plus last-4, a status machine, and a nullable link from orders. Keep the order snapshot authoritative; the vault is a convenience cache.

### Cited Findings
- **[Fact]** Laravel's `encrypted` cast and `Crypt` use AES-256-CBC with a MAC, so ciphertext cannot be tampered with undetected — [Laravel 13.x Encryption](https://laravel.com/docs/13.x/encryption).
- **[Fact]** Laravel supports graceful key rotation through `APP_PREVIOUS_KEYS`, decrypting with old keys while encrypting with the new one — [Laravel 13.x Encryption](https://laravel.com/docs/13.x/encryption).
- **[Fact]** CipherSweet provides field-level encryption plus **blind indexes** for exact-match search, with `whereBlind()` and an `EncryptedUniqueRule`; partial search is not supported — [spatie/laravel-ciphersweet](https://github.com/spatie/laravel-ciphersweet).
- **[Fact]** CipherSweet key rotation is a re-encrypt command over the model, and the command is restartable — [spatie/laravel-ciphersweet](https://github.com/spatie/laravel-ciphersweet).
- **[Fact]** Existing orders already hold the card snapshot and an encrypted number (`app/Models/Order.php:49-51`), so a new table is additive, not a migration of history.

### Proposed schema (`discount_credentials`)

| Column | Type | Notes |
|---|---|---|
| `id` | ULID | Public identifier; never expose sequential IDs |
| `user_id` | FK users | Indexed; cascade on user delete |
| `type` | string(10) | `senior` \| `pwd`; matches `SeniorPwdDiscount::CARD_TYPES` |
| `id_number` | text | Laravel `encrypted` cast; never in `$hidden`-less serialization |
| `id_number_hash` | char(64) | HMAC-SHA256 blind index; unique index for exact duplicate detection |
| `id_number_last4` | char(4) | Display and support; safe to show |
| `name_on_id` | text | Encrypted; may be a different person (dependent) |
| `name_normalized` | string(160) | Lowercased, collapsed whitespace, accents folded; for fuzzy duplicate scoring only |
| `issuer` | string(120), null | OSCA/LGU name, optional |
| `expires_at` | date, null | Most PH cards print no expiry; null means "no printed expiry" |
| `revalidate_at` | date | Default +12 months; drives a "please re-confirm" prompt |
| `status` | string(20) | `pending`, `verified`, `rejected`, `expired`, `revoked` |
| `verification_method` | string(30) | `manual_entry`, `ocr_confirmed`, `document_review`, `staff_handover`, `registry` |
| `confidence` | decimal(4,3), null | OCR/parse confidence, not a fraud score |
| `reviewed_by` | FK users, null | Admin reviewer |
| `reviewed_at` | timestamp, null | |
| `review_notes` | text, null | Reason codes only; no PII |
| `last_used_at` | timestamp, null | Velocity checks |
| `use_count` | unsigned int | Default 0 |
| `superseded_by_id` | FK self, null | Versioning on name change / re-issue |
| `created_at`, `updated_at` | timestamps | |

Indexes and constraints:
- `unique(user_id, type) where status in ('pending','verified')` — PostgreSQL partial unique index. One active ID per type per account.
- `unique(id_number_hash) where status = 'verified'` — blocks the same verified ID on two accounts. Do **not** apply to `pending`, or abandoned carts and typos create false collisions.
- `index(user_id, status)`, `index(last_used_at)`.

### Relationships and reuse rules
- `User hasMany DiscountCredential`; `Order belongsTo DiscountCredential` **nullable**, `nullOnDelete`. The order keeps its own snapshot columns; the FK is for analytics and review, never for rendering historical totals.
- Prefill only when `status = verified` and (`expires_at` is null or in the future) and `revalidate_at` has not passed.
- If `revalidate_at` passes, still prefill but show "Please confirm this ID is still current" and set status back to `pending` after the order if the customer does not re-confirm.
- **Never** mutate an order's snapshot when a credential is edited, revoked or deleted. Historical accuracy is a hard rule (`app/Actions/CheckoutCart.php:198-200`).

### Expiry, name change, revocation
- **[Inference]** Most OSCA/PWD cards have no printed expiry. Model that as `expires_at = null` and use `revalidate_at` as an operational freshness check, not a legal expiry.
- **[Inference]** Name change (marriage, correction): create a new row, set `superseded_by_id` on the old one, and keep the old row for order history. Do not overwrite the name on past orders.
- **[Inference]** Revocation reasons: customer request, staff report of invalid ID, duplicate confirmed, data breach suspicion. Revocation sets status and `revoked_at`; it does not delete the row until the retention job runs.
- **[Inference]** A revoked credential must stop prefilling immediately. The customer sees "This saved ID can no longer be used — enter it again or contact support", not a generic error.

### Duplicate detection across accounts
- **[Inference]** Exact hash match is the only reliable automatic signal. On match: flag both credentials `pending`, create a review case, and do not auto-reject. Families, caregivers and typo collisions all produce false positives.
- **[Inference]** Fuzzy name matching should rank review cases, not decide them. Normalise case/whitespace/ñ, then use token-set ratio or Jaro-Winkler; a high score with a different hash is "possible household", not fraud.
- **[Inference]** Add non-PII signals to the case: same phone, same delivery address, same device fingerprint, account age, order count. These are stronger than name similarity and cheaper than a vendor.

### Inferences
- **[Inference]** Resist a `discount_verifications` event table at MVP. `discount_credentials` plus `AuditLog` answers the questions actually asked ("is this ID verified?", "who looked at it?"). Add events when there is a dashboard that needs them.
- **[Inference]** The hash must come from one normalisation function used by checkout, import and duplicate checks. Two normalisations is a silent duplicate-detection bug that surfaces months later.
- **[Inference]** Because orders keep their own snapshot, the vault can be deleted without touching tax records. That separation is what makes a customer-facing delete button safe to ship.
- **[Inference]** The `superseded_by_id` chain is cheaper than auditing overwrites: it preserves what was true when, and it gives support a way to explain a mismatch to a customer.

### Gaps
- No source defines a canonical OSCA/PWD ID number format. Do not validate with a strict regex; accept 4–40 printable characters and normalise whitespace/hyphens only.
- No source on whether a caregiver may lawfully hold and reuse another person's SC/PWD ID in an online account. This is a product-policy and legal question; default to "the account holder must be the ID holder" until advised otherwise.

---

## What is the minimum PII we must store, and what breaks if we store less?

### Takeaway
You cannot go hash-only. BIR record-keeping and the branch's handover check both require the **full number to be recoverable**, so the minimum viable set is: encrypted full number + blind-index hash + last-4 + name. Hash-only would break the two things the discount depends on.

### Cited Findings
- **[Fact]** BIR's RR 11-2015 digest requires the seller's record to include the senior citizen's name and "Identification Document", plus gross sales, discount, dates and invoice numbers — [BIR digest](https://bir-cdn.bir.gov.ph/BIR/pdf/Digest%20RR%2011-2015.pdf).
- **[Fact]** The seller order screen currently prints `discount_card_name — discount_card_number` so staff can compare it with the physical card (`resources/views/seller/orders/show.blade.php:98`).
- **[Fact]** RA 10173 requires personal data to be "adequate and not excessive", retained only as long as necessary, and kept identifiable no longer than necessary — [RA 10173 §11(d)-(f)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** Data subjects have the right to suspend, withdraw, block, remove or destroy their personal information when it is no longer necessary or unlawfully held — [RA 10173 §16(e)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** An `encrypted` cast prevents reading ciphertext without `APP_KEY`, but the key lives in the environment, not in a KMS; a database dump plus `.env` exposure defeats it — [Laravel Encryption](https://laravel.com/docs/13.x/encryption).
- **[Fact]** Blind indexes allow exact-match lookups on encrypted values without decrypting the column — [spatie/laravel-ciphersweet](https://github.com/spatie/laravel-ciphersweet).

### What each storage choice breaks

| Approach | Prefill works | Branch can check at handover | BIR record reproducible | Duplicate detection | Verdict |
|---|---|---|---|---|---|
| Hash only | No (cannot show/enter the number) | No | No | Yes | **Fails** |
| Last-4 + hash | No | No (last 4 insufficient) | No | Yes | Fails |
| Encrypted full number | Yes | Yes | Yes | Needs separate hash | **Required** |
| Encrypted full number + blind index | Yes | Yes | Yes | Yes | **Recommended** |
| Tokenization service | Yes, if it returns the number to authorised callers | Yes, via token exchange | Yes, if token can be resolved | Depends | Overkill for MVP |

### Inferences
- **Store the full number encrypted.** It is the only option that satisfies the branch check and BIR simultaneously. The privacy work goes into access control, retention and audit — not into deleting the number.
- **Add a blind index for duplicate detection**, not a deterministic hash of the plain number. A plain SHA-256 of a 12-digit ID is brute-forceable in minutes; HMAC with a secret pepper is not. CipherSweet's blind index does this correctly ([spatie/laravel-ciphersweet](https://github.com/spatie/laravel-ciphersweet)).
- **Never log the number.** Keep it out of `AuditLog` (already done in `RemoveSeniorPwdDiscount`), out of exception trackers, out of query logs, and out of Flutter analytics.
- **Encryption at rest is not enough on its own.** For a small team, the practical controls are: `APP_KEY` in the platform secret store (not the repo, not the DB backup), a separate `CIPHERSWEET_KEY`, restricted DB access, encrypted backups, and a documented rotation drill using `APP_PREVIOUS_KEYS` / `ciphersweet:encrypt`.
- **Tokenization is not worth building.** There is no PH ID tokenization vendor, and a home-grown token service adds a second system to secure. Revisit only if JMMart stores IDs for a purpose beyond discounts.

### Gaps
- No source specifies a BIR retention period for these records; confirm with the accountant.
- No source confirms whether NPC would accept "delete the vault but retain the order snapshot under legal obligation" as satisfying §16(e). Document the legal-obligation basis in the privacy notice and PIA.

---

## How should checkout integrate with a saved ID?

### Takeaway
Resolve the credential **server-side before pricing**, pass the same `{type, id_number, name}` array into the existing checkout action, and let the existing fingerprint and snapshot logic do the rest. The client sends an ID, never the authoritative number.

### Cited Findings
- **[Fact]** `CheckoutCart::handle()` accepts `?array $discountCard` and writes it straight into the order snapshot (`app/Actions/CheckoutCart.php:37, 198-200`).
- **[Fact]** The idempotency fingerprint already includes `discount_card` as `[type, id_number, name]`, so a replay with a different card is rejected (`app/Actions/CheckoutCart.php:347-364`, `331-337`).
- **[Fact]** The action already rejects a discount when no line is a medicine (`app/Actions/CheckoutCart.php:177-179`).
- **[Fact]** Validation rules live in `SeniorPwdDiscount::rules()` and the card extraction in `SeniorPwdDiscount::cardFrom()` (line 61), and both controllers call them — `app/Http/Controllers/Marketplace/CheckoutController.php:116,132` and `app/Http/Controllers/Api/V1/CheckoutController.php:155,172`. A saved-credential resolver drops into that single seam (`app/Support/SeniorPwdDiscount.php:47-61`).
- **[Fact]** The API masks the number in `OrderResource` but the seller view shows it (`app/Http/Resources/Api/V1/OrderResource.php:35`; seller Blade line 98).

### Recommended flow
1. **Quote** (`POST /checkout/quote`): client sends `discount_credential_id` (preferred) or raw `discount_card`. Server loads the credential with `where('user_id', $request->user()->id)`; ignores any client-supplied type/number when an ID is present. Returns the same `senior_pwd_discount` preview as today.
2. **Place order**: server re-resolves the credential, checks `status = verified`, `expires_at`/`revalidate_at`, and that the hash of any typed number matches. Builds `$card = ['type' => ..., 'id_number' => ..., 'name' => ...]` and passes it to `CheckoutCart`.
3. **Fallback**: if the credential is missing/revoked, return a 422 with a machine-readable code (`discount_credential.invalid`) so Flutter/Blade can show "Enter your ID again" without losing the cart.
4. **Save after success**: if the customer ticked "Save this ID", upsert the credential **after** the order commits, status `pending` (or `verified` if the branch later confirms at handover). Do not create credentials from abandoned carts.
5. **Snapshot**: never re-read the vault to render an old order. The order's `discount_card_*` columns remain the source of truth.

### Anti-replay, abuse and rate limits
- **[Inference]** Keep the existing `client_request_id` + hash; it already covers the card. Add the credential ID into the fingerprint only if the resolved card can differ for the same credential (e.g., name edited) — otherwise the card array is sufficient.
- **[Inference]** Rate-limit credential creation (5/day/user), OCR attempts (20/day/user), checkout (10/min/user), and review decisions (100/hour/reviewer). Use Laravel named rate limiters, not ad-hoc `sleep`.
- **[Inference]** Require re-authentication (password or Clerk session refresh) before revealing the full number in account settings. The seller view can show it after an explicit "Reveal ID" click that writes an audit row.
- **[Inference]** Bind a credential to the account, not the device. Device binding is a useful *signal* (store `last_device_hash`) but a terrible hard rule for elderly users who change phones or share a family tablet.

### Prepaid vs COD
- **[Inference]** For COD, the physical check remains the control; the vault mainly saves typing.
- **[Inference]** For prepaid, add one extra step: if the credential is `pending` (never confirmed at handover), show a warning at checkout that the discount may be removed and the order cancelled if the ID fails. Optionally require `verified` status for prepaid discounts above a threshold (e.g., ₱1,000).
- **[Inference]** Do not let the client choose "verified". Status transitions happen only through server actions (review, handover confirmation, revocation).

### Inferences
- **[Inference]** The resolver belongs in `SeniorPwdDiscount` or a small `ResolveDiscountCard` action, not in each controller. Two controllers resolving credentials independently will drift within a sprint.
- **[Inference]** Returning 422 with a machine-readable code, not a generic validation bag, is what lets Flutter preserve the cart and show a targeted fix.
- **[Inference]** A saved ID must never change the price without the customer seeing the new total before placing the order. Quote and place must resolve identically, or the preview becomes a lie.
- **[Inference]** Keep the raw-number path working indefinitely. The vault is an accelerator; making it mandatory turns a convenience feature into an outage.

### Gaps
- No product decision yet on whether a saved ID may be used for a different recipient name/address. Recommend requiring the order recipient to match the ID holder, or flagging mismatches for review.

---

## What human review workflow is proportionate?

### Takeaway
Review **exceptions, not submissions**. A 2–5% escalation rate with a one-business-day SLA and reason codes is enough; reviewing every ID destroys the economics and delays elderly customers.

### Cited Findings
- **[Vendor claim]** Manual review is one of the hidden costs that can exceed the headline per-check price, and high false-rejection rates quietly raise cost per customer — [Verihubs](https://verihubs.com/ph/blog/ekyc-pricing-philippines).
- **[Fact]** NPC Circular 2023-06 requires a Privacy Impact Assessment, a DPO, access controls for authorised personnel, and secure authentication — [NPC, 1 Apr 2024](https://privacy.gov.ph/npc-issues-circulars-to-strengthen-personal-data-protection-in-ph/).
- **[Fact]** The platform already has an `AuditLog` model with actor, action, entity, old/new values and IP (`app/Models/AuditLog.php`), and `RemoveSeniorPwdDiscount` uses it.

### Escalation rules (start here, tune monthly)
- OCR confidence below threshold, or number/name not extracted.
- Name on ID does not match the account name **and** the customer did not declare a dependent.
- Same `id_number_hash` already `verified` on another account.
- 3+ distinct ID numbers on one account in 30 days.
- ID image looks like a screenshot of a screenshot, a printout, or has inconsistent fonts/alignment around the number.
- Prepaid order above a threshold with a `pending` credential.

### Reviewer UI and process
- **[Inference]** Queue sorted by risk score, not FIFO. Show: masked number, last-4, name, type, issuer, OCR confidence, image thumbnails, duplicate/velocity signals, order value, customer tenure.
- **[Inference]** Decisions: `approve`, `reject_reason` (illegible, mismatch, expired, suspected_fake, duplicate, other), `request_reupload`. Reason codes are the feedback loop into rules.
- **[Inference]** No download button. Images served through short-lived signed URLs, watermarked with reviewer ID + timestamp, `Content-Disposition: inline`, and a `no-store` cache header.
- **[Inference]** Two-person rule for bans/revocations; single reviewer for approvals.
- **[Inference]** SLA: first response 1 business day; auto-expire `pending` after 7 days with a friendly "we couldn't verify this ID, you can still check out by entering it manually" message.
- **[Inference]** Appeals: one re-upload per rejection, then a second reviewer; log both decisions.

### Keeping volume low
- **[Inference]** Only review when the order value at risk exceeds the review cost (e.g., discount > ₱150). A ₱40 discount does not justify ₱10 of review.
- **[Inference]** Trust established customers: after 3 successful handover confirmations, auto-verify and stop reviewing.
- **[Inference]** Use branch confirmations as free labels: "ID shown and accepted" is a positive label; "discount removed" is a negative label. This is the cheapest accuracy data JMMart will get.
- **[Inference]** Batch review into two windows per day rather than real-time, except for prepaid high-value orders.

### Inferences
- **[Inference]** Review capacity, not the fraud rate, sets the ceiling in month one. One reviewer at 3 minutes per case clears roughly 100 cases a day; tune the escalation rules to stay under that.
- **[Inference]** Every reason code should map to an action the customer can take. Codes that only classify are trivia.
- **[Inference]** The review queue is the only place a human should ever see an image. Keep it that way — no email attachments, no chat screenshots, no shared drive.
- **[Inference]** Rejections create more support load than approvals. Budget for one appeal per rejection and write the macro copy before launch.

### Gaps
- No local benchmark for reviewer throughput on Philippine IDs. Measure it in the pilot; 2–4 minutes/case is a reasonable starting assumption.

---

## What does build vs buy cost at 100 / 1,000 / 10,000 verifications?

### Takeaway
At MVP volumes the **variable** cost of OCR is trivial (under ₱1,000/month even at 10,000 scans); the real costs are engineering time and human review. Vendor eKYC at $0.33/check is 50–250× the OCR cost and buys assurance this discount does not need.

### Cited Findings
- **[Fact]** Google Vision text detection: first 1,000 units/month free, then $1.50/1,000 — [Google Cloud Vision pricing](https://cloud.google.com/vision/pricing).
- **[Fact]** AWS Textract `DetectDocumentText`: $0.0015/page; `Analyze ID`: $0.025/page for first 100k — [AWS Textract pricing](https://aws.amazon.com/textract/pricing/).
- **[Vendor claim]** Didit full KYC $0.33/check; NFC $0.15; AML $0.20 — [Didit pricing](https://didit.me/pricing/).
- **[Vendor claim]** eKYC vendors commonly bill per attempt, including failures, and retries add cost — [Verihubs](https://verihubs.com/ph/blog/ekyc-pricing-philippines).
- **[Fact]** PhilSys online authentication fees were unconfirmed as of Oct 2026 — [Didit](https://didit.me/blog/philsys-verification/).

### Assumptions used below
- **[Assumption]** ₱58 = US$1 for planning. Verify with finance; the conclusions do not change at ₱55–62.
- **[Assumption]** 3% of verifications go to human review; ₱10 fully loaded cost per review (≈3 minutes at a ₱30k/month reviewer cost).
- **[Assumption]** One scan per verification; retries ignored (multiply by 1.2 for a realistic buffer).

### Monthly variable cost

| Volume | On-device ML Kit + 3% review | Cloud Vision + 3% review | Textract DetectDocumentText + review | Vendor eKYC @ $0.33 |
|---|---|---|---|---|
| **100** | ₱30 | ₱30 (free tier) | ₱39 ($0.15 + review) | **₱1,914** ($33) |
| **1,000** | ₱300 | ₱300 (free tier) | ₱387 ($1.50 + review) | **₱19,140** ($330) |
| **10,000** | ₱3,000 | ₱3,783 ($13.50 + review) | ₱3,870 ($15 + review) | **₱191,400** ($3,300) |

Notes: Textract `Analyze ID` would be $2.50 / $25 / $250 in API fees alone, but is documented for US-style IDs and is not a fit for LGU cards ([AWS](https://aws.amazon.com/textract/pricing/)). Cloud OCR storage at 10,000 × 300 KB ≈ 3 GB, i.e. single-digit dollars per month on S3 Standard; delete after review anyway.

### One-off engineering (estimates, not quotes)

| Phase | Scope | Dev-days | Cost @ ₱3,000/day |
|---|---|---|---|
| P1 | Vault table, model, policy, save/remove, prefill in API + Blade + Flutter, audit | 5–8 | ₱15k–24k |
| P2 | On-device OCR, capture UX, parse + confirm, feature flag | 8–12 | ₱24k–36k |
| P3 | Review queue, duplicate/velocity rules, signed-URL viewer, appeals | 10–15 | ₱30k–45k |
| P4 | Vendor eKYC or PhilSys onboarding | 20–30 + contract + VAPT | ₱60k–90k + vendor/audit fees |

### Break-even reasoning
- **[Inference]** A ₱1,000 medicine basket yields a ₱200 discount. If 1% of 10,000 monthly claims are invalid and unrecoverable, leakage is ₱20,000/month — ten times the entire OCR + review budget. Controls, not OCR accuracy, dominate the business case.
- **[Inference]** Vendor eKYC at 10,000 checks costs ₱191k/month. To justify that, it would need to prevent ~955 invalid ₱200 discounts per month (9.5% of claims). That is implausible for a pharmacy discount with a physical handover check.
- **[Inference]** At 100–1,000/month, even the P3 review build costs more than a year of OCR fees. Build P1/P2, measure, then decide on P3.

### Inferences
- **[Inference]** Engineering time dominates every scenario: 5 dev-days (~₱15k) buys about 500 months of Google Vision at 1,000 scans/month. Optimising the per-scan price is not the lever.
- **[Inference]** The buy decision is really "buy review-labour avoidance" or "buy fraud-loss reduction", never "buy OCR". Frame the business case that way or the comparison is meaningless.
- **[Inference]** At 10,000/month the gap between on-device (₱3,000) and cloud OCR (₱3,783) is smaller than one day of engineering. Choose on privacy and maintenance, not on the number.
- **[Inference]** The 3% review assumption is the cost model's weakest input. Measure it in the pilot before using this table for a budget decision.

### Gaps
- No public PhilSys eVerify price; the vendor summary explicitly says it is unconfirmed. Get written pricing during onboarding.
- No quote from a Philippine eKYC vendor for SC/PWD-specific OCR. Generic KYC pricing may not cover LGU card layouts.

---

## Flutter specifics: packages, permissions, offline, app size, privacy

### Takeaway
The stack is `camera` (or the ML Kit document scanner) + `google_mlkit_text_recognition` + `image_picker`, with no vendor SDK. Keep images in memory or app-private storage, delete cache files immediately, and never send them to analytics.

### Cited Findings
- **[Fact]** Current versions (Nov 2026): `camera` 0.12.2 (Android SDK 24+), `image_picker` 1.2.4, `image_cropper` 13.0.0 (Flutter ≥3.47), `google_mlkit_text_recognition` 1.0.3 (Flutter ≥3.44), `google_mlkit_document_scanner` 0.7.2 — [pub.dev camera](https://pub.dev/packages/camera), [image_picker](https://pub.dev/packages/image_picker), [image_cropper](https://pub.dev/packages/image_cropper), [text recognition](https://pub.dev/packages/google_mlkit_text_recognition), [document scanner](https://pub.dev/packages/google_mlkit_document_scanner).
- **[Fact]** `camera` requires Android SDK 24+, exposes `CameraAccessDenied` / `CameraAccessDeniedWithoutPrompt` / `CameraAccessRestricted` errors, and no longer handles lifecycle changes itself — the app must dispose/recreate the controller in `didChangeAppLifecycleState` — [pub.dev camera](https://pub.dev/packages/camera).
- **[Fact]** ML Kit text recognition supports Latin script on both platforms; on Android, non-Latin scripts need extra Gradle dependencies; on iOS only Latin is currently supported by the wrapper — [pub.dev text recognition](https://pub.dev/packages/google_mlkit_text_recognition).
- **[Fact]** The document scanner needs 1.7 GB total RAM and API 21+, and adds ~300 KB — [Google ML Kit](https://developers.google.com/ml-kit/vision/doc-scanner/android).
- **[Vendor claim]** EkycID publishes a Flutter SDK for OCR/face recognition on government-issued IDs, including Philippine documents — [EKYCSolutions/ekyc-id-flutter](https://github.com/EKYCSolutions/ekyc-id-flutter). No pricing, accuracy or SC/PWD-specific support is published on that page.
- **[Fact]** The app currently has no camera/image/OCR dependency (`mobile/pubspec.yaml`), so this is net-new surface area and needs an Android permission review.

### Recommended packages and shape
- `google_mlkit_text_recognition` for OCR; wrap it behind a `DiscountIdScanner` interface so it can be swapped or faked in tests.
- `camera` if you build a custom viewfinder; `google_mlkit_document_scanner` if you want auto edge detection/cropping with less UI work (but test the RAM floor).
- `image_picker` for gallery fallback; `image_cropper` only if users need manual crop (check the Flutter 3.47 constraint against `flutter --version`).
- `flutter_image_compress` (or `image` package) to downscale before upload; avoid shipping full-resolution photos.

### Android permissions and manifest
- `<uses-permission android:name="android.permission.CAMERA" />` and `<uses-feature android:name="android.hardware.camera" android:required="false" />` so tablets without a camera can still install.
- Do **not** request `READ_MEDIA_IMAGES` unless you use gallery import; the photo picker does not need it on modern Android.
- Set `android:allowBackup="false"` or exclude the credential/image cache from backup.
- If you add `FLAG_SECURE` on the review screen, screenshots are blocked — good for IDs, but tell users why.

### Offline and app size
- **[Inference]** Bundle the Latin OCR model if first-use download is a problem for low-connectivity users; accept ~4 MB per architecture. Otherwise use the Play Services model and show "Preparing scanner…" on first use.
- **[Inference]** OCR can run fully offline once the model is present; the *verification* step still needs the server. Design the flow so OCR + confirmation work offline and the order submission queues/retries.
- **[Inference]** App size impact is modest (single-digit MB); do not let it block the feature.

### Keeping images out of logs and caches
- **[Inference]** `camera.takePicture()` writes to the app cache; move the file to app-private storage, upload, then delete both copies in a `finally` block.
- **[Inference]** Disable Firebase Analytics/Crashlytics parameter logging for this screen; never log file paths, base64, OCR text or the ID number.
- **[Inference]** Clear the image cache on logout and on backgrounding the review screen; use `path_provider` + a dedicated `discount_id/` directory you can purge.
- **[Inference]** Do not save to the gallery (`gal` is already a dependency for other features — do not reuse it here).

### Inferences
- **[Inference]** Make the scanner a self-contained screen with its own state so an OCR failure cannot leave checkout half-valid.
- **[Inference]** Test on the cheapest phone the audience actually uses, not an emulator. The 1.7 GB RAM floor is the deciding constraint for the document scanner.
- **[Inference]** Since the app already uses Riverpod, expose the result as a plain value object (`DiscountCardDraft`) and keep ML Kit out of widget code; that is also what makes the scanner fakeable in tests.
- **[Inference]** Reuse the existing `gal` dependency nowhere near this feature. Gallery writes make an ID photo indistinguishable from a user photo and survive uninstall.

### Gaps
- No published benchmark of EkycID accuracy on OSCA/PWD cards; treat the vendor claim as unverified.
- No Flutter SDK found from PSA/NCSC/NCDA for PhilSys or NSCID verification.
- The app's exact Flutter version was not checked; confirm before adding `image_cropper 13.0.0`.

---

## Laravel specifics: queues, storage, encryption, audit, policies, flags, tests

### Takeaway
Use the existing database queue for OCR/review side-effects at MVP volume; move to Redis + Horizon only when job latency matters. Keep images on a private S3 disk with short signed URLs, store only the object key, and put every read of the full number behind a policy + audit row.

### Cited Findings
- **[Fact]** The default queue connection is `database` (`config/queue.php:16`), and the app already configures local + S3 disks (`config/filesystems.php`).
- **[Fact]** Laravel's encrypter uses AES-256-CBC with a MAC and supports `APP_PREVIOUS_KEYS` rotation — [Laravel Encryption](https://laravel.com/docs/13.x/encryption).
- **[Fact]** CipherSweet integrates with Eloquent, provides `whereBlind()` and `EncryptedUniqueRule`, and supports key rotation — [spatie/laravel-ciphersweet](https://github.com/spatie/laravel-ciphersweet).
- **[Fact]** The project already has `AuditLog` and policies under `app/Policies` (e.g. `OrderPolicy`, `SellerOrderPolicy`), so the pattern exists.
- **[Fact]** `Order` hides `discount_card_number` from array/JSON serialization (`app/Models/Order.php:46`).

### Implementation notes
- **Job**: `AnalyseDiscountId` implements `ShouldQueue`, `ShouldBeUnique` (by credential ID), `$tries = 3`, `backoff = [10, 60, 300]`, `$timeout = 30`. On final failure, set status `pending` and notify the customer — never leave a spinner.
- **Storage**: `Storage::disk('s3')->put($key, $contents, ['visibility' => 'private'])`; store only `$key` in `discount_id_images`. Serve via `Storage::temporaryUrl($key, now()->addMinutes(5))`. Add an S3 lifecycle rule to expire the prefix after 30 days (or 7 after review).
- **Signed URL leakage**: bind the URL to the reviewer's session by generating it server-side per request; do not cache it in the SPA. Set `Cache-Control: no-store`.
- **Encryption**: `encrypted` cast on `id_number` and `name_on_id`; `id_number_hash` via CipherSweet blind index; never put plaintext in `AuditLog` or `review_notes`.
- **Policies**: `DiscountCredentialPolicy` with `view`, `update`, `delete`, `review`, `revealNumber`. `review` and `revealNumber` require an admin role and 2FA; `delete` only the owner (soft revoke, then purge).
- **Validation**: a `StoreDiscountCredentialRequest` with `type` in `senior,pwd`, `id_number` string 4–40, `name` 2–120, `consent` accepted. Normalise before hashing (trim, collapse spaces, uppercase? — decide and keep consistent; do not change normalisation after launch without rehashing).
- **Feature flags**: `laravel/pennant` is not installed. Either add it (dependency approval required) or use a `config('marketplace.discount_vault.enabled')` + per-user allowlist for the pilot. Do not build a bespoke flag system.
- **Testing**:
  - Bind `OcrClient` to a `FakeOcrClient` returning fixture JSON per ID type; no network in tests.
  - `Storage::fake('s3')`, `Queue::fake()`, `Http::fake()` for vendor calls.
  - Fixtures: 3–5 synthetic ID images per type, plus red-team variants (number edited, photo swapped, printout, screenshot-of-screenshot, glare, fold).
  - Assert: another user's credential ID returns 404/403; revoked credential cannot prefill; order snapshot unchanged after credential edit; duplicate hash creates a review case, not a hard block; `RemoveSeniorPwdDiscount` still works.
- **Logging hygiene**: add `discount_card`, `id_number`, `name_on_id` to a redaction list in the logging config; disable Telescope in production or filter it; configure Sentry/Flare `before_send` to drop these keys. Do not `report($e)` with the request payload attached.

### Inferences
- **[Inference]** Do not add Horizon or Redis for this feature. The database queue already exists, and on-device OCR means the server has almost no async work at MVP.
- **[Inference]** One action per state change (`VerifyDiscountCredential`, `RevokeDiscountCredential`) keeps the status machine auditable. Status writes scattered across controllers will not be.
- **[Inference]** The `encrypted` cast is the cheap part; the policy, audit and redaction wiring is the expensive part. Build that once, properly, before the first real ID is saved.
- **[Inference]** Prefer the existing `AuditLog` over a new audit mechanism even though its schema is generic. A second audit trail is a second place to forget to write.

### Gaps
- No queue worker supervisor is documented in the repo; confirm how `queue:work` runs locally and on the target host before relying on async OCR.
- Horizon requires Redis; adding it is an infrastructure decision, not just a Composer package.

---

## Security controls: what to put in place before saving a single ID

### Takeaway
The controls that matter are access, retention and audit — not exotic cryptography. Treat the ID vault as a sensitive-data system with a named owner, a deletion path, and evidence of every read.

### Cited Findings
- **[Fact]** RA 10173 §20 requires reasonable organisational, physical and technical measures, including network safeguards, a security policy, vulnerability identification, and monitoring — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** Breach notification is required when sensitive personal information (or information enabling identity fraud) is reasonably believed acquired and likely to cause real risk of serious harm; notify the NPC and affected data subjects — [RA 10173 §20(f)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** Negligent access to sensitive personal information carries imprisonment and fines of ₱500,000–₱4,000,000 — [RA 10173 §26(b)](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** NPC Circular 2023-06 (effective 30 Mar 2024, repealing 16-01) requires a DPO, registration of processing systems, a Privacy Impact Assessment, a Privacy Management Program, personnel training, access procedures, secure authentication, remote deletion on mobile devices, and a Business Continuity Plan — [NPC](https://privacy.gov.ph/npc-issues-circulars-to-strengthen-personal-data-protection-in-ph/).

### Control checklist
- **Access**: admin review role only; 2FA on admin accounts; no bulk export; masked by default; "reveal" writes an audit row with actor, IP, user agent, credential ID, timestamp.
- **Least privilege**: sellers see only the snapshot for their own `seller_order`; they never query the vault. This is already the pattern in `SellerOrderPolicy`.
- **Rate limits**: credential create 5/day/user; OCR 20/day/user; reveal 10/hour/admin; checkout 10/min/user. Log 429s.
- **One ID per account per type**: partial unique index; a second submission goes to review, not silent overwrite.
- **Family/shared use**: decide explicitly. Default: the account holder must be the ID holder; add a "buying for a senior" flow that does **not** save the ID to the account.
- **Duplicate accounts**: hash + phone + address + device signals; flag, don't auto-ban. A false ban on an elderly customer is a support and reputational cost.
- **Retention**: images 30 days (7 after review); OCR raw text not stored; credentials until user deletes or 24 months inactive; orders per finance/BIR policy. A scheduled `PurgeDiscountIdImages` job and `RevokeStaleCredentials` job.
- **Deletion**: customer-initiated delete revokes immediately and purges ciphertext + images; order snapshots remain under the legal-obligation basis. Explain this in the privacy notice.
- **Breach response**: named incident owner, 24-hour internal triage, NPC notification per §20(f), customer notice template, key rotation drill (`APP_PREVIOUS_KEYS`, `ciphersweet:encrypt`).
- **Monitoring**: alert on spikes in credential creation, duplicate-hash hits, reveal actions, OCR failures, and 5xx on the review queue. Do not put ID numbers in alert payloads.
- **Vendor contracts**: if using OCR/eKYC, a Data Processing Agreement under RA 10173 §14, region pinning where possible, and a no-training clause on customer images.

### Inferences
- The biggest realistic breach vector is not the database; it is a screenshot in a support chat, a photo in a shared drive, or a log aggregator. Control those first.
- Encrypting the column while emailing the number to a branch would be theatre. Prohibit the number in email/chat; use the seller order page.

### Gaps
- NPC's operational breach-notification deadline (commonly cited as 72 hours) was not verified in the sources read; confirm against the current NPC circular before writing the incident runbook.
- No source on whether a small marketplace must register its processing system with NPC; confirm thresholds with the DPO.

---

## Testing and rollout: how to know it works before trusting it

### Takeaway
Build a small, consented specimen set, measure **field accuracy** and **false accepts** separately, and canary the feature on staff + one branch. OCR quality is not the same as fraud control; measure both.

### Cited Findings
- **[Fact]** ML Kit's own guidance says poor focus reduces accuracy and users should be asked to recapture — [Google ML Kit](https://developers.google.com/ml-kit/vision/text-recognition/v2/android).
- **[Vendor claim]** Vendors recommend piloting with real Philippine IDs because "real pass rates on Philippine IDs, especially older or worn cards, decide which quote is actually lower" — [Verihubs](https://verihubs.com/ph/blog/ekyc-pricing-philippines).
- **[Fact]** The repo's test suite already covers the discount (`tests/Feature/SeniorPwdDiscountTest.php`), so there is a place to add regression cases.

### Dataset
- **[Inference]** 30–50 specimens per type: OSCA IDs from 3–4 LGUs, PWD IDs from 2–3 LGUs, plus PhilID/ePhilID and Digital NSCID screenshots. Include worn, laminated, folded, low-light and glare variants.
- **[Inference]** Written consent from staff/volunteers who provide specimens; redact address and birth date where not needed; store specimens outside the repo (encrypted, access-controlled) and never in CI artifacts.
- **[Inference]** Synthetic red-team set: number changed by one digit, font mismatch, photo swap, printed copy, screenshot of a screenshot, cropped hologram, expired-looking card. These test the *reviewer*, not the OCR.

### Metrics
- Field-level exact match: ID number, name; character error rate per field.
- Auto-fill acceptance rate (user keeps OCR values without editing).
- Time from opening scanner to confirmed ID; checkout completion rate.
- Review rate, approval rate, rejection reasons, time-to-decision.
- **False accept rate**: reviewer approves a red-team sample (target near zero).
- **False reject rate**: legitimate ID sent to review or rejected (target <5%).
- Discount removal rate at handover (existing metric) — should fall if the vault is working.

### Rollout
1. **[Inference]** Internal dogfood with staff accounts; flag on for 2 weeks; collect OCR accuracy on 50+ real IDs.
2. **[Inference]** One branch, COD only; compare checkout completion and discount-removal rate against control.
3. **[Inference]** Expand to prepaid with a value threshold; add review queue.
4. **[Inference]** Only then consider a vendor or PhilSys pilot.

### Fallback
- **[Inference]** Manual entry stays available forever. If OCR, review or the vault is down, checkout proceeds with typed data and the existing handover check.
- **[Inference]** A feature flag kill switch must disable prefill and OCR without a deploy.

### Inferences
- **[Inference]** Write the pass/fail criterion before collecting the dataset, or the team will rationalise whatever accuracy it gets.
- **[Inference]** Discount-removal rate is the north-star metric: it is an existing, cheap, real-world label for whether verification changed anything.
- **[Inference]** Run the pilot on COD first. Prepaid failures cost real money and arrive in a smaller, slower sample.
- **[Inference]** Synthetic red-team cards are reusable across future features; build them once and keep them out of the repo.

### Gaps
- No existing labeled dataset; building one is the long pole, not the code.
- No agreed false-accept tolerance; set one before the pilot (e.g., <0.5% on red-team samples) or the pilot has no pass/fail criterion.

---

## Accessibility and UX for elderly and PWD customers

### Takeaway
The feature must be **optional, large, slow-friendly and skippable**. Every screen needs a visible "type it instead" path, and no state should trap the user behind a camera or a pending review.

### Cited Findings
- **[Fact]** WCAG 2.2 requires text to be resizable up to 200% without loss of content, and target sizes of at least 24×24 CSS px (with exceptions) — [W3C WCAG 2.2 Quick Reference](https://www.w3.org/WAI/WCAG22/quickref/).
- **[Fact]** Android/Material guidance uses 48dp minimum touch targets; Flutter's `MaterialTapTargetSize` defaults are designed around this.
- **[Inference]** The current checkout screen renders the discount explanation in `bodySmall` (`mobile/lib/src/screens/checkout_screen.dart:746-749`), which is likely too small for this audience.

### Concrete UX rules
- Body text ≥16sp, inputs ≥18sp, line height ≥1.4; support OS font scaling to 200% without clipping.
- One primary action per screen; "Save this ID for next time" is opt-in and unchecked by default.
- Camera guidance: a large frame overlay, plain-language instructions ("Put the whole card inside the box"), a torch button, and a "Can't scan? Type it" button always visible.
- Status language: "Checking your ID…", "Saved — you can use this next time", "We need to look at this ID. You can still check out." Never "Verification failed" alone.
- Show the parsed number in a large, editable field with the photo beside it; let the user correct it before applying the discount.
- Do not auto-advance or time out; do not use carousels or small "X" dismiss buttons.
- Provide Filipino/Tagalog copy alongside English; test with 5–8 users aged 60+ and at least 2 PWD users.
- On the Blade checkout, the same rules apply: large labels, visible manual fields, no camera-only flow.

### Inferences
- The elderly user is often not the account holder (a child or caregiver orders). Design for "I'm ordering for my mother" explicitly; otherwise the saved-ID model will be used in ways the policy forbids.
- A pending review must not block checkout. The customer should be able to proceed with manual entry and let the branch decide at handover.

### Gaps
- No usability testing has been done with JMMart's actual customers; this is the highest-value cheap research before building.

---

## What is the recommended phased approach, and what should we not build yet?

### Takeaway
Ship the vault first (P1), add OCR as assistance (P2), add review only for exceptions (P3), and defer PhilSys/vendor eKYC until there is evidence of material prepaid fraud. Do **not** build face matching, liveness, a custom OCR model, or a tokenization service.

### Cited Findings (constraints that drive the phasing)
- **[Fact]** BIR requires name + ID document on the discounted-sale record — [BIR digest RR 11-2015](https://bir-cdn.bir.gov.ph/BIR/pdf/Digest%20RR%2011-2015.pdf).
- **[Fact]** SC/PWD data is sensitive personal information under RA 10173, requiring a §13 basis and §20 security — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html).
- **[Fact]** NPC Circular 2023-06 requires a PIA, DPO, access controls and retention discipline — [NPC](https://privacy.gov.ph/npc-issues-circulars-to-strengthen-personal-data-protection-in-ph/).
- **[Fact]** PhilSys eVerify onboarding requires a PSA contract, a Business Process Map, a security assessment and a VAPT, over weeks-to-months, with fees unconfirmed — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** PhilSys Tier 1/2 returns identity/age data, not OSCA or PWD entitlement — [Didit](https://didit.me/blog/philsys-verification/).
- **[Fact]** On-device ML Kit costs nothing per scan and needs no image upload — [Google ML Kit](https://developers.google.com/ml-kit/vision/text-recognition/v2/android).
- **[Fact]** `RemoveSeniorPwdDiscount` cannot charge more on prepaid orders; staff must cancel — `app/Actions/RemoveSeniorPwdDiscount.php:32-34`.

### Recommended phases

**P0 — Decisions and paperwork (1–2 weeks, mostly non-code)**
- Product policy: account holder vs dependent; one ID per type; what "verified" means; prepaid threshold.
- Privacy: PIA, privacy-notice update, consent copy, retention schedule, DPO sign-off.
- Data model review and threat model; decide hash normalisation once.
- **Exit criteria**: signed policy + PIA, agreed retention, no unresolved legal question blocking P1.

**P1 — Vault without OCR (5–8 dev-days)**
- `discount_credentials` table, model, policy, `StoreDiscountCredential` / `RevokeDiscountCredential` actions.
- Save-on-checkout (opt-in), prefill, revoke, delete.
- API + Blade + Flutter prefill; server-side resolution before pricing.
- Audit on reveal/review; feature flag; kill switch.
- Tests: ownership isolation, revoked credential, snapshot immutability, idempotency.
- **Exit criteria**: a customer can save once and reuse; another user cannot read it; staff still see the snapshot.

**P2 — OCR assist (8–12 dev-days)**
- On-device capture + parse + confirm; manual entry always visible.
- Blur/glare checks, downscale, delete cache files.
- Optional server-side re-OCR behind an interface for review cases.
- Accuracy measurement on the consented specimen set.
- **Exit criteria**: OCR auto-fill acceptance ≥70% on the specimen set, no increase in checkout abandonment, zero PII in logs.

**P3 — Exception review (10–15 dev-days)**
- Review queue, signed-URL viewer, reason codes, SLA, appeals.
- Duplicate hash + velocity rules; prepaid threshold.
- Metrics dashboard: review rate, false accept/reject, discount removal rate.
- **Exit criteria**: review rate <5%, decisions within 1 business day, red-team false accepts <0.5%.

**P4 — Only if evidence demands it**
- PhilSys eVerify for identity/age (not entitlement), or a vendor eKYC for high-value prepaid orders.
- Requires a business case showing fraud loss > vendor cost + integration + VAPT.

### What not to build yet
- **Face match / liveness / selfie verification.** It verifies the buyer, not the card holder, and adds biometric data (heightened sensitivity) for a 20% medicine discount.
- **Hologram/UV/security-feature detection.** Phone cameras cannot reliably capture or verify these; a model that guesses will create false confidence.
- **A custom OCR model or self-hosted Tesseract pipeline.** Generic ML Kit is adequate for assisted entry; custom training needs a dataset JMMart does not have.
- **Tokenization service / KMS envelope encryption.** Use Laravel encryption + a separate pepper now; revisit if the vault grows beyond discounts.
- **Automatic approval from OCR confidence.** Confidence measures legibility, not authenticity.
- **Hard device binding.** It locks out elderly users who change phones; use it as a signal only.
- **A public "verify ID" endpoint.** It would be an enumeration oracle for ID numbers.
- **Storing ID images indefinitely.** 30 days maximum, 7 after review.

### Inferences
- The ROI order is P1 → P2 → P3, not the reverse. OCR is a UX feature; the vault is the product feature; review is a risk control.
- The single best fraud signal is free: the branch's handover confirmation. Feed it back into credential status and stop paying for review on customers with a clean history.
- If the team can only do one thing, do P1 with a strict "verified only" prefill and a manual fallback. It delivers most of the convenience with none of the OCR risk.

### Gaps
- No decision yet on who owns the review queue operationally (platform admin vs branch staff). This changes the SLA and the cost model.
- No estimate of JMMart's actual SC/PWD order volume; the 100/1,000/10,000 scenarios are placeholders. Pull 90 days of `discount_amount > 0` orders before committing to P3.

---

## Source confidence summary

Strongest evidence: RA 10173 (primary law), NPC Circular 2023-06 (regulator), BIR RR 11-2015 digest (primary tax guidance), Google/AWS pricing pages (primary vendor pricing), Laravel/ML Kit/pub.dev docs (primary technical docs), and this repository's code.

Medium: Didit's PhilSys explainer (a vendor, but dated Oct 2026 and explicit about what it could not confirm; it cites PSA pages and the BSP memo). Treat its onboarding steps and SLA as accurate-enough for planning, and its fee statement as "unconfirmed".

Weak / do not rely on: the AI-assisted law-firm commentary on SC ID verification; it self-disclaims accuracy and asserts an NCSC portal that could not be independently confirmed.

Unanswered and material: PhilSys eVerify pricing; BIR retention period; whether a caregiver may hold another person's ID; actual JMMart order volume; OCR accuracy on LGU cards.
