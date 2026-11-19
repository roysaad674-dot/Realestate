# EstateOS — Testing Strategy

*This document also serves as `QA_TEST_STRATEGY.md`; the two were asked for separately but
would have held the same content, and one document that is current beats two that drift.*

---

## 1. The constraint that shapes everything

EstateOS has **no Composer and no npm, by design**. There is no framework, no ORM, no
template engine and no JavaScript library. That is a deliberate architectural decision, not
an oversight, and it rules out the usual testing stack:

| Normally | Here | Why |
|---|---|---|
| PHPUnit | Not available | No Composer; PHPUnit cannot be installed |
| Vitest / Jest / React Testing Library | Not applicable | No build step and no component framework — the views are PHP templates rendered server-side |
| Playwright / Cypress | Not available | No npm |
| OWASP ZAP, Semgrep, Snyk | Not available | No package manager in this environment |

Adding any of them would mean introducing the dependency model the project exists to avoid.
The strategy therefore does not pretend to use them. It uses what the project can actually
run: **PHP's own CLI, `curl`, and `bash`.**

## 2. What is tested instead, and why it is not a compromise

The suites drive the **real HTTP stack**: a real session cookie, a real CSRF token taken
from a real form, real validation, real transactions, real permission checks, and then
assertions made **against the database** rather than against the response body alone.

That is closer to an integration/end-to-end test than a unit test, and for an application
of this shape it catches more. A unit test of a payroll calculation would have passed while
the payslip lines were being inserted before their header row; the HTTP suite failed
immediately with a foreign-key violation, because it posted a real form and then looked at
what the database actually held.

What this approach gives up is pinpointing: a failure says "approving payroll broke", not
"line 147 of `PayrollService` broke". The stack traces in `storage/logs/php-error.log` close
most of that gap.

## 3. The test estate

| Suite | Checks | Covers |
|---|---|---|
| `tests/e2e-phase1.sh` | 155 | Sessions and CSRF, land and its payment schedule, projects, milestones, buildings, floors, units, server-side pricing, holds under concurrency, the sweeper, **document uploads and their file handling**, role boundaries, the audit trail, exports |
| `tests/e2e-phase2.sh` | 125 | Lead capture and duplicate detection, the pipeline, site visits, **notifications**, conversion, KYC, quotations, bookings, the schedule they raise, and concurrency |
| `tests/e2e-phase3.sh` | 125 | Collections, accounting, expenses, the finance statements |
| `tests/e2e-phase4.sh` | 281 | Vendors, construction, procurement, stores, broker commission |
| `tests/e2e-phase5.sh` | 193 | Possession, snagging, warranties, service desk, mail, the customer portal |
| `tests/e2e-phase6.sh` | 147 | Employees, attendance, leave, payroll, targets, the assistant |
| `tests/e2e-security.sh` | 135 | Tenant isolation, auth, authorization, injection, CSRF, portal and notification isolation, financial integrity |
| **Total** | **1,161** | |

Supporting scripts:

- `tests/q.php` — one value from one query, for assertions.
- `tests/sql.php` — a statement, for arranging a precondition.
- `tests/cleanup-phase*.php`, `tests/cleanup-security.php` — restore the database.
- `tests/fixtures-security.php` — the second tenant.
- `tests/fixtures-documents.php` — the harmless probe files the upload checks post.
- `tests/test-unit.php` — calculation and validator checks that need no HTTP.
- `tests/test-security.php` — a static pass over the source for unsafe patterns.

## 4. Conventions

**Every suite cleans up after itself.** A suite that can only be run once on a fresh
database is not a regression suite. Each one records what it created in a state file and its
cleanup script removes exactly that, then prints the trial balance so an unbalanced ledger
cannot be left behind quietly.

**A suite clears its own leftovers before it starts.** Phase 1 and Phase 2 call their own
cleanup script as their first act, using the state file the previous run wrote. A suite that
only passes on a freshly seeded database is not a regression suite — it is a demo.

**Cleanup is deterministic, not only id-based.** A suite interrupted before it recorded an
id would otherwise leave a row that makes the next run fail. Cleanup therefore also removes
by marker — the month a payroll suite always uses, records named `E2E%`, leads named
`XSSProbe` — so an interrupted run self-heals.

**Assertions are about facts, not wording.** `is "the run is approved" "$(q "SELECT status
…")" approved` survives a change to a flash message; grepping the page for "approved" does
not. Where a message is the thing being tested, the message is asserted deliberately.

**A flaky assertion is worse than a missing one.** The Phase 2 concurrency check does not
assert that three simultaneous bookings leave exactly one: three identical transactions
contending for the same row can all be rolled back as deadlock victims, leaving none. That
is poor service, not a data defect. The suite asserts the property that actually matters —
the unit is never sold twice — and accepts zero or one, naming which happened. Asserting
"exactly one" would have produced a test that fails for the wrong reason roughly one run in
ten, and a suite nobody trusts gets ignored.

**Negative tests are first-class.** Every suite spends much of its length proving that the
wrong thing is refused: paying twice, approving your own work, booking a sold unit, editing
a month already paid, reading another tenant's record.

**Standing invariants run at the end of every suite.** Every journal balances, the books
balance, one payroll run per month, one attendance record per person per day, no unit sold
twice, no negative stock. These catch damage done anywhere, including by a test.

**A refusal is asserted by status code, not by redirect.** The application answers a
service-level refusal with 403/404/422 and a reason on the page; a validation failure
redirects with a flash. The suites assert whichever is correct for the path, and the
security suite treats a 500 as a failure in itself — a 500 means an attack reached code that
did not expect it.

## 5. Test-driven development, as actually practised here

For every defect found during this work the cycle was: **write the failing check, run it,
watch it fail for the intended reason, fix, watch it pass, then run the whole suite.**

The clearest example is in `BUG_TRACKER.md` as BUG-001. The holiday-delete defect was
predicted by reading the schema, but it was not reported until a check was added to
`tests/e2e-phase6.sh`, run, and seen to fail with a 500. The fix then turned 140 passing
checks into 147.

What TDD was **not** used for: the Phase 6 feature code itself was written before its suite,
module by module, with the suite following immediately. That is honest to record — it is
test-after for the features and test-first for every fix.

## 6. Coverage, and why no percentage is quoted

There is no coverage instrumentation available (Xdebug is not enabled and cannot be assumed
in this environment), so **no line-coverage figure is given.** Quoting one without measuring
it would be the kind of number this project's own design principles reject.

What can be stated factually:

- Every route in `routes/web.php` that the phase suites cover is exercised over HTTP,
  including its refusal paths.
- Every financial posting path (collections, expenses, vendor bills, commission, payroll)
  is asserted against the ledger, with balance checks after each.
- Every "second pair of hands" rule — discount approval, requisition approval, work
  certificate approval, snag verification, leave decision, payroll approval — has a check
  that the *same* person is refused.
- The modules with **no** automated coverage are listed in `MODULE_QUALITY_MATRIX.md`.

## 7. Running everything

```bash
php database/migrate.php --status           # schema
bash tests/e2e-phase3.sh && php tests/cleanup-phase3.php
bash tests/e2e-phase4.sh && php tests/cleanup-phase4.php
bash tests/e2e-phase5.sh && php tests/cleanup-phase5.php
bash tests/e2e-phase6.sh && php tests/cleanup-phase6.php
php  tests/fixtures-security.php
bash tests/e2e-security.sh && php tests/cleanup-security.php
```

The suites need the development server running (`start.bat`, or
`php -S localhost:8787 server.php`) and a demo database. They must never be pointed at
production: they create, approve and pay real records.
