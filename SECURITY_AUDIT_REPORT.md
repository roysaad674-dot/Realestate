# EstateOS — Security Audit Report

**Date:** 13 November 2026
**Target:** EstateOS Real Estate Developer ERP, local instance at `http://localhost:8787`
**Scope:** this application, its database and its local environment only. No third-party
service, no external host and no production system was touched.
**Method:** authorized grey-box testing — full source access, plus live attacks over HTTP
with real sessions against a demo database, using synthetic accounts and synthetic records.
**Test harness:** `tests/e2e-security.sh` (129 checks) with `tests/fixtures-security.php`
and `tests/cleanup-security.php`. Every check is repeatable and leaves the database as it
found it.

---

## 1. Verdict

**No critical or high-severity vulnerability was found.**

Five low-severity or informational findings are recorded below. Three of them are hardening
recommendations rather than exploitable defects; none allowed data to cross a tenant
boundary, escalate a privilege, or alter a financial record.

This is not a claim that the application is secure. It is a statement of what was tested,
what was attempted, and what the attempts produced. Section 5 lists what remains untested.

---

## 2. What was attacked, and what happened

Each line below was an actual request sent to the running application, not a code reading.
All 129 checks pass; the ones that matter most are summarised here.

### 2.1 Tenant isolation — the critical control

A second organization (`SEC-B`, "Second Tenant Developers") was created with its own owner
holding **every permission inside their own tenant**. From that account, the first
organization's records were attacked directly by id.

| Attempt | Result |
|---|---|
| Read A's project, unit, booking, customer, lead, employee, expense, vendor, payroll run by id | **404 on every one** |
| Edit A's customer, project, unit (`PUT`) | **404** — A's records unchanged, verified in the database afterwards |
| Delete A's lead, A's project (`DELETE`) | **404** |
| Switch the session into A's organization (`POST /context/organization`) | **Refused**, "You do not have access to that organization" |
| Search for A's customer by name (`/search`, `/api/search`) | **No records returned** |
| Export employees and customers as B | **CSV contained none of A's records** |
| Dashboard statistics API as B | **No record of A's** |
| Download A's document by id | **Refused** |

Isolation is enforced in the backend: `Controller::findOrFail()` scopes every lookup by
`organization_id` as well as id, and `Auth::switchOrganization()` verifies membership before
changing the session. The frontend hiding records is not what stops this, which is the point.

### 2.2 Authentication and session handling

| Attempt | Result |
|---|---|
| Wrong password | Refused; the message does not reveal whether the email exists |
| Six wrong passwords | **Account locked for 15 minutes** on the sixth, exactly as configured |
| Correct password while locked | **Refused**, with the remaining wait stated |
| Session id before vs after sign-in | **Changed** — no session fixation |
| Session cookie flags | `HttpOnly` ✓, `SameSite=Lax` ✓, `Secure` conditional (see SEC-003) |
| Protected page after sign-out | Redirected to login; the session no longer opens anything |
| Password hash in any response | **Never present** |

### 2.3 Authorization — role boundaries

Signed in as a Sales Executive, the least privileged staff role:

finance statements · accounting · payroll · the employee list · the audit trail · roles and
permissions · approving a booking · creating an employee · building payroll · exporting
employees — **every one refused by the server**, not merely hidden in the interface.

### 2.4 Mass assignment

An employee was created with `organization_id`, `id`, `employee_no`, `status` and
`created_by` all supplied by the attacker in the payload. The record was created with the
**server's** id, the **server's** organization, the **server's** allotted number and the
**server's** status. A profile update carrying `is_super_admin=1` and a `role_id` did not
grant either.

### 2.5 Injection and cross-site scripting

| Attempt | Result |
|---|---|
| `' OR '1'='1`, `'; DROP TABLE users; --`, `1' UNION SELECT NULL,NULL--`, URL-encoded variants, in search and filters | Treated as text; no 500, no leak; `users` intact |
| `sort=name);DROP TABLE users;--`, `dir=asc,(SELECT 1)` | Fell back to the whitelist; no injection |
| Stored XSS: a lead whose surname is `<script>alert(1)</script>` | Stored as data, rendered **escaped** (`&lt;script&gt;`); the tag is not live in the HTML |
| Path traversal on a document download, encoded and plain | Refused |

Every query in the application runs through prepared statements; sort columns come from a
whitelist (`Controller::sorting()`) rather than from the request.

### 2.6 CSRF, headers and information exposure

POST with no token and POST with a forged token are both refused and write nothing.
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and
`Content-Security-Policy` are all present; `X-Powered-By` is suppressed.
`/.env`, `/storage/...`, `/app/...` and `/config/...` are all 404 over HTTP — the document
root is `public/` and nothing else is reachable. A 404 page leaks no stack trace and no
filesystem path; an invalid UUID is a clean 404, not a database error.

### 2.7 Customer portal isolation

Signed in as one buyer, another buyer's receipt, service request and booking were all
unreachable, and no other buyer's name appeared on any page. A portal session cannot open
the staff application at all. The portal has its own identity (`PortalAuth`), its own
session keys and its own lockout; it is never granted a place in the staff ACL.

### 2.8 Business logic

| Attempt | Result |
|---|---|
| Book a unit that is already sold | Refused; the unit still has exactly one live booking |
| Pay a payroll run that is already paid | Refused; still exactly one payment entry in the ledger |
| Edit the audit trail through the application | No route exists that can |

### 2.9 Financial integrity

Checked across the whole database, not on a sample:

every posted journal balances · the books as a whole balance to 0.00 · billed equals paid
plus waived plus outstanding on every installment · no installment is paid more than it was
billed · no negative amounts in any schedule · no booking has received more than it was
billed · no unit is sold to two live bookings · no store holds negative stock · every
payslip's net equals gross less deductions.

---

## 3. Findings

### SEC-001 — Content Security Policy permits inline script

| | |
|---|---|
| **Severity** | Low |
| **CVSS 3.1** | 3.1 (AV:N/AC:H/PR:L/UI:R/S:U/C:L/I:L/A:N) |
| **OWASP** | A05:2021 Security Misconfiguration · ASVS 14.4.3 |
| **Affected** | `public/index.php`, the CSP header |

**Description.** The policy is `script-src 'self' 'unsafe-inline'`. `'unsafe-inline'`
disables the part of CSP that would stop an injected `<script>` from running, so CSP
provides no second line of defence if an output-encoding bug is ever introduced.

**Evidence.** The header as served:
`Content-Security-Policy: default-src 'self'; … script-src 'self' 'unsafe-inline'; …`
Five view files contain inline `<script>` blocks, and no nonce mechanism exists.

**Why it is Low and not Medium.** No working XSS was found — output encoding is applied
consistently through `e()`, and the stored-XSS attempt rendered escaped. This is a missing
layer of defence, not an open door.

**Remediation.** Generate a per-request nonce, emit it on each inline block and replace
`'unsafe-inline'` with `'nonce-…'`; or move the five inline blocks into
`public/assets/js/`. The second is simpler here, since there are only five.

**Status.** Reported, not fixed — it touches five views and the header, and changing CSP
without being able to test every page in a browser risks breaking behaviour silently.

---

### SEC-002 — No rate limiting beyond per-account lockout

| | |
|---|---|
| **Severity** | Low |
| **CVSS 3.1** | 3.7 (AV:N/AC:H/PR:N/UI:N/S:U/C:N/I:N/A:L) |
| **OWASP** | A07:2021 Identification and Authentication Failures · API4:2023 Unrestricted Resource Consumption |
| **Affected** | `app/Core/Auth.php`, the whole request pipeline |

**Description.** Failed sign-ins lock *an account* after six attempts, which stops
brute-forcing one password. Nothing limits requests by IP or by session, so an attacker can
try one password against many accounts (password spraying), and no endpoint is throttled
against automated abuse.

**Evidence.** No throttle exists anywhere in `app/` — confirmed by search. Lockout is
applied per user row in `Auth::attempt()`.

**Remediation.** Add an IP-based limit on `/login`, `/portal/login` and
`/forgot-password` — a counter table keyed by IP and a short window would fit this codebase
without a dependency. A general per-session request cap is worth considering for exports.

**Status.** Reported, not fixed — it is a new control rather than a bug, and where the limit
belongs (application, or the web server in front of it) is a deployment decision.

---

### SEC-003 — Session cookie `Secure` flag depends on `$_SERVER['HTTPS']`

| | |
|---|---|
| **Severity** | Low |
| **CVSS 3.1** | 4.8 (AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N) — only when deployed behind a TLS-terminating proxy |
| **OWASP** | A02:2021 Cryptographic Failures · ASVS 3.4.1 |
| **Affected** | `app/Core/App.php`, `session_set_cookie_params()` |

**Description.** `'secure' => (($_SERVER['HTTPS'] ?? '') === 'on')`. This is correct on a
server terminating TLS itself. Behind a reverse proxy or load balancer that terminates TLS
and forwards plain HTTP, `$_SERVER['HTTPS']` is **not** set, so the session cookie would be
issued without `Secure` and could then be sent over an unencrypted connection.

**Evidence.** The code, and the local instance serving over HTTP with the flag absent — the
expected behaviour locally, but the same code path a proxy would take.

**Remediation.** Honour `X-Forwarded-Proto` from trusted proxies only, or add an explicit
`SESSION_SECURE=true` setting for production and default it on when `APP_ENV=production`.

**Status.** Reported, not fixed — the right answer depends on the deployment, and guessing
could make the cookie unusable on a plain-HTTP intranet install.

---

### SEC-004 — No third-party runtime dependencies (informational)

**Severity:** Informational — this is a **strength**, recorded because an audit should say
so. The application has no Composer and no npm dependencies: no framework, no ORM, no
template engine, no JavaScript libraries. The consequence is that the dependency-CVE attack
surface, which is where most reported vulnerabilities in comparable applications come from,
is zero. The cost is that everything — the router, the ACL, the SMTP client — is the
project's own code and carries its own risk, which is why the suites exercise it directly.

---

### SEC-005 — No automated SAST, DAST or dependency scanning in this environment (informational)

**Severity:** Informational

OWASP ZAP, Semgrep, Snyk and similar tools are not available here and cannot be installed:
there is no package manager in this environment by design. The audit was therefore manual
and test-driven — source review plus 129 live attack checks. A production programme should
add an external DAST run against a staging instance and a static analyser in CI; see
`PRODUCTION_READINESS_REPORT.md`.

---

## 4. Findings that were investigated and dismissed

Three apparent failures in the first run of the suite turned out to be defects **in the
tests**, not in the application. They are recorded because a report that only lists
confirmed problems hides how much of the work was ruling things out.

| Apparent finding | Investigation | Conclusion |
|---|---|---|
| "Search leaks another tenant's customer name" | The page was fetched and read: the name appeared only in `Results for "…"`, the search box value, and `No records match "…"`. No record was returned. | **False positive** — the probe searched the page for a string the page echoes back. Assertion changed to require "No records match". |
| "No account lockout — seven wrong passwords accepted" | Reproduced by hand: the account locked on the sixth attempt for fifteen minutes, and the correct password was then refused. The suite's own `CSRF_FROM` had leaked from the previous section, so its POSTs were rejected as CSRF failures and never reached the login code. | **False positive** — the probe was never signing in. Rewritten to read the lock state from the database. |
| "Cross-tenant edit returned 405, not a refusal" | The application uses real `PUT`/`DELETE` verbs for updates; the probe sent `POST`. | **Probe error** — no write ever occurred. Re-tested with method spoofing: still refused, 404. |

---

## 5. What was not tested

Stated plainly, because an audit that implies full coverage is worse than one that admits
its edges.

- **No external DAST sweep.** No ZAP or Burp crawl; the testing was targeted at controls
  that were reasoned about, so an unreasoned-about endpoint could have been missed.
- **No browser-level testing.** No real browser was driven, so DOM-based XSS, clickjacking
  behaviour in practice, and client-side storage of sensitive data were not examined.
- **File upload was tested only for access control**, not for content: no malicious file,
  polyglot, zip bomb or SVG payload was uploaded, and MIME sniffing was not exercised.
- **No load or denial-of-service testing**, by choice — it is destructive by nature.
- **No infrastructure review.** The local XAMPP instance is not the production environment;
  TLS configuration, file permissions, database network exposure and backup security must be
  audited where the application will actually run.
- **No review of the staff password-reset email path end to end** — the token logic was read
  but a live reset was not driven.
- **Concurrency was not attacked.** Race conditions on booking a unit and on recording a
  payment are guarded in code by transactions and `FOR UPDATE`, and Phase 1's suite proves
  three concurrent holds yield one, but no parallel attack was run here.

---

## 6. Reproducing this audit

```bash
php  database/migrate.php            # schema
php  database/seed.php --demo        # synthetic data
php  tests/fixtures-security.php     # the second tenant
bash tests/e2e-security.sh           # 129 checks
php  tests/cleanup-security.php      # restores everything it touched
```

The suite restores the buyer password it borrows, removes the probe records it writes, and
clears any lock counter it set. The second tenant is kept between runs so its ids stay
stable; `php tests/cleanup-security.php --drop-tenant` removes it.
