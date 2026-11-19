# EstateOS — Automated Business Workflows Documentation

This document specifies the end-to-end automated business workflows implemented in EstateOS, detailing state machines, data transformations, invariant rules, and failure recovery.

---

## 1. Complete Property Sales & Inventory Allocation Workflow
```
[Lead Acquisition]
       │
       ▼
[Lead Activities & Site Visits]
       │
       ▼
[Convert Lead → Customer Master]
       │
       ▼
[Unit Hold (Lock & Auto-Expiry)]
       │
       ▼
[Quotation Generation & PLC / Floor Premium Calculation]
       │
       ├── Discount > Org Threshold? ──► [Central Approvals Inbox] ──► [Reject → Release Hold]
       │                                            │ (Approved)
       ▼                                            ▼
[Booking Confirmation (Unit Atomic Lock: 'booked')]
       │
       ├──► [Payment Plan Schedule Generation (Installments)]
       ├──► [Broker Commission Auto-Accrual (Gross - 5% TDS u/s 194H)]
       ├──► [Document Vault: Booking Confirmation Deed]
       └──► [Omnichannel WhatsApp: Customer Welcome & Booking Notice]
```

### Business Rules & Invariants:
1. **Atomic Inventory Isolation**: `units.inventory_status` transition from `available` $\to$ `hold` $\to$ `booked` is serialized using `SELECT ... FOR UPDATE` transactions with unique constraints on `bookings(unit_id, active_flag)`. Two concurrent customers can never book the same unit.
2. **Mandatory KYC**: A booking cannot proceed if the organization's KYC policy requires verified status and the buyer's KYC is `pending`.

---

## 2. Collections, FIFO Allocation & Accounting Integration Workflow
```
[Cashier Collections / Online Gateway]
       │
       ▼
[Idempotency Verification (Prevents Duplicate Receipts)]
       │
       ▼
[FIFO Installment Walk (Oldest Due Date First)]
       │
       ├── Partially Fills / Closes Installment(s)
       └── Excess Amount ──► [Explicit Advance Allocation]
       │
       ├──► [Customer Subledger: Credit Entry]
       ├──► [General Ledger Posting: Dr Cash/Bank, Cr Advances / Receivables]
       ├──► [Document Vault: Official Cash Receipt PDF]
       ├──► [Transactional Email: Buyer Receipt]
       └──► [Omnichannel WhatsApp: Payment Confirmation & Outstanding Balance]
```

### Financial Invariants:
1. `Total Payable = Amount Paid + Outstanding Balance`
2. `Total Debits = Total Credits` for all posted journal entries.
3. Once confirmed, payment records are immutable. Corrections are performed via audit-stamped reversals.

---

## 3. Construction Milestones & Dynamic Demand Generation
```
[Site Engineering: Milestone Progress Update]
       │
       ▼
[Milestone Progress Reaches 100%]
       │
       ▼
[Auto-Detect Waiting Linked Installments]
       │
       ├── Sets due_date = completion_date + 15 days
       ├── Updates status = 'pending' and stamps raised_at = now()
       │
       ├──► [Customer Portal: Demand Notice Badge]
       └──► [Omnichannel WhatsApp: Automated Demand Letter to Buyer]
```

---

## 4. Procurement, Goods Receipt (GRN) & Perpetual Stock Movement
```
[Material Requisition from Site]
       │
       ▼
[Central Approvals: Purchase Request Review]
       │
       ▼
[Purchase Order Issuance to Vendor (Status: 'issued')]
       │
       ▼
[Warehouse Delivery: Physical Inspection & QC]
       │
       ▼
[Goods Receipt Note (GRN) Creation]
       │
       ├── Warehouse Stock Increment (On-Hand += Accepted Qty)
       ├── Stock Movement Ledger Entry (Source: GRN, Type: Inward)
       ├── Weighted Average Cost (WAC) Recalculation
       └── General Ledger: Dr Inventory, Cr Accounts Payable (GRN Clearing)
```

---

## 5. Broker Attribution, Statutory Withholding & Commission Payout
```
[Booking Linked to Authorized Broker]
       │
       ▼
[Automatic Rule Resolution (Most Specific: Broker + Project > Priority)]
       │
       ▼
[Commission Computation: Gross = Rate × Basis Amount]
       │
       ├── Statutory Withholding: TDS 5% u/s 194H
       ├── Net Payable = Gross - TDS
       └── Status: 'pending' in Broker Ledger
       │
       ▼
[Central Approvals: Approval Inbox Decision]
       │
       ├── If Approved ──► Status: 'approved' ──► Payment Voucher in Accounts
       └── If Voided   ──► Status: 'voided'   ──► Reverse Accrual
```

---

## 6. Handover Clearance Gates & Digital Possession
```
[Pre-Possession Joint Inspection]
       │
       ▼
[Snag Audit: Rectification by Site Contractors (Status: 'resolved')]
       │
       ▼
[Four Mandatory Handover Gates Check]
       ├── Gate 1: Sale Agreement Status == 'signed'
       ├── Gate 2: Financial Outstanding <= 0.00 (100% Installments Cleared)
       ├── Gate 3: Critical / Major Snags Open == 0
       └── Gate 4: Handover Checklist Items Completed == 100%
       │
       ▼
[Handover Complete Execution]
       │
       ├── Unit Status ──► 'completed'
       ├── Booking Status ──► 'completed'
       ├── Defect Liability Warranties Initialized (12 Months)
       ├── Document Vault: Possession Certificate PDF
       └── Customer Portal: Instant Certificate Access & Digital Keys Released
```

