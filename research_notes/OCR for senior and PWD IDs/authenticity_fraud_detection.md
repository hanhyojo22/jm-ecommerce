# ID Document Authenticity, Biometric Matching, and eKYC Fraud Detection (Philippines focus, Nov 2026)

> **Evidence labels used throughout:** `[VERIFIED]` = primary source or regulator/standards body; `[VENDOR CLAIM]` = vendor marketing or documentation, not independently tested; `[SECONDARY]` = trade press or aggregator reporting on a primary source; `[INFERENCE]` = my reasoning from cited facts; `[OLDER]` = pre-2023, retained because no newer equivalent exists.
> **Scope:** a Philippine multi-vendor pharmacy marketplace (Laravel + Flutter) that grants the 20% senior-citizen/PWD medicine discount, currently by typed ID details plus physical inspection at handover.

---

## 1. Document authenticity algorithms: what they actually check, what hardware they need, and what survives a plain phone photo

### Takeaway
Document authentication is a stack of independent tests — template/layout, data cross-checks, machine-readable elements (MRZ/barcode/QR/chip), optical security features, and image forensics — and each test only answers its own narrow question. From a single ordinary phone photo you can realistically get OCR, field/format consistency, barcode/QR decoding (including PhilSys's cryptographic QR), and some recapture/manipulation signals; you cannot verify UV/IR inks, optically variable ink behaviour, hologram dynamics, microprint, or chip data, and for LGU-issued senior/PWD IDs there is usually no reference template to compare against at all.

### Cited Findings

**The standard taxonomy of checks**

- Regula's SDK stack enumerates the industry-standard set: document type identification, OCR, MRZ reading, barcode reading, RFID/NFC reading, graphic-field processing, face matching, "automatic authenticity control", and hardware-assisted authentication under white/UV/IR light — [Regula Document Reader SDK](https://regulaforensics.com/products/document-reader-sdk) `[VENDOR CLAIM]`.
- A 2025 peer-reviewed survey of document forgery detection splits methods into *active* (extrinsic fingerprints, signatures) and *passive* (printer-source attribution, noise analysis, machine-learning forensics), and notes that cheap scanners/printers have made alteration easier — [Document forgery detection: a comprehensive review, Int. J. Data Science and Analytics, Feb 2025](https://link.springer.com/article/10.1007/s41060-025-00723-0) `[VERIFIED]`.
- Regula's November 2026 release adds detection of *damaged and cancelled* documents — split cards, punched holes, cut corners, VOID stamps — as a distinct authenticity check, i.e. "is this a valid, un-cancelled credential" is treated separately from "is this a counterfeit" — [Document Reader SDK 9.10 release notes, 11 Nov 2026](https://docs.regulaforensics.com/develop/doc-reader-sdk/release-notes/9-10) `[VENDOR CLAIM]`.

**Template / layout matching**

- Template matching requires a per-document-type reference: field positions, fonts, expected graphics, photo-embedding type. Regula's database held **17,064 document types from 254 countries** as of 11 Nov 2026, with templates updated to encode OVI, MLI, contact chip, holograms, extended MRZ/OCR, photo embedding and pattern checks — [Regula 9.10 release notes](https://docs.regulaforensics.com/develop/doc-reader-sdk/release-notes/9-10) `[VENDOR CLAIM]`.
- Jumio describes its per-document checks as "security features, known forgeries, layout, font, image manipulation" for Philippine passports, driver's licences and ID cards — [Jumio Philippines coverage page](https://www.jumio.com/global-coverage/philippines) `[VENDOR CLAIM]`.
- Entrust (formerly Onfido) describes its document report as data-integrity + visual-authenticity + "police record" checks, built from "10,000+ micro-models" trained on "tens of millions" of verifications across 2,500+ documents in 195 countries — [Entrust document verification](https://onfido.com/solutions/document-verification) `[VENDOR CLAIM]`.

**Holograms / OVDs / OVI**

- Holograms are view- and illumination-dependent by design; automatic verification therefore needs *multiple frames from different angles or light directions*, not one image. A 2017 open-access paper used a portable ring-light module to capture photometric stacks and a CNN to classify genuine vs counterfeit Euro banknote holograms — [Soukup & Huber-Mörk, IPSJ CVA 2017](https://link.springer.com/article/10.1186/s41074-017-0022-7) `[VERIFIED, OLDER]`.
- A 2015 controlled user study of a mobile AR hologram-verification prototype found humans correctly judged holograms in **80%** of cases while the system was correct in **73%**, and the system managed only **50%** on one of the four holograms tested — [Mobile User Interfaces for Efficient Verification of Holograms, IEEE TVCG 2015](https://arbook.icg.tugraz.at/schmalstieg/Schmalstieg_291.pdf) `[VERIFIED, OLDER]`. This is the clearest published evidence that hologram checking is *not* a solved single-photo problem.
- A 2022 method detects the *presence* of a hologram anywhere on a document from smartphone video in ordinary indoor/outdoor light, tested on 85 genuine and fake identity documents — [Hologram Detection for Identity Document Authentication, Springer 2022](https://link.springer.com/chapter/10.1007/978-3-031-09037-0_29) `[VERIFIED]`.
- The public benchmark for this task is MIDV-Holo (ICDAR 2023): **300 video clips of documents with custom holograms plus 400 presentation-attack clips**, with a baseline using pixel-wise chromaticity statistics accumulated across the video — [MIDV-Holo, ICDAR 2023](https://dl.acm.org/doi/10.1007/978-3-031-41682-8_30) `[VERIFIED]`. A 2026 follow-up (MIDV-DynAttack) triples the attack samples and argues prior methods detect *that a hologram exists* but not *that the expected hologram behaves correctly* — [Verification of Dynamic Holographic Behavior in Identity Documents, arXiv 2607.06466](https://arxiv.org/html/2607.06466) `[VERIFIED, preprint]`.

**Image forensics (ELA, JPEG ghosts, noise, recapture)**

- Error Level Analysis re-saves the image at a known JPEG quality and looks for blocks whose error differs from the local norm; JPEG-ghost analysis re-compresses at a sweep of qualities and looks for blocks with a local error minimum at a *foreign* quality, indicating a pasted region — mechanics and thresholds documented in a working forensic tool — [Photo & Video Forensics Methods, Parameters and Limitations, 2026](https://maxintel.org/forensics-methods.html) `[VERIFIED, practitioner documentation]`.
- Both techniques are explicitly weak on "heavily recompressed or resized images, screenshots and social-media downloads", and JPEG ghosts require pasted regions of roughly 200 px aligned to the 8×8 DCT grid — [same](https://maxintel.org/forensics-methods.html) `[VERIFIED]`.
- Recapture (photographing a screen or printout) is treated as a *separate* attack surface from editing, because a recaptured genuine or edited document can read perfectly while the capture path is fake — [Screen Recapture Attacks, CheckFile, Aug 2026](https://www.checkfile.ai/en-US/blog/screen-recapture-attack-detection-document-fraud) `[SECONDARY]`.
- Recapture detection signals are moiré patterns, backlight/glare hotspots, bezel/edge geometry, lighting inconsistency and cross-signal correlation; no single signal is reliable against an attacker who knows what reviewers check — [same](https://www.checkfile.ai/en-US/blog/screen-recapture-attack-detection-document-fraud) `[SECONDARY]`.
- Best published recapture numbers are modest: a CVPR 2024 chromaticity-map transformer reduced average EER on recaptured office documents from **26.82% to 16.78%**, holding AUC 0.8688 at JPEG QF=70 and 0.7554 at 534×503 px — [CMA, CVPR 2024](https://www.computer.org/csdl/proceedings-article/cvpr/2024/530000p577/20hOYpKEb5u) `[VERIFIED]`. In other words, even research-grade screen-recapture detection is far from a clean binary.
- ID R&D ships a dedicated document-liveness product addressing two attack vectors — *screen replay* and *printed copy* — with versioned pipelines (`screen-replay_2025-10`) and a configurable threshold — [IDLive Doc documentation](https://docs.idrnd.net/idlivedoc) `[VENDOR CLAIM]`.

**MRZ, barcode, QR, chip**

- MRZ check digits are the cheapest high-value check where an MRZ exists (ICAO 9303 documents: passports, TD1/TD2/TD3 cards). Regula exposes MRZ reading and cross-checks against VIZ and barcode data as core SDK functions — [Regula Document Reader SDK](https://regulaforensics.com/products/document-reader-sdk) `[VENDOR CLAIM]`. Philippine senior/PWD LGU cards and the PhilID carry **no MRZ**; MRZ is effectively a passport/e-passport check in this market — `[INFERENCE from document descriptions below]`.
- The PhilID/ePhilID QR is not a barcode lookup — it carries a **digital signature** verified with public-key cryptography, offline, at `verify.philsys.gov.ph`; results distinguish "verified", "deactivated card", "QR could not be read", and "connect to the internet" — [PhilSys Check, PSA](https://philsys.gov.ph/philsys-check); [PSA press release, 1 Feb 2024](https://philsys.gov.ph/philsys-authentication-services-strengthen-transaction-security/) `[VERIFIED]`.
- PhilSys Check's stated device floor is a **720p camera with at least 8 MP stills, visual enhancements disabled, well-lit environment** — a useful, concrete minimum for any marketplace capture flow — [PhilSys Check](https://philsys.gov.ph/philsys-check) `[VERIFIED]`.
- The PhilID is polycarbonate with laser-engraved details, guilloche patterns, colour-shifting ink and the signed QR; the ePhilID/paper format has **no physical security features at all** and relies entirely on the QR signature — [Regula, Philippine IDs, 24 Mar 2025](https://regulaforensics.com/blog/philippine-national-id-verification) `[SECONDARY/vendor analysis]`.
- NFC/chip reading applies to the **Philippine ePassport** (2016 series: RFID chip, laser engraving, intaglio/microtext, UV ink, holographic overlay, IPI and LetterScreen++), not to the PhilID — [same](https://regulaforensics.com/blog/philippine-national-id-verification) `[SECONDARY/vendor analysis]`.
- Regula validates NFC chip data **server-side** specifically so that tampering on the user's device cannot forge a chip result — [Regula Document Reader SDK](https://regulaforensics.com/products/document-reader-sdk) `[VENDOR CLAIM]`.

**Philippine LGU-issued IDs: what is actually on the card**

- A Naga City ordinance prescribes a senior ID on "special paper… plastic-laminated", front carrying photo, name, ID number **and barcode**, signature and city seal; back carrying address, birth date, gender, emergency contacts and signatures — [Naga City Ordinance 2018-082](https://www2.naga.gov.ph/wp-content/uploads/2021/06/ord2018-082.pdf) `[VERIFIED]`. This is a *printed, laminated paper card with a barcode* — not a polycarbonate credential.
- Valenzuela City relaunched its PWD and senior IDs in May 2025 with a **holographic seal** plus a centralised online validation portal (VAL ID) for establishments, backed by an ordinance allowing confiscation of fake IDs — [Valenzuela City, 20 May 2025](https://valenzuela.gov.ph/article/valenzuela-unveils-new-design-for-pwd-and-senior-citizen-id-cards-launches-val-id-validation-portal) `[VERIFIED]`.
- There is **no single national template** for the OSCA senior ID: each LGU's OSCA publishes its own requirements, card design and replacement rules; the ID is free on first issuance under RA 9994 and valid nationwide — [Sulat.ph guide, updated 11 Nov 2026](https://sulat.ph/government-documents/how-to/senior-citizen-id-requirements); [Respicio, Jul 2026](https://www.respicio.ph/commentaries/how-to-apply-for-a-senior-citizen-id-in-the-philippines) `[SECONDARY]`.
- Regula added a **barcode parser for the "Philippines Senior Citizen ID Card"** in its 9.2 release (12 Feb 2026) — [Regula 9.2 release notes](https://docs.regulaforensics.com/develop/doc-reader-sdk/release-notes/9-2) `[VENDOR CLAIM]`. That is evidence of *data extraction* support, not evidence of template-based authentication for any specific LGU design.
- Microblink's Philippine coverage list is generic — "eID, Identity Card, Multipurpose ID, MySSS Card, Postal ID, Professional ID, Voter ID, Driver's License, Health Insurance Card, Tax ID, Work Permit, Alien ID, Paper Passport" — with **no senior-citizen or PWD entry** — [Microblink supported documents](https://docs.microblink.com/blinkid/supported-documents) `[VERIFIED vendor documentation]`.

### Inferences
- From one phone photo, the defensible checks are: OCR + field presence/format, cross-field consistency (name vs ID number vs DOB), barcode/QR decode, PhilSys QR signature validation, digital-manipulation forensics (ELA/ghost/noise), and screen-recapture scoring. Everything else needs either multiple frames under controlled light (hologram/OVI), a special camera (UV/IR), a macro optic (microprint), or NFC (ePassport only).
- For a laminated LGU card with no template, "authenticity" collapses into "generic ID-like layout + no obvious digital tampering". A competently printed physical counterfeit of a Naga-style card would pass every phone-only check except a registry lookup — and no registry lookup exists for most LGUs.
- Barcode presence is not authenticity: a barcode that encodes only the printed ID number is trivially reproducible. Only signed payloads (PhilSys QR) or online registry lookups add assurance.
- `[INFERENCE]` Regula's new senior-citizen barcode parser implies the barcode is a real, structured data source worth reading — it may carry the OSCA ID number and possibly DOB — but no public specification exists, so a marketplace cannot rely on it without testing against real cards from its own LGUs.

### Gaps
- No public dataset, benchmark, or vendor accuracy figure for **Philippine LGU senior/PWD ID** authentication. Every accuracy claim in this space would be extrapolation.
- Unclear whether Regula/Microblink/Jumio/Entrust have templates for *any* specific LGU design (e.g. Quezon City QCitizen, Valenzuela VAL ID). Vendor "supported documents" lists are country/type-level, not LGU-level.
- No published specification of what the Philippine senior-citizen barcode encodes, or whether any LGU signs it.

---

## 2. Biometric verification: face match, liveness/PAD, injection and deepfakes, accuracy and bias

### Takeaway
1:1 face matching is the most rigorously benchmarked part of the stack — NIST's ongoing FRTE shows top algorithms at roughly 0.16%–4% false non-match on controlled 1:1 pairs — but NIST does **not** test "selfie vs a laminated printed portrait", which is the actual marketplace task. Passive liveness is good against known physical artefacts (iBeta Level 2 tests report 0% penetration) yet structurally weak against camera-path injection, and the elderly/PWD population most relevant here is the least represented in every published test.

### Cited Findings

**Face recognition accuracy (NIST FRTE)**

- NIST's FRTE 1:1 track has evaluated **1,501 algorithms from 461 developers** cumulatively (2017–2026), with 138 algorithms from 111 developers in 2026 alone — [NIST FRTE 1:1, updated 6 Nov 2026](https://pages.nist.gov/frvt/html/frvt11.html) `[VERIFIED]`.
- NIST reports FNMR at fixed FMR thresholds per dataset. Paravision's Gen 7 submission reported **1:1 VISA-BORDER FNMR 0.0016 (0.16%)**, **BORDER-BORDER 0.0034**, and **BORDER-KIOSK 0.0375 (3.75%)**, with an 80% reduction in 1:1 error over six years — [Paravision, Jul 2025](https://www.paravision.ai/news/paravision-delivers-world-class-performance-in-latest-nist-frte-evaluations-ranking-in-top-5-globally-for-both-face-verification-and-identification) `[VENDOR CLAIM reporting NIST results]`.
- Innovatrics reported 2nd in BORDER:KIOSK, 4th in BORDER:BORDER, 6th in VISA:BORDER and 1st in MUGSHOT:MUGSHOT in the April 2025 FRTE 1:1 update — [Innovatrics, 30 Apr 2025](https://www.innovatrics.com/news/innovatrics-achieves-top-results-in-nist-frte-evaluation) `[VENDOR CLAIM reporting NIST results]`.
- NIST itself cautions that results "do not constitute an endorsement of any particular system" and publishes per-scenario tables rather than one leaderboard — [NIST FRTE 1:1](https://pages.nist.gov/frvt/html/frvt11.html) `[VERIFIED]`.

**Presentation attack detection (PAD)**

- NIST FATE Part 10 (NISTIR 8491, Sept 2023) evaluated **82 passive, software-only PAD algorithms** on conventional 2D imagery across **9 presentation-attack types**, reporting APCER at BPCER=1% and BPCER at APCER=1% separately for impersonation and evasion — [NIST IR 8491](https://nvlpubs.nist.gov/nistpubs/ir/2023/NIST.IR.8491.pdf); [NIST news, 20 Sep 2023](https://www.nist.gov/news-events/news/2023/09/whats-wrong-picture-nist-face-analysis-program-helps-find-answers) `[VERIFIED]`.
- There is **no aggregate PAD leaderboard**: results are per attack type (print, replay, mask, makeup, etc.), and fusing multiple PAD algorithms improves performance — [Paravision analysis of NIST FATE PAD](https://www.paravision.ai/news/understanding-nist-fate-presentation-attack-detection-pad); [Biometric Update, Oct 2023](https://www.biometricupdate.com/202310/biometric-presentation-attack-detection-shows-maturity-in-nist-evaluation) `[SECONDARY]`.
- **The NIST PAD track is currently closed** and NIST is not accepting submissions; no successor PAD report exists as of Nov 2026 — [NIST FATE PAD page](https://pages.nist.gov/frvt/html/frvt_pad.html) `[VERIFIED]`. This is a real evidence gap for 2025–2026 vendor claims.
- iBeta conformance letters show the practical shape of "Level 2": 5 artefact species × 150 attacks per device, **APCER 0%** across 1,500 attacks, using "mid-level" artefacts and **cooperative subjects** who provide high-quality biometric samples, with a 24-hour limit per PAI — e.g. [Shufti Pro Level 2 letter, Apr 2025](https://www.ibeta.com/wp-content/uploads/2025/04/250410-Shufti-Pro-PAD-Level-2-Confirmation-Letter.pdf); [Veriff Level 2 letter, Aug 2024](https://www.ibeta.com/wp-content/uploads/2024/09/240829-Veriff-PAD-Level-2-Confirmation-Letter.pdf) `[VERIFIED test reports]`.
- Compliance with Level 1/2 requires **0% spoof penetration** in the tested set — [Innovatrics iBeta explainer](https://www.innovatrics.com/benchmarks-and-certificates/level-1-ibeta-presentation-attack-detection-conformance/) `[SECONDARY/vendor]`. "0% in a lab with cooperative subjects" and "0% in the field with elderly users and commodity phones" are very different claims.
- The 2023 NIST report includes demographic breakdowns, and trade coverage notes performance varies by demographic group — [Biometric Update](https://www.biometricupdate.com/202310/biometric-presentation-attack-detection-shows-maturity-in-nist-evaluation) `[SECONDARY]`.

**Injection attacks, virtual cameras, deepfakes**

- WEF's 2026 deepfake/KYC report evaluated **17 face-swap tools and 8 camera-injection tools** collected from darknet forums, Telegram and social media (Jul 2024–Apr 2025), classifying injection at app level (intercepting Camera2/AVFoundation APIs), OS level, and hardware level — [WEF, Unmasking Cybercrime, 2026](https://reports.weforum.org/docs/WEF_Unmasking_Cybercrime_Strengthening_Digital_Identity_Verification_against_Deepfakes_2026.pdf) `[VERIFIED]`.
- WEF's recommended controls are camera-path verification (detect virtual cameras, mid-session device swaps, new drivers) and randomized active liveness with dynamic lighting to break real-time face-swap synchronisation — [same](https://reports.weforum.org/docs/WEF_Unmasking_Cybercrime_Strengthening_Digital_Identity_Verification_against_Deepfakes_2026.pdf) `[VERIFIED]`.
- iProov's red team passed a financial app's liveness check using desktop face-swap software, OBS, and an Android virtual-camera app on a **non-rooted** phone; the scenario is published as a MITRE ATLAS case study — [Biometric Update, 17 Dec 2025](https://www.biometricupdate.com/202512/iproov-shows-how-face-swapping-app-can-fool-liveness-detection-on-financial-apps); [SecurityBrief, 18 Dec 2025](https://securitybrief.co.nz/story/mitre-flags-deepfake-kyc-threat-using-face-swap-tools) `[SECONDARY]`.
- iProov reports a **300% increase in face-swap attacks since 2023** on KYC face systems — [Biometric Update](https://www.biometricupdate.com/202512/iproov-shows-how-face-swapping-app-can-fool-liveness-detection-on-financial-apps) `[VENDOR CLAIM]`.
- Regula's own taxonomy of video injection: virtual cameras renamed to look physical, replay of a previously captured genuine video, deepfake overlay on the attacker's own feed, and mixed attacks — [Regula, Video Injection Attacks, Sep 2025](https://regulaforensics.com/blog/video-injection-attacks) `[VENDOR CLAIM/analysis]`.
- Persona analysed **over 7 million selfie verifications** and found that virtual-camera *names* are easily spoofed, while **missing device metadata** is a harder-to-disguise signal — [Persona injection attack report 2026](https://withpersona.com/guides/injection-attack-report-2026) `[VENDOR CLAIM, large sample]`.
- An academic study of deepfakes against six public eKYC/liveness detectors found most produced videos bypassed most detectors, with only one detector separating real from fake by a small margin — [Bypassing Conventional eKYC: How Far Can We Go using Deepfake?, ACM, Dec 2025](https://dl.acm.org/doi/full/10.1145/3777555.3777563) `[VERIFIED, small sample; authors note public detectors only]`.
- **Morphing** is a distinct threat: NIST reports single-image morph detection can reach ~100% at 1% false-detection rate *if trained on the same morphing software*, but degrades **below 40%** on unfamiliar software; differential (two-image) detection is more consistent at 72–90% but needs a second genuine photo — [NIST IR 8584 / FATE MORPH 4B, Aug 2025](https://www.nist.gov/news-events/news/2025/08/nist-guidelines-can-help-organizations-detect-face-photo-morphs-deter) `[VERIFIED]`.

**Demographic and accessibility concerns**

- At a fixed FMR of 0.0003, the **median Bias Gini across FRTE 1:1 submissions is 0.62**, and overall accuracy rank does not predict demographic consistency (Innovatrics v17: 0.55; ROC v21: 0.68; SenseTime v12: 0.73) — [Biometric Update reporting NIST FRTE 1:1, 21 Sep 2026](https://www.biometricupdate.com/202609/nist-frte-11-update-shows-shifting-leaders-persistent-demographic-disparities) `[SECONDARY reporting NIST]`.
- NIST's 2019 demographic study found higher false-positive rates for Asian and African American faces relative to Caucasian faces in 1:1 matching — [NIST, Dec 2019](https://www.nist.gov/news-events/news/2019/12/nist-study-evaluates-effects-race-age-sex-face-recognition-software) `[VERIFIED, OLDER]`.
- A 2025 review of demographic bias in face recognition documents skin-tone and other subgroup effects persisting across systems — [Review of Demographic Bias in Face Recognition, arXiv 2502.02309](https://arxiv.org/html/2502.02309v1) `[VERIFIED, preprint]`.
- **Accessibility is structurally under-tested.** iBeta's own protocol language specifies "cooperative subjects… willing and able to provide any and all biometric samples, including high-quality biometric facial samples" and mid-level artefacts — which excludes exactly the users who struggle: people with tremor, facial palsy, cataracts, heavy glasses, limited neck mobility, or poor lighting at home — [iBeta Level 2 letter](https://www.ibeta.com/wp-content/uploads/2025/04/250410-Shufti-Pro-PAD-Level-2-Confirmation-Letter.pdf) `[VERIFIED]`.
- No public benchmark measures PAD or face-match error rates for **persons with disabilities** (e.g. Down syndrome, facial nerve palsy, blindness/low vision) or for Filipinos aged 60+ using low-end Android front cameras — `[GAP]`.

### Inferences
- Use NIST FRTE numbers as an *upper bound on algorithm quality*, not a forecast: the marketplace task is unconstrained selfie vs a printed portrait that may be 10+ years old, low-resolution, laminated, and photographed under pharmacy lighting. Expect materially higher FNMR than 0.16–3.75%.
- Passive liveness is worth having but must not be the only control. Injection is a *capture-path* problem; the mitigations that actually work (attestation, camera-source verification, metadata, randomised challenge) are SDK-level, not model-level.
- `[INFERENCE]` For a pharmacy discount, the realistic attacker is not a deepfake artist — it is a family member using grandma's ID, or a bought counterfeit card. Deepfake defence is therefore lower priority than identity-to-person binding at the counter and duplicate-account detection.
- `[INFERENCE]` Any flow that requires a selfie will fail a meaningful share of elderly/PWD users for reasons unrelated to fraud. A no-selfie path with in-person verification must exist, or the product will exclude the exact beneficiaries of RA 9994/RA 10754.

### Gaps
- NIST FRTE does not test ID-portrait-to-selfie; no independent benchmark isolates that scenario.
- No published BPCER/FNMR for elderly or disabled users in commercial PAD/face-match systems.
- NIST PAD track is closed; 2025–2026 vendor PAD claims rest on iBeta letters (cooperative subjects, mid-level artefacts) rather than independent government evaluation.

---

## 3. Vendor landscape: what is verified, PH coverage, deployment, Flutter, pricing, independent evidence

### Takeaway
The market splits into three layers: (a) **document capture/OCR SDKs** that run on-device (Regula, Microblink) and can be embedded in Flutter; (b) **full IDV platforms** that add liveness, face match, fraud signals and manual review (Jumio, Entrust/Onfido, Veriff, Persona, Incode, Sumsub, AU10TIX, IDnow, Shufti, Trulioo, Didit); and (c) **authoritative registry checks** (PhilSys eVerify), which no vendor can substitute for because the relying party must contract with PSA directly. None of them publicly claims template-level authentication of LGU-issued senior/PWD cards.

### Cited Findings

**Comparison table** (prices are published list prices, Nov 2026; "quote" = not published)

| Vendor | What is actually checked | PH document coverage (as stated) | Deployment | Flutter | Pricing | Independent evidence |
|---|---|---|---|---|---|---|
| **Regula** | Template/layout, OCR, MRZ, barcode, RFID/NFC, OVI/hologram/UV/IR via hardware, document liveness, face match; 17,064 templates / 254 countries (Nov 2026) — [src](https://docs.regulaforensics.com/develop/doc-reader-sdk/release-notes/9-10) | PH National ID (QR signature), UMID, DL, postal, passport; **senior-citizen barcode parser added Feb 2026** — [src](https://docs.regulaforensics.com/develop/doc-reader-sdk/release-notes/9-2) | On-device mobile SDK, web service, on-prem Windows, hardware readers; mobile licence works offline — [src](https://docs.regulaforensics.com/develop/doc-reader-sdk/overview/licensing) | Yes (DocumentReader-Flutter on GitHub) — [src](https://regulaforensics.com/products/document-reader-sdk) | Quote; mobile/online/software/hardware licence tiers; online licences metered per document journey — [src](https://docs.regulaforensics.com/develop/doc-reader-sdk/overview/transactions) | No public NIST/iBeta accuracy per document type; 30+ years, used by 80 border-control authorities (vendor claim) |
| **Microblink (BlinkID)** | On-device OCR/data extraction, document classification, barcode/MRZ/Viz modules; no cloud needed — [src](https://docs.microblink.com/blinkid) | PH: eID, Identity Card, Multipurpose ID, MySSS, Postal, Professional, Voter, DL, Health Insurance, Tax ID, Work Permit, Alien ID, passport — **no senior/PWD entry** — [src](https://docs.microblink.com/blinkid/supported-documents) | Mobile SDK (Android/iOS), web, React Native, Flutter, Cordova, Capacitor — [src](https://microblink.com/identity/document-scanner-sdk) | Yes, `blinkid_flutter` v8001; Flutter 3.44+, Android minSdk 24, iOS 16+ — [src](https://github.com/microblink/blinkid-flutter) | Quote ("contact us"); licence key per app — [src](https://microblink.com/identity/document-scanner-sdk) | None published for accuracy; claim "1,800+ docs / 200+ countries" (docs) vs "2,500+ / 3,000+" on marketing pages — inconsistent |
| **Jumio** | Security features, known forgeries, layout, font, image manipulation; liveness, face match, AML — [src](https://www.jumio.com/global-coverage/philippines) | PH passports, driver's licences, ID cards — [src](https://www.jumio.com/global-coverage/philippines) | SaaS/API + mobile SDK; quote | Not stated in public docs (assume native SDKs only; verify) | Quote only — [src](https://kyccost.com/provider-pricing-context/) | No public NIST PAD/FRTE submission data found |
| **Entrust (Onfido)** | Data integrity, visual authenticity, "police record" checks; 10,000+ micro-models; facial similarity (photo/video/motion), NFC — [src](https://onfido.com/solutions/document-verification) | PH page names DL, Social Security Card, Voter's ID "and many others"; 2,500+ docs / 195 countries — [src](https://onfido.com/use-cases/kyc/philippines) | SaaS/API + Smart Capture SDK (iOS/Android/web) | Not stated publicly | Quote only — [src](https://kyccost.com/provider-pricing-context/) | ETSI TS 119 461; no PAD level named in reviewed pages — [src](https://authenticate.com/resources/blog/best-identity-verification-software) |
| **Veriff** | Document verification, biometric liveness, face match, risk insights, human review on higher tiers — [src](https://www.veriff.com/pricing) | Global; PH not separately enumerated in reviewed page — check supported-docs tool | SaaS + SDK/web | Not stated publicly | **$0.80 / $1.39 / $1.89** per verification; $49/$99/$209 monthly minimum; retries not billed; AML +$0.64; 2-yr retention +$0.30 — [src](https://www.veriff.com/pricing) | iBeta Level 2 letter (Aug 2024, 300 attacks, 0% IAPAR) — [src](https://www.ibeta.com/wp-content/uploads/2024/09/240829-Veriff-PAD-Level-2-Confirmation-Letter.pdf) |
| **Persona** | Document + selfie + database checks, configurable workflows, manual review; device-metadata injection signals — [src](https://withpersona.com/guides/injection-attack-report-2026) | Global; PH not enumerated | Hosted/embedded flow; native iOS/Android/React Native SDKs — [src](https://docs.withpersona.com/choosing-an-integration-method) | **No native Flutter SDK**; use hosted/embedded web | From **$250/mo** annual, 500 services included, **$1.50** per service beyond — [src](https://kyccost.com/provider-pricing-context/) | 7M+ selfie analysis published (injection) — [src](https://withpersona.com/guides/injection-attack-report-2026) |
| **Incode** | ID capture + OCR, selfie/video liveness, face match, NFC e-passport, device/camera trust signals; Deepsight for device-level deepfake detection — [src](https://developer.incode.com/sdk-reference/flutter-sdk/) | Global; PH not enumerated | Native + **Flutter** hybrid SDK; capture-only and ODR modes native-only — [src](https://developer.incode.com/integrate-by-platform) | **Yes, first-class Flutter SDK** — [src](https://developer.incode.com/sdk-reference/flutter-getting-started) | Quote | Vendor claims 0.01% FMR / 99.9% genuine selfies passed — [src](https://www.incode.com/ai-info) `[VENDOR CLAIM]` |
| **AU10TIX** | Document forensics ("180+ forgery tests"), biometrics, liveness, deepfake detection, serial fraud monitor — [src](https://www.au10tix.com/pricing) | 5,000+ docs / 190+ countries; PH specifics not published | SaaS (Azure), API/SDK | Not stated | **$500/mo minimum** on Basic and Enhanced; AWS Marketplace prepaid **$18,000 per 1,000 requests (~$18/check)** — [src](https://www.au10tix.com/pricing); [src](https://aws.amazon.com/marketplace/pprodview-stz2mqmbpan4s) | iBeta PAD Level 1 & 2 — [src](https://marketplace.microsoft.com/en-us/product/saas/au10tix1662380672540.1234) |
| **IDnow** | AutoIdent (automated doc + biometric) and VideoIdent (live agent); eID/signing in EU — [src](https://didit.me/blog/best-kyc-solutions) | EU-centric; PH not enumerated | SaaS; video + auto | Not stated | Quote | eIDAS QTSP via ARIADNEXT (EU) — [src](https://shuftipro.com/blog/top-kyc-platforms-for-fintechs) |
| **Sumsub** | ID + liveness + face match; AML/PoA on Compliance tier; reusable KYC — [src](https://sumsub.com/pricing) | 220+ countries, 14,000+ doc types (vendor) | SaaS; web, iOS, Android, React Native, **Flutter**, Cordova — [src](https://bitpolaris.com/insights/how-to-choose-a-kyc-provider-for-a-crypto-exchange/) | **Yes (plugin)** | **$1.35** Basic / **$1.85** Compliance; $149/$299 minimum — [src](https://sumsub.com/pricing) | iBeta Level 3 claimed 21 Oct 2026 (vendor-reported) — [src](https://bitpolaris.com/insights/how-to-choose-a-kyc-provider-for-a-crypto-exchange/) |
| **Shufti Pro** | Document verification, face/liveness ("3D analysis"), AML, KYB, video KYC — [src](https://shuftipro.com/plans/) | 240+ countries, 3,000+ docs; PH not enumerated | SaaS, private cloud, **on-premise** — [src](https://beverified.org/providers/shufti-pro) | Not stated | Free 10/month; **from $1.50** Essentials up to 20k verifications; enterprise quote — [src](https://beverified.org/providers/shufti-pro) | iBeta Level 2 (Apr 2025, 1,500 attacks, APCER 0%) — [src](https://www.ibeta.com/wp-content/uploads/2025/04/250410-Shufti-Pro-PAD-Level-2-Confirmation-Letter.pdf) |
| **Trulioo** | Data-source identity verification (450+ sources) + document/biometric; KYB; 14,000+ doc types — [src](https://shuftipro.com/blog/top-kyc-platforms-for-fintechs) | Global; PH data coverage not enumerated | API/SaaS | Not stated | Quote | ISO 27001, SOC 2 Type 2; limited public iBeta info — [src](https://shuftipro.com/blog/top-kyc-platforms-for-fintechs) |
| **Innov8tif EMAS** | Document checks incl. "microprint, hologram, tampering", face biometrics, BSP/AMLC-aware — [src](https://innov8tif.com/solutions/landing-pages/ekyc-philippines) | **PhilSys, UMID, SSS ID, voter ID, professional/non-professional DL, passport** — [src](https://landingpage.innov8tif.com/ekyc-philippines) | Cloud, on-prem, hybrid; self-serve API — [src](https://innov8tif.com/solutions/landing-pages/ekyc-philippines) | Not stated | Self-serve API / quote | Regional ASEAN vendor; no public NIST/iBeta results found |
| **Didit** | ID + passive liveness + face match + device/IP + AML; PhilSys "on request" — [src](https://didit.me/blog/philsys-verification) | Global docs; PH via PhilSys + document route | API/SaaS | Web SDKs; Flutter not confirmed | **$0.33** full KYC ($0.15 ID + $0.10 liveness + $0.05 face + $0.03 device); NFC +$0.15; AML +$0.20 — [src](https://didit.me/blog/the-5-best-kyc-solutions-on-the-market-which-software-is-right-for-your-business/) | iBeta PAD Level 2 (vendor blog) — no NIST FRTE entry found |
| **PhilSys eVerify (PSA/DICT)** | Tier 0 QR signature; Tier 1 verified/not + full name; Tier 2 demographics + registration portrait — [src](https://philsys.gov.ph/faq-frequently-asked-questions) | **Only Philippine National ID** | REST API; relying party contracts with PSA directly | N/A (backend) | QR check **free**; online auth fees unconfirmed publicly — [src](https://didit.me/blog/philsys-verification) | Government registry; 90,290,024 registered/verified as of 31 Oct 2025 — [src](https://philsys.gov.ph/90m-filipinos-successfully-registered-verified-to-the-national-id) |

**Key structural facts about the vendor layer**

- Vendors cannot be the PhilSys relying party: "Technology providers cannot register as relying parties on their own; they may act as solution partners," and the business must sign the PSA contract — [Didit, PhilSys verification, 6 Oct 2026](https://didit.me/blog/philsys-verification) `[SECONDARY, detailed and PSA-sourced]`.
- PSA onboarding runs regulatory review (7–10 working days), technical validation/security assessment, then a subscription contract for 1 or 3 years; a VAPT is required before go-live; end-to-end "several weeks to a few months"; published SLA 99.5% monthly availability — [same](https://didit.me/blog/philsys-verification) `[SECONDARY]`.
- PhilSys returns **only** the minimum data per tier; PSA prohibits storing the PSN itself, and a relying party may retain only what its agreement authorises — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions); [Didit](https://didit.me/blog/philsys-verification) `[VERIFIED / SECONDARY]`.
- Tonik integrated the PhilSys API in Aug 2025 and verifies in **under 10 seconds**, accepting card, digital copy and eGovPH app ID — [Fintech News PH, 29 Aug 2025](https://fintechnews.ph/68070/digital-banking-news-philippines/tonik-digital-bank-now-accepts-national-id-for-account-opening) `[SECONDARY]`.
- GCash reports >24 million users verified/opened via National ID, and accepts card, ePhilID/paper and Digital National ID — [PSA, 4 Aug 2026](https://psa.gov.ph/content/national-id-authentication-services-drive-digital-transformation-service-delivery); [PSA](https://psa.gov.ph/content/digital-national-id-accepted-valid-id-mobile-banking-e-wallet-services) `[VERIFIED]`.
- BSP Circular 1170 (2023) sets the eKYC rulebook for BSP-supervised institutions: tiered/risk-based assurance, identity proofing vs authentication vs lifecycle management, robust ICT, consent and data-protection compliance — [BSP Circular 1170](https://www.bsp.gov.ph/Regulations/Issuances/2023/1170.pdf) `[VERIFIED]`. BSP M-2025-012 then required BSFIs to accept **all** National ID formats as sufficient proof without demanding another ID — [BSP M-2025-012](https://www.bsp.gov.ph/Regulations/Issuances/2025/M-2025-012.pdf) `[VERIFIED]`.
- Regula, Microblink and Incode publish Flutter integrations; Persona explicitly lists only iOS/Android/React Native; Jumio/Onfido/Veriff/Sumsub/Shufti/Trulioo/Innov8tif do not clearly document Flutter in the pages reviewed — sources as per table `[VERIFIED where linked; otherwise GAP]`.

### Inferences
- For a Flutter app, the practical shortlist is **Microblink or Regula on-device** (capture + OCR + optional document checks) plus a **cloud IDV with a real Flutter SDK** (Incode, Sumsub) if face match/liveness/manual review is wanted; Persona and Onfido would need a web-view bridge.
- `[INFERENCE]` None of these vendors will authenticate a Valenzuela or Naga senior card out of the box. They will OCR it, maybe read its barcode, and return a low-confidence "unknown document" or a generic "ID card" classification. Budget for custom template work or accept OCR-only.
- `[INFERENCE]` Unit economics rule out per-order IDV. At $0.33–$1.89 per check against a 20% discount on a typical medicine basket, verification cost can approach or exceed the discount itself. Verification must be per-account, one-time, with risk-based re-checks.
- PhilSys is the only vendor-independent, government-backed check, but it proves **identity and age**, not **PWD status** — the Tier 0/1/2 field lists contain name, sex, DOB, place of birth, PCN, issuance date, fingerprint/portrait; there is no disability attribute — [Didit tier table](https://didit.me/blog/philsys-verification) `[SECONDARY]` → `[INFERENCE]`.

### Gaps
- No vendor publishes per-document accuracy, and none publishes a Philippine LGU-specific template list.
- Flutter support for Jumio, Entrust/Onfido, Veriff, Shufti, Trulioo, Innov8tif is unconfirmed from public docs — must be asked in an RFP.
- Public PhilSys Tier 2 pricing is unavailable; the fee schedule is only disclosed during onboarding.
- No independent head-to-head evaluation of these vendors on Philippine documents exists.

---

## 4. What a small marketplace can realistically automate from a phone photo of a laminated LGU senior/PWD ID

### Takeaway
Realistically: capture quality, OCR prefill, field/format consistency, duplicate-ID detection across accounts, PhilSys QR verification when the user presents a National ID, and a risk-scored selfie-to-portrait match. What cannot be automated is the thing the discount actually depends on — whether this specific LGU issued this specific card to this specific person — because there is no national template, no MRZ, usually no signed barcode, and (until UPWDIS matures) no queryable national PWD registry.

### Cited Findings

**The entitlement is local, not national**

- RA 10754 grants the 20% discount and VAT exemption "upon submission" of an LGU-issued PWD ID, a passport, or an NCWDP transport discount ID — the statutory list has not been amended — [RA 10754 as summarised by AI Council/bettergov, Nov 2026](https://aicouncil.bettergov.ph/issues/ncda-ao-001-2026-pwdid) `[SECONDARY, quoting statute]`.
- The DOJ's October 2026 legal opinion: "Prior verification of the validity of a PWD ID is not allowed under the law. You have to extend the benefit already upon presentation of the ID. However, the business establishment concerned is not prevented from subsequently validating the authenticity of the ID" — [Newswatch Plus, 9 Oct 2026](https://www.newswatchplus.ph/2026/10/09/ncda-flags-nearly-6m-fake-pwd-ids) `[SECONDARY reporting DOJ]`. **This directly constrains product design: you cannot block the discount at checkout pending verification.**
- NCDA reports ~**8.5 million people using PWD IDs against ~2.8 million registered PWDs** (~6 million discrepancy), and a 2024 Senate inquiry estimated **₱88.2 billion** in lost revenue from fake PWD IDs — [Newswatch Plus](https://www.newswatchplus.ph/2026/10/09/ncda-flags-nearly-6m-fake-pwd-ids); [PIA, 4 Oct 2026](https://pia.gov.ph/news/luzon/calabarzon/unified-pwd-id-to-protect-benefits-curb-fake-ids/) `[SECONDARY]`.
- The DOH Philippine Registry of Persons with Disability (PRPWD) is incomplete; DOH says legitimate PWD IDs remain valid even if absent from it, and Baguio's PDAO has appealed to DOJ against treating PRPWD as the sole basis for denying discounts — [PIA CAR, 11 Mar 2026](https://pia.gov.ph/news/rcda-cordillera-tackles-pwd-discount-verification-challenges); [Remote Staff, Mar 2025](https://www.remotestaff.ph/blog/fake-pwd-ids) `[SECONDARY]`.

**The national PWD registry is being built now, not finished**

- NCDA Administrative Order 001 s.2026 creates the **Unified PWD ID** and the Unified Persons with Disabilities Identification System (UPWDIS): standardised format with unique alphanumeric number, photo, and **QR code or barcode**, centralised registry fed by LGUs, lifetime validity for permanent disabilities, 5-year revalidation for non-apparent disabilities, 3-year transition, and **no PhilSys number required** — [Manila Bulletin, 10 Sep 2026](https://mb.com.ph/2026/09/10/new-rules-set-for-unified-pwd-id-system-heres-what-you-need-to-know) `[SECONDARY]`.
- DSWD/NCDA began urging all PWD ID holders to register with UPWDIS in November 2026, including holders of still-valid cards, with mobile/assisted registration for GIDAs and people with limited mobility — [Interaksyon/Philstar, 12 Nov 2026](https://interaksyon.philstar.com/trends-spotlights/2026/11/12/321738/pwds-urged-register-nationwide-centralized-database) `[SECONDARY]`.
- PhilHealth's October 2026 circular states it currently uses PRPWD for PWD validation "for now, and the UPWDIS once PhilHealth announces that the UPWDIS is fully operational" — i.e. as of Oct 2026 the unified registry was **not yet the operational source of truth** — [PhilHealth TS-PC-2026-0017](https://www.philhealth.gov.ph/circulars/2026/TS-PC-2026-0017_English.pdf) `[VERIFIED]`.
- Rollout is uneven: reports differ on LGU readiness (roughly 959–1,100 of ~1,642 LGUs connected/trained as of Sept 2026), and a substantial share of LGUs cannot yet issue the unified card — [AI Council summary of Rappler/Inquirer/Tech Pilipinas reporting, Nov 2026](https://aicouncil.bettergov.ph/issues/ncda-ao-001-2026-pwdid) `[SECONDARY, conflicting counts — treat as directional only]`.

**Some LGUs already run their own verification portals**

- Valenzuela's VAL ID portal lets establishments validate senior/PWD IDs in real time, alongside a holographic seal on the new card — [Valenzuela City, May 2025](https://valenzuela.gov.ph/article/valenzuela-unveils-new-design-for-pwd-and-senior-citizen-id-cards-launches-val-id-validation-portal) `[VERIFIED]`.
- Baguio City built a local verifier for registered PWDs on its website, but it does not cover PWDs from other provinces — [PIA CAR, Mar 2026](https://pia.gov.ph/news/rcda-cordillera-tackles-pwd-discount-verification-challenges) `[SECONDARY]`.
- Quezon City's QCitizen ID is the unified local ID for seniors, PWDs and solo parents, with digital presentation accepted — [QC memorandum, 2021](https://quezoncity.gov.ph/wp-content/uploads/2021/04/Memo-QCitizen-ID.pdf) `[VERIFIED, older]`.

**What a phone can still do usefully**

- OCR + field consistency is cheap and improves data quality: name, DOB, ID number, address, issuance date; compare against the account profile and against the order's typed details.
- Duplicate detection: the same ID number or the same portrait appearing across multiple accounts is a strong fraud signal and requires no vendor at all — `[INFERENCE]`.
- PhilSys QR verification is free, offline-capable, and cryptographically sound for the National ID; PSA explicitly permits any relying party to use it with cardholder consent — [PhilSys Check](https://philsys.gov.ph/philsys-check) `[VERIFIED]`.
- Senior status *is* derivable from PhilSys: DOB is returned in Tier 0/1/2, so age ≥60 can be computed with certainty — [Didit tier table](https://didit.me/blog/philsys-verification) `[SECONDARY]` → `[INFERENCE]`.
- Face match + passive liveness can be bought per check; Incode and Sumsub offer Flutter SDKs, Microblink/Regula cover capture — see §3.

### Inferences
- `[INFERENCE]` A defensible automation boundary for this marketplace:
  1. **Automate**: capture-quality gating, OCR prefill, format/expiry checks, name/DOB consistency, duplicate-ID and duplicate-face detection, PhilSys QR check when available, device/image reuse signals.
  2. **Semi-automate**: selfie↔portrait similarity as a *risk score* routed to human review, never as an automatic rejection.
  3. **Keep human/in-person**: physical card inspection at handover (already exists), plus exception handling for users who cannot or will not do a selfie.
  4. **Do not attempt**: hologram/OVI/UV/microprint verification from a phone photo; LGU template authentication; PWD-status verification without a registry.
- `[INFERENCE]` The highest-ROI control is not biometrics — it is **binding the discount to a verified account once, then checking at handover that the person present matches the account holder**. That addresses ID sharing, which is the dominant fraud pattern implied by the 8.5M-vs-2.8M gap.
- `[INFERENCE]` Because the DOJ says the discount must be granted on presentation, any "pending verification" state must apply to the *account* (e.g. flag for review, restrict future online discount use), not to the current transaction.
- `[INFERENCE]` Cost: at $0.33–$1.89 per full IDV check, verifying every order is uneconomic; verifying once per account plus risk-based re-verification is the only sane model.

### Gaps
- No public API documentation or launch date for **UPWDIS** query access; whether third-party marketplaces will be allowed to query it is unknown.
- No national standard for OSCA senior ID design, so no vendor can build a complete template library.
- No published data on how many pharmacy discount claims are fraudulent, or on the loss rate specific to medicine purchases.
- No data on smartphone/camera quality among Filipino senior citizens, which determines whether OCR/selfie flows are even feasible for the target population.

---

## 5. What "verified" can legitimately mean at each level — and which claims are defensible

### Takeaway
"Verified" is not one thing. It can mean "the text was read correctly", "the card looks like the expected design", "the presenter's face matches the portrait", or "the government registry confirms this person". Only the last is authoritative, and in the Philippines only PhilSys offers it at scale — for identity and age, not for PWD status. Any marketing or UI claim must name the level, or it is misleading.

### Cited Findings

**What the registry actually returns**

- PhilSys Tier 0 (QR check, free, offline): names, suffix, sex, date and place of birth, PhilSys Card Number, date of issuance, "best fingerprint"; the ePhilID result adds a portrait — [Didit tier table, citing PSA](https://didit.me/blog/philsys-verification) `[SECONDARY]`.
- Tier 1 (basic): "Verified or Not Verified, plus the registered full name". Tier 2 (eKYC): verified demographic data plus the registration portrait, usable to pre-fill forms; the exact field list is only released after onboarding — [same](https://didit.me/blog/philsys-verification) `[SECONDARY]`.
- PhilSys Check reports four states: verified, deactivated card, QR unreadable/tampered/not from PhilID, and offline/unable to verify — [PSA, 1 Feb 2024](https://philsys.gov.ph/philsys-authentication-services-strengthen-transaction-security/) `[VERIFIED]`.
- The PhilID has no handwritten signature; authenticity rests on the digital signature in the QR — [Regula, Mar 2025](https://regulaforensics.com/blog/philippine-national-id-verification) `[SECONDARY]`.
- RA 11055 makes the National ID valid proof of identity in public and private transactions; refusing it without just cause carries a ₱500,000 fine, and BSP requires all formats to be accepted — [Didit summary of RA 11055 and BSP M-2025-012](https://didit.me/blog/philsys-verification) `[SECONDARY]`.

**What vendors mean by "verified"**

- Entrust returns "clear" or "consider", explicitly a recommendation, with configurable logic — [Entrust document verification](https://onfido.com/solutions/document-verification) `[VENDOR CLAIM]`.
- Regula returns per-check results (template match, MRZ cross-check, barcode cross-check, RFID, authenticity) rather than a single boolean — [Regula Document Reader SDK](https://regulaforensics.com/products/document-reader-sdk) `[VENDOR CLAIM]`.
- Jumio/Veriff/Sumsub market "verification" but the underlying artefacts are document report + face match + risk signals; none claims to have queried a Philippine registry for a senior/PWD card.

**Legal reality of the discount**

- RA 9994 (senior citizens) and RA 10754 (PWD) grant the discount on presentation of the ID; the establishment's obligation is triggered by presentation, not by prior verification — [RA 10754 summary](https://aicouncil.bettergov.ph/issues/ncda-ao-001-2026-pwdid); DOJ opinion as above `[SECONDARY]`.

### Inferences

| Level | What is actually established | What it does **not** establish | Defensible claim (UI/marketing) | Typical cost / latency |
|---|---|---|---|---|
| **L0 — OCR only** | Text was extracted and matches typed/account data | Anything about the card's genuineness | "We read your ID details" | ~free, <1 s on-device |
| **L1 — Field/format consistency** | Name, DOB, ID number, expiry are internally coherent and plausible | That the card was issued by the LGU | "ID details look consistent" | ~free |
| **L2 — Template/security-feature check** | Card matches a known design; visible security features present; not an obvious screen replay/printout | That the specific card is genuine, especially for LGU designs with no template | "Document appears genuine" — only if a template actually exists | $0.15–$1.00+, 1–8 s |
| **L3 — Biometric match** | Presenter's face resembles the portrait on the card | That the portrait belongs to the presenter, that the card is genuine, that the account holder is the person ordering | "Selfie matches the photo on your ID" | $0.05–$0.30, 1–6 s |
| **L4 — Government registry** | PhilSys confirms identity (+ age via DOB); UPWDIS (when live) would confirm PWD registration | PWD status today; senior discount entitlement for a *specific* LGU card | "Identity confirmed by the National ID system" | Free (QR) to contract (Tier 1/2), 1–10 s |

- `[INFERENCE]` "Verified senior citizen" is defensible **only** if you mean "PhilSys confirms this person is ≥60" or "an OSCA ID was inspected and accepted at handover". It is not defensible from OCR + a selfie.
- `[INFERENCE]` "Verified PWD" is not defensible at all today unless you queried the issuing LGU/PDAO or UPWDIS. Until UPWDIS is live, the honest claim is "PWD ID recorded; eligibility checked at pickup".
- `[INFERENCE]` Wording that survives scrutiny: "ID details recorded", "photo matched", "identity confirmed by PhilSys", "eligibility verified at branch". Wording that does not: "government-verified ID", "authenticated senior/PWD", "fraud-proof".

### Gaps
- No Philippine case law or NPC guidance specifically on what a marketplace may claim when it relies on a vendor's "verified" result.
- No confirmation whether UPWDIS will expose a third-party verification API, or whether it will be limited to government/PhilHealth use.
- Whether an LGU can authorise a private marketplace to query its local senior/PWD database is untested.

---

## 6. False accept/false reject rates, failure modes, adversarial robustness and known bypasses

### Takeaway
Published error rates are laboratory artefacts measured on cooperative subjects, controlled lighting and known attack types. In the field, the dominant failure mode for this use case is not algorithmic error but **image quality and card variability**, and the dominant attack is not a deepfake but **a shared, borrowed or physically counterfeited card**. Budget for false rejects among the elderly, and treat every vendor "0%" as "0% against the attacks they were tested on".

### Cited Findings

**Accuracy numbers and what they hide**

- NIST FRTE 1:1 top-tier FNMR ranges from **0.16% (visa→border)** to **3.75% (border→kiosk)** on controlled pairs — [Paravision, Jul 2025](https://www.paravision.ai/news/paravision-delivers-world-class-performance-in-latest-nist-frte-evaluations-ranking-in-top-5-globally-for-both-face-verification-and-identification) `[VENDOR CLAIM reporting NIST]`.
- NIST's own report warns there is no single "best" algorithm; performance depends on image conditions, demographics and matching mode — [Biometric Update, 21 Sep 2026](https://www.biometricupdate.com/202609/nist-frte-11-update-shows-shifting-leaders-persistent-demographic-disparities) `[SECONDARY]`.
- iBeta Level 2 reports 0% APCER across 1,500 attacks, but with **cooperative subjects**, **mid-level artefacts**, and **24 hours per attack instrument** — [iBeta letter](https://www.ibeta.com/wp-content/uploads/2025/04/250410-Shufti-Pro-PAD-Level-2-Confirmation-Letter.pdf) `[VERIFIED]`. It says nothing about a 70-year-old with glasses in a dim room.
- Screen-recapture detection research reports **EER 16.78%** even after a purpose-built model, and degrades under JPEG compression and low resolution — [CMA, CVPR 2024](https://www.computer.org/csdl/proceedings-article/cvpr/2024/530000p577/20hOYpKEb5u) `[VERIFIED]`.

**Failure modes specific to this document class**

- Laminated paper cards produce glare and specular reflections that obscure fields and portrait; the PhilID is polycarbonate and reflective too — [Naga ordinance describes plastic-laminated card](https://www2.naga.gov.ph/wp-content/uploads/2021/06/ord2018-082.pdf); [Regula on PhilID materials](https://regulaforensics.com/blog/philippine-national-id-verification) `[VERIFIED/SECONDARY]`.
- Portraits on LGU cards are often older, lower-resolution, and printed on paper — degrading face-match similarity independent of algorithm quality — `[INFERENCE from card production described in ordinances]`.
- No MRZ, no chip, frequently no signed QR → the high-assurance machine-readable checks simply do not apply — `[INFERENCE]`.
- Expiry/renewal rules vary by LGU (some cards show dates, some do not; replacement fees and affidavit requirements differ) — [Sulat.ph](https://sulat.ph/government-documents/how-to/senior-citizen-id-requirements); [Naga OSCA](https://www2.naga.gov.ph/ccgovernment-service/get-a-senior-citizens-id/) `[SECONDARY]`.

**Adversarial robustness / known bypasses**

- **Camera injection**: virtual camera + real-time face swap passed a bank's liveness check on a non-rooted Android phone — [MITRE ATLAS via Biometric Update, Dec 2025](https://www.biometricupdate.com/202512/iproov-shows-how-face-swapping-app-can-fool-liveness-detection-on-financial-apps) `[SECONDARY]`.
- **Tooling market**: WEF catalogued 17 face-swap and 8 injection tools in 10 months; MIT Technology Review found bypass kits sold across 22 Telegram channels/groups; Binance said it saw and blocked attempts, BBVA and Revolut declined to comment — [WEF 2026](https://reports.weforum.org/docs/WEF_Unmasking_Cybercrime_Strengthening_Digital_Identity_Verification_against_Deepfakes_2026.pdf); [Lemma brief on MIT Tech Review investigation, Jun 2026](https://lemma.frame00.com/critical/briefs/034-ekyc-liveness-bypass) `[VERIFIED/SECONDARY]`.
- **Replay of a genuine session**: a recorded authentic video can pass basic checks if the system cannot tell live from replayed — [Regula, Sep 2025](https://regulaforensics.com/blog/video-injection-attacks) `[VENDOR CLAIM]`.
- **Document recapture**: a photo of a screen defeats checks that only ask "does this look like a valid document" — [CheckFile, Aug 2026](https://www.checkfile.ai/en-US/blog/screen-recapture-attack-detection-document-fraud) `[SECONDARY]`.
- **Physical counterfeits**: the DOJ/NCDA enforcement push and Valenzuela's confiscation ordinance both presuppose a large volume of physical fake cards in circulation — [Newswatch Plus](https://www.newswatchplus.ph/2026/10/09/ncda-flags-nearly-6m-fake-pwd-ids); [Valenzuela](https://valenzuela.gov.ph/article/valenzuela-unveils-new-design-for-pwd-and-senior-citizen-id-cards-launches-val-id-validation-portal) `[SECONDARY/VERIFIED]`.
- **Morphing** is a lower-probability attack here (it targets enrolment with a self-submitted photo), but NIST's finding that unfamiliar-software detection falls below 40% is a reminder that "detection" claims are conditional — [NIST IR 8584](https://www.nist.gov/news-events/news/2025/08/nist-guidelines-can-help-organizations-detect-face-photo-morphs-deter) `[VERIFIED]`.

**Threat model for this marketplace** `[INFERENCE]`

| Attacker | Capability | Best control | Residual risk |
|---|---|---|---|
| Family member using senior's card | Has physical card, may know details | In-person handover check; account-to-person binding; duplicate-face/ID detection | High — hard to distinguish from legitimate assisted purchase |
| Buyer of a counterfeit LGU card | Printed/laminated fake with plausible data | LGU portal lookup where available; barcode/QR registry; human inspection | High — no national registry |
| Reused photo of someone else's ID | Digital image, no physical card | Document liveness/recapture detection; PhilSys QR required for identity | Medium |
| Synthetic identity with deepfake | Face-swap + virtual camera | Camera-path attestation, device metadata, randomised challenge, PhilSys | Low priority at this discount value |
| Organised discount arbitrage | Many accounts, stolen IDs | Velocity limits, duplicate detection, manual review queue | Medium |

### Inferences
- `[INFERENCE]` The false-reject cost is asymmetric and political: rejecting a legitimate senior at the counter is a PR and legal risk (RA 9994/RA 10754; DOJ says benefits cannot be denied on suspicion alone), while accepting a fake costs ~20% of one basket. Design for **low false rejects**, recover losses through post-hoc audit and account-level controls.
- `[INFERENCE]` Do not deploy active liveness (blink/turn head) as a hard gate for elderly users; it is the modality most likely to fail them and the one most easily defeated by injection anyway.
- `[INFERENCE]` The single most valuable anti-fraud signal is **cross-account duplication** (same ID number, same portrait, same device, same pickup pattern), which needs no vendor and no biometrics.

### Gaps
- No published FAR/FRR for any commercial system on Philippine LGU IDs or on Filipino elderly faces.
- No Philippine data on the share of fake-ID use that is physical counterfeit vs borrowed genuine vs digital manipulation.
- No independent test of whether PhilSys QR verification survives photographing a *screen* showing a valid QR (replay of a signed payload) — the signature proves the payload is genuine, not that the physical card is present.

---

## 7. Privacy-by-design: on-device processing, templates vs raw images, biometric rules, vendor retention

### Takeaway
Biometric data is sensitive personal information under the Data Privacy Act, so the default must be: process on-device where possible, store a decision and a non-reversible template/hash rather than raw images, never store the PSN, and give the user a deletion path. Vendor retention is a contractual lever, not a default — several vendors charge extra for longer retention precisely because short retention is the baseline.

### Cited Findings

**Philippine legal baseline**

- The NPC has treated biometric data as sensitive personal information in advisory opinions — [NPC Advisory Opinion No. 2025-007](https://privacy.gov.ph/wp-content/uploads/2025/09/NPC-Advisory-Opinion-No.-2025-007_Redacted.pdf) `[VERIFIED]`.
- Consent must be specific, informed, and evidenced; NPC Circular 2023-04 governs how consent is obtained and managed — [NPC Circular 2023-04, 7 Nov 2023](https://privacy.gov.ph/wp-content/uploads/2023/11/NPC-Circular-No.-2023-04_Guidelines-on-Consent_07Nov2023.pdf) `[VERIFIED]`.
- PhilSys requires consent per authentication transaction, and the relying party may retain only data its agreement authorises; storing the PSN itself is prohibited — [PhilSys Check](https://philsys.gov.ph/philsys-check); [Didit summary](https://didit.me/blog/philsys-verification) `[VERIFIED/SECONDARY]`.
- BSP Circular 1170 requires eKYC to comply with "relevant user consent and data sharing and protection/privacy laws" and to document roles and responsibilities of each party — [BSP Circular 1170](https://www.bsp.gov.ph/Regulations/Issuances/2023/1170.pdf) `[VERIFIED]`.
- PSA states PhilSys is designed privacy-by-design from registration to authentication — [PSA, 1 Feb 2024](https://philsys.gov.ph/philsys-authentication-services-strengthen-transaction-security/) `[VERIFIED]`.

**Technical privacy options**

- Microblink extracts document data **entirely on-device**; "no data from the document leaves the device" — [Microblink docs](https://docs.microblink.com/blinkid) `[VENDOR CLAIM]`. This is the strongest privacy posture available for OCR.
- Regula offers on-device mobile SDK processing, but validates NFC chip data server-side to prevent device tampering — a deliberate split between privacy and integrity — [Regula](https://regulaforensics.com/products/document-reader-sdk) `[VENDOR CLAIM]`.
- Incode exposes end-to-end encryption of capture artefacts and a capture-only SDK mode where the customer handles all network traffic — [Incode Flutter SDK](https://developer.incode.com/sdk-reference/flutter-sdk/); [Mobile integration overview](https://developer.incode.com/integrate-by-platform/mobile-integration-overview) `[VENDOR CLAIM]`.
- Veriff charges **+$0.30 per verification for 2-year data retention**, which implies a shorter default retention window — [Veriff pricing](https://www.veriff.com/pricing) `[VENDOR CLAIM]`.
- Incode's API-only integration loses "on-device Device and Camera trust signals" used for deepfake detection, pushing analysis server-side — a concrete privacy/security trade-off — [Incode integrate-by-platform](https://developer.incode.com/integrate-by-platform) `[VENDOR CLAIM]`.

### Inferences
- `[INFERENCE]` Minimum viable privacy design for this marketplace:
  - Do OCR and image-quality checks **on-device** where the SDK allows; upload only the extracted fields plus a liveness/quality decision.
  - If a selfie is taken, derive a face template or similarity score and **discard the raw selfie** after the decision (or after a short, stated window for dispute resolution).
  - Store the ID **number as a keyed hash** for duplicate detection, not in clear text; store the PSN never.
  - Keep an audit record of *what was checked, when, by which method, and the result* — not the images.
  - Provide in-app deletion and a stated retention period (e.g. 90 days for images, lifetime of account for the verification decision).
- `[INFERENCE]` A DPIA is required in practice before launch: biometric processing of a vulnerable population, a new processor (the vendor), cross-border transfer, and a legal basis that is not plain consent for the discount entitlement.
- `[INFERENCE]` Vendor selection should weight: on-device processing, data residency options, retention configurability, deletion API, and whether the vendor trains on customer data. None of the reviewed vendor pages clearly states PH data residency — ask in the RFP.
- `[INFERENCE]` Avoid storing the PhilSys portrait returned by Tier 2 unless there is a documented purpose; it is government-sourced biometric data and the agreement limits retention.

### Gaps
- No public information on which IDV vendors host Philippine customer data in-country, or on their sub-processor lists for PH.
- Unclear whether NPC has issued guidance specific to face-match for discount eligibility (as opposed to KYC), and whether "legitimate interest" could ever substitute for consent here.
- No standard retention period for biometric templates under Philippine law; it is risk-based and must be justified in the DPIA.
- No public evidence on whether any vendor uses customer verification images for model training by default.

---

## Cross-cutting notes for the report writer

1. **The strongest single fact** for the "what can be automated" argument is structural, not algorithmic: the discount entitlement lives in LGU/OSCA/PDAO records that are only now being unified (UPWDIS, AO 001 s.2026, 3-year transition), while PhilSys — the one mature national registry — carries identity and age but not disability status.
2. **The second strongest** is the DOJ opinion: prior verification of a PWD ID is not allowed; post-hoc validation is. That kills any design that gates the discount on an automated check.
3. **Vendor claims should be quoted as claims.** None of the reviewed vendors publishes accuracy on Philippine LGU documents, and NIST's PAD track is closed, so 2025–2026 liveness claims rest on iBeta letters with cooperative subjects.
4. **Cost anchors** (Nov 2026 list): Didit $0.33 full KYC; Veriff $0.80–$1.89; Sumsub $1.35–$1.85; Shufti from $1.50; AU10TIX $500/mo minimum (AWS prepaid ~$18/check); Persona from $250/mo + $1.50/service; Regula/Microblink/Jumio/Onfido/Trulioo/IDnow quote-only. PhilSys QR is free; Tier 1/2 fees unconfirmed.
5. **Latency anchors**: PhilSys 1–5 s (Tonik <10 s end-to-end); Veriff ~6 s average decision; AU10TIX 4–8 s document, <90 s full onboarding; Regula "seconds".
6. **Named uncertainty to flag in the report**: whether any vendor has a template for a specific Philippine LGU card; whether UPWDIS will expose a third-party API; whether PhilSys Tier 2 returns address; and the actual fraud loss rate for pharmacy discounts.
