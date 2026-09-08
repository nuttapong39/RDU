# DEVELOPMENT PHASES
## RDU Dashboard — Development Roadmap

---

## PHASE 0 — Architecture & Security Planning

ก่อนเขียน Code ให้แสดง:

1. Final Architecture
2. Database Architecture
3. Read-Only Security Architecture
4. ERD
5. Database Connections
6. JSON Configuration Structure
7. Security Threat Model
8. XAMPP Setup
9. Directory Structure
10. 13 KPI Data Model
11. Roles & Permissions
12. Testing Strategy
13. Development Roadmap

---

## PHASE 1 — XAMPP Foundation

แทน Docker ด้วย XAMPP

### Environment

```
Windows
XAMPP
Apache
PHP 8.3+
PostgreSQL 16+
Composer
Node.js
NPM
Git
```

### XAMPP Setup Steps

```
1. Install XAMPP
2. Enable Apache
3. Install PostgreSQL (แยกต่างหาก — ไม่ได้รวมใน XAMPP)
4. Install Composer
5. Install Node.js
6. Clone Project
7. composer install
8. npm install
9. configure .env
10. php artisan key:generate
11. php artisan migrate      ← RDU Application Database เท่านั้น
12. php artisan db:seed
13. npm run build
```

### Project Location

```
C:\xampp\htdocs\rdu-dashboard
```

### Apache Virtual Host

```
ServerName: rdu-dashboard.test
DocumentRoot: C:\xampp\htdocs\rdu-dashboard\public
```

Document Root ต้องชี้ไปที่ `/public` เพราะ Laravel ต้องให้ Web Root เป็น `/public`

### Tests

```
[ ] Apache ทำงานได้
[ ] PHP ทำงานได้
[ ] PostgreSQL ทำงานได้
[ ] Composer ทำงานได้
[ ] Laravel ทำงานได้
```

---

## PHASE 2 — Application Database

สร้าง RDU Application Database: `rdu_dashboard`

### Tables

```
users
rdu_indicators
rdu_indicator_results
audit_logs
settings
```

### Tests

```
[ ] Migration ทำงานได้
[ ] Seeder ทำงานได้
[ ] Tables ถูกต้อง
```

---

## PHASE 3 — Read-Only Database Layer

### สิ่งที่ต้องสร้าง

```
RduDatabaseConnectionFactory
ReadonlyHosxpModel
RduQueryService
RduDataSourceInterface
```

### Tests

```
[ ] SELECT PASS
[ ] INSERT BLOCK
[ ] UPDATE BLOCK
[ ] DELETE BLOCK
[ ] CREATE BLOCK
[ ] DROP BLOCK
```

---

## PHASE 4 — JSON Database Configuration

### สิ่งที่ต้องสร้าง

```
Database Configuration Page
DatabaseConfigService
JSON Storage
Encryption
Connection Test
Read-Only Verification
```

### Tests

```
[ ] Save
[ ] Load
[ ] Validation
[ ] Encryption
[ ] Connection
[ ] Read-only verification
[ ] Permission
```

---

## PHASE 5 — Dashboard Foundation

### สิ่งที่ต้องสร้าง

```
Sidebar
Topbar
Overview Page
13 KPI Cards
Charts
Filters
```

---

## PHASE 6 — KPI Engine

### สิ่งที่ต้องสร้าง

```
Calculation Service
Numerator
Denominator
Percentage
Target
Status
Trend
```

---

## PHASE 7 — KPI Detail

สร้าง Detail Page ทั้ง 13 KPI

---

## PHASE 8 — Reports

```
Excel Export
CSV Export
PDF Export
```

---

## PHASE 9 — Security

### ตรวจสอบ

```
[ ] Authentication
[ ] Authorization
[ ] Read-only
[ ] JSON Security
[ ] Audit Log
[ ] Session
[ ] CSRF
[ ] XSS
[ ] SQL Injection
```

---

## PHASE 10 — Performance

### ตรวจสอบ

```
[ ] Query Optimization
[ ] Index
[ ] Cache
[ ] N+1 Problem
[ ] Pagination
[ ] Chart Payload Size
```

---

## PHASE 11 — Full QA

ทดสอบทั้งระบบ End-to-End

---

## PHASE 12 — Final Refactor

### ตรวจสอบ

```
[ ] Duplicate Code
[ ] Dead Code
[ ] Security
[ ] Performance
[ ] Architecture
[ ] Naming
[ ] Documentation
```

แล้ว Run Test ใหม่ทั้งหมด

---

## Phase Gate Rule

**ห้ามเข้าสู่ Phase ถัดไป หาก Test ของ Phase ปัจจุบันยังไม่ผ่าน**

ทุก Phase ต้องผ่านกระบวนการ:

```
PLAN
 ↓
IMPLEMENT
 ↓
TEST
 ↓
SECURITY REVIEW
 ↓
PERFORMANCE REVIEW
 ↓
REFACTOR
 ↓
TEST AGAIN
```

---

## XAMPP vs Docker

ใช้ **XAMPP** แทน Docker — ไม่ต้องสร้าง:

```
docker-compose.yml
Dockerfile
Nginx Container
PHP Container
PostgreSQL Container
```
