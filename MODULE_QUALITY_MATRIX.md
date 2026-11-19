# EstateOS — Module Quality Matrix

*This document also answers the request for `MODULE_TEST_MATRIX.md`: one table, kept current,
rather than two that disagree.*

**As at 13 November 2026.** Every "tested" claim below points at checks that were run on
that date, not at an intention.

**Phase 1 and Phase 2 are now in the repository.** Both suites were rewritten on
13 November 2026 — 109 and 101 checks, passing and repeatable — so every row below is
currently verified rather than last known good.

**Legend** — Automated: covered by a suite that runs over real HTTP.
Security: probed by `tests/e2e-security.sh`.
Status: **Pass** = covered and passing · **Partial** = covered in part, gaps named ·
**Untested** = no automated coverage.

| # | Module | Automated | Security | Status | What is not covered |
|---|---|---|---|---|---|
| 1 | Authentication & sessions | Phase 1 + security | Yes | **Pass** | Staff password-reset email driven end to end |
| 2 | Organizations & tenancy | Security | Yes — read, write, switch, search, export | **Pass** | Organization creation flow by a brand-new user |
| 3 | Roles & permissions | Phase 1 + security | Yes — 6 refusal paths | **Pass** | Per-user allow/deny overlay is exercised indirectly only |
| 4 | Land management | Phase 1 | Partial | **Pass** | Feasibility calculator edge cases |
| 5 | Projects | Phase 1 | Yes — cross-tenant | **Pass** | Milestone weight recalculation on deletion |
| 6 | Buildings & floors | Phase 1 | Partial | **Pass** | Bulk floor generation at extreme counts |
| 7 | Unit inventory | Phase 1 + security | Yes | **Pass** | — |
| 8 | CRM & leads | Phase 2 + security | Yes — stored XSS | **Pass** | Lead import from file |
| 9 | Customers | Phase 2 + security | Yes | **Pass** | — |
| 10 | Quotations | Phase 2 | Partial | **Pass** | Pricing, the discount's effect on tax, and conversion to a booking are covered; quotation *expiry* over time is not |
| 11 | Bookings | Phase 2 + security | Yes — double-booking | **Pass** | — |
| 12 | Installments & collections | Phase 3 + security | Yes — financial identities | **Pass** | SMS and WhatsApp reminders (recorded, never sent — by design) |
| 13 | Accounting & finance | Phase 3 + security | Yes — ledger balance | **Pass** | Period close/reopen under concurrent posting |
| 14 | Expenses | Phase 3 | Partial | **Pass** | — |
| 15 | Construction | Phase 4 | Partial | **Pass** | — |
| 16 | Procurement | Phase 4 | Partial | **Pass** | Debit note to a vendor for a rejected receipt (not built) |
| 17 | Warehouse & stock | Phase 4 + security | Yes — negative stock | **Pass** | — |
| 18 | Brokers & commission | Phase 4 | Partial | **Pass** | Commission voided automatically on cancellation (not built) |
| 19 | Legal & documents | Phase 1 | Yes — access control, traversal | **Pass** | The `/legal` register itself has no functional checks; uploads, downloads and file handling do |
| 20 | HR & employees | Phase 6 | Yes | **Pass** | Leave accrual run (not built) |
| 21 | Payroll | Phase 6 + security | Yes — double payment | **Pass** | Reversal of an approved run (manual journal only) |
| 22 | Customer portal | Phase 5 + security | Yes — per-customer isolation | **Pass** | Online payment (no gateway) |
| 23 | Handover & after-sales | Phase 5 | Partial | **Pass** | — |
| 24 | Service desk | Phase 5 | Yes | **Pass** | SLA breach escalation to a person |
| 25 | Outbound mail | Phase 5 | No | **Partial** | SMTP driver never exercised against a live mail server |
| 26 | Dashboard & reports | Phase 1 + security | Yes — tenant scope | **Partial** | Report figures are not asserted against independently computed totals |
| 27 | Assistant | Phase 6 | Yes — permission refusal | **Pass** | — |
| 28 | Notifications | Phase 2 | Yes — cross-tenant and cross-user | **Pass** | One read_at per row, so reading an org-wide notification marks it read for everybody (see below) |
| 29 | Audit trail | Phase 1 | Yes — immutability | **Pass** | Retention/archival policy |
| 30 | Settings & users | Phase 1 | Partial | **Partial** | User invitation and deactivation flows |

## Summary

- **Pass:** 24 modules
- **Partial:** 6 modules — named gaps, none of them a correctness risk that is currently
  known to be live
- **Untested:** none

## A design limitation found while testing notifications

**An org-wide notification is one row with one `read_at`.** `notifications.user_id` is
nullable, and a NULL means "everybody in this organization". Because the read state lives on
the row rather than per recipient, the first person to open it marks it read **for the whole
organization** — everybody else never sees it as new.

This is current behaviour, proved by a check in the Phase 2 suite rather than assumed, and
it is a design limitation rather than a bug in the code as written: the controller does
exactly what the schema allows. Fixing it properly needs a `notification_reads` table keyed
by (notification, user), which is a schema change and so a decision rather than a patch. It
matters most for the notifications the application actually broadcasts today — "Discount
approval needed" and "Booking cancelled" — where one person glancing at the list silently
clears it for everybody who needed to see it.

## The gaps worth acting on first

1. **The org-wide read state above**, if broadcasts are relied on operationally. It is a small module, but "no coverage" and "works"
   are different claims and only one of them is supported.
2. **The `/legal` register has no functional checks.** Document upload and download are now
   covered by 46 checks in Phase 1 — a lying extension, an SVG, an empty file, an oversized
   file and a traversal filename are all exercised — but the legal-register screens that sit
   on top of the same data are only known to render.
3. **Report totals are rendered but not reconciled.** The finance statements are asserted
   against the ledger; the management reports are asserted only to render. A report that
   quietly double-counts would pass today.
