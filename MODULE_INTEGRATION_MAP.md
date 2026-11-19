# EstateOS — Module Integration Map

**Produced:** 14 November 2026 · **Method:** code inspection + live schema + live data.
Every claim below carries a `file:line` or a query result. Nothing here is inferred from
naming conventions.

---

## 1. How to read this

A link between two modules is only called **Implemented** when backend code performs it.
A column that *could* hold the link, a form field, a view that joins two tables, or a route
that accepts an id does **not** count. Three states are used:

| State | Meaning |
|---|---|
| **Implemented** | Backend code performs the link. Cited. |
| **Partial** | The link works in one direction, or only on one code path. |
| **Missing** | No code performs it, however suggestive the schema is. |

## 2. System shape

| | |
|---|---|
| Tables | 117 (all InnoDB) |
| Declared foreign keys | 204 |
| `*_id` columns with **no** FK behind them | **118** |
| Tables carrying `organization_id` | 96 of 117 |
| Controllers · Services · Routes | 43 · 18 · 425 |

## 3. The integration spine

```
Organization ─┬─ Land ──── Projects ─── Buildings ─ Floors ─ Units
              │     │                                    │
              │     └── land_payments ✗ ledger           │
              │                                          │
              ├─ Leads ─── Customers ─── Quotations ─ ─ ─┤
              │     ✔ dedup        ⚠ prefill only        │
              │                                      Bookings
              │                                          │
              │                    ┌─────────────────────┤
              │                    │                     │
              │              Payment plans         Unit status ✔
              │                    │
              │              Installments ─ Payments ─ Allocations ─ Ledger ─ Accounting ✔
              │
              ├─ Vendors ─ Requisitions ─ POs ─ GRNs ─ Stock ─ Accounting ✔
              │                                   │
              ├─ Construction ─ Tasks ─ Progress ─┘ ✗ (no requisition link)
              │        └─ Milestones ─── Installments ✔
              │
              ├─ Brokers ─ Commission ─ Accounting ✔   (raise: manual ⚠ · void: missing ✗)
              ├─ HR ─ Attendance ─ Payroll ─ Accounting ✔
              └─ Handover ─ Snags ─ Clearance ✔ ─ Portal ✔
```

`✔` implemented · `⚠` partial · `✗` missing

## 4. Module-by-module

### 4.1 Organization & Administration — **Implemented**

Users are global; membership is the `organization_users` pivot (`user_id`, `organization_id`,
`role_id`, `is_default`). `users` correctly carries no `organization_id`. 96 of 117 tables
carry `organization_id` directly; the other 21 are child rows that inherit scope through a
parent — legitimate **except** `user_project_access`, which has neither `organization_id`
nor any FK on `user_id`/`project_id`.

ACL is a role grant plus a per-user overlay (`user_permissions.effect`), applied centrally
in the front controller before the controller runs.

| Gap | Severity |
|---|---|
| `user_project_access` is neither tenant- nor referentially constrained | Medium |

### 4.2 Land → Projects — **Partial**

Land, landowners, legal checks, feasibility and acquisition payments all exist and are
linked to projects. Land cost is recorded.

> **INT-001 — land payments never reached the general ledger. Critical.**
> **New payments: FIXED (14 Nov). Historical backfill: outstanding.**
>
> `AccountingService::postLandPayment()` was fully written at `AccountingService.php:555`
> with **zero callers**. `LandController::recordPayment()` took the money, updated
> `land_payments.paid_amount`, wrote an audit row — and posted no journal. Migration 006
> had even added `land_payments.journal_entry_id` and `bank_account_id` under the heading
> *"Link the money records created in Phase 2 to their journals"*. The plumbing was laid
> and never connected.
>
> At the time of the audit: **336,000,000.00** paid, **0** journal entries.
>
> **What was done.** The posting now runs inside the same transaction as the payment
> (`LandController.php:382-402`), so the two cannot diverge — if the journal is refused, a
> closed period for instance, the payment rolls back with it. The entry id is written back
> to `land_payments.journal_entry_id`, matching how commissions and vendor payments already
> behave. Guarded by `isConfigured()`, consistent with the other 24 posting sites.
>
> One defect was found and fixed while wiring it: the idempotency key was built from the
> payment amount, so **two part-payments of the same size against one installment would
> have collided** and the second would have been silently swallowed as a replay. The key now
> uses the cumulative total the installment has reached, which only ever increases while a
> retried request lands on the same value. `tests/e2e-phase1.sh` covers this directly —
> *"both part-payments posted, not just the first"*.
>
> **Still outstanding:** the 336,000,000 already paid remains off-book. 2 rows carry it, and
> backfilling them is Stage 1.2 in `INTEGRATION_IMPLEMENTATION_PLAN.md` — it moves the trial
> balance by design and needs its entries dated to the original payments, so it is a
> deliberate, separate step.

### 4.3 Projects → Buildings → Floors → Units — **Implemented**

The hierarchy is fully keyed. Unit status has one chokepoint — `BookingService::moveUnit()`
(`BookingService.php:617-642`) — which writes `unit_status_history` and an audit row on
every transition. 130 history rows exist. No code writes `units.status` directly.

### 4.4 CRM: Leads → Customers — **Implemented**

`LeadController.php:679-735` converts inside a transaction: looks up an existing customer by
`organization_id + phone` (`:680`) and **links rather than duplicating** when found
(`:685-695`); otherwise creates one carrying `lead_id` (`:708`). The lead keeps
`customer_id` and a `lead_activities` row (`:726`). Converted leads cannot be archived or
re-staged (`:377`, `:404`).

| Gap | Severity |
|---|---|
| `site_visits` has **zero** foreign keys — disconnected at schema level | Medium |
| Dedup is phone-only; no email or name matching | Low |

### 4.5 Customers → Quotations → Bookings — **Partial**

Pricing is computed server-side from the unit row in both paths
(`QuotationController.php:151`, `BookingService.php:85-91`) — a client cannot post a price.

Booking concurrency is correct: a transaction opens at `BookingService.php:36`, takes
`SELECT ... FOR UPDATE` on the unit (`:38-41`) and re-checks status (`:46`), with
`UNIQUE KEY uq_booking_active_unit (unit_id, active_flag)` as the database backstop.
**Two customers cannot book the same unit.**

> **INT-002 — the quotation is never told it became a booking. High.**
> The forward link works: `BookingService.php:126` writes `lead_id` and `:129` writes
> `quotation_id` when the form supplies them, and `:245-254` moves the lead's stage and
> writes a `lead_activities` row. The phase 2 suite asserts this and passes
> (`e2e-phase2.sh:322`).
>
> What is missing is the **reverse** write and the **gate**:
> - `BookingService::create()` never writes back to `quotations` — no status change to
>   `converted`, no `booking_id` stamped on the quote. An accepted quotation stays
>   `accepted` for ever and can seed unlimited bookings.
> - Nothing requires `quotations.status = 'accepted'` before a booking cites it. A `draft`
>   or `rejected` quote is equally acceptable.
>
> Live data shows 16 bookings all with `quotation_id IS NULL`, but that is **seed
> infidelity, not the code path** — `database/seed.php` creates bookings without a quote.
> The runtime path populates it.

> **INT-003 — cross-tenant leak through unvalidated reference ids. High (security).**
> `BookingController.php:224-228` validates `lead_id`, `quotation_id`, `sales_executive_id`
> and `broker_id` as `nullable|uuid` — **format only, no ownership check** — and
> `BookingService` inserts them verbatim. The GET form path scopes correctly by
> `organization_id`; the POST path does not, so the protection is bypassed by posting
> directly.
> It becomes a read leak on the show page, where the lookups carry no tenant filter:
> ```php
> 394: Database::first('SELECT * FROM approval_requests WHERE id = :a', ...)
> 398: Database::first('SELECT id, quote_no, total_amount, status FROM quotations WHERE id = :q', ...)
> 402: Database::first('SELECT id, code, first_name, last_name, source_slug FROM leads WHERE id = :l', ...)
> ```
> A crafted POST attaches another tenant's quotation; its `quote_no` and `total_amount`
> then render. The existing 135-check security suite does not cover this.

### 4.6 Bookings → Installments → Collections — **Implemented**

Schedule generation is idempotent two ways: an existence check at
`PaymentPlanService.php:147-153` **and** `UNIQUE uq_plan_booking` on
`payment_plans.booking_id`. Percentages are server-side; the final installment absorbs
rounding (`:189-192`) so the schedule totals the booking price exactly.

Allocation (`CollectionService.php:149-217`) is oldest-due-first under `FOR UPDATE`, with
the remainder written as an explicit `advance` row so allocations always reconcile to the
receipt. All arithmetic is `round(...,2)` on DECIMAL columns. Outstanding balance is
computed live (`:220-243`) and is therefore incapable of drifting.

Duplicate payments are blocked at the database: `uq_payment_idem (organization_id,
idempotency_key)`, plus `uq_receipt_no`.

### 4.7 Collections → Accounting — **Implemented** (with one caveat)

`AccountingService::post()` rejects unbalanced entries before any write
(`:156-162`, `abs(debit−credit) > EPSILON`), negative lines (`:128`), both-sided lines
(`:131`), zero lines (`:137`) and posting into a closed period (`:109-115`). Live trial
balance is `0.00`. Reversal (`CollectionService::reverse()`, `:249-359`) unwinds
allocations, recomputes installment status, marks the original `reversed` — never deletes —
and posts a mirror entry.

| Caveat | Severity |
|---|---|
| **`isConfigured()` silent skip.** All 24 posting sites are wrapped in `if (AccountingService::isConfigured(...))`, which is only `COUNT(*) > 0` on `chart_of_accounts` (`AccountingService.php:1336-1343`). An organization without a chart of accounts transacts money with **no ledger entry and no warning**. | Medium |
| **`credit_notes` is a dead table** — 0 rows, no code writes it anywhere | Low |

### 4.8 Procurement → Vendors → Purchase Orders — **Implemented**

Every hop is real: `submitRequisition` (`ProcurementService.php:34`), `decideRequisition`
(`:58`, self-approval refused at `:67`), `orderFromRequisition` (`:89`, unapproved refused
at `:95`), `receiveGoods` (`:295`), `refreshOrderStatus` (`:457`). Over-receipt against the
PO outstanding is refused (`:364-372`). Vendor bill approval posts to the ledger
(`:546-562`); at `AccountingService.php:671` the bill deliberately posts **only tax** when
the GRN already booked the stock and payable, avoiding a double count.

### 4.9 Procurement → Warehouse → Inventory — **Implemented**

**There is no stored stock balance anywhere** — verified against `information_schema`.
Balance is `SUM(direction * quantity)` over `stock_movements` (`StockService.php:42-56`);
valuation is weighted-average from inward movements (`:63-86`). All 12 `StockService` call
sites go through `record()` or `transfer()`, both of which insert a movement. Outward
movement is refused when the store lacks quantity (`:243-256`), so **stock cannot go
negative**.

### 4.10 Construction → Procurement → Warehouse — **Partial**

Progress is genuinely computed: task progress (`ConstructionService.php:48`) rolls into a
weighted milestone average (`:146-192`, `SUM(weight_pc * progress_pc)`) and into
`projects.construction_progress` (`:212`). Dependency gating at `:87`. Completing a
milestone raises its installment (`:227`) — a real construction→finance link.

> **INT-004 — construction→procurement is schema-only. High.**
> `purchase_requisitions.task_id` exists and is **never written**.
> `ConstructionController.php` contains no `requisition` or `purchase` reference at all, and
> `ProcurementService` never sets `task_id`. A site engineer cannot raise a material request
> from a task. Live data: 1 requisition, 0 with a task.

> **INT-005 — project cost rollup does not exist. High.**
> `project_budgets` holds `budget_amount` and `revised_amount` but **no actual column**, and
> nothing computes actual-versus-budget. No query anywhere sums `expenses` by project.
> Procurement spend, material consumption and payroll never aggregate into a project cost
> figure — even though `expenses.project_id`, `stock_movements.project_id` and the GL all
> carry the data needed.

Material issue itself works (`WarehouseController.php:221-244` records an `issue` carrying
`task_id` and `project_id`, posted via `StockService.php:294`) — but it can only be started
from the Warehouse module.

### 4.11 Brokers → Commission → Accounting — **Partial**

Duplicate generation is genuinely prevented, twice: at `CommissionService.php:166-172` and
by `UNIQUE KEY uq_commission_booking (booking_id)`. Approval posts the cost (`:253-257`);
payment posts and writes `journal_entry_id` back (`:330-335`).

> **INT-006 — a cancelled booking never voids its commission. High (money).**
> `BookingService.php:498-505` writes an audit *note* reading
> *"Broker commission is void on cancellation under current policy"* — and then does not
> void it. The comment at `:499` admits the gap: *"the commission ledger itself arrives with
> the broker module."* `CommissionService::void()` exists but is called only manually from
> `BrokerController.php:585`. A cancelled booking leaves its commission `approved` or
> `part_paid`, and still payable.

> **INT-007 — commission is never raised automatically. Medium.**
> `CommissionService::raise()` has two callers, both in `BrokerController`
> (`:494`, `:518`). Booking confirmation never calls it. Until someone opens the broker
> screen and presses a button, an eligible booking has no commission.

### 4.12 Legal → Documents — **Partial**

Path traversal is properly defended (`realpath` + root-prefix check,
`DocumentController.php:190`).

> **INT-008 — document authorization covers one entity type. Medium (security).**
> Per-record authorization runs **only** when `entity_type === 'project'`
> (`DocumentController.php:182-184`). A document attached to a customer, booking, land, unit
> or vendor gets no ownership check, so a user restricted via `user_project_access` can
> still download KYC and agreements outside their projects. The polymorphic
> `entity_type`/`entity_id` pair carries no FK and is not validated on write.

### 4.13 HR → Payroll — **Implemented**

`fk_employee_user` → `users(id) ON DELETE SET NULL` with `UNIQUE uq_employee_user` gives a
true, optional 1:1. Payroll posts journals (`PayrollService.php:255-256`) and payment
(`:311-312`).

### 4.14 Customer Portal — **Implemented**

Every query binds `customer_id` from the portal session. Single-record lookups are
double-keyed on id **and** `customer_id` (`PortalController.php:235`, `:334`, `:375`,
`:403`). No route trusts a client-supplied customer id. Portal routes skip the staff ACL
because a portal session never holds a staff identity.

### 4.15 Handover — **Implemented, and the best-built gate in the system**

Gates are re-evaluated at commit time **inside** the transaction under `SELECT ... FOR
UPDATE`, rather than trusting an earlier "ready" flag (`HandoverService.php:359-368`).
Dues (`outstanding <= 0.009`), blocking snags, agreement signature and mandatory checklist
are all enforced server-side. This defends the TOCTOU correctly.

## 5. Cross-cutting concerns

| Concern | State | Evidence |
|---|---|---|
| Audit trail | **Partial** — 3,614 rows, but 16 files never call `Audit::log`, including `ApprovalController`, `PayrollController`, `InventoryController` | grep |
| Approvals | **Not reusable** — `approval_requests` is used only by booking; 8 other tables carry a bespoke `approved_by` | schema |
| Notifications | **Ad hoc** — no `Notification` class exists; 5 inline `Database::insert('notifications', …)` sites with per-caller payloads | grep |
| Dashboards | **Computed, not hardcoded** (`MetricsService`, 428 lines) — but only **one** dashboard exists, not the five role-specific ones | `app/Views/dashboard/` |
| Tenant isolation | Strong by default, **except** INT-003 and `user_project_access` | — |

## 6. Data-integrity damage already present

Because 118 reference columns carry no FK, deletions have already orphaned financial rows:

| Orphan | Count |
|---|---|
| `customer_ledger` → missing customer | **26** |
| `customer_ledger` → missing booking | **27** |

That is ~27% of the 98-row ledger pointing at rows that no longer exist. With
`ON DELETE RESTRICT` these writes could not have happened.

A second, separate fidelity problem: 65 of 67 confirmed payments carry
`journal_entry_id IS NULL`, inserted in bulk by `database/seed.php:769`, which bypasses
`CollectionService::receive()`. The organization *does* have 72 chart-of-accounts rows, so
this is seed infidelity rather than an `isConfigured()` miss — but it means the demo
database shows collections and accounting roughly 249M apart.

## 6a. INT-017 — a flaky suite that tested a different project each run · **FIXED**

Found by running the gate twice. The first run was **13/13**; the second **12/13**, with
phase 2 failing on:

```
FAIL and every milestone-linked installment is dated from its milestone
     expected [0], got [2]
```

> **A first diagnosis blamed leftover state from the phase 4 construction suite. That was
> wrong, and is recorded here because the correction is the useful part.** The two completed
> milestones are **seeded**, by `database/seed.php:442-449`, which sets `status='completed'`
> and an `actual_end` wherever progress reaches 100. They were created on 11 November and
> have nothing to do with any test run.

**Actual cause — two defects, both in the suite.**

**1. The suite chose its project at random.** It picked a floor with:

```sql
ORDER BY f.created_at LIMIT 1
```

The demo data seeds every floor in one batch, so **34 floors share the single timestamp
`2026-11-11 07:35:05`**, spanning two projects — *Horizon Business Park* and *Horizon
Heights*. With the sort column tied across all 34 rows and no tiebreaker, MySQL returns an
arbitrary winner. Only *Horizon Heights* has completed milestones, so whether the suite
passed depended on which row the engine happened to hand back.

**2. The assertion described behaviour the code never produces.** It expected
`due_date = COALESCE(m.actual_end, m.planned_end, i.due_date)`. The code has two distinct
rules, and that expression is neither of them:

| When | Due date | Where |
|---|---|---|
| Schedule generated | `m.planned_end`, always | `PaymentPlanService.php:134` |
| Milestone later completes | `m.actual_end + 15 days` (the payment window) | `ConstructionService.php:243` |

So on any project with a completed milestone the assertion failed, because it wanted
`actual_end` where a freshly generated schedule correctly holds `planned_end`. Verified
against the live rows: due dates `2025-06-11` and `2025-09-11` exactly equal their
milestones' `planned_end`, while `actual_end` is `2026-03-21` and `2026-05-16`.

**Fix applied** — both in `tests/e2e-phase2.sh`, test-side only, no change to payment
behaviour:

1. `ORDER BY f.created_at, f.id` — the tiebreaker makes the suite test the same project
   every run. It now deterministically selects *Horizon Heights*, the harder case.
2. The assertion now checks `i.due_date = m.planned_end`, which is what generation
   guarantees. The re-dating path is already covered by phase 4, which records and restores
   `INST_DUE` around it.

**Why it mattered more than its severity suggested:** the plan in
`INTEGRATION_IMPLEMENTATION_PLAN.md` runs the gate after every item. A suite that silently
tests a different project each run cannot tell a real regression from a coin toss.

## 7. Summary

| # | Finding | Severity |
|---|---|---|
| INT-001 | Land payments never reach the ledger — 336,000,000 off-book | **Critical** — new payments **fixed**; backfill outstanding |
| INT-002 | Quotation→booking is a prefill; no state transition or status gate | High |
| INT-003 | Cross-tenant leak via unvalidated reference ids on booking POST | High |
| INT-004 | Construction→procurement link is schema-only and dead | High |
| INT-005 | Project cost rollup does not exist | High |
| INT-006 | Cancelled booking never voids its commission | High |
| INT-007 | Commission never raised automatically | Medium |
| INT-008 | Document authorization covers only `entity_type='project'` | Medium |
| INT-009 | 118 unenforced reference columns; 53 orphaned ledger rows | Medium |
| INT-010 | `approval_requests` not reusable; approval re-implemented 8× | Medium |
| INT-011 | Notifications ad hoc; no shared service | Medium |
| INT-012 | `isConfigured()` silently skips all 24 posting sites | Medium |
| INT-013 | `user_project_access` has no FKs and no tenant column | Medium |
| INT-014 | Audit coverage missing in 16 files | Low |
| INT-015 | `credit_notes` is a dead table | Low |
| INT-016 | Only one dashboard, not five | Low |
| INT-017 | **The phase 2 suite picked its project at random**, and its milestone date assertion described behaviour the code never produces | Was a blocker — **fixed** |

**What this audit did not find:** a broken financial calculation, a concurrency hole in
booking, a stored balance that can drift, or a hardcoded dashboard figure. Where EstateOS
is integrated, it is integrated properly. The defects are *absences* — links that were
designed, given their columns, and never wired.
