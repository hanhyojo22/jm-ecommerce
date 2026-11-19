# Pharmacy Operations and Inventory Requirements for a PH Retail Chain Selling Online

Note on sources: fda.gov.ph pages returned HTTP 403 to the fetch tool. FDA statements below come from search-result excerpts of fda.gov.ph pages, from secondary legal summaries (legalresearchph.com, jur.ph, lawyerly.ph), and from news. Treat issuance numbers as needing confirmation against the primary text. International (UK GPhC) items are labelled **[INTL]**.

## Licensing baseline for online ordering and delivery (context for every section)

### Takeaway
In the PH, only an FDA-licensed physical drugstore may take online orders and deliver, and only after FDA approves "online ordering and delivery" as an additional activity on its License to Operate. Purely virtual pharmacies are not allowed. Each branch that fulfils orders needs that approval.

### Cited Findings
- FDA lists "Online ordering and delivery" among activities that "shall require application and prior approval of FDA" (FDA Circular 2014-025, implementing DOH AO 2014-0034) — [FDA Circular 2014-025, SC E-Library](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/68409)
- FDA "only allows online ordering and delivery services provided that the seller has an existing licensed pharmacy with a physical address". The patient or customer must present a prescription or e-prescription when ordering any prescription drug — FDA statement quoted in [FDA search results / FDA site](https://www.fda.gov.ph/?p=46752) (page 403 to fetch; content from the search excerpt)
- FDA Advisory No. 2019-154 (11 June 2019): only FDA-licensed pharmacies or boticas with a physical address may offer online ordering. Others must apply for it as an additional activity — [legalresearchph.com (2024)](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- DOH AO 2020-0017, Annex C: drugstores offering online ordering and delivery must declare it as an additional activity and submit an SOP and a website screenshot — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- RA 10918 IRR, Rule IV Sec. 7(A): "A virtual pharmacy without a licensed physical outlet is not allowed." IRR Sec. 14: the telepharmacy and online services of FDA-licensed outlets are subject to FDA rules. RA 10918 Sec. 5(dd) defines online pharmacy services as those provided by a duly licensed outlet over the Internet — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- RA 10918 Sec. 30: only a registered pharmacist may dispense prescription drugs and pharmacist-only OTCs, except in emergencies when none is available. Sales to the public must go through an FDA-registered outlet — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- FDA posted a **draft** "Guidelines on the Issuance of LTO for Drug Establishments Engaged in E-Pharmacy". It **excludes prescription medicines** ("This draft will not cover prescription medicines"), and ads and orders are limited to non-Rx. Other provisions:
  - only FDA-registered products may be sold
  - services must be hosted only in the Philippine domain
  - a PRC-licensed pharmacist must be appointed
  - the website must have a consumer-complaint feature on its navigation bar
  - marketplace promotions need an FDA e-promo permit
  - an SOP must be filed, and FDA does a pre-approval inspection
  — [FDA draft page](https://www.fda.gov.ph/draft-for-comments-guidelines-on-the-issuance-of-license-to-operate-for-drug-establishments-engaged-in-e-pharmacy/); details via [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- FDA publishes a public verification list "Drugstore - Online Ordering and Delivery" — [FDA verification portal](https://verification.fda.gov.ph/online_drugstorelist.php?start=541)
- Joint DTI-DOH-DA AO No. 01 s. 2008: Sec. 5 requires e-commerce sellers to disclose business identity and product information. Sec. 9 requires a complaint-handling mechanism that resolves complaints within three months — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- FDA ordered Shopee and Lazada to cease and desist from selling drug products online — [Rouse](https://www.rouse.com/magazine/news/online-ecom-platform-medicine-sales-halted-in-philippines/)

### Inferences
- System: store per branch the LTO number, its expiry, and an "online ordering and delivery approved" flag. Route orders only to branches that have the flag, and show the LTO and pharmacist details in the app. The draft also asks for a complaints entry point in the app navigation.
- Rx can be ordered online only through an existing licensed drugstore that holds the add-on approval, and the customer must present an Rx or e-Rx. The draft e-pharmacy LTO, if finalised as written, would govern OTC-only e-pharmacy and would not replace that route.

### Gaps
- Whether the e-pharmacy draft was finalised by Nov 2026, and under what circular number. fda.gov.ph blocked the fetch.
- Full text of AO 2020-0017 Annex C (SOP contents expected for online ordering).

## Pharmacist workflow for online orders (Rx verification, partial fill, records, counselling)

### Takeaway
PH law requires a pharmacist to dispense Rx items. Partial filling is allowed if the partial fill is recorded on the prescription and in the prescription book, and a computerised log has been accepted as equivalent. Dangerous drugs follow a separate S2, yellow-Rx and Dangerous Drugs Book regime. PH sources found say little about counselling at a distance, so UK GPhC guidance is the best available model for ID checks, counselling channels and decision records.

### Cited Findings
- RA 10918 Sec. 33 "Filling and Partial Filling of Prescription" allows dispensing a quantity less than prescribed, subject to dispensing guidelines — [RA 10918 text (Scribd)](https://www.scribd.com/document/540490618/RA-10918); [Digest.ph summary](https://www.digest.ph/laws/philippine-pharmacy-act); official text at [Senate](https://legacy.senate.gov.ph/republic_acts/ra%2010918.pdf)
- Under the dispensing guidelines (per a pharmacy-law module), a partial fill requires writing on the prescription the date, the quantity served, the balance unserved, and the drugstore's name and address. The prescription goes back to the buyer after the partial fill is recorded in the prescription book. The same module says partial filling is not allowed for dangerous drugs (List A / S-2) — [Scribd module notes (secondary)](https://www.scribd.com/document/829065638/Module-4-notes)
- BFAD Memorandum Circular No. 8 (1990): "A computerized data base containing the same information as the Prescription Book will be considered substantial compliance". It allowed entries to be made no later than the end of each quarter (historical; may be superseded) — [lawyerly.ph](https://dev.lawyerly.ph/laws/41646)
- E-prescriptions: FDA Circular 2020-037 reissued guidelines on electronic prescriptions for individuals vulnerable to COVID-19 — [FDA](https://www.fda.gov.ph/fda-circular-no-2020-037-reissuance-of-the-guidelines-on-the-implementation-of-the-use-of-electronic-means-of-prescription-for-drugs-for-the-benefit-of-individuals-vulnerable-to-covid-19/) (contents not retrieved)
- Watsons PH online: "For Dispensary items, a Pharmacist will need to validate the RX orders" — [Watsons PH terms](https://www.watsons.com.ph/terms-conditions)
- Dangerous drugs:
  - The S2 is a special PDEA license that lets medical practitioners prescribe dangerous drugs — [Inquirer](https://newsinfo.inquirer.net/2081621/ddb-s2-physicians-can-prescribe-dangerous-drugs-using-regular-forms)
  - DDB Board Regulation / Resolution No. 10 s. 2025 lets S2 doctors use ordinary prescriptions in triplicate instead of yellow forms. It took effect 19 July 2025 and is valid until 30 June 2026 unless repealed. Sanctions under RA 9165 remain — [Inquirer](https://newsinfo.inquirer.net/2081691/ddb-no-yellow-rx-needed-until-2026); [Manila Bulletin](https://mb.com.ph/2025/07/11/ddb-eases-prescription-restrictions-on-dangerous-drugs)
  - Recording in the Dangerous Drugs Record Book is a listed step in dispensing — [BFAD MC 7, 1990](https://dev.lawyerly.ph/laws/41644). Recording must be in the Dangerous Drugs Book or an equivalent system under RA 9165 — [Scribd module (secondary)](https://www.scribd.com/document/829065638/Module-4-notes)
  - A retail pharmacist who compounds dangerous-drug preparations needs an S-3 license (DDB Regulation No. 4, 1991) — [jur.ph](https://jur.ph/law/summary/amending-board-regulation-4-drug-establishments-s-license-requirements)
- **[INTL] GPhC (UK), Feb 2025, "Guidance for registered pharmacies providing pharmacy services at a distance, including on the internet"** — [PDF](https://assets.pharmacyregulation.org/files/2025-02/gphc-guidance-registered-pharmacies-providing-pharmacy-services-distance-february-2025.pdf):
  - "For sales or supplies of P and POM medicines, make sure pharmacy staff can check that the person receiving pharmacy services is who they claim to be by carrying out an identity check appropriate for the medicine". Methods include credit-reference databases or photo-ID verification services.
  - Records must include:
    - the accountable staff for each part of the service
    - the advice given
    - the key points behind a decision to make or refuse a supply
    - "the person's consent to use a particular delivery method, the delivery method used, the date of dispatch of the medicine and the record of successful delivery"
    - complaints and the steps taken
  - "When pharmacy staff do not see the person receiving care face-to-face, consider using methods of communication that encourage discussion between the person and pharmacy staff, including the pharmacist." Patients must be told how to contact the pharmacist if they have problems.
  - Staff should watch for a customer under duress or domestic abuse and consider whether the customer has capacity.
  - Online pharmacies must "carry out an appropriate identity check" — [Pharmaceutical Journal](https://pharmaceutical-journal.com/article/news/online-pharmacies-required-to-verify-patient-id-before-prescribing-under-new-gphc-guidelines)

### Inferences
- Pharmacist verification queue per Rx order. Checklist fields:
  - prescriber name
  - PRC license no.
  - PTR no. and S2 no. (for dangerous drugs)
  - Rx date
  - patient name, age and address
  - generic name, strength, dosage form, quantity and sig
  - a verified-by pharmacist with PRC no. and timestamp
  - outcome: approve, partial, reject (with reason) or query prescriber
  The specific checklist fields are standard PH practice under RA 10918 and the Generics Act. The sources found do not enumerate them, so confirm against the dispensing guidelines.
- An electronic dispensing log (the prescription book). Each entry: date, Rx serial, patient, prescriber, item, batch, quantity dispensed, balance, pharmacist. Under BFAD MC 8 a computerised book with the same data satisfies the requirement.
- Partial fill: record the quantity served and the balance. Generate an annotation for the original Rx (date, quantity served, balance, branch name and address) so the pharmacist can write it on the paper Rx on handover. Track the open balance for later fills. Block partial fill and refill for dangerous drugs.
- Dangerous drugs: the safest design is to exclude them from delivery and require in-branch pickup with the original triplicate or yellow Rx surrendered and entered in the DDB. This is a design inference; no PH source found explicitly bans delivery of dangerous drugs.
- Counselling: a pharmacist chat or call channel on each Rx order, logged with timestamp, pharmacist and summary. Follows the GPhC model; no PH-specific source found.
- Fake or altered Rx: a reject reason code, an image audit trail, and a flag on the customer account. No PH source found on the reporting procedure.

### Gaps
- No primary source found on whether the original paper Rx must be surrendered for delivery orders, or whether a photo or upload plus presentation on delivery is enough. FDA says only "present a prescription or e-prescription upon ordering".
- Current FDA/PRC dispensing guidelines (retention period for the Rx book, refill rules) not retrieved.
- Whether the DDB 2025 ordinary-Rx relaxation was extended past 30 June 2026 is unconfirmed.
- PPhA or PH Board of Pharmacy guidance on tele-counselling not found.
- PH procedure for reporting fake or altered prescriptions (to FDA, PDEA or PRC) not found.

## Inventory: batch/expiry, FEFO, recalls, quarantine, controlled registers, counts, storage

### Takeaway
PH drugstores are inspected for compliance with Good Distribution and Storage Practice. The PH adopted WHO GDP (Annex 5) and GSP (Annex 9) by reference, so batch and expiry control, segregation of rejected stock, and temperature control are expected. Recalls are lot-specific. Retailers must stop sale of the affected lot and return sealed stock to the marketing authorisation holder. No PH rule found requires pharmacies to notify the individual customers who bought a recalled lot.

### Cited Findings
- FDA Circular 2014-025: GDP and GSP govern compliance for drugstores, pharmacies and boticas, and licensed outlets are routinely inspected for it — [jur.ph summary](https://jur.ph/law/summary/guidelines-implementation-new-rules-drugstore-licensing); [FDA Circular 2014-025](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/68409)
- DOH-FDA adopted the WHO Good Distribution Practice (Annex 5) and Good Storage Practice (Annex 9) guides, which "shall be adopted automatically as the current versions are updated" — [jur.ph](https://jur.ph/law/facts/adoption-implementation-who-good-distribution-storage-practices)
- FDA has a draft circular on GDSP (Good Distribution and Storage Practice) deficiency classification — [FDA draft PDF](https://www.fda.gov.ph/wp-content/uploads/2024/04/Draft-Circular_GDSP-Deficiencies.pdf) (contents not retrieved)
- Recall framework:
  - The governing issuance is FDA (BFAD) Bureau Circular No. 8 s. 2001 (secondary source; verify against the circular).
  - A recall is either firm-initiated or FDA-ordered.
  - The marketing authorisation holder notifies consignees with the product identity, batch or lot no., and the reason for the recall.
  - The Director General may order a recall for a safety risk or gross deception.
  — [SlideShare overview (secondary)](https://www.slideshare.net/slideshow/product-recall-overview/53326270)
- Typical FDA recall advisories instruct "distributors, hospitals, retailers, pharmacies, or clinics that have the affected lot" to discontinue distribution, sale and use, and to return sealed stocks to the MAH. In some cases FDA directed all drug retail outlets to stop dispensing — [FDA advisory](https://www.fda.gov.ph/?p=70221); [Inquirer](https://newsinfo.inquirer.net/2034125/fda-orders-recall-of-antibiotics-drug-used-vs-pneumonia-bronchitis-uti/amp); [Inquirer](https://newsinfo.inquirer.net/1920236/fda-orders-recall-of-chemotherapy-injectable/amp)
- Dangerous drugs register: a Dangerous Drugs Book (or equivalent system) under RA 9165 — [BFAD MC 7](https://dev.lawyerly.ph/laws/41644); [Scribd module](https://www.scribd.com/document/829065638/Module-4-notes)
- Returned medicines "should not be casually placed back on the shelf for resale". They should be segregated and investigated, then returned to the supplier or disposed of (commentary, no issuance cited) — [Respicio commentary](https://www.respicio.ph/commentaries/rules-on-the-return-of-medicines-by-consumers-to-manufacturers-and-pharmacies)

### Inferences
- Stock must be held at batch level per branch (product, batch no., expiry, quantity, location). Without it, a branch cannot comply with a lot-specific recall or trace buyers. Order items must record the batch dispensed.
- Picking should follow FEFO (first expiry, first out), and the system should suggest the batch to pick. This is standard WHO GSP practice; the GSP text was not retrieved here.
- Near-expiry: a configurable alert window (e.g. 90, 60 or 30 days), and a block on selling expired batches. A further block on dispensing a batch that would expire before the course of therapy ends is a design choice, not a sourced rule.
- Recall workflow:
  1. Head office enters the recall (product, lot(s), FDA advisory no.).
  2. The system auto-blocks sale of those lots at every branch.
  3. Branches move the stock to a quarantine location and record the count.
  4. Stock is returned to the MAH with a document reference.
  5. The system generates a list of customers who bought the lot, from order-item batch data, for optional outreach. No PH mandate was found, but it is good practice and cheap once batches are recorded.
- Stock statuses: available, quarantined (damaged, expired, recalled or returned), and disposed. A reason code and audit entry on every move. Quarantined stock is never sellable.
- Cycle counts and reconciliation: periodic counts per branch with variance reports. DDB items need a perpetual running balance per transaction. These are good practice; no PH frequency rule was retrieved.

### Gaps
- Full text of Bureau Circular 8 s. 2001, or any newer recall circular. In particular, whether retailers must keep traceability to the patient and how fast they must act on recall classes.
- WHO GSP Annex 9 specifics (FEFO wording, records retention) were not fetched in this session.
- No PH rule found on cycle-count frequency or retention period for inventory records.

## Cold chain (2–8°C: insulin, vaccines, biologics)

### Takeaway
PH drugstores that carry vaccines, biologics or other temperature-sensitive products must meet cold-chain requirements, now in FDA Circular 2021-003 (Revised Cold Chain Management guidelines). The sources found say nothing on whether such items may be home-delivered. Leading chains exclude them from returns.

### Cited Findings
- "Cold-chain compliance is required for drugstore/pharmacy/botica and similar outlets carrying vaccines, biologics, and other temperature-sensitive products" (FDA Circular 2014-025) — [jur.ph](https://jur.ph/law/summary/guidelines-implementation-new-rules-drugstore-licensing)
- FDA Circular 2021-003, "Revised Guidelines on the Cold Chain Management of Pharmaceutical Products and Establishments", is cited in FDA's GDSP draft — [FDA draft PDF](https://www.fda.gov.ph/wp-content/uploads/2024/04/Draft-Circular_GDSP-Deficiencies.pdf)
- Refrigerated storage for vaccines is +2 to +8°C (workshop slide, not regulation) — [ANSI/US-PH Cold Chain Workshop slides](https://share.ansi.org/Shared%20Documents/Standards%20Activities/International%20Standardization/Indo-Pacific%20STCP/U.S.-Philippines%20Cold%20Chain%20Workshop/Workshop%20Materials/Session%203_Allan%20Javier_ANSI_Cold%20Chain%20Innovation%20in%20the%20Healthcare%20Industry.pdf)
- Mercury Drug excludes "Biologicals, parenterals, injections, vaccines, and other products requiring special storage conditions" from returns — [Mercury Drug return policy](https://mercurydrug.com/return-policy.html)
- **[INTL] GPhC:** risk assessment must cover the suitability and timescale of the delivery method "for example, for refrigerated medicines and controlled drugs", and the suitability of packaging ("testing or auditing packaging that is tamper-proof and/or temperature controlled") — [GPhC 2025 PDF](https://assets.pharmacyregulation.org/files/2025-02/gphc-guidance-registered-pharmacies-providing-pharmacy-services-distance-february-2025.pdf)

### Inferences
- Product attribute: storage condition (room temperature / 2–8°C / frozen). Cold-chain items should:
  - be fulfilled only by branches that have a monitored fridge
  - be limited to pickup, or to delivery by pharmacy staff or a vetted courier with validated insulated packaging and a time limit
  - be non-returnable
- Branch temperature log (fridge min/max readings, excursions, and the action taken) is a likely GSP inspection item. Not confirmed from a primary text.

### Gaps
- Text of FDA Circular 2021-003 (monitoring frequency, packaging validation, delivery provisions) not retrieved.
- No PH source found that allows or restricts last-mile home delivery of insulin or vaccines.

## Delivery rules (who delivers, packaging, ID on delivery, area limits)

### Takeaway
No PH primary source found sets rules for riders, couriers, sealed packaging or ID checks on delivering medicines to a patient. The binding PH constraints are upstream: a licensed physical branch with FDA approval for online ordering and delivery, a pharmacist dispensing Rx items, and an Rx presented. The SOP filed under AO 2020-0017 is where the chain must define its delivery controls. UK GPhC guidance gives a usable template.

### Cited Findings
- Online ordering and delivery is an FDA-approved add-on activity of a licensed physical pharmacy. The application requires an SOP — [FDA Circular 2014-025](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/68409); [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- Only pharmacists can dispense medicines — [Inquirer, "FDA: Only pharmacists can dispense meds"](https://newsinfo.inquirer.net/?p=1942278)
- The pure e-pharmacy draft requires a declared warehouse and office open to inspection, and that the warehouse be used only for storing, distributing and dispensing registered products — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)
- **[INTL] GPhC 2025**, section 4.3 "Supplying medicines safely": the owner must show how
  - "the package is tracked and monitored to make sure it reaches the right person, and to monitor any unexpected interruptions in delivery"
  - "the terms, conditions and restrictions of the carrier are checked"
  - packaging is tamper-proof and/or temperature-controlled where needed
  - "staff are trained and their competence is assessed"
  - "third-party providers are monitored"
  Records must hold the delivery method, dispatch date and "record of successful delivery" — [GPhC PDF](https://assets.pharmacyregulation.org/files/2025-02/gphc-guidance-registered-pharmacies-providing-pharmacy-services-distance-february-2025.pdf)

### Inferences
- A third-party courier (Grab, Lalamove) is not prohibited by any PH source found. It only carries a sealed, already dispensed package, while dispensing (pharmacist verification and release) happens at the licensed branch. The risk sits with the chain's SOP.
- System capabilities:
  - Packages are sealed and labelled at the branch (order no., patient name, pharmacist who released).
  - The pharmacist release step must come before handover to the rider.
  - The courier and rider ID are recorded.
  - Proof of delivery is captured: recipient name, photo or signature, timestamp.
  - For Rx items, an optional OTP or ID match to the patient on delivery (GPhC-style).
  - The failed-delivery path returns stock to quarantine, never directly to sellable stock.
- Delivery area: limit it to a radius or zones per branch. This is a design choice, since no PH distance limit was found. Exclude dangerous drugs and possibly cold-chain items from rider delivery.

### Gaps
- No FDA circular found on third-party couriers for medicines, tamper-evident packaging, or ID checks at the door.
- No PH rule found on delivery area limits.
- Grab/Lalamove pharmacy partnership terms not found.

## Returns policy for medicines

### Takeaway
Leading PH practice is that sold medicines are not resellable. Mercury Drug accepts returns only for defective items with the original receipt and packaging, excludes cold-chain, injectable, compounded, tampered and expired items, and disposes of all returns. Watsons PH online allows return requests within 7 days of receipt. Legal commentary lists wrong item, damaged, expired, recalled and counterfeit as valid grounds.

### Cited Findings
- Mercury Drug:
  - returns or exchanges only if "the purchased item is defective"
  - the item must be in its original packaging or condition, with the original official receipt
  - excluded: biologicals, parenterals, injections, vaccines, special-storage items, tampered seals or packaging, compounded items, expired items, and items damaged by buyer mishandling
  - returned items are disposed of and never resold or restocked
  — [Mercury Drug return policy](https://mercurydrug.com/return-policy.html)
- Watsons PH online (home delivery and Click & Collect): requests "will only be entertained within seven (7) days of receipt". COD refunds are credited as an e-voucher to the online account — [Watsons PH return-refund](https://www.watsons.com.ph/return-refund) (search excerpt; page blocked from fetch)
- PH default: no voluntary returns or exchanges for medicines — [lawyer-philippines.com](https://www.lawyer-philippines.com/articles/return-policy-for-purchased-medicine-philippines-consumer-law). Pharmacies may refuse change-of-mind returns. Recognised grounds are expired, wrong medicine, wrong strength or form, damaged or tampered, recalled, counterfeit, or short quantity — [Respicio commentary](https://www.respicio.ph/commentaries/rules-on-the-return-of-medicines-by-consumers-to-manufacturers-and-pharmacies)
- **[INTL]** UK online pharmacies generally do not accept medicine returns after dispatch, and treat returned medicines as pharmaceutical waste — [Pharmacy2U](https://www.pharmacy2u.co.uk/shop/returns); [The Independent Pharmacy](https://www.theindependentpharmacy.co.uk/returns-policy)

### Inferences
- Returns module:
  - Reason codes: wrong item, damaged on arrival, defective, expired on arrival, recalled, short quantity.
  - A time window (e.g. 7 days, matching Watsons).
  - Photo evidence.
  - A pharmacist approves each return.
  - Returned medicine goes to quarantine and then disposal or return to supplier, never back to sellable stock.
  - Cold-chain, injectable and compounded items are non-returnable except for a dispensing error or recall.

### Gaps
- Southstar, Rose Pharmacy and MedExpress return terms were not retrieved.
- No FDA or DOH issuance found that explicitly prohibits resale of returned dispensed medicine. The practice comes from chain policy and commentary.

## Pricing operations (branch pricing, MDRP/MRP, Rx promos)

### Takeaway
Drugs under price regulation (EO 104 s. 2020, expanded by EO 155 s. 2021) have a legal maximum retail price at every retail outlet. The system must cap selling prices at the MRP regardless of branch. Advertising and promotion of prescription drugs to the public is prohibited, which restricts Rx promos.

### Cited Findings
- EO 104 (Feb 2020) set maximum retail and/or wholesale prices for 87 molecules or 133 formulas (some sources say 86). It covers all public and private retail outlets, including drugstores, hospital pharmacies, convenience stores and supermarkets — [BusinessWorld](https://www.bworldonline.com/?p=279191); [Philstar](https://www.philstar.com/headlines/2020/02/19/1994297/doh-warns-violators-drug-price-ceiling)
- Labels must show the MRP, preceded by "RETAIL PRICE NOT TO EXCEED" and "UNDER DRUG PRICE REGULATION", on a red strip — [jur.ph summary of EO 104](https://jur.ph/law/summary/further-improving-access-to-healthcare-through-the-regulation-of-prices-in-the-retail-of-drugs-and-medicines)
- EO 155 (7 Dec 2021) added 34 molecules or 71 formulas (Annex A). The list is subject to DOH review — [Lawphil EO 155](https://lawphil.net/executive/execord/eo2021/eo_155_2021.html)
- DOH deploys pharmacists to monitor prices at drugstores. Violators face fines and prison terms — [Philstar](https://www.philstar.com/headlines/2020/02/19/1994297/doh-warns-violators-drug-price-ceiling)
- FDA: promoting and advertising prescription drugs to the public is prohibited and punishable — [FDA Advisory 2022-0108](https://www.fda.gov.ph/fda-advisory-no-2022-0108-reiteration-of-warning-against-the-promotion-and-advertisement-of-prescription-drug-products/)
- The e-pharmacy draft requires an FDA e-promo permit for marketplace promotions — [legalresearchph.com](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)

### Inferences
- Product fields: an "under price regulation" flag and its MRP. Validation: no branch price, promo or bundle may exceed the MRP. Head office maintains the MRP list as DOH updates it.
- Branch-level pricing is legally allowed below the MRP. A single chain-wide price is simpler and avoids customer confusion when orders route to the nearest branch. That is a business choice.
- Rx products: exclude them from banners, push promos and the "deals" carousel. Do not advertise discounts on Rx items, and make promo engines skip Rx SKUs. Statutory senior citizen and PWD discounts (20%) still apply at checkout. Statutory discounts are well-known PH law but were not sourced in this session.

### Gaps
- Current EO 104/155 list status in 2026 and any newer price-regulation EO not checked.
- Senior citizen and PWD discount mechanics (RA 9994, RA 10754, and the purchase booklet or Rx requirements) not researched here.
- No source found on whether the Universally Accessible Cheaper and Quality Medicines Act (RA 9502) adds retail price-posting duties.
