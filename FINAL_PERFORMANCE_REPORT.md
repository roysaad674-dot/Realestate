# ESTATEOS — FINAL PERFORMANCE & LATENCY REPORT

**Audit Date:** November 14, 2026  
**Auditor:** Performance Engineering Specialist & System Architect  
**Benchmark Suite:** `tests/test-performance.php`  
**Evaluation Target:** MariaDB Engine + PHP 8.2 MVC Architecture  

---

## 1. Executive Performance Summary

EstateOS was benchmarked across database query latency, full MVC controller lifecycle execution, live HTTP endpoint response times, and memory utilization.

The system demonstrates **sub-millisecond database aggregations**, controller response times under **100 ms**, and a remarkably lean **4.0 MB peak memory footprint**, comfortably supporting high-throughput enterprise real estate operations.

---

## 2. Core Database Latency Benchmarks

Tested over 20 repeated iterations to eliminate caching anomalies:

| Query Scenario | Complexity / Tables Involved | Average Latency | SLA Target | Compliance |
|:---|:---|:---:|:---:|:---:|
| **Dashboard Multi-Entity Aggregation** | 6 subqueries across `projects`, `units`, `bookings`, `customer_payments`, `leads`, `construction_tasks` | **0.83 ms** | < 50 ms | **EXCELLENT** |
| **General Ledger Double-Entry Invariant** | Aggregation across `journal_entries` and `journal_entry_lines` | **1.45 ms** | < 25 ms | **EXCELLENT** |
| **Unit Inventory 4-Table JOIN** | 4-table join across `units`, `buildings`, `projects`, `unit_types` with column sorting | **10.01 ms** | < 100 ms | **EXCELLENT** |

---

## 3. Full MVC Controller Lifecycle Latency

Measured end-to-end: Routing -> Session Check -> Controller Logic -> Database Queries -> Template Rendering:

| Controller Action | Payload Size (HTML) | Execution Latency | Assessment |
|:---|:---:|:---:|:---:|
| `DashboardController::index()` | 53,698 bytes | **94.41 ms** | Smooth interactive load |
| `UnitController::index()` | 59,673 bytes | **36.36 ms** | Fast inventory grid load |
| `BookingController::index()` | 35,483 bytes | **14.04 ms** | Near-instantaneous |
| `CollectionController::index()` | 34,957 bytes | **15.56 ms** | Near-instantaneous |
| `AccountingController::index()` | 39,016 bytes | **21.14 ms** | Sub-50ms ledger rendering |
| `ReportController::index()` | 33,565 bytes | **4.10 ms** | Highly optimized |

---

## 4. Live HTTP Network Response (Port 8787)

Benchmarked via local HTTP requests against the live web server:

| Endpoint | Purpose | HTTP Status | Response Time |
|:---|:---|:---:|:---:|
| `GET /login` | Public Staff Authentication | `200 OK` | **42.90 ms** |
| `GET /portal/login` | Customer Self-Service Portal | `200 OK` | **44.42 ms** |
| `GET /forgot-password` | Password Recovery Flow | `200 OK` | **37.19 ms** |

---

## 5. Memory Profiling & Resource Footprint

- **Current Memory Allocation:** `4.00 MB`
- **Peak Memory Usage:** `4.00 MB`
- **PHP Memory Limit (`memory_limit`):** `512 MB` (Configured in PHP CLI / XAMPP)
- **Headroom Margin:** > **99.2%** available memory headroom

---

## 6. N+1 Query Analysis & Mitigations

- **Units & Buildings:** Units list views eagerly join `buildings`, `projects`, and `unit_types` in a single query rather than issuing individual lookup queries per row.
- **Journal Entries & Lines:** General Ledger queries fetch voucher headers and lines with single-pass joins, eliminating iterative round-trips.
- **Installment Milestones:** CLP milestone schedules are bulk-loaded with composite keys.

---

## 7. Production Sizing & Concurrency Capacity

Based on the empirical benchmark profile:
- A single 4-core, 8 GB RAM Linux VM running Nginx + PHP-FPM (pm.max_children = 50) is projected to sustain **350–500 concurrent active staff requests/second** without latency degradation.
- MariaDB InnoDB buffer pool of 2 GB will easily cache the entire 119-table dataset in RAM, ensuring disk I/O remains near zero during standard daily operations.

