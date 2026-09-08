# DATABASE CONFIGURATION
## RDU Dashboard — Database Configuration Specification

---

## Database User Setup

Database ต้นทาง (HOSxP) เป็น PostgreSQL ให้สร้าง Database User สำหรับ RDU โดยเฉพาะ:

```
rdu_readonly
```

และให้สิทธิ์เฉพาะ `SELECT`:

```sql
CREATE USER rdu_readonly WITH PASSWORD 'STRONG_PASSWORD';

GRANT CONNECT ON DATABASE hosxp TO rdu_readonly;
GRANT USAGE ON SCHEMA public TO rdu_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO rdu_readonly;
```

> **หมายเหตุ:** คำสั่งดังกล่าวเป็นเพียงตัวอย่าง ห้ามนำ Password ตัวอย่างไปใช้จริง

ห้ามใช้:
```sql
GRANT ALL PRIVILEGES
```

ห้ามใช้ Database User ของ Application ที่มีสิทธิ์เขียนข้อมูล

---

## Separate Database Connection

ให้แยก Database Connection สำหรับ RDU โดยเฉพาะ:

```
Primary Application Database
RDU Read-Only Database
```

Configuration Concept:

```
database.connections.rdu
```

Connection นี้ต้องใช้ Credential ของ Read-Only Database User เท่านั้น

---

## Application Database

สำหรับ Development ให้ติดตั้ง PostgreSQL แยกต่างหากจาก XAMPP (XAMPP ไม่ได้รวม PostgreSQL มาด้วย):

```
rdu_dashboard
```

Database นี้ใช้สำหรับ:

```
users
sessions
cache
audit_logs
rdu_indicators
application_settings
```

ส่วน HOSxP ใช้ `rdu_readonly` — แยก Connection ออกจากกันอย่างชัดเจน

### Application Database Tables

หากระบบต้องมี Table ให้แยกเป็น RDU Application Database ออกจาก HOSxP Database:

```
users
sessions
cache
audit_logs
rdu_indicators
rdu_indicator_results
```

Architecture:

```
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

## Configuration Page

URL:

```
/admin/settings/database
```

หรือ:

```
/settings/database
```

เฉพาะ **Admin** เท่านั้นที่สามารถเข้าหน้านี้ได้

---

## JSON Configuration

**ห้าม Hard-code Database Configuration ใน Source Code**

ให้จัดเก็บ Configuration เป็น JSON File:

```
storage/app/private/config/rdu_database.json
```

โครงสร้าง JSON:

```json
{
    "driver": "pgsql",
    "host": "192.168.1.100",
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

## Password Encryption

Password ของ Database **ห้ามเก็บ Plain Text**

Encryption Flow:

```
User Input
    ↓
Laravel Validation
    ↓
Encrypt Password
    ↓
JSON
```

Decryption Flow:

```
JSON
 ↓
Decrypt
 ↓
Database Connection
```

ห้ามส่ง Password กลับไปแสดงใน Browser

---

## JSON File Security

JSON Configuration File ต้อง:

- ไม่อยู่ใน `public/`
- ไม่สามารถเปิดผ่าน Browser
- ไม่สามารถ Download ผ่าน URL
- ไม่ Commit เข้า Git
- ไม่อยู่ใน Repository
- มี Permission ที่เหมาะสม

ต้องเพิ่มใน `.gitignore`:

```
/storage/app/private/config/*.json
```

---

## Config Service

สร้าง Service: `DatabaseConfigService`

Methods:

```
load()
save()
validate()
testConnection()
getConnectionConfig()
encryptPassword()
decryptPassword()
```

Architecture:

```
Controller
    ↓
DatabaseConfigService
    ↓
JSON Configuration
```

ห้ามให้ Controller อ่าน JSON โดยตรง

---

## Database Connection Factory

สร้าง: `RduDatabaseConnectionFactory`

Flow:

```
อ่าน JSON
      ↓
Validate Config
      ↓
สร้าง Dynamic Connection
      ↓
เชื่อมต่อ PostgreSQL
      ↓
ใช้ SELECT ONLY
```

---

## Config Version / Backup

เมื่อ Admin เปลี่ยน Configuration ให้รองรับ:

```
Current Configuration
Previous Configuration
Updated At
Updated By
```

ห้ามเก็บ Password ใน Audit Log

---

## Audit Log for Config Changes

ทุกครั้งที่มีการเปลี่ยน Database Configuration ให้บันทึก:

```
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

```
Password
Decrypted Secret
```

Event Name: `DATABASE_CONFIG_UPDATED`
