# TESTING STRATEGY
## RDU Dashboard — Testing Requirements

---

## Testing Philosophy

ทุก Phase ต้องผ่านการทดสอบก่อนเข้า Phase ถัดไป:

```
PLAN → IMPLEMENT → TEST → SECURITY REVIEW → PERFORMANCE REVIEW → REFACTOR → TEST AGAIN
```

---

## Read-Only Database Tests

### Test 1 — SELECT
```
Query:  SELECT
Result: PASS
```

### Test 2 — INSERT
```
Query:  INSERT
Result: BLOCK
```

### Test 3 — UPDATE
```
Query:  UPDATE
Result: BLOCK
```

### Test 4 — DELETE
```
Query:  DELETE
Result: BLOCK
```

### Test 5 — CREATE TABLE
```
Query:  CREATE TABLE
Result: BLOCK
```

### Test 6 — DROP TABLE
```
Query:  DROP TABLE
Result: BLOCK
```

---

## Database Query Guard

สร้าง `RduDatabaseQueryGuard` สำหรับ Development / Testing

```
RDU Connection
       ↓
Query Listener
       ↓
ตรวจ Query
       ↓
SELECT → Allow
อื่น ๆ → Reject / Fail
```

เป้าหมาย: **ถ้า Query ไม่ใช่ SELECT ให้ Test Fail**

---

## Security Acceptance Tests (Pre-Production)

```
[ ] RDU DB User มี SELECT เท่านั้น
[ ] ไม่มี INSERT
[ ] ไม่มี UPDATE
[ ] ไม่มี DELETE
[ ] ไม่มี CREATE
[ ] ไม่มี ALTER
[ ] ไม่มี DROP
[ ] Laravel ใช้ RDU Connection
[ ] RDU Connection ไม่สามารถ Write
[ ] JSON Config ไม่อยู่ public
[ ] Password ไม่เป็น Plain Text
[ ] Password ไม่อยู่ Git
[ ] Password ไม่อยู่ Audit Log
[ ] Admin เท่านั้นที่แก้ Config
```

---

## Phase Testing Requirements

### Phase 1 — XAMPP Foundation
```
[ ] Apache ทำงานได้
[ ] PHP ทำงานได้
[ ] PostgreSQL ทำงานได้
[ ] Composer ทำงานได้
[ ] Laravel ทำงานได้
```

### Phase 2 — Application Database
```
[ ] Migration ทำงานได้
[ ] Seeder ทำงานได้
[ ] Tables ถูกต้อง
```

### Phase 3 — Read-Only Database Layer
```
[ ] SELECT PASS
[ ] INSERT BLOCK
[ ] UPDATE BLOCK
[ ] DELETE BLOCK
[ ] CREATE BLOCK
[ ] DROP BLOCK
```

### Phase 4 — JSON Database Configuration
```
[ ] Save ทำงานได้
[ ] Load ทำงานได้
[ ] Validation ทำงานได้
[ ] Encryption ทำงานได้
[ ] Connection Test ทำงานได้
[ ] Read-only verification ทำงานได้
[ ] Permission ถูกต้อง
```

### Phase 9 — Security
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

### Phase 10 — Performance
```
[ ] Query Optimization
[ ] Index
[ ] Cache
[ ] N+1 Problem
[ ] Pagination
[ ] Chart Payload Size
```

---

## PHP Extension Verification

ตรวจสอบ PHP Extension อย่างน้อย:

```
OpenSSL
PDO
PDO_PGSQL
Mbstring
Tokenizer
XML
Ctype
JSON
Fileinfo
BCMath
```

คำสั่งตรวจสอบ:

```bash
php -m
php -v
composer -V
node -v
npm -v
```

---

## Full QA (Phase 11)

ทดสอบทั้งระบบ End-to-End ก่อน Production

---

## Final Refactor Checklist (Phase 12)

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
