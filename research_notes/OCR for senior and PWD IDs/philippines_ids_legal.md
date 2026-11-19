# Philippine Senior Citizen, PWD, and PhilSys IDs: Legal, Regulatory, and Verification Landscape for a Private E-Commerce Pharmacy

**Scope and date.** Philippine jurisdiction; desk research current to **18 November 2026**. Sources are official texts and regulator pages wherever possible; law-firm/press items are labelled secondary. This is product/engineering research, **not legal advice** — items marked "counsel" need a Philippine lawyer.

**Method note.** RA 9994, RA 10754, RA 10173, RA 11055, and RA 11967 were read from LawPhil full texts. The RA 9994 IRR was read from the same LawPhil page. PSA/PhilSys, NPC, BIR, FDA, BSP, NCSC, and NCDA material was read from agency sites or agency PDFs; FDA and some news sites blocked automated retrieval, so those items rely on the agency URL plus corroborating coverage and are flagged.

**Bottom line for the product (5 points).**
1. A **saved, reusable ID record is lawful in principle** — the discount laws require *presentation* of proof, and BIR requires a *record* containing name + ID number, not possession of the card. But there is **no legal basis for treating a one-time online check as satisfying the handover check**: the RA 9994 IRR explicitly requires the senior's ID to be presented again on delivery.
2. **PhilSys cannot prove discount entitlement.** RA 11055 §14 says proof of identity is not proof of eligibility, and PhilSys holds no senior/PWD/disability status. It can prove name, age, and identity — useful, but not sufficient for the 20%.
3. **PWD verification just became genuinely digital**: NCDA's Unified PWD ID System (AO 001 s. 2026, effective Sept 2026) issues QR-coded IDs verifiable against a national database at `verify.alaga.ph`. **Senior IDs remain a local-government patchwork with no national API** (though a digital NSCID in eGovPH now exists).
4. The biggest legal risk is **not** "is saving the ID allowed" — it is **data minimization**. The NPC's December 2025 opinion on PhilSys QR/KYC says to ask whether you need to retain the image at all, or only "a verification result and a limited subset of fields." Storing photos of senior/PWD IDs is hard to justify when the tax rule only needs name + number.
5. Keep the **physical check at handover** as the legal backstop and the **BIR sales record** as the compliance artifact. Treat the saved ID as a convenience/risk-scoring layer, not as the legal proof.

---

## 1. Senior citizen ID: issuance under RA 9994, formats, validity, registry/API, and proof for the 20% medicine discount + VAT exemption

### Takeaway
OSCA IDs are issued **locally, free, with no national template, no stated expiry, and no national verification API**. The law lets a senior prove entitlement with an OSCA ID, a passport, or "other documents" (driver's licence, voter's ID, SSS/GSIS ID, PRC card, postal ID). For medicines, the 20% discount and VAT exemption are mandatory on presentation, and since September 2025 the purchase booklet is no longer required. BIR requires the pharmacy to keep a sales record showing the senior's **name and identification document** — not a copy of the card.

### Cited Findings
- **Who may issue / what it is.** RA 9994 §6(c) tasks each city/municipal OSCA "to maintain and regularly update on a quarterly basis the list of senior citizens and to issue national individual identification cards, **free of charge**, which shall be **valid anywhere in the country**." — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **No national design, no expiry, no chip/QR mandate.** RA 9994 and its IRR prescribe only that the card be issued by OSCA and honoured nationwide; neither sets a card template, a validity period, a QR code, nor a central registry. IRR Art. 6 requires presentation of "a **valid and original** Senior Citizens' Identification Card issued by the Head of the [OSCA] of the place where the senior citizen resides." — [RA 9994 and IRR](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **No statutory expiry (practical position).** A Philippine practitioner commentary states the card itself does not expire, though some LGUs recommend periodic renewal/updating for record-keeping and booklets — **secondary**. — [Respicio & Co.](https://respicio.ph/commentaries/difference-between-osca-id-and-senior-citizen-sc-id-philippines)
- **Accepted proof (statute).** RA 9994 §4 lists: "(1) an identification card issued by the [OSCA] ... **Provided, That the identification card issued by the particular OSCA shall be honored nationwide**; (2) the passport of the senior citizen concerned; and (3) **other documents that establish that the senior citizen is a citizen of the Republic and is at least sixty (60) years of age** as further provided in the IRR." — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **Accepted proof (IRR expansion).** IRR §5.5 lists, as "other valid documents": "**driver's license, voters ID, SSS/GSIS ID, PRC card, postal ID**" indicating birthdate/age. — [RA 9994 IRR](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **FDA confirms "any" of these IDs.** FDA Advisory No. 2024-0429 ("Clarification on the Requirements for Senior Citizens to Avail 20% Discount on Medicines") requires drug outlets to grant the discount "upon presentation of **any** of the following Identification Document as per Section 4 of RA 9994." — [FDA Advisory 2024-0429](https://fda.gov.ph/fda-advisory-no-2024-0429-clarification-on-the-requirements-for-senior-citizens-to-avail-20-discount-on-medicines/)
- **Booklet abolished.** FDA Circular No. 2025-005 (issued 4 September 2025) delisted the purchase booklet from the requirements to avail the 20% senior discount on medicines and medical devices; coverage reports that an ID proving citizenship/age is now sufficient. — [FDA Circular 2025-005](https://www.fda.gov.ph/fda-circular-no-2025-005-delisting-of-purchase-booklet-from-the-checklist-of-requirements-to-avail-of-the-20-senior-citizen-discount-on-the-purchase-of-medicines-and-medical-devices-in-accordance-w/); [PNA](https://pna.gov.ph/articles/1258031)
- **Discount scope.** RA 9994 §4(a)(1) grants "twenty percent (20%) discount and exemption from the value-added tax (VAT), if applicable, on the **purchase of medicines**, including ... influenza and pneumococcal vaccines, and such other essential medical supplies, accessories and equipment to be determined by the [DOH]" — for the senior's "exclusive use and enjoyment." — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **Digital national senior ID now exists.** The NCSC's **Public Advisory No. 1, s. 2026** states the **digital National Senior Citizens ID (NSCID)** in the **eGovPH** app is a valid government ID usable for "the 20 percent discount and value-added tax (VAT) exemption on medicines, food, transportation," and banking transactions; launched August 2025 with DICT, >1.3 million users by April 2026. — [Abogado.com.ph report on NCSC Advisory](https://abogado.com.ph/govt-reminds-public-establishments-to-accept-digital-senior-citizen-id); [Tech Pilipinas guide — secondary](https://techpilipinas.com/digital-senior-citizen-id-philippines)
- **No national OSCA registry/API found.** No PSA/DILG/NCSC public API for OSCA ID validation was located; the NSCID is generated by matching PhilSys and LGU records inside eGovPH, but no third-party verification endpoint is documented. (See Gaps.)

### Inferences
- A pharmacy platform **should not hard-require an OSCA ID**. A passport, driver's licence, voter's ID, SSS/GSIS ID, PRC ID, postal ID, or the new digital NSCID can lawfully evidence entitlement, provided it shows citizenship and age ≥60.
- Because there is no national API, **senior eligibility cannot be cryptographically verified today** except indirectly: (a) scan a **PhilID** QR via PhilSys Check to authenticate identity and read date of birth; or (b) manual review of the uploaded/presented ID at handover. Neither proves OSCA registration, and neither is a legal substitute for the OSCA card if the customer presents one.
- A **photo of the OSCA ID adds no BIR value** over name + ID number, and increases breach exposure. The record, not the image, is the compliance artifact.
- The **digital NSCID changes UX expectations**: seniors may present a phone, not a card, and staff refusing it risk a complaint to NCSC. Onboarding copy and branch SOPs should explicitly accept it.

### Gaps
- No primary source found that prescribes **OSCA ID fields** or a standard layout; actual cards vary by LGU (name, address, birthdate, photo, ID no., signature are typical but not mandated in the law).
- It is unclear whether the **digital NSCID carries a scannable QR** that a third party can verify, and whether NCSC exposes any verification service. Needs direct NCSC/DICT inquiry.
- No official statement found on whether a **PhilID alone** (showing Filipino citizenship and DOB) satisfies RA 9994 §4's "other documents" catch-all for the medicine discount. Arguable yes; **counsel/OSCA confirmation recommended**.

---

## 2. PWD ID: issuance under RA 7277 (as amended), RA 10754, fields, QR, validity, discount, and proof/record rules

### Takeaway
PWD IDs were historically issued by LGUs (mayor/barangay captain, now PDAO/C/MSWDO) with **no national format** and widely varying validity. That changed in 2026: NCDA's **Unified PWD ID System (UPWDIS)** imposes a standard card with a **unique alphanumeric number and QR code**, backed by a national database, and the public can verify a card at `verify.alaga.ph`. PWDs get at least 20% + VAT exemption on medicines, and BIR requires name + PWD ID number in the sales record.

### Cited Findings
- **Statutory entitlement and proof.** RA 10754 §1 (amending RA 7277 §32) grants "at least twenty percent (20%) discount and exemption from the value-added tax (VAT) ... on the purchase of medicines in all drugstores" and lists proof: "(i) An identification card issued by the **city or municipal mayor or the barangay captain** of the place where the PWD resides; (ii) The passport of the PWD concerned; or (iii) **Transportation discount fare Identification Card (ID)** issued by the [NCWDP]." — [RA 10754](https://lawphil.net/statutes/repacts/ra2016/ra_10754_2016.html)
- **No stacking.** RA 10754: "The privileges may not be claimed if the PWD claims a higher discount as may be granted by the commercial establishment and/or under other existing laws or in combination with other discount program/s." — [RA 10754](https://lawphil.net/statutes/repacts/ra2016/ra_10754_2016.html)
- **BIR accepted proof.** RR 5-2017 §9 accepts: "An identification card issued by the **Office of Persons with Disability Affairs (PDAO) or the City/Municipal Social Welfare and Development Office (C/MSWDO)**"; a passport "with apparent disability"; or an NCDA-issued ID. — [BIR RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754)
- **BIR record-keeping.** RR 5-2017 §5.9 requires "separate and accurate records of sales, which shall include the **name of the person with disability, PWD ID Number, gross sales/receipts, sales discount granted, date of transactions and invoice number** for every sales transaction to PWD." §5.1 adds that if the name and PWD ID No. are absent, "the sales discount claimed as deduction ... shall be disallowed." — [BIR RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754)
- **Unified PWD ID System (2026).** NCDA Administrative Order No. 001, s. 2026 institutionalised the UPWDIS; it took effect September 2026 with a **three-year transition**, during which existing authentic PWD IDs remain valid. The new card has "a standardized template, which includes a **QR code and an alphanumeric identification number**." — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system); [NCDA AO 001 s. 2026](https://ncda.gov.ph/2026/09/09/institutionalization-and-implementation-of-a-unified-persons-with-disabilities-identification-system-administrative-order-no-001-series-of-2026/)
- **New validity rules.** Under the new system: cards for **permanent disabilities have lifetime validity**, while cards for **non-apparent disabilities are revalidated every five years**; previously PWD IDs "were generally valid for five years." — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **Public verification endpoint.** "Establishments, merchants, and offices can also verify PWD IDs by **scanning the QR code and checking it against the national database through the website `https://verify.alaga.ph/`**." — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **Why it exists.** Rappler reports the Senate found the government lost **₱88.2 billion** in revenues to fake PWD IDs, and that in 2024 there may have been as many as 8.5 million "illegitimate" PWDs against 1.89 million registered. As of October 2026: ~2.9 million registered PWDs, ~1,100 of 1,642 LGUs onboarded, >190,000 registered in UPWDIS, >128,000 digital IDs issued. — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **Registration route.** PWDs register through the **PDAO** in their locality; those with non-apparent disabilities need a PDAO assessment/certificate uploaded to UPWDIS; only UPWDIS generates the unique ID number; only PDAO officers and LGU IT personnel have access. — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **Physical card still available.** "The new ID is primarily digital, but physical cards can still be requested." — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **Pre-2026 verification was weak.** Before UPWDIS, establishments verified through a **DOH-managed registry** or the issuing LGU's PDAO, and "the registry was not always updated, making verification difficult." — [Rappler, 6 Nov 2026](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)

### Inferences
- The PWD side is the **one place where a real digital verification workflow is now possible**: QR scan against a national database, returning an authenticity result. A platform could ask the customer to scan/upload the QR and store the **verification result + UPWDIS number**, not the card image.
- During the **three-year transition**, both old LGU IDs and new UPWDIS IDs will circulate. A design that only accepts the new QR format will fail legitimate customers until ~2029; a design that only accepts old IDs will miss the fraud-reduction benefit.
- Because only PDAO/LGU IT staff can write to UPWDIS, the platform cannot self-enrol a customer; it can only **read/verify**.
- A PWD who is also a senior may claim **only one** 20% discount (RA 9994 IRR Art. 9; RR 5-2017 §6). The platform must not stack senior + PWD discounts on the same basket.

### Gaps
- `verify.alaga.ph` returned no readable content to automated retrieval (JavaScript app). **What fields it returns, whether it needs an account/API key, rate limits, and terms of use are unconfirmed** — this is the single most important technical unknown for PWD verification.
- No official NCDA issuance was found stating whether third-party platforms may **programmatically** query `verify.alaga.ph` or must use the web page manually. Counsel/ NCDA inquiry needed.
- Conflicting validity figures in circulation (IRR-era 3 years vs. practice 5 years vs. new lifetime/5-year rules); the 2026 AO should be treated as controlling going forward.

---

## 3. PhilSys: RA 11055, PhilID/ePhilID, PhilSys Check and eVerify; private-sector access, cost, limits, data exposed, and whether it carries senior/PWD status

### Takeaway
Private entities are **mandated to accept** the PhilID/PSN as valid proof of identity, subject to authentication, and **must obtain consent** and disclose what will be shared and why. Two verification channels exist: **PhilSys Check** (public, free QR scan, "offline" authentication) and **National ID eVerify/NIDAS** (relying-party onboarding, API, Tier 1 "yes/no" and Tier 2 eKYC; currently free, with minimal private-sector fees possible later). PhilSys proves **identity and date of birth only** — it carries **no senior, PWD, or discount status**.

### Cited Findings
- **Mandatory acceptance + consent duty.** RA 11055 §12: "Private entities are likewise mandated to accept the PhilID or PSN as valid proof of identity under the same terms and conditions as above ... **Any requesting entity shall obtain the consent of an individual before collecting his or her identity information for the purpose of authentication. It shall inform the individual ... (a) the nature of the information that may be shared upon authentication, and (b) the uses to which the information received during authentication may be put** ... Provided, That the information requested shall only be used for the purpose for which it was requested." — [RA 11055](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)
- **Identity ≠ eligibility.** RA 11055 §14: "**Proof of identity shall not necessarily be construed as proof of eligibility to avail of certain benefits and services** which shall be determined based on applicable rules and regulations of the government authorities/agencies concerned." — [RA 11055](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)
- **Refusal is penalised.** RA 11055 §19: refusing to accept/recognise the PhilID or PSN "without just and sufficient cause" carries a **₱500,000 fine**; misuse/falsification carries imprisonment and higher fines. — [RA 11055](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)
- **Formats.** The PhilID "comes in various formats—printed, downloadable, and mobile—all of which are referred to as the ePhilID" (PSA handbook). PSA's FAQ: all formats — card, paper ePhilID, and Digital National ID — "have the same functionality and validity," and for citizens the National ID "has **no expiration**." — [PhilSys Check Handbook v3.0](https://verify.philsys.gov.ph/api/handbook); [PhilSys FAQ, updated 13 Feb 2026](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **What the QR contains.** "The PhilSys QR code contains the **digital signature of PSA**, the registered person's **PhilSys Card Number (PCN)** and **demographic information** and the **best captured fingerprint**." — [PhilSys Check Handbook v3.0](https://verify.philsys.gov.ph/api/handbook)
- **What PhilSys Check displays.** On success: last/first/middle name, suffix, sex, date of birth, place of birth, **PCN**, date of issuance, and "best capture finger"; ePhilID additionally shows the photo. It also shows "Deactivated Card" or "QR Code could not be read." — [PhilSys Check Handbook v3.0](https://verify.philsys.gov.ph/api/handbook)
- **PhilSys Check is public and free.** It is "developed for PhilSys relying parties **and the public**"; PSA describes it as free and accessible via `verify.philsys.gov.ph`. — [PhilSys Check Handbook v3.0](https://verify.philsys.gov.ph/api/handbook); [PSA RSSO-10](https://rsso10.psa.gov.ph/content/psa-launched-philsys-check)
- **eVerify is the relying-party channel.** eVerify is "for government agencies and **private establishments**"; it features "advanced facial recognition ... with the National ID registry," and hosts **National ID Check** (QR authenticity). — [PSA/DICT press release, 10 June 2024](https://philsys.gov.ph/psa-dict-rolls-out-digital-national-id-authentication-services-national-id-everify-national-id-check/)
- **Who may access.** eVerify's Terms of Use define "Authorized Representative" as "Any representative from **government offices, financial institutions, private entities, and other organizations** who intend to integrate with the PhilSys and consume its authentication services," and allow them to "view lists of **accredited RPs and Trusted Services Providers**." — [eVerify Terms of Use](https://everify.gov.ph/terms-of-use)
- **Cost.** "Currently, the use of National ID Authentication services is **free of charge** to those companies/agencies who underwent onboarding ... In the long run ... if any fees are introduced ... they will be minimal ... The possibility of charging fees will only apply to the onboarded Relying Parties from the **Private Sector**." — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **Authentication tiers (PSA).** Tier 0: presenting the National ID and matching data in the QR. Tier I: "Basic Authentication"; Tier II: "electronic Know-Your-Customer (eKYC)," both validated real-time against the registry. — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **Tier detail (regulator draft).** A July 2026 **draft** BSP memorandum explains: "Tier 1 enables basic online authentication through '**yes/no**' responses and back-end PSN token verification; Tier 2 provides e-KYC services with **pre-agreed demographic data fields** between the relying party and PSA; and Tier 3, **which is not yet operational**, is intended to allow lawful interoperability by matching back-end PSN tokens across relying parties." (The draft is internally inconsistent: its operative paragraph tells BSIs to adopt "the appropriate NIDAS authentication tier 3 for face-to-face and/or digital onboarding" while the footnote says Tier 3 is not yet operational. Treat the tier definitions as indicative only.) — [BSP draft Memorandum M-2026-___](https://www.bsp.gov.ph/Regulations/Issuances%20of%20Policy%20Exposure%20Drafts/Draft-Memorandum-to-All-BSIs-on-NIDAS-Integration.pdf)
- **Tokenisation constraint.** "Government agencies and the private sector **cannot store the National ID Number itself in their databases** but they can store specific **tokens** that will allow them to establish the uniqueness of individuals." — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **Scale.** 56 million National ID authentications; 87 relying parties onboarded as of 17 September 2025. — [PhilSys/PSA](https://philsys.gov.ph/56m-national-id-authentications-largest-use-cases-in-social-protection-financial-inclusion)
- **No senior/PWD data.** PhilSys "does not collect information on socio-economic status, political or religious affiliation, criminal record, educational qualifications, etc."; the registry holds only the demographic and biometric fields in RA 11055 §8. — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/); [RA 11055 §8](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)

### Inferences
- **A pharmacy marketplace is a plausible relying party** (private entity with a legitimate verification need), but PSA's FAQ says non-government applicants are "NGOs engaged in social protection," while eVerify's Terms of Use and the BSP draft describe private entities/financial institutions generally. The onboarding gate is PSA's discretion; **confirm eligibility with PSA before designing around it**.
- **PhilSys Check is the low-cost, no-onboarding option**: any staff device can scan a PhilID/ePhilID QR and see name + DOB + PCN. It is a **manual** check, not an API, and returns more data (place of birth, best-capture finger) than a pharmacy needs.
- **eVerify Tier 1 is the privacy-minimal API** ("yes/no" + token) and is sufficient to answer "is this person who they say they are?" — but not "are they 60+?" unless age is a returned field. Tier 2 would return pre-agreed demographics (which could include DOB), at higher onboarding cost.
- The **PCN/PSN should not become the platform's customer key**; store a token or a platform-generated ID instead.
- **PhilID can help prove age**, not discount status. A DOB on a PhilID is evidence for the senior 20%; it says nothing about PWD status or OSCA registration.

### Gaps
- **Whether a for-profit pharmacy platform will be accepted as an RP** is unresolved. The public FAQ answer is narrower than the Terms of Use. This is the top question for PSA (`fpsucd@psa.gov.ph` for "financial and private institutions" per PSA's own article; `fpsucd.staff@psa.gov.ph` per the BSP draft).
- Onboarding requirements (BPM, VAPT, LOI, SLA, API key timeline) are listed as FAQ headings on eVerify but the answers are rendered client-side and could not be retrieved. **The actual documentary/technical checklist is unconfirmed.**
- No public price list for private-sector RPs exists; PSA says "free ... currently."

---

## 4. Other IDs: LTO licence, DFA passport, UMID, PRC ID, postal ID, voter's ID — public verification services vs. eKYC vendors; eGovPH

### Takeaway
Only **PhilSys** and the **new UPWDIS** offer government-run verification of the *credential itself* to a third party. Among the "other IDs" accepted for the senior discount, only the **PRC ID** has a free public online lookup (LERIS) — and that verifies the licence, not the holder. LTO, DFA, SSS/GSIS (UMID), PHLPost (postal ID), and COMELEC do **not** publish a general third-party verification API; private verification therefore runs through **eKYC vendors** or physical inspection. The **eGovPH** app is now the container for the Digital National ID and the digital NSCID.

### Cited Findings
- **PRC.** PRC's LERIS portal provides "online verification of rating of successful examinees and **verification of license of registered professionals**"; a practitioner commentary states basic online verification is free and needs no login. — [PRC LERIS](https://online.prc.gov.ph/); [Respicio & Co. — secondary](https://respicio.ph/commentaries/how-to-verify-a-prc-license-online)
- **PSA's own list of primary/secondary documents** for PhilSys registration includes passport, UMID, LTO licence/permit, PRC ID, Seaman's Book, Senior Citizen ID, PWD ID, voter's ID, postal ID, TIN ID, PhilHealth ID — confirming these are recognised identity documents, but **not** that any of them is verifiable online by a third party. — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **eGovPH.** The Digital National ID is accessible "via `https://national-id.gov.ph` or [by] download[ing] the **eGovPH app**, which requires input of demographic information and **facial verification**"; the digital NSCID is likewise delivered through eGovPH. — [PSA/DICT press release](https://philsys.gov.ph/psa-dict-rolls-out-digital-national-id-authentication-services-national-id-everify-national-id-check/); [Abogado/NCSC](https://abogado.com.ph/govt-reminds-public-establishments-to-accept-digital-senior-citizen-id)
- **UPWDIS.** PWD IDs can be checked at `verify.alaga.ph` against the national database. — [Rappler](https://rappler.com/philippines/persons-with-disabilities-registration-unified-pwd-id-system)
- **No public API found** for LTO, DFA passport, UMID, postal ID, or voter's ID verification. (Search of official portals and regulator pages; see Gaps.)
- **eKYC market.** BSP-regulated institutions conduct electronic KYC under BSP rules; BSP Circular No. 1170 (2023) defines e-KYC as "the process of electronically verifying the credentials of a customer" and sets standards for digital ID use. — [BSP Circular 1170](https://www.bsp.gov.ph/Regulations/Issuances/2023/1170.pdf); [BSP e-KYC coverage — secondary](https://context.ph/2023/04/03/bsp-issues-new-rules-on-customer-due-diligence-e-kyc)
- **Accredited providers list.** eVerify lets RPs "view lists of accredited RPs and **Trusted Services Providers**," implying PSA maintains a TSP list. — [eVerify Terms of Use](https://everify.gov.ph/terms-of-use)

### Inferences
- For a pharmacy, the practical hierarchy is: **PhilID/NSCID (verifiable) → PWD UPWDIS QR (verifiable) → OSCA ID/other IDs (manual only)**.
- PRC verification is useful only if the customer is using a PRC ID to prove age; it verifies the licence, not the person holding the card, so it is weak as an identity check.
- "Upload a photo of your driver's licence" is **not verification** — it is document capture. Without a government lookup or a vendor's liveness/face-match, it only deters casual fraud.
- eGovPH is a **user-side wallet**, not a merchant verification API. Do not design as if the platform can query eGovPH.

### Gaps
- COMELEC, LTO, DFA, SSS/GSIS, and PHLPost may have internal or agency-only verification channels; no public documentation was found. **Absence of evidence, not proof of absence.**
- The PSA **Trusted Services Provider list** could not be retrieved (client-side rendered). It may name vendors a platform could contract with.
- No DTI/DICT "sandbox" for private ID verification was found.

---

## 5. Data Privacy Act (RA 10173), IRR, and NPC issuances: government ID numbers as sensitive personal information, consent, purpose limitation, retention, security, breach, and ID images

### Takeaway
A government ID number is **sensitive personal information** under DPA §3(l)(3) ("issued by government agencies peculiar to an individual"), and the NPC treats PhilSys QR codes and data derived from them as SPI. Processing needs a §12 basis **plus** a §13 basis; for a discount, the strongest pairing is **contract/legal obligation** for the personal data and **consent or "provided for by existing laws"** for the SPI. The NPC's December 2025 KYC opinion is directly on point: question whether you need to **retain the image** at all, prefer a **verification result and a limited subset of fields**, and never repurpose PhilSys identifiers as general database keys.

### Cited Findings
- **SPI definition.** DPA §3(l): sensitive personal information includes "(3) **Issued by government agencies peculiar to an individual** which includes, but is not limited to, social security numbers, previous or current health records, **licenses or its denials, suspension or revocation**, and tax returns." — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- **NPC confirms ID numbers are SPI.** NPC Advisory Opinion No. 2017-066 states that "the **government-issued ID numbers**, civil status, gender and blood type are **sensitive personal information (SPI)**." — [NPC AO 2017-066](https://privacy.gov.ph/wp-content/uploads/2022/01/NPCAONo.2017-066.pdf)
- **NPC confirms PhilSys QR is personal data + SPI.** "The **PhilSys QR code, and any data derived from it** in the course of KYC authentication, should be treated as personal [data] ... associated with government-issued identifiers and biometric data, which are expressly classified as sensitive personal information under Section 3(l)." — [NPC Advisory Opinion No. 2025-1015, 26 Dec 2025](https://privacy.gov.ph/wp-content/uploads/2026/01/NPC-Advisory-Opinion-No.-2025-015_Redact_redacted.pdf)
- **Principles.** DPA §11 requires transparency, legitimate purpose, proportionality; data must be "**adequate and not excessive** in relation to the purposes," and "retained only for as long as necessary." — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- **Lawful bases.** §12 lists consent, contract, legal obligation, vital interests, public order, legitimate interests. §13 permits SPI only with specific prior consent, or when "provided for by existing laws and regulations" (with safeguards), medical treatment, legal claims, etc. — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- **Privacy notice elements.** §16(b) requires, before entry into the system, disclosure of: "(1) Description of the personal information ...; (2) Purposes ...; (3) Scope and method ...; (4) The recipients or classes of recipients ...; (5) Methods utilized for automated access ...; (6) The identity and contact details of the PIC ...; (7) **The period for which the information will be stored**; and (8) **The existence of their rights**, i.e., to access, correction, as well as the right to lodge a complaint before the Commission." — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- **Security.** §20 requires organizational, physical, and technical measures, including network safeguards, a security policy, vulnerability management, and monitoring; §20(f) requires **prompt notification of the NPC and affected data subjects** when SPI "or other information that may ... be used to enable identity fraud" is reasonably believed acquired by an unauthorised person and likely to give rise to real risk of serious harm. — [RA 10173](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)
- **Breach timing.** NPC Circular No. 16-03 (Personal Data Breach Management) is the governing issuance; the NPC's circular index lists it. — [NPC Advisories & Circulars](https://privacy.gov.ph/pips-and-pics/advisories-circulars/)
- **Data minimisation on QR/KYC (directly on point).** The NPC says the RP should assess "whether you need to **retain an image of the QR code** or only to **scan it transiently** for authentication; whether it is sufficient for you to receive a **verification result and a limited subset of fields**, rather than the full QR payload; and whether there are ways to **minimize the number of copies and locations** where PhilSys data are stored." — [NPC AO 2025-1015](https://privacy.gov.ph/wp-content/uploads/2026/01/NPC-Advisory-Opinion-No.-2025-015_Redact_redacted.pdf)
- **Outsourcing.** A processor must act on documented instructions under a written agreement covering security, confidentiality, retention, data-subject rights; a separate consent for the processor is generally not required where the PIC and purpose are unchanged. — [NPC AO 2025-1015](https://privacy.gov.ph/wp-content/uploads/2026/01/NPC-Advisory-Opinion-No.-2025-015_Redact_redacted.pdf)
- **NPC ID-card circular is about IDs the PIC issues, not IDs it accepts.** NPC Circular No. 2023-03 applies to PICs that **issue** ID cards and expressly excludes government regulatory IDs such as "driver's license, passport, Professional ID card of a Registered Professional ... TIN card." — [NPC Circular 2023-03](https://privacy.gov.ph/wp-content/uploads/2023/11/Published-NPC-Circular-No.-2023-03_Guidelines-on-Identification-Cards_07Nov2023.pdf)
- **Penalties.** Unauthorised processing of SPI: 3–6 years and ₱500,000–₱4,000,000; accessing SPI through negligence: same range; unauthorised disclosure of SPI: 3–5 years and ₱500,000–₱2,000,000; large-scale (≥100 persons) triggers maximum penalties. — [RA 10173 §§25–35](https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html)

### Inferences
- **Storing a photographed/uploaded ID is a materially higher-risk activity than storing a number.** It creates a durable copy of an SPI document (often with photo, signature, address, sometimes biometric data), which the NPC's proportionality test is likely to find excessive where the BIR requirement is only name + number.
- **Two different purposes need two different notices/consents**: (a) process the ID for *this order's* discount and BIR record; (b) **save** the verified ID on the account for *future* orders. (b) is not "necessary for the contract" at the time of collection and should be opt-in, revocable, and deletable.
- A defensible data model: ID type; ID number (encrypted, access-logged); holder name; date of birth or derived age band; verification method and result; verification timestamp; document expiry/revalidation date; **no image**; a flag "verified at handover by [branch/user]".
- Retention should be driven by the **BIR record** obligation (tax) and the **dispute/limitation** period, not "forever." The exact BIR retention period after the EOPT Act should be confirmed with tax counsel (see Gaps).
- **Privacy notice must name the ID processing explicitly** — "we collect your senior/PWD ID number and verify it to apply the mandatory discount and to comply with BIR record-keeping" — plus the storage period, recipients (branches, couriers, auditors, BIR), and rights.
- A **DPO and NPC registration** are likely required if the platform is a PIC processing SPI at scale; NPC registration rules (Circular 2022-01 etc.) apply. (Not researched in depth here — flag for counsel.)

### Gaps
- **BIR records retention period** after the Ease of Paying Taxes Act (and whether an ID image, if kept, must be retained for the same period) — verify with tax counsel.
- Whether the NPC would treat a **photograph of an OSCA/PWD ID** as necessary for "establishment, exercise or defense of legal claims" (DPA §13(f)) in case of a fake-ID dispute. Arguable, but not settled.
- No NPC enforcement decision specifically on **pharmacy ID capture** or on **eKYC vendors** was found. The NPC's public advisory opinions are guidance, not adjudication.
- NPC Circular 16-03's exact notification deadline (commonly described as 72 hours) was not re-read from the primary text for this note.

---

## 6. E-commerce and consumer law: RA 11967 (Internet Transactions Act) and IRR; platform/seller obligations; fairness for elderly/PWD users

### Takeaway
RA 11967 imposes **ordinary diligence** on e-marketplaces (verify merchant identity, publish seller information, protect consumer data, maintain redress) and makes the **online merchant/e-retailer primarily liable**, with the marketplace **subsidiarily liable** for failure of diligence. It explicitly requires platforms to comply with the DPA. For a senior/PWD audience, the fairness overlay is the Consumer Act (RA 7394) plus RA 9994/RA 10754's anti-refusal provisions.

### Cited Findings
- **Marketplace obligations.** RA 11967 §21 requires e-marketplaces to ensure transactions are identifiable, identify the person on whose behalf the transaction is made, and identify "any promotional offer including any **discount** ... and that conditions required to qualify for it are accessible, clear, and unambiguous"; to require merchants to submit "at least one (1) **valid government identification**"; to maintain and regularly verify a merchant list; and to "take the necessary precautions to protect the data privacy of consumers ... in accordance with [RA 10173]." — [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)
- **E-retailer obligations.** §23 requires publishing corporate name, physical address, contact details, and a government ID/registration document; protecting consumer data under RA 10173; and issuing "paper or electronic invoices or receipts for all sales." — [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)
- **Liability allocation.** §25: the e-retailer/online merchant is "**primarily liable**"; §26: the e-marketplace is "**subsidiarily liable**" where it failed ordinary diligence under §§21–22, failed to act on a takedown after notice, or the merchant has no Philippine presence. Good-faith reliance on merchant representations is a defence if the platform shows "reasonable effort ... to ascertain and maintain the accuracy, authenticity and veracity" of documents. — [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)
- **Penalties.** §29: deceptive/unfair/unconscionable online sales practices — ₱20k–₱100k (1st), ₱100k–₱500k (2nd), ₱500k–₱1M (3rd+); failure to comply with marketplace/merchant obligations — lower tiers; takedown may be imposed in addition. — [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)
- **Construction.** §34: "In case of doubt, the provisions of this Act ... shall be construed in a manner that accords the **highest respect for human dignity, consumer rights, and individual privacy**." — [RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)
- **IRR exists.** The DTI-led IRR was issued in 2025; a consolidated text is published. — [ITA IRR (ASEAN Consumer)](https://aseanconsumer.org/read-legislation-implementing-rules-and-regulations-of-the-internet-transactions-act-of-2023)
- **Refusal to honour senior/PWD privileges is itself penalised.** RA 9994 §10 penalises anyone who "refuses to honor the senior citizen card" or violates the Act (2–6 years, ₱50k–₱100k), and "any person who **abuses the privileges**" (6 months, ₱50k–₱100k). RA 10754/RR 5-2017 contain parallel penalties. — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html); [RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754)

### Inferences
- A marketplace that advertises "20% senior/PWD discount" must make the **conditions** clear and unambiguous (§21(a)(3)) — including that the discount applies only to covered items, is not stackable, and is confirmed at handover.
- The platform should **not** treat an unverified uploaded ID as proof and then deny the discount at the door; that creates both a consumer-protection complaint and a possible RA 9994 "refusal to honor" issue. Better: apply the discount provisionally, verify at handover, and have a clear fallback.
- Because the marketplace is only **subsidiarily** liable, the cleanest structure is: the **seller/branch** is the merchant of record for the discount and the BIR invoice; the platform provides tooling and records.
- Elderly/PWD usability is a **compliance-adjacent** concern: RA 9994 §4(k) and RA 10754 §32(e) require express lanes/priority; online, the analogue is accessible design and assisted verification. (Inference, not a statutory online-accessibility rule.)

### Gaps
- The ITA IRR's specific **registration** mechanics for online merchants with the E-Commerce Bureau, and any **code of conduct** under §18, were not read in detail.
- No DTI issuance specifically on **senior/PWD discounts in online pharmacies** was found.
- Whether a marketplace must **display** the seller's government ID (vs. hold it on file) is clear from §21(b) (ID not published; other details published) but IRR nuance was not checked.

---

## 7. Discount compliance: what the pharmacy must record and retain; are digital/photo copies acceptable; liability for fake IDs; is physical inspection mandated?

### Takeaway
The compliance artifact is a **sales record**, not a document collection: for seniors, name + identification document + gross sales + discount + date + invoice number; for PWDs, name + PWD ID number + gross sales + discount + date + invoice number. Nothing in the BIR rules requires a photocopy of the ID, and nothing prohibits keeping one — but the DPA proportionality test makes an image hard to justify. **Physical presentation is legally required** for the discount (and expressly re-required on delivery for delivery orders), so a saved ID cannot replace the handover check.

### Cited Findings
- **Senior record.** RR 7-2010 §7(6): the establishment "is required to keep a **separate and accurate record of sales**, which shall include the **name of the Senior Citizen, OSCA ID, gross sales/receipts, sales discount granted, dates of transactions and invoice numbers** for every sale transaction to Senior Citizen." — [BIR RR 7-2010](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/55830)
- **"OSCA ID" was broadened.** RR 11-2015 amended §§2 and 7(6) of RR 7-2010 (as amended by RR 8-2010); the record now refers to the **"Identification Document"** rather than strictly the OSCA ID, aligning the tax rule with RA 9994's multi-ID proof list. — [BIR RR 11-2015](https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2011-2015.pdf)
- **Senior invoice content.** RR 7-2010 §7(2): "The **gross selling price and the sales discount must be separately indicated** in the official receipt or sales invoice"; §7(4): "The seller must record its sales **inclusive of the discount** granted." — [BIR RR 7-2010](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/55830)
- **PWD record.** RR 5-2017 §5.9 (name, PWD ID Number, gross sales, discount, date, invoice number); §7.4: "If there is no name of PWD and PWD ID No. indicated in the records of sales, the **input tax attributable to VAT exempt sale** ... shall be disallowed." — [BIR RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754)
- **Photocopy is required only in a different context.** RR 5-2017 §8.5(b) requires a "**photocopy of PWD Identification Card**" when a *benefactor* claims the additional dependent exemption — not when a pharmacy grants the discount. — [BIR RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754)
- **Presentation is required.** RA 9994 IRR Art. 6: the senior "**shall present** as proof of eligibility, a **valid and original** Senior Citizens' Identification Card." — [RA 9994 IRR](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **Delivery orders expressly require re-presentation.** IRR Art. 7, §3(g): for delivery orders, "**senior citizen ID card number must be given while making the order** over the telephone; the **senior citizen ID card must also be presented upon delivery** to verify the identity of the senior citizen entitled to the 20% discount. Delivery fee charged separately are not entitled to the discount." — [RA 9994 IRR](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **No double discount.** IRR Art. 9: where the senior is also a PWD, "the senior citizen shall use **either** his/her OSCA-issued ID card or PWD ID card to avail of the 20% discount." — [RA 9994 IRR](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html)
- **Burden-sharing changed in Oct 2026.** BIR **RR No. 7-2026** amends RR 7-2010 to implement DOH burden-sharing so that manufacturers/distributors/wholesalers/retailers can share the 20% senior medicine discount, with each party deducting only its share under a written agreement — **secondary/tax-commentary source; primary text not read.** — [UpCloud Accounting summary](https://upcloudaccounting.com/resources-bir-rr-7-2026-senior-citizen-discount-sharing)
- **Abuse/fraud penalties.** RA 9994 §10(c): abuse of privileges — 6 months and ₱50k–₱100k; RA 10754/RR 5-2017 §10.2.3: abuse — 6 months or ₱5k–₱50k. Forged government IDs are punishable under the Revised Penal Code (falsification, Art. 172) and estafa (Art. 315) — [Revised Penal Code](https://lawphil.net/statutes/repacts/ra1930/ra_3815_1930.html); PhilID-specific offences are in RA 11055 §19. — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html); [RR 5-2017](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754); [RA 11055](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)

### Inferences
- **A digital/photo copy is not "required" and not "prohibited."** The legal requirement is the record. If a copy is kept, it should be encrypted, access-controlled, and retained only as long as the tax/dispute need; otherwise it is excess data.
- **Physical inspection is not "just practice" for delivery orders** — the IRR mandates presentation at delivery. For over-the-counter sales, presentation is likewise mandated. A saved ID therefore cannot be the sole basis for the discount; it can pre-fill and risk-score.
- **Liability for accepting a fake ID** is primarily a **tax** risk (disallowed deduction/input tax; deficiency assessment) and a **reputational/consumer** risk, not a specific "accepting a fake ID" crime for a good-faith pharmacy. The customer commits the falsification/estafa/abuse. A pharmacy that *knowingly* honours a fake ID to help a customer evade tax could be exposed as an accomplice or under the Tax Code. **Counsel should confirm the boundary.**
- The **₱88.2 billion** fake-PWD-ID revenue-loss figure and the 2026 UPWDIS rollout mean enforcement attention is rising; a platform that can show a **verification log** (QR scan result, timestamp, branch) is better positioned than one relying on visual inspection.

### Gaps
- **Exact BIR retention period** for the sales record/booklet and invoices (NIRC §235 as amended by EOPT) — verify with tax counsel.
- Whether BIR accepts a **digital image** of the ID as supporting documentation in an audit (as opposed to the sales record) — no issuance found either way.
- The primary text of **RR 7-2026** and its interaction with the pharmacy's deduction were not read; tax adviser should confirm.
- No jurisprudence found on a pharmacy's **civil liability** to a customer denied a discount because an ID could not be verified online.

---

## 8. Existing Philippine eKYC practice: banks, telcos, GCash/Maya, e-wallets; BSP rules; NPC-approved or DICT/DTI sandbox; accredited identity providers

### Takeaway
Philippine eKYC is **BSP-regulated for financial institutions**, not for pharmacies. BSP Circular 950 (2016) introduced eKYC; Circular 1170 (2023) sets the current standard for digital ID and liveness/face matching. Banks are being pushed to integrate **NIDAS** (PhilSys eVerify) for customer due diligence. There is **no general "NPC-approved eKYC vendor" licence**; PSA maintains a **Trusted Services Provider** list inside eVerify. A pharmacy can contract an eKYC vendor, but the vendor's bank-grade checks are calibrated to AML, not to discount eligibility.

### Cited Findings
- **BSP introduced then updated eKYC.** BSP Circular No. 950 (s. 2016) first allowed electronic KYC; Circular No. 1170 (2023) is the current framework. — [BSP Circular 1170](https://www.bsp.gov.ph/Regulations/Issuances/2023/1170.pdf); [Veriff summary — secondary](https://www.veriff.com/kyc/apac-2026-guide)
- **BSP eKYC definition and standard.** BSP Circular No. 1170 (2023): e-KYC is "the process of electronically verifying the credentials of a customer"; covered institutions may use different methods but must meet standards equivalent to face-to-face identification and establish risk management. — [BSP Circular 1170](https://www.bsp.gov.ph/Regulations/Issuances/2023/1170.pdf); [BSP coverage — secondary](https://context.ph/2023/04/03/bsp-issues-new-rules-on-customer-due-diligence-e-kyc)
- **BSP pushes PhilSys.** A 2021 BSP circular/coverage states PhilSys records are accepted and that digital identification must meet "the same standards equivalent to those for face-to-face basis." — [Manila Bulletin — secondary](https://mb.com.ph/2021/09/29/bsp-enhances-e-kyc-rules)
- **BSP now moving to mandatory NIDAS integration.** The July 2026 **draft** memorandum would require BSP-supervised institutions to onboard with NIDAS via eVerify and, pending onboarding, use **National ID Check** (`everify.gov.ph/check`) to verify authenticity; it treats a NIDAS authentication confirmation as satisfying ID submission for first-time customers, and allows retention of "NIDAS confirmation records ... subject to applicable data privacy and information security requirements." — [BSP draft Memorandum](https://www.bsp.gov.ph/Regulations/Issuances%20of%20Policy%20Exposure%20Drafts/Draft-Memorandum-to-All-BSIs-on-NIDAS-Integration.pdf)
- **Existing BSP issuances already require acceptance.** The draft's footnote cites "Sections 921/921-Q of the MORB/MORNBFI ... and BSP Memoranda issued from 2021 to 2025" requiring BSIs to accept all National ID formats, and **BSP Memorandum No. M-2025-012** enjoining NIDAS integration. — [BSP draft Memorandum](https://www.bsp.gov.ph/Regulations/Issuances%20of%20Policy%20Exposure%20Drafts/Draft-Memorandum-to-All-BSIs-on-NIDAS-Integration.pdf)
- **PSA Trusted Services Providers.** eVerify's RP dashboard exposes "lists of accredited RPs and **Trusted Services Providers**." — [eVerify Terms of Use](https://everify.gov.ph/terms-of-use)
- **No NPC "approval" of eKYC vendors found.** NPC regulates PICs/PIPs and issues advisories; it does not license identity-verification vendors. NPC AO 2025-1015 treats the KYC vendor as a **personal information processor** of the bank, not as a separately licensed entity. — [NPC AO 2025-1015](https://privacy.gov.ph/wp-content/uploads/2026/01/NPC-Advisory-Opinion-No.-2025-015_Redact_redacted.pdf)
- **Telco/e-wallet practice.** SIM registration and e-wallet onboarding use ID capture + selfie/liveness, with PhilSys increasingly used as the identity source; no public, citable standard specific to telcos was located for this note. (See Gaps.)

### Inferences
- A pharmacy platform **cannot rely on "the bank already KYC'd them"** — that is a different purpose and a different lawful basis, and the bank cannot share the KYC file without a basis.
- If the platform uses an eKYC vendor, the vendor is a **PIP**; the platform remains the **PIC** and must have a written processing agreement, notice, and retention limits (NPC AO 2025-1015's checklist is a ready template).
- **BSP-grade eKYC is overkill for a 20% discount.** Tier 1-style "is this a real ID?" plus physical handover is proportionate; full biometric eKYC on every senior is likely to fail the proportionality test and to exclude the very users the law protects.
- The **PSA TSP list** is the right procurement shortlist if API-based PhilSys verification is pursued.

### Gaps
- The **PSA TSP list contents, accreditation criteria, and whether a TSP can resell PhilSys verification** to a non-RP pharmacy were not retrievable.
- No DICT "digital ID sandbox" or DTI e-commerce sandbox specific to identity verification was found.
- GCash/Maya/telco verification architectures are not publicly documented at a level that would support a citation; treat as industry practice, not authority.

---

## 9. Cross-border lessons (only where directly useful) and what they imply for the saved-ID design

### Takeaway
The directly transferable lesson is architectural, not legal: mature pharmacy/discount schemes separate **eligibility** (a status credential issued by government) from **identity** (a foundational ID), and verify eligibility **once**, then re-check **at the point of dispensing**. The Philippine equivalents are only now emerging: UPWDIS for PWD eligibility, OSCA/NSCID for senior eligibility, PhilSys for identity. A design that conflates them will either over-collect data or under-verify.

### Cited Findings
- **Philippine eligibility credentials are status-specific.** RA 9994 proof is an OSCA ID/passport/other age+citzenship document; RA 10754 proof is a PWD ID/passport/NCWDP ID; PhilSys is expressly **not** proof of eligibility (RA 11055 §14). — [RA 9994](https://lawphil.net/statutes/repacts/ra2010/ra_9994_2010.html); [RA 10754](https://lawphil.net/statutes/repacts/ra2016/ra_10754_2016.html); [RA 11055](https://lawphil.net/statutes/repacts/ra2018/ra_11055_2018.html)
- **The NPC already prescribes the "verify once, store little" pattern** for KYC: transient scan, limited fields, minimise copies/locations, contractual limits on the processor. — [NPC AO 2025-1015](https://privacy.gov.ph/wp-content/uploads/2026/01/NPC-Advisory-Opinion-No.-2025-015_Redact_redacted.pdf)
- **PSA's own design guidance** is privacy-by-design and tokenisation: "the National ID Number is fully randomized, not revealing any information about the person such as their date of birth"; private entities "cannot store the National ID Number itself" but can store tokens. — [PhilSys FAQ](https://philsys.gov.ph/faq-frequently-asked-questions/)
- **No cross-border pharmacy-specific rule was researched for this note**; the assignment limited cross-border material to what is directly useful, and the Philippine sources above already supply the design pattern. (Deliberate scope limit.)

### Inferences — concrete design for "save a verified ID on the account"
1. **Two-tier record.** (a) *Order-level compliance record*: name, ID type, ID number, discount amount, invoice no., date, branch — retained per BIR/tax. (b) *Account-level convenience record*: ID type, masked number/last 4, holder name, DOB/age band, verification method (PhilSys Check / UPWDIS QR / manual), verification timestamp, verifier (branch/user), revalidation due date. **No image by default.**
2. **Verification is a state, not a document.** Store `verification_status`, `verification_method`, `verified_at`, `verified_by`, and `evidence_ref` (e.g., a hash or audit-log ID), not the raw ID photo.
3. **Re-verify at handover every time**, at least for the first N orders or until the ID's revalidation date; the IRR requires presentation on delivery, so the app should prompt the rider/branch to confirm "ID presented and matches."
4. **PWD:** accept both old LGU IDs (until the 2029 transition ends) and new UPWDIS QR IDs; for the new ones, record the QR verification result and the alphanumeric ID. Do **not** attempt to store the disability diagnosis — that is health data and unnecessary for the discount.
5. **Senior:** accept OSCA ID, passport, driver's licence, voter's ID, SSS/GSIS, PRC, postal ID, and the digital NSCID; if a PhilID is presented, PhilSys Check can authenticate it and confirm DOB, but the discount still needs a covered-item calculation and handover check.
6. **Consent:** separate, granular, opt-in for "save my ID for future discounts"; easy withdrawal that deletes the account-level record without deleting the BIR-required order records (explain this in the notice).
7. **Vendor/processor:** if using an eKYC vendor or PSA TSP, put the NPC AO 2025-1015 clauses in the DPA (instructions-only, security, retention/deletion, audit, no repurposing of PhilSys identifiers).
8. **Do not** use PCN/PSN/PWD ID number as the primary customer key; use an internal UUID and store the government identifier as an encrypted attribute.

### Gaps
- No cross-border pharmacy discount verification model was researched in detail; the design above is inferred from Philippine law + NPC guidance, not from a foreign statute.
- Whether the **digital NSCID** is scannable/verifiable by a third party is still unresolved (same gap as §1).
- Whether **UPWDIS** will expose an API or only the `verify.alaga.ph` web page is unresolved (same gap as §2).

---

## Consolidated "send to counsel" list

1. **PhilSys RP eligibility** for a for-profit pharmacy marketplace, and the actual onboarding checklist/cost (PSA eVerify; `fpsucd@psa.gov.ph`).
2. **Whether a PhilID alone** satisfies RA 9994 §4's "other documents" requirement for the senior medicine discount, without an OSCA ID.
3. **Lawful basis for saving SPI** (ID number) on an account for future discounts: consent under §13(a) vs. "provided for by existing laws" under §13(b) (RA 9994/RA 10754/BIR rules), and whether consent can be bundled with account creation (NPC Circular 2023-04 on consent).
4. **Retention period** for the ID record and any image under BIR rules post-EOPT, and whether the platform may delete the account-level record on request while keeping the order-level BIR record.
5. **Liability boundary** for honouring a fake ID: tax deduction disallowance vs. criminal exposure for the pharmacy; whether a verification log is enough to show good faith.
6. **NPC registration/DPO obligations** for a marketplace processing SPI at scale, and whether a DPIA is required for the saved-ID feature (NPC registration rules, e.g. NPC Circular 2022-01).
7. **UPWDIS access terms** — whether `verify.alaga.ph` permits automated/API verification by a private platform, and what data it returns (NCDA).
8. **Digital NSCID acceptance/verification** — whether the eGovPH digital senior ID carries a verifiable QR and whether NCSC endorses third-party scanning (NCSC/DICT).

---

## Source-quality notes

- **Primary/official:** LawPhil texts of RA 9994 (+IRR), RA 10754, RA 10173, RA 11055, RA 11967; BIR RR 7-2010 (Supreme Court E-Library), RR 5-2017 (NCDA), RR 11-2015 (BIR CDN); FDA Advisory 2024-0429 and Circular 2025-005 (agency pages; retrieval blocked, corroborated by PNA); PhilSys Check Handbook v3.0, PhilSys FAQ, PSA/DICT press release, eVerify Terms of Use; NPC Circular 2023-03 and NPC AO 2025-1015; BSP Circular 1170 and draft NIDAS memorandum; NCDA AO 001 s. 2026 page.
- **Secondary, labelled:** Rappler (UPWDIS details), Abogado.com.ph (NCSC advisory), Respicio & Co. (OSCA expiry, PRC verification), UpCloud Accounting (RR 7-2026), Manila Bulletin/Context (BSP eKYC), Tech Pilipinas (digital NSCID).
- **Conflicts noted:** (a) PSA FAQ limits non-government RPs to social-protection NGOs while eVerify Terms of Use/BSP draft describe private entities generally; (b) PWD ID validity figures (3 vs. 5 years vs. new lifetime/5-year rules); (c) the NPC AO 2025-1015's internal citations to RA 11055 section numbers do not match the LawPhil numbering — the substance (SPI, consent, proportionality) is consistent, but quote the statute from LawPhil, not the AO.
- **Could not retrieve:** FDA pages (CloudFront 403), MB/PIA/BusinessMirror (403/CAPTCHA), `verify.alaga.ph` (JS app, no content), eVerify FAQ answers (client-side), PSA TSP list. These are recorded as gaps rather than guessed.
