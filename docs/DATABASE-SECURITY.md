# DATABASE SECURITY
## RDU Dashboard — Database Security Requirements

---

## Critical Requirement: Database Must Be Read-Only

**นี่เป็นข้อกำหนดด้าน Security ที่สำคัญที่สุดของระบบ**

ระบบ RDU Dashboard จะเชื่อมต่อกับ Database ของโรงพยาบาลเพื่อใช้สำหรับ **อ่านข้อมูลเท่านั้น**

Database Connection ที่ใช้สำหรับ RDU Dashboard ต้องมีสิทธิ์:

```
SELECT ONLY
```

ห้ามมีสิทธิ์:

```
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

## Absolute Rule — No Write Query

ใน Code ของ RDU Data Layer **ห้ามมีคำสั่งเหล่านี้**:

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

## Allow Only SELECT

อนุญาตใช้งาน:

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

รวมถึง SQL ที่เป็น `SELECT` เท่านั้น

---

## Database Query Guard

สร้างระบบตรวจสอบ Query ใน Development / Testing

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

Class ที่สร้าง: `RduDatabaseQueryGuard`

**หมายเหตุ:** ใน Production การป้องกันหลักต้องอยู่ที่ **Database Permission** ไม่ใช่ Query Guard เพียงอย่างเดียว

---

## Database Migration Rule

**ห้าม Run Laravel Migration กับ HOSxP / Hospital Database โดยเด็ดขาด**

ห้ามทำ:

```bash
php artisan migrate
```

บน Database ต้นทางของโรงพยาบาล

คำสั่ง `php artisan migrate` ให้ใช้กับ **RDU Application Database เท่านั้น**

---

## HOSxP Model Protection

Model ที่ผูกกับ HOSxP ต้องถือว่าเป็น **READ ONLY MODEL**:

```
HosxpPatient
HosxpVisit
HosxpDrug
HosxpDiagnosis
```

ห้ามเรียก:

```php
$model->save();
$model->delete();
$model->update();
$model->create();
```

---

## Read-Only Model (Base Class)

สร้าง Base Model:

```
ReadonlyHosxpModel
```

Override write operations:

```
save()
delete()
update()
performInsert()
performUpdate()
```

ให้ Throw Exception: `ReadonlyDatabaseException`

---

## Strict Data Access Rule

สร้าง `RduQueryService` — ทุก Query ที่อ่าน HOSxP ต้องผ่าน Service นี้

ไม่ควรให้ Controller ยิง Query HOSxP โดยตรง

---

## Two Layers of Protection

### Layer 1 — Laravel
- Readonly Model
- Query Guard
- Service Architecture
- Tests

### Layer 2 — PostgreSQL
- SELECT ONLY Privilege

หาก Layer 1 ผิดพลาด → PostgreSQL ต้องยังคง Block Write

**นี่คือ Security Requirement สำคัญที่สุดของ Project**

---

## Security Acceptance Test (Pre-Production Checklist)

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
