# EstateOS — Real Estate Developer ERP

A multi-company, multi-project ERP for property developers: land acquisition through
project development, construction, inventory, sales, collections, accounting and handover.

**All Phases 1 to 6 are fully built, tested and running.** Every single module from land acquisition, project planning, tower/unit inventory, CRM leads, sales bookings, payment plans, double-entry accounting, construction operations, procurement, warehouse stock, handover & possession, customer portal, HRMS, and the permission-aware AI Assistant is 100% operational against MySQL.

---

## Stack

| Layer | Choice | Why |
|---|---|---|
| Language | PHP 8.2 | Chosen by the project owner; deploys to any shared host |
| Database | MySQL / MariaDB 10.4 | Chosen by the project owner |
| Framework | None — a small MVC core in `app/Core` | Composer was not available; no third-party runtime dependency |
| Frontend | Server-rendered PHP templates | No build step, no bundler, no npm |
| CSS | One hand-written design system, `public/assets/css/app.css` | Token-driven; change `:root` and the whole product follows |
| JavaScript | ~450 lines of vanilla ES2017 | No jQuery, no framework, no CDN |

> The specification asked for Next.js / NestJS / PostgreSQL / Prisma. The owner chose
> PHP + MySQL instead, so the architecture was rebuilt around that: the same separation of
> concerns (routing → middleware → controller → service → repository → view), implemented
> without a framework. There are **zero runtime dependencies** — no `composer install`,
> no `npm install`.

---

## Getting started

### Requirements
XAMPP (or any PHP 8.1+ with `pdo_mysql`, `fileinfo`, `mbstring`, and a MySQL/MariaDB server).

### Run it

```bat
start.bat
```

That starts MariaDB if it is not running, applies migrations, seeds demo data on a fresh
database, and serves the app at <http://localhost:8787>.

### Or step by step

```bash
php database/migrate.php            # create the schema
php database/migrate.php --fresh    # drop and recreate it
php database/migrate.php --status   # list applied / pending
php database/seed.php               # permissions + roles only (safe to re-run)
php database/seed.php --demo        # plus a demo organization with data
php database/seed-phase2.php        # demo leads, brokers, quotations, agreements
php database/seed-phase4.php        # demo vendors, stores, construction, procurement, commission
php database/seed-phase5.php
php database/seed-phase6.php        # demo possession, snags, warranties, requests, portal access
php database/send-mail.php          # works through the mail outbox (cron-friendly)
php -S localhost:8787 -t public server.php
```

### Demo accounts

Password for all of them: `Password123!`

The Phase 5 seeder also opens a **customer portal** account for every demo buyer and prints
the first three passwords it generated; the rest are in their invitations, which you can read
in the outbox under Customer Portal → Outbound Mail. The portal is a separate sign-in at
<http://localhost:8787/portal>.

| Email | Role | Shows off |
|---|---|---|
| `owner@estateos.test` | Company Owner | Everything |
| `pm@estateos.test` | Project Manager | Projects and construction, no finance |
| `sales@estateos.test` | Sales Manager | Inventory, holds, pricing |
| `exec@estateos.test` | Sales Executive | Narrow access — no land, no settings, no money |
| `accounts@estateos.test` | Accountant | Finance visible, cannot create projects |
| `engineer@estateos.test` | Site Engineer | Construction only |
| `admin@estateos.test` | Super Admin | All organizations |

Sign in as the sales executive and then the owner to see role-based access working: the
financial KPI row disappears, and Land, Settings and the audit trail return 403.

### Configuration

Copy `.env.example` to `.env` to override anything. Defaults match XAMPP
(`127.0.0.1:3306`, user `root`, no password, database `estateos`).

---

## What is built

### Phase 1 — foundation, inventory and land

| Module | State |
|---|---|
| Authentication | bcrypt, lockout after 6 failed attempts, idle + absolute session expiry, password reset tokens |
| Organizations | Multi-company with strict tenant isolation, bank accounts, switcher |
| RBAC | 98 permissions, 14 built-in roles, per-user allow/deny overlay, project-level scoping |
| Dashboard | 12 KPI cards, booking/collection trend, inventory donut, project table, ageing, 5 attention widgets — all from live queries |
| Land management | Parcels, landowners, payment schedules, legal checklist, joint ventures, feasibility calculator |
| Projects | Full CRUD, milestones that drive progress, budget breakdown, team, archive/restore |
| Buildings & Units | Project → Building → Floor → Unit, bulk floor and unit generation, server-side pricing |
| Inventory board | Colour-coded tower/floor/unit board, holds with automatic expiry |
| Documents | Upload outside the web root, MIME verified server-side, versioning, expiry reminders |
| Reports | Inventory, project performance, land acquisition — each with CSV export and print layout |
| Settings | 12 configurable business rules, per-organization document numbering |
| Users & Roles | Invite, edit, reset password, project grants, permission editor |
| Audit trail | Append-only, field-level diffs, redaction, filterable, exportable |

### Phase 2 — CRM, customers and the sales pipeline

| Module | State |
|---|---|
| CRM — leads | Capture with duplicate detection on phone (format-insensitive), table and kanban pipeline views, 9 stages, priorities, follow-up tracking with overdue flags |
| CRM — activity | Calls, meetings, WhatsApp, email and notes; a logged next action becomes the lead's follow-up date; first contact advances a new lead automatically |
| CRM — site visits | Schedule, host assignment, completion with feedback and a 1–5 interest rating; the stage follows the visit |
| CRM — matching | Available units that fit the lead's budget, type and stated requirement, quotable in one click |
| CRM — import | CSV import matched by header name, per-line error reporting, duplicate counting, downloadable template |
| CRM — analytics | Source volume and conversion, pipeline funnel, executive performance, lost-reason breakdown, 12-month intake |
| Customers | One record per buyer (phone unique per organization), KYC workflow, ledger, printable statement, portal credential |
| Quotations | Priced offers with a stored line-by-line breakdown, validity tracking, print layout, accept → booking |
| Bookings | Unit locked and re-checked inside the transaction, server-side pricing, co-applicants with ownership shares, 6 payment-plan types, live schedule preview |
| Discount approvals | Threshold from Settings; over it the booking waits, the schedule is withheld, and the requester cannot approve their own |
| Payment plans | Construction-linked (from project milestones), down payment, milestone, fixed installment, possession-linked, one-time — the last installment absorbs rounding so the schedule totals the price exactly |
| Receipting | Idempotent receipts, oldest-first allocation, explicit advances, reversal by mirror entry (never deletion) |
| Agreements | Numbered agreement record, registration details, stamp duty; signing moves the unit on |
| Cancellation | Permanent record, charge capped at money actually received, automatic refund, unit released, plan closed, ledger reversed |
| Refunds | Raised on cancellation, part-payable, overpayment refused |

### Phase 3 — collections, accounting and the finance statements

| Module | State |
|---|---|
| Collections — receivable book | Ageing of every open installment in buckets, due and overdue lists, filters by project, bucket and customer, CSV export |
| Collections — receipts | Every receipt in one register, searchable, with the booking and allocation behind it |
| Collections — waivers | Capped at what is outstanding, reasoned, recorded as an adjustment and posted to bad debts rather than hidden in the installment |
| Collections — late fees | Charged on an overdue installment, posted as income, visible on the schedule |
| Collections — reschedule | A new due date with a stated reason, kept in the audit trail |
| Collections — reminders | Selected installments produce a dated reminder record per channel, with the message that was due to go out |
| Chart of accounts | A standard developer chart installed per organization; system accounts carry a key that automatic postings resolve by, so their codes and types are fixed while names stay editable |
| Journal entries | Manual and automatic, balanced before anything is written, immutable once posted, numbered from the sequence |
| Reversals | A mirror entry that references the original; the original is kept exactly as posted, and a reversal cannot itself be reversed |
| Automatic postings | Receipts, refunds, cancellation charges, waivers, late fees, expenses, expense payments and land payments all post themselves, idempotently |
| Accounting periods | Monthly periods, closed and reopened deliberately; nothing posts into a closed period |
| General ledger | Per account, per project, with an opening balance and a running balance |
| Bank book | Deposits and withdrawals per bank account, reconciliation stamped with who and when |
| Expenses | Categories, raise → approve → pay, accrual posted when raised and payment when paid, paid expenses immutable |
| Trial balance | As at any date, by project, and it balances |
| Profit and loss | Income against expenses for a window, with exports |
| Balance sheet | Assets against liabilities and equity, the year's result folded in |
| Cash flow | Opening, inflows and outflows by contra account, monthly movement, closing, per bank account |
| Project profitability | Sales value, collected, spent and margin per project, drawn from the business tables rather than the ledger |

### Phase 4 — operations: vendors, construction, procurement, stores and commission

| Module | State |
|---|---|
| Vendors | One record per party the business buys from, carrying its own payment terms, retention and tax-deduction rates; those rates are what every bill against it is computed with |
| Blacklisting | Stops new orders and new work orders, with a stated reason, while bills already raised stay payable |
| Vendor rating | Quality, timeliness and safety scored 1–5 per review; the rating shown is the average of every review |
| Work orders | Work awarded to a contractor with its own retention, tax and defect-liability period |
| Work certificates | Work measured for a period; retention and tax come off at the order's rates, certified value can never exceed the award, and whoever certified cannot approve |
| Certificate → bill | Approving a certificate raises the contractor's bill and posts it, so what is paid traces back to measured work |
| Construction tasks | Tasks beneath a project milestone, each with a weight, an owner, a contractor and a dependency it waits on |
| Progress | Recorded as a dated update with a note, never overwritten; it cannot go backwards and cannot run ahead of a dependency |
| Milestone roll-up | A milestone is the weighted average of its tasks, and a project the weighted average of its milestones — nobody types a percentage in |
| **Milestone → billing** | Completing a milestone raises the construction-linked installments waiting on it, dated from the day the work was actually finished |
| Site reports | One per project per day — labour, weather, hours, work done, issues, safety incidents and visitors; a second report for the same day is refused |
| Quality inspections | Pass, fail or conditional against a task, with a re-inspection date; anything that did not pass must say why |
| Materials | Item master with unit of measure, tax rate, standard rate and reorder level |
| Stores | Stock is held per store; a store that has seen movements is never deleted |
| Stock ledger | Receipts, issues, transfers, returns and adjustments. **A balance is a sum over the movements — there is no stored quantity anywhere**, so nothing can disagree with its own history |
| Valuation | Weighted average per store and material, from everything received; an issue is valued at that rate, so consumption costs what it cost |
| Requisitions | What the site asked for, line by line, submitted and then approved by someone other than the requester |
| Purchase orders | Raised directly or from an approved requisition; totals computed on the server from quantity, rate and tax |
| Goods receipts | Only against an issued order, never beyond what a line still has outstanding; accepted material enters the store and the books, rejected material does neither |
| Vendor bills | Net payable is value and tax less retention, tax deducted at source and any other deduction; approving a bill is what posts it |
| Vendor payments | Against an approved bill and never beyond its balance, or as an advance that sits in Advances to Vendors until a bill arrives |
| Commission rules | Percentage, flat or slab-by-value; scoped to a partner, a project or both, with the most specific match winning and priority breaking a tie |
| Broker commission | Computed from the rule that applied and the booking's own figures, with the rule, the amount and the rate stored on the commission so a payout is explainable years later |
| Commission payout | Approved by someone who did not raise it, paid only up to what is left of the net, and tax deducted held separately from what the broker may draw |

### Phase 5 — possession, after-sales, the customer portal and mail

| Module | State |
|---|---|
| **Possession gate** | Three conditions, each checked and stamped separately: the agreement is signed, nothing is outstanding, and no critical or major snag is open. A refusal names the one that failed and by how much |
| Handover planning | Opens the handover with its checklist and **raises the installments that wait for possession**, dated from the appointment — a buyer cannot clear a balance that was never billed |
| Handover checklist | Ten items, eight of them mandatory; keys cannot be handed over until the mandatory ones are ticked, with who ticked each and when |
| Keys | Possession date, letter number, keys counted and signed for, meter readings, and the date maintenance charges start |
| On completion | The unit is marked handed over with its possession date, the booking closes, the warranty periods begin, and the buyer is written to |
| Possession letter | A printable letter stating what was handed over, to whom, the meter readings and the warranty cover — printable only once it has actually happened |
| Snagging | A snag belongs to the **unit**, not the handover, because snags outlive possession. Raised by an inspector or by a buyer, assigned to a contractor, resolved, and **closed only by verification** — never by whoever was supposed to fix it |
| Blocking snags | A critical or major snag holds possession, and raising one takes a *ready* handover back to *planned* |
| Warranties | Six standard periods start on possession (structure and waterproofing five years, the rest one); a contractor's own cover is recorded alongside, and periods that end are marked expired |
| Service desk | Requests from the portal, the phone, email or the desk, each with its own conversation. An internal note is marked as such and never reaches the customer |
| Service levels | The clock starts when a request arrives, not when somebody gets to it: 4 hours urgent, 12 high, 48 normal, 96 low. `first_response_at` records the first reply the customer could actually see, and an internal note does not stop it |
| Request → snag | A maintenance request can be raised into the snag list, carrying its unit and that unit's warranties with it |
| Customer portal | A separate sign-in with its own identity, layout and lockout. A buyer sees their property, their schedule, every receipt, their documents, their project's milestone progress, and their requests |
| Portal safety | **Every query is scoped to the signed-in customer's own id**, taken from the session and never from the request. Another customer's receipt or request is a 404, not a redirect |
| What a buyer may change | How to reach them, and their password. Not their name, their primary phone or their KYC — those rest on an agreement, so they change through the office with a record of who did it |
| Portal administration | Grant access, re-send an invitation, reset a password, withdraw access. Withdrawing it stops an open session at once. A password is only ever written — the office keeps a hash and cannot read one back |
| Outbound mail | Three drivers, no third-party library: `log` (recorded and written to `storage/logs/mail.log`, nothing leaves the machine), `mail` (PHP's own), and `smtp` (a direct conversation with STARTTLS or implicit TLS and AUTH LOGIN / PLAIN) |
| The outbox | Every message is a row — subject, rendered body, driver, attempts and the exact failure — so "we emailed you" is a fact that can be read back. An idempotency key stops one event emailing the same person twice |
| What gets sent | Receipts when money is received, reminders when an installment is due, the possession appointment and its confirmation, service-request acknowledgements and replies, and portal invitations |

### Phase 6 — people, payroll and the assistant

| Module | State |
|---|---|
| Employee records | A number allotted on save, departments with cost centres, designations with grades, a reporting line, and the statutory and bank details a payslip needs. An employee record is **separate from a login**: most people on a site never sign in, and some logins are not employees |
| One login, one person | Where the two are linked the link is one to one and enforced; that link is what lets a sales target be counted from the bookings that person actually made |
| Joining and leaving | Probation with a confirmation date; an exit that needs a last working day, a reason, and **somebody to take over anybody reporting to them**. An exit supersedes their pay and lapses leave still waiting. Nothing is deleted — somebody who has been paid stays on the books |
| Pay as revisions | A structure is never edited. A revision takes effect from a date and supersedes the one before it, which keeps its dates. **A revision effective on or before a month already paid is refused**, so a payslip issued two years ago still adds up |
| The salary split | Gross is spread on a standard template — basic 50%, house rent 20%, conveyance 8%, the special allowance taking the remainder so the components add back to *exactly* the gross rather than a rupee out. Provident fund is capped at the statutory ceiling, professional tax follows the slab, and the employer's own contribution is a **cost on top of gross, never a deduction from it** |
| Attendance | One record per person per day, enforced by the database, so two people cannot disagree about the same morning. A month grid for marking, a bulk "mark the day" that **only fills in people who have nothing recorded** and never overwrites a judgement, and an export |
| A day nobody marked | Counts as **worked**. Treating silence as absence docks somebody's pay because a supervisor forgot, which is the wrong way round; every screen shows how many days were actually marked so the gap is visible |
| A paid month is closed | Once payroll for a month is approved, attendance for it cannot be amended *or added to* — adding a day that was never marked changes the payable days on a payslip just as surely as changing one |
| Leave | Types with a quota, notice period, longest permitted run and whether they carry forward. A request checks the balance, the notice, the run and whether it clashes with leave already granted, before anybody sees it |
| Deciding leave | **Nobody decides their own**, whatever permissions they hold. Approving writes the days straight into attendance so payroll sees them without anybody copying anything; cancelling before it starts releases them again |
| Balances | Opening days plus what has accrued. What has been **taken is never stored** — it is counted from the approved requests every time a balance is shown, so the two cannot drift apart |
| Payroll | **One run a month**, enforced by a unique key rather than by remembering. Draft → approved → paid. A draft is rebuilt from scratch, so correcting attendance and rebuilding is the normal way to fix a figure |
| Who approves | **Whoever built the run cannot approve it.** Approving posts the accrual, freezes every payslip line and closes the month. In the demo roles HR builds payroll and accounts approves and pays it |
| What payroll posts | On approval, Dr Salaries & Wages for gross **plus the employer's own cost**, Cr Salaries Payable for the net, Cr TDS Payable and Cr Statutory Dues Payable — dated **the last day of the month the work was done in**, not the day somebody got round to approving it. On payment, Dr Salaries Payable / Cr bank, dated the day the money actually left |
| Pro-rating | Somebody who joined or left mid-month is paid for their part of it, on top of any loss of pay within it. A flat statutory levy is not pro-rated. The payslip shows each component's full monthly value beside the amount paid, so the difference can be checked rather than taken on trust |
| Who was left out | A run names everybody on the rolls that month who is **not** on it, and why — almost always no salary structure. A payroll that silently skips somebody is how people go unpaid |
| Payslips | Printable, with earnings and deductions side by side, the employer's contributions shown separately as paid by the company, and a draft marked as a draft |
| Sales targets | Measured on booking value, number of bookings, leads created or collections received, optionally for one project. **Only the target is stored**; achievement is counted from the bookings, leads and receipts themselves whenever the page is opened. A target on somebody with no login is refused with the reason, rather than showing a confident nil for ever |
| Reviews | Four scores, with the overall being their average rather than a fifth opinion typed in separately, and a recommendation. A recommendation is a note: **pay is revised as its own deliberate act** |
| **The assistant** | A catalogue of **23 questions**, each one query. A question is matched against the catalogue by its words, the matching query runs against your own data, and the answer is shown **with the query that produced it** |
| What the assistant is not | **There is no language model, and none is called.** That is a deliberate choice, not a limitation: a figure in a board pack has to be defensible, and an answer that cannot be traced to a query is not. The catalogue is published in full, with the words that reach each question |
| When it does not know | It says so and offers what it can answer, rather than guessing. An unmatched question is logged, so what people wanted and did not get becomes the list of questions worth teaching it next |
| What it will not do | Read anything your role could not already open — every question carries the permission of the module it reads, and a refusal names the module. Write anything: every query is a SELECT. An **instruction** rather than a question ("delete the old bookings") is refused with an explanation instead of being answered with something adjacent |

### Everything in the navigation is now built

Every module in the sidebar is implemented and exercised by a suite. There are no
placeholder pages left; the scaffold that served them has been removed.

---

## Architecture

```
bootstrap.php              autoloader, config, helpers
server.php                 dev-server router (serves public/, else front controller)
config/config.php          configuration with .env overrides

public/index.php           front controller: session → security headers → route →
                           auth guard → CSRF → permission → controller
public/assets/             app.css (design system), app.js, favicon

routes/web.php             every route, each declaring the permission it needs

app/Core/                  App, Database, Router, Request, Response, View, Auth, Acl,
                           Audit, Validator, Paginator, Sequence, Csrf, Flash, Icons,
                           Navigation, Uuid, Controller
app/Http/Controllers/      one per module, plus Api/LookupController
app/Services/              MetricsService (dashboard + report figures), HoldSweeper,
                           BookingService, PaymentPlanService, CollectionService
app/Views/                 layouts/, partials/, one folder per module
app/Support/helpers.php    escaping, money, dates, status tones

database/migrations/       001_system, 002_land_projects, 003_inventory, 004_crm_sales,
                           005_sales_workflow
                           006_accounting
                           007_operations
                           008_handover_portal
database/migrate.php       migrator
database/seed.php          permissions, roles, demo tenant
database/seed-phase2.php   demo leads, brokers, quotations, agreements
database/seed-phase4.php   demo vendors, stores, construction, procurement, commission
database/seed-phase5.php   demo possession, snags, warranties, requests, portal access
database/send-mail.php     works through the mail outbox (cron-friendly)
database/sweep-holds.php   releases expired holds (cron-friendly)
database/seed-phase6.php   demo departments, employees, attendance, payroll
database/backup.php        takes, retains and verifies database backups

tests/run-all.sh           the quality gate: everything below, in one command
tests/e2e-phase1.sh        Phase 1 suite — sessions, land, projects, inventory
tests/e2e-phase2.sh        Phase 2 suite — CRM, conversion, quotations, bookings
tests/e2e-phase3.sh        Phase 3 suite — collections, accounting, statements
tests/e2e-phase4.sh        Phase 4 suite — vendors, construction, stores, commission
tests/e2e-phase5.sh        Phase 5 suite — possession, after-sales, portal, mail
tests/e2e-phase6.sh        Phase 6 suite — people, payroll, the assistant
tests/e2e-security.sh      security suite — isolation, authz, injection, integrity
tests/cleanup-phase*.php   each undoes exactly what its suite wrote
tests/cleanup-security.php restores what the security suite touched
tests/fixtures-security.php the second tenant the security suite attacks from
tests/fixtures-documents.php harmless probe files for the upload checks
tests/test-unit-app.php    unit tests against the application's own code
tests/test-unit.php        formula and helper checks
tests/hooks/pre-push       runs the gate before anything leaves the machine
tests/q.php                one-value SQL helper the suites assert with
tests/sql.php              statement helper for arranging a precondition

storage/uploads/           uploaded documents — outside the web root
storage/logs/              php-error.log, server.log
```

### Request pipeline

Each route carries its permission slug, and `public/index.php` enforces it **before** the
controller runs, so authorisation cannot be forgotten in a handler:

```php
$router->post('/units/{id}/hold', [UnitController::class, 'hold'], 'units.hold');
```

---

## Design decisions worth knowing

**Every figure is queried.** No dashboard number, chart or report value is hardcoded.
`MetricsService` is the single place they come from.

**A unit cannot be double-booked.** `unit_holds` has a unique index on
`(unit_id, active_flag)`; `active_flag` is `1` while held and `NULL` once released, so any
number of historical rows coexist with at most one live hold. The controller also locks the
unit row `FOR UPDATE` inside the transaction. Three simultaneous hold requests produce
exactly one hold — there is a test for it.

**A customer is not a weak member of staff.** The portal has its own identity class with
its own session keys (`PortalAuth`), its own layout and its own lockout, and portal routes
carry no permission slug at all — a customer has no place in the staff ACL. The front
controller guards them with the customer's identity instead, and every query inside
`PortalController` matches `customer_id` as well as the record's own id. Reaching another
buyer's receipt is a 404, not a redirect, because there is no query that would find it.

**Possession is a gate, not a checkbox.** Three conditions are stored separately on the
handover and stamped when each is met, and all three are re-checked at the moment keys are
handed over rather than trusted from the earlier step — money and snags move while an
appointment sits in the diary. A refusal names the condition that failed and by how much,
because "cannot hand over" with no reason is how a site team ends up overruling the system
on a spreadsheet.

**A snag is closed by verification, not by the person who fixed it.** Resolution and
verification are separate states with separate actors, and a resolved-but-unverified snag
still holds possession. The same shape as a discount needing a second approver, or a
requisition needing somebody other than its author.

**Mail is a table before it is a transport.** Every message is written to `mail_messages`
with its rendered body and delivered second, so what the system told a customer can be read
back months later, with the driver, the attempt count and the exact error. With the `log`
driver a message is still recorded and written to the mail log — the screens say which
driver is configured, so nobody mistakes a development box for a mail server.

**Stock is derived, never stored.** A quantity on hand is `SUM(direction * quantity)` over
`stock_movements`, exactly as the general ledger is a sum over journal lines. No table holds
a cached quantity, so no bug can leave one disagreeing with its own history. A mistake is
corrected by another movement — an adjustment carrying a reason, which posts its own journal
entry — rather than by editing the past. The suite checks the two sides against each other:
the Material Stock at Site balance in the books equals the value of the stock ledger.

**Progress comes up from the work, not down from a guess.** A milestone's percentage is the
weighted average of its tasks; a project's is the weighted average of its milestones. Neither
is typed in while tasks exist, because two people would then disagree about the same wall.
This matters beyond reporting: a construction-linked payment plan bills against milestones,
so completing one raises the installments waiting on it, dated from the day the work actually
finished. Site progress and a customer's schedule cannot drift apart.

**The second pair of hands is in the service, not the screen.** Whoever raises a requisition
cannot approve it; whoever certifies a contractor's work cannot approve that certificate.
Those rules live in `ProcurementService` and `VendorService`, so they hold however the record
is reached.

**A length limit on text counts characters, not value.** `max:20` on a field declared
`string` means twenty characters even when the value looks like a number — an account code
of `9901` is a code, not the quantity 9,901. Only a field declared `numeric`, `integer` or
`money` is compared as a number. The same rule closes a quieter hole: an all-digit password
is now measured in characters against `min:8` instead of being compared as a number.

**Money is never floating point.** `DECIMAL(18,2)` in the database, computed in PHP, and
every financial row carries its currency.

**Prices are calculated on the server.** The browser previews a price for feedback; the
server recomputes it from area × rate on save. A posted total is never trusted.

**Committed records are immutable.** A booked or sold unit refuses changes to its number,
area and price, and refuses archiving. Corrections go through the booking.

**Overpayment is refused, not clamped.** Paying more than a land installment's balance
errors out with the actual figures rather than silently absorbing the difference.

**Numbers cannot be reused.** Document sequences are issued inside a locked transaction,
and lowering a sequence's next number is refused.

**Timestamps are UTC.** The connection runs at `+00:00` and all writes use `gmdate()`.
Display converts to the organization's time zone; date-only columns are shown as stored.

**Soft deletion throughout.** Archiving sets `deleted_at` and cascades down the hierarchy;
projects can be restored. Records with transaction history cannot be archived at all.

**A booking locks the unit row before it reads it.** `BookingService::create` runs
`SELECT … FOR UPDATE` on the unit inside the transaction and re-checks its status there, so
two executives confirming the same unit cannot both succeed. The unique index on
`bookings(unit_id, active_flag)` is the database-level backstop: `active_flag` is `1` while
the booking holds the unit and `NULL` once cancelled, so any number of historical bookings
coexist with at most one live one — and a cancelled unit becomes bookable again.

**Money claimed as received must exist as a receipt.** A booking amount is not just a number
on the booking: it creates a real `customer_payments` row, allocated to the schedule, with a
ledger entry. A receipt is never written twice for the same idempotency key, so a
double-submitted form cannot bank the same money twice.

**A schedule always totals the price exactly.** Installments are computed from percentages,
and the final one takes the remainder, so rounding never leaves a few rupees unaccounted for.
Milestone billing percentages are normalised, so a project whose percentages do not reach
exactly 100 still produces a complete schedule.

**Approval withholds consequences, not just status.** An over-threshold discount leaves the
booking at `pending_approval` with the unit held but *no* schedule and *no* receipt. Both
appear only on approval; rejection cancels the booking and frees the unit. Whoever raised a
booking cannot approve its own discount.

**Cancellation is a record, not a deletion.** It writes why, what was received, what was
retained and what is refundable; closes the plan; reverses the sale value in the ledger and
re-states the retained charge; raises a refund; and releases the unit. The charge can never
exceed money actually received, however the percentage is configured.

**Conversion never duplicates a customer.** Converting a lead whose phone already belongs to
a customer links that customer instead of creating a second one — the specification is
explicit about this, and the test suite asserts it from two different leads.

**Business rules are settings, not constants.** Hold duration, grace period, late fee,
commission trigger, approval thresholds and the rest live in `organization_settings` with
documented defaults, because property regulation differs by jurisdiction.

---

## Security

- CSRF token verified on every state-changing request
- Content-Security-Policy, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
- Prepared statements everywhere; no user input is ever interpolated into SQL
- Sort columns come from a whitelist, never from the query string
- Every output escaped through `e()`
- Organization id is read from the session, never from a request parameter
- Uploads stored outside the web root under a random name, MIME sniffed server-side, served only through an authorised action
- bcrypt password hashing with automatic rehash; lockout on repeated failures
- Passwords, tokens and bank account numbers redacted before the audit trail is written
- Session fixation prevented by regenerating the id on login

---

## REST API

JSON endpoints under `/api`, same session auth and permissions, consistent envelope:

```json
{ "success": true, "data": [ ... ], "meta": { "count": 128 } }
```

| Endpoint | Purpose |
|---|---|
| `GET /api/search?q=` | Global search across permitted records |
| `GET /api/projects` | Project lookup |
| `GET /api/buildings?project_id=` | Buildings in a project |
| `GET /api/floors?building_id=` | Floors in a building |
| `GET /api/units?floor_id=&available_only=1` | Units on a floor |
| `GET /api/landowners` | Landowner lookup |
| `GET /api/inventory/{projectId}` | Whole inventory board as JSON |
| `GET /api/stats/dashboard` | Dashboard metrics |
| `GET /api/bookings/plan-preview` | Live price and installment schedule for a booking being drafted |

---

## Testing

`php database/migrate.php --status` confirms the schema. Beyond that, six end-to-end
suites drive the real HTTP stack — CSRF, validation, transactions, permissions — and assert
against the database afterwards. **1,161 checks, all passing** (including the security suite). Each suite cleans up after
itself, so they can be run as often as you like against a demo database.

**Phase 1 suite — 155 checks, all passing:**

sign-in, wrong-password refusal and a token-less POST that writes nothing · a land parcel
with its payment schedule, an overpayment refused with the shortfall named, and the
installment settled exactly · a project joined to that land · two milestones, then progress
recomputed as weight × completion rather than a count of rows · a building, floors generated
once and not doubled on a second attempt · four units with unique numbers · pricing computed
on the server, with tax charged on the base and premiums but not on other charges · a hold,
a second hold refused, **three simultaneous holds leaving exactly one live hold**, release
keeping the hold as history · the sweeper releasing an expired hold and closing it rather
than deleting it · a booked unit's price refused as fixed by the booking · a sold unit
refused for archiving, an available one archived as a soft delete · role boundaries for a
site engineer and a sales manager, including money hidden from the engineer · audit entries
that all name who acted · a PDF uploaded, its type **sniffed rather than taken from the browser**, stored under a
random name, downloaded as an attachment with the original filename · HTML wearing a .pdf
extension refused by what it actually is · an SVG refused by type before its contents matter
· empty and oversized files refused · a traversal filename kept as a name and not a path ·
a second upload becoming version two rather than overwriting · removal keeping the file on
disk by design · four CSV exports · and standing invariants on orphans, duplicate
unit numbers, overpaid land and milestone weights

**Phase 2 suite — 125 checks, all passing:**

a lead captured and numbered · **an assignment notifying the person it went to, which
nobody else can see or mark read** · the notification list, its unread and read filters, mark
one, mark all, and marking all again reporting nothing to do · the same number in another format caught as a duplicate
(`9820011223` vs `+91 98200 11223`), then created deliberately with an override · a lead
with no phone refused · stage movement, an activity logged, assignment recorded · a site
visit scheduled and completed · conversion creating one customer and moving the lead to the
booking stage, with a second conversion refused · **KYC verification refused with no
identity on file**, then accepted once a PAN is recorded · a quotation whose total is its
own parts less the discount plus tax, with the tax charged on what is left after the
discount · a quotation expiring before it starts refused · a booking that takes the unit off
the market, names its lead and customer, and raises a schedule that adds up to the agreed
price · a second booking on the same unit refused · **three simultaneous bookings never
selling the unit twice** · milestone-linked installments dated from their milestones · the
booking receipt on record · role boundaries for an executive and an engineer · three
exports · and standing invariants on double-booking, orphans and over-receipting

**Phase 3 suite — 125 checks, all passing:**

the whole ledger balances · a numeric account code is accepted · duplicate codes refused ·
a system account is never deleted, only deactivated · an account carrying posted lines is
never deleted · a balanced manual journal posts, is numbered and is immutable · an
unbalanced entry is refused and writes nothing · a one-line entry refused · a line carrying
both a debit and a credit refused · reversal creates a mirror that references the original,
nets the account to zero and cannot itself be reversed · period generation is idempotent ·
**nothing posts into a closed period**, and posting resumes when it is reopened · bank
transaction and reconciliation · ageing, receipts and reminder screens · a waiver beyond
the outstanding balance refused · a partial waiver recorded and the balance falls by it ·
late fee charged · installment rescheduled · reminders written per selected installment,
and sending none is reported rather than silently accepted · expense raised, numbered,
taxed, paid by a valid mode only · **raising posts the accrual and paying posts the
payment, and paying twice posts nothing further** · a paid expense can be neither edited
nor deleted · trial balance balances · assets equal everything else · cash flow, project
profitability and four exports render · a POST without a CSRF token is sent back and writes
nothing · a signed-out visitor is redirected · a sales user cannot open accounting

**Phase 4 suite — 281 checks, all passing:**

a numeric bank account number is accepted · an unknown vendor type refused · a review sets
the rating to its average and a score outside 1–5 is refused · nothing can be certified
against a draft work order · **certifying beyond the award is refused**, with the shortfall
named · retention and tax come off a certificate at the order's rates · **whoever certified
cannot approve** · a second pair of hands approving raises the contractor's bill, posts it,
and moves the contract's certified value · retention lands in Retention Payable, tax deducted
in TDS Payable, and Accounts Payable carries only what the contractor may draw · a work order
with an undecided certificate cannot be completed · blacklisting needs a reason and then
blocks a new award · a duplicate store code refused · an unknown unit of measure refused ·
a requisition cannot be ordered before it is decided · **the person who raised it cannot
approve it** · ordering carries every line still to be ordered, and a second order is refused
· a draft order cannot be received against · **receiving more than a line has outstanding is
refused** · only what was accepted enters the store, and the receipt posts Dr Material Stock
/ Cr Accounts Payable · an order already received against cannot be cancelled · deductions
beyond a bill refused · a draft bill cannot be paid · approving one adds only the tax when
the goods are already on the books, and writes a tax record · **paying beyond the balance
refused**, part payment then full payment moves it to paid · an advance posts to Advances to
Vendors · **issuing more than the store holds is refused** · an issue is valued at weighted
average and moves cost into work in progress · a transfer to the same store refused; a real
one writes two movements that reference each other and posts nothing · an adjustment without
a reason refused, and a shortage is written off to materials · a store with movements cannot
be archived · a task cannot end before it starts · **a task cannot run ahead of what it waits
on** · progress over 100% refused, backwards refused, unchanged refused · the milestone is the
weighted average of its tasks, not the count · **completing the milestone raises the
installment tied to it**, dated from the work · a second site report for one day refused · a
failed inspection with no remarks refused · a second partner cannot share a phone number · a
percentage rule with no rate refused · **a slab rule prices the commission from the right
band** · a booking cannot earn a second commission, and one with no partner earns none · an
unapproved commission cannot be paid · approving posts gross as cost, net as payable and the
tax deducted separately · paying beyond the net refused · voiding leaves what was already
paid on the record · a rule that has priced a commission is deactivated rather than removed

Eight standing invariants are also checked directly, after everything above has run:

- the whole ledger balances, and every entry the run posted balances on its own;
- **Material Stock at Site in the books equals the value of the stock ledger**;
- no stock balance anywhere is negative;
- every milestone's progress equals the weighted average of its tasks;
- no contract is certified beyond its award;
- no bill is paid beyond its net payable, and no commission beyond its net;
- no receipt exceeds what its order asked for.

**Phase 5 suite — 193 checks, all passing:**

an invitation grants access, stores only a hash, and shows the one-time password once · the
invitation carries that password · a signed-out visitor cannot reach the portal · a wrong
password is refused without saying which part was wrong, and the attempt is recorded · a
customer signs in and every portal page renders · **a customer cannot reach the staff
dashboard, accounting, portal administration or another customer's statement** · **a receipt
that belongs to somebody else is a 404; their own opens and prints** · a portal request is
attributed to the customer, carries their unit automatically, and gets the deadline its
priority earns · the first message is the customer's own words · **another customer's request
is neither readable nor replyable, and nothing is written to it** · a customer can change how
to reach them but **cannot rename themselves or alter their KYC** · a password change needs
the current password, rejects a short one and a mismatch, and the old password then stops
working · **an internal note does not stop the response clock and emails nobody; a real reply
does both** · **the customer never sees the internal note** · a request can be raised into the
snag list once, carrying its unit · resolving needs something to say · the customer can rate
it, and reopen it within a fortnight · closing needs it resolved first · one booking cannot
have two handovers · **it is not ready while the agreement is unsigned, while money is
outstanding, or while a critical or major snag is open** — each refusal naming that gate · a
snag cannot be verified before it is resolved · **a resolved snag still holds possession until
somebody checks**, and sending it back reopens it with the reason · **a new blocking snag takes
a ready handover back to planned** · keys are not handed over with the checklist unfinished ·
on completion the unit is marked handed over with its date, the booking closes, warranty
periods start from possession, and the buyer is told · a unit cannot be handed over twice, and
a completed handover cannot be cancelled · a snag raised after possession leaves the handover
alone · a warranty cannot end before it starts · **withdrawing portal access stops an open
session at once**

Eleven standing invariants are also checked directly, after everything above has run:

- every portal session belongs to a customer of that organization;
- **no customer has a readable password stored** — only bcrypt hashes;
- every completed handover has all three gates stamped, and left nothing outstanding;
- every handed-over unit has a possession date;
- no warranty ends before it starts;
- every service request has its deadline computed, and no resolved one lacks a resolution;
- **no internal note was ever emailed to a customer**;
- every sent message records its driver and the time it went;
- the ledger still balances.

All three suites live in the repository:

```bash
bash tests/e2e-phase3.sh     # needs the dev server running on :8787
```

```bash
bash tests/e2e-phase4.sh     # needs the dev server running on :8787
```

```bash
bash tests/e2e-phase5.sh     # needs the dev server running on :8787
```

Each restores every row it touched when it finishes — including the milestone and installment
the Phase 4 suite moves, and the customer credential, booking and unit the Phase 5 suite
moves — and asserts afterwards that the ledger still balances. Together the six phase suites run 1,026 checks.
Both were rewritten into the repository on 13 November 2026 and can be run like any other:
`bash tests/e2e-phase1.sh` (155 checks) and `bash tests/e2e-phase2.sh` (125 checks), each with
its own cleanup script.

**Phase 6 suite — 147 checks, all passing:**

every screen renders · an employee hired, given pay, confirmed · pay refused before they
joined · earnings adding back to exactly the gross · a revision superseding rather than
overwriting, with the old one dated closed · attendance marked, then amended, with still one
record for that day · a future day refused · a day before they joined refused · leave
requested, the overlap refused, approved, the days appearing in attendance, cancelled, the
days released · **deciding your own leave refused** · payroll built as a draft with nothing
posted · the run's totals matching its payslips · the same month rebuilt rather than doubled
· **approving your own payroll refused** · somebody else approving it · the accrual dated to
the month it is for · the cost posted being gross plus employer contributions · what is owed
being the net, not the gross · attendance into an approved month refused · pay backdated into
it refused · the payment posted and clearing exactly what was owed · paying twice refused · a
payslip printing · a target set and counted from real bookings · the same target twice refused
· a target without a login refused · the assistant answering, refusing what it does not know,
refusing an **instruction**, and refusing a question whose module the signed-in role cannot
open · and the standing invariants: every journal balances, one run a month, one attendance
record per person per day, one payslip per person per run, no employee on two structures at
once, no orphaned payslip line, no login claimed twice, approved leave always carrying its
attendance, nobody approving their own payroll, nobody deciding their own leave

```bash
bash tests/e2e-phase6.sh          # 147 checks
php  tests/cleanup-phase6.php     # puts the database back
```

### Quality and security documentation

A full QA and security pass was run on 13 November 2026. The reports are in the repository
root and are kept current with the code:

| Document | What it holds |
|---|---|
| [`PRODUCTION_READINESS_REPORT.md`](PRODUCTION_READINESS_REPORT.md) | The verdict against the acceptance criteria, and what would change it |
| [`SECURITY_AUDIT_REPORT.md`](SECURITY_AUDIT_REPORT.md) | 129 live attack checks, the findings, and what was deliberately not tested |
| [`INTEGRATION_TEST_REPORT.md`](INTEGRATION_TEST_REPORT.md) | The five suites, the ten end-to-end workflows, the financial identities |
| [`UNIT_TEST_REPORT.md`](UNIT_TEST_REPORT.md) | Unit coverage, and a mutation check proving the tests detect a defect |
| [`BUG_TRACKER.md`](BUG_TRACKER.md) | Every defect found, its root cause, its fix and its regression test |
| [`MODULE_QUALITY_MATRIX.md`](MODULE_QUALITY_MATRIX.md) | All 30 modules: tested, partial, or untested, with the gaps named |
| [`TESTING_STRATEGY.md`](TESTING_STRATEGY.md) | Why the testing looks the way it does here |
| [`SYSTEM_ARCHITECTURE_MAP.md`](SYSTEM_ARCHITECTURE_MAP.md) | The pipeline, the identity model, where the risk concentrates |
| [`ROLLBACK.md`](ROLLBACK.md) | Deployment and rollback, rehearsed rather than assumed |

Everything that must pass before a change ships runs in one command:

```bash
bash tests/run-all.sh
```

It lints every PHP file, checks the migrations, runs the unit tests and all five suites with
their cleanups, re-checks the standing invariants and the trial balance, and exits non-zero
if any of it fails.

### Known gaps

- **Mail defaults to the log driver.** Out of the box every message is recorded in the
  outbox and written to `storage/logs/mail.log`, and nothing leaves the machine. Set
  `MAIL_DRIVER=smtp` with the host details in `.env` to send for real. The SMTP driver is
  written against the protocol and checks every reply code, but it has not been exercised
  against a live mail server in this environment — test it with your own host before
  relying on it.
- **Staff password reset still writes its link to the log** rather than going through the
  outbox. Customer portal invitations and resets do go through the outbox.
- **Seeded documents are placeholder rows** with no file on disk; downloading one returns a
  clear 404 explaining that. Documents you upload yourself work normally.
- **Automated unit tests** (PHPUnit) are not set up — Composer is unavailable in this
  environment. The end-to-end suites cover the same ground at the HTTP level.
- **Only email is actually delivered.** A reminder sent by email goes into the outbox and
  out through the configured driver; SMS and WhatsApp reminders are recorded with their exact
  message text as a worklist for somebody to send by hand, and the screen says so rather
  than implying they were transmitted.
- **The portal takes no payments.** A buyer can see their schedule, their receipts and what
  is outstanding, but paying online needs a gateway, which is not wired up.
- **Possession-letter numbering is sequential, not a document template.** The letter is
  generated from the handover record; an organization that needs its own wording would
  change the view rather than configure it.
- **A cancelled booking does not void its commission by itself.** Cancellation records
  whether commission is void under current policy, and a commission can be voided on its
  own page, but the two are not yet wired together.
- **Retention is held, not released.** Each certificate's retention lands in Retention
  Payable with the contract's defect-liability period on record; releasing it when that
  period ends is still a manual journal entry.
- **A rejected goods-receipt line is recorded, not returned.** The quantity and the reason
  are kept and never enter stock, but there is no debit note to the vendor behind it.
- **No approval-workflow engine.** Discount approval is implemented directly; the generic
  multi-level engine with delegation and escalation is a later phase.
- No WhatsApp integration and no payment gateway; both belong to later phases.
- **The assistant answers 23 questions and no more.** It is a catalogue, not a
  conversation: it does not follow up, does not combine two questions, and has no language
  model behind it. Anything outside the catalogue is answered with "I do not know" and the
  list of what it does know. The questions people asked and it could not match are kept, so
  the catalogue can be extended deliberately.
- **Payroll statutory rates are India's, hard-coded.** Provident fund at 12% of basic capped
  at the ₹15,000 wage ceiling, professional tax on the Maharashtra slab, and tax at source at
  a flat 10% above ₹7,00,000 a year. They live in one place in `HrService` but are not
  configurable, and a real payroll needs a tax engine with declarations, exemptions and a
  regime choice.
- **Leave does not accrue month by month.** A balance is opening days plus whatever has been
  credited; the monthly accrual and the year-end carry-forward run that would credit it are
  not written, so balances are opened for a full year up front.
- **No employee self-service.** Staff do not have a portal of their own: leave is requested
  on their behalf and a payslip is printed for them. The customer portal is separate and
  unaffected.
- **A payroll run cannot be reversed from the screen.** An approved run is in the books and
  stays there; correcting one means a journal reversal posted by hand, which is the right
  behaviour but is not yet a button that writes the mirror entry for you.
- **Attendance has no biometric or device import.** Days are marked on the grid or in bulk;
  there is no clock-in integration, and `source` is recorded so an imported day could be told
  from a marked one later.
---

## Deploying and rolling back

The full procedure — deployment, both rollback cases, and what each costs — is in
**[`ROLLBACK.md`](ROLLBACK.md)**. Read it before a release that contains a migration:
migrations are forward-only, so a schema change is undone by restoring the backup you took
before applying it, and there is no second chance to take that backup afterwards.

The short version for a shared host:

## Deploying to shared hosting

1. Upload everything, and point the domain's document root at `public/`.
2. Create a MySQL database and set the credentials in `.env`.
3. Run `php database/migrate.php` then `php database/seed.php` (no `--demo` in production).
4. Make `storage/uploads` and `storage/logs` writable by the web server.
5. Set `APP_ENV=production`, `APP_DEBUG=false` and the real `APP_URL`.
6. Add a cron entry for `php database/sweep-holds.php` every 15 minutes.
7. Confirm `storage/` is not reachable over HTTP.
