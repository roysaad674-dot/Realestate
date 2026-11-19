# EstateOS — Final QA Master Plan

**Produced:** 14 November 2026 · **Status:** Phase One under way.

---

## 1. Environment — established before anything was touched

| Question | Answer |
|---|---|
| Development, staging or production? | **Development.** Local only: `http://localhost:5000`, MariaDB 10.4 on `127.0.0.1:3306`, XAMPP, `APP_DEBUG` on, demo seed data, no remote git. |
| Safe to write? | **Yes** — no production system is reachable from here. |
| Real customer data present? | **No.** All records are seeded demo data (`owner@estateos.test` and similar). |
| External sends? | `MailService` writes to `storage/logs/mail.log`. `whatsapp_messages` rows are queued and nothing dispatches them. **No real message can leave this machine.** |
| Backup | `database/backup.php` exists and `backups/` is populated. |

The brief's safety rules are therefore satisfiable in place. There is no production database
to protect and no real recipient to reach.

**Test data convention:** prefix `QA-DEMO-`, inside a dedicated organisation named
`ESTATEOS QA DEMO ORGANIZATION`. Existing suites already use their own prefixes (`E2E1-`,
`E2E2-`, `SEC-`) with matching cleanup scripts; `QA-DEMO-` deliberately does not collide.

## 2. What already exists — and is not worth repeating

This repository is not starting from zero. Before planning new tests, the existing estate
was measured:

| Asset | Size |
|---|---|
| `tests/run-all.sh` — the quality gate | 13 gates, **1,287 checks**, one command |
| End-to-end suites, phases 1–6 | 1,038 checks over HTTP against a real session |
| Security suite | 144 checks — tenant isolation, authz, injection, portal |
| Unit tests | 105 checks |
| Prior audit | `MODULE_INTEGRATION_MAP.md`, `DATABASE_RELATIONSHIP_MAP.md`, `MODULE_DEPENDENCY_MATRIX.md` — 17 findings |

These suites drive the real HTTP stack — real session cookie, real CSRF token, real
validation, assertions made against the database. They already satisfy large parts of the
brief's Phases Five, Seven and Eight.

**So this plan does not re-test what the gate already covers.** It targets what the gate
does *not*: settings behaviour, UI interaction, demo-data realism, and the reconciliation of
dashboards against source records.

## 3. Scope reality

The brief asks for every form, field, button, modal, dropdown, filter and setting across
**43 controllers, 425 routes, 182 views and 117 tables**, plus demo data for 15 domains,
seven end-to-end workflows, responsive testing at three breakpoints, and nine reports.

That is a programme, not a single pass. It is sequenced below by **evidence value per
hour** — the things most likely to be broken and least likely to be covered already, first.
Each stage ends with a written result, so work is never lost if the programme pauses.

## 4. Sequence

### Stage 1 — Settings · **in progress**

The brief calls this *"one of the highest-priority testing activities"*, and it is the
single biggest gap in the existing gate: **no suite tests a setting's effect.**

Done: all 12 settings inventoried, read paths traced, **5 proven inert**, one of those
demonstrated live through the running application.
See `SETTINGS_AUDIT_MATRIX.md`.

Remaining: live-test the 7 that code does read; audit `/settings/numbering`; test settings
permissions by role; test cross-tenant setting writes.

### Stage 2 — Demo organisation and connected data

A dedicated `ESTATEOS QA DEMO ORGANIZATION`, populated through the application's own forms
where practical, so the data proves the forms work rather than only the tables.

Order matters — each step depends on the last: organisation → users and roles → land →
projects → buildings → floors → units → leads → customers → quotations → bookings →
installments → payments → suppliers → stock → construction → brokers → HR → documents.

Unit statuses will be made to **agree with real bookings** rather than set directly, which
the brief explicitly requires.

### Stage 3 — Module functionality

CRUD, validation, filters, sorting, pagination and permissions per module, with the
dead-control check the brief calls for: *a button that looks clickable but does nothing is a
defect.*

### Stage 4 — Settings impact

Change a setting, create a **new** transaction, verify the effect, restore. This is distinct
from Stage 1, which only asks whether a setting is read at all.

### Stage 5 — End-to-end workflows

The brief's seven. Workflows 1, 2, 3, 5 and 6 are already largely covered by phases 1–6 of
the gate; the work here is **Workflow 7 — dashboard and report reconciliation**, which
nothing currently tests.

### Stage 6 — Data integrity

The gate already asserts trial balance, double-booking, payslip orphans and attendance
duplicates. Added here: the brief's inventory identity (every unit counted exactly once),
the stock identity, and a sweep for orphans across the **118 unenforced reference columns**
recorded in `DATABASE_RELATIONSHIP_MAP.md`.

### Stage 7 — Security

144 checks pass today. Added here: deactivated-account access, approval bypass by direct
POST, unauthorised refund and stock adjustment, and document download authorisation — the
last already known weak (`MODULE_INTEGRATION_MAP.md` INT-008).

### Stage 8 — Responsive UI

Desktop, tablet, mobile in the built-in browser. Playwright is not available and will not be
installed: this project has no package manager by design.

### Stage 9 — Fix, retest, report

TDD per the brief: red, green, refactor. Every fix gets a check that fails without it.

## 5. Reports

| File | State |
|---|---|
| `SETTINGS_AUDIT_MATRIX.md` | **Written** — 12 settings, 5 failures |
| `FINAL_QA_MASTER_PLAN.md` | **This file** |
| `MODULE_TEST_INVENTORY.md` | Stage 3 |
| `DEMO_DATA_PLAN.md` · `DEMO_DATA_REGISTER.md` | Stage 2 |
| `FINAL_TEST_RESULTS.md` · `FINAL_QA_SUMMARY.md` | Last |
| `MODULE_TEST_REPORT.md` · `END_TO_END_WORKFLOW_REPORT.md` | Stages 3 and 5 |
| `DATA_INTEGRITY_REPORT.md` · `SECURITY_TEST_REPORT.md` | Stages 6 and 7 |
| `BUG_FIX_REPORT.md` | Stage 9 |
| `PRODUCTION_READINESS_REPORT.md` | Exists; to be re-decided at the end |

## 6. The blocker

**Another process is editing this codebase while testing runs.**

Between 11:55 and 12:10 today, seven files changed that this session did not touch —
`BookingService`, `CollectionService`, `ConstructionService`, `HandoverService`, `Auth`,
`PortalAuth`, `ApprovalController` — plus a new `tests/test-cross-module-integrations.php`.
One edit of this session's was rejected mid-write because the file had changed underneath
it; on re-reading, the other writer had applied substantially the same fix.

That code arrived broken against the live schema — `document_files` has no `file_path`,
`whatsapp_messages` has no `booking_id` — and because the inserts sat inside the booking
transaction, **every booking creation failed**, taking phases 2, 4 and 5 of the gate with
it. Both writers have since corrected it and phase 2 is back to 125/0, but a full gate run
started at 12:08 was killed before it could finish.

**QA results are only meaningful against a codebase that holds still.** A pass recorded at
12:05 says nothing about the code at 12:10. Before Stage 2 begins, that other process should
be stopped, or this session should stop writing and act purely as the tester — but not both
at once.

## 7. Standing rules for this programme

1. No claim of 100% completion or 100% security.
2. A setting that saves but does not act is **FAILED**, not partial.
3. No test is marked passed from reading code — the application is run.
4. Every bug fix gets a regression check that fails first.
5. Nothing outside the demo scope is deleted, and the demo organisation is not removed
   without explicit authorisation.
6. No real message, payment or external call leaves this machine.
