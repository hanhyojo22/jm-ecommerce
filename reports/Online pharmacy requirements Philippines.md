# Launching a compliant Philippine pharmacy chain app

Your app already has most of what Philippine law expects from a chain that takes medicine orders online. Philippine rules treat online ordering as an extension of a licensed physical drugstore, never as a separate "virtual pharmacy". The nearest-branch model, the pharmacist approval step and branch pickup line up with that. The gaps that matter before launch are mostly not customer features. They are records and controls a regulator, the BIR or a court will ask for:

- each branch's FDA license with the "online ordering and delivery" add-on, shown in the app;
- a product catalog that only lists FDA-registered products, carries a drug classification and enforces price caps;
- a pharmacist record that logs who approved each prescription and how much was dispensed;
- correct senior citizen and PWD discount math with VAT removed first;
- batch and expiry tracking per branch so a lot recall can be acted on;
- privacy handling for prescriptions as sensitive health data;
- BIR-compliant invoices.

The most urgent date is **31 December 2026**. By then, e-commerce sellers must issue electronic invoices through the BIR Electronic Invoicing System. That is about six weeks from the date of this report. Two other points need a decision from counsel or the FDA before you design around them. First, whether prescription items may be ordered online at all, because a pending FDA draft would limit e-pharmacy to non-prescription items. Second, whether a photo of a prescription is enough or the original paper must be seen at handover. In this report, **[Confirm with counsel]**, **[Confirm with FDA]** and **[Confirm with accountant]** mark items where the research found only drafts, secondary summaries or no source at all.

A note on sources: the FDA website blocked automated reading. Several FDA rules below therefore come from legal-research summaries, news reports and search excerpts rather than the issuance text itself. The report says so where it matters.

## Every branch needs its own license, with online ordering approved

### No license, no online sales: the "virtual pharmacy" ban

The Pharmacy Act (RA 10918) defines online pharmacy services as services provided by a "duly-licensed pharmaceutical outlet over the Internet". Its implementing rules state flatly that **"a virtual pharmacy without a licensed physical outlet is not allowed"** ([LegalResearchPH](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)). FDA Advisory 2019-154 adds that only FDA-licensed pharmacies with a physical address may offer online ordering, and only after the FDA approves it as an **additional activity** on their License to Operate (LTO) ([LegalResearchPH](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/); [BusinessWorld](https://www.bworldonline.com/?p=236563)). The current licensing rule, DOH AO 2020-0017, requires drugstores offering online ordering and delivery to declare it on the license application. They must submit an SOP and a screenshot of the ordering site or app ([DOH AO 2020-0017](https://law.upd.edu.ph/wp-content/uploads/2020/05/DOH-AO-No-2020-0017.pdf)). The FDA keeps a public list of drugstores approved for "Online Ordering and Delivery" ([FDA verification portal](https://verification.fda.gov.ph/online_drugstorelist.php?start=541)).

For the app, that means **every branch that fulfils app orders needs its own LTO with the online add-on**. The order must always be tied to a specific licensed branch. Your nearest-branch routing already does this. What is missing is a check that refuses to route an order to a branch whose license lacks the add-on or has expired. Selling a drug without an LTO is prohibited under the FDA Act (RA 9711). The E-Commerce Act applies the same penalties when the violation happens online ([LegalResearchPH](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)). The FDA has shown it will act. It ordered Shopee and Lazada to stop selling medicines, and 2,202 violating posts were taken down between 2020 and February 2022 ([Rouse](https://www.rouse.com/magazine/news/online-ecom-platform-medicine-sales-halted-in-philippines/)).

### The e-pharmacy draft that could change the rules for prescription items

The FDA has published a **draft** "Guidelines on the Issuance of LTO for Drug Establishments Engaged in E-Pharmacy", notified to the WTO in 2022. It says ads, offers and online orders "shall be limited only to non-prescription drugs". It would also require:

- only FDA-registered products;
- hosting "only in the Philippine domain";
- an appointed PRC-licensed pharmacist accountable for compliance;
- a **consumer complaint feature in the site's navigation bar**;
- an FDA e-promo permit for marketplace promotions;
- a pre-approval inspection.

([FDA draft page](https://www.fda.gov.ph/draft-for-comments-guidelines-on-the-issuance-of-license-to-operate-for-drug-establishments-engaged-in-e-pharmacy/); [WTO notification](https://members.wto.org/crnattachments/2022/TBT/PHL/22_2295_00_e.pdf).)

It was still a draft in July 2024, and the research found no final version as of November 2026 ([LegalResearchPH](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)). Bills to amend RA 10918 for e-pharmacies, Senate Bill 2512 and the House "eBotika Act" HB 6163, were pending with no evidence of enactment.

This leaves prescription items in a grey area. The binding rule lets licensed drugstores take online orders. An FDA statement quoted in search results says the customer must "present a prescription or e-prescription" when ordering a prescription drug ([FDA](https://www.fda.gov.ph/?p=46752)). Nothing found in the binding rules bans prescription items, but the draft would. The safest design, which your app is close to already, works in three steps:

1. Treat a prescription item as a *request* backed by an uploaded photo.
2. Have a pharmacist verify it.
3. Have the original prescription sighted at pickup or on delivery.

**[Confirm with counsel and FDA]** whether online ordering of prescription items is allowed under your branches' licenses, and whether "Philippine domain" hosting means a .ph domain name or servers located in the Philippines.

### What the app must show about who you are

Two laws require public disclosure. The older Joint DTI-DOH-DA AO 01 s.2008 requires online sellers to disclose:

- business identity and registration;
- principal address;
- owners or directors;
- contact details and licenses.

It also requires complaints to be handled within three months ([LegalResearchPH](https://legalresearchph.com/2024/07/13/buying-medicines-online-are-there-enough-regulations-to-protect-the-publics-health/)). The Internet Transactions Act (RA 11967) has been fully enforced since 20 June 2025. It requires e-retailers to publish on their homepage their corporate and trade names, physical address, phone and email, and to register with the DTI E-Commerce Bureau ([RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html); [SunStar](https://www.sunstar.com.ph/davao/dti-begins-full-enforcement-of-e-commerce-law-liability-rules-2)). No source found requires the LTO number on screen specifically **[Confirm with FDA]**. Still, showing each branch's LTO number, address, contact details and pharmacist on the branch page costs little and matches what the draft expects.

## Pharmacists must own every prescription decision, and the app must prove it

### Who may dispense, and what to record

RA 10918 Sec. 30 says **prescription drugs and "pharmacist-only" OTC medicines may be dispensed only by a registered, licensed pharmacist**. Emergencies are the only exception ([RA 10918](https://legacy.senate.gov.ph/republic_acts/ra%2010918.pdf)). Your pharmacist flag and approve/reject step cover the decision. The gap is the record behind it.

A 1990 FDA circular accepted that "a computerized data base containing the same information as the Prescription Book will be considered substantial compliance" ([BFAD MC 8, lawyerly.ph](https://dev.lawyerly.ph/laws/41646)). Your audit log can become an **electronic prescription book**. For that, each approval needs to capture:

- the prescriber's name and PRC license number;
- the patient;
- the prescription date;
- each item's generic name, strength and quantity;
- the batch dispensed;
- the approving pharmacist's name and PRC number;
- a timestamp;
- a reason code for rejections.

The research did not find the official list of fields, so **[Confirm with counsel]** against the current dispensing guidelines. Watsons PH works the same way: "a Pharmacist will need to validate the RX orders" ([Watsons PH terms](https://www.watsons.com.ph/terms-conditions)).

### Partial fills

Sec. 33 allows **partial filling** of a prescription ([RA 10918](https://legacy.senate.gov.ph/republic_acts/ra%2010918.pdf)). A secondary pharmacy-law module describes how it is recorded. The pharmacist writes on the prescription the date, the quantity served, the balance left and the drugstore's name and address. The fill is logged in the prescription book, and the paper goes back to the buyer ([Scribd module notes](https://www.scribd.com/document/829065638/Module-4-notes)). Your app needs a "quantity served / balance left" field on Rx items, plus a printable annotation for the pharmacist to copy onto the original. Whether the original paper must be seen for delivery orders is not settled by any source found **[Confirm with FDA]**. The COVID-era circulars that allowed e-prescriptions lapsed in September 2021 ([Philstar](https://philstar.com/nation/2020/12/26/2066257/fda-extends-use-electronic-prescriptions)). The conservative reading is that an uploaded photo is pre-verification only.

### Dangerous drugs (S2) and antibiotics

**Dangerous drugs should be blocked from online ordering entirely.** They include many sleeping pills, strong painkillers and ADHD drugs. They need a doctor holding a PDEA S2 license, a special DOH-issued yellow prescription form, and an entry in a Dangerous Drugs Book. The DDB reimposed the ban on e-prescriptions for them in August 2023 ([Philstar](https://philstar.com/headlines/2023/08/05/2286404/ban-reimposed-e-prescriptions-dangerous-drugs)). DDB Board Regulation 10 s.2025 temporarily allowed ordinary triplicate prescriptions instead of yellow forms. Even under it, the original had to be surrendered to the dispensing pharmacy. It was valid only until **30 June 2026** ([Manila Bulletin](https://mb.com.ph/2025/07/11/ddb-eases-prescription-restrictions-on-dangerous-drugs); [Inquirer](https://newsinfo.inquirer.net/2081691/ddb-no-yellow-rx-needed-until-2026)). No extension was found, so assume the yellow-form rule applies again **[Confirm with counsel]**. No source explicitly bans delivery of dangerous drugs. However, an uploaded photo cannot satisfy a rule that the original form be surrendered. These products should therefore be marked "in-store only", with at most a stock check in the app.

**Antibiotics** are prescription-only. The FDA has warned that selling them without a doctor's prescription is "strictly prohibited" ([Philstar](https://www.philstar.com/metro/2013/02/07/905742/fda-report-drugstores-selling-antibiotics-without-prescription)). A 2020 DOH rule required antibiotics to be dispensed for the full course ([COVID Law Lab](https://www.covidlawlab.org/wp-content/uploads/2021/03/Philippines_2020.03.19_Regulation_Dispensing-of-Medicines-for-PWD-relaxed-from-one-month-rule-to-three-months-except-antibiotics_EN.pdf)). Keep them under mandatory prescription verification and out of all promotions.

### Generic names, price caps and the ban on promoting prescription drugs

The Generics Act (RA 6675) has three duties that translate directly into screens:

- the **generic name must appear prominently, immediately above the brand name**;
- drug outlets must **inform buyers of all other products with the same generic name and their prices**;
- outlets must post a list of those products.

Violations cost P5,000 to P10,000, with possible LTO suspension, and responsible officers face jail terms of six months to a year ([RA 6675, Lawphil](https://lawphil.net/statutes/repacts/ra1988/ra_6675_1988.html)). On a product page, the digital equivalent is to show the generic name first and a "same generic, other brands and prices" list. Global apps such as Tata 1mg market this as a "cheaper alternative" feature ([1mg](https://www.1mg.com/otc/beneficiale-capsule-otc63513)). In the Philippines, part of it is a legal duty.

Price caps also apply. EO 104 s.2020 and EO 155 s.2021 set **maximum retail prices** on regulated drugs at every retail outlet. Labels must carry "RETAIL PRICE NOT TO EXCEED" and "UNDER DRUG PRICE REGULATION" ([EO 104, Lawphil](https://lawphil.net/executive/execord/eo2020/eo_104_2020.html); [EO 155, Lawphil](https://lawphil.net/executive/execord/eo2021/eo_155_2021.html)). Your pricing must refuse any branch price, promo or bundle above the cap. Whether the list changed in 2024 to 2026 was not checked **[Confirm with counsel]**.

The FDA prohibits **promoting or advertising prescription drugs to the public** ([FDA Advisory 2022-0108](https://www.fda.gov.ph/fda-advisory-no-2022-0108-reiteration-of-warning-against-the-promotion-and-advertisement-of-prescription-drug-products/)). Banners, "deals" carousels, push promos and featured placements must skip prescription items. Statutory senior and PWD discounts still apply at checkout. The research did not cover whether public **customer reviews on prescription products** count as promotion. Raise this with counsel before reviews are switched on for Rx items **[Confirm with counsel]**.

### Classifying every product

All of this depends on one catalog field the app may not yet have: **a drug classification**. The options are prescription, pharmacist-only OTC, general-sale OTC, dangerous drug and non-medicine. DOH AO 2024-0013, effective December 2024, reportedly splits non-prescription products into pharmacist-only and general sale ([Cruz Marcelo](https://cruzmarcelo.com/?p=21322)). Only FDA-registered products may be offered online ([WTO notification](https://members.wto.org/crnattachments/2022/TBT/PHL/22_2295_00_e.pdf)). Each product should therefore also store its FDA registration (CPR) number, and the catalog should refuse to list a product without one.

## Discounts, VAT and invoices follow exact formulas, and one deadline is weeks away

### Senior citizens: 20% off after removing VAT

Seniors get **20% off plus VAT exemption on medicines**. The BIR formula removes VAT first, then takes the discount: price / 1.12, then 20% off that ([Davao City OSCA copy of RMC 38-2012](https://seniorcitizen.davaocity.gov.ph/index.php/republic-act-9994-page-4)). For example, a medicine priced at **P112.00 becomes P100.00 without VAT, less P20.00 discount, so the senior pays P80.00**. A flat 20% off the P112 price would be wrong, and POS vendors call that non-compliant ([Divergent POS](https://www.divergentpos.com/resources/educational/bir-compliance-discounts/)).

The booklet requirement is gone for seniors. DOH AO 2024-0017 (December 2024) and FDA Circular 2025-005 (September 2025) removed the purchase booklet. Seniors still need an OSCA or senior ID and a doctor's prescription ([Philstar](https://www.philstar.com/headlines/2024/12/23/2409368/one-less-hassle-senior-citizens-booklet-rule-medicines-scrapped); [SunStar](https://www.sunstar.com.ph/manila/fda-seniors-no-longer-need-purchase-booklets-for-meds)).

Online orders qualify. A 2022 DTI-led circular extended the discount to online and phone orders. The customer declares senior or PWD status before ordering and shows the ID on delivery ([ABS-CBN](https://www.abs-cbn.com/business/08/02/22/dti-extends-senior-and-pwd-discounts-to-online-buys)). Medicines are not subject to the P1,300 weekly cap that applies to groceries ([Manila Standard](https://manilastandard.net/?p=314249044)).

The invoice must show:

- the gross price and the discount on separate lines;
- "VAT Exempt Sale".

The store must also keep a **separate sales book** for these sales, recording the senior's name, ID number, gross amount, discount, date and invoice number ([jur.ph RR 7-2010](https://jur.ph/law/summary/implementing-tax-privileges-republic-act-9994-senior-citizens)). Whether the delivery fee is discounted is unclear. One 2021 report quoted advocates saying it is not ([Philstar](https://philstar.com/headlines/2021/05/31/2102109/rules-online-discounts-pwds-seniors-still-pending-months-after-request)). The safe default is no discount on the fee **[Confirm with accountant]**.

### PWDs and solo parents: different proof rules

**PWDs** get the same 20% plus VAT exemption, computed the same way. Unlike seniors, they **still need the purchase booklet**. DOH AO 2017-0008 requires it. In January 2025 the FDA said it was only "studying" whether to extend the booklet-free rule to PWDs ([Philstar](https://philstar.com/bansa/2025/01/07/2412521/fda-studies-extending-booklet-free-medicine-discount-pwds)). No later change was found **[Confirm with counsel before launch]**. A booklet cannot be stamped remotely, so the rider or pickup counter has to handle it at handover. Build the discount so its proof rules are a setting per discount type. A later DOH change then needs no new code.

**Solo parents** earning under P250,000 a year get **10% off plus VAT exemption** on medicines, vaccines, milk and diapers for children up to age six. They need a Solo Parent ID and booklet, under BIR RR 1-2023 ([KPMG](https://kpmg.com/ph/en/home/insights/2023/03/naytay.html); [Rappler](https://www.rappler.com/nation/benefits-families-get-expanded-solo-parents-law/)). Watsons PH does not let senior or PWD discounts stack with its 10% promo. The higher one applies, and customers are sent to the hotline or a store to claim special discounts ([Watsons Terms](https://www.watsons.com.ph/terms-conditions)). The research found **no PH chain offering a smooth in-app senior or PWD discount**. Doing it well is both a legal duty and a way to stand out. The rule that one person cannot combine the senior and PWD discounts is widely cited, but it was not sourced in this research **[Confirm with counsel]**.

### VAT-exempt maintenance medicines

Separately from the personal discounts, medicines for **diabetes, high cholesterol, hypertension, cancer, mental illness, tuberculosis and kidney disease** are VAT-exempt for every buyer if they are on the FDA-endorsed list the BIR publishes. The list reached **2,277 drugs in August 2026** ([Philstar](https://www.philstar.com/nation/2026/08/06/2547262/bir-14-more-drugs-exempt-vat); [DOF](https://www.dof.gov.ph/more-medicines-for-cancer-diabetes-dialysis-hypertension-high-cholesterol-and-mental-illnesses-are-now-vat-free/)). It is updated quarterly, in February, May, August and November ([Inquirer](https://newsinfo.inquirer.net/2078287)).

Each product therefore needs a **VAT status** field that head office updates every quarter. When a senior buys a listed medicine, there is no VAT to remove, so the 20% applies to the price as shown. This follows logically from the "remove VAT first" rule but was not confirmed in a source **[Confirm with accountant]**. A cart can mix VAT-exempt medicines, discounted medicines and ordinary taxable items such as cosmetics, so the invoice has to split them correctly.

### Invoices, and the 31 December 2026 e-invoicing deadline

Under the Ease of Paying Taxes Act (RA 11976) and RR 7-2024, effective 27 April 2024, **the invoice is now the main proof of sale**. Official receipts are only supplementary. VAT-registered sellers must issue a VAT invoice for **every sale regardless of amount** ([Grant Thornton](https://www.grantthornton.com.ph/insights/articles-and-updates1/tax-notes/clarification-on-the-invoicing-requirements-per-rr-no-7-2024-as-amended-by-rr-no-11-2024/)). The Internet Transactions Act also requires e-retailers to issue paper or electronic invoices for all sales ([RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)).

Under RR 11-2025, as moved by RR 26-2025, e-commerce sellers (micro taxpayers excepted) must issue electronic invoices and send them to the **BIR Electronic Invoicing System by 31 December 2026**. RMC 98-2026 (22 September 2026) sets the implementing guidelines ([Daily Tribune](https://tribune.net.ph/2026/09/23/bir-sets-31-december-e-invoicing-deadline); [Sovos](https://sovos.com/regulatory-updates/vat/philippines-deadline-for-mandatory-structured-e-invoicing-extended/)).

Three things must be settled with your accountant now **[Confirm with accountant]**:

- whether your company is covered;
- whether app orders are invoiced through your BIR-registered branch POS or by the app itself, under each branch's own invoice series;
- when a COD invoice is issued: at order confirmation or at delivery.

PH pharmacy POS vendors list BIR accreditation, senior and PWD compliance, and batch-level expiry as their three core selling points ([Orkids](https://orkids.ph/guides/best-pos-system-philippines); [HashMicro](https://www.hashmicro.com/ph/blog/pharmacy-pos/)). That is a fair summary of where your back office will be judged.

## Prescriptions are sensitive health data, and online buyers have a 7-day complaint clock

### Data privacy

Under the Data Privacy Act (RA 10173), medical and health information is **sensitive personal information** ([NPC Advisory Opinion 2019-032](https://www.scribd.com/document/702330174/AdOps-2019-032)). In your app that covers:

- prescription photos;
- order history of prescription items;
- PWD ID numbers, which reveal a disability;
- chat messages about symptoms.

The duties that follow have hard deadlines:

- **Register** your Data Protection Officer and data processing systems with the NPC within 20 days of starting operations, and renew every year (NPC Circular 2022-04).
- If sensitive data leaks, **notify the NPC and affected customers within 72 hours** (NPC Circular 16-03).

([InCorp](https://philippines.incorp.asia/guides/operationalizing-npc-compliance-and-its-timelines/); [NDV Law](https://ndvlaw.com/when-must-philippine-companies-report-data-breaches/).) The Internet Transactions Act separately makes e-retailers follow NPC security standards ([RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)).

Regulators abroad have penalised online pharmacies for two specific failures:

- fines for sending customer data to advertising trackers such as Meta Pixel;
- an order to stop one account holder from seeing another person's prescriptions.

([Usercentrics](https://usercentrics.com/us/press/pharma-data-compliance-report/?output_format=md); [Pharmaceutical Journal](https://pharmaceutical-journal.com/article/ld/how-pharmacists-can-comply-with-gdpr).) Those are foreign cases, not PH law, but they show where an app is most likely to slip.

In practice:

- Store prescription images privately, never at public URLs.
- Let only the fulfilling branch's pharmacist and compliance staff view them, and log every view.
- Keep trackers off prescription, checkout and order pages.
- Keep medicine names out of push notifications.
- Show riders only the name, address, phone and amount due.
- Ask for separate, explicit consent when a customer uploads a prescription or a discount ID.

Treat **customer-store chat** the same way, since customers will type health details into it. How long prescriptions must legally be kept was not found **[Confirm with counsel]**. That period sets the minimum retention for prescription images. Tax records have their own retention period **[Confirm with accountant]**.

### Consumer protection

The Internet Transactions Act requires e-retailers to:

- describe each product with its name, brand, price and condition;
- sell goods fit for purpose;
- run an internal complaint channel.

A complaint unresolved after **7 calendar days** lets the customer escalate to the DTI. The DTI can issue compliance and takedown orders and blacklist merchants. Fines for deceptive practices run from P20,000 for a first offence to P1 million for repeat offences ([RA 11967](https://lawphil.net/statutes/repacts/ra2023/ra_11967_2023.html)). Your chat feature can serve as the redress channel if complaints get a ticket status, a timestamp, a 7-day timer and a visible link to the DTI. The e-pharmacy draft separately wants the complaint entry point in the app's main navigation ([FDA draft page](https://www.fda.gov.ph/draft-for-comments-guidelines-on-the-issuance-of-license-to-operate-for-drug-establishments-engaged-in-e-pharmacy/)).

## Stock must be tracked by batch and expiry, or a recall cannot be handled

### Batch and expiry tracking, FEFO and recalls

The FDA inspects drugstores for compliance with Good Distribution and Storage Practice (FDA Circular 2014-025) ([jur.ph](https://jur.ph/law/summary/guidelines-implementation-new-rules-drugstore-licensing)). The Philippines adopted the WHO GDP and GSP guides, which update automatically as WHO revises them ([jur.ph](https://jur.ph/law/facts/adoption-implementation-who-good-distribution-storage-practices)).

The single most important inventory fact is that **recalls are issued by lot**. FDA recall advisories tell pharmacies "that have the affected lot" to stop selling it and return sealed stock to the marketing authorization holder ([FDA advisory](https://www.fda.gov.ph/?p=70221); [Inquirer](https://newsinfo.inquirer.net/2034125/fda-orders-recall-of-antibiotics-drug-used-vs-pneumonia-bronchitis-uti/amp)). Per-branch stock counted only by product cannot answer the question "do we have lot X anywhere?". The app therefore needs stock held **by batch number and expiry date per branch**.

Batch tracking should come with:

- first-expiry-first-out (FEFO) picking suggestions;
- near-expiry alerts;
- a hard block on selling expired batches;
- a stock status of available, quarantined or disposed;
- the batch recorded on each order line.

A recall then takes four steps:

1. Head office enters the product and lot.
2. Sales of that lot are blocked at every branch.
3. Branches move the stock to quarantine.
4. The system lists which customers bought the lot.

No PH rule found requires contacting those customers. It is cheap once batches are recorded. The governing recall circular (reported as Bureau Circular 8 s.2001) was not read in full ([SlideShare overview](https://www.slideshare.net/slideshow/product-recall-overview/53326270)) **[Confirm with FDA]**. Dangerous drugs also need a running balance per transaction in a Dangerous Drugs Book or equivalent system ([BFAD MC 7](https://dev.lawyerly.ph/laws/41644)).

### Cold chain

Drugstores carrying vaccines, biologics, insulin and other temperature-sensitive products must meet cold-chain rules, now in **FDA Circular 2021-003** ([FDA GDSP draft](https://www.fda.gov.ph/wp-content/uploads/2024/04/Draft-Circular_GDSP-Deficiencies.pdf); [jur.ph](https://jur.ph/law/summary/guidelines-implementation-new-rules-drugstore-licensing)). Its delivery provisions were not retrieved. No PH source either allows or forbids home delivery of insulin **[Confirm with FDA]**. UK guidance asks pharmacies to risk-assess delivery times and use tested temperature-controlled packaging for refrigerated medicines ([GPhC 2025](https://assets.pharmacyregulation.org/files/2025-02/gphc-guidance-registered-pharmacies-providing-pharmacy-services-distance-february-2025.pdf)). Until that is confirmed, give products a storage-condition field. Limit cold-chain items to pickup, or to short-distance delivery from branches with a monitored fridge, and make them non-returnable.

### Delivery

No PH rule found governs riders, sealed packaging or ID checks at the door. The binding controls sit upstream:

- a licensed branch;
- pharmacist release before handover;
- a delivery SOP filed with the FDA when applying for the online add-on.

([FDA Circular 2014-025](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/68409).) Third-party couriers are not prohibited by any source found. They carry a sealed package that was already dispensed. The UK GPhC is the best available model for the SOP. It expects:

- tracking that the package reaches the right person;
- tamper-evident packaging;
- trained staff;
- monitored third-party carriers;
- records of the delivery method, dispatch date and **proof of successful delivery**.

([GPhC 2025](https://assets.pharmacyregulation.org/files/2025-02/gphc-guidance-registered-pharmacies-providing-pharmacy-services-distance-february-2025.pdf).) Your radius and flat fee are a business choice; no PH distance limit was found. What the app should add is proof of delivery (recipient name, photo or signature, time). Rx and discount orders also need an ID check at the door. A failed delivery should send stock to quarantine, not back to the shelf.

### Returns

PH practice is that sold medicines never go back on sale. **Mercury Drug** accepts returns only for defective items with the original receipt and packaging. It excludes biologicals, injections, vaccines, special-storage items, tampered, compounded and expired items, and **disposes of all returns** ([Mercury Drug return policy](https://mercurydrug.com/return-policy.html)). **Watsons PH online** takes return requests within **7 days** of receipt and refunds COD orders as an e-voucher ([Watsons PH](https://www.watsons.com.ph/return-refund)). Legal commentary lists the accepted grounds as wrong item, wrong strength, damaged, tampered, expired, recalled, counterfeit or short quantity. Change-of-mind returns may be refused ([Respicio](https://www.respicio.ph/commentaries/rules-on-the-return-of-medicines-by-consumers-to-manufacturers-and-pharmacies)). No FDA issuance explicitly bans reselling a returned medicine **[Confirm with counsel]**. Your returns feature should require a reason code, photos and pharmacist approval, and it must **never add returned medicine back to sellable stock**.

## Leading pharmacies compete on refills, speed and loyalty, not on basics

Against the major Philippine chains, your core flow is at parity. Watsons PH puts a prescription upload in the cart (up to 10 images), and a pharmacist contacts the customer to validate it. It offers home delivery or Click & Collect, with orders prepared in 24 to 48 hours ([Watsons Order & Payment](https://www.watsons.com.ph/order-payment); [Watsons Shipping](https://www.watsons.com.ph/shipping-delivery)). Generika routes orders to the nearest branch ([Getmeds](https://getmeds.ph/blog/top-online-pharmacy-apps-in-philippines)), and India's PharmEasy does the same with the nearest licensed pharmacy ([PharmEasy](https://pharmeasy.in/online-medicine-order/alto-cap-137491)). Both support the model your app already uses.

The chains add retention and speed features on top:

- **Loyalty:** Mercury's Suki Card ([App Store](https://apps.apple.com/ph/app/mercury-drug/id950135645)) and Watsons Club.
- **Recurring orders on prescription medicines:** Watsons **Subscribe & Save** offers monthly deliveries on some prescription items, for example 35% off a 90-day supply ([Watsons](https://www.watsons.com.ph/c/shop-subscribe-and-save-prescription-medicines)). Southstar's MedSubscribe plan reportedly covers 3, 6 or 12 months ([Moneymax](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines)).
- **Speed:** Watsons Click & Collect "within 30 minutes" and express delivery in about four hours in select areas ([Cebu Daily News](https://cebudailynews.inquirer.net/?p=459277); [SunStar](https://www.sunstar.com.ph/cebu/feature/watsons-invests-in-delivery-services-upgrades-its-mobile-app)).
- **Gifting:** Generika's **MEDPadala**, gift credit a relative (for example an OFW) can send for medicines ([Moneymax](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines)).
- **Aggregators:** Rose Pharmacy collects prescriptions on its own site for Grab orders ([Rose Pharmacy](https://www.rosepharmacy.com/grab-order-prescription-uploader/)).

Several of these come from secondary guides and may be out of date.

Global leaders, labelled GLOBAL here, go further on the refill cycle:

- Amazon Pharmacy offers auto-refill (excluding controlled drugs) and 24/7 pharmacist contact ([Amazon Pharmacy](https://pharmacy.amazon.com/how-it-works)).
- CVS and Walgreens offer refill reminders, prescription status notifications and synchronized refills of several medicines on one day ([Pillo](https://pillo.care/blog/how-to-sync-prescription-refills-same-day)).

Insurance billing, prescription transfer between pharmacies and pill packs depend on infrastructure the Philippines lacks, so they are low priority. The one Philippine payer channel worth watching is **PhilHealth GAMOT**, launched in August 2025. It covers up to **P20,000 of medicines per person per year** at accredited physical pharmacies, 304 of them as of 30 September 2025, Generika and Southstar among them. Claimants present a prescription from a certified doctor plus a government ID ([Manila Bulletin](https://mb.com.ph/2025/08/21/free-medicines-under-philhealths-p20k-annual-benefit-what-you-can-get-starting-august-21); [PNA](https://www.pna.gov.ph/articles/1261062)). No e-pharmacy was found accepting GAMOT or HMO cards for online orders. Whether GAMOT allows delivery is unknown **[Confirm with PhilHealth]**.

## Conclusion

The biggest risk is not a missing customer feature. Your app's main legal exposure sits in features it already has but whose records are too thin to prove compliance. A pharmacist "approve" button with no PRC number is one example. Stock counted by product rather than by lot is another, and so are returns that might restock and promos that might include prescription items. Each of these looks finished in a demo and fails in an FDA inspection, a BIR audit or a DTI complaint. The fixes are mostly data fields, validation rules and reports, not new screens, which makes them cheaper than they look.

The regulatory picture is also less settled than it seems. The binding Philippine rules on online pharmacy are short and old: a 2019 advisory and the 2020 licensing order. The detailed e-pharmacy guideline has sat in draft since 2022, and if finalized as written it would exclude prescription items. Build the prescription flow so it can be switched to "reserve online, pay and collect in branch" by configuration. Put the discount proof rules, VAT list and price caps in data that head office updates, not in code. That way a new FDA, DOH or BIR issuance means a settings change instead of a rebuild. Before launch, the three conversations worth paying for are:

- counsel on online prescription orders and photo versus original;
- the FDA on each branch's online add-on and cold-chain delivery;
- an accountant on e-invoicing before 31 December.

## Prioritized gap list

The tables compare the research against your current app. "Extends" names the existing feature each gap builds on. Items phrased "confirm the app..." may already be done; the feature list did not say.

### Must-have before launch

| Gap | Why it matters, in plain words | Extends | Confirm |
| --- | --- | --- | --- |
| Per-branch FDA LTO number, expiry and "online ordering approved" flag; refuse to route orders to branches without it; show the LTO on the branch page | Selling from an unlicensed branch, or one without the online add-on, is illegal | Branches / nearest-branch selection | [Confirm with FDA] that every fulfilling branch has the add-on |
| Product catalog: FDA registration (CPR) number required; drug classification (Rx / pharmacist-only / general OTC / dangerous drug / non-medicine) | Only registered products may be sold online; every other rule depends on the classification | Rx flag | |
| Dangerous drugs (S2) blocked from ordering and delivery; "in-store only" | Original special prescription must be surrendered; e-prescriptions banned | Rx flag | [Confirm with counsel] status after Board Reg. 10 expired 30 Jun 2026 |
| Electronic prescription book: prescriber name and PRC number, patient, items, quantity served and balance left (partial fill), batch, approving pharmacist's name and PRC number, timestamp, rejection reason | Proves a licensed pharmacist dispensed; a computerized record has been accepted as equivalent | Pharmacist approve/reject, audit log | [Confirm with counsel] official field list and retention period |
| Original prescription sighted at pickup or delivery handover (photo is pre-check only) | No current rule accepts a photo alone | Rx photo upload | [Confirm with FDA] |
| Generic name shown above brand; "same generic, other brands and prices" list on product pages | Generics Act duty, P5,000–10,000 fines | Product pages | |
| Maximum retail price per regulated product; block any price or promo above it | Legal price caps at every outlet | Per-branch stock/pricing | [Confirm with counsel] current list |
| Promos, banners, push and "featured" exclude prescription items; decide on reviews for Rx items | Public promotion of Rx drugs is prohibited | Reviews, any promo features | [Confirm with counsel] on reviews |
| Senior and PWD discount: declare at checkout, capture ID number, compute VAT-removed-then-20%, check ID (and PWD booklet) at handover, separate sales book report | Mandatory; wrong math is non-compliant; online orders qualify | Checkout, pickup/delivery | [Confirm with counsel] PWD booklet, no stacking; [Confirm with accountant] delivery fee |
| VAT status per product (VAT-exempt list), updated quarterly; invoices split VATable / VAT-exempt / discount | 2,277 listed medicines are VAT-free for everyone | Checkout, pricing | [Confirm with accountant] |
| BIR invoice for every sale under the fulfilling branch's series; e-invoice transmission to BIR EIS | Required since 2024; EIS deadline 31 Dec 2026 | COD, PayMongo | [Confirm with accountant] coverage, POS vs app, COD timing |
| Batch number and expiry on stock per branch; block expired batches; batch recorded on each order line | Recalls are by lot; expired sales are a basic inspection failure | Per-branch stock | |
| Recall: block a lot chain-wide, quarantine, list buyers | FDA recall advisories target specific lots | Per-branch stock, audit log | [Confirm with FDA] recall circular |
| Confirm returns never restock dispensed medicine; reason codes, photos, pharmacist approval, time window | Industry practice and safety; returned medicine is disposed of or sent back to supplier | Returns | [Confirm with counsel] |
| Privacy: private Rx image storage, branch-only access with view logging, no trackers on Rx/checkout pages, no medicine names in push, riders never see Rx, separate consent at upload, privacy notice | Prescriptions and PWD IDs are sensitive data | Rx upload, chat, audit log | |
| NPC registration of DPO and systems; 72-hour breach runbook | Legal deadlines (20 days; 72 hours) | (operations) | |
| Business disclosure on homepage/About: corporate and trade name, address, phone, email; DTI E-Commerce Bureau registration | Internet Transactions Act, fully enforced since June 2025 | (app shell) | |
| Complaint channel with ticket status and 7-day timer, visible DTI link, entry point in main navigation | 7-day escalation rule; draft FDA requirement | Customer-store chat | |

### Should-have soon

| Gap | Why it matters | Extends | Confirm |
| --- | --- | --- | --- |
| FEFO picking suggestion and near-expiry alerts (e.g. 90/60/30 days) | Less waste; expected under storage practice | Per-branch stock | |
| Storage-condition field; cold-chain items pickup-only or limited delivery from branches with monitored fridges; non-returnable; fridge temperature log | Cold-chain circular applies to stores carrying these items | Stock, delivery radius | [Confirm with FDA] delivery of insulin/vaccines |
| Proof of delivery (recipient, photo/signature, time); courier/rider ID; failed delivery goes to quarantine | Best-practice delivery records; protects you in disputes | Delivery | |
| Inter-branch stock transfer records | "Available at another branch" needs a traceable move | Per-branch stock | |
| Solo parent discount (10%, VAT-exempt, child under 6, ID and booklet) | Legally mandated, smaller volume | Checkout | [Confirm with accountant] |
| Discount proof rules as settings per type | DOH may drop the PWD booklet; avoids rework | Checkout | |
| Multiple photos per prescription | Watsons allows up to 10; real prescriptions span pages | Rx upload | |
| Pharmacist counselling log on Rx orders (chat summary, pharmacist, time) | Remote-counselling model from UK guidance | Chat, pharmacist flag | |
| Prescription status notifications (received, verified, ready, out for delivery) without medicine names | Customers expect it; privacy-safe wording required | Order flow | |
| Fake/altered prescription reason code and account flag | Fraud control | Approve/reject | |
| "Ready for pickup" notification with a target time | Watsons promises about 30 minutes | Pickup | |

### Nice-to-have later

| Gap | Why it matters | Extends | Confirm |
| --- | --- | --- | --- |
| Refill reminders and "reorder" from past orders | Retention; standard in global apps | Order history | |
| Subscribe-and-save or scheduled recurring delivery for maintenance medicines | Watsons and Southstar offer it | Checkout, delivery | |
| Loyalty points | Mercury Suki Card, Watsons Club | Accounts | |
| Express (same-day, under 4 hours) delivery tier | Competitive speed | Delivery fee | |
| Gift credit / pay for someone else (MEDPadala-style) | Fits OFW family buying | PayMongo | |
| PhilHealth GAMOT flag and code capture, pickup at accredited branches | New payer channel | Rx upload, pickup | [Confirm with PhilHealth] |
| Link uploaded Rx to Grab/aggregator order reference | Needed only if you sell through aggregators | Rx upload | |
| Teleconsult handoff and synchronized refills | Global features; little PH infrastructure yet | | |
