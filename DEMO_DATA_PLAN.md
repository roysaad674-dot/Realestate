# EstateOS — Demo Data Plan

**Produced:** 14 November 2026 · **Target:** local development only
(`http://localhost:5000`, MariaDB `estateos`).

---

## 1. Isolation

| | |
|---|---|
| Organisation | `ESTATEOS QA DEMO ORGANIZATION`, code **`QA-DEMO`** |
| Record prefix | **`QA-DEMO-`** on every code, name and reference that accepts free text |
| Email domain | `@qa-demo.test` — a reserved TLD that cannot route anywhere |
| Phone range | `9100000xxx` — synthetic, not allocated |
| Existing data | Untouched. The demo org (`Horizon Developers`) and the security fixture org (`SEC-B`) are left exactly as they are |

`QA-DEMO-` deliberately does not collide with the prefixes the existing suites already use
(`E2E1-`, `E2E2-`, `SEC-`), so this data can coexist with a gate run.

**Why a separate organisation at all:** every table that matters carries
`organization_id`, and 96 of 117 tables are scoped by it. A new organisation is therefore
the cleanest possible blast radius — nothing created here can appear in the existing demo
org's lists, reports or dashboards, and that isolation is itself one of the things worth
testing.

## 2. What the forms actually require

Established by reading the validators, not guessed:

| Entity | Required fields | Source |
|---|---|---|
| Organisation | `name`, `code`, `currency` (INR/USD/EUR/GBP/AED/SAR), `area_unit` (sqft/sqm/sqyd) | `OrganizationController::rules()` |
| User | `first_name`, `email`, `role_id`, `password` (min 8) | `UserController::store()` |

Roles are **global** (`roles.organization_id IS NULL`) and attach through the
`organization_users` pivot, so the same person can hold different roles in different
organisations. Fourteen exist:

```
company-owner   director        project-manager   sales-manager
sales-executive accountant      finance-manager   purchase-manager
warehouse-manager  hr-manager   legal-manager     site-engineer
site-supervisor broker
```

That covers every role the brief names. "Super Admin" is not a role — it is the
`users.is_super_admin` flag.

## 3. The records

Ordered by dependency. Each step needs the one before it.

### A. Organisation and people

| Record | Detail |
|---|---|
| 1 organisation | `QA-DEMO`, INR, sqft |
| 10 users | owner, director, project manager, sales manager, 2 sales executives, accountant, purchase manager, warehouse manager, HR manager |
| 1 broker user | `broker` role |
| 1 portal customer | created later, once a booking exists to attach it to |

Every account gets a synthetic address at `@qa-demo.test`. **No real email is used, and no
password is reused from anywhere.**

### B. Land → projects

- 2 land parcels with owners, area, acquisition price, legal status
- Acquisition payment schedules — at least one part-paid, to exercise the ledger posting
  wired in Stage 1.1 of `INTEGRATION_IMPLEMENTATION_PLAN.md`
- 2 projects, each drawing on one parcel, with budgets and milestones

### C. Inventory

- 2 buildings per project, several floors each, **≥5 units per building**
- Mixed types, areas and prices

**Statuses are not set directly.** The brief is explicit: *"Ensure that the inventory
statuses agree with actual booking and sales records."* So units reach `booked`, `sold` and
`on hold` only by being booked, sold or held through the application. Only `blocked` is set
deliberately, because that is what the control is for.

### D. CRM → sales

- 15 leads spread across sources, stages and owners — including ones that stay open, go
  cold, and convert
- 10 customers, several converted from leads so the `lead_id` back-reference is exercised
- Quotations at several discount levels, deliberately straddling the
  `max_discount_pc_without_approval` threshold (3%) so both the auto-approve and
  needs-approval paths run
- Bookings in several valid states, each driving its unit's status

### E. Money

- Payment plans of each type, including `construction_linked` so milestone dating is exercised
- Part payments, full payments, one overdue, one reversal
- Receipts, allocations, ledger entries

### F. Operations

- 5 suppliers, materials, 2 warehouses
- Requisition → PO → GRN → stock movement, so stock balance is *derived*, never written
- Construction tasks with progress, feeding milestone completion
- 3 brokers with agreements and commission rules
- Employees, attendance, leave, one payroll run
- Documents attached across entity types — all harmless placeholder files

## 4. Rules while creating

1. **Through the application wherever practical.** Data created by direct `INSERT` proves
   only that the table accepts it; data created through the form proves the form, the
   validator, the service and the audit trail.
2. **Fixtures only where the UI cannot reasonably reach** — bulk unit generation, for
   instance, already has a generator endpoint and will use it.
3. **Every write is verified** against the database afterwards, not assumed from a 302.
4. **Nothing outside `QA-DEMO` is modified.** No existing record is edited or deleted.
5. **No external send.** Mail goes to `storage/logs/mail.log`; `whatsapp_messages` rows are
   queued and nothing dispatches them. Verified before starting.
6. **Record as we go** — `DEMO_DATA_REGISTER.md` is written during creation, not
   reconstructed afterwards.

## 5. Cleanup

The demo organisation is **kept**, because the brief asks for a populated demo environment.

If removal is ever wanted it must be asked for explicitly: deleting the organisation
cascades through 67 inbound foreign keys and cannot be undone. No broad `DELETE` will be
run, and nothing outside `QA-DEMO` will be touched.

A caution worth recording: **118 reference columns carry no foreign key**
(`DATABASE_RELATIONSHIP_MAP.md`), so a cascade would *not* reach everything. Deleting this
organisation later would orphan rows in `customer_ledger`, `quotations`, `leads` and others
— exactly as it already has for the existing demo data, where 53 orphaned ledger rows were
found. Cleanup, if requested, must be explicit per table rather than trusting the cascade.

## 6. What this data is for

Not decoration. Each record exists to let a later stage assert something:

| Data | Proves |
|---|---|
| Units reaching status through bookings | Inventory identity — every unit counted once |
| Part payments and a reversal | `Outstanding = Payable − Allocated`, and that reversal preserves history |
| Quotes either side of 3% | The discount approval gate actually gates |
| GRN → stock movement | Stock balance is derived from the ledger, never stored |
| Milestone completion | Construction → finance link re-dates installments |
| A second organisation | Tenant isolation, from the other side of the fence |
| Land payments | The ledger posting added in Stage 1.1 |
