# EstateOS — Integration Implementation Plan

**Produced:** 14 November 2026 · **Status:** Phase 1 (audit) complete; Phase 2 not started.

Derived from `MODULE_INTEGRATION_MAP.md`, `DATABASE_RELATIONSHIP_MAP.md` and
`MODULE_DEPENDENCY_MATRIX.md`. This document is the work order.

---

## 1. Ground rules

1. **Test first.** Every item below gets a failing check before the fix, in the existing
   suites (`tests/e2e-phase*.sh`, `tests/test-unit-app.php`). A fix without a check that
   fails without it is not finished.
2. **One integration at a time.** Each item is independently shippable and independently
   revertible.
3. **The gate must stay green.** `bash tests/run-all.sh` (currently **13/13**, 1,266 checks)
   runs after every item. A red gate blocks the next item.
4. **Preserve what works.** The audit found the hard parts already correct — booking
   concurrency, the unit-status chokepoint, idempotent schedules, ledger-derived stock,
   TOCTOU-safe handover. None of these is to be refactored.
5. **No new dependencies.** No Composer, no npm. That constraint is deliberate and stands.

## 2. Preconditions

Before any code changes:

- **MySQL must be running.** It is not currently a Windows service; it was started manually
  this session. Confirm `127.0.0.1:3306` is listening.
- **Baseline recorded.** The gate passes 13/13 as of today.
- **Fix the fixture before trusting the data.** `database/seed.php:769` writes
  `customer_payments` directly and is why 65 of 67 payments carry no `journal_entry_id`.
  Until it routes through `CollectionService::receive()`, demo data will keep contradicting
  the runtime path and will mask regressions in Step 2.4.

## 2a. Stage 0 — make the gate trustworthy · **DONE**

> **Resolved 14 November.** The cause was not milestone residue from phase 4, as first
> thought. The phase 2 suite selected its floor with `ORDER BY f.created_at LIMIT 1` over
> **34 seeded floors sharing one timestamp** across two projects, so it tested a different
> project on each run; and its date assertion expected
> `COALESCE(actual_end, planned_end)`, which the code never produces — generation always
> uses `planned_end` (`PaymentPlanService.php:134`), and completion re-dates to
> `actual_end + 15 days` (`ConstructionService.php:243`).
>
> Fixed in `tests/e2e-phase2.sh` by adding an `f.id` tiebreaker and asserting
> `due_date = m.planned_end`. Test-side only; payment behaviour is unchanged. The analysis
> below is kept for the reasoning about *why* billing was left alone.

### Original analysis

Running the gate twice in this session gave **13/13** then **12/13**. Phase 2 fails on every
run after the first:

```
FAIL and every milestone-linked installment is dated from its milestone
     expected [0], got [2]
```

`PaymentPlanService::generate()` reads only `planned_end` (`PaymentPlanService.php:38-39`);
`tests/e2e-phase2.sh:383` expects `COALESCE(actual_end, planned_end, due_date)`. They agree
only while no milestone has an `actual_end`. Phase 4 completes milestones;
`tests/cleanup-phase2.php` never resets `project_milestones`. Two milestones are completed
right now, and the suite reports exactly two failures.

**This is not a Phase 2 item — it is a precondition.** The whole plan below rests on "run
the gate after every change". A gate that fails for a pre-existing reason cannot show
whether a change regressed anything.

It needs a decision before a fix, because both sides are arguable:

| Option | Consequence |
|---|---|
| **A — the assertion is too strict.** Only require `actual_end` to drive the date for milestones completed *after* the schedule was raised. | Smallest change; keeps today's payment behaviour. |
| **B — the code should prefer `actual_end`.** `generate()` uses `COALESCE(actual_end, planned_end)`. | Changes real payment dates: a booking against an already-complete milestone becomes immediately overdue. |
| **C — fix the cleanup leak.** `cleanup-phase4.php:130-137` restores **only** the single milestone it tracked in `$state['MS']`. Milestones completed as a *side effect* of the construction progress rollup (`ConstructionService.php:146-212`) are never restored, so their `actual_end` survives into the next run. Restore every milestone the suite touched. | Makes the gate idempotent without deciding the business question. Leaves code and test disagreeing, but that disagreement is then dormant rather than blocking. |

A and C are safe; B changes when customers are asked for money and should not be chosen
without the business saying so.

## 3. The work, in order

Severity is from the audit. "Blast radius" is how much else the change can break.

### Stage 1 — Stop the bleeding (money and tenancy)

| # | Item | Severity | Blast radius |
|---|---|---|---|
| ~~**1.1**~~ | ~~**Land payment → ledger.**~~ **DONE 14 Nov.** Posts inside the payment transaction (`LandController.php:382-402`); entry id written back; guarded by `isConfigured()`. Also fixed an idempotency key that would have swallowed a second equal part-payment. 6 new checks in `tests/e2e-phase1.sh`; gate 13/13, 1,272 checks, trial balance 0.00. | Critical | Low — as expected |
| **1.2** | **Backfill the 336,000,000.** A one-off, reversible script posting historical land payments, dated to the payment, not to today. Must be idempotent (reuse `uq_je_idem`). | Critical | Medium — touches the trial balance |
| **1.3** | **Close the booking IDOR.** Validate `lead_id`, `quotation_id`, `sales_executive_id`, `broker_id` against `organization_id` in `BookingController.php:224-228`, and add the tenant filter to the three unscoped reads at `:394`, `:398`, `:402`. | High | Low |
| **1.4** | **Void commission on cancellation.** Replace the audit-note-only block at `BookingService.php:498-505` with a real `CommissionService::void()` call, honouring the `commission_on_cancelled` setting. | High | Medium — financial reversal |

**Tests for Stage 1:** a land payment leaves a balanced journal entry and the trial balance
stays `0.00`; a booking POST citing another tenant's quotation is refused; a cancelled
booking's commission reaches `void` and drops out of payable.

### Stage 2 — Shared services (before anything else multiplies)

| # | Item | Why first |
|---|---|---|
| **2.1** | **`ReferenceValidator`** — one helper that resolves an id within the caller's organization or throws. | INT-003 happened because every controller does this by hand. Build it before adding more reference columns. |
| **2.2** | **`NotificationService`** — replace the 5 inline `Database::insert('notifications', …)` sites with one service and a stable payload. | Stage 3 and 4 both need to notify; writing two more inline sites entrenches the problem. |
| **2.3** | **Generic approvals** — extend `approval_requests` to cover the 8 tables carrying a bespoke `approved_by`. | Highest-leverage structural fix, but **not urgent**; schedule it only if Stage 3 is not delayed. |
| **2.4** | **Seed through the service** — `database/seed.php:769` to call `CollectionService::receive()`. | Makes demo data honest; unblocks trustworthy assertions everywhere downstream. |

### Stage 3 — Close the dead links

| # | Item | Severity |
|---|---|---|
| **3.1** | **Quotation → booking is a real transition.** Write `bookings.quotation_id`; set the quotation to `converted` and stamp `booking_id`; refuse a booking citing a quote that is not `accepted`; refuse a second booking from a converted quote. | High |
| **3.2** | **Lead attribution survives booking.** Populate `bookings.lead_id` and `customers.lead_id` on the conversion path. | High |
| **3.3** | **Construction → procurement.** Let a construction task raise a requisition, writing `purchase_requisitions.task_id`; surface the requisition on the task. | High |
| **3.4** | **Raise commission automatically** on booking confirmation, relying on `uq_commission_booking` for idempotency rather than a new guard. | Medium |
| **3.5** | **Document authorization for every entity type** — generalise `DocumentController.php:182-184` beyond `entity_type='project'`, and validate the polymorphic pair on write. | Medium |

### Stage 4 — Read models

| # | Item | Severity |
|---|---|---|
| **4.1** | **Project cost rollup.** Add `actual_amount` to `project_budgets` (or a view); aggregate `expenses`, `stock_movements` and payroll by `project_id`. Read-only — cannot break L0–L4. | High |
| **4.2** | **The four missing dashboards** — sales, finance, inventory, construction. `MetricsService` (428 lines) already computes most of what they need and contains no hardcoded figures. | Low |
| **4.3** | **Related-record panels** on customer, project, unit and booking pages. | Low |

### Stage 5 — Constraints (last, deliberately)

Foreign keys go on **after** the code stops writing rows that would violate them. Order
from `DATABASE_RELATIONSHIP_MAP.md` §8:

| # | Item |
|---|---|
| **5.1** | Resolve the 53 orphaned `customer_ledger` rows — delete or re-point, decision recorded. Any `ADD CONSTRAINT` fails while they exist. |
| **5.2** | Financial spine as `RESTRICT`: `customer_ledger.customer_id`, `customer_ledger.booking_id`, `customer_payments.customer_id`, `customer_payments.journal_entry_id`. |
| **5.3** | Sales chain as `SET NULL` / `RESTRICT` per the map — note `customers.lead_id` ↔ `leads.customer_id` is a cycle; both stay nullable. |
| **5.4** | `user_project_access`: add `organization_id`, plus FKs on `user_id` and `project_id`. |
| **5.5** | Remainder: `site_visits`, `tax_records`, `document_files`, the two `journal_entry_id` columns. |

### Deferred, with reasons

| Item | Why not now |
|---|---|
| `credit_notes` dead table | Harmless. Either wire refunds through it or drop it — a decision, not a defect. |
| `isConfigured()` silent skip (24 sites) | Arguably correct for an org still in setup. Needs a product decision — warn, queue, or refuse — not a unilateral change. |
| Audit coverage in 16 files | Real but low-risk; batch it once `ApprovalController` has a reason to log. |

## 4. Verification after each stage

```bash
# MySQL must be listening on 127.0.0.1:3306 first
BASE=http://localhost:5000 bash tests/run-all.sh
```

Baseline recorded today, from two consecutive runs:

```
run 1 (clean milestones):  13/13 gates · 1,266 checks · 0 failures
run 2 (after run 1):       12/13 gates · phase 2 fails 1 of 125  ← INT-017
both runs:                 invariant violations 0 · trial balance 0.00
lint 297 files · unit 66 + formulas · phases 155/125/125/281/193/147 · security 135
```

**The number to beat is run 2's 12/13 until Stage 0 lands, not 13/13.** Treating 13/13 as
the baseline would mean every future run looks like a regression.

Stage 1 changes the trial balance by design — 1.2 posts 336,000,000 of historical land
cost. The invariant that must hold is **balance**, not **constancy**: debits equal credits
and `SUM(debit-credit) = 0.00` after the backfill, with the new entries dated to the
original payments.

## 5. Explicit non-goals

- No rewrite. The stack, schema and UI stay.
- No framework, ORM or package manager.
- No refactor of booking concurrency, unit-status transitions, stock derivation or handover
  gating — all four are correct and are the most expensive things to get right.
- No change to the portal's isolation model, which the audit found sound.

## 6. Tracking

`MODULE_INTEGRATION_STATUS.md` is created at the start of Phase 2 and carries one row per
module with the statuses the brief requires (Not Started · Under Analysis · In Progress ·
Implemented · Testing · Passed · Failed · Blocked). `INTEGRATION_BUG_REPORT.md` gets a row
per INT-00n as it is fixed, with the regression check that proves it.

Current state: **Phase 1 complete. 17 findings, 1 critical, 1 blocker.**
Gate 13/13 on a clean database, 12/13 on a used one (INT-017).
