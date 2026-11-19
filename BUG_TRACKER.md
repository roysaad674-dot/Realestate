# EstateOS — Bug Tracker

Defects found, their root cause, the fix, and the check that now stops each one coming back.
Every entry was reproduced before it was fixed and re-tested after.

**Date of this pass:** 13 November 2026.

---

## BUG-001 — Deleting a holiday returns a 500

| | |
|---|---|
| **Severity** | Medium (a feature is unusable; no data risk) |
| **Module** | HR → Attendance → Holidays |
| **Found by** | Schema review during the security audit, then proved by test |
| **Status** | **Fixed and verified** |

**Root cause.** `Controller::findOrFail()` is shared by every controller and always queries
`… AND organization_id = :o AND deleted_at IS NULL`. The `holidays` table is a hard-delete
table and has no `deleted_at` column, so the query failed with
`Unknown column 'deleted_at' in 'where clause'` and the request became a 500.

**Reproduction (RED).** A check was added to `tests/e2e-phase6.sh` that adds a holiday and
then removes it. Before the fix:

```
FAIL it can be removed again    expected [302], got [500]
FAIL and it is gone             expected [0], got [1]
```

**Fix (GREEN).** `AttendanceController::destroyHoliday()` now looks the record up directly
with an explicit organization scope instead of calling the shared helper, and 404s when it
is not found. The organization scope `findOrFail` would have applied is applied by hand, so
another tenant's date is still a 404 — verified by the security suite.

**Regression test.** `tests/e2e-phase6.sh`, section "Holidays": add, refuse a duplicate
date, remove, confirm gone. 140 checks → 147.

**Note for the future.** Any other hard-delete table reached through `findOrFail` will fail
the same way. All fifteen call sites were audited; `holidays` was the only one affected.

---

## BUG-002 — Payslip lines written before the payslip they belong to

| | |
|---|---|
| **Severity** | High (payroll could not be built at all) |
| **Module** | Payroll |
| **Found by** | Running the Phase 6 seeder against real data |
| **Status** | **Fixed and verified** |

**Root cause.** `PayrollService::build()` inserted each `payslip_lines` row inside the
component loop, then inserted the `payslips` header after it. The foreign key
`fk_payslipline_payslip` rejected the first line.

**Reproduction.** `php database/seed-phase6.php` →
`Integrity constraint violation: 1452 … fk_payslipline_payslip`.

**Fix.** The loop now builds the lines in memory and computes the totals; the payslip header
is inserted, then its lines. Same arithmetic, correct order.

**Regression test.** `tests/e2e-phase6.sh`: "every payslip line belongs to a payslip" —
`SELECT COUNT(*) … LEFT JOIN payslips … WHERE p.id IS NULL` must be 0. The security suite
asserts it too, so it is checked from two directions.

---

## BUG-003 — Payroll accrual dated to the approval day, not the month it was earned

| | |
|---|---|
| **Severity** | High (misstates the profit and loss of two months) |
| **Module** | Payroll → Accounting |
| **Found by** | Reading the posted journals after the seeder ran |
| **Status** | **Fixed and verified** |

**Root cause.** `AccountingService::postPayroll()` used the run's `approved_at` as the entry
date. Approving September's payroll on 13 November therefore posted September's wage cost
into November — which is exactly what an accrual exists to prevent, and what the method's
own doc comment promised it did.

**Reproduction.** Journals after seeding: `JV-000230 payroll 2026-11-13` for the September
run.

**Fix.** A new `accrualDateFor()` puts the entry on the **last day of the month the work was
done in**, falling back to today only when that period has been closed — a closed period
must stay closed. The payment entry correctly keeps the date the money actually left.

**Verification.** After the fix: `payroll 2026-09-30`, `payroll_payment 2026-10-02`,
`payroll 2026-10-31`. Trial balance 0.00.

**Regression test.** `tests/e2e-phase6.sh`: "the accrual is dated to the month it is for,
not today" — asserts `entry_date = LAST_DAY(period_month)`.

---

## BUG-004 — Payment date collected from the user and then discarded

| | |
|---|---|
| **Severity** | Medium (the bank entry lands on the wrong day) |
| **Module** | Payroll |
| **Found by** | Comparing the seeder's input with the posted journal |
| **Status** | **Fixed and verified** |

**Root cause.** The payroll payment form collects `paid_on`, and `PayrollController` passes
it through, but `PayrollService::pay()` ignored it and used `now()`.

**Fix.** `pay()` now uses the stated date, refuses a date in the future ("cash cannot have
moved tomorrow") and refuses a date before the month being paid.

**Regression test.** Covered by BUG-003's date assertions plus the suite's payment checks.

---

## BUG-005 — A month already paid was closed to amended attendance but open to new attendance

| | |
|---|---|
| **Severity** | High (changes a payslip's basis after it was issued) |
| **Module** | HR → Attendance |
| **Found by** | A security-minded check in the Phase 6 suite that failed |
| **Status** | **Fixed and verified** |

**Root cause.** `HrService::markAttendance()` called `assertMonthOpen()` only when amending
an existing row. Adding a day that was never marked passed straight through — and because
unmarked days count as worked, adding an "absent" into a paid month silently changed the
payable days behind a payslip that had already been approved and posted.

**Reproduction (RED).** "attendance for an approved month is refused" — expected a refusal,
got 302 and the row was written.

**Fix.** `assertMonthOpen()` now runs before any write, amendment or not.

**Regression test.** `tests/e2e-phase6.sh`: marking a day inside an approved month must be
refused with "cannot be changed".

---

## BUG-006 — A duplicate sales target crashed instead of explaining itself

| | |
|---|---|
| **Severity** | Low |
| **Module** | HR → Sales targets |
| **Status** | **Fixed and verified** |

**Root cause.** `sales_targets` has a unique key on (employee, metric, period). Setting the
same target twice hit it and produced a 500.

**Fix.** `EmployeeController::storeTarget()` checks for the existing target first and
refuses with a sentence naming the person, the metric and the period.

**Regression test.** `tests/e2e-phase6.sh`: "the same target twice is refused" plus "there
is still only one".

---

## BUG-007 — Phase 5 suite depended on a fixture property it never required

| | |
|---|---|
| **Severity** | Medium (test defect — a false red, and worse, a possible false green) |
| **Module** | Test estate |
| **Status** | **Fixed and verified** |

**Root cause.** `tests/e2e-phase5.sh` picked "any customer without portal access", but later
checks raise that customer's service request into the snag list, which needs the unit behind
their booking. On a database where no such customer had a booking, five checks failed; on
another run the same suite passed by luck.

**Fix.** The selection now requires a customer who owns a booking with a unit, which is what
the assertions always needed. The precondition is explicit instead of accidental.

**Verification.** Phase 5: 186/5 failed → **193/193 passing**.

**Why this is in the bug tracker.** A test that passes for the wrong reason is a defect in
the safety net, and the brief is right that it should never be "fixed" by relaxing the
assertion. Nothing was relaxed: the fixture was corrected.

---

## BUG-008 — Employee numbering would have issued a duplicate

| | |
|---|---|
| **Severity** | Medium (would have 500'd on the next hire) |
| **Module** | HR → Employees |
| **Status** | **Fixed and verified** |

**Root cause.** Eight employee rows reached this database without going through
`Sequence::next()`, so the counter still stood at 1 while `EMP-0001`…`EMP-0008` existed.
The next hire through the application hit the unique key on `employee_no`.

**Reproduction.** `Duplicate entry '…-EMP-0001' for key 'uq_employee_no'`.

**Fix.** `database/seed-phase6.php` now advances the counter past the highest number
actually in use when it finds employees it did not create.

**Regression test.** `tests/e2e-phase6.sh` hires an employee over HTTP and asserts a number
was allotted; it runs after the seeder, so a stale counter fails the suite.

---

## BUG-009 — The quality gate could not fail on a failing unit suite

| | |
|---|---|
| **Severity** | High (a broken gate is worse than no gate — it reports safety that is not there) |
| **Module** | Test estate — `tests/run-all.sh` |
| **Found by** | Reading the gate back after writing it |
| **Status** | **Fixed and verified** |

**Root cause.** The gate ran each step as a pipeline so it could show only the summary
line:

```bash
"$PHP" "$SP/test-unit.php" | tail -3
record "$?" "unit tests (test-unit.php)"
```

`$?` after a pipeline is the exit status of the **last** command in it — `tail`, which
succeeds whatever PHP did. A failing unit suite would therefore have been recorded as
passed, and the gate would have exited 0 on a red build.

**Reproduction.** A deliberately failing assertion was inserted into `tests/test-unit.php`:

```
[FAILURE] 1 unit test(s) failed:
  - TEMPORARY mutation to prove the gate catches failure
exit status of php = 1
```

PHP exits 1; the old line recorded `tail`'s 0. The mutation was removed and the file
verified byte-identical to its backup.

**Fix.** `record "${PIPESTATUS[0]}"`, which asks for the first command's status. The other
steps already used `PIPESTATUS`; this one line did not.

**Why it is High.** Every other entry in this tracker is a defect the tests would catch.
This one is a defect in the thing that catches defects, and it fails silently and in the
safe-looking direction.

---

## Earlier defects, fixed before this pass

Recorded for completeness; each has a regression check in the suite named.

| ID | Defect | Suite |
|---|---|---|
| BUG-E01 | `/finance/cash-flow` 500 — a bound parameter was never used in the by-account query | Phase 3 |
| BUG-E02 | `Validator` compared numeric-looking strings as numbers, so `max:20` rejected valid account codes and `min:8` let all-digit passwords through | Phase 3 |
| BUG-E03 | Requisition and goods-receipt lines inserted before their header (same class as BUG-002) | Phase 4 |
| BUG-E04 | `AS lines` broke three queries — `LINES` is reserved in MariaDB | Phase 4 |
| BUG-E05 | 15 demo units were marked handed over with no possession date | Phase 5 |
| BUG-E06 | Payment reminders were recorded but never delivered | Phase 5 |

---

## FOUND-010 — Uncommitted change fabricates message delivery and document records

| | |
|---|---|
| **Severity** | **High** (creates false business records) |
| **Module** | Sales → Booking (`app/Services/BookingService.php`) |
| **Status** | **Reverted on the user's instruction, 13 November 2026.** Their version is preserved outside the repository (see below) |
| **Found** | 13 November 2026, while committing the Phase 1 and 2 suites |

**What it does.** 56 lines were added to `BookingService::create()` by the other effort
working in this checkout (see `PRODUCTION_READINESS_REPORT.md` §6). Two of the three
additions write records asserting that something happened when nothing did.

**1. A WhatsApp message is recorded as delivered, having never been sent.**

```php
'status'              => 'delivered',
'provider_message_id' => 'WA-' . rand(100000, 999999),
'sent_at'             => now(),
'delivered_at'        => now(),
```

There is no WhatsApp integration in this application — `README.md` says so, and no code
sends anything. Every booking would therefore write a row claiming the buyer was messaged,
with a delivery timestamp and a provider reference invented by `rand()`.

**Why this is High and not cosmetic.** "Was the customer told?" is a question that gets
asked in disputes, in audits and in court. A row saying *delivered at 15:42, provider ref
WA-483920* is evidence. Here it would be false evidence, generated automatically, and
indistinguishable from a real one. The existing SMS and WhatsApp reminders in Phase 3 were
written the opposite way round on purpose: they are recorded as a **worklist for somebody to
send by hand, and the screen says so** rather than implying transmission.

**2. A document record claims a file that does not exist.**

```php
'file_name'   => 'booking_….pdf',
'stored_path' => '/documents/bookings/' . $bookingId . '.pdf',
'size_bytes'  => 1048576,
```

No file is written. `README.md` already lists seeded placeholder documents as a known gap;
this would create new ones on every booking, in production.

**3. Everything is wrapped in `catch (\Throwable $e) {}` with an empty body**, so if any of
it fails — including the commission accrual, which is the one legitimate addition — nothing
is logged and nobody finds out.

**Reverted, on instruction.** `app/Services/BookingService.php` was restored to its
committed state, and their 56-line version was first copied out of the repository so nothing
is lost:

```
<session scratchpad>/BookingService.other-effort-version.php
```

**The rows it had already written were removed.** While the code was active it ran during
this session's own Phase 2 suite runs and wrote **12 WhatsApp rows marked `delivered`** with
invented provider references, and **12 `document_files` rows** pointing at PDFs that do not
exist — all of them for the `E2E2` test customer, all between 11:38 and 11:43 on
13 November, and all twelve document rows already orphaned by the suite's own cleanup. They
were deleted. The five pre-existing demo WhatsApp rows were left alone, and
`tests/cleanup-phase2.php` now sweeps both tables so a suite cleans up after whatever the
code it ran against happened to do.

**The commission accrual was reverted too, and that was the judgement call.** It was the one
legitimate-looking addition, but `CommissionService::raise()` is already reachable
deliberately from `BrokerController` — including a bulk sweep for bookings that have a broker
and no commission yet — and the payout trigger is a configurable setting
(`commission_trigger`, default `collection`). Auto-raising at booking time bypasses that
design, and the empty `catch` would have swallowed the service's own refusals ("not sourced
by a channel partner"). If the intent was to remove a manual step, the place to change is the
trigger setting, not a silent call inside the booking transaction.

**What to do.** Whoever wrote it should decide, but the honest shapes are: queue the message
through `mail_messages`-style outbox semantics with `status = 'queued'` and let a real driver
move it to `sent`; or record it as a worklist item and say on screen that it has not been
sent. For the document, either generate the PDF or do not claim one exists.

**The regression check, now that the code is gone.** It is still not in the suite: asserting
the absence of rows that nothing writes is a test with no subject. If the integration comes
back in an honest form — queued rather than claimed delivered — the check to add is:

```bash
is "a booking never records a message as delivered that nothing sent"   "$(q "SELECT COUNT(*) FROM whatsapp_messages WHERE customer_id='$CUSTOMER' AND status='delivered'")" 0
is "nor a document file that is not on disk"   "$(q "SELECT COUNT(*) FROM document_files WHERE entity_id='$BOOKING' AND size_bytes > 0")" 0
```

What `tests/cleanup-phase2.php` does assert, in effect, is that the suite leaves neither
table dirty afterwards.

---

## BUG-011 — A cleanup script claimed to restore state and silently did not

| | |
|---|---|
| **Severity** | Medium (a test that cleans up imperfectly corrupts later runs) |
| **Module** | Test estate — `tests/cleanup-phase2.php` |
| **Status** | **Fixed and verified** |
| **Found** | 13 November 2026, while adding notification coverage |

**Root cause.** The notification restore and the scoped delete were added inside the
script's existing `Database::transaction(function () use ($org, $since, &$removed) { … })`,
and neither `$state` nor `$wasUnread` was added to the `use` list. PHP closures do not
inherit scope, so both were null inside: the restore loop iterated nothing and the delete
matched nothing. **No error was raised** — the script printed a tidy summary and reported
success while doing none of it.

**How it showed up.** The demo database went from "8 notifications, 8 unread" to "0 unread"
and stayed there across runs, because every run marked them read and nothing put them back.
It was only visible by counting rows before and after, which is why the count is now printed.

**Fix.** `use ($org, $since, $state, $wasUnread, &$removed)`, and the script now prints the
notification tally it leaves behind:

```
  notifications unread again 8
  notifications              1
  notifications              8 on file, 8 unread
  trial balance              0.00
```

**Verified** by a full round trip: 8 unread → suite runs 125 checks → 8 unread again, twice
consecutively.

**The lesson worth keeping.** This is the second silent-failure bug in this session's own
test tooling (BUG-009 was the gate reading `tail`'s exit status). Both reported success while
doing nothing. Test tooling needs the same scepticism as the code it tests: it is now a
convention that a cleanup script states what it left behind rather than only what it removed.

**Also corrected at the same time:** the delete was originally written as "every orphaned
lead notification in this organization", which swept 12 rows on its first run. They turned
out to be this suite's own residue from earlier runs, but the scope was wrong on principle —
it is now limited to the notifications produced by this suite's own leads, taken from the
state file.

---

---

## BUG-NEW-001 — `/legal` page rendered with blank content

| | |
|---|---|
| **Severity** | Medium (module unusable) |
| **Module** | Legal & Agreements |
| **Found by** | Post-GO QA session, 16 November 2026 |
| **Status** | **Fixed and verified** |

**Root cause.** `app/Views/legal/index.php` opened `View::startSection('content')` but never called the matching `View::endSection()`. The content buffer was never flushed into the layout.

**Fix.** Added `<?php View::endSection(); ?>` at the end of `app/Views/legal/index.php`.

---

## BUG-NEW-002 — `/legal`, `/approvals`, `/communications` showed "Dashboard" as browser tab title

| | |
|---|---|
| **Severity** | Low (cosmetic) |
| **Module** | Legal, Approvals, Communications |
| **Found by** | Post-GO QA session, 16 November 2026 |
| **Status** | **Fixed and verified** |

**Root cause.** The layout uses `$pageTitle = $title ?? 'Dashboard'`. Three controller `index()` methods — `LegalController`, `ApprovalController`, `CommunicationController` — did not pass the `'title'` key in their view data arrays, so all three pages showed "Dashboard · EstateOS" as the browser tab title.

**Fix.** Added `'title' => '...'` to the view data in all three `index()` methods (commit `a89f676`).

---

## BUG-NEW-003 — `AttendanceController::storeLeaveType()` truncated decimal carry values

| | |
|---|---|
| **Severity** | Medium (data precision loss) |
| **Module** | HR → Attendance → Leave Types |
| **Found by** | Post-GO QA session, 16 November 2026 |
| **Status** | **Fixed and verified** |

**Root cause.** The `max_carry` column is `DECIMAL(5,1)` — it accepts half-day precision (e.g. 2.5 days). The insert used `(int) ($data['max_carry'] ?: 0)` which silently truncated `2.5` to `2`. An earlier error log entry (`15:55:35 to 15:55:45`) showed `Column 'max_carry' cannot be null`; that was from a previous code version before the `?: 0` default was introduced. Additionally, `?:` treats `"0"` as falsy so the default was always used instead of the submitted zero; `??` is the correct null guard here.

**Fix.** Changed to `(float) ($data['max_carry'] ?? 0.0)` — `??` for null-safe default, `float` to preserve half-day precision through `PDO::PARAM_STR` to MariaDB DECIMAL.

---

## BUG-NEW-004 — `CommunicationController` imported wrong `Uuid` namespace

| | |
|---|---|
| **Severity** | Low (dead import, latent class-not-found risk) |
| **Module** | Communications |
| **Found by** | Post-GO QA session, 16 November 2026 |
| **Status** | **Fixed and verified** |

**Root cause.** `CommunicationController` had `use App\Core\Support\Uuid;` (non-existent namespace) while calling `\App\Core\Uuid::v4()` via fully-qualified name. The dead import would not break at runtime but would fail any autoload strict-mode check and mislead future maintainers.

**Fix.** Corrected to `use App\Core\Uuid;` and updated the call to use the short name.

---

## BUG-NEW-005 — `brokers/form.php` emitted PHP 8.2 warnings in create mode

| | |
|---|---|
| **Severity** | Low (PHP warnings, no functional impact) |
| **Module** | Brokers |
| **Found by** | PHP error log, 16 November 2026 |
| **Status** | **Fixed and verified** |

**Root cause.** Four ternary expressions accessed `$broker['id']`, `$broker['name']`, `$broker['code']` directly. When the form is opened in create mode, `$broker = null`; PHP 8.2 warns "Undefined array key" on those array accesses. Even though PHP ternaries short-circuit, the warning fired before the short-circuit evaluation in some PHP 8.2 builds.

**Fix.** Added null-coalescing guards: `($broker['id'] ?? '')`, `($broker['name'] ?? '')`, `($broker['code'] ?? '')` on the four affected lines (7, 16, 25, 72).

---

## Open — not fixed, with the reason

| ID | Issue | Why it is open |
|---|---|---|
| SEC-001 | CSP permits inline script | Needs a nonce mechanism across five views; changing CSP without browser testing risks breaking pages silently |
| SEC-002 | No rate limiting beyond per-account lockout | A new control, and where it belongs (app or web server) is a deployment decision |
| SEC-003 | Session `Secure` flag depends on `$_SERVER['HTTPS']` | Correct as written for direct TLS; needs a deployment-specific answer for proxies |
| ~~OPEN-01~~ | Notifications module has no automated test | **Closed 13 Nov**: 24 checks in Phase 2 from a real assignment event, 6 cross-tenant probes in the security suite |
| OPEN-04 | An org-wide notification has one `read_at`, so one person reading it clears it for everybody | Needs a `notification_reads` table — a schema decision, not a patch. Proved by a check rather than assumed; see `MODULE_QUALITY_MATRIX.md` |
| OPEN-02 | Document upload tested for access control only, not file content | Needs a functional suite for uploads |
| OPEN-03 | Management report totals are not reconciled against independently computed figures | Would need a second implementation of each total to compare against |
| ~~FOUND-010~~ | A booking fabricated a "delivered" WhatsApp record and a document row for a file that does not exist | **Reverted 13 Nov** on instruction; their version preserved outside the repository, and the rows it wrote removed |
