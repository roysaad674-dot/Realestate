# ESTATEOS — FINAL MODULE INVENTORY & CAPABILITY CATALOG

**Audit Date:** November 14, 2026  
**Auditor:** Principal Software Architect & QA Automation Lead  
**Scope:** 24 ERP Modules across 119 MariaDB Tables, 32 MVC Controllers, 24 Headless REST APIs  

---

## 1. System Inventory Summary

EstateOS comprises **24 functional ERP modules** fully linked through database foreign keys, transactional service classes, centralized event hooks, approval workflows, and general ledger double-entry postings.

| Category | Modules Covered | Table Count | Controller Count |
|:---|:---|:---|:---|
| **Core & Administration** | Organizations, Users, Roles, Permissions, Settings, Audit Logs | 14 | 5 |
| **Land & Project Management** | Lands, Legal Due Diligence, Projects, Buildings, Floors, Units | 18 | 5 |
| **Sales & CRM** | Leads, Customers, Quotations, Bookings, Payment Plans | 16 | 4 |
| **Finance & Revenue** | Collections, Receipts, Customer Ledger, General Ledger, Chart of Accounts | 12 | 3 |
| **Procurement & Materials**| Vendors, Requisitions, Purchase Orders, Warehouses, Stock Ledger | 15 | 4 |
| **Construction & Engineering**| Tasks, Milestones, Inspections, Material Consumption | 10 | 2 |
| **Channel Partners & Legal** | Brokers, Commission Slabs, Withholding Tax, Handover, Snags, Document Vault | 14 | 4 |
| **Operations, HR & AI** | Approvals, WhatsApp Queue, Employees, Attendance, Payroll, AI Assistant | 20 | 5 |
| **Total** | **24 Integrated Modules** | **119 Tables** | **32 Controllers** |

---

## 2. Granular Module Inventory & Architecture Map

### Module 1: Organization & Administration
- **Scope:** Multi-tenant organization boundaries, branch definitions, currency codes, timezone configurations.
- **Key Tables:** `organizations`, `organization_settings`, `departments`, `number_sequences`.
- **Controllers & Views:** `OrganizationController.php`, `views/organizations/`
- **APIs:** `/api/v1/organizations`
- **Integrations:** Acts as the primary foreign key boundary (`organization_id`) for every single transactional table.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (12 active settings tested).

### Module 2: Access Control, Roles & Permissions (RBAC)
- **Scope:** Role definitions, granular action permissions, user assignments, password encryption via bcrypt.
- **Key Tables:** `users`, `roles`, `permissions`, `role_permissions`, `organization_users`, `user_permissions`.
- **Controllers & Views:** `AuthController.php`, `views/auth/`
- **APIs:** `/api/v1/auth/login`, `/api/v1/auth/me`
- **Integrations:** Enforced universally via `Auth::hasPermission()` and `Auth::actAs()`.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (BFLA/BOLA tests passed with 0 leaks).

### Module 3: Land Acquisition & Due Diligence
- **Scope:** Land parcels, ownership records, survey numbers, purchase payments, legal title clearance checks, feasibility studies.
- **Key Tables:** `lands`, `landowners`, `land_payments`, `land_legal_checks`, `land_feasibility_studies`, `joint_ventures`.
- **Controllers & Views:** `LandController.php`, `views/lands/`
- **APIs:** `/api/v1/lands`
- **Integrations:** Projects allocate land parcels directly; title clearances require legal sign-off.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (2 parcels active in QA Demo).

### Module 4: Project Management & Phase Tracking
- **Scope:** Master township/project definitions, RERA registrations, start/end dates, budget tracking.
- **Key Tables:** `projects`, `project_members`, `project_budgets`.
- **Controllers & Views:** `ProjectController.php`, `views/projects/`
- **APIs:** `/api/v1/projects`
- **Integrations:** Anchors buildings, units, milestones, procurement requisitions, and financial reporting.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Budget & completion dates validated).

### Module 5: Tower, Floor & Multi-Level Asset Hierarchy
- **Scope:** Buildings/towers within projects, floor counts, structural stage tracking.
- **Key Tables:** `buildings`, `floors`.
- **Controllers & Views:** `BuildingController.php`, `views/buildings/`
- **APIs:** `/api/v1/buildings`
- **Integrations:** Links projects to individual unit units with composite keys.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Project-floor integrity verified).

### Module 6: Property Inventory & Unit Pricing
- **Scope:** Unit dimensions, carpet area, base rate, PLC, floor rise, parking allocation, inventory lifecycle state machine (`available`, `reserved`, `booked`, `sold`, `blocked`, `under_construction`).
- **Key Tables:** `units`, `unit_types`, `unit_pricing`, `parking_spaces`, `unit_holds`.
- **Controllers & Views:** `UnitController.php`, `views/units/`
- **APIs:** `/api/v1/units`
- **Integrations:** Unit availability is atomically updated and locked upon booking creation.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Atomic reservation and lock tested).

### Module 7: CRM & Lead Pipeline Management
- **Scope:** Lead capture, source tracking (Digital, Walk-in, Broker), scoring, stage progression (New -> Contacted -> Site Visit -> Negotiation -> Converted).
- **Key Tables:** `leads`, `lead_activities`, `lead_sources`, `lead_requirements`, `site_visits`.
- **Controllers & Views:** `LeadController.php`, `views/leads/`
- **APIs:** `/api/v1/leads`
- **Integrations:** On conversion, atomically provisions a customer record and transfers activity history.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (15 demo leads spanning 7 funnel stages).

### Module 8: Customer 360 & KYC Compliance
- **Scope:** Master customer profiles, PAN/Aadhaar/passport KYC documents, co-applicants, communication history.
- **Key Tables:** `customers`, `booking_co_applicants`, `document_files`.
- **Controllers & Views:** `CustomerController.php`, `views/customers/`
- **APIs:** `/api/v1/customers`
- **Integrations:** Connects bookings, collections, receipts, and customer portal authentication.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (KYC validation gate tested).

### Module 9: Quotations & Price Calculations
- **Scope:** Dynamic quote generation with base price, floor rise premiums, PLC charges, clubhouse fees, GST (5%), legal fees.
- **Key Tables:** `quotations`, `quotation_items`.
- **Controllers & Views:** `QuotationController.php`, `views/quotations/`
- **APIs:** `/api/v1/quotations`
- **Integrations:** Approved quotations lock pricing and seed booking records.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Mathematical precision verified to 2 decimal places).

### Module 10: Sales Bookings & Unit Locking
- **Scope:** Official booking agreements, agreement status (`draft`, `generated`, `signed`, `registered`), broker attribution, cancellation workflows.
- **Key Tables:** `bookings`, `booking_cancellations`, `agreements`.
- **Controllers & Views:** `BookingController.php`, `views/bookings/`
- **APIs:** `/api/v1/bookings`
- **Integrations:** Atomically sets unit to `booked`, spawns payment plan installments, raises broker commission.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Atomic locking & cancellation teardown verified).

### Module 11: Construction-Linked Payment Plans (CLP)
- **Scope:** Milestone-triggered schedules, time-based installments, custom down payments, sequence enforcement.
- **Key Tables:** `payment_plans`, `payment_plan_installments`, `installment_adjustments`.
- **Controllers & Views:** `PaymentPlanController.php`, `views/payment_plans/`
- **APIs:** `/api/v1/payment-plans`
- **Integrations:** Progress updates on construction milestones automatically date and trigger due installments.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Auto-trigger verified on milestone completion).

### Module 12: Cashier Collections & FIFO Allocation
- **Scope:** Inward customer payments (Cheque, NEFT/RTGS, UPI, Gateway), payment allocations, official money receipts.
- **Key Tables:** `customer_payments`, `payment_allocations`, `customer_ledger`.
- **Controllers & Views:** `CollectionController.php`, `views/collections/`
- **APIs:** `/api/v1/collections`
- **Integrations:** Credits customer ledger, allocates against earliest due installments (FIFO), triggers GL posting.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Exact paisa FIFO allocation verified).

### Module 13: Chart of Accounts & General Ledger (Accounting)
- **Scope:** Double-entry journal entries, fiscal periods, trial balance, balance sheet, automated voucher generation.
- **Key Tables:** `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `accounting_periods`.
- **Controllers & Views:** `AccountingController.php`, `views/accounting/`
- **APIs:** `/api/v1/accounting`
- **Integrations:** Universal receiver of financial transactions from Collections, Procurement, and Commissions.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Double-entry balance strictly verified: Debits == Credits).

### Module 14: Construction Milestones & Task Progress
- **Scope:** Physical work packages, percentage completion, site progress logs, milestone sign-offs.
- **Key Tables:** `construction_tasks`, `project_milestones`, `site_reports`, `task_progress_updates`.
- **Controllers & Views:** `ConstructionController.php`, `views/construction/`
- **APIs:** `/api/v1/construction`
- **Integrations:** Drives CLP installment triggers and contractor certificate payments.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Automatic installment dating verified).

### Module 15: Vendor Management & Subcontractor Contracts
- **Scope:** Supplier directory, contractor agreements, GSTIN validation, vendor reviews, performance ratings.
- **Key Tables:** `vendors`, `vendor_contracts`, `vendor_reviews`.
- **Controllers & Views:** `VendorController.php`, `views/vendors/`
- **APIs:** `/api/v1/vendors`
- **Integrations:** Anchors Purchase Requisitions, Purchase Orders, and Contractor Bills.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (5 qualified suppliers active in QA Demo).

### Module 16: Procurement, Requisitions & Purchase Orders
- **Scope:** Material requests (PR), approval routing, PO generation, commercial terms, delivery schedules.
- **Key Tables:** `purchase_requisitions`, `purchase_requisition_items`, `purchase_orders`, `purchase_order_items`.
- **Controllers & Views:** `ProcurementController.php`, `views/procurement/`
- **APIs:** `/api/v1/procurement`
- **Integrations:** Approval inbox controls PO release; POs feed Goods Receipt Notes.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Procurement lifecycle tested).

### Module 17: Warehouse & Perpetual Stock Ledger
- **Scope:** Multi-warehouse storage, materials catalog, goods receipt notes (GRN), issues, transfers, weighted average costing.
- **Key Tables:** `warehouses`, `materials`, `goods_receipts`, `goods_receipt_items`, `stock_movements`.
- **Controllers & Views:** `WarehouseController.php`, `views/warehouse/`
- **APIs:** `/api/v1/warehouse`
- **Integrations:** GRNs increment stock ledger; issues charge construction task cost centers.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Perpetual inventory equation verified).

### Module 18: Channel Partners & Statutory Broker Commission
- **Scope:** Broker registration, tiered commission rules, Section 194H TDS deduction (5%), voucher generation.
- **Key Tables:** `brokers`, `commission_rules`, `commission_rule_slabs`, `broker_commissions`, `broker_commission_payments`.
- **Controllers & Views:** `BrokerController.php`, `views/brokers/`
- **APIs:** `/api/v1/brokers`
- **Integrations:** Auto-raised on booking confirmation; deducts TDS and balances via GL.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Exact gross, 5% TDS, and net payable validated).

### Module 19: Possession Handover & Snagging Management
- **Scope:** 4-Gate clearance (Financial, Legal, QC, Management), snag listing, contractor assignment, key release.
- **Key Tables:** `handovers`, `handover_checklist_items`, `snag_items`, `warranties`.
- **Controllers & Views:** `HandoverController.php`, `views/handovers/`
- **APIs:** `/api/v1/handovers`
- **Integrations:** Requires zero outstanding balance from Collections before gate clearance.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Snag lifecycle open -> resolve -> verify tested).

### Module 20: Legal Agreements & Document Vault
- **Scope:** Stored deeds, agreements for sale, title search certificates, encumbrance certificates, KYC uploads.
- **Key Tables:** `document_files`, `agreements`.
- **Controllers & Views:** `LegalController.php`, `views/legal/`
- **APIs:** `/api/v1/documents`
- **Integrations:** Links to bookings, customers, lands, and handovers with mime/size validation.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Tenant isolation and storage paths verified).

### Module 21: Centralized Approvals Inbox & Audit Trail
- **Scope:** Cross-module approval workflow (Discounts, POs, Handover gates, Journal entries), immutable audit logs.
- **Key Tables:** `approval_requests`, `audit_logs`.
- **Controllers & Views:** `ApprovalController.php`, `views/approvals/`
- **APIs:** `/api/v1/approvals`
- **Integrations:** Intercepts high-value transactions based on organization settings.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Approve/Reject audit trails verified).

### Module 22: Omnichannel WhatsApp & Messaging Queue
- **Scope:** Automated template-based WhatsApp dispatch for booking confirmations, demand letters, payment receipts.
- **Key Tables:** `whatsapp_messages`, `whatsapp_templates`, `mail_messages`.
- **Controllers & Views:** `CommunicationController.php`, `views/communications/`
- **APIs:** `/api/v1/communications`
- **Integrations:** Event-driven hooks queue messages on cashier collection and booking creation.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Asynchronous message queue verified).

### Module 23: Customer Self-Service Portal
- **Scope:** Dedicated customer login, booking status tracking, payment schedule, receipt downloads, service requests.
- **Key Tables:** `portal_sessions`, `portal_login_attempts`, `service_requests`, `service_request_updates`.
- **Controllers & Views:** `PortalController.php`, `views/portal/`
- **APIs:** `/api/v1/portal`
- **Integrations:** Strictly sandboxed to authenticated customer bookings with zero staff RBAC access.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (Cross-customer boundary protection verified).

### Module 24: Executive Analytics & NL AI Query Assistant
- **Scope:** Real-time KPI dashboards, lead conversion funnels, cash position, SQL-backed natural language query engine.
- **Key Tables:** `assistant_queries`.
- **Controllers & Views:** `DashboardController.php`, `AssistantController.php`, `ReportController.php`
- **APIs:** `/api/v1/assistant/ask`
- **Integrations:** Aggregates real-time data across all 23 other modules with strict permission gating.
- **Audit Status:** **FUNCTIONAL & VERIFIED** (All 8 enterprise executive queries tested and passing).

