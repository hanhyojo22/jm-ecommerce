# E-pharmacy feature inventory: Philippines first, global second

All sources were searched on 2026-11-15. Several official pages (watsons.com.ph FAQ pages, rosepharmacy.com, inquirer.net) returned HTTP 403 to the fetch tool, and the Apple App Store returned 429. Where that happened, the claims below come from search-engine extracts of those pages and not from a full read. Third-party "best online pharmacies" guides (Moneymax, Getmeds, MedsGo) are secondary sources and often out of date, so they are labelled as such.

Baseline app being compared: branches with map pins and nearest-branch selection; per-branch stock and "available at another branch"; Rx flag with prescription photo upload and pharmacist approve/reject; pickup or delivery with a radius and flat fee; COD and PayMongo; customer-store chat; reviews; returns; staff roles with a pharmacist flag; audit log.

## Q1. What do Philippine e-pharmacies and aggregators offer customers?

### Takeaway
The PH chains have converged on the same core your app already has: Rx upload with pharmacist validation, pickup or delivery, and COD. The features they add on top are mostly about retention and speed: loyalty cards (Suki, Watsons Club), subscribe-and-save or scheduled refills (Watsons Subscribe & Save, Southstar MedSubscribe), express delivery measured in hours, click-and-collect within minutes, chat-to-order, gift or pay-for-someone-else credit (Generika MEDPadala), and telemedicine-to-pharmacy handoff (Southstar with Doctor Anywhere). I found no evidence that any PH chain lets customers apply the senior/PWD discount inside the app with an ID upload. Watsons sends those customers to its hotline or a store.

### Cited Findings

**Mercury Drug (app + online ordering)**
- The app lets users apply for a Suki Card, find stores including 24/7 and delivery stores, read store advisories, view promos, send e-gifts and contact Mercury ("Ask Us"). The listing's version history is about four years old, so the feature list may be stale. — [Mercury Drug, App Store PH](https://apps.apple.com/ph/app/mercury-drug/id950135645) (via search extract; direct fetch returned 429)
- Online ordering ("Mercury Drug Order Meds Online") requires a valid prescription for Rx items. Support staff verify the prescription before confirming the order, and customers can message the store to send order details and prescriptions. — [The Data Code, 2026 guide](https://thedatacode.com/budgeting/mercury-drug-order-online/) (secondary)
- The same guide contradicts itself on whether the Suki Card is honored for online orders. Unresolved. — [The Data Code](https://thedatacode.com/budgeting/mercury-drug-order-online/)
- Suki points: older blogs give 1 point per ₱200 spent. Points can be checked online, at an in-store scanner or with a cashier, and they reset to zero if the card goes unused for 180 days. — [randomlycluttered blog, 2014](https://randomlycluttered.blogspot.com/2014/04/why-you-should-apply-for-mercury-drug.html); [dareenortega FAQ, 2017](http://dareenortega.blogspot.com/2017/11/mercury-drug-suki-card-frequently-asked.html) (old; rate may have changed)
- Some Mercury Drug/MedExpress in-store clinics accept HMOs such as Maxicare, Intellicare and PhilCare. This varies by branch, and it applies to clinic consults, not online orders. — [ClinicFinderPH, 2026](https://www.clinicfinderph.com/blog/mercury-drug-medical-clinic-price-list)

**Watsons PH**
- **Rx upload in the cart:** a prescription icon appears in the order summary when an Rx item is in the cart, up to 10 images can be attached, and a pharmacist contacts the customer after checkout to validate the prescription. The prescription must be uploaded when the order is created. — [Watsons Order & Payment](https://www.watsons.com.ph/order-payment) (search extract)
- Prescriptions can also be emailed to onlinepharmacy@watsons.com.ph. — [Watsons Click & Collect Express FAQ](https://www.watsons.com.ph/shop-medicines-click-and-collect-express) (search extract; fetch returned 403)
- **Fulfilment:** home delivery or Click & Collect. Orders are verified and prepared within 24–48 hours. Watsons Club members get a shipping discount, capped at ₱50, on orders of ₱2,000 or more. — [Watsons Shipping & Delivery](https://www.watsons.com.ph/shipping-delivery) (search extract)
- **Click & Collect via the app:** pick up in store "within 30 minutes" of ordering. The article is older and undated. — [Cebu Daily News](https://cebudailynews.inquirer.net/?p=459277)
- **Express delivery:** "four hours or less" for orders placed before 5 p.m. in select areas, per one source, versus "under five hours" in a 2021 piece. A 2026 release mentions "Home Delivery Express" without a time. — [SunStar Cebu](https://www.sunstar.com.ph/cebu/feature/watsons-invests-in-delivery-services-upgrades-its-mobile-app); [Manila Bulletin 2021](https://mb.com.ph/2021/07/27/mega-brands-collaborate-online-to-elevate-health-and-beauty-in-the-philippines/)
- **Subscribe & Save on Rx medicines:** offered on some Rx SKUs. For example, clopidogrel can be delivered every month for 3 deliveries ("Buy 90 & Get 35% off"), while other SKUs (gliclazide) offer only one-time purchase. There is a dedicated category page. — [Watsons clopidogrel PDP](https://www.watsons.com.ph/watsons-generics-clopidogrel-as-bisulfate-75mg-1-film-coated-tablet-prescription-required/p/BP_50002331); [Watsons gliclazide PDP](https://www.watsons.com.ph/watsons-generics-gliclazide-30mg-1-modified-release-tablet-prescription-required/p/BP_10100665); [Subscribe and Save Rx category](https://www.watsons.com.ph/c/shop-subscribe-and-save-prescription-medicines)
- Subscriptions save "up to 40%" on vitamins and medicines. — [Philstar advertorial 2022](https://philstar.com/lifestyle/health-and-family/2022/03/18/2167433/when-it-comes-shopping-goals-sa-watsons-ako)
- **Senior/PWD online:** the SC/PWD discount cannot be combined with the 10% promo, and the higher discount applies. For special discounts the T&C point customers to the Call & Delivery hotline or a store. No in-app discount application flow was found. — [Watsons Terms](https://www.watsons.com.ph/terms-conditions) (search extract); [Chinabank promo](https://www.chinabank.ph/up-to-300-pesos-cashback-at-watsons-club-nationwide-sale)
- Watsons has its own "Watsons Generics" private-label line, listed as separate Rx SKUs. — [Watsons PDPs above](https://www.watsons.com.ph/watsons-generics-watsons-glimepiride-2mg-1tablet/p/BP_10101040)
- Watsons runs a vaccination service with a FAQ page. — [Watsons vaccination FAQs](https://www.watsons.com.ph/vaccination-faqs)

**Southstar Drug**
- Online store with cart and checkout, documented on a Zendesk help centre. — [Southstar "How to order?"](https://southstardrug.zendesk.com/hc/en-us/articles/6194844461455-How-to-order)
- Per third-party guides: a "Chat to Order" channel, COD and GCash, a **MedSubscribe** scheduled-delivery plan over 3, 6 or 12 months, and same-day delivery in some areas (another guide says up to 14 days). Through a Doctor Anywhere partnership, DA members can order prescribed medicines in the DA app after a video consult, with same-day delivery. — [Moneymax](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines); [Getmeds](https://getmeds.ph/blog/top-online-pharmacy-apps-in-philippines); [MedsGo 2026](https://medsgo.ph/medsgo-blog/10-best-online-drugstore-for-medicine-delivery-in-the-philippines/) (secondary; the search tool did not say which guide made which claim)
- Southstar was formally accredited as a PhilHealth GAMOT partner pharmacy. — [Inquirer](https://newsinfo.inquirer.net/2331239/philhealth-partners-with-drugstore-chain-to-expand-free-medicines) (search extract)

**Rose Pharmacy (Cebu/Visayas-Mindanao)**
- Online ordering and delivery, with some branches open 24 hours. Delivery is concentrated in Cebu and Davao. Delivery is free in Metro Cebu above ₱1,499, and ₱99–₱199 elsewhere depending on weight. — [Moneymax / Getmeds / MedsGo guides](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines) (secondary)
- **"Grab Order Prescription Uploader":** a page on Rose's own site where customers who order through Grab attach the prescription. The pharmacy collects the Rx itself because the aggregator does not handle it. — [Rose Pharmacy](https://www.rosepharmacy.com/grab-order-prescription-uploader/) (page exists per search; fetch returned 403, so field details are unknown)

**The Generics Pharmacy (TGP)**
- Official online store at shop.tgp.com.ph carrying Rx and OTC medicines, vitamins and supplements, with a generics-first positioning. — [Getmeds](https://getmeds.ph/blog/online-drugstore-in-philippines) (secondary)

**Generika**
- An app and e-commerce site route orders to the nearest branch for delivery or pickup. — [Getmeds / MedsGo](https://getmeds.ph/blog/top-online-pharmacy-apps-in-philippines) (secondary)
- **MEDPadala:** an electronic gift certificate a relative can send, redeemable for medicines at any of 700+ branches. This suits OFW remittance-style gifting. — [Moneymax](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines) (secondary)
- COD is available. — [MedsGo 2026](https://medsgo.ph/medsgo-blog/10-best-online-drugstore-for-medicine-delivery-in-the-philippines/)
- Generika (CGD Medical Depot Inc.) was among the early PhilHealth GAMOT accredited providers. — [SunStar](https://www.sunstar.com.ph/manila/300-drugstores-accredited-for-gamot-program); [PNA](https://www.pna.gov.ph/articles/1261062)
- The App Store listing "Generika PH" (developer Actimed) returned no feature text. — [App Store](https://apps.apple.com/ph/app/generika-ph/id1551587890)

**MedExpress**
- Nationwide delivery. One source gives a ₱250 minimum order and a fee from ₱100 based on distance from hubs, while another gives a ₱50 fee under ₱1,000. Rx items are uploaded at checkout, then staff call to confirm. Pickup at about 50 points and same-day or 24-hour delivery appear in a single source. One directory mentions refill reminders, which I could not confirm. — [Rappler list](https://www.rappler.com/technology/features/255124-websites-apps-medicine-delivery-manila/); [HelloDoctor](https://hellodoctor.com.ph/health/health-news/medicine-delivery-philippines/); [OneNews](https://www.onenews.ph/articles/these-6-online-pharmacies-will-deliver-your-medicines-to-your-doorstep-1) (secondary, inconsistent)

**MedGrocer (B2B / employer e-pharmacy)**
- A licensed ePharmacy and clinic operator serving employers' workforces. A roughly four-year-old guide gives next-working-day delivery in Metro Manila at ₱200 and ₱500 elsewhere. — [MedGrocer](https://medgrocer.com/); [Moneymax](https://www.moneymax.ph/lifestyle/articles/online-drugstores-philippines)

**Aggregators**
- **GrabMart** lists OTC pharmacy supplies (undated). A 2026 guide says some Rx medicines can be ordered through Watsons, Southstar and GrabMart, with a digital copy of the prescription. — [GrabMart PH](https://www.grab.com/ph/mart/); [MedsGo 2026](https://medsgo.ph/medsgo-blog/10-best-online-drugstore-for-medicine-delivery-in-the-philippines/)
- Grab's Rx upload flow (upload in the cart for Rx items) is documented only for GrabHealth Indonesia. — [GrabHealth ID](https://www.grab.com/id/en/health/)
- Pharmacies on Grab review uploaded prescriptions themselves for availability, pricing and delivery. — [Medsforall Grab page](https://medsforall.com.ph/pages/grab)
- foodpanda: Shops lists third-party stores (7-Eleven, MerryMart). No Rx-upload details were found. — [3D Academy Cebu](https://3d-universal.com/en/blogs/online-shopping-and-delivery-services-in-cebu.html) (old)

### Inferences
- Compared with the baseline app, the PH-market features it lacks are: (1) a loyalty points card; (2) subscribe-and-save or scheduled recurring Rx delivery; (3) a "ready for pickup in X minutes" click-and-collect SLA with notification; (4) express delivery under 4 hours; (5) senior/PWD ID capture with a statutory discount (no chain does this well online, so it could set the app apart); (6) gift credit that someone else pays for (MEDPadala-style); (7) a private-label or "cheaper generic" suggestion (Watsons Generics, TGP); (8) a teleconsult-to-order handoff. Rx upload with pharmacist validation, branch locator, COD and chat are already at parity.
- Watsons' multi-image upload (up to 10) and email fallback suggest the app should accept several photos per Rx.
- Rose's Grab uploader shows that pharmacies selling through aggregators collect the Rx on their own site. Linking an uploaded Rx to an external order reference would be useful if the business sells on Grab.

### Gaps
- I could not read official pages in full (403/429), so exact current flows are unverified for Mercury (does online ordering still exist, and is there a "Ready to Pick Up" notice?), Southstar MedSubscribe and Rose.
- No source found for: family profiles, saved prescription wallets, medication reminders, symptom-based search or price comparison at any PH chain; Lazada/Shopee LazMall pharmacy Rx policies; Mercury "Ready to Pick Up".
- I did not verify whether the senior/PWD discount is ever applied automatically online.

## Q2. Notable global features (labelled GLOBAL)

### Takeaway
Global leaders go further on the refill lifecycle and payment. Their headline features are auto-refill, med-sync, photo-based Rx transfer, insurance billing with up-front price display, 24/7 pharmacist chat, multi-dose pill packs and generic substitution showing the price gap. Amazon, CVS and Walgreens all ship these. India's 1mg and PharmEasy add salt-based cheaper-substitute prompts and lab-test booking.

### Cited Findings
- **Amazon Pharmacy (US):** automatic refills can be enabled after the first fill (not every prescription is eligible, and Schedule II is excluded); customers are notified on shipment and if the next refill price changes significantly; it works with most insurance plans; transfers are simple; and there is 24/7 pharmacist contact. — [Amazon Pharmacy home](https://pharmacy.amazon.com/); [How it works](https://pharmacy.amazon.com/how-it-works)
- **PillPack:** meds are pre-sorted into packets by date and time and shipped monthly on a 30-day cycle. It includes up-front, transparent pricing and automatically applied manufacturer coupons, and accepts Medicare/Medicaid depending on the state. — [PillPack](https://pharmacy.amazon.com/pillpack); [Amazon help](https://www.amazon.com/gp/help/customer/display.html?nodeId=TAR0Aoi3xbTZoIBkVD)
- **CVS:** refill, transfer (new patients transfer by photographing the Rx label), prescription reminders by text or email, a store locator, Rx status push notifications, and ScriptSync med-synchronisation enrolment online, in the app or in store. — [iMedicalApps review 2016](https://www.imedicalapps.com/2016/06/cvs-app-pharmacy-app-review/); [CVS digital capabilities brochure](https://www.cu.edu/doc/cvs-digital-capabilities-brochurepdf-1); [Pillo](https://pillo.care/blog/how-to-sync-prescription-refills-same-day)
- **Walgreens:** Rx transfer in the app; delivery choice of same-day ($5.99 shown, then waived at checkout) or free 1–2 day; optional 24/7 pharmacist chat; barcode scanning to refill; pill reminders; Save a Trip Refills med-sync (enrolment by phone). — [UNH guide 2025](https://www.unh.edu/health/sites/default/files/media/2025-10/How%20to%20use%20walgreen%20app.docx); [App Store Tracker](https://www.appstoretracker.com/alternatives/walgreens-335364882); [Pillo](https://pillo.care/blog/how-to-sync-prescription-refills-same-day)
- A 2026 user-review comparison says Walgreens users praise its same-day delivery and that CVS users complain about notification volume (duplicates, reminders for discontinued meds). — [Unstar 2026](https://unstar.app/blog/cvs-walgreens-goodrx-amazon-pharmacy-singlecare-pharmacy-apps-ranked-2026)
- **Tata 1mg (India):** products come from licensed pharmacies; PDPs show "X% cheaper alternative available with same salt composition"; lab tests can be booked. — [1mg PDP](https://www.1mg.com/otc/beneficiale-capsule-otc63513)
- **PharmEasy (India):** orders are fulfilled by the licensed retail pharmacy nearest the customer, subject to prescription verification and stock; COD is available; express delivery is tagged on PDPs. — [PharmEasy PDP](https://pharmeasy.in/online-medicine-order/alto-cap-137491)

### Inferences
- PharmEasy's nearest-licensed-pharmacy routing is close to the baseline app's nearest-branch model, so the model is validated.
- Cheap, high-value additions modelled on global apps: a "same active ingredient, cheaper" suggestion on product pages (needs a generic-name field per product), a refill reminder or reorder button from past orders, and an Rx status notification timeline.
- Insurance billing, Rx transfer between pharmacies and pill packs depend on US/India infrastructure (PBMs, e-Rx networks) that the PH market lacks. Treat them as low priority.

### Gaps
- No usable sources were found for Boots, Alto, Capsule or delivery ID verification in the time budget.

## Q3. Staff / back-office features of pharmacy systems

### Takeaway
PH pharmacy POS systems sell on three requirements: BIR accreditation, senior/PWD discount compliance, and batch-level expiry and stock control. Multi-branch transfers, auto purchase orders and low-stock or near-expiry alerts are standard vendor claims. I found no PH evidence of dispensing-label printing or a formal dispensing queue.

### Cited Findings
- A PH POS must be BIR-accredited. Receipts carry the TIN, business name, branch code, SC/PWD discount lines, a VAT breakdown and a valid Z-reading. Accreditation requires sequential unbroken invoice numbers and a running lifetime sales total. — [Orkids guide 2026](https://orkids.ph/guides/best-pos-system-philippines); [KwikPOS](https://www.kwikpos.ph/blogs/point-of-sale/bir-pos-accreditation-process); [HashMicro BIR receipt rules](https://www.hashmicro.com/ph/blog/official-receipt-bir/) (vendor content)
- SC/PWD: a 20% discount plus VAT exemption, computed on the VAT-exclusive price, itemised on the receipt. The POS must capture the SC/PWD ID number and name. A flat percentage off the VAT-inclusive price is non-compliant. — [Divergent POS](https://www.divergentpos.com/resources/educational/bir-compliance-discounts/)
- Pharmacy POS must track expiry at batch level, not only product level. — [HashMicro pharmacy POS](https://www.hashmicro.com/ph/blog/pharmacy-pos/)
- RxPlus by Oasis (PH-developed): automated purchase orders and low-stock or near-expiry alerts. Parmazip: a BIR-accredited pharmacy POS for PH community drugstores. — [HashMicro 10 best pharmacy POS 2025](https://www.hashmicro.com/ph/blog/?p=25557)
- StoreHub PH pharmacy: low-stock alerts at a reorder threshold, and it pairs stock accuracy with clean BIR receipts. — [StoreHub PH](https://www.storehub.com/ph/blog/pharmacy-pos-system-philippines)
- Inter-branch stock transfers, stock-taking, adjustments and handovers (iPharma). Branch transfers and purchase orders (CloudPOS, a Pakistani vendor). Barcode/label management (PharmaSolo, Oman/AU). — [G2 iPharma](https://www.g2.com/products/ipharma/reviews); [CloudPOS](https://cloudpos.pk/pharmacy-management-system-software); [PharmaSolo](https://www.getapp.com.au/software/2091682/pharmasolo)

### Inferences
- The baseline app's audit log and pharmacist flag cover the staff accountability layer. The back-office gaps against typical pharmacy systems are: batch/lot and expiry on stock with near-expiry alerts; branch-to-branch transfer records (the app already shows "available at another branch" but may not move stock); purchase orders to suppliers; per-branch sales reports; and SC/PWD discount capture (ID number and name, VAT-exempt computation) on the order and receipt.
- BIR-accredited receipts are normally issued by the in-store POS. An e-commerce app would issue an e-invoice or order acknowledgement and leave official receipts to the accredited system unless it seeks accreditation itself. This needs confirming with a PH accountant.

### Gaps
- No PH-specific source for a dispensing queue UI, Rx verification tooling or label printing. I did not find which systems Mercury, Watsons or Southstar use internally.

## Q4. HMO / PhilHealth integration in PH e-pharmacies

### Takeaway
PhilHealth GAMOT, launched August 2025, is the significant new payer channel. It works at accredited physical pharmacies (304 as of Sept 30; Generika and Southstar among them) using a prescription from a YAKAP/Konsulta doctor plus a government ID. I found no e-pharmacy that takes GAMOT, Maxicare or Intellicare as payment for online orders. HMO coverage at chains appears limited to in-store clinic consults.

### Cited Findings
- GAMOT covers 75 essential drugs for common and chronic conditions, capped at ₱20,000 per beneficiary per year. — [Manila Bulletin, 2025-08-21](https://mb.com.ph/2025/08/21/free-medicines-under-philhealths-p20k-annual-benefit-what-you-can-get-starting-august-21); [Spot.ph](https://www.spot.ph/newsfeatures/adulting/psa-you-can-now-get-up-to-p20000-worth-of-free-medicines-through-philhealth-a5229-20250915-dyn)
- Launch was 2025-08-21 in most reports, with one saying August 14. The initial drug list is given as about 20 or 21 in early reports. — [Manila Bulletin](https://mb.com.ph/2025/08/21/free-medicines-under-philhealths-p20k-annual-benefit-what-you-can-get-starting-august-21); [Inquirer](https://newsinfo.inquirer.net/2125459/philhealth-eyes-offering-more-free-medicines-under-gamot-package)
- There were 304 accredited pharmacies as of Sept 30 (56 in Metro Manila), plus about 4,300 GAMOT partner clinics per another report. Pharmacies apply through their PhilHealth Regional Office with a documentary checklist. — [PNA](https://www.pna.gov.ph/articles/1261062); [SunStar](https://www.sunstar.com.ph/manila/300-drugstores-accredited-for-gamot-program); [SunStar call for providers](https://www.sunstar.com.ph/manila/philhealth-calls-on-pharmacies-to-be-gamot-providers)
- Claim flow: register with YAKAP, get a prescription from a certified doctor, then present it with a government ID at any GAMOT facility. One guide says the prescription is issued through a GAMOT app with a Unique Prescription Security Code (UPSC). — [BusinessDiary 2025 guide](https://businessdiary.com.ph/36811/how-to-get-%E2%82%B120000-worth-of-free-medicines-through-philhealths-gamot-program-2025-guide/); [8List](https://8list.ph/how-to-get-free-medicine-from-philhealth/)
- Early accredited providers include Generika (CGD Medical Depot) and Vidacure. Southstar was later accredited as a partner. — [SunStar](https://www.sunstar.com.ph/manila/300-drugstores-accredited-for-gamot-program); [Inquirer](https://newsinfo.inquirer.net/2331239/philhealth-partners-with-drugstore-chain-to-expand-free-medicines)
- HMOs (Maxicare, Intellicare etc.) bundle 24/7 teleconsults through partners (Intellicare uses Medgate). No e-pharmacy HMO billing was found. — [MoneyHubPH HMO guide 2026](https://moneyhubph.com/insurance/hmo-guide-2026)
- Mercury/MedExpress in-store clinics accept some HMOs, varying by branch. — [ClinicFinderPH](https://www.clinicfinderph.com/blog/mercury-drug-medical-clinic-price-list)

### Inferences
- For a multi-branch pharmacy, GAMOT accreditation is a store-level, in-person dispensing channel. The app's Rx upload plus pickup flow could support it by letting the customer flag "GAMOT/YAKAP prescription", capture the UPSC code, and require pickup with ID at an accredited branch. This depends on PhilHealth rules, which I did not verify.
- HMO medicine billing online is not a PH market norm, so lacking it is not a gap at parity.

### Gaps
- I could not confirm whether GAMOT permits remote or delivery dispensing, how pharmacies validate the UPSC, or whether Mercury or Watsons are GAMOT providers.
- No source on any PH e-pharmacy accepting Maxicare or Intellicare LOAs or cards for medicine orders. MedGrocer's employer programmes might, but that is unconfirmed.
