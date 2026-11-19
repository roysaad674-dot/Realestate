# EstateOS — Unit Test Report

**Date:** 13 November 2026
**Runner:** PHP CLI. There is no PHPUnit — see `TESTING_STRATEGY.md` §1 for why, and what
is used instead.

---

## 1. Results

| Suite | Checks | Result |
|---|---|---|
| `tests/test-unit-app.php` — tests that call the application's own code | **66** | **all passing** |
| `tests/test-unit.php` — pre-existing formula checks | 39 | all passing, but see §3 |
| `tests/test-security.php` — static pass over the source for unsafe patterns | — | passing |

```bash
php tests/test-unit-app.php     # 66 checks
php tests/test-unit.php         # 39 assertions
```

## 2. What `test-unit-app.php` covers

Only the parts of the codebase that are **pure** — no database, no HTTP — which is what
makes them unit-testable at all.

| Area | Checks | Notable |
|---|---|---|
| Money and number formatting | 16 | Indian grouping (lakh/crore), paise, negatives, the `days()` helper added for Phase 6 |
| Output encoding | 3 | `e()` encodes a script tag and encodes quotes, so an HTML attribute cannot be broken out of |
| `Uuid` | 6 | Shape, uniqueness, and that `isValid()` rejects `' OR 1=1 --` — the guard that turns a tampered id into a 404 instead of a query |
| `Validator` | 21 | Every rule family, positive and negative, **including the BUG-E02 regression** |
| HR classification rules | 7 | Which attendance statuses count as paid — the rule payroll is built on |
| Assistant matching | 13 | Real questions reaching the right intent, unknown questions returning nothing, and structural invariants over all 23 intents |

### The regression that matters most

Two checks exist specifically because the bug they describe was real (BUG-E02): the
validator used to decide `max`/`min` by comparing numeric-looking strings as numbers, so a
four-character account code `"2190"` failed `max:20`, and an all-digit password of seven
characters passed `min:8`. Both directions are now asserted:

```php
t('max:20 on a string counts characters, not value (regression, BUG-E02)', …);
t('min:8 on a password counts characters, so all digits do not bypass it', …);
```

## 3. A weakness in the pre-existing `test-unit.php`

Recorded because the brief asks for tests that pass without validating anything to be called
out, and this is one.

`tests/test-unit.php` reports "39 assertions passed", of which **15 are financial
calculations the test computes inline and compares to a hard-coded constant.** For example,
at line 53:

```php
$outstanding = $totalPrice - $paidAmount;              // arithmetic done in the test
unit_assert_near(8750000.00, $outstanding, "Outstanding balance formula: Total - Paid");
```

Nothing in that touches EstateOS. It would pass unchanged if `BookingService` priced units
by a completely different formula, or if the application were deleted. The same applies to
its GST, late-fee, broker-commission, retention, TDS, weighted-average-cost and milestone
assertions.

Its later sections are genuine — they call `e()`, `money()` and the real validators.

**This was not fixed by editing that file,** both because another effort is working in this
repository (see `PRODUCTION_READINESS_REPORT.md` §6) and because deleting someone's tests is
not the right way to raise quality. `test-unit-app.php` was added alongside it, calling the
real code. The financial formulas it covers are, however, genuinely asserted elsewhere: the
phase suites post real bookings, receipts, bills and payrolls and then check the ledger.

## 4. Mutation check

Coverage percentages are not available (no Xdebug), so the tests were checked the way the
brief suggests instead — by introducing a deliberate defect and confirming it is caught.

`days()` was changed to round to zero decimals rather than one, which would make a half-day
of leave disappear from every attendance and payroll screen:

```
mutation applied: days() rounds to 0 decimals
  FAIL days() keeps a half day
  FAIL days() rounds to one decimal
64 passed, 2 failed
```

The mutation was reverted and the file verified byte-identical to its backup; 66 checks pass
again. The tests detect the defect, which is the only evidence that they are worth running.

## 5. What is not unit tested, and why

Most of EstateOS's logic is **not pure**: `HrService`, `PayrollService`,
`AccountingService`, `CollectionService` and the rest read and write the database inside
transactions, and their correctness is about what ends up in the tables. Unit-testing them
would mean mocking the database, and a mocked database cannot catch the two worst bugs found
this week — a foreign key violated by insert ordering, and an accrual posted into the wrong
month. Both were caught by suites that used a real database.

Those services are therefore covered by the HTTP suites and reported in
`INTEGRATION_TEST_REPORT.md`. This is a deliberate split, not a gap being glossed over:

| Kind of code | Tested by |
|---|---|
| Pure helpers, validators, matchers, constants | `test-unit-app.php` |
| Services, controllers, transactions, permissions | the five HTTP suites |
| Cross-tenant and attack behaviour | `e2e-security.sh` |
