# CLAUDE.md — RDU Dashboard
## โรงพยาบาลเชียงกลาง จังหวัดน่าน

ระบบ **RDU Dashboard** (Rational Drug Use) ติดตาม 13 ตัวชี้วัดการใช้ยาอย่างสมเหตุผล
พัฒนาด้วย Laravel บน XAMPP เชื่อมต่อ HOSxP Database แบบ **อ่านอย่างเดียวเท่านั้น**

---

## Technology Stack

| Layer | Technology |
|---|---|
| Web Server | Apache (XAMPP) — ไม่ใช้ Docker |
| Backend | Laravel (PHP 8.3+) |
| Frontend | Blade + Chart.js |
| Application DB | PostgreSQL (`rdu_dashboard`) |
| Hospital DB | HOSxP / PostgreSQL — **SELECT ONLY** |
| Tools | Composer, Node.js, NPM |

---

## ⚠️ Critical Security Rules — อ่านก่อนเขียน Code ทุกครั้ง

### HOSxP Database = READ ONLY อย่างเด็ดขาด

Connection `rdu` เชื่อมต่อ HOSxP — ห้ามใช้คำสั่งเหล่านี้:

```php
// ❌ ห้ามเด็ดขาดบน connection 'rdu'
create() / insert() / insertGetId() / update() / updateOrInsert()
upsert() / delete() / destroy() / truncate() / save() / createOrUpdate()
increment() / decrement()
```

```sql
-- ❌ ห้ามเด็ดขาด
INSERT / UPDATE / DELETE / CREATE / ALTER / DROP / TRUNCATE / REPLACE
```

อนุญาตเฉพาะ:

```php
// ✅ บน connection 'rdu'
select() / get() / first() / find() / where() / whereIn() / whereBetween()
join() / leftJoin() / groupBy() / orderBy() / having()
count() / sum() / avg() / min() / max() / exists() / paginate()
```

### ⛔ การลบทุกชนิด ต้องขออนุญาตผู้ใช้ก่อนทุกครั้ง — ห้ามทำเองโดยเด็ดขาด

ก่อนสั่งงานใดๆ ที่**ลบ**ข้อมูล ต้องบอกผู้ใช้ว่าจะลบอะไร และรอให้ผู้ใช้ตอบอนุญาตชัดเจนก่อนทุกครั้ง แม้เป็นส่วนหนึ่งของงานที่อนุมัติไว้แล้ว
ครอบคลุม:
- ลบไฟล์หรือโฟลเดอร์ (`rm`, `unlink`, `Remove-Item`, `File::delete`, ลบไฟล์ผ่าน editor)
- คำสั่ง `DELETE` / `TRUNCATE` / `DROP` บนฐานข้อมูลใดก็ตาม (รวม `rdu_dashboard`), `migrate:fresh`, `migrate:rollback`, `db:wipe`
- คำสั่ง git ที่ทิ้งงาน (`reset --hard`, `clean`, `checkout -- .`, `restore`, `branch -D`, `stash drop`, force push)
- `cache:clear` / `view:clear` / `config:clear` ไม่นับเป็นการลบข้อมูล ใช้ได้ตามปกติ
- **การรันชุดทดสอบ (`php artisan test`) ที่มี test สร้าง/ลบไฟล์จริง** — ตรวจก่อนว่าทุก test ใช้ storage ชั่วคราว
  (`tests/TestCase.php` ตั้งไว้แล้ว) ถ้าไม่แน่ใจให้ถามก่อน

เหตุผล: เคยเกิดเหตุ 2 ครั้ง (2026-09-23 และ 2026-09-25) ที่การรัน test ลบไฟล์ `rdu_database.json` ตัวจริง ทำให้ระบบเชื่อม HOSxP ไม่ได้และ login ไม่ได้

### กฎเพิ่มเติมที่ห้ามละเมิด

- **ห้าม `php artisan migrate` บน HOSxP** — Migration ใช้กับ `rdu_dashboard` เท่านั้น
- **ห้าม Hard-code Credential** — ทุก DB config เก็บใน `storage/app/private/config/rdu_database.json`
- **ห้าม Plain-text Password** — ต้อง Encrypt ก่อนบันทึก JSON เสมอ
- **ห้ามให้ `rdu_database.json` อยู่ใน `public/`** — และต้องอยู่ใน `.gitignore`
- **ห้าม Controller ยิง Query HOSxP โดยตรง** — ต้องผ่าน `RduQueryService` เท่านั้น

---

## Database Connections

```php
DB::connection('pgsql')   // RDU Application DB — อ่าน-เขียนได้
DB::connection('rdu')     // HOSxP Hospital DB  — SELECT เท่านั้น
```

Two layers of protection:
1. **Laravel layer** — `ReadonlyHosxpModel` throws `ReadonlyDatabaseException` หากมี write
2. **PostgreSQL layer** — DB user `rdu_readonly` มี `SELECT` privilege เท่านั้น

---

## Directory Structure

```
app/
├── DataSources/
│   ├── RduDataSourceInterface.php
│   ├── MockRduDataSource.php        ← ใช้ใน Testing
│   └── HosxpRduDataSource.php
├── Database/
│   ├── ReadonlyConnection.php
│   └── RduDatabaseConnectionFactory.php
├── Models/
│   ├── Hosxp/
│   │   ├── ReadonlyHosxpModel.php   ← Base model ทุก HOSxP model ต้อง extend นี้
│   │   ├── HosxpPatient.php
│   │   ├── HosxpVisit.php
│   │   ├── HosxpDrug.php
│   │   └── HosxpDiagnosis.php
│   ├── RduIndicator.php
│   ├── RduIndicatorResult.php
│   └── AuditLog.php
├── Services/
│   ├── RduQueryService.php          ← จุดเดียวที่ Query HOSxP
│   ├── RduCalculationService.php    ← คำนวณ KPI
│   └── DatabaseConfigService.php   ← อ่าน/เขียน JSON Config
├── Http/
│   ├── Controllers/Admin/
│   │   └── DatabaseConfigController.php
│   └── Requests/
│       └── DatabaseConfigRequest.php
└── Exceptions/
    └── ReadonlyDatabaseException.php

storage/app/private/config/
└── rdu_database.json                ← ห้ามอยู่ public/, ห้าม commit

docs/
├── PROJECT-SPECIFICATION.md
├── ARCHITECTURE.md
├── DATABASE-SECURITY.md
├── DATABASE-CONFIGURATION.md
├── KPI-SPECIFICATION.md
├── UI-UX-SPECIFICATION.md
├── TESTING-STRATEGY.md
└── DEVELOPMENT-PHASES.md
```

---

## Data Flow

```
HOSxP (PostgreSQL)
  ↓ SELECT ONLY
RduDataSource → RduQueryService → RduCalculationService → Cache → Controller → Blade → Chart.js
```

```
Admin → Config Page → DatabaseConfigService → Validate → Encrypt → JSON → TestConnection → VerifyReadOnly → AuditLog
```

---

## JSON Config Structure (HOSxP Connection)

```json
{
    "driver": "pgsql",
    "host": "...",
    "port": 5432,
    "database": "hosxp",
    "schema": "public",
    "username": "rdu_readonly",
    "password": "ENCRYPTED_VALUE",
    "charset": "utf8",
    "readonly": true
}
```

---

## Coding Conventions

- HOSxP Model ทุกตัวต้อง `extend ReadonlyHosxpModel` — ห้าม extend `Model` โดยตรง
- Query HOSxP ทุกอย่างต้องผ่าน `RduQueryService` — ห้าม Query จาก Controller
- `DatabaseConfigService` เท่านั้นที่อ่าน/เขียน JSON — ห้าม Controller อ่านโดยตรง
- Password ต้อง Encrypt ด้วย Laravel Encryption ก่อนบันทึก และ Decrypt เมื่อใช้งาน
- ห้ามแสดง Password ใน Response, Error Message, หรือ Log
- **KPI ใหม่ทุกตัวต้องทำตาม `docs/KPI-REFERENCE-IMPLEMENTATION.md`** — KPI01, KPI03, KPI04, KPI05 คือแบบอ้างอิง
  (query class, reference SQL, key ของข้อมูลรายกรณี, การลงทะเบียน และหน้าแสดงผล) อ่านก่อนเริ่มทุกครั้ง

---

## Testing Requirements

ทุก Phase ต้องผ่าน: `PLAN → IMPLEMENT → TEST → SECURITY REVIEW → PERFORMANCE REVIEW → REFACTOR → TEST AGAIN`

**ห้ามเข้า Phase ถัดไปหาก Test ยังไม่ผ่าน**

Read-Only Tests ที่ต้องผ่านก่อน Production:

| Test | Expected |
|---|---|
| SELECT บน `rdu` | PASS |
| INSERT บน `rdu` | BLOCK |
| UPDATE บน `rdu` | BLOCK |
| DELETE บน `rdu` | BLOCK |
| CREATE TABLE บน `rdu` | BLOCK |
| DROP TABLE บน `rdu` | BLOCK |

---

## Development Phases

| Phase | เนื้อหา |
|---|---|
| 0 | Architecture & Security Planning |
| 1 | XAMPP Foundation (Apache, PHP, PostgreSQL) |
| 2 | Application Database (`rdu_dashboard`) |
| 3 | Read-Only Database Layer |
| 4 | JSON Database Configuration |
| 5 | Dashboard Foundation (Sidebar, Overview, 13 KPI) |
| 6 | KPI Engine (Calculation Service) |
| 7 | KPI Detail Pages |
| 8 | Reports (Excel, CSV, PDF) |
| 9 | Security Audit |
| 10 | Performance Optimization |
| 11 | Full QA |
| 12 | Final Refactor |

---

## XAMPP Setup (ย่อ)

```
DocumentRoot: C:\xampp\htdocs\rdu-dashboard\public   ← ต้องเป็น /public
PostgreSQL: ติดตั้งแยก — ไม่ได้รวมใน XAMPP
php artisan migrate: รันบน rdu_dashboard เท่านั้น
```

PHP Extensions ที่ต้องการ: `OpenSSL, PDO, PDO_PGSQL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, BCMath`

---

## Docs Reference

| เรื่อง | ไฟล์ |
|---|---|
| Architecture & Data Flow | `docs/ARCHITECTURE.md` |
| Security Rules (ละเอียด) | `docs/DATABASE-SECURITY.md` |
| DB Config & JSON | `docs/DATABASE-CONFIGURATION.md` |
| KPI Specification | `docs/KPI-SPECIFICATION.md` |
| KPI Reference Implementation (แบบอ้างอิง KPI ใหม่) | `docs/KPI-REFERENCE-IMPLEMENTATION.md` |
| UI/UX Design | `docs/UI-UX-SPECIFICATION.md` |
| Testing Strategy | `docs/TESTING-STRATEGY.md` |
| Phase Roadmap | `docs/DEVELOPMENT-PHASES.md` |
