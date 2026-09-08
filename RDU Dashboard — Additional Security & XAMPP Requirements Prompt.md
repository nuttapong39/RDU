# ADDITIONAL REQUIREMENTS
## RDU Dashboard – โรงพยาบาลเชียงกลาง จังหวัดน่าน

ให้ปรับปรุง Project Specification เดิม โดยมีข้อกำหนดเพิ่มเติมต่อไปนี้

---

# 1. CRITICAL REQUIREMENT — DATABASE MUST BE READ-ONLY

**นี่เป็นข้อกำหนดด้าน Security ที่สำคัญที่สุดของระบบ**

ระบบ RDU Dashboard จะเชื่อมต่อกับ Database ของโรงพยาบาลเพื่อใช้สำหรับอ่านข้อมูลเท่านั้น

Database Connection ที่ใช้สำหรับ RDU Dashboard ต้องมีสิทธิ์:

```text
SELECT ONLY
```

ห้ามมีสิทธิ์:

```text
INSERT
UPDATE
DELETE
CREATE
ALTER
DROP
TRUNCATE
REPLACE
GRANT
REVOKE
```

โดยเด็ดขาด

---

# 2. DATABASE SECURITY ARCHITECTURE

ต้องออกแบบ Architecture ให้เป็น:

```text
HOSxP / Hospital Database
            │
            │
            ▼
     READ-ONLY DB USER
            │
            │ SELECT ONLY
            ▼
      Laravel Application
            │
            ▼
     Service / Query Layer
            │
            ▼
       Cache
            │
            ▼
       Dashboard
```

Laravel จะต้องไม่สามารถแก้ไข Database ต้นทางได้

---

# 3. IMPORTANT — DATABASE USER

หาก Database ต้นทางเป็น MySQL ให้สร้าง Database User สำหรับ RDU โดยเฉพาะ เช่น:

```text
rdu_readonly
```

และให้สิทธิ์เฉพาะ:

```sql
SELECT
```

ตัวอย่างแนวคิด:

```sql
CREATE USER 'rdu_readonly'@'localhost'
IDENTIFIED BY 'STRONG_PASSWORD';

GRANT SELECT
ON hospital_database.*
TO 'rdu_readonly'@'localhost';
```

**หมายเหตุ**

คำสั่งดังกล่าวเป็นเพียงตัวอย่างสำหรับการตั้งค่าระบบ

ห้ามนำ Password ตัวอย่างไปใช้จริง

ห้ามใช้:

```sql
GRANT ALL PRIVILEGES
```

ห้ามใช้ Database User ของ Application ที่มีสิทธิ์เขียนข้อมูล

---

# 4. SEPARATE DATABASE CONNECTION

ให้แยก Database Connection สำหรับ RDU โดยเฉพาะ

ตัวอย่าง:

```text
Primary Application Database
RDU Read-Only Database
```

แต่หากระบบไม่ได้มี Application Database แยก ให้ใช้ RDU Connection เป็น Read-Only Connection

ตัวอย่าง Configuration Concept:

```text
database.connections.rdu
```

โดย Connection นี้ต้องใช้ Credential ของ Read-Only Database User เท่านั้น

---

# 5. ABSOLUTE RULE — NO WRITE QUERY

ใน Code ของ RDU Data Layer:

**ห้ามมีคำสั่งเหล่านี้**

```php
create()
insert()
insertGetId()
update()
updateOrInsert()
upsert()
delete()
destroy()
truncate()
increment()
decrement()
save()
createOrUpdate()
```

รวมถึง Raw SQL:

```sql
INSERT
UPDATE
DELETE
CREATE
ALTER
DROP
TRUNCATE
REPLACE
```

ห้ามใช้กับ RDU Database Connection

---

# 6. ALLOW ONLY SELECT

อนุญาต:

```php
select()
get()
first()
find()
where()
whereIn()
whereBetween()
join()
leftJoin()
rightJoin()
groupBy()
orderBy()
having()
count()
sum()
avg()
min()
max()
exists()
paginate()
simplePaginate()
cursorPaginate()
```

รวมถึง SQL ที่เป็น:

```sql
SELECT
```

เท่านั้น

---

# 7. DEFENSIVE DATABASE DESIGN

นอกจาก Application Level แล้ว ให้ Database Server เป็นตัวป้องกันอีกชั้นหนึ่ง

ต้องมี:

```text
Application
      ↓
Laravel Permission
      ↓
Read-Only DB User
      ↓
Database Permission
```

ดังนั้นแม้ Developer จะเขียน Code ผิดและพยายาม:

```php
DB::connection('rdu')->update(...)
```

Database Server ต้อง Reject Operation

---

# 8. DATABASE QUERY GUARD

สร้างระบบตรวจสอบ Query ใน Development / Testing

เป้าหมาย:

```text
ถ้า Query ไม่ใช่ SELECT
ให้ Test Fail
```

สามารถสร้าง Test / Listener สำหรับตรวจ Query เช่น:

```text
RduDatabaseQueryGuard
```

แนวคิด:

```text
RDU Connection
       ↓
Query Listener
       ↓
ตรวจ Query
       ↓
SELECT → Allow
อื่น ๆ → Reject / Fail
```

ใน Production การป้องกันหลักต้องอยู่ที่ **Database Permission** ไม่ใช่ Query Guard เพียงอย่างเดียว

---

# 9. DATABASE MIGRATION RULE

**ห้าม Run Laravel Migration กับ HOSxP / Hospital Database**

โดยเด็ดขาด

ห้ามทำ:

```bash
php artisan migrate
```

บน Database ต้นทางของโรงพยาบาล

---

# 10. APPLICATION DATABASE

หากระบบต้องมี Table สำหรับ:

```text
users
sessions
cache
audit_logs
rdu_indicators
rdu_indicator_results
```

ให้พิจารณาแยกเป็น:

```text
RDU Application Database
```

ออกจาก:

```text
HOSxP Database
```

Architecture:

```text
                  ┌──────────────────────┐
                  │   RDU Application    │
                  │      Database        │
                  │                      │
                  │ users                │
                  │ settings             │
                  │ audit_logs           │
                  │ rdu_indicators       │
                  └──────────┬───────────┘
                             │
                         Laravel
                             │
                  ┌──────────┴───────────┐
                  │                      │
                  ▼                      ▼
           Application DB       HOSxP Read-Only DB
                                    │
                                  SELECT
```

ห้ามนำ Table ของ RDU ไปสร้างใน HOSxP Database หากไม่ได้รับอนุญาตจากผู้ดูแลระบบฐานข้อมูล

---

# 11. CONFIGURATION PAGE

สร้างหน้าสำหรับ:

```text
Database Connection Configuration
```

URL:

```text
/admin/settings/database
```

หรือ:

```text
/settings/database
```

เฉพาะ:

```text
Admin
```

เท่านั้นที่สามารถเข้าหน้านี้ได้

---

# 12. DATABASE CONFIG UI

ออกแบบหน้า Config ให้เป็น Modern Admin UI

หัวข้อ:

```text
การเชื่อมต่อฐานข้อมูล RDU
RDU Database Connection
```

Fields:

```text
Connection Name
Database Driver
Host
Port
Database Name
Username
Password
Charset
```

ตัวอย่าง:

```text
Connection Name:
HOSxP RDU Read Only

Driver:
MySQL

Host:
192.168.1.100

Port:
3306

Database:
hosxp

Username:
rdu_readonly

Password:
********

Charset:
utf8mb4
```

---

# 13. JSON CONFIGURATION

**ห้าม Hard-code Database Configuration ใน Source Code**

ให้จัดเก็บ Configuration เป็น JSON File

ตัวอย่าง:

```text
storage/app/private/config/rdu_database.json
```

หรือ Path ที่ปลอดภัยที่เหมาะสมกับ Laravel

ตัวอย่างโครงสร้าง:

```json
{
    "driver": "mysql",
    "host": "192.168.1.100",
    "port": 3306,
    "database": "hosxp",
    "username": "rdu_readonly",
    "password": "ENCRYPTED_VALUE",
    "charset": "utf8mb4",
    "readonly": true
}
```

---

# 14. NEVER STORE PLAINTEXT PASSWORD

Password ของ Database:

**ห้ามเก็บ Plain Text**

หากสามารถทำได้ ให้ Encrypt Password ก่อนบันทึก JSON

ตัวอย่าง Concept:

```text
User Input
    ↓
Laravel Validation
    ↓
Encrypt Password
    ↓
JSON
```

เมื่ออ่าน:

```text
JSON
 ↓
Decrypt
 ↓
Database Connection
```

ห้ามส่ง Password กลับไปแสดงใน Browser

---

# 15. JSON FILE SECURITY

JSON Configuration File ต้อง:

- ไม่อยู่ใน public/
- ไม่สามารถเปิดผ่าน Browser
- ไม่สามารถ Download ผ่าน URL
- ไม่ Commit เข้า Git
- ไม่อยู่ใน Repository
- มี Permission ที่เหมาะสม

ต้องเพิ่มใน:

```text
.gitignore
```

ตัวอย่าง:

```text
/storage/app/private/config/*.json
```

หรือ Path ที่ Project กำหนด

---

# 16. CONFIG SERVICE

สร้าง Service:

```text
DatabaseConfigService
```

หน้าที่:

```text
load()
save()
validate()
testConnection()
getConnectionConfig()
encryptPassword()
decryptPassword()
```

ห้ามให้ Controller อ่าน JSON โดยตรง

Architecture:

```text
Controller
    ↓
DatabaseConfigService
    ↓
JSON Configuration
```

---

# 17. DATABASE CONNECTION FACTORY

สร้าง:

```text
RduDatabaseConnectionFactory
```

หน้าที่:

```text
อ่าน JSON
      ↓
Validate Config
      ↓
สร้าง Dynamic Connection
      ↓
เชื่อมต่อ MySQL
      ↓
ใช้ SELECT ONLY
```

---

# 18. TEST CONNECTION BUTTON

หน้า Configuration ต้องมี:

```text
[ ทดสอบการเชื่อมต่อ ]
```

เมื่อกด:

```text
Validate Configuration
        ↓
Connect Database
        ↓
SELECT 1
        ↓
Check Connection
```

แสดงผล:

### Success

```text
✓ เชื่อมต่อฐานข้อมูลสำเร็จ

Database:
HOSxP

Host:
192.168.1.100

Connection:
Read-Only
```

### Failed

```text
✕ ไม่สามารถเชื่อมต่อฐานข้อมูลได้

กรุณาตรวจสอบ:
Host
Port
Database
Username
Password
Network
```

**ห้ามแสดง Password ใน Error Message**

---

# 19. VERIFY READ-ONLY

หลัง Test Connection สำเร็จ ให้ระบบตรวจสอบเพิ่มเติมว่า Connection เป็น Read-Only จริง

แนวคิด:

```text
Connect
 ↓
ตรวจสอบ Database User
 ↓
ตรวจสอบ Privileges
 ↓
ตรวจสอบว่า SELECT ได้
 ↓
ตรวจสอบว่าไม่มี Write Privilege
```

ถ้าตรวจพบว่า User มี:

```text
INSERT
UPDATE
DELETE
CREATE
ALTER
DROP
```

ให้แสดง:

```text
⚠️ SECURITY WARNING

Database User นี้ไม่ใช่ Read-Only User

ระบบจะไม่อนุญาตให้ใช้งานเป็น RDU Data Source
```

---

# 20. CONFIG VERSION / BACKUP

เมื่อ Admin เปลี่ยน Configuration

ไม่ควร Overwrite แบบไม่มีประวัติ

ให้รองรับ:

```text
Current Configuration
Previous Configuration
Updated At
Updated By
```

แต่ห้ามเก็บ Password ใน Audit Log

---

# 21. AUDIT LOG FOR CONFIG

ทุกครั้งที่มีการเปลี่ยน Database Configuration:

```text
Admin User
Date / Time
IP
Action
Host
Port
Database
Username
```

ห้าม Log:

```text
Password
Decrypted Secret
```

ตัวอย่าง:

```text
DATABASE_CONFIG_UPDATED
```

---

# 22. XAMPP — REPLACE DOCKER

เปลี่ยนจาก Docker เป็น:

```text
XAMPP
```

ไม่ต้องสร้าง:

```text
docker-compose.yml
Dockerfile
Nginx Container
PHP Container
MySQL Container
```

---

# 23. XAMPP ENVIRONMENT

Development Environment:

```text
Windows
XAMPP
Apache
PHP 8.3+
MySQL 8
Composer
Node.js
NPM
Git
```

Architecture:

```text
Browser
   ↓
Apache
   ↓
Laravel
   ↓
PHP 8.3+
   ↓
MySQL
```

---

# 24. XAMPP PROJECT LOCATION

แนะนำ:

```text
C:\xampp\htdocs\rdu-dashboard
```

หรือ Path อื่นตามเครื่อง Developer

---

# 25. XAMPP SETUP

README ต้องอธิบายขั้นตอน:

```text
1. Install XAMPP
2. Enable Apache
3. Enable MySQL
4. Install Composer
5. Install Node.js
6. Clone Project
7. composer install
8. npm install
9. configure .env
10. php artisan key:generate
11. php artisan migrate
12. php artisan db:seed
13. npm run build
```

**หมายเหตุ**

คำสั่ง:

```text
php artisan migrate
```

ให้ใช้กับ **RDU Application Database เท่านั้น**

ห้ามใช้กับ HOSxP Database

---

# 26. XAMPP APACHE CONFIGURATION

จัดเตรียมตัวอย่าง Apache Virtual Host:

```text
rdu-dashboard.test
```

Document Root ต้องชี้ไป:

```text
C:\xampp\htdocs\rdu-dashboard\public
```

ไม่ใช่:

```text
C:\xampp\htdocs\rdu-dashboard
```

เพราะ Laravel ต้องให้ Web Root เป็น:

```text
/public
```

---

# 27. PHP REQUIREMENTS

ตรวจสอบ PHP Extension อย่างน้อย:

```text
OpenSSL
PDO
PDO_MySQL
Mbstring
Tokenizer
XML
Ctype
JSON
Fileinfo
BCMath
```

README ต้องมีคำสั่งตรวจสอบ:

```bash
php -m
php -v
composer -V
node -v
npm -v
```

---

# 28. APPLICATION DATABASE

สำหรับ Development ให้ใช้ MySQL ที่มากับ XAMPP

ตัวอย่าง:

```text
rdu_dashboard
```

Database นี้ใช้สำหรับ:

```text
users
sessions
cache
audit_logs
rdu_indicators
application_settings
```

ส่วน HOSxP ใช้:

```text
rdu_readonly
```

แยก Connection ออกจากกันอย่างชัดเจน

---

# 29. DATABASE CONNECTION STRUCTURE

ระบบควรมี:

```text
default
    ↓
RDU Application Database

rdu
    ↓
HOSxP Read-Only Database
```

ตัวอย่าง Concept:

```php
DB::connection('mysql')
```

สำหรับ Application DB

และ:

```php
DB::connection('rdu')
```

สำหรับ HOSxP

---

# 30. STRICT DATA ACCESS RULE

สร้าง Base Query / Service สำหรับ HOSxP เช่น:

```text
RduQueryService
```

ทุก Query ที่อ่าน HOSxP ต้องผ่าน:

```text
RduQueryService
```

หรือ Query Layer ที่กำหนด

ไม่ควรให้ Controller ยิง Query HOSxP โดยตรง

---

# 31. NO MODEL WRITE

Model ที่ผูกกับ HOSxP:

```text
HosxpPatient
HosxpVisit
HosxpDrug
HosxpDiagnosis
```

ต้องถือว่าเป็น:

```text
READ ONLY MODEL
```

ห้ามเรียก:

```php
$model->save();
$model->delete();
$model->update();
$model->create();
```

---

# 32. READ-ONLY MODEL PROTECTION

ถ้าใช้ Eloquent Model กับ HOSxP ให้สร้าง Base Model:

```text
ReadonlyHosxpModel
```

Override write operations เพื่อป้องกัน Developer เขียนข้อมูลโดยไม่ตั้งใจ

ตัวอย่าง Concept:

```text
save()
delete()
update()
performInsert()
performUpdate()
```

ให้ Throw Exception:

```text
ReadonlyDatabaseException
```

---

# 33. IMPORTANT — TWO LAYERS OF PROTECTION

ระบบต้องมี Protection อย่างน้อย 2 ชั้น:

### Layer 1 — Laravel

```text
Readonly Model
Query Guard
Service Architecture
Tests
```

### Layer 2 — MySQL

```text
SELECT ONLY
```

หาก Layer 1 ผิดพลาด:

```text
MySQL ต้องยังคง Block Write
```

นี่คือ Security Requirement สำคัญที่สุดของ Project

---

# 34. TESTING — READ ONLY

สร้าง Test:

### Test 1

```text
SELECT
```

ต้อง:

```text
PASS
```

### Test 2

```text
INSERT
```

ต้อง:

```text
BLOCK
```

### Test 3

```text
UPDATE
```

ต้อง:

```text
BLOCK
```

### Test 4

```text
DELETE
```

ต้อง:

```text
BLOCK
```

### Test 5

```text
CREATE TABLE
```

ต้อง:

```text
BLOCK
```

### Test 6

```text
DROP TABLE
```

ต้อง:

```text
BLOCK
```

---

# 35. SECURITY ACCEPTANCE TEST

ก่อน Production ต้องตรวจสอบว่า:

```text
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

# 36. UPDATED PROJECT ARCHITECTURE

Architecture สุดท้าย:

```text
                         ┌─────────────────────┐
                         │      Browser        │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │       Apache        │
                         │       XAMPP         │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │      Laravel        │
                         │                     │
                         │ Authentication      │
                         │ Authorization       │
                         │ Controllers         │
                         │ Services            │
                         │ Query Layer         │
                         └───────┬───────┬─────┘
                                 │       │
                    Application  │       │ RDU Read Only
                       DB        │       │
                                 ▼       ▼
                        ┌──────────┐  ┌──────────────┐
                        │ MySQL    │  │ HOSxP / MySQL│
                        │ RDU DB   │  │              │
                        │          │  │ SELECT ONLY  │
                        └──────────┘  └──────────────┘
```

---

# 37. UPDATED DATA FLOW

สำหรับ Dashboard:

```text
HOSxP
  ↓
SELECT ONLY
  ↓
RduDataSource
  ↓
RduQueryService
  ↓
RduCalculationService
  ↓
Cache
  ↓
Controller
  ↓
Blade
  ↓
Chart.js
```

สำหรับ Configuration:

```text
Admin
  ↓
Database Configuration Page
  ↓
Form Request
  ↓
DatabaseConfigService
  ↓
Validate
  ↓
Encrypt Password
  ↓
JSON
  ↓
Test Connection
  ↓
Verify Read-Only
  ↓
Save
  ↓
Audit Log
```

---

# 38. UPDATED DIRECTORY STRUCTURE

เพิ่มโครงสร้าง:

```text
app/
├── DataSources/
│   ├── RduDataSourceInterface.php
│   ├── MockRduDataSource.php
│   └── HosxpRduDataSource.php
│
├── Database/
│   ├── ReadonlyConnection.php
│   └── RduDatabaseConnectionFactory.php
│
├── Models/
│   ├── RduIndicator.php
│   ├── RduIndicatorResult.php
│   ├── AuditLog.php
│   └── Hosxp/
│       ├── ReadonlyHosxpModel.php
│       ├── HosxpPatient.php
│       ├── HosxpVisit.php
│       ├── HosxpDrug.php
│       └── HosxpDiagnosis.php
│
├── Services/
│   ├── DatabaseConfigService.php
│   ├── RduQueryService.php
│   ├── RduCalculationService.php
│   └── RduDatabaseConnectionFactory.php
│
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       └── DatabaseConfigController.php
│   │
│   └── Requests/
│       └── DatabaseConfigRequest.php
│
└── Exceptions/
    └── ReadonlyDatabaseException.php
```

---

# 39. UPDATED PHASE PLAN

ให้เพิ่ม Phase ดังนี้

## PHASE 1 — XAMPP Foundation

แทน Docker ด้วย:

```text
XAMPP
Apache
PHP
MySQL
Composer
Node
NPM
Git
```

Test:

```text
Apache
PHP
MySQL
Composer
Laravel
```

---

## PHASE 2 — Application Database

สร้าง RDU Application Database

รองรับ:

```text
users
rdu_indicators
rdu_indicator_results
audit_logs
settings
```

Test Migration / Seeder

---

## PHASE 3 — Read-Only Database Layer

สร้าง:

```text
RduDatabaseConnectionFactory
ReadonlyHosxpModel
RduQueryService
RduDataSourceInterface
```

Test:

```text
SELECT PASS
INSERT BLOCK
UPDATE BLOCK
DELETE BLOCK
CREATE BLOCK
DROP BLOCK
```

---

## PHASE 4 — JSON Database Configuration

สร้าง:

```text
Database Configuration Page
DatabaseConfigService
JSON Storage
Encryption
Connection Test
Read-Only Verification
```

Test:

```text
Save
Load
Validation
Encryption
Connection
Read-only verification
Permission
```

---

## PHASE 5 — Dashboard Foundation

สร้าง:

```text
Sidebar
Topbar
Overview
13 KPI
Charts
Filters
```

---

## PHASE 6 — KPI Engine

สร้าง:

```text
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

```text
Excel
CSV
PDF
```

---

## PHASE 9 — Security

ตรวจ:

```text
Authentication
Authorization
Read-only
JSON Security
Audit Log
Session
CSRF
XSS
SQL Injection
```

---

## PHASE 10 — Performance

ตรวจ:

```text
Query
Index
Cache
N+1
Pagination
Chart Payload
```

---

## PHASE 11 — Full QA

ทดสอบทั้งระบบ

---

## PHASE 12 — Final Refactor

ตรวจ:

```text
Duplicate Code
Dead Code
Security
Performance
Architecture
Naming
Documentation
```

แล้ว Run Test ใหม่ทั้งหมด

---

# 40. FINAL NON-NEGOTIABLE RULES

AI Coding Agent ต้องปฏิบัติตามกฎต่อไปนี้ตลอด Project:

### RULE 1

**HOSxP Database = READ ONLY**

### RULE 2

**ใช้ SELECT เท่านั้น**

### RULE 3

**ห้าม Migration ไป HOSxP**

### RULE 4

**ห้ามใช้ HOSxP Database เป็น Application Database**

### RULE 5

**Database Credential ต้องไม่ Hard-code**

### RULE 6

**Database Configuration เก็บใน JSON**

### RULE 7

**Password ต้อง Encrypt**

### RULE 8

**JSON ต้องอยู่นอก public directory**

### RULE 9

**Admin เท่านั้นที่แก้ Database Configuration**

### RULE 10

**ต้อง Test Connection ก่อนบันทึก Configuration**

### RULE 11

**ต้อง Verify ว่า Database User เป็น Read-Only**

### RULE 12

**Database Server ต้องเป็น Security Boundary สูงสุด**

### RULE 13

**ใช้ XAMPP แทน Docker**

### RULE 14

ทุก Phase ต้อง:

```text
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

### RULE 15

ห้ามเข้าสู่ Phase ถัดไป หาก Test ของ Phase ปัจจุบันยังไม่ผ่าน

---

# FINAL DEVELOPMENT COMMAND

เริ่มจาก:

**PHASE 0 — Architecture & Security Planning**

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

จากนั้นเริ่ม:

**PHASE 1 — XAMPP Foundation**

ห้ามสร้าง Feature อื่นจนกว่า Foundation จะผ่าน Test