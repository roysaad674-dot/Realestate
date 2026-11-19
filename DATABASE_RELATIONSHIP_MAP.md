# EstateOS — Database Relationship Map

**Produced:** 14 November 2026 · **Source:** the live `estateos` schema via
`information_schema`, not the migration files. Where the two disagree, the live schema wins.

---

## 1. Totals

| | |
|---|---|
| Tables | **117** (all InnoDB — so foreign keys are possible everywhere) |
| Declared foreign keys | **204** |
| `*_id` columns with **no** FK behind them | **118** |
| Tables carrying `organization_id` | **96** of 117 |
| Tables with no outgoing FK at all | **20** |

Delete rules on the 204 declared keys:

| Rule | Count | Comment |
|---|---|---|
| `CASCADE` | 150 | Correct for tenant teardown; dangerous on financial children |
| `SET NULL` | 48 | Used for optional references such as `employees.user_id` |
| `RESTRICT` | **6** | Only six relationships in the system refuse a destructive delete |

## 2. The hubs

Most-referenced tables, by inbound foreign key count:

| Table | Inbound FKs | Role |
|---|---|---|
| `organizations` | **67** | Tenant root |
| `projects` | 21 | Operational root |
| `vendors` | 10 | Procurement master |
| `units` | 9 | Inventory master |
| `bookings` | 9 | Sales transaction root |
| `employees` | 9 | HR master |
| `users` | 5 | Identity master |
| `customers` | 5 | Customer master |

`organizations` at 67 confirms tenancy is modelled at the schema level, not bolted on.

## 3. Tenancy

`users` deliberately carries **no** `organization_id`. Membership is the
`organization_users` pivot (`user_id`, `organization_id`, `role_id`, `is_default`,
`status`), which correctly allows one person in several organizations. This is right, and
worth stating because it looks like an omission at first glance.

The 21 tables without `organization_id` are child rows that inherit tenancy through a
parent — legitimate in every case **except one**:

> **`user_project_access`** has no `organization_id`, and **no FK** on either `user_id` or
> `project_id`. It is a privilege-granting table that is neither tenant-scoped nor
> referentially constrained. It currently holds no orphans, but nothing prevents them.

## 4. The 118 unenforced reference columns

These columns are named like foreign keys, are used like foreign keys by application code,
and have no constraint behind them. Concentrations:

| Table | Unenforced columns |
|---|---|
| `bookings` | `quotation_id`, `lead_id`, `broker_id`, `sales_executive_id`, `building_id`, `approval_request_id` |
| `stock_movements` | `journal_entry_id`, `project_id`, `reference_id`, `counterpart_id`, `to_warehouse_id` |
| `expenses` | `journal_entry_id`, `category_id`, `bank_account_id`, `building_id`, `approval_request_id` |
| `quotations` | `customer_id`, `lead_id`, `project_id`, `unit_id` — **all four; the table has zero FKs** |
| `leads` | `customer_id`, `project_id`, `unit_id`, `broker_id` |
| `customer_payments` | `customer_id`, `journal_entry_id`, `project_id`, `bank_account_id` |
| `customer_ledger` | `customer_id`, `booking_id`, `ref_id` — **all three; zero FKs** |
| `refunds`, `vendor_bills`, `credit_notes`, `tax_records`, `site_visits` | 3–4 each |

### Tables with no outgoing FK whatsoever (20)

```
audit_logs          customers            customer_ledger      document_files
land_feasibility_studies   leads         lead_sources         login_attempts
notifications       organizations        permissions          portal_login_attempts
quotations          roles                schema_migrations    site_visits
tax_records         unit_types           users                user_project_access
```

Several belong here legitimately — `organizations` is the root, `schema_migrations`,
`login_attempts` and `audit_logs` are append-only logs by design, `permissions` is a global
catalogue. The rest are genuine gaps, and three of them matter most:

- **`quotations`** — the document that turns an enquiry into a sale, tied to nothing.
- **`customer_ledger`** — the customer's financial history, tied to nothing.
- **`site_visits`** — fully disconnected.

## 5. Damage already done

Missing constraints are not hypothetical here. Two queries, run today:

```sql
SELECT COUNT(*) FROM customer_ledger l
  LEFT JOIN customers c ON c.id = l.customer_id
 WHERE l.customer_id IS NOT NULL AND c.id IS NULL;        -- 26

SELECT COUNT(*) FROM customer_ledger l
  LEFT JOIN bookings b ON b.id = l.booking_id
 WHERE l.booking_id IS NOT NULL AND b.id IS NULL;         -- 27
```

**53 orphaned rows in a 98-row financial ledger** — about 27%. Sample rows are
`ref_type='customer_payment'` credits of 500,000.00 each, created 13 November 2026, whose
customer no longer exists. These are not soft-deletes: the join finds no row at all.

`ON DELETE RESTRICT` on `customer_ledger.customer_id` would have refused the delete that
created them.

## 6. Where the links are populated — and where they are not

A constraint-free column is only a problem if code relies on it. These are measured from
live data:

| Link | Rows | Null | Verdict |
|---|---|---|---|
| `bookings.quotation_id` | 16 | **16** | Written by `BookingService.php:129` when supplied — null here because the **seed** creates bookings without a quote |
| `bookings.lead_id` | 16 | **16** | Written by `BookingService.php:126`; asserted green at `e2e-phase2.sh:322`. Seed artefact, not a code gap |
| `customers.lead_id` | 19 | **19** | Written by `LeadController.php:708` on conversion; seed customers are created directly |
| `quotations.customer_id` | 2 | **2** | Seed quotations only; the runtime path populates it |
| `customer_payments.journal_entry_id` | 67 | **65** | 97% of payments carry no ledger reference |
| `stock_movements.journal_entry_id` | 12 | 7 | 58% carry no ledger reference |
| `expenses.journal_entry_id` | 2 | **0** | Fully populated — this path works |

Two different causes sit behind this table, and they must not be conflated:

1. **Real code gaps.** `bookings.quotation_id` is null in all 16 rows because
   `BookingService::create()` never writes it (see `MODULE_INTEGRATION_MAP.md` INT-002).
2. **Seed infidelity.** The 65 payments without a journal entry were written in bulk by
   `database/seed.php:769`, which bypasses `CollectionService::receive()`. The runtime path
   does post correctly. The organization has 72 chart-of-accounts rows, so this is not an
   `isConfigured()` skip.

`expenses` at 0 nulls is the control case: it proves the posting machinery works when it is
actually called.

## 7. Integrity guarantees that *are* enforced

It would be wrong to read this document as "the schema is weak". Several of the hardest
guarantees are enforced at the database, not merely in code:

| Guarantee | Mechanism |
|---|---|
| One active booking per unit | `UNIQUE uq_booking_active_unit (unit_id, active_flag)` |
| One payment plan per booking | `UNIQUE uq_plan_booking (booking_id)` |
| One commission per booking | `UNIQUE uq_commission_booking (booking_id)` |
| No duplicate payment on retry | `UNIQUE uq_payment_idem (organization_id, idempotency_key)` |
| No duplicate journal on retry | `UNIQUE uq_je_idem` |
| Employee ↔ user is 1:1 and optional | `UNIQUE uq_employee_user` + `ON DELETE SET NULL` |
| Unique customer phone per tenant | `UNIQUE uq_cust_phone (organization_id, phone)` |
| No stored stock balance to drift | Balance is `SUM(direction * quantity)` over `stock_movements` |
| No stored outstanding balance to drift | Recomputed per call in `CollectionService::outstanding()` |

The idempotency keys in particular are real database constraints, not advisory service-layer
checks — retries cannot double-charge.

## 8. Recommended constraint work

In dependency order. Each step is additive and independently shippable.

**Step 1 — repair before constraining.** The 53 orphaned `customer_ledger` rows must be
resolved first; any `ALTER TABLE ... ADD CONSTRAINT` will fail while they exist. Decide per
row whether to delete or re-point, and record the decision.

**Step 2 — the financial spine, `RESTRICT`.** Financial history should refuse to lose its
parent:

```
customer_ledger.customer_id   → customers(id)        ON DELETE RESTRICT
customer_ledger.booking_id    → bookings(id)         ON DELETE RESTRICT
customer_payments.customer_id → customers(id)        ON DELETE RESTRICT
customer_payments.journal_entry_id → journal_entries(id) ON DELETE RESTRICT
```

**Step 3 — the sales chain, `SET NULL` for optional links.**

```
quotations.customer_id  → customers(id)   ON DELETE SET NULL
quotations.lead_id      → leads(id)       ON DELETE SET NULL
quotations.project_id   → projects(id)    ON DELETE CASCADE
quotations.unit_id      → units(id)       ON DELETE RESTRICT
bookings.quotation_id   → quotations(id)  ON DELETE SET NULL
bookings.lead_id        → leads(id)       ON DELETE SET NULL
bookings.broker_id      → brokers(id)     ON DELETE SET NULL
customers.lead_id       → leads(id)       ON DELETE SET NULL
leads.customer_id       → customers(id)   ON DELETE SET NULL
```

**Step 4 — privileges and tenancy.** Add `organization_id` to `user_project_access`, plus
FKs on `user_id` and `project_id`, both `ON DELETE CASCADE`.

**Step 5 — the remainder.** `site_visits`, `tax_records`, `document_files`,
`stock_movements.journal_entry_id`, `expenses.journal_entry_id`.

Two cautions:

- `customers.lead_id` and `leads.customer_id` form a cycle. Both must be nullable and
  `SET NULL`, and the conversion in `LeadController.php:679-735` writes them in one
  transaction — which is already correct.
- `CASCADE` currently dominates at 150 of 204. Adding financial keys as `CASCADE` would let
  one delete take the ledger with it. The four keys in Step 2 are the argument for
  `RESTRICT`.
