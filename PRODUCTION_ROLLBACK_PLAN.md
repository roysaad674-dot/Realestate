# ESTATEOS — PRODUCTION ROLLBACK PLAN & EMERGENCY PROCEDURES

**Document Version:** 1.0.0  
**Owner:** DevOps Lead & Principal Architect  
**Classification:** Operational Incident Response Standard  

---

## 1. Rollback Authority & Triggers

A production rollback is immediately authorized when any of the following critical triggers occurs during or within 2 hours of a deployment:

| Code | Trigger Condition | Threshold Metric |
|:---:|:---|:---|
| **TRG-01** | Elevated HTTP 5xx Server Error Rate | > 1.0% of all requests failing for > 3 minutes |
| **TRG-02** | Failed Database Schema Migration | Any migration script halts with an unhandled SQL error |
| **TRG-03** | Financial or Inventory Invariant Violation | Any mismatch detected in GL double-entry or unit inventory |
| **TRG-04** | Security Boundary Failure | Any cross-tenant data leakage or authentication bypass |
| **TRG-05** | Extreme Latency Degradation | P95 latency > 2,000 ms on core endpoints for > 5 minutes |

---

## 2. Emergency Rollback Playbook (Step-by-Step)

```
[Incident Triggered] ──> 1. Enable Maintenance Mode ──> 2. Stop Background Queues
                                                                  │
                                                                  ▼
[Post-Rollback Checks] <── 5. Disable Maintenance Mode <── 4. Revert DB / Code
```

### Step 1: Enable Maintenance Mode
Instantly prevent users from submitting partial transactions during rollback:
```bash
# Touch maintenance flag file checked by public/index.php
touch /var/www/estateos/storage/framework/down
```
*Note: Incoming HTTP requests will immediately receive a friendly 503 "System Maintenance Underway" page.*

### Step 2: Stop Asynchronous Queue Processing
Prevent background jobs from executing during database rollback:
```bash
supervisorctl stop estateos-worker:*
```

### Step 3: Revert Application Codebase
Roll back the application repository to the previous stable release tag:
```bash
cd /var/www/estateos
# Checkout previous known good commit or tag
git checkout tags/v1.0.0-phase1-stable
# Clear any transient caches or compiled templates
rm -rf storage/cache/*
```

### Step 4: Revert Database Schema & Data State
If the rollback involves failed database migrations or inconsistent records:

1. **Terminate active database connections:**
   ```sql
   SELECT concat('KILL ',id,';') FROM information_schema.processlist WHERE user='estateos_app';
   ```
2. **Restore pre-deployment snapshot:**
   ```bash
   gunzip < /var/backups/estateos/pre_deployment_snapshot_LATEST.sql.gz | mysql -u estateos_app -p estateos_prod
   ```
3. **Verify restored schema:**
   ```sql
   SELECT count(*) FROM information_schema.tables WHERE table_schema = 'estateos_prod';
   ```

### Step 5: Restart Daemons & Disable Maintenance Mode
1. **Restart PHP-FPM and Nginx:**
   ```bash
   systemctl restart php8.2-fpm
   systemctl reload nginx
   ```
2. **Restart Queue Worker:**
   ```bash
   supervisorctl start estateos-worker:*
   ```
3. **Disable Maintenance Mode:**
   ```bash
   rm -f /var/www/estateos/storage/framework/down
   ```

---

## 3. Post-Rollback Verification Checklist

- [ ] Execute smoke test: `curl -I https://erp.estateos.com/login` returns HTTP 200 OK.
- [ ] Verify database connectivity and execute invariant check:
  ```bash
  php /var/www/estateos/tests/test-qa-demo-workflows-and-invariants.php
  ```
- [ ] Inspect queue worker logs: `tail -n 100 /var/www/estateos/storage/logs/queue-worker.log`.
- [ ] Verify customer portal: `curl -I https://erp.estateos.com/portal/login` returns HTTP 200 OK.
- [ ] Dispatch stakeholder incident notification confirming rollback completion.

---

## 4. Post-Mortem & Incident Review Protocol

Within 24 hours of any production rollback:
1. Preserve error logs from `/var/www/estateos/storage/logs/` and `/var/log/nginx/`.
2. Replicate the failure in a staging environment to isolate root cause.
3. Author a Post-Mortem Incident Report detailing timeline, root cause, resolution, and preventative unit tests before scheduling any subsequent deployment attempt.

