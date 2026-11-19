# Adversarial Fraud, Abuse Patterns, Evaluation, and Operational Controls for a Saved Senior/PWD Discount ID (Philippines, November 2026)

**Scope.** This stream covers the threat model, fraud economics, detection/evaluation methods, human review, privacy/legal exposure, Philippine practice, and operational design for saving a senior-citizen or PWD discount ID to a marketplace account and reusing it at checkout. It deliberately does **not** cover OCR engine selection, document-authenticity algorithms, hologram/UV hardware, or biometric matching accuracy; those are covered in the sibling notes `ocr_extraction.md` and `authenticity_fraud_detection.md`.

**Evidence labels used below.** `[Law/regulator]` = statute, regulator, or standards body. `[Peer-reviewed]` = academic publication. `[Vendor claim]` = company marketing or vendor research, not independently tested here. `[Industry data]` = proprietary network data published by a company. `[News/secondary]` = reporting or practitioner summary of a primary source. `[Inference]` = my reasoning from the cited facts. Where sources conflict, the conflict is stated rather than smoothed over.

**The one-sentence framing.** A saved ID is not a stronger version of a counter check; it is a **bearer credential** that can be reused, lent, sold, or stolen, and the marketplace becomes the issuer of that credential without any ability to revoke it at the source.

---

## 1. Threat model: what changes when a discount ID is verified once and reused

### Takeaway

The dominant local threats are not Hollywood deepfakes. They are cheap template-generated or purchased LGU/PWD IDs, borrowed family IDs (which are partly lawful because representatives may buy for seniors), and insider or account-level abuse. Saving the ID converts a per-order visual check into a one-time gate, so a single successful verification can be monetised repeatedly; the control objective shifts from "is this card genuine?" to "is the person using this saved identity the person entitled to it, on this order, today?"

### Cited Findings

**Local supply of fake and borrowed IDs**

- The National Council on Disability Affairs (NCDA) issued a formal condemnation of the "widespread production and deliberate misuse of fake Persons with Disabilities Identification Cards", stating that producing, distributing, or using fake PWD IDs may constitute falsification of public documents, violations of the Data Privacy Act, and perjury under Articles 171 and 172 of the Revised Penal Code — [NCDA, 11 Feb 2025](https://ncda.gov.ph/2025/04/10/statement-of-condemnation-against-the-proliferation-of-fake-persons-with-disability-identification-cards-pwd-ids/). `[Law/regulator]`
- NCDA's governing board recorded that it "received reports on the fraudulent use of fake PWD-IDCs, which were purchased through online platforms or legitimately issued to non-eligible persons, in order to avail of discounts and privileges exclusive for persons with disabilities", and that LGUs use different numbering, card designs, and security measures — [NCDA Board Resolution No. 5, s. 2025](https://ncda.gov.ph/a-resolution-creating-the-inter-agency-technical-working-group-on-the-development-of-the-unified-id-system-for-persons-with-disabilities-board-resolution-no-5-series-of-2025/). `[Law/regulator]`
- There is no uniform PWD ID design: each municipality or city has its own format. A Facebook Marketplace search surfaced dozens of PWD ID listings, some offering booklets and some explicitly "Class A", with one-to-two-day processing — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`
- One restaurant group rejected **726 of 4,212 PWD IDs (17.24%)** presented across its branches between 15 June and 14 August 2024; reasons included erroneous numbers or codes, missing details, photocopies, and formats that differed from authentic IDs — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`
- In September 2025, Valenzuela police arrested a couple alleged to be mass-producing fake government IDs, seizing more than 300 cards and forged PhilHealth Member Data Records; the IDs were reportedly sold on social media for **P40 to P1,000** each. Charges were falsification of documents under the Revised Penal Code in relation to the Cybercrime Prevention Act of 2012 — [Inquirer, 10 Sep 2025](https://newsinfo.inquirer.net/2107461/couple-nabbed-for-mass-production-selling-fake-govt-ids-in-valenzuela). `[News/secondary]`
- In the same case, Valenzuela's mayor said fake PWD and senior IDs cost establishments "20 percent for pharmacies and another 12 percent for restaurants and bars", and cited the city's VAL-ID portal for real-time card verification and new IDs with security seals from Germany — [Inquirer, 10 Sep 2025](https://newsinfo.inquirer.net/2107461/couple-nabbed-for-mass-production-selling-fake-govt-ids-in-valenzuela). `[News/secondary]`

**Scale estimates, and why they should be treated cautiously**

- A Philippine Senate ways-and-means committee hearing heard estimates that there could be **8.5 million "illegitimate" PWDs**, nearly five times the **1.89 million registered PWDs**, and that **P166.1 billion of the P208.4 billion** in PWD discounts availed in 2023 went to holders of fake IDs, with **P88.2 billion** in taxes lost — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). These are Senate panel estimates, not an audited count; Rappler notes the exact number of fake IDs is unknown. `[News/secondary]`
- In November 2026, NCDA said the Department of Justice had previously raised the alarm on an estimated **6 million fake PWD cards** — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary]`

**Borrowed and family-shared IDs**

- Restaurant owners described "entire families that will present cards that say they're all PWD", including parents, children, and a nanny; in one group of 23 diners, 19 submitted PWD cards — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`
- Resto PH's August 2024 position paper said PWD discounts "are about to or are already exceeding that of senior citizens at an alarming rate" in many restaurants, despite there being more seniors (9.22 million in the 2020 census) than registered PWDs — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[Industry claim]`
- Philippine law expressly contemplates proxy purchase: the FDA's 2024 advisory says "Senior Citizens **or their representatives**" may avail the 20% medicine discount on presentation of an OSCA/mayor/barangay ID, a Philippine passport, or other government-issued documents showing age — [FDA Advisory No. 2024-0429](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`

**Document synthesis and AI-generated IDs**

- Entrust's 2025 Identity Fraud Report, drawing on over 1 billion verifications, found that **digital forgeries accounted for 57.46% of all document fraud**, up from 16.7% the prior year (a 244% year-over-year increase and a 1,600% increase versus 2021), and that a deepfake attempt occurred every five minutes in 2024 — [Entrust, 2025 Identity Fraud Report](https://www.entrust.com/sites/default/files/documentation/reports/2025-identity-fraud-report.pdf). `[Industry data]`
- The same report found that among selfie-based biometric attacks, a **photo on a screen** accounted for 50.29% of fraudulent attempts in 2024; among video/motion biometrics, **deepfakes made up 40.80%** of attempts — [Entrust, 2025](https://www.entrust.com/sites/default/files/documentation/reports/2025-identity-fraud-report.pdf). `[Industry data]`
- Entrust's 2026 report says deepfakes now account for **one in five biometric fraud attempts**, deepfake selfies rose **58% in 2025**, and injection attacks rose **40% year over year** — [Entrust, 2026 Identity Fraud Report](https://www.entrust.com/resources/reports/identity-fraud-report). `[Industry data]`
- Sumsub's 2025 report says **2% of all detected fake documents** in a six-month 2025 sample were created using generative-AI tools such as ChatGPT, Grok, and Gemini; it also describes underground "KYC bypass" services that combine forged documents, AI-generated faces, and stolen data — [Sumsub, 2025](https://sumsub.com/blog/ai-fake-id-challenge-for-kyc). `[Vendor claim]`
- OnlyFake and similar services reportedly generate realistic passports, driver's licences, and bank statements in under five minutes; researchers say such documents have bypassed automated KYC checks by mimicking textures, lighting, and fonts — [G2 Risk Solutions](https://g2risksolutions.com/blog/protect-your-business-from-ai-generated-fake-documents); [HYPR](https://www.hypr.com/blog/ai-forgery-epidemic). `[Vendor claim / secondary]`

**Deepfakes and camera-path attacks (relevant if a selfie/liveness step is added)**

- iProov's red team used a desktop face-swap app plus Open Broadcaster Software and a virtual-camera Android app to stream a live deepfake into a financial app's identity-verification flow and evade liveness detection; iProov described the vulnerability as "critical, high-risk" and said its February 2025 threat report recorded a **300% increase in face-swap attacks since 2023** — [Biometric Update, 17 Dec 2025](https://www.biometricupdate.com/202512/iproov-shows-how-face-swapping-app-can-fool-liveness-detection-on-financial-apps). `[Vendor claim]`
- TrendAI researchers used the Deepfake Offensive Toolkit and Deep-Live-Cam to bypass Veriff by streaming a deepfake through an Android cloud service; against IDScan, they achieved an anti-spoofing confidence score of **99** and a face-match confidence of **87** after a few attempts, using a face cropped from a passport at roughly 200×260 pixels — [TrendAI, 28 Nov 2024](https://www.trendaisecurity.com/en/resources-insights/deep-research/ai-vs-ai-deepfakes-and-ekyc). `[Vendor research]`
- A 2025 peer-reviewed study using `inswapper_128` and `buffalo_l` found most publicly accessible liveness/deepfake detectors could be bypassed; high-resolution (>720p) deepfakes were more successful than low-resolution ones, and 240p videos were caught — [NSysS '25, ACM](https://dl.acm.org/doi/full/10.1145/3777555.3777563). `[Peer-reviewed]`
- A 2025 arXiv paper argues that eKYC deepfake detection improves materially when the system compares the submitted video against a **registered reference image** assumed genuine — [arXiv 2507.22601](https://arxiv.org/html/2507.22601). `[Peer-reviewed, preprint]`

**OCR/LLM prompt injection**

- OWASP's prompt-injection guidance documents multimodal injection in images, screenshots, scanned documents, and PDFs: hidden text can be read by OCR and enter the model context as an instruction; defenses include rendering the page and flagging text that does not appear visibly, but OCR errors and fine print cause false positives — [OWASP LLM Prompt Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html). `[Standards body]`
- In a study of **196,682 resumes** from one hiring platform, more than **90% of hidden prompts contained no explicit instruction** — meaning keyword blocklists aimed at phrases like "ignore previous instructions" miss most hidden text — [OWASP, citing Zhang et al., USENIX Security 2026](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html). `[Peer-reviewed]`
- Practitioner guidance describes multimodal injection as hiding instructions in "images, screenshots, scanned documents, PDFs, audio, video, or visual layouts" and recommends treating stored content as untrusted unless it has a clear trust boundary and provenance — [Alice.io](https://alice.io/blog/prompt-injection-attack). `[Vendor claim]`

**Account takeover and insider abuse**

- TransUnion's global network data put the Philippines' suspected digital fraud rate at **13.4% in 2024**, second-highest among analysed markets and above the global 5.4%; its 2025 figure was **4.1%** against a global 3.8%, still above global for the sixth consecutive year — [TransUnion Philippines, 29 Apr 2025](https://newsroom.transunion.ph/philippines-suspected-digital-fraud-rate-higher-than-global-level-for-fifth-consecutive-year); [TransUnion, 2026](https://newsroom.transunion.ph/filipinos-face-widespread-digital-fraud-exposure-despite-lower-financial-losses-transunion-finds). The two figures are not like-for-like; treat them as directional. `[Industry data]`
- Globally, **8.3% of digital account-creation transactions** and **4.3% of account-login transactions** in H1 2025 were suspected digital fraud — [TransUnion H2 2025 Fraud Trends](https://media.transunion.com/content/dam/transunion/roa/business/collateral/report/3611950-h2-25-fraud-trends-rpr.pdf). `[Industry data]`
- The Association of Certified Fraud Examiners' 2024 global study found a median occupational-fraud loss of **US$145,000**, a median duration of **12 months**, and that **43% of frauds were detected by tips**; organisations with fewer than 100 employees had a median loss of **US$141,000** — [ACFE, Occupational Fraud 2024](https://www.ivey.uwo.ca/media/kjljj5cy/2024-report-to-the-nations.pdf). `[Industry data]`

**Biometrics are irrevocable**

- The NPC issued a cease-and-desist order against Tools for Humanity (Worldcoin) in September 2025, finding that biometric identifiers, unlike passwords or ID cards, "cannot be reset or replaced" and that a single breach could expose individuals to permanent, irreparable harm; it also found consent obtained in exchange for compensation was not freely given — [JDSupra summary of NPC order](https://www.jdsupra.com/legalnews/philippines-orders-halt-to-biometric-8231406). `[Law/regulator, secondary summary]`
- In an advisory opinion on the unified PWD ID system, the NPC addressed whether its 2023 circular on ID cards applies to the unified PWD ID, whether biometric data collection is governed by existing rules, and whether DSWD may process fingerprints and facial scans to verify PWD benefit claimants — [NPC Advisory Opinion No. 2025-007](https://privacy.gov.ph/wp-content/uploads/2025/09/NPC-Advisory-Opinion-No.-2025-007_Redacted.pdf). `[Law/regulator]`

### Inferences

**Threat ranking for a saved-ID pharmacy flow** (likelihood/impact are analyst judgments, not measured):

| Threat | Why it fits this flow | Likelihood now | Impact if successful |
|---|---|---|---|
| Template/synthetic LGU or PWD ID, printed or Photoshopped | No uniform design; templates sold online; cheap printers | High | High — passes a one-time check and is reusable |
| Borrowed family member's real ID | Proxy purchase is lawful in part; families already do it | High | Medium — contested entitlement, not always fraud |
| Stolen real ID + impostor | Breached government data and fake PhilHealth records | Medium | High — hard to detect without biometrics |
| Edited photo / screenshot / recapture | Gallery upload and screen photos are common | Medium | Medium — defeated by in-app capture, not by OCR |
| AI-generated whole document | Commoditised services; 2% of detected fakes in one vendor sample | Low–medium, rising | High if selfie/liveness is added without injection detection |
| OCR/LLM prompt injection on the card | Any visible or hidden text is attacker-controlled | Low today; high if an LLM's output is parsed as a decision | High — can flip a decision or corrupt fields |
| Fake QR pointing to a clone "verification" page | QR is increasingly used for PWD/senior verification | Medium | High if the app trusts the QR's URL |
| Account takeover | Saved ID plus saved payment is a valuable account | Medium | High — attacker inherits the discount and PII |
| Insider override / collusion | Branch or support staff can mark IDs verified | Medium | High — organised, hard to see in aggregate |
| Identity mismatch between account holder and ID | Common and often legitimate (caregiver, child, spouse) | High volume | Low per case, but masks fraud at scale |

- **The saved ID is a bearer credential.** Once verified, the marketplace has no cryptographic binding between the ID and a human at checkout. The cheapest attack is therefore not forgery but **account sharing or resale of a verified account**, which looks like normal repeat custom. `[Inference]`
- **Deepfakes are not the main local threat today.** A printed LGU card with a plausible layout and a real-looking number is cheaper and sufficient for a document-only check; deepfakes matter mainly if the marketplace adds a selfie/liveness step. Adding biometrics therefore raises the technical bar but also raises privacy, cost, and failure-mode risk for elderly users. `[Inference]`
- **Family sharing is not synonymous with fraud.** Because representatives may lawfully buy for seniors, duplicate use of the same ID across accounts is a **review trigger**, not a ban trigger. A design that treats every duplicate as fraud will punish legitimate caregivers and may amount to refusing a lawful discount. `[Inference]`
- **The monetary prize is small per order but large in aggregate.** A 20% discount on a P2,500 basket is P500; a P500 fake ID pays for itself on one order, and a P40 template pays for itself immediately. This is why fake-ID markets persist despite low per-transaction value. `[Inference]`
- **The most valuable stolen asset is the identity dossier, not the discount.** Name, date of birth, ID number, address, and photo can be reused for synthetic accounts, phishing, and future fake IDs. The discount is the lure that gets the user to upload the dossier. `[Inference]`

### Gaps

- No published Philippine estimate of what share of **online** senior/PWD discount claims are fraudulent; the Senate figures are about PWD discounts generally and are not an audited fraud rate.
- No public data distinguishing fake **senior** IDs from fake **PWD** IDs, or quantifying forged OSCA cards specifically.
- No Philippine data on insider collusion or override abuse in pharmacy discount programs; the ACFE evidence is global and cross-industry.
- No public evidence on organised **rental** of verified discount accounts (as opposed to sale of fake cards); this is inferred from the value of the credential and from account-takeover trends.
- Whether every LGU-issued senior/PWD card is a "public document" for falsification purposes in every issuance mode is a legal question; NCDA asserts it, but I found no appellate decision specific to LGU discount IDs.
- The rules on PWD **representative** purchases are less clear than the senior rules; the FDA advisory addresses seniors, and I did not find an equivalent PWD advisory.

---

## 2. Attack surface and controls at each stage of the flow

### Takeaway

The highest-leverage controls are at **capture** (in-app camera, both sides, no gallery, recapture detection), at **decision** (deterministic rules plus registry/QR where available, never an LLM verdict), and at **reuse** (expiry, step-up, revocation, handover). OCR/LLM should extract fields into a strict schema and nothing more. Every stage should assume the previous stage was fooled.

### Cited Findings

- NIST SP 800-63-4 adds controls for **injection attacks and forged media (e.g., deep fakes)**, requires anti-automation protections for enrollment, and recommends continuous evaluation metrics rather than a one-time certification — [NIST SP 800-63-4](https://pages.nist.gov/800-63-4/sp800-63.html). `[Standards body]`
- NIST SP 800-63B requires account recovery to repeat the necessary steps of identity proofing consistent with the initial level, and permits repeating only the verification portion if a biometric sample or copy of the original evidence was retained — [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html). `[Standards body]`
- The Bangko Sentral ng Pilipinas requires eKYC to meet the **same standards as face-to-face** identification and verification, with appropriate risk management; it recognises PhilSys-enabled eKYC, subject to PSA relying-party onboarding — [BSP Circular No. 1170](https://www.bsp.gov.ph/Regulations/Published%20Issuances/Images/Circular_1170.pdf). `[Law/regulator]`
- Entrust categorises injection attacks into **virtual cameras** (most common) and **network injection**, with presentation attacks treated as a separate category; it recommends defenses at the source (device/media signals), at presentation (dynamic capture), and at submission (backend detection) — [Entrust, 2025](https://www.entrust.com/sites/default/files/documentation/reports/2025-identity-fraud-report.pdf). `[Industry data]`
- OWASP recommends rendering each page and flagging text that is not visible, checking all hidden text rather than only text that "reads like an instruction", and measuring false positives on documents from the organisation's own domain because OCR errors and fine print cause them — [OWASP](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html). `[Standards body]`
- The FTC warns that QR codes can send users to spoofed sites that look real, install malware, or steal credentials; scammers create urgency and may place stickers over legitimate codes — [FTC, 2023](https://consumer.ftc.gov/consumer-alerts/2023/12/scammers-hide-harmful-links-qr-codes-steal-your-information). `[Law/regulator]`
- NCDA's unified digital PWD ID uses a unique QR code that merchants scan; the scan goes to a DSWD government portal to confirm the PWD is verified. Registration is still in-person at LGUs, and a physical card may be printed on request — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary]`
- Bacolod City began issuing QR-code-based senior and PWD IDs in February 2024; scanning the QR displays the cardholder's data — [Sunstar, 28 Nov 2024](https://www.sunstar.com.ph/bacolod/dssd-opens-probe-into-tax-leakages-caused-by-fake-pwd-ids). `[News/secondary]`
- NPC Circular No. 2023-03 prescribes minimum requirements and standards for the lawful collection, processing, and issuance of identification cards containing personal data; the NPC applied it to the unified PWD ID question in AO 2025-007 — [NPC Advisory Opinions index](https://privacy.gov.ph/pips-and-pics/advisory-opinions). `[Law/regulator]`
- The FDA lists the IDs that must be honoured for the senior medicine discount: an OSCA/mayor/barangay captain ID, a Philippine passport, or other government-issued documents showing age (driver's licence, voter's ID, SSS/GSIS ID, PRC card, postal ID) — [FDA Advisory No. 2024-0429](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`
- At least one Philippine online pharmacy already requires senior/PWD account registration with an ID number and front/back ID photo upload — [Rose Pharmacy senior and PWD registration](https://www.rosepharmacy.com/senior-and-pwd-account-registration). `[Industry practice]`
- The NPC's PhilHealth leak guidance includes a specific advisory on "heightened vigilance against counterfeit PhilHealth IDs", showing that a health-insurer breach was followed by counterfeit-ID risk — [NPC PhilHealthLeak page](https://privacy.gov.ph/npc-philhealth-leak/). `[Law/regulator]`

### Inferences

**Stage-by-stage control design.** "Basis" marks whether the control is supported by the cited evidence or is a design inference.

| Stage | Main threats | Controls | Basis |
|---|---|---|---|
| **Capture / upload** | Edited screenshots, printouts, screen recapture, gallery photos of someone else's card, mismatched front/back | Force in-app camera; disable gallery and file upload for the primary check; capture front and back in one session; ask the user to tilt the card to catch hologram/print changes; detect moiré, refresh banding, bezel edges, and glare; capture device model, OS, IP, and session; limit attempts per account/device; allow an assisted branch/phone path for users who cannot use the camera | Recapture and screen-photo evidence in [Entrust 2025](https://www.entrust.com/sites/default/files/documentation/reports/2025-identity-fraud-report.pdf); design inference for the rest |
| **OCR / extraction** | Prompt injection in visible or hidden text; field corruption; hallucinated fields; wrong template | Use OCR/VLM only to populate a strict schema; reject free text; never pass extracted text into a tool-calling or decision path; validate DOB → age ≥60, expiry, issuer-specific number patterns, name tokenisation; flag instruction-like or invisible text as a risk signal, not an instruction; require field-level confidence; store model version and raw extraction for audit with short retention | [OWASP](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html); design inference |
| **Verification decision** | Fake/cancelled ID; duplicate ID across accounts; identity mismatch; synthetic identity | Exact normalised ID-number hash check; fuzzy name + DOB + address; QR/registry lookup where the issuer supports it; allowlist government domains and verify signatures; risk score; step-up for medium/high risk; reason codes; no auto-deny on a single signal | NIST risk-based proofing [800-63-4](https://pages.nist.gov/800-63-4/sp800-63.html); [NCDA QR](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/); design inference |
| **Human review** | Rubber-stamping; inconsistency; bias; queue pressure; insider override | Blind double review for high-risk; rubric with accept/reject/insufficient; evidence checklist; adjudicator; QA sample of approvals and rejections; override and overturn tracking; reason codes; no speed quotas | Automation-bias evidence in §5; design inference |
| **Storage** | Breach; over-collection; insider browsing; vendor leak | Store the minimum fields; encrypt ID number; store a salted hash for dedupe; delete images after decision or a short retention window; role-based access; audit every view; watermark downloads; no bulk export; vendor DPA with deletion and breach-notification clauses | RA 10173 and NPC guidance in §6; design inference |
| **Reuse / autofill** | Reuse by a different person; expired ID; account takeover; address/device change | Show masked ID; reconfirm at checkout; block expired IDs; step-up on new device, address, or payment method; periodic re-verification; revoke on registry mismatch, death, ATO, chargeback, or customer request; one active account per ID unless a representative/household flag is set | [NIST 800-63B recovery](https://pages.nist.gov/800-63-4/sp800-63b.html); design inference |
| **Handover** | Borrowed/stolen physical card; rider collusion; wrong person receives | Physical ID check at pickup/delivery; match name, photo, and last four digits; rider checklist; if the ID is not presented, charge the difference under a disclosed policy or allow return; record outcome; feed the risk system; do not let riders confiscate or photograph IDs | FDA presentation requirement [FDA](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/); design inference |
| **Post-order** | Repeated abuse; refund/chargeback fraud; complaints | Weekly audit sample; complaint and appeal tracking; chargeback review; revocation workflow; monthly reconciliation of discounts against verified IDs; insider tip hotline | [ACFE](https://www.ivey.uwo.ca/media/kjljj5cy/2024-report-to-the-nations.pdf); design inference |

- **Capture is the cheapest place to win.** A gallery upload is the single biggest enabler of edited and borrowed IDs; an in-app camera with a tilt challenge removes most screenshot and printout attacks without any model. It also creates a cleaner evidentiary record for disputes. `[Inference]`
- **Do not let the LLM decide.** Prompt injection is not reliably fixable at the model layer; the only robust design is to keep the model's output in a data box and make the decision in deterministic code or a human queue. If the extracted text can set a status field, the system is injectable by design. `[Inference]`
- **Treat a QR pass as strong positive evidence, not a mandatory gate.** QR coverage is uneven across LGUs and issuance years; requiring a QR on every card would reject valid legacy IDs and violate the "presumption of regularity" approach NCDA describes. Use QR as a score boost and a revocation signal. `[Inference]`
- **Do not require the account holder's name to match the ID for senior medicine purchases.** The FDA expressly allows representatives. Instead, collect a **patient name** and relationship at checkout, and score mismatch rather than blocking it. `[Inference]`
- **Handover is the last real-person check.** If the marketplace removes it in the name of convenience, the saved ID becomes the only check and the fraud model collapses to "one successful upload = unlimited discounts". Keep a physical check at least for high-value or first-time saved-ID orders. `[Inference]`

### Gaps

- No published evaluation of in-app capture versus gallery upload for Philippine LGU IDs; the recapture evidence is generic.
- No public coverage map of which LGUs issue QR-verifiable senior/PWD IDs, nor a public API catalogue.
- No standard for LGU ID-number formats or check digits, so format validation is necessarily issuer-specific and brittle.
- No data on rider compliance with handover ID checks in Philippine pharmacy delivery.
- No public guidance on whether a marketplace may require a digital upload when the statute speaks of "presentation".

---

## 3. Detection and risk-scoring: what works, what it costs, and what evidence supports it

### Takeaway

Cheap deterministic checks — exact ID hash, expiry, name/DOB consistency, duplicate image hash, velocity — catch most repeatable abuse at near-zero marginal cost. Fuzzy matching and device/location signals add recall but generate false positives that fall disproportionately on legitimate families and elderly users. Buy registry/QR verification where it exists; treat vendor biometrics as a later, high-cost, privacy-heavy option rather than the default.

### Cited Findings

- NIST defines the **false match rate (FMR)** as the proportion of impostor comparisons at or above a threshold and the **false non-match rate (FNMR)** as the proportion of mated comparisons below it; the threshold trade-off is explicit and cannot be optimised away — [NIST FRTE 1:1 Verification](https://pages.nist.gov/frvt/html/frvt11.html). `[Standards body]`
- NIST's demographic testing found false-positive rates varying by factors of **10 to beyond 100** across demographic groups; false positives are higher in women, the elderly, and the young, and higher in Asian and African American faces relative to Caucasian faces — [NIST testimony](https://www.nist.gov/speech-testimony/facial-recognition-technology-part-iii-ensuring-commercial-transparency-accuracy); [NIST IR 8280](https://nvlpubs.nist.gov/nistpubs/ir/2019/nist.ir.8280.pdf). `[Peer-reviewed]`
- NIST's 1:N identification test measures the false-negative identification rate at a threshold that limits the false-positive identification rate to 0.003 — [Bipartisan Policy Center summary](https://bipartisanpolicy.org/article/frt-accuracy-performance). `[Secondary summary of NIST]`
- Fuzzy-matching guidance recommends standardising data first, using secondary identifiers (DOB, address, ID number) to confirm or discount a name match, combining several algorithms, segmenting thresholds by risk, and testing/tuning regularly; for fraud detection the priority is **high recall** (review extra records) rather than high precision — [DataLadder](https://dataladder.com/fuzzy-matching-101); [SymphonyAI](https://www.symphonyai.com/glossary/fuzzy-matching). `[Vendor claim / practitioner guidance]`
- A name-matching vendor notes that adding context such as date of birth and address distinguishes similar names, and that thresholds should be tuned to risk tolerance — [Babel Street](https://www.babelstreet.com/modules/match). `[Vendor claim]`
- TransUnion's global data shows account creation and login are the highest-risk lifecycle stages (8.3% and 4.3% suspected fraud respectively in H1 2025) — [TransUnion H2 2025](https://media.transunion.com/content/dam/transunion/roa/business/collateral/report/3611950-h2-25-fraud-trends-rpr.pdf). `[Industry data]`
- Entrust's 2026 data shows fraud attempts peaking between **2 and 4 a.m. UTC**, with attackers reusing identities, credentials, and patterns — consistent with coordinated networks rather than individuals — [Entrust 2026](https://www.entrust.com/resources/reports/identity-fraud-report). `[Industry data]`
- ACFE found that **proactive data analysis** was one of four controls associated with at least a **50% reduction** in fraud losses and faster detection — [Commonwealth Fraud Prevention Centre summary of ACFE 2024](https://www.counterfraud.gov.au/news/general-news/digest-occupational-fraud-report-2024). `[Industry data]`
- NCDA, citing a DOJ opinion, says there is a **presumption of regularity** when a PWD presents an ID: the discount should be given, and verification should happen only when there is doubt — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary, quoting regulator]`
- The FDA expressly permits representatives to buy senior medicines, so the same ID may legitimately appear on orders placed by different people — [FDA Advisory No. 2024-0429](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`
- PWD ID formats vary by LGU and there is no uniform design, which limits the value of format/checksum rules — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`

### Inferences

**A workable risk score** (points are illustrative; calibrate against local data):

| Signal | Points | Why |
|---|---:|---|
| Exact ID-number hash already on a **different** account, no representative flag | +40 | Strong duplicate signal; false positive if family/caregiver |
| Name similarity to account holder < 0.85 with different DOB | +25 | Possible borrowed/stolen ID |
| Perceptual image hash matches another account's upload | +30 | Reused photo or template |
| Device fingerprint shared across ≥3 accounts with different IDs | +20 | Multi-accounting / resale |
| Delivery address differs from ID address and from account history | +10 | Weak; caregivers and students move |
| >3 discounted orders in 7 days or >2× typical basket | +15 | Velocity; chronic patients may legitimately exceed |
| OCR confidence below threshold on name/DOB/ID number | +15 | Extraction risk, not fraud |
| Instruction-like or invisible text detected | +35 | Prompt-injection attempt or tampered document |
| QR/registry lookup fails or returns cancelled | +40 | Strong negative, but coverage gaps cause false alarms |
| QR/registry lookup passes | −30 | Strong positive |
| Account age < 7 days and first order is discounted | +10 | New-account risk |
| Payment instrument name differs from ID name | +10 | Weak; family cards common |

Suggested bands: **0–29 auto-approve**, **30–59 sample review (10–20%)**, **60+ hold for human review**, **90+ step-up and hold**. Never auto-deny solely on score; route to a human and an appeal. `[Inference]`

**Cheap versus expensive controls**

| Control | What it catches | Evidence strength | Relative cost | Notes |
|---|---|---|---|---|
| Exact ID-number hash duplicate | Same ID on multiple accounts | Strong for duplicates; not proof of fraud | Very low | Normalise and salt; keep encrypted ID for operations |
| Perceptual image hash | Reused/stolen photo | Moderate; edits evade | Low | pHash/dHash plus crop-resistant variants |
| Fuzzy name + DOB + address | Typos, borrowed IDs, synthetic identities | Moderate; high false positives on common names | Low | Review trigger, not block |
| Issuer format/checksum | Crude templates | Weak; formats vary; no national checksum | Low | Maintain issuer library; do not reject unknown formats |
| QR/registry lookup | Fake, cancelled, or expired IDs | Strong where available; coverage gaps | Medium | Allowlist domains; verify signatures; fallback to human |
| Device fingerprint / cluster | Multi-accounting, ATO | Moderate; privacy-sensitive | Medium | Coarse signals; DPIA |
| Geo/address mismatch | Stolen IDs, resale | Weak–moderate | Low | Do not over-weight; caregivers |
| Velocity / value limits | Bulk abuse | Moderate | Low | Value-based; avoid denying chronic patients |
| Order-recipient cross-check | Borrowed ID | Weak alone; legitimate representatives | Low | Collect patient name and relationship |
| Handover physical check | Borrowed/stolen/fake physical cards | Strong for possession; not skilled fakes | Low marginal (already sunk) | Train; checklist; escalation |
| Human review | Complex cases | Moderate if independent; weak if rubber-stamp | Medium | Blind double review; QA |
| Vendor eKYC (ID + selfie + liveness) | Impersonation, deepfakes | Moderate–strong but bypassable; overkill | High | Demand injection detection and independent red-team testing |
| Biometric duplicate (face) | Same person, multiple accounts | Strong technically; legal/privacy heavy | High | NPC scrutiny; avoid for a discount |
| Red team | Unknown gaps | Strong for known attack classes | Medium | Quarterly; prompt-injection corpus |
| Audit sampling | Unknown fraud rate | Strong statistically if sized | Low–medium | 100–300 cases/month |
| Tip hotline | Insider fraud | Strong (ACFE 43% via tips) | Low | Independent; anti-retaliation |

- **The 1:N fuzzy-matching trap.** If you compare every new ID against 100,000 existing records at a per-comparison false-match rate of 0.1%, you expect about **100 false hits**; at 0.01%, about 10. Fuzzy matching must be blocked by exact keys (DOB, name token, address) and thresholded, or the review queue will drown. This is the NIST 1:N FPIR problem in miniature. `[Inference from NIST FRTE]`
- **Do not use a hard weekly cap on medicine discounts.** RA 9994 has a weekly cap for basic necessities and prime commodities (P125/week) but no equivalent cap on the 20% medicine discount; chronic patients may legitimately order often. Use value/velocity anomalies and human review, not a blanket cap — [Rappler](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[Inference from that description]`
- **Registry/QR is the best single control where it exists.** A signed QR or a lookup against an issuer registry is much harder to fake than a printed card. But coverage is uneven, registration is still in-person, and private real-time APIs are not yet general. Design the flow to work with and without it. `[Inference]`
- **Device and location signals are supporting evidence, not verdicts.** They are useful for detecting account farms and resale, but a daughter ordering medicine to her own address for her father will look "wrong" on address and device. Use them to prioritise review, not to deny. `[Inference]`

### Gaps

- No public accuracy or coverage data for LGU QR/registry verification; no published API catalogue.
- No Philippine study on device-fingerprint efficacy in pharmacy or e-commerce discount fraud.
- No labelled dataset of fake versus genuine Philippine senior/PWD IDs; all thresholds would be local calibration.
- No public pricing for registry lookups or per-LGU verification.
- No evidence on whether ID-number reuse is common for legitimate reasons beyond representative purchases (e.g., siblings sharing a parent's card).

---

## 4. Metrics and evaluation without a large labelled dataset

### Takeaway

Measure the business outcome (discount leakage in pesos) and the two error types separately; never report a single "accuracy". With no labels, run a small stratified audit, seed known fakes through red teaming, and use the rule of three to state what the sample can and cannot prove. A 300-case audit with zero errors proves only that the rate is probably below 1%, not below 0.1%.

### Cited Findings

- NIST's FRTE defines FMR and FNMR at a threshold; the choice of threshold determines the trade-off, so a single "accuracy" number is meaningless without the operating point — [NIST FRTE 1:1](https://pages.nist.gov/frvt/html/frvt11.html). `[Standards body]`
- The **rule of three**: if an event is not observed in *n* independent trials, the 95% upper bound on its probability is approximately **3/n**; 300 trials with zero events gives a 95% upper bound of about 1% — [Wikipedia summary of Jovanović & Levy](https://en.wikipedia.org/wiki/Rule_of_three_(statistics)); [Quality Digest](https://www.qualitydigest.com/inside/statistics-column/rules-three-and-five-082317.html). `[Peer-reviewed / practitioner]`
- Entrust's data shows fraud attempts peaking between 2 and 4 a.m. UTC, so time-of-day is a real operational variable and review staffing should not assume business-hours fraud — [Entrust 2026](https://www.entrust.com/resources/reports/identity-fraud-report). `[Industry data]`
- ACFE's median fraud duration is 12 months, which means annual or quarterly measurement will miss slow-building abuse; monthly leading indicators matter — [ACFE 2024](https://www.ivey.uwo.ca/media/kjljj5cy/2024-report-to-the-nations.pdf). `[Industry data]`
- HDSR's 2026 study found that annotators with favourable attitudes toward AI had **lower accuracy and fewer corrections**, and a higher **undercorrection** rate, even after controls; bonus payments and early AI accuracy did not meaningfully improve performance — [HDSR 8.2, Spring 2026](https://hdsr.mitpress.mit.edu/pub/nrcn4h7d). `[Peer-reviewed]`
- A 2025 NBER study found people reduced effort when presented with confident AI predictions, suggesting uncertain cases should be routed to humans rather than relying on automation alone — [Ethos Risk summary](https://ethosrisk.com/blog/when-ai-becomes-the-default-automation-bias-in-claims). `[Secondary summary of NBER]`

### Inferences

**Metric definitions and targets** (targets are proposed, not benchmarks):

| Metric | Definition | Why it matters | Proposed target |
|---|---|---|---|
| **Discount leakage rate** | Invalid discount pesos ÷ total discount pesos | The business outcome; ties fraud to money | <0.5% of discount spend; investigate any month-over-month rise >20% |
| **False accept rate (technical)** | Accepted invalid IDs ÷ all invalid IDs presented | Requires labels; measures the gate | <5% at the chosen threshold |
| **False accept share (precision)** | Invalid IDs ÷ all accepted IDs | What the customer sees; base-rate sensitive | <0.5% |
| **False reject rate (FRR)** | Rejected valid IDs ÷ all valid IDs presented | The human cost; especially elderly/PWD | <2%; appeal overturn <10% |
| **OCR critical-field accuracy** | Exact match on name, DOB, ID number, expiry | Determines automation | >98% on the local golden set; per-field, not per-document |
| **Automation rate** | Orders decided without human touch ÷ all discounted orders | Efficiency | 60–80% after phase 2 |
| **Human review rate** | Orders held for review ÷ all discounted orders | Cost and friction | 10–25% initially; <10% at maturity |
| **Attack success rate** | Confirmed fraudulent discounted orders ÷ attempted fraudulent orders | Red-team and confirmed cases | <1% for known attack classes; track by class |
| **Cost per verified user** | Total verification cost (vendor + reviewer + handover + support) ÷ verified users | Budget | Measure; target downward trend |
| **Time to verify** | Median and p95 from upload to decision | Customer experience | p50 <1 min auto; p95 <24 h review |
| **Appeal rate** | Appeals ÷ adverse decisions | Fairness signal | <2%; overturn <10% |
| **Reviewer agreement** | Cohen's kappa on a blinded gold set | Whether the rubric is real | >0.7 |
| **Handover failure rate** | Orders where physical ID not presented or mismatched ÷ checked orders | Last-mile control | <3% |
| **Repeat-use concentration** | Share of discount value from top 1% of ID numbers | Network abuse | Monitor; investigate outliers |
| **Expiry compliance** | Saved IDs used past expiry ÷ all saved-ID orders | Hygiene | 0% |

**How to estimate without labels**

1. **Stratified random audit.** Each week, sample 100 approved discounted orders, stratified by risk band and issuer, and re-verify them by QR/registry, OSCA/PDAO call, or a callback to the customer. This yields a labelled sample. At 5% fraud prevalence, n=100 gives a 95% CI of roughly ±4.3 percentage points; n=300 gives ±2.5 points; to estimate 0.5% with ±0.2 points you need roughly 4,800 cases. Do not pretend a 100-case audit can find a 0.1% problem. `[Inference from binomial sampling]`
2. **Rule of three for "no errors found".** If 300 audit cases produce zero confirmed fraud, the 95% upper bound is about 1% (3/300). That is a useful statement; "zero fraud" is not. `[Inference from rule of three]`
3. **Red-team seeding.** Insert synthetic IDs with known labels into a test queue or, with ethics/legal approval, into production at low volume; measure detection by attack class (template, screenshot, borrowed, prompt injection, fake QR). This is the only way to measure **attack success rate** before real losses accumulate. `[Inference]`
4. **Shadow mode.** Run new checks silently for 4–8 weeks, record what they would have decided, and compare with the existing process before enforcing. This avoids a launch-day false-reject crisis. `[Inference]`
5. **Capture-recapture.** Have two independent reviewers assess the same 100–200 cases; use the overlap to estimate missed fraud (Lincoln–Petersen). The assumptions (independence, equal catchability) are strong, so treat the result as an order-of-magnitude check, not a precise rate. `[Inference]`
6. **Proxy labels.** Use handover failures, chargebacks, customer complaints, OSCA/DSWD advisories, and "confirmed valid after appeal" as labels when full verification is unavailable. Track them separately from adjudicated fraud. `[Inference]`
7. **A/B step-up.** Randomise medium-risk orders to step-up versus no step-up; measure abandonment, completion, and confirmed fraud. This directly estimates the friction/fraud trade-off. `[Inference]`

**Base-rate arithmetic every product manager should see.** Suppose 2% of applicants are fraudulent, the detector catches 80% of fraud, and it falsely flags 2% of legitimate users. Of 10,000 applicants: 200 fraudulent, 160 caught, 40 pass; 9,800 legitimate, 196 falsely rejected, 9,604 accepted. The share of accepted applicants who are fraudulent is 40/9,644 ≈ **0.41%**, not 2%. Of those flagged, only 160/356 ≈ **45%** are actually fraudulent. A "2% false positive rate" sounds good but produces a review queue that is majority innocent. `[Inference]`

**A practical cost model** (illustrative assumptions, not quotes):

- Reviewer fully loaded at P25,000–P35,000/month over ~160 productive hours = **P156–P219/hour**; 3–5 minutes per case = **P8–P18 per review**. At 3,000 reviews/month, **P24,000–P54,000/month**.
- Vendor eKYC is usually priced per check; at an illustrative P20–P80/check, 10,000 checks/month is **P200,000–P800,000/month**, which is hard to justify against a 20% discount on typical pharmacy baskets. Obtain quotes; do not assume.
- A 100-case monthly audit at 5 minutes/case is about **8 reviewer-hours/month** — cheap enough to run from day one.
- If monthly discounted GMV is P10 million and 1% is invalid, leakage is **P100,000/month (P1.2 million/year)**. A control package costing P50,000/month is justified if it halves leakage. `[Inference]`

**Dashboard cadence.** Daily: review queue age, step-up completion, handover failures. Weekly: leakage sample, duplicate rate, false-reject complaints, appeal backlog. Monthly: cost per verified user, reviewer kappa, attack-class detection, revocation count. Quarterly: red-team results, policy review, vendor performance. `[Inference]`

### Gaps

- No Philippine benchmark for OCR field accuracy on LGU senior/PWD IDs.
- No public benchmark for cost per verified user in Philippine pharmacy or e-commerce.
- No agreed industry definition of "discount fraud" (invalid ID vs ineligible user vs overstated basket vs collusion), which makes cross-company comparison unsafe.
- No published base rate for online discount fraud in the Philippines; the arithmetic above is illustrative only.
- No evidence on how much audit sampling is politically acceptable to senior/PWD groups; sampling real customers raises consent and dignity issues.

---

## 5. Human review: design, failure modes, appeals, and fairness

### Takeaway

Human review is not a control unless it is independent, blind where possible, rubric-driven, and measured against a gold set. Otherwise it rubber-stamps the OCR or risk score and adds cost without reducing fraud. For this population, review design must also protect dignity: assisted channels, no hard denial on fuzzy evidence, fast appeals, and no biometrics-by-default for elderly or disabled users.

### Cited Findings

- A systematic review by Goddard, Roudsari, and Wyatt found automation bias is mediated by **workload, task complexity, and time constraints**, and mitigated by **training and emphasising user accountability**, plus design choices such as the position of advice on screen and whether the system gives information versus a recommendation — [Thematic summary of the JAMIA review](https://getthematic.com/insights/human-review-ai-insights-automation-bias). `[Peer-reviewed, secondary summary]`
- A 2025 NBER study found people reduced effort when presented with **confident AI predictions**; the researchers recommended routing uncertain cases to humans rather than relying on automation alone — [Ethos Risk summary](https://ethosrisk.com/blog/when-ai-becomes-the-default-automation-bias-in-claims). `[Secondary summary of NBER]`
- HDSR's 2026 study found that annotators favourable toward AI had lower accuracy, fewer corrections, and higher undercorrection, and that bonus payments did not fix it — [HDSR 8.2](https://hdsr.mitpress.mit.edu/pub/nrcn4h7d). `[Peer-reviewed]`
- ACFE found **43% of frauds are detected by tips**, and organisations with hotlines/proactive data analysis had lower losses and faster detection; the median fraud ran 12 months before detection — [ACFE 2024](https://www.ivey.uwo.ca/media/kjljj5cy/2024-report-to-the-nations.pdf). `[Industry data]`
- Restaurant staff in the Philippines reported being subjected to "threats and intimidation" when they rejected fake or unverifiable PWD IDs, including "I know the mayor" and "you don't have the right to verify" — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`
- NIST's demographic work shows false positives are higher for women, the elderly, and the young, which is exactly the population a senior/PWD discount flow serves — [NIST testimony](https://www.nist.gov/speech-testimony/facial-recognition-technology-part-iii-ensuring-commercial-transparency-accuracy). `[Standards body]`
- NCDA, citing DOJ, says there is a **presumption of regularity** and verification should occur only when there is doubt — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary, quoting regulator]`
- The Internet Transactions Act requires an online marketplace to maintain an internal redress mechanism; a complaint is deemed exhausted if unresolved after **seven calendar days** — [One Asia Lawyers summary of RA 11967](https://oneasia.legal/en/5367); [RA 11967 text](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html). `[Law]`
- Refusing to honour a valid senior card carries a first-offence penalty of **2–6 years' imprisonment and a P50,000–P100,000 fine** under RA 9994 — [RA 9994, Sec. 10](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html). `[Law]`

### Inferences

**Reviewer guidance (rubric).** Every case should end in one of four codes, not a free-text opinion:
- **Accept** — ID type is one the law recognises; name/DOB/ID number are internally consistent; expiry valid; no unresolved duplicate or tamper signal; registry/QR pass if available.
- **Accept with representative flag** — account holder differs from ID holder but patient name and relationship are declared and plausible; discount is for the ID holder's medicine.
- **Insufficient evidence** — image unreadable, field missing, issuer not in library, registry unavailable; ask for a recapture or use an assisted channel; do not accuse.
- **Refer for investigation** — confirmed duplicate on another account without representative explanation, tampered image, instruction-like text, registry says cancelled, or insider tip.

**Blind double review.** For high-risk cases (score ≥60, confirmed duplicates, suspected tampering, high-value orders), have two reviewers assess independently before seeing each other's decision. An adjudicator resolves disagreements. Track Cohen's kappa monthly; if it falls below ~0.7, the rubric or training is broken, not the reviewers. `[Inference]`

**Queue prioritisation.** Order the queue by: confirmed duplicate on another account; registry/QR negative; tamper/injection signal; value; new account; device cluster; repeat failed uploads; customer complaint. Do not order by demographic attributes. Set a 24-hour SLA for high-risk and 72 hours for routine. `[Inference]`

**Handling disagreements.** Record both decisions and the evidence each reviewer relied on; adjudicate with a written reason; feed the case into rubric calibration. A high disagreement rate is a signal to simplify the rubric and add examples, not to punish reviewers. `[Inference]`

**Fraud feedback loop.** Confirmed fraud should generate a structured label (attack class, issuer, signal, outcome) that goes to the rules team, not just a case note. Review the top 20 confirmed cases monthly and ask which control would have caught them earlier. Share only aggregated patterns externally; do not publish names. `[Inference]`

**Appeals.** One-click appeal from the order status; a human who was not the original reviewer; decision within seven days to align with RA 11967's redress expectation; restore the discount and apologise if the appeal succeeds; track overturn rate by reviewer and by issuer. `[Inference]`

**Avoiding discrimination against elderly/PWD users.**
- Do not use age or disability as a risk factor; use behaviour and document consistency.
- Offer a phone/branch assisted path for users who cannot use the in-app camera; do not make smartphone ownership a condition of the discount.
- Do not require selfie/liveness for a 20% discount; the privacy and failure costs fall on the same group NIST shows is more likely to be falsely rejected.
- Use large type, plain language, Filipino/English, and step-by-step photo tips.
- Never display "suspected fraud" to the customer; use "we need a clearer photo" or "we're checking with the issuer".
- Allow a representative to complete verification with the senior's consent, consistent with the FDA's representative rule. `[Inference]`

**Common reviewer failure modes.**
- **Rubber-stamping** the OCR/risk recommendation, especially when the model is confident (automation bias).
- **Anchoring** on the first field or the customer's explanation.
- **Inconsistency** across shifts and reviewers; the same ID type accepted by one and rejected by another.
- **Format bias**: rejecting a valid but unfamiliar LGU design because it "looks different" — the Resto PH data shows format variation was a rejection reason, which may include false rejects.
- **Fatigue and queue pressure**: accuracy falls with workload and time constraints.
- **Demographic bias**: harsher scrutiny of elderly, disabled, low-income, or unfamiliar-name customers.
- **Over-escalation**: sending everything to investigation to avoid accountability, which clogs the queue and delays legitimate customers.
- **Under-escalation**: closing obvious duplicates as "family" without checking the patient name.
- **Retaliation risk**: frontline staff facing threats; reviewers need a safety escalation path and should not be alone at a counter with an angry customer.
- **Groupthink**: three reviewers in a room converge on the loudest opinion; use independent written decisions first. `[Inference]`

**Staffing model.** Start with one senior reviewer plus one backup, not a large team. The senior reviewer owns the rubric, calibration, and weekly audit. Add capacity only when the review rate exceeds ~25% of orders or the SLA slips. Use a separate investigator for confirmed-fraud cases so the reviewer is not judge and prosecutor. `[Inference]`

### Gaps

- No published study of ID-review accuracy in a pharmacy or discount context; the automation-bias literature is generic.
- No data on reviewer throughput or accuracy for Philippine LGU IDs.
- No Philippine guidance on appeal rights for automated discount decisions.
- No evidence on the prevalence of threats against pharmacy staff in the Philippines; the restaurant anecdote is not a rate.
- No standard for "representative" verification for PWD purchases equivalent to the senior FDA advisory.

---

## 6. Privacy and security: breach impact, minimisation, retention, and Philippine notification duties

### Takeaway

The saved-ID database is a compact identity dossier — name, date of birth, ID number, address, photo — and a breach harms customers long after the discount ends. Minimise fields, delete images, encrypt and log access, and treat breach notification as a 72-hour operational capability. Biometrics are the one data type you cannot rotate; do not collect them for a discount program without a compelling, counsel-reviewed reason.

### Cited Findings

- RA 10173 penalises unauthorised processing of personal information with **1–3 years' imprisonment and a P500,000–P2,000,000 fine**; for sensitive personal information, **3–6 years and P500,000–P4,000,000** — [RA 10173, Sec. 25](https://privacy.gov.ph/data-privacy-act). `[Law]`
- The DPA's implementing rules require notification to the NPC and affected data subjects within **72 hours** of knowledge or reasonable belief of a breach requiring notification, when sensitive personal information or identity-fraud-enabling data is reasonably believed to have been acquired and there is a real risk of serious harm — [NPC Advisory Opinion No. 2020-038, quoting IRR Sec. 38](https://privacy.gov.ph/wp-content/uploads/2022/01/Redacted-Advisory-Opinion-No.-2020-038.pdf). `[Law/regulator]`
- NPC Circular No. 16-03 governs personal data breach management. NPC Advisory No. 2026-02 clarifies that filing a request for postponement, exemption, or alternative notification does **not** pause the obligation; the full breach report is due within **five days** of discovery, and NPC silence is not approval — [Baker McKenzie, 4 Jun 2026](https://www.bakermckenzie.com/en/insight/publications/2026/06/philippines-data-breach-notification-procedure-further-clarified); [NPC advisories](https://privacy.gov.ph/pips-and-pics/advisories-circulars/). `[Law/regulator, secondary summary]`
- The 2023 PhilHealth ransomware attack exfiltrated about **750 GB**, with the NPC extracting **734 GB** of personal and sensitive personal information; reporting says **13 million members** were affected, including **8.5 million senior citizens**, while lawmakers later cited **42 million** possibly affected — [PMC review](https://pmc.ncbi.nlm.nih.gov/articles/PMC11975704); [The Record](https://therecord.media/philippine-lawmakers-want-answers-data-breach); [NPC statement](https://privacy.gov.ph/npc-takes-firm-stand-unwavering-commitment-to-protect-data-privacy-rights-in-wake-of-philhealth-data-breach). The 13M/42M figures conflict and should be labelled as estimates. `[News/secondary, regulator]`
- PhilHealth reportedly allowed its antivirus licence to expire in April 2023, five months before the September 2023 attack — [HIPAA Journal](https://www.hipaajournal.com/lack-of-antivirus-software-behind-philhealth-ransomware-attack). `[News/secondary]`
- In November 2026, **10 Philippine government agencies** filed breach notifications with the NPC after a series of incidents; exposed data categories included personally identifiable information, **government IDs**, taxpayer and corporate registration details, and payroll/HR records. The NPC reiterated that outsourcing IT or data processing does not relieve the controller of liability under Section 21 of the DPA — [Rappler, 11 Nov 2026](https://www.rappler.com/technology/npc-sounds-alarm-government-agencies-report-leaks-november-2026/); [Philstar, 12 Nov 2026](https://www.philstar.com/headlines/2026/11/12/2569565/npc-confirms-data-breach-10-government-agencies). `[News/secondary]`
- The NPC's cease-and-desist order against Tools for Humanity found biometric data uniquely dangerous because it cannot be reset, and that a single breach could cause lifelong identity-fraud risk — [JDSupra](https://www.jdsupra.com/legalnews/philippines-orders-halt-to-biometric-8231406). `[Law/regulator, secondary summary]`
- The NPC's advisory opinion on the unified PWD ID addresses whether the 2023 ID-card circular applies, whether biometric processing is governed, and whether DSWD may process fingerprints and facial scans to verify benefit claimants — [NPC AO 2025-007](https://privacy.gov.ph/wp-content/uploads/2025/09/NPC-Advisory-Opinion-No.-2025-007_Redacted.pdf). `[Law/regulator]`
- The NPC has also issued an opinion on the processing of QR codes for KYC by third-party service providers (AO 2025-015), and an opinion on disclosure of individuals' names in connection with registration and use of fake national identification cards (AO 2024-014) — [NPC Advisory Opinions index](https://privacy.gov.ph/pips-and-pics/advisory-opinions). `[Law/regulator]`
- The DPA creates a private right of action for damages, in addition to criminal penalties — [IAPP summary](https://iapp.org/news/a/summary-philippines-data-protection-act-and-implementing-regulations). `[Law, secondary summary]`
- NIST notes that federation and reuse can reduce data collection and storage burdens; the privacy benefit depends on what the relying party retains — [NIST SP 800-63-3](https://pages.nist.gov/800-63-3/sp800-63-3.html). `[Standards body]`

### Inferences

**Data inventory and retention** (proposed; counsel and DPO should confirm):

| Field | Purpose | Retention | Access | Protection |
|---|---|---|---|---|
| Full name | Match to order/patient | Life of account + 2 years | Support (masked), fraud team (full) | Encrypted at rest |
| Date of birth | Age eligibility; disambiguation | Life of account + 2 years | Same | Encrypted |
| ID type and issuer | Eligibility; format rules | Life of account + 2 years | Same | Encrypted |
| ID number | Dedupe and registry lookup | Life of account + 2 years | Fraud team only; support sees last 4 | Field-level encryption + salted hash for dedupe |
| Address | Risk signal; delivery | Life of account + 1 year | Support | Encrypted |
| ID images (front/back) | Verification evidence | **30–90 days**, then delete; longer only on legal hold | Fraud team only | Encrypted object storage; signed URLs; watermark; access log |
| Verification decision, reason code, reviewer | Audit and appeals | 2–5 years | Audit/fraud | Immutable log |
| Device/IP/session | Fraud signals | 90–180 days | Fraud team | Pseudonymised where possible |
| Biometrics (if ever) | Not recommended | Do not collect | — | — |

- **Delete the image, keep the facts.** The image is the highest-risk artefact (face, signature, address, ID number); the extracted fields plus a hash are enough for most re-verification and audit. If an image must be kept for a dispute, keep it encrypted, access-logged, and time-limited. `[Inference]`
- **A breach of this database is worse than a discount loss.** Name + DOB + ID number + address is enough to order a fake ID, open synthetic accounts, or phish convincingly; unlike a password, an ID number cannot be rotated. Treat it as sensitive personal information and design accordingly. `[Inference]`
- **Breach notification is an operational capability.** Maintain a 72-hour clock, a pre-approved notification template, a named incident lead, an NPC DBNMS account, and a customer-communication plan. Do not wait for the full forensic report to start the clock. `[Inference from NPC rules]`
- **Vendor contracts are part of the control set.** Require deletion on termination, breach notification without delay, subprocessor disclosure, audit rights, data residency, and a prohibition on using customer ID images to train models. The NPC's 2026 statement that outsourcing does not relieve the controller is the relevant principle. `[Inference]`
- **Access controls.** Role-based access; support sees masked ID numbers and no images by default; fraud team can unmask with a reason; break-glass access requires a second approver and is reviewed monthly; no bulk export; DLP on downloads; watermarked images. `[Inference]`
- **Audit logs.** Log every view, export, edit, and deletion with actor, timestamp, purpose, and case ID; retain logs at least as long as the underlying record; review anomalies monthly. `[Inference]`
- **Privacy notice.** Explain what is collected, why, who sees it, how long it is kept, that it may be checked with the issuer, and how to appeal or request deletion; provide a DPO contact. `[Inference]`
- **Do not collect biometrics for this use case.** The discount is low-value, the population is vulnerable, the NPC has shown willingness to halt biometric processing, and a face template cannot be reset. If a future fraud case justifies biometrics, use a vendor with injection detection, store only a match result, and obtain explicit, counsel-reviewed consent. `[Inference]`

### Gaps

- No specific NPC retention period for discount-ID records; the 30–90-day image retention is a proposal, not a legal requirement.
- Whether an ID image is always "sensitive personal information" under the DPA depends on content (it includes a photo, DOB, ID number, address); counsel should confirm.
- No public NPC fine schedule specific to ID-verification data; penalties are criminal ranges plus administrative sanctions.
- No public data on breach costs for Philippine e-commerce; the PhilHealth case is a government outlier.
- No guidance on cross-border storage of Philippine ID images by a foreign eKYC vendor; a transfer impact assessment is prudent.

---

## 7. Legal and platform consequences: what is criminal, what is civil, and what needs counsel

### Takeaway

For the customer, fake IDs are a criminal exposure (falsification, estafa, DPA, cybercrime). For the marketplace, the bigger legal risk is **refusing a valid discount** under RA 9994, mishandling ID data under the DPA, and platform/consumer-redress liability under RA 11967 — not a direct "discount fraud" statute. The penalty asymmetry (2–6 years for refusal versus at least six months for abuse) argues for grant-provisionally-then-audit, not deny-and-litigate.

### Cited Findings

- RA 9994, Sec. 10, as amended: refusing to honour a senior citizen card or violating the Act is punishable on first offence by **2–6 years' imprisonment and a P50,000–P100,000 fine**; on subsequent offence by **2–6 years and P100,000–P200,000**; and any person who **abuses the privileges** is punishable by **not less than six months' imprisonment and a P50,000–P100,000 fine** — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html). A secondary summary puts the abuse range at six months to two years; the statutory excerpt is the safer citation. `[Law]`
- The FDA advisory confirms that seniors **or their representatives** may avail the discount and lists the accepted IDs, including passports and other government documents — [FDA Advisory No. 2024-0429](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`
- Falsification by a private individual under Article 172 of the Revised Penal Code carries **prision correccional in its medium and maximum periods** (roughly six months and one day to six years) and a fine of up to **P1,000,000** under the current text; older jurisprudence cites a P5,000 fine, so verify the current amended text before relying on the amount — [Legal Resource PH](https://legalresource.ph/falsification-by-private-individual-a172-revised-penal-code); [G.R. No. 179448](https://lawphil.net/judjuris/juri2013/jun2013/gr_179448_2013.html). `[Law]`
- The Supreme Court has upheld conviction for the complex crime of **estafa through falsification of a commercial document** where a falsified document was used to defraud a bank — [G.R. No. 179448](https://lawphil.net/judjuris/juri2013/jun2013/gr_179448_2013.html). `[Law]`
- NCDA states that producing, distributing, or using fake PWD IDs may constitute falsification of public documents, violations of the Data Privacy Act, and perjury, with corresponding fines and/or imprisonment — [NCDA, 11 Feb 2025](https://ncda.gov.ph/2025/04/10/statement-of-condemnation-against-the-proliferation-of-fake-persons-with-disability-identification-cards-pwd-ids/). `[Law/regulator]`
- The Valenzuela fake-ID case was charged as falsification of documents under the Revised Penal Code in relation to the Cybercrime Prevention Act of 2012 — [Inquirer, 10 Sep 2025](https://newsinfo.inquirer.net/2107461/couple-nabbed-for-mass-production-selling-fake-govt-ids-in-valenzuela). `[News/secondary]`
- RA 11967 (Internet Transactions Act of 2023) makes the **e-retailer or online merchant primarily liable** for indemnifying consumers; the **e-marketplace is subsidiarily liable** in civil or administrative actions, and **solidarily liable** if it fails, after notice, to act expeditiously to remove or disable access to goods or services that are prohibited by law, imminently injurious, unsafe, or dangerous — [RA 11967, Secs. 25–27](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html); [One Asia summary](https://oneasia.legal/en/5367). `[Law]`
- RA 11967 also requires e-marketplaces to disclose merchant identity and to maintain an internal redress mechanism; a complaint is deemed exhausted after seven days if unresolved — [One Asia](https://oneasia.legal/en/5367); [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html). `[Law]`
- The Consumer Act (RA 7394) supplies additional consumer remedies — [One Asia](https://oneasia.legal/en/5367). `[Law]`
- The DPA penalties are set out in RA 10173, Sec. 25 (see §6), and the NPC has issued an opinion on disclosing the names of individuals connected to fake national IDs — [NPC Advisory Opinions index](https://privacy.gov.ph/pips-and-pics/advisory-opinions). `[Law/regulator]`

### Inferences

**Liability map**

| Actor | Main exposure | Trigger | Notes |
|---|---|---|---|
| Customer | Falsification (Art. 172), estafa (Art. 315), DPA, cybercrime | Using a fake/borrowed ID to obtain a discount | Criminal; prosecution is discretionary and resource-constrained |
| Seller/pharmacy | Primary liability to consumer under RA 11967; tax/allowance disallowance; RA 9994 refusal | Wrongful denial or invalid discount claim | Seller bears the commercial loss of a fake discount |
| Marketplace | DPA controller/processor exposure; RA 11967 subsidiary/solidary liability; consumer redress; reputational | Designing the verification flow, storing IDs, failing to act on reports | The marketplace owns the data and the user experience |
| Reviewer/rider | Employment/contractual; possible civil liability for gross negligence | Wrongful denial, privacy breach, confiscating an ID | Train and indemnify; do not make them the final judge |

- **The refusal risk is asymmetric.** A false accept costs 20% of one basket; a false reject can expose the establishment to a criminal complaint carrying 2–6 years' imprisonment. Even if prosecution is unlikely, the complaint risk, regulatory scrutiny, and reputational damage dominate. Default to granting the discount when the card looks valid, then audit. `[Inference]`
- **Do not auto-deny on a fuzzy match.** A name mismatch is common in Philippine naming conventions (middle names, married names, nicknames, "Jr./III"). Denial on a fuzzy score is both a fraud-control error and a legal/consumer risk. Route to review, grant provisionally, and recover only on confirmed fraud. `[Inference]`
- **Contractual allocation matters.** The marketplace should specify in merchant terms who bears invalid-discount losses, who is responsible for verification, and how appeals are handled. Without this, the platform may end up absorbing seller losses or vice versa. Counsel should draft. `[Inference]`
- **Tax treatment needs counsel.** Senior/PWD discounts are commonly treated as deductions/allowances; if the ID is invalid, the seller may face disallowance or assessment. I found no BIR ruling specific to online discount fraud, so this is a flag, not a conclusion. `[Inference]`
- **Do not publish a "fraudster list".** The NPC has opined on disclosure of names connected to fake national IDs; defamation and DPA risk are real. Use private case management and, where appropriate, report to the PNP/NBI. `[Inference]`
- **PWD legal exposure is less clear than senior exposure.** RA 10754/RA 9442 provide the discount, but I did not find an equivalent, clearly worded abuse/refusal penalty provision in the sources reviewed. Counsel should confirm before designing PWD-specific denial rules. `[Inference]`
- **Reputational risk cuts both ways.** A platform seen to enable fake IDs faces regulator and media scrutiny; a platform seen to harass seniors faces consumer backlash and NCDA/CHR attention. Transparency about the process, a real appeal, and assisted channels are reputational controls, not just fairness gestures. `[Inference]`

### Gaps

- No Philippine case law on marketplace liability for **discount** fraud specifically; RA 11967 is about e-commerce transactions generally.
- No BIR ruling on whether invalid senior/PWD discounts are disallowed, and at what penalty.
- No clear statutory penalty for **PWD** discount abuse equivalent to RA 9994's abuse clause.
- Whether an online "upload" satisfies the RA 9994 requirement to "present" an ID at purchase is untested; counsel should advise.
- Whether the marketplace is a personal information controller or processor for seller-fulfilled orders, and how joint controllership works with pharmacies, needs a data-sharing agreement.
- No guidance on whether a platform can require a digital ID copy when the customer prefers in-person presentation.

---

## 8. Case studies and incidents: concrete lessons

### Takeaway

Local cases show factories, online marketplaces, family-scale borrowing, and weak registry integration; global cases show cheap deepfakes and camera injection can bypass liveness, and prompt injection is a document-layer risk. The lesson is layered controls and registry checks, not a single "AI detector".

### Cited Findings

**Philippines**

- **Valenzuela fake-ID factory (September 2025).** Police seized 300+ fake government IDs and forged PhilHealth records; IDs sold for P40–P1,000 on social media; charges of falsification in relation to the Cybercrime Prevention Act; the city responded with VAL-ID real-time verification and new security seals — [Inquirer](https://newsinfo.inquirer.net/2107461/couple-nabbed-for-mass-production-selling-fake-govt-ids-in-valenzuela). **Lesson:** the attack is a printing-and-distribution business, not a deepfake lab; real-time registry checks and physical security features raise cost, but only where the LGU has them.
- **Resto PH ID audit (June–August 2024).** 726 of 4,212 PWD IDs rejected (17.24%) for wrong numbers/codes, missing details, photocopies, and format differences; staff reported threats and intimidation — [Rappler](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). **Lesson:** format inconsistency creates both fraud opportunity and false rejects; frontline staff need a rubric, a safe escalation path, and a second reviewer.
- **Senate tax-leakage hearings (December 2024).** Senate estimates of 8.5 million illegitimate PWDs, P166.1 billion of P208.4 billion in discounts to fake IDs, and P88.2 billion in lost taxes — [Rappler](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). **Lesson:** the political and tax framing is already established; a marketplace should expect scrutiny and should be able to explain its controls in plain language. The estimates are not audited; do not cite them as precise.
- **PhilHealth ransomware (September 2023).** ~750 GB exfiltrated, 734 GB of personal/sensitive data, 13 million members affected (8.5 million seniors), 42 million possibly affected per lawmakers; expired antivirus; NPC investigation; NPC later warned of counterfeit PhilHealth IDs — [PMC](https://pmc.ncbi.nlm.nih.gov/articles/PMC11975704); [The Record](https://therecord.media/philippine-lawmakers-want-answers-data-breach); [NPC PhilHealthLeak](https://privacy.gov.ph/npc-philhealth-leak/). **Lesson:** a breach of ID data immediately creates a counterfeit-ID market; storing less ID data reduces the downstream fraud, not just the breach cost.
- **NPC v. Tools for Humanity (September 2025).** Cease-and-desist for biometric processing; NPC found biometrics irrevocable and consent via compensation invalid — [JDSupra](https://www.jdsupra.com/legalnews/philippines-orders-halt-to-biometric-8231406). **Lesson:** do not add biometrics to a low-value discount flow without a strong legal basis and a privacy-by-design case.
- **10 government agencies breach notifications (November 2026).** Government IDs among exposed data; NPC reiterated controller accountability despite outsourcing — [Rappler](https://www.rappler.com/technology/npc-sounds-alarm-government-agencies-report-leaks-november-2026/). **Lesson:** government ID data is already circulating; assume some customers' IDs are known to attackers and rely on behavioural/registry signals, not secrecy of the ID format.

**Global eKYC and document fraud**

- **OnlyFake / AI-generated documents (2024–2025).** Services generate realistic passports and licences in minutes; researchers say they have passed automated KYC — [G2 Risk Solutions](https://g2risksolutions.com/blog/protect-your-business-from-ai-generated-fake-documents); [Sumsub](https://sumsub.com/blog/ai-fake-id-challenge-for-kyc). **Lesson:** document-only verification is a commodity check; the marginal attacker can buy the artefact.
- **iProov virtual-camera face-swap (2025).** A consumer face-swap app plus OBS/virtual camera bypassed liveness in a financial app; MITRE ATLAS case study; iProov claims a 300% rise in face-swap attacks since 2023 — [Biometric Update](https://www.biometricupdate.com/202512/iproov-shows-how-face-swapping-app-can-fool-liveness-detection-on-financial-apps). **Lesson:** if you add selfie verification, test for injection (virtual camera, emulator, network injection), not just presentation attacks.
- **TrendAI Veriff/IDScan bypass (2024).** Deepfake from a passport photo bypassed Veriff via an Android cloud service; IDScan accepted a spoofed image with 99 anti-spoofing confidence after a few tries — [TrendAI](https://www.trendaisecurity.com/en/resources-insights/deep-research/ai-vs-ai-deepfakes-and-ekyc). **Lesson:** vendor "liveness" claims are not a guarantee; layered signals and manual review of high-risk cases still matter.
- **NSysS deepfake study (2025).** Most public detectors bypassed; high-resolution deepfakes more successful; 240p caught — [ACM](https://dl.acm.org/doi/full/10.1145/3777555.3777563). **Lesson:** detection performance is resolution- and condition-dependent; do not deploy a single detector as a gate.
- **Prompt-injection resume study (USENIX Security 2026).** >90% of hidden prompts in 196,682 resumes had no explicit instruction — [OWASP](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html). **Lesson:** keyword blocklists are insufficient; treat all document text as untrusted and keep the decision out of the model.
- **Deepfake video-call fraud (Hong Kong, 2023).** A finance employee was reportedly duped into transferring US$25 million after a deepfake video call — [ID.me](https://id.me/articles/fighting-the-new-face-of-identity-theft). **Lesson:** deepfakes are already used in high-value social engineering; a pharmacy discount is a lower-value target, but the same tooling is available.

### Inferences

- **Attack economics favour the attacker at low volume.** A P40–P1,000 fake ID against a 20% discount on a P2,500 basket yields P500; even a P1,000 fake pays back on two orders. The marketplace should assume a persistent low-level attack, not a one-off. `[Inference]`
- **The local attack surface is document supply, not biometric spoofing.** The Philippine cases are printing, templates, and borrowed cards. Investing first in registry checks, duplicate detection, and handover will outperform investing first in deepfake detection. `[Inference]`
- **Every global case is a warning about single-factor gates.** iProov, TrendAI, and NSysS all defeated a single liveness/document check; the defenses that held were layered (injection detection, registered-image comparison, human review, device signals). `[Inference]`
- **Registry checks change the economics.** If a QR/registry lookup is available, a fake printed card fails regardless of print quality. The constraint is coverage, not technology. `[Inference]`

### Gaps

- No published Philippine prosecution or conviction statistics for fake senior/PWD IDs; the Valenzuela case is an arrest, not a conviction.
- No data on recidivism or on the share of fake IDs that are never detected.
- No public data on eKYC vendor breaches in the Philippines.
- No independent evaluation of the NCDA unified digital PWD ID's anti-fraud effectiveness; it launched in late 2026 and coverage is still rolling out.

---

## 9. Existing Philippine practice: banks, e-wallets, drugstores, and regulators

### Takeaway

Philippine online pharmacies already save uploaded senior/PWD IDs to customer accounts; the state is moving to QR-based unified IDs, but private real-time verification is not yet universal. Banks and e-wallets operate under BSP eKYC rules that are stricter than a discount program needs; copying them wholesale would over-collect data and slow elderly users.

### Cited Findings

- **Online pharmacies already do saved-ID verification.** Rose Pharmacy's senior/PWD account registration asks for the ID number and front/back ID photo upload — [Rose Pharmacy](https://www.rosepharmacy.com/senior-and-pwd-account-registration). Southstar Drug's social-media post tells customers to upload a photo of the senior citizen ID when shopping online to get 20% off — [Southstar Drug Facebook post](https://www.facebook.com/southstardrugph/posts/you-can-conveniently-shop-for-your-lolo-and-lolas-medicine-onlineavail-of-the-se/10161352350364848). `[Industry practice / social media]`
- **The FDA's 2024 advisory** restates the accepted IDs and the representative rule, and (as of February 2024) still refers to the purchase booklet — [FDA Advisory No. 2024-0429](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`
- **The booklet requirement was later removed for prescription medicines.** The National Commission of Senior Citizens announced that DOH Administrative Order No. 2024-0017 (23 December 2024) removed the need to present purchase booklets for the 20% discount on prescription medicines — [NCSC, 24 Dec 2024](https://www.ncsc.gov.ph/post/a48e7c6b). This is a conflict/update relative to the FDA advisory's booklet reference; treat the newer DOH order as controlling for prescriptions. `[Law/regulator]`
- **Bacolod City** began issuing QR-code-based senior and PWD IDs in February 2024; by 26 November 2024 it had issued 50,433 senior IDs and 17,078 PWD IDs, and the DSSD urged businesses to scan the QR to see the cardholder's data — [Sunstar, 28 Nov 2024](https://www.sunstar.com.ph/bacolod/dssd-opens-probe-into-tax-leakages-caused-by-fake-pwd-ids). `[News/secondary]`
- **NCDA's Unified PWD Identification System (UPWDIS)** uses a unique QR code scanned by merchants into a DSWD portal; registration remains in-person at LGUs, and a physical card can be printed on request — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary]`
- **Restaurants** verify PWD IDs through the Philippine Registry for Persons with Disabilities (PRPWD) managed by DOH and/or the PDAO; the PRPWD is not always updated promptly because LGUs upload data manually and in batches — [Rappler, 7 Dec 2024](https://www.rappler.com/newsbreak/in-depth/increase-fake-person-with-disability-ids-philippines/). `[News/secondary]`
- **BSP Circular No. 1170** permits eKYC for banks and non-bank financial institutions, requires the same standard as face-to-face verification, recognises PhilSys-enabled eKYC, and requires PSA relying-party onboarding — [BSP](https://www.bsp.gov.ph/Regulations/Published%20Issuances/Images/Circular_1170.pdf). `[Law/regulator]`
- **The NPC** has issued guidance on QR codes for KYC by third-party processors (AO 2025-015) and on biometric processing in the unified PWD ID (AO 2025-007) — [NPC Advisory Opinions](https://privacy.gov.ph/pips-and-pics/advisory-opinions). `[Law/regulator]`
- **TransUnion's Philippine data** puts the country's suspected digital fraud rate at 13.4% in 2024 (second-highest among analysed markets) and 4.1% in 2025 (above the 3.8% global rate for the sixth year) — [TransUnion 2025](https://newsroom.transunion.ph/philippines-suspected-digital-fraud-rate-higher-than-global-level-for-fifth-consecutive-year); [TransUnion 2026](https://newsroom.transunion.ph/filipinos-face-widespread-digital-fraud-exposure-despite-lower-financial-losses-transunion-finds). `[Industry data]`

### Inferences

- **The market norm is already "upload once, save to account".** A marketplace that does this is not an outlier; it inherits a risk that online pharmacies have already taken. The differentiator should be auditability, revocation, and fairness, not the mere fact of saving. `[Inference]`
- **Verification coverage is a patchwork.** PhilSys QR is cryptographically signed and widely usable; Bacolod and NCDA QR systems are local/central; Valenzuela has VAL-ID; most LGUs have no private API. A national marketplace cannot assume a single lookup. Build a per-issuer adapter layer and a manual fallback. `[Inference]`
- **Banks are not a template.** BSP eKYC is designed for AML/CFT and account-opening risk, with PhilSys access and strict relying-party obligations. A 20% pharmacy discount does not warrant that level of proofing; NIST explicitly warns that excessive proofing has its own impact. Use the lightest level that manages the loss. `[Inference]`
- **E-wallets and telcos likely use ID + selfie + liveness, but public detail is thin.** I found no published GCash/Maya/Globe/Smart verification specs or discount-fraud data; do not cite them as evidence without a primary source. `[Inference]`
- **Senior/PWD groups will resist app-only and biometric flows.** The DOJ/NCDA presumption of regularity, the NPC's biometric caution, and the practical needs of elderly users all point to assisted verification. A phone/branch path is not a nice-to-have. `[Inference]`

### Gaps

- No public documentation of GCash, Maya, or Philippine telco ID-verification methods or their fraud rates.
- No industry data on Philippine pharmacy discount fraud specifically; the TransUnion figures are all digital fraud, not discount abuse.
- No public API catalogue or coverage list for LGU senior/PWD verification.
- No published evaluation of NCDA UPWDIS accuracy, uptime, or merchant adoption.
- No data on how often online pharmacies actually reject an uploaded ID, or on their false-reject rates.

---

## 10. Operational recommendations: phased rollout, automation boundaries, revocation, family use, statuses, and claims

### Takeaway

Phase in saved IDs behind a low-risk auto-approval lane, keep handover checks, never auto-deny on one signal, and invest in audit and appeals before biometrics. Measure leakage in pesos and false rejects in people. Publish clear statuses and avoid overclaiming what the system can prove.

### Cited Findings

- NIST's risk-management framing says the relying party should select an assurance level based on the impact of failure and should not collect more than needed; excessive proofing is itself an impact to weigh — [NIST SP 800-63-3](https://pages.nist.gov/800-63-3/sp800-63-3.html); [NIST SP 800-63-4](https://pages.nist.gov/800-63-4/sp800-63.html). `[Standards body]`
- BSP Circular 1170 requires eKYC to meet the same standard as face-to-face verification, which is a useful benchmark for "how good is good enough" if the marketplace ever adds digital identity checks — [BSP](https://www.bsp.gov.ph/Regulations/Published%20Issuances/Images/Circular_1170.pdf). `[Law/regulator]`
- NCDA/DOJ's presumption of regularity means the default should be to grant the discount and verify only on doubt — [GMA News, 11 Nov 2026](https://www.gmanetwork.com/news/topstories/nation/1009153/merchants-can-scan-unified-digital-pwd-cards-for-verification-ncda/story/). `[News/secondary, quoting regulator]`
- RA 9994's refusal penalty is 2–6 years' imprisonment and a P50,000–P100,000 fine for a first offence — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html). `[Law]`
- The FDA's representative rule expressly contemplates that a representative may buy for the senior — [FDA](https://www.fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/). `[Law/regulator]`
- ACFE found that **43% of frauds were detected by tips**, more than any other method — [ACFE 2024](https://www.ivey.uwo.ca/media/kjljj5cy/2024-report-to-the-nations.pdf). `[Industry data]`
- The DPA's 72-hour breach notification and the NPC's five-day full-report rule make incident response a board-level operational requirement — [NPC AO 2020-038](https://privacy.gov.ph/wp-content/uploads/2022/01/Redacted-Advisory-Opinion-No.-2020-038.pdf); [Baker McKenzie](https://www.bakermckenzie.com/en/insight/publications/2026/06/philippines-data-breach-notification-procedure-further-clarified). `[Law/regulator]`

### Inferences

**Phased rollout with gates**

| Phase | Duration | What ships | Gate to proceed |
|---|---|---|---|
| **0. Baseline** | 2–4 weeks | Audit 100–300 past discounted orders; map ID types/issuers; measure duplicate/mismatch/handover-failure rates; DPIA; retention policy; legal review; staff interviews | You know your baseline leakage and false-reject rate; counsel has signed off on the flow |
| **1. Save with manual first check** | 1–2 months | Save ID after a trained reviewer verifies first use; auto-apply only for exact-match repeat orders below risk threshold; keep physical handover check; statuses and appeal live | Reviewer agreement >0.7; false rejects <2%; no critical privacy gap |
| **2. Risk scoring + registry** | 2–4 months | Add QR/registry lookup where available; fuzzy matching; device/velocity; sample audit; step-up; reviewer QA; weekly dashboard | Leakage falls; appeal overturn <10%; review SLA <24h high-risk |
| **3. Optimise** | 6+ months | Expand auto-approval; add issuer adapters; consider vendor eKYC only if data justifies; quarterly red team; publish transparency note | Red-team pass >95% for known classes; cost per verified user trending down |
| **4. Biometrics (only if justified)** | Later | Liveness + injection detection + registered-image comparison, with explicit consent and counsel review | A measured fraud class that cannot be controlled otherwise; NPC/DPO sign-off |

**Automate versus review**

| Decision | Criteria | Action |
|---|---|---|
| **Auto-approve** | Saved ID, not expired, same customer, exact ID hash match, name/DOB consistent, low velocity, no device/address anomaly | Apply discount; 5% random audit |
| **Auto-approve with spot check** | Same but new device or address; medium value | Apply; flag for 20% audit |
| **Review** | First-time ID; duplicate on another account; name mismatch; fuzzy 0.80–0.95; OCR low confidence; QR/registry fail; high value; new account | Hold discount, not the medicine; human decision within 24–72h |
| **Step-up** | High risk; ATO signals; repeated failed uploads; registry mismatch; address/device change plus high value | Re-upload in-app, video call, branch visit, or OSCA/DSWD confirmation |
| **Decline (rare)** | Confirmed fake/tampered document; registry says cancelled; confirmed account fraud | Decline the discount only; allow full-price purchase; appeal path |

**When to revoke a saved ID**
- ID expired or within 30 days of expiry (prompt renewal).
- Issuer/registry says invalid, cancelled, or not found (after a human check).
- Name, DOB, or account holder changed.
- Account takeover or suspicious login.
- Confirmed use by a different person without a representative declaration.
- Two handover failures for the same saved ID.
- Chargeback or confirmed fraud on the account.
- Customer request.
- Verified death report.
- Document tampering or edited image.
- No re-verification after a defined period (e.g., 3 years or the ID's validity, whichever is shorter). `[Inference]`

**Family members and representatives**
- Allow a **representative** flag at checkout: patient name, relationship, and a declaration that the medicine is for the ID holder.
- Do **not** require the account holder's name to match the ID for senior medicine purchases; the FDA expressly allows representatives.
- Give the senior one account; add family caregivers as **authorised users** with their own logins, not shared passwords.
- Do not let one saved ID cover multiple unrelated accounts; require a household/representative link and audit it.
- Cap one discount per ID per order; no stacking; monitor multiple accounts using the same ID.
- For PWD, confirm whether representative purchases are permitted before applying the same design; the senior rule is clearer. `[Inference]`

**Statuses to show customers**
- "ID saved — verified on [date]."
- "Discount applied from your saved ID."
- "We need a clearer photo — here's how" (with tips and an assisted option).
- "Your ID has expired — update it to keep the discount."
- "We're checking this with the issuer. You can still check out; the discount is applied provisionally."
- "This order needs a quick review — we'll update you within 24 hours."
- "Discount not applied — you can upload a valid ID or continue at full price."
- "Appeal received — we'll decide within 7 days."
- Avoid: "Suspected fraud", "Fake ID", "Blacklisted", "Invalid person". `[Inference]`

**Claims to avoid**
- "Government-verified" unless you actually call an authoritative registry.
- "Biometric verification" if you only do OCR or a photo match without liveness/injection detection.
- "100% fraud detection" or "zero fraud".
- "We report all fraudulent users to the police" unless that is the approved policy and counsel has signed off.
- "Your ID is encrypted end-to-end" if you store images that staff can view.
- "All seniors must verify" — legally risky and discriminatory.
- "We verify with OSCA" if you only make a phone call or use a commercial database.
- "AI-powered fraud detection" as marketing without published metrics.
- "No one can steal your identity" — a breach can still happen. `[Inference]`

**Cost and staffing plan** (illustrative)
- One senior reviewer + one backup; P25,000–P35,000/month each fully loaded.
- 100-case monthly audit ≈ 8 reviewer-hours.
- Vendor eKYC quotes should be compared on a **per-verified-user** basis, including manual review and false-reject support, not per-scan sticker price.
- Budget for appeal handling, customer support, legal review, and incident response; these are usually underestimated. `[Inference]`

**Quarterly operating rhythm**
- Red-team the capture, OCR/LLM, QR, and review queue with a fixed corpus (template, screenshot, recapture, prompt injection, fake QR, borrowed ID).
- Recalibrate risk thresholds against the audit sample.
- Review revoked IDs and appeals.
- Reconcile discounts against verified IDs with finance.
- Tabletop a breach: 72-hour notification, NPC DBNMS, customer comms.
- Review vendor access and deletion certificates. `[Inference]`

### Gaps

- No pilot data exists yet; the gates above are proposals, not validated thresholds.
- No vendor quotes or LGU API commitments; costs and coverage must be confirmed locally.
- No NPC guidance on customer-facing status language for discount verification.
- No evidence on the optimal re-verification interval for saved IDs in this market.
- No data on how many customers would abandon the flow if asked to re-upload or step up; this needs an A/B test.
