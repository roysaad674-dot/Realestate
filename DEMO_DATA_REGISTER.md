# ESTATEOS — DEMO DATA REGISTER

**Organization:** ESTATEOS QA DEMO ORGANIZATION  
**Organization Code:** `QA-DEMO`  
**Organization ID:** `0dea4432-333b-4bab-8780-47f149269663`  
**Test Data Prefix:** `QA-DEMO-`  
**Generation Script:** `database/seed_qa_demo_organization.php`  
**Verification Script:** `tests/test-qa-demo-workflows-and-invariants.php`  

---

## 1. Organization & Core Settings

- **Legal Entity:** ESTATEOS QA DEMO ORGANIZATION
- **Registered Address:** Level 18, Horizon One, BKC, Mumbai, Maharashtra 400051, India
- **GSTIN:** `27AAACH0001Z1Z5`
- **Default Currency:** INR (₹)
- **Primary Email:** `contact@qademo.estateos.test`
- **Primary Phone:** `022-98765432`

### Active Settings Register

| Setting Key | Configured Value | Business Impact Description |
| :--- | :---: | :--- |
| `hold_duration_hours` | `72` | Inventory hold expires automatically after 72 hours if booking is not finalized |
| `broker_commission_pc` | `2.5` | Standard default brokerage rate assigned to new channel partners |
| `commission_trigger` | `agreement` | Commission liability becomes payable upon formal agreement signing |
| `require_clearance_for_possession` | `1` | Enforces 100% financial dues clearance before possession release |
| `document_expiry_warning_days` | `60` | Surfaces document expiry warning badge 60 days before compliance cutoff |
| `payment_grace_days` | `10` | Days allowed after installment due date before penalty accrual |
| `late_fee_pc` | `15` | 15% p.a. pro-rata interest assessed on overdue installment balances |
| `default_tax_pc` | `5` | 5% GST calculated on residential property sales |
| `commission_on_cancelled` | `0` | Disallows broker commission payout on cancelled customer bookings |
| `cancellation_charge_pc` | `10` | Retains 10% forfeiture fee when customer cancels confirmed booking |
| `require_kyc_before_booking` | `1` | Mandates verified customer KYC before booking creation |
| `max_discount_pc_without_approval` | `3.0` | Discounts above 3.0% trigger mandatory management approval workflow |

---

## 2. Departments & Staff User Directory

### Functional Departments

| Department Code | Department Name | Status |
| :--- | :--- | :--- |
| `QA-DEMO-DEP-PRJ` | Project Engineering & Planning | Active |
| `QA-DEMO-DEP-SLS` | Sales & CRM Management | Active |
| `QA-DEMO-DEP-ACC` | Corporate Finance & Accounts | Active |
| `QA-DEMO-DEP-PRC` | Procurement & Contracts | Active |
| `QA-DEMO-DEP-WRH` | Stores & Warehouse Logistics | Active |
| `QA-DEMO-DEP-HR` | Human Resources & Payroll | Active |

### Staff User Credentials (Password: `Password123!`)

| Name | Role | Email | Assigned Department |
| :--- | :--- | :--- | :--- |
| Demo Admin | Company Owner | `qa.admin@estateos.test` | Executive |
| Rohan Kulkarni | Project Manager | `qa.pm@estateos.test` | Project Engineering |
| Ananya Deshmukh | Sales Manager | `qa.salesmgr@estateos.test` | Sales & CRM |
| Kunal Sharma | Sales Executive | `qa.agent1@estateos.test` | Sales & CRM |
| Pooja Nair | Sales Executive | `qa.agent2@estateos.test` | Sales & CRM |
| Suresh Iyer | Finance Manager | `qa.accountant@estateos.test` | Corporate Finance |
| Vikram Singhania | Procurement Manager | `qa.procurement@estateos.test` | Procurement |
| Ganesh Patil | Warehouse Manager | `qa.warehouse@estateos.test` | Stores & Logistics |
| Meera Joshi | HR Manager | `qa.hr@estateos.test` | Human Resources |

---

## 3. Land Parcels Register

| Land Code | Parcel Name | Survey / CTS | Location | Area | Acquisition Cost | Legal Title |
| :--- | :--- | :--- | :--- | :---: | :---: | :--- |
| `QA-DEMO-LND-001` | Bandra West Waterfront Parcel | CTS 1042/B | Bandra West, Mumbai | 4.5 Acres | ₹120,000,000.00 | Clear Title Verified |
| `QA-DEMO-LND-002` | Kalyani Nagar Meadows | Survey 88/1 | Kalyani Nagar, Pune | 6.2 Acres | ₹85,000,000.00 | Clear Title Verified |

---

## 4. Real Estate Projects & Towers

### Projects

| Project Code | Project Name | Type | RERA Number | Location | Budget Amount | Status |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| `QA-DEMO-PRJ-001` | The Heritage Residences | Residential | `MAHARERA-P51800099881` | Mumbai | ₹450,000,000.00 | Under Construction |
| `QA-DEMO-PRJ-002` | Skyline Pinnacle Towers | Mixed-Use | `MAHARERA-P51800099882` | Pune | ₹320,000,000.00 | Under Construction |

### Project Construction Milestones (`QA-DEMO-PRJ-001`)

| Sequence | Milestone Description | Progress % | Billing % | Status |
| :---: | :--- | :---: | :---: | :--- |
| 1 | Foundation & Substructure Raft | 100.0% | 10.0% | Completed |
| 2 | 5th Floor Structural Slab Cast | 75.0% | 40.0% | In Progress |
| 3 | Façade, MEP & Handover Finishing | 10.0% | 50.0% | Pending |

---

## 5. Property Inventory (24 Units across 6 Statuses)

| Unit Number | Project / Building | Floor | Carpet Area | Base Price | Final Price (inc. GST) | Status |
| :--- | :--- | :---: | :---: | :---: | :---: | :--- |
| `A-101` | Heritage / Tower Alpha | 1 | 1,000 sqft | ₹12,000,000.00 | ₹12,600,000.00 | `booked` (Sales Booking) |
| `A-102` | Heritage / Tower Alpha | 1 | 1,150 sqft | ₹13,800,000.00 | ₹14,490,000.00 | `reserved` (Token Hold) |
| `A-201` | Heritage / Tower Alpha | 2 | 1,000 sqft | ₹12,000,000.00 | ₹12,600,000.00 | `sold` (Fully Paid) |
| `A-202` | Heritage / Tower Alpha | 2 | 1,150 sqft | ₹13,800,000.00 | ₹14,490,000.00 | `blocked` (Management) |
| `A-301` | Heritage / Tower Alpha | 3 | 1,000 sqft | ₹12,000,000.00 | ₹12,600,000.00 | `under_construction` |
| `A-302` | Heritage / Tower Alpha | 3 | 1,150 sqft | ₹13,800,000.00 | ₹14,490,000.00 | `available` |
| `B-101..B-302` | Heritage / Tower Beta | 1–3 | 1,000–1,150 | ₹12.0M–₹13.8M | ₹12.6M–₹14.49M | Distributed Across States |
| `Z-101..X-302` | Skyline / Towers Zenith & Apex | 1–3 | 1,000–1,150 | ₹12.0M–₹13.8M | ₹12.6M–₹14.49M | Distributed Across States |

---

## 6. CRM Leads Directory (15 Leads)

| Lead Code | Prospect Name | Contact Phone | Budget Range | Funnel Stage | Lead Source |
| :--- | :--- | :--- | :---: | :--- | :--- |
| `QA-DEMO-LD-001` | Lead1 Investor | 9820100001 | ₹8.0M – ₹15.0M | `new` | Website |
| `QA-DEMO-LD-002` | Lead2 Investor | 9820100002 | ₹8.0M – ₹15.0M | `contacted` | Walk-in |
| `QA-DEMO-LD-003` | Lead3 Investor | 9820100003 | ₹8.0M – ₹15.0M | `site_visit_scheduled` | Referral |
| `QA-DEMO-LD-004` | Lead4 Investor | 9820100004 | ₹8.0M – ₹15.0M | `site_visit_completed` | Digital Ad |
| `QA-DEMO-LD-005` | Lead5 Investor | 9820100005 | ₹8.0M – ₹15.0M | `negotiation` | Property Expo |
| `QA-DEMO-LD-006` | Lead6 Investor | 9820100006 | ₹8.0M – ₹15.0M | `converted` | Website |
| `QA-DEMO-LD-007` | Lead7 Investor | 9820100007 | ₹8.0M – ₹15.0M | `lost` | Walk-in |
| `QA-DEMO-LD-008..015` | Leads 8–15 | 9820100008+ | ₹8.0M – ₹15.0M | Pipeline Rotation | Multi-channel |

---

## 7. Customer Register (10 Verified Customers)

| Customer Code | Customer Name | Email | Phone | PAN Number | KYC Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `QA-DEMO-CUST-001` | Rajesh Singhania | `qa.customer1@estateos.test` | `9833200001` | `ABCDE1001F` | Verified |
| `QA-DEMO-CUST-002` | Priya Venkatesh | `cust2.qa@demo.estateos.test` | `9833200002` | `ABCDE1002F` | Verified |
| `QA-DEMO-CUST-003` | Amitabh Khurana | `cust3.qa@demo.estateos.test` | `9833200003` | `ABCDE1003F` | Verified |
| `QA-DEMO-CUST-004` | Sunita Deshmukh | `cust4.qa@demo.estateos.test` | `9833200004` | `ABCDE1004F` | Verified |
| `QA-DEMO-CUST-005` | Vikram Malhotra | `cust5.qa@demo.estateos.test` | `9833200005` | `ABCDE1005F` | Verified |
| `QA-DEMO-CUST-006` | Neha Chawla | `cust6.qa@demo.estateos.test` | `9833200006` | `ABCDE1006F` | Verified |
| `QA-DEMO-CUST-007` | Sanjay Mittal | `cust7.qa@demo.estateos.test` | `9833200007` | `ABCDE1007F` | Verified |
| `QA-DEMO-CUST-008` | Anand Kothari | `cust8.qa@demo.estateos.test` | `9833200008` | `ABCDE1008F` | Verified |
| `QA-DEMO-CUST-009` | Kavita Agarwal | `cust9.qa@demo.estateos.test` | `9833200009` | `ABCDE1009F` | Verified |
| `QA-DEMO-CUST-010` | Deepak Nambiar | `cust10.qa@demo.estateos.test`| `9833200010` | `ABCDE1010F` | Verified |

---

## 8. Channel Partners & Commission Rules

| Broker Code | Broker / Agency Name | Commission % | KYC Status | Status |
| :--- | :--- | :---: | :--- | :--- |
| `QA-DEMO-BRK-001` | Knight & Frank Realty Advisors | 2.5% | Verified | Active |
| `QA-DEMO-BRK-002` | Square Yards Prime | 2.0% | Verified | Active |
| `QA-DEMO-BRK-003` | Anarock Capital Partners | 3.0% | Verified | Active |

**Commission Rule:** `QA Demo Standard Channel Partner Rule (2.5%)`  
- Basis: 2.5% on Agreement Value  
- Statutory TDS: 5.0% withheld under Section 194H  
- Trigger: Formal Agreement Signing  

---

## 9. Sales Booking, CLP Payment Plan & Collections

- **Booking Reference:** `QA-DEMO-BK-001`
- **Unit Booked:** `A-101` (Carpet: 1,000 sqft, The Heritage Residences)
- **Purchaser:** Rajesh Singhania (`QA-DEMO-CUST-001`)
- **Channel Partner:** Knight & Frank Realty Advisors (`QA-DEMO-BRK-001`)
- **Base Price:** ₹12,000,000.00
- **Total Price (inc. GST):** ₹12,600,000.00
- **Agreement Status:** Signed (`2026-10-20`)

### Construction-Linked Payment Schedule

| Seq | Milestone Trigger | Pct % | Amount (₹) | Paid Amount (₹) | Status | Due Date |
| :---: | :--- | :---: | :---: | :---: | :--- | :---: |
| 1 | Booking Token & Downpayment | 10.0% | ₹1,260,000.00 | ₹1,260,000.00 | **Paid** | 2026-10-25 |
| 2 | 5th Floor Slab Completion | 40.0% | ₹5,040,000.00 | ₹0.00 | **Due** | 2026-11-29 |
| 3 | Final Handover & Possession | 50.0% | ₹6,300,000.00 | ₹0.00 | **Not Due**| 2027-05-15 |

### Cashier Receipts & GL Postings

- **Receipt Number:** `RCP-00001`
- **Received Amount:** ₹1,260,000.00 (Mode: Bank RTGS, Ref: `HDFC-RTGS-998811`)
- **Customer Ledger:** Credited ₹1,260,000.00
- **General Ledger Entry:** Balanced Voucher `JV-000001` (Dr `1120 Bank Current`, Cr `2120 Customer Advances`)

---

## 10. Suppliers, Procurement & Warehouses

### Qualified Vendors

| Vendor Number | Vendor Name | Type | Trade / Service | Status |
| :--- | :--- | :--- | :--- | :--- |
| `QA-DEMO-VND-001` | Tata Steel Infrastructure Ltd | Supplier | TMT Steel Fe550D | Active |
| `QA-DEMO-VND-002` | UltraTech Cement Corporation | Supplier | OPC 53 Grade Cement | Active |
| `QA-DEMO-VND-003` | Kajaria Vitrified Ceramics | Supplier | Floor & Wall Tiles | Active |
| `QA-DEMO-VND-004` | Schindler Lifts India Pvt Ltd | Contractor | High-Speed Elevators | Active |
| `QA-DEMO-VND-005` | Shapoorji Civil Works | Contractor | General Civil Works | Active |

### Warehouses & Materials Inventory

| Warehouse Code | Facility Name | Materials Stocked | Balance on Hand |
| :--- | :--- | :--- | :---: |
| `QA-DEMO-WH-001` | Central Materials Depot — BKC | TMT Steel Fe550D (`MAT-STL-01`) | 500 Tonnes |
| `QA-DEMO-WH-001` | Central Materials Depot — BKC | UltraTech OPC 53 (`MAT-CMT-01`) | 500 Bags |
| `QA-DEMO-WH-002` | Site Staging Store — Bandra | Materials in Transit & Staging | 50 Bags |

---

## 11. Handover Planning & Snagging Management

- **Handover Reference:** `HO-00001`
- **Target Unit:** `A-101` (The Heritage Residences)
- **Planned Possession Date:** December 29, 2026
- **Checklist Inspection:** 10 mandatory pre-possession checks (Structure, Paint, Electrical, Plumbing, Fixtures, Fire Safety, Keys, Meters, RERA Compliance, Customer Walkthrough)
- **Active Snags:**
  - `SNG-00001` (Master Bedroom): Minor paint scuff near wardrobe niche (Finishes — Minor)
  - `SNG-00002` (Balcony): Weatherproof electrical socket cover loose (Electrical — Minor)

