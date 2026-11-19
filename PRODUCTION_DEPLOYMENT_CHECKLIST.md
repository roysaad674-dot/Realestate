# ESTATEOS — PRODUCTION DEPLOYMENT CHECKLIST

**Document Version:** 1.0.0  
**Target Architecture:** Linux (Ubuntu 22.04 LTS / Debian 12) + Nginx + PHP 8.2-FPM + MariaDB 10.11+  
**Audience:** DevOps Engineers, System Administrators, Lead Developers  

---

## 1. Pre-Deployment Infrastructure Verification

- [ ] **Target Server Provisioning:**
  - [ ] Minimum 4 vCPU, 8 GB RAM, 100 GB NVMe SSD.
  - [ ] OS: Ubuntu 22.04 LTS / Debian 12 LTS with latest security patches.
  - [ ] Firewall (UFW): Ports 22 (SSH), 80 (HTTP), 443 (HTTPS) open; 3306 restricted strictly to `127.0.0.1` / VPC internal network.
- [ ] **Runtime Dependencies Installed:**
  - [ ] PHP 8.2+ with extensions: `php8.2-fpm`, `php8.2-mysql`, `php8.2-mbstring`, `php8.2-xml`, `php8.2-curl`, `php8.2-bcmath`, `php8.2-zip`, `php8.2-intl`.
  - [ ] Web Server: Nginx 1.24+ with TLS 1.3 module.
  - [ ] Database: MariaDB 10.11+ or MySQL 8.0+ configured with `utf8mb4`.
  - [ ] Process Manager: `supervisor` or `systemd` installed.
  - [ ] Certbot: `certbot python3-certbot-nginx` for automated SSL provisioning.

---

## 2. Environment Configuration & Secret Management

- [ ] **Repository Deployment:**
  - [ ] Clone repository to `/var/www/estateos`.
  - [ ] Set directory permissions:
    ```bash
    chown -R www-data:www-data /var/www/estateos
    chmod -R 755 /var/www/estateos
    chmod -R 775 /var/www/estateos/storage
    ```
- [ ] **Configure Production `.env` File:**
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false` *(MANDATORY: Must NEVER be set to `true` in production)*
  - [ ] `APP_URL=https://erp.estateos.com`
  - [ ] `APP_TIMEZONE=Asia/Kolkata`
  - [ ] `DB_HOST=127.0.0.1`
  - [ ] `DB_DATABASE=estateos_prod`
  - [ ] `DB_USERNAME=estateos_app` *(Dedicated non-root user)*
  - [ ] `DB_PASSWORD=<32_CHARACTER_HIGH_ENTROPY_PASSWORD>`
  - [ ] `MAIL_DRIVER=smtp`
  - [ ] `MAIL_HOST=smtp.sendgrid.net` (or AWS SES)
  - [ ] `MAIL_USERNAME=<SMTP_KEY>`
  - [ ] `MAIL_PASSWORD=<SMTP_SECRET>`
  - [ ] `RAZORPAY_WEBHOOK_SECRET=<PRODUCTION_HMAC_SECRET>`
  - [ ] `STRIPE_WEBHOOK_SECRET=<PRODUCTION_SIGNING_SECRET>`

---

## 3. Database Migration & Initialization

- [ ] **Provision Dedicated Database User:**
  ```sql
  CREATE DATABASE estateos_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'estateos_app'@'localhost' IDENTIFIED BY '<32_CHARACTER_HIGH_ENTROPY_PASSWORD>';
  GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, REFERENCES ON estateos_prod.* TO 'estateos_app'@'localhost';
  FLUSH PRIVILEGES;
  ```
- [ ] **Execute Sequential Schema Migrations:**
  ```bash
  for f in database/migrations/*.sql; do
    echo "Applying $f..."
    mysql -u estateos_app -p"<PASSWORD>" estateos_prod < "$f"
  done
  ```
- [ ] **Verify Migration Count:**
  - [ ] Confirm all 119 tables are created in `estateos_prod`.
  - [ ] Confirm standard chart of accounts is seeded.

---

## 4. Nginx Reverse Proxy & SSL Configuration

- [ ] **Deploy Virtual Host Config (`/etc/nginx/sites-available/estateos`):**
  ```nginx
  server {
      listen 80;
      server_name erp.estateos.com;
      return 301 https://$host$request_uri;
  }

  server {
      listen 443 ssl http2;
      server_name erp.estateos.com;
      root /var/www/estateos/public;
      index index.php;

      ssl_certificate /etc/letsencrypt/live/erp.estateos.com/fullchain.pem;
      ssl_certificate_key /etc/letsencrypt/live/erp.estateos.com/privkey.pem;
      ssl_protocols TLSv1.2 TLSv1.3;
      ssl_ciphers HIGH:!aNULL:!MD5;

      client_max_body_size 25M;

      # Security Headers
      add_header X-Frame-Options "SAMEORIGIN" always;
      add_header X-Content-Type-Options "nosniff" always;
      add_header X-XSS-Protection "0" always;
      add_header Referrer-Policy "strict-origin-when-cross-origin" always;
      add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          include snippets/fastcgi-php.conf;
          fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
          fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
          include fastcgi_params;
      }

      location ~ /\.(?!well-known).* {
          deny all;
      }
  }
  ```
- [ ] **Test & Reload Nginx:**
  ```bash
  nginx -t && systemctl reload nginx
  ```

---

## 5. Background Queue Worker Daemon Setup

- [ ] **Configure Supervisor (`/etc/supervisor/conf.d/estateos-worker.conf`):**
  ```ini
  [program:estateos-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=/usr/bin/php /var/www/estateos/bin/queue-worker.php
  autostart=true
  autorestart=true
  user=www-data
  numprocs=2
  redirect_stderr=true
  stdout_logfile=/var/www/estateos/storage/logs/queue-worker.log
  stopwaitsecs=3600
  ```
- [ ] **Start Queue Supervisor:**
  ```bash
  supervisorctl reread && supervisorctl update && supervisorctl start estateos-worker:*
  ```

---

## 6. Post-Deployment Smoke Testing & Verification

- [ ] Verify `https://erp.estateos.com/login` loads cleanly with HTTP 200 OK.
- [ ] Verify SSL certificate is valid and rated A/A+ on SSLLabs.
- [ ] Verify administrative login with initial Owner credentials.
- [ ] Verify password change on first login.
- [ ] Verify creating a test project, tower, and unit.
- [ ] Verify customer portal login at `https://erp.estateos.com/portal/login`.
- [ ] Confirm queue worker logs show active polling without errors.
- [ ] Verify automated database backup cron job is installed and scheduled.
- [ ] Enable Datadog / Prometheus / CloudWatch application performance monitoring.

