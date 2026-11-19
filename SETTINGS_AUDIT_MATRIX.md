# EstateOS — Settings Audit Matrix

**Produced:** 14 November 2026 · **Environment:** local development
(`http://localhost:5000`, MariaDB `estateos`) — **not production.**

The brief's rule is applied literally: *"If a setting saves but does not produce the
expected behaviour, mark it as FAILED."* Saving is not evidence. Every row below says what
the code actually reads, with a `file:line`.

---

> ## Update — 14 November, later the same day: **all five have since been fixed.**
>
> This audit found five inert settings. Another process working in this repository then
> wired every one of them up, and the fix has been **independently re-verified live** by the
> same test that proved the failure:
>
> ```
> setting value: 96
> hold POST -> 302
> hours granted: 96      ← was 72 before the fix
> ```
>
> Current state of the five, by grep across `app/` (was zero references each):
>
> | Setting | Now read at |
> |---|---|
> | `hold_duration_hours` | `UnitController.php:457` — `$defaultHours` |
> | `require_clearance_for_possession` | `HandoverService.php:132` — gates `$duesOk` |
> | `document_expiry_warning_days` | `DashboardController.php:58` — `SettingController::value(...)` |
> | `broker_commission_pc` | 3 call sites |
> | `commission_trigger` | 1 call site |
>
> **The FAILED rows below are kept as the record of what was found**, because the evidence
> is what justified the fix. They describe the state at the time of the audit, not now.
> Section 4's `file:line` references are therefore historical.
>
> Still outstanding from this audit: rows 6–10 remain **not live-tested**, and the gaps in
> §5 are unchanged.

---

## 1. Headline *(as found)*

**5 of the 12 settings in EstateOS are inert.** They render, validate, save, persist across
a refresh and a re-login — and change nothing. Each is read by no code at all outside the
settings screen that writes it.

| | Count |
|---|---|
| Settings declared (`SettingController::DEFAULTS`) | 12 |
| Read by application code | **7** |
| **Read by nothing — FAILED** | **5** |

This was established two ways. First, `SettingController::value()` at
`SettingController.php:226` is the **only** read path in the codebase — nothing else
queries `organization_settings` at all. Second, each of the five was grepped by key name
across `app/` and `database/`: **zero references** outside the file that defines them.

## 2. Where settings live

| | |
|---|---|
| Storage | `organization_settings` (`organization_id`, `setting_key`, `setting_value`) |
| Definition | `SettingController::DEFAULTS` — label, type, default, help |
| Read path | `SettingController::value(key, orgId)` — `SettingController.php:226` |
| Screens | `/settings` (the 12 below) · `/settings/numbering` (sequences) |
| Scope | Per organisation. 11 of 12 keys stored for the demo org; `commission_on_cancelled` falls through to its default |

## 3. The matrix

Status vocabulary from the brief: Not Tested · Passed · Failed · Partially Working ·
Not Applicable · Blocked.

| # | Setting | Stored | Saves | Persists | Read by code | Actual effect | Status |
|---|---|---|---|---|---|---|---|
| 1 | `hold_duration_hours` | 96 | Yes | Yes | **No** | **None — app uses 72** | **FAILED** |
| 2 | `broker_commission_pc` | 2.5 | Yes | Yes | **No** | **None** | **FAILED** |
| 3 | `commission_trigger` | agreement | Yes | Yes | **No** | **None** | **FAILED** |
| 4 | `require_clearance_for_possession` | 0 (off) | Yes | Yes | **No** | **None — gate always on** | **FAILED** |
| 5 | `document_expiry_warning_days` | 45 | Yes | Yes | **No** | **None — dashboard uses 60** | **FAILED** |
| 6 | `payment_grace_days` | 10 | Yes | Yes | Yes | Read when scheduling | Not Tested (live) |
| 7 | `late_fee_pc` | 15 | Yes | Yes | Yes | Read when scheduling | Not Tested (live) |
| 8 | `default_tax_pc` | 5 | Yes | Yes | Yes | Pre-fills unit generation | Not Tested (live) |
| 9 | `commission_on_cancelled` | *(default 0)* | Yes | Yes | Yes | Read on cancellation | Not Tested (live) |
| 10 | `cancellation_charge_pc` | 10 | Yes | Yes | Yes | Read on cancellation | Not Tested (live) |
| 11 | `require_kyc_before_booking` | 1 | Yes | Yes | Yes | Blocks booking without KYC | **Passed** — covered by `e2e-phase2.sh` |
| 12 | `max_discount_pc_without_approval` | 3 | Yes | Yes | Yes | Forces discount approval | **Passed** — covered by `e2e-phase2.sh` |

Rows 6–10 are read by code, so they are not inert — but the brief requires the *effect* to
be demonstrated, and that has not been done yet. They are honestly **Not Tested**, not
Passed.

## 4. The five failures, with evidence

### SET-001 — `hold_duration_hours` does nothing · **proven live**

The only failure confirmed end-to-end through the running application rather than by
reading code.

`UnitController.php:456` reads a **config file**, not the organisation setting:

```php
$hours = (int) ($data['hold_hours'] ?: App::config('defaults.hold_hours', 72));
```

`config/app.php:122` sets `'hold_hours' => 72`. The organisation's stored setting is **96**.

**Live test.** Signed in as the demo owner, placed a hold on an available unit through
`POST /units/{id}/hold` with **no** `hold_hours` supplied, so the organisation default had
to decide it:

```
hold POST -> 302
hours_granted = 72        ← the setting says 96
```

The hold was released afterwards and the unit returned to `available`.

**Impact:** an organisation that lengthens or shortens its hold window is ignored, and units
release on a schedule nobody chose. Worse, the screen reports success.

### SET-002 — `broker_commission_pc` does nothing

`CommissionService.php:116` takes the rate from the broker row:

```sql
SELECT commission_pc FROM brokers WHERE id = :b
```

The organisation-level default is never consulted, not even as a starting value when a new
broker is created — which is exactly what its help text promises (*"Starting point for
channel partner agreements"*).

### SET-003 — `commission_trigger` does nothing

`CommissionService.php:405` switches on `$rule['payout_trigger']` from the
`commission_rules` table. The organisation setting — stored as `agreement` — never
participates. Two sources of truth exist for the same business rule, and the one presented
to the administrator is the dead one.

### SET-004 — `require_clearance_for_possession` does nothing · **the serious one**

`HandoverService.php:128` hard-codes the gate:

```php
$duesOk = $outstanding <= 0.009;
```

There is no reference to the setting anywhere. The gate is therefore **always enforced**.

The demo organisation has this stored as **`0`** — meaning *do not require clearance*. The
administrator has explicitly turned the requirement off, the screen shows it off, and the
system enforces it anyway.

This is the most consequential of the five, because it fails *closed* rather than open: the
business cannot hand over a unit it has decided to hand over, and nothing on screen explains
why. (The gate itself is well built — see `MODULE_INTEGRATION_MAP.md` §4.15 — it is only its
configurability that is missing.)

### SET-005 — `document_expiry_warning_days` does nothing

`DashboardController.php:57` passes a literal:

```php
'expiringDocs' => $metrics->expiringDocuments(60, 5),
```

`MetricsService::expiringDocuments(int $days = 60, …)` also defaults to 60. The stored
setting is **45**, so documents are flagged on a 60-day horizon regardless.

## 5. What has not been covered yet

Stated plainly, because the brief forbids claiming completeness:

- **Rows 6–10 are not live-tested.** Code reads them; the behavioural proof is outstanding.
- **`/settings/numbering`** (sequence prefixes, padding, next number) is not yet audited.
- **No per-role test.** Whether a non-administrator can reach `POST /settings` has not been
  probed. The route carries `settings.edit`, but that is a declaration, not a result.
- **No cross-tenant test** on settings — whether organisation A's values can be written
  while signed into B.
- **There is no UI for** application name, branding, logo, favicon, theme, language,
  timezone, date format, number format, currency, session timeout, password policy, file
  upload limits, or backup configuration. The brief asks for these to be tested; they do not
  exist to test. They are **Not Applicable** rather than failing, but the gap is worth
  recording — several are assumed present by the brief.

## 6. Recommendation

The five failures are small, isolated fixes — each is a single call site reading a literal
where it should read `SettingController::value()`. None requires schema change.

Suggested order, by harm:

1. **SET-004** — a business decision is being silently overruled.
2. **SET-001** — proven to mis-set inventory holds.
3. **SET-005**, **SET-003**, **SET-002** — misleading rather than harmful.

Each needs a regression test that fails first: change the setting, exercise the behaviour,
assert the new value is honoured. Without that, these will rot back.
