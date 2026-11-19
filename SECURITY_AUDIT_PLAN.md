# Security Audit Plan

The plan and its results are in **[SECURITY_AUDIT_REPORT.md](SECURITY_AUDIT_REPORT.md)** —
the plan was executed in the same pass, so it is recorded there with what each test actually
produced rather than as a separate list of intentions.

**Scope.** This application, its database and its local environment only. Synthetic accounts
and synthetic records throughout. Nothing destructive, no third-party service, no system
outside the authorized target.

**Method.** Authorized grey-box: full source access, plus live attacks over HTTP with real
sessions, driven by `tests/e2e-security.sh` (129 repeatable checks).

**Areas covered**, each with its results in the report:

| § | Area |
|---|---|
| 2.1 | Tenant isolation — a second organization attacking the first by record id |
| 2.2 | Authentication, lockout, session fixation, cookie flags |
| 2.3 | Authorization — role boundaries, BOLA, BFLA, IDOR |
| 2.4 | Mass assignment |
| 2.5 | SQL injection, stored XSS, path traversal |
| 2.6 | CSRF, security headers, information exposure |
| 2.7 | Customer portal isolation, one buyer against another |
| 2.8 | Business-logic abuse — double booking, double payment, audit tampering |
| 2.9 | Financial integrity across the whole database |

**Findings:** none critical, none high. Three Low, two Informational — report §3.

**Explicitly out of scope / not done:** external DAST, browser-level testing, file-content
upload attacks, load and denial-of-service testing, infrastructure review, concurrency
attacks. Listed with reasons in report §5.

**Reproduce:**

```bash
php  tests/fixtures-security.php
bash tests/e2e-security.sh
php  tests/cleanup-security.php
```
