# EstateOS — System Architecture Map

Built by inspection on 13 November 2026, for the purpose of testing it. Figures are counted
from the repository and the live schema, not estimated.

---

## 1. Shape

| | |
|---|---|
| Language | PHP 8.2, no framework |
| Database | MariaDB / MySQL, **117 tables** |
| Dependencies | **None.** No Composer, no npm, no vendor directory |
| PHP files | 278 (`app/`, `routes/`, `public/`, `database/`) |
| Controllers | 43 · Services 18 · Core 20 · Views 182 |
| Routes | 394 |
| Front controller | `public/index.php` — the only web-reachable entry point |
| Document root | `public/` — `app/`, `config/`, `storage/`, `database/` are not reachable over HTTP (verified) |

Hand-rolled MVC: `app/Core` holds the framework pieces, `app/Services` the business rules,
`app/Http/Controllers` the HTTP layer, `app/Views` PHP templates rendered server-side. There
is no client-side framework and one JavaScript file.

## 2. Request pipeline

```
request
  → App::startSession()          session cookie: HttpOnly, SameSite=Lax, Secure when HTTPS
  → security headers             nosniff, SAMEORIGIN, Referrer-Policy, CSP, X-Powered-By removed
  → Router::match()              394 routes, {param} placeholders, groups
  → auth guard                   Auth::check()  — or PortalAuth::check() for portal routes
  → organization guard           a staff session with no organization is sent to create one
  → CSRF                         every non-GET verb; hash_equals against a per-session token
  → ACL                          Acl::authorize($route['permission'])  — skipped for portal routes
  → controller                   validation → service → database → view
  → response                     HTML, JSON for /api, or a download
```

Exceptions are mapped centrally: `ValidationException` re-fills the form and redirects;
`HttpException` renders an error page carrying the reason, with JSON for API routes.

## 3. Identity: two separate systems

| | Staff | Customer |
|---|---|---|
| Class | `App\Core\Auth` | `App\Core\PortalAuth` |
| Session keys | `user_id`, `organization_id` | `portal_customer_id`, `portal_org_id` |
| Permissions | Role + per-user overlay, via `Acl` | **None** — no place in the staff ACL at all |
| Scoping | Every query by `organization_id` | Every query by the signed-in customer's own id, taken from the session |
| Lockout | 6 attempts, 15 minutes | Same, configured separately |

A portal route is marked `portal` in the router; `public/index.php` then guards it with
`PortalAuth` and **skips the staff ACL entirely**. This is why a portal session cannot reach
the staff application: it never had a staff identity to begin with.

## 4. Authorization model

```
super admin            → everything
explicit user deny     → refused        (deny always wins)
explicit user allow    → granted
role permission        → granted
module.manage          → implies every action in that module
otherwise              → refused
```

112 permission slugs across 29 modules; 14 system roles. Record-level scope exists on top:
`Acl::canAccessProject()` restricts a project manager to their assigned projects, and
`Acl::canSeeFinancials()` hides money columns from roles without financial access.

## 5. Tenant isolation — how it is actually enforced

Three mechanisms, all server-side:

1. **`Controller::findOrFail($table, $id)`** always adds `AND organization_id = :o`. A
   record belonging to another tenant is a 404, not a 403 — it does not exist as far as that
   session is concerned.
2. **Every service query** takes an `$orgId` and binds it. Services default it to
   `Auth::orgId()` rather than accepting it from the request.
3. **`Auth::switchOrganization()`** verifies membership in `organization_users` before
   changing the session, for anybody who is not a super admin.

Tested in `tests/e2e-security.sh` section B against a real second tenant: reads, writes,
deletes, switching, search, exports and the dashboard API.

## 6. Data model: the load-bearing decisions

- **Derived, never stored.** The general ledger comes from `journal_entry_lines`; stock
  balance is `SUM(direction × quantity)` over `stock_movements`; leave taken is summed from
  approved requests; target achievement is counted from bookings and receipts at read time.
  Nothing can drift from its own source because there is no second copy.
- **Immutability with reversal.** A posted journal is never edited; a correction is a mirror
  entry. A paid expense and an approved bill cannot be edited. A salary structure is
  superseded, not overwritten.
- **Idempotency keys** on journals and on outbound mail, so one event cannot post twice or
  email twice.
- **Document numbers** from `number_sequences`, never typed in.
- **Money** `DECIMAL(18,2)`, quantities `DECIMAL(18,3)`. No float arithmetic on money.
- **Timestamps** UTC throughout; `to_local()` only at the edges.
- **Soft deletes** (`deleted_at`) on the business tables; a handful of small tables
  (`holidays`, `attendance`, `payslips`) are hard-delete by design — which is what BUG-001
  turned on.

## 7. Second-pair-of-hands rules

Enforced in services, not in the interface, and each has a test that the *same* person is
refused:

discount approval · requisition approval · work-certificate approval · snag verification ·
leave decision · payroll approval.

## 8. External surface

| Integration | State |
|---|---|
| Outbound email | Three drivers: `log` (default — nothing leaves the machine), `mail`, and `smtp` written directly against the protocol. Every message is a row in `mail_messages` first |
| SMS / WhatsApp | **Not integrated.** Reminders are recorded with their exact text as a worklist; the screen says so rather than implying they were sent |
| Payment gateway | **None.** The portal shows what is owed; it cannot take money |
| File storage | Local disk under `storage/uploads`, served only through an authorizing controller |
| Scheduled work | `database/sweep-holds.php` (expiring unit holds) and `database/send-mail.php` (the outbox), both cron-driven |

## 9. Where the risk concentrates

For a tester, these are the places where a mistake would cost the most:

1. **`Controller::findOrFail()`** — one helper enforcing tenant isolation for most of the
   application. It is correct, and that is load-bearing.
2. **`AccountingService::post()`** — every financial posting in the system goes through it.
   If it ever posted unbalanced, the whole ledger would be wrong.
3. **`Acl::can()`** — one method deciding every permission.
4. **`Database::run()`** — one method preparing every statement. The reason SQL injection
   had nowhere to land.
5. **`PortalController::customer()`** — the single point where a buyer's identity becomes a
   query scope.

All five are exercised directly by the suites, and the first four by the security suite
specifically.
