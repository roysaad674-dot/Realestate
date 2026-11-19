# ESTATEOS — FINAL SETTINGS VERIFICATION REPORT

**Audit Date:** November 14, 2026  
**Auditor:** Senior ERP Functional Tester & Integration Architect  
**Deliverable:** Pre-Production Audit Report #6 (Settings Verification)  

---

## 1. Scope & Verification Objective

The objective of this verification is to validate that all 12 operational configuration settings in `organization_settings` are actively wired to backend services, calculation routines, approval gateways, and validation hooks, rather than serving as inert database records.

---

## 2. Dynamic Impact Verification Summary

Each setting was tested for both its static configuration and its dynamic behavioral impact when business actions are performed:

### 1. `default_currency` (SET-001)
- **Configured:** `INR`
- **Dynamic Check:** When creating a booking without explicit currency, the system automatically defaults to `INR`. Cashier receipts, customer ledgers, and general ledger journal vouchers record currency as `INR`.
- **Result:** **PASSED**

### 2. `tax_rate_gst` (SET-002)
- **Configured:** `5.00` (%)
- **Dynamic Check:** Calculated across units and quotations. Unit base price of ₹50,00,000 generates tax of ₹2,50,000 (5%), resulting in a final price of ₹52,50,000.
- **Result:** **PASSED**

### 3. `commission_trigger` (SET-003)
- **Configured:** `booking`
- **Dynamic Check:** When a sales booking is confirmed with an attributed broker, `CommissionService::raise()` automatically generates the broker commission voucher immediately upon booking confirmation without awaiting registration.
- **Result:** **PASSED**

### 4. `late_payment_interest_rate` (SET-004)
- **Configured:** `18.00` (% per annum)
- **Dynamic Check:** Overdue installment calculation applies standard commercial rate of 18% p.a. pro-rated daily for the duration of default.
- **Result:** **PASSED**

### 5. `document_expiry_warning_days` (SET-005)
- **Configured:** `45` (days)
- **Dynamic Check:** The Document Vault and AI Assistant query flags documents whose expiration date falls within 45 days.
- **Result:** **PASSED**

### 6. `inventory_hold_hours` (SET-006)
- **Configured:** `48` (hours)
- **Dynamic Check:** Provisional holds automatically set expiration timestamp to `NOW() + 48 HOURS`. Queue worker inspects expired holds to release units.
- **Result:** **PASSED**

### 7. `auto_receipt_sms` (SET-007)
- **Configured:** `true`
- **Dynamic Check:** Cashier receipt generation synchronously queues payment receipt notification records into `whatsapp_messages` and `mail_messages`.
- **Result:** **PASSED**

### 8. `fiscal_year_start` (SET-008)
- **Configured:** `04-01` (April 1st)
- **Dynamic Check:** Accounting periods map from April 1 to March 31, aligning with Indian tax calendar regulations.
- **Result:** **PASSED**

### 9. `snag_grace_period_days` (SET-009)
- **Configured:** `14` (days)
- **Dynamic Check:** Snag items assigned to contractors automatically calculate target resolution date as `inspection_date + 14 days`.
- **Result:** **PASSED**

### 10. `enable_customer_portal` (SET-010)
- **Configured:** `true`
- **Dynamic Check:** Customer portal login endpoints actively authenticate valid customers. When flipped to `false`, portal returns HTTP 403 / maintenance message.
- **Result:** **PASSED**

### 11. `require_kyc_before_booking` (SET-011)
- **Configured:** `true`
- **Dynamic Check:** Attempting to confirm a booking for a customer with `kyc_status != 'verified'` is rejected by `BookingService` with a 422 Unprocessable Entity error.
- **Result:** **PASSED**

### 12. `max_discount_pc_without_approval` (SET-012)
- **Configured:** `5.00` (%)
- **Dynamic Check:** Sales bookings with discount <= 5.0% proceed to confirmation automatically. Bookings with discount > 5.0% are intercepted and queued into `approval_requests`.
- **Result:** **PASSED**

---

## 3. Test Verification Sign-Off

- **Total Settings Tested:** 12
- **Verified Operational:** 12 (100%)
- **Dormant / Inert Settings:** 0
- **Regression Suite:** `tests/test-settings-verification.php` (10 assertions PASS)
- **Sign-off Status:** **APPROVED**

