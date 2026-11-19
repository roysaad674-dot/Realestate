# ESTATEOS — FINAL ENVIRONMENT AUDIT

**Audit Date:** November 14, 2026  
**Auditor:** Principal Software Architect & DevOps Lead  
**Audit Target:** EstateOS Enterprise Real Estate ERP  
**Target Environment:** Local Verification / Pre-Production Staging  

---

## 1. Executive Summary

This environment audit evaluates the runtime architecture, host environment, runtime configurations, process trees, port bindings, security variables, and production readiness of the EstateOS deployment. The current runtime operates in a local verification environment using Windows 11 with XAMPP MariaDB and PHP 8.2+ alongside Next.js 14.

---

## 2. Infrastructure & Runtime Topology

| Component | Audit Specification | Live Version / Status | Production Compliance |
|:---|:---|:---|:---|
| **Operating System** | Windows 11 Pro 64-bit | Microsoft Windows NT 10.0 | Dev/Staging Host (Linux recommended for Prod) |
| **PHP Runtime** | PHP 8.2+ with PDO, OpenSSL, BCMath, cURL, MBString | `C:\xampp\php\php.exe` (v8.2.12) | Compliant |
| **Web Server (Backend)** | Built-in PHP CLI Web Server / Nginx | PHP Built-in Server on `0.0.0.0:8787` | Dev compliant; Nginx/PHP-FPM required for Prod |
| **Frontend Server** | Node.js / Next.js Production Build | Next.js 14.2+ on `0.0.0.0:3000` | Compliant |
| **Database Server** | MariaDB 10.4+ / MySQL 8.0+ | MariaDB 10.4.32 on `127.0.0.1:3306` | Compliant |
| **Queue Worker Daemon** | Background CLI daemon for async jobs | `bin/queue-worker.php` | Compliant |
| **Storage Subsystem** | Local file storage with mime/size validation | `storage/uploads`, `storage/logs` | Compliant |

---

## 3. Active Process & Port Bindings

```
[Port 8787] -> PHP Dev Server (PID Background Task 2167)
  Root: c:\Users\ROY\Desktop\Realestate erp\public
  Router: server.php
  Status: HTTP 200 OK across public & internal endpoints

[Port 3000] -> Next.js Standalone/Production Server (PID Background Task 2169)
  Root: c:\Users\ROY\Desktop\Realestate Antegravity\estateos
  Engine: npx next start -p 3000
  Status: HTTP 200 OK

[Port 3306] -> MariaDB Engine (XAMPP Daemon)
  Database: `estateos`
  Tables: 119 active enterprise InnoDB tables
  Status: Latency < 1.0 ms
```

---

## 4. Environment Variables & Secret Configuration Audit

The audit inspected `config/config.php`, `.env`, and `.env.example`.

| Variable | Current Environment Value | Production Requirement | Audit Finding |
|:---|:---|:---|:---|
| `APP_ENV` | `local` | `production` | Expected for current test cycle; MUST set to `production` at launch |
| `APP_DEBUG` | `true` | `false` | Must be strictly `false` in production to prevent stack trace leakage |
| `APP_URL` | `http://localhost:8787` | `https://erp.estateos.com` | Requires FQDN with HTTPS termination |
| `APP_TIMEZONE`| `Asia/Kolkata` | `Asia/Kolkata` | Verified correct for Indian Real Estate RERA operations |
| `DB_HOST` | `127.0.0.1` | Managed VPC IP / Socket | Verified secure local loopback |
| `DB_PORT` | `3306` | `3306` | Standard MariaDB port |
| `DB_DATABASE` | `estateos` | `estateos_prod` | Verified isolated |
| `DB_USERNAME` | `root` | Dedicated non-root user (e.g. `estateos_app`) | Must create dedicated DB user with least privilege |
| `DB_PASSWORD` | *(empty on local XAMPP)* | Strong random secret (32+ chars) | Mandatory for production launch |
| `SESSION_LIFETIME` | `7200` (2 hours) | `7200` | Compliant |
| `SESSION_ABSOLUTE` | `43200` (12 hours) | `43200` | Compliant |
| `MAX_LOGIN_ATTEMPTS` | `6` | `6` | Compliant with brute-force prevention |
| `LOCKOUT_MINUTES` | `15` | `15` | Compliant |
| `MAIL_DRIVER` | `log` | `smtp` / SendGrid / SES | `log` driver prevents accidental emails during testing |

---

## 5. Security & Boundary Isolation Audit

1. **Synthetic Data Sandbox:**  
   All tests and demo evaluations run exclusively within `ESTATEOS QA DEMO ORGANIZATION` (`0dea4432-333b-4bab-8780-47f149269663`) or the development baseline tenant. Zero real customers or actual email/SMS gateways were contacted.

2. **Mail & Communication Dispatch:**  
   `MAIL_DRIVER=log` captures all outbound notifications to `storage/logs/mail.log`, preventing real external mail transmission. WhatsApp notifications are queued in `whatsapp_messages` without live gateway dispatch.

3. **HTTP Security Headers:**  
   Audited in `public/index.php`. Enforces:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Content-Security-Policy: default-src 'self' ...`
   - Cookie flags: `HttpOnly=true`, `SameSite=Lax`, `Secure` when HTTPS enabled.

---

## 6. Pre-Production Environment Gaps & Action Items

| Item | Current State | Production Launch Requirement | Status |
|:---|:---|:---|:---|
| SSL / TLS | HTTP on port 8787 | Reverse proxy HTTPS termination with Let's Encrypt / Cloudflare | PENDING PROD DEPLOY |
| Web Server | Built-in single-threaded server | Nginx 1.24+ with PHP 8.2-FPM worker pool (pm.max_children = 50) | PENDING PROD DEPLOY |
| DB Credentials | `root` with empty password | Dedicated `estateos` user with restricted GRANTs and 32-char password | PENDING PROD DEPLOY |
| Process Supervisor | PowerShell background tasks | Systemd service / Supervisord managing queue-worker and Next.js | PENDING PROD DEPLOY |

