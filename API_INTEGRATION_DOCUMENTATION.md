# EstateOS — Master API & Service Integration Documentation

**Architecture Version**: Enterprise 1.0  
**Runtime**: PHP 8.2 Native MVC + MySQL 10.4 (MariaDB)  
**Security Boundary**: Multi-Tenant Organization Isolation (`organization_id`), Role-Based Access Control (RBAC), CSRF Token Protection, Parameterized Server-Side Prepared Statements, Strict Foreign Key Cascades.

---

## 1. Integration Service Registry

| Service Name | Namespace | Authoritative Responsibility | Connected Consumers |
| :--- | :--- | :--- | :--- |
| **`BookingService`** | `App\Services\BookingService` | Booking confirmation, inventory status locking, discount approvals, cancellations, unit release | CRM, Unit Inventory, Payment Plans, Collections, Document Vault, Broker Commissions, Omnichannel |
| **`CollectionService`** | `App\Services\CollectionService` | Cashier receipts, FIFO installment allocation, advances ledger, reversal audit | Bookings, Payment Plans, Accounting General Ledger, Document Vault, Omnichannel, Customer Portal |
| **`PaymentPlanService`** | `App\Services\PaymentPlanService` | Installment schedule generation, milestone linking, possession linking, penalty calculations | Bookings, Construction Milestones, Collections, Customer Portal |
| **`AccountingService`** | `App\Services\AccountingService` | Double-entry journals, chart of accounts, automated cash/bank and receivable postings | Collections, Procurement (GRN), Vendor Bills, Payroll, Land Purchases |
| **`CommissionService`** | `App\Services\CommissionService` | Slabs, flat & percentage broker rules, automated accrual, Section 194H TDS withholding, payouts | Bookings, Brokers, Approvals, Accounting Ledger |
| **`ConstructionService`** | `App\Services\ConstructionService` | Project milestones, task WBS, progress tracking, automatic demand raising | Projects, Payment Plans, Omnichannel Demand Notices, Executive Dashboard |
| **`HandoverService`** | `App\Services\HandoverService` | Snag audits, gate clearance checks, key releases, warranties, possession deeds | Units, Bookings, Document Vault, Customer Portal |
| **`MailService`** | `App\Services\MailService` | Asynchronous email dispatch queue, HTML transactional templates | Collections, Bookings, Approvals, Handovers |

---

## 2. Cross-Module Service APIs & Contracts

### A. Sales & Commercial Engine

#### `BookingService::create(array $input, string $orgId): array`
- **Purpose**: Atomically locks unit inventory, validates customer KYC, confirms booking, generates payment plan installments, accrues broker commission, catalogs deed in Document Vault, and queues welcome WhatsApp.
- **Request Payload**:
  ```php
  [
      'customer_id'       => (string) UUID,
      'unit_id'           => (string) UUID,
      'project_id'        => (string) UUID,
      'booking_date'      => 'YYYY-MM-DD',
      'agreement_value'   => (float) 15000000.00,
      'booking_amount'    => (float) 1000000.00,
      'plan_type'         => 'possession_linked|fixed_installment|milestone_linked|construction_linked',
      'broker_id'         => ?(string) UUID,
      'payment_mode'      => 'cash|cheque|rtgs|neft|imps|upi',
      'payment_reference' => ?string,
      'discount_amount'   => ?float,
  ]
  ```
- **Returns**: `['booking_id' => string, 'booking_no' => string, 'installments' => int, 'receipt_no' => ?string, 'needs_approval' => bool]`
- **Exceptions**: `HttpException(404, 'Unit/Customer not found')`, `HttpException(422, 'Unit is not available')`, `HttpException(422, 'KYC pending')`.

#### `BookingService::decideDiscount(string $bookingId, bool $approve, string $note, string $orgId): array`
- **Purpose**: Decides on pending discount approvals. If approved, sets booking to `confirmed`, generates payment plan, posts sale value to customer ledger, issues booking receipt, accrues broker commission, catalogs deed, and sends notifications. If rejected, cancels booking and releases unit back to `available`.

---

### B. Collections & Financial Engine

#### `CollectionService::receive(array $input, string $orgId): string`
- **Purpose**: Idempotently records cashier payment, runs FIFO allocation across outstanding installments, holds excess as explicit advance, posts customer ledger, generates double-entry voucher in General Ledger, queues email/WhatsApp receipt, and catalogs receipt PDF in Document Vault.
- **Request Payload**:
  ```php
  [
      'booking'         => array, // Booking record
      'amount'          => (float) 2000000.00,
      'payment_date'    => 'YYYY-MM-DD',
      'payment_mode'    => 'rtgs',
      'reference_no'    => 'TXN-998877',
      'note'            => 'Installment payment',
      'idempotency_key' => 'receipt-cross-mod-12345',
  ]
  ```
- **Returns**: `string` (e.g. `"RCP-00163"`)
- **Ledger Invariant**: Total Debits == Total Credits. Dr Cash/Bank, Cr Customer Advances / Receivables.

---

### C. Construction & Demands Engine

#### `ConstructionService::raiseMilestoneInstallments(string $milestoneId, string $onDate, ?string $orgId = null): int`
- **Purpose**: Triggers payment plan installments linked to a milestone when progress reaches 100%. Sets `due_date = onDate + 15 days`, stamps `raised_at = now()`, and queues personalized WhatsApp demand notices to all affected buyers.
- **Returns**: `int` (Count of installments raised).

---

### D. Central Approvals Engine

#### `ApprovalController::decide(Request $request, string $id): Response`
- **Purpose**: Actionable inbox decision (`approve` / `reject`).
- **Domain Dispatches**:
  - `booking_discount`: Calls `BookingService::decideDiscount()`
  - `purchase_order`: Updates `purchase_orders.status` to `issued` or `cancelled`
  - `broker_commission`: Updates `broker_commissions.status` to `approved` or `voided`
  - `refund`: Updates refund authorization and customer balance

---

### E. Handover & Key Release Engine

#### `HandoverService::complete(string $handoverId, array $input, ?string $orgId = null): array`
- **Purpose**: Enforces all 4 handover gates (Agreement Signed, Zero Financial Dues, Zero Open Critical Snags, 100% Checklist Complete). Upon verification, updates unit `handover_status = completed`, booking `status = completed`, initializes defect liability warranties, catalogs Possession Certificate in Document Vault, and exposes certificate to Customer Portal.
- **Returns**: `['warranties' => int, 'possession_letter_no' => string]`

