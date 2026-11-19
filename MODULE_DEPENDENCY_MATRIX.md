# EstateOS — Module Dependency Matrix

**Produced:** 14 November 2026. Companion to `MODULE_INTEGRATION_MAP.md` (what is wired)
and `DATABASE_RELATIONSHIP_MAP.md` (what the schema enforces). This document answers a
narrower question: **who owns each master record, and who is allowed to depend on whom.**

---

## 1. Ownership register

Exactly one module may create or amend each master record. Every other module references it
by id. Where a second writer exists today, it is named as a defect.

| Master record | Owning module | Table | Second writer? |
|---|---|---|---|
| Organization | Administration | `organizations` | — |
| User · membership | Administration | `users`, `organization_users` | — |
| Role · permission | Administration | `roles`, `permissions` | — |
| Landowner · land | Land | `landowners`, `lands` | — |
| Project | Projects | `projects` | Construction writes `construction_progress` only (correct) |
| Building · floor · unit | Inventory | `buildings`, `floors`, `units` | — |
| Unit status | **Inventory, via `BookingService::moveUnit()`** | `units.status` | None — single chokepoint, verified |
| Lead | CRM | `leads` | — |
| Customer | Customers | `customers` | CRM creates on conversion — **intended**, deduplicated |
| Quotation | Sales | `quotations` | — |
| Booking | Sales | `bookings` | — |
| Payment plan · installment | Finance | `payment_plans`, `payment_plan_installments` | Construction raises milestone installments (correct) |
| Payment · allocation · ledger | Finance | `customer_payments`, `payment_allocations`, `customer_ledger` | **`database/seed.php:769` bypasses the service** |
| Journal entry | Accounting | `journal_entries`, `journal_entry_lines` | — |
| Vendor | Procurement | `vendors` | — |
| Requisition · PO · GRN | Procurement | `purchase_*`, `goods_receipts` | — |
| Material · stock movement | Warehouse | `materials`, `stock_movements` | None — all writes go through `StockService` |
| Construction task | Construction | `construction_tasks` | — |
| Broker · commission | Brokers | `brokers`, `broker_commissions` | — |
| Employee | HR | `employees` | — |
| Attendance · payroll | HR | `attendance`, `payroll_runs`, `payslips` | — |
| Handover · snag · warranty | Handover | `handovers`, `snag_items`, `warranties` | — |
| Service request | After-sales | `service_requests` | — |
| Document | Documents | `document_files` | — |

**Two ownership violations exist, and only two:**

1. `database/seed.php:769` writes `customer_payments` directly, bypassing
   `CollectionService::receive()`. This is why 65 of 67 payments carry no
   `journal_entry_id`. It is a fixture defect, not a runtime one — but it makes the demo
   database misrepresent the system.
2. Nothing else. Unit status, stock balance and the ledger each have exactly one writer,
   which is the hardest part of this to get right and is already done.

## 2. Dependency matrix

Read as: **row depends on column.** `●` = implemented and exercised · `○` = declared in the
schema but not wired · `·` = no dependency.

| ↓ depends on → | Org | Land | Proj | Inv | CRM | Sales | Fin | Acct | Proc | Whse | Cons | Brok | HR | Docs |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Land**        | ● | — | · | · | · | · | · | **○** | · | · | · | · | · | ● |
| **Projects**    | ● | ● | — | · | · | · | · | · | · | · | · | · | · | ● |
| **Inventory**   | ● | · | ● | — | · | · | · | · | · | · | · | · | · | · |
| **CRM**         | ● | · | ● | ● | — | · | · | · | · | · | · | ○ | ● | · |
| **Sales**       | ● | · | ● | ● | **○** | — | · | · | · | · | · | ○ | ● | ● |
| **Finance**     | ● | · | ● | ● | · | ● | — | ● | · | · | ● | · | · | · |
| **Accounting**  | ● | · | ● | · | · | · | ● | — | ● | ● | · | ● | ● | · |
| **Procurement** | ● | · | ● | · | · | · | · | ● | — | ● | **○** | · | ● | ● |
| **Warehouse**   | ● | · | ● | · | · | · | · | ● | ● | — | ● | · | · | · |
| **Construction**| ● | · | ● | ● | · | · | ● | · | **○** | ● | — | · | ● | ● |
| **Brokers**     | ● | · | · | · | ● | **○** | · | ● | · | · | · | — | ● | ● |
| **HR**          | ● | · | ● | · | · | · | · | ● | · | · | ● | · | — | ● |
| **Handover**    | ● | · | ● | ● | · | ● | ● | · | · | · | ● | · | · | ● |
| **Portal**      | ● | · | ● | ● | · | ● | ● | · | · | · | ● | · | · | ● |
| **Documents**   | ● | · | **○** | · | · | · | · | · | · | · | · | · | · | — |

### The six `○` cells, in severity order

| # | Dependency | What exists | What is missing |
|---|---|---|---|
| 1 | **Land → Accounting** | `postLandPayment()` at `AccountingService.php:555` | **Zero callers.** 336,000,000 paid, 0 journal entries. (INT-001, Critical) |
| 2 | **Construction → Procurement** | `purchase_requisitions.task_id` column | Never written by any code. A task cannot raise a material request. (INT-004) |
| 3 | **Sales → CRM** | Forward write works (`BookingService.php:126`, `:129`) | The **reverse** write is missing: the quotation is never marked `converted` and never stamped with `booking_id`, and no rule requires it to be `accepted` first. (INT-002) |
| 4 | **Brokers → Sales** | `CommissionService::void()` | Never called on cancellation — only an audit note is written. (INT-006) |
| 5 | **CRM/Sales → Brokers** | `leads.broker_id`, `bookings.broker_id` | Populated, but commission is raised only by a manual button. (INT-007) |
| 6 | **Documents → Projects** | `entity_type`/`entity_id` | Authorization applies only when `entity_type='project'`. (INT-008) |

## 3. Layering

Dependencies must flow downward. One rule, and it currently holds:

```
L0  Administration      organizations, users, roles        (depends on nothing)
L1  Masters             lands, projects, buildings, units, customers,
                        vendors, materials, employees, brokers
L2  Transactions        leads, quotations, bookings, requisitions, POs,
                        GRNs, construction tasks, attendance
L3  Financial           payment plans, installments, payments, allocations,
                        stock movements, commissions, payroll
L4  Ledger              journal entries, journal entry lines
L5  Read models         dashboards, reports, customer portal
```

**No upward dependency exists today** — nothing in L1 reads L3, and the ledger is written
by L3 and read only by L5. This is worth protecting, because the fix for INT-001 is a new
L2→L4 write (land payment → journal) and must go through `AccountingService`, not a direct
insert.

The one apparent exception is legitimate: Construction (L2) raising a milestone installment
(L3) at `ConstructionService.php:227` is downward, not upward.

## 4. Shared services — present, missing, and duplicated

| Concern | Shared service | Verdict |
|---|---|---|
| Accounting posting | `AccountingService` | **Present**, 24 call sites, balance enforced |
| Payment allocation | `CollectionService` | **Present**, bypassed only by the seed |
| Schedule generation | `PaymentPlanService` | **Present**, idempotent |
| Stock movement | `StockService` | **Present**, sole writer |
| Procurement workflow | `ProcurementService` | **Present** |
| Commission | `CommissionService` | Present but **under-called** (INT-006, INT-007) |
| Booking lifecycle | `BookingService` | **Present**, holds the unit-status chokepoint |
| Handover gating | `HandoverService` | **Present**, TOCTOU-safe |
| Audit | `Audit::log` | Present but **uneven** — 16 files never call it |
| **Approvals** | — | **Missing.** `approval_requests` is used only by booking; 8 other tables carry a bespoke `approved_by` |
| **Notifications** | — | **Missing.** No `Notification` class; 5 inline insert sites |
| **Reference validation** | — | **Missing.** Each controller validates ids ad hoc; INT-003 is the direct consequence |

The last three are the structural finding of this matrix. Approval, notification and
reference-ownership checking are each implemented repeatedly, inconsistently, and in one
case (INT-003) incorrectly — because no shared service exists to implement them once.

## 5. What this implies for sequencing

- **INT-001 is independent.** Land → Accounting touches no other module and can ship alone.
- **INT-003 should precede INT-002.** Both touch booking reference ids; fixing the security
  hole first means the state-transition work lands on validated input.
- **INT-006 and INT-007 are one piece of work** — both are the Sales↔Brokers edge.
- **INT-005 (project cost rollup) depends on nothing** but reads from procurement,
  warehouse, payroll and the GL; it is a read model (L5) and cannot break L0–L4.
- **A shared reference-validator should come before the FK migration**, so that application
  code stops writing the rows the constraints will later refuse.
