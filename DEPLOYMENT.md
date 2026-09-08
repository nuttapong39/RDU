# DEPLOYMENT.md — คู่มือการติดตั้ง RDU Dashboard
## โรงพยาบาลเชียงกลาง จ.น่าน

คู่มือนี้ครอบคลุมทั้ง **การติดตั้งครั้งแรก** และ **การอัปเดตรายวัน** บนเครื่อง target ใน VLAN โรงพยาบาล
โดยใช้ GitHub (Private repo) เป็น source of truth

---

## สารบัญ

- [1. Architecture Overview](#1-architecture-overview)
- [2. Prerequisites (สิ่งที่ต้องติดตั้งก่อน)](#2-prerequisites)
- [3. Dev Machine (บ้าน) — Push code ขึ้น GitHub](#3-dev-machine-บ้าน)
- [4. Target Machine (Office) — Clone ครั้งแรก](#4-target-machine-office--clone-ครั้งแรก)
- [5. Target Machine — Update ปกติ](#5-target-machine--update-ปกติ)
- [6. Configuration](#6-configuration)
- [7. Apache Setup](#7-apache-setup)
- [8. Troubleshooting](#8-troubleshooting)
- [9. Security Checklist](#9-security-checklist)

---

## 1. Architecture Overview

```
┌─────────────────────┐         VPN         ┌─────────────────────┐
│   บ้าน (Dev)         │  ────────────────►  │  Office VLAN         │
│                     │                     │  192.168.1.0/24      │
│  C:\xampp\htdocs\RDU│                     │                      │
│         │           │                     │  ┌────────────────┐  │
│         ▼           │                     │  │ Target Machine │  │
│      git push       │                     │  │  (Apache+PHP)  │  │
│         │           │                     │  │                │  │
└─────────┼───────────┘                     │  │  git pull      │  │
          │                                 │  │      ▲         │  │
          ▼                                 │  └──────┼─────────┘  │
   ┌─────────────┐                          │         │            │
   │  GitHub     │  ◄───── HTTPS ─────────  │         │  ┌───────┐ │
   │  (Private)  │        (token/SSH)       │         └──┤ HOSxP │ │
   └─────────────┘                          │            │ DB    │ │
                                            │            └───────┘ │
                                            └──────────────────────┘
```

**ข้อสำคัญ:**
- **Code** (blade views, controllers, migrations) → ผ่าน GitHub
- **Config** (`.env`, `storage/app/private/config/rdu_database.json`) → ตั้งค่าแยกในแต่ละเครื่อง (ไม่อยู่ใน git — มี `.example` เป็น template)
- **HOSxP credentials** → ไม่อยู่ใน GitHub เด็ดขาด (encrypted แล้วเก็บใน JSON local เท่านั้น)

---

## 2. Prerequisites

**ต้องติดตั้งก่อนบน target machine (ครั้งเดียว):**

| Software | Version | Note |
|---|---|---|
| **XAMPP** | Apache + PHP **8.3+** | https://www.apachefriends.org/ |
| **PostgreSQL** | 15+ | ติดตั้งแยก (ไม่รวมใน XAMPP) — https://www.postgresql.org/download/ |
| **Composer** | 2.x | https://getcomposer.org/download/ |
| **Node.js** | 20+ LTS | https://nodejs.org/ |
| **Git** | 2.30+ | https://git-scm.com/download/win |

**PHP Extensions ที่ต้องเปิด** (ใน `C:\xampp\php\php.ini`):
```ini
extension=openssl
extension=pdo_pgsql
extension=pgsql
extension=mbstring
extension=tokenizer
extension=xml
extension=ctype
extension=fileinfo
extension=bcmath
```

**ตรวจว่าครบ:**
```powershell
php -v                  # ต้องขึ้น PHP 8.3.x
composer --version      # Composer 2.x
node -v                 # v20+
git --version           # 2.30+
psql --version          # PostgreSQL 15+
```

---

## 3. Dev Machine (บ้าน)

### 3.1 ครั้งแรก — Push โปรเจกต์ขึ้น GitHub

**Step 1: สร้าง GitHub Private Repo**

1. เปิด https://github.com/new
2. Repository name: `RDU` (หรือ `rdu-dashboard`)
3. Visibility: **Private** ✅
4. ⚠️ **อย่า** เลือก "Add README" / ".gitignore" / "license" (โปรเจกต์มีอยู่แล้ว)
5. คลิก **Create repository**
6. Copy URL ที่ได้ (เช่น `https://github.com/nutta/RDU.git`)

**Step 2: สร้าง Personal Access Token** (สำหรับ push/pull)

1. https://github.com/settings/tokens → **Generate new token (classic)**
2. Note: `RDU Deployment`
3. Expiration: **90 days** (หรือ No expiration ตามความเสี่ยงที่รับได้)
4. Scopes: เลือก `repo` (ทั้ง block) ✅
5. Generate → **copy token เก็บไว้ทันที** (จะไม่ขึ้นอีก)

**Step 3: Push code ครั้งแรก (จาก Dev machine)**

```powershell
cd C:\xampp\htdocs\RDU

# ตรวจว่า sensitive files ถูก gitignore แล้ว
git status                              # ไม่ควรเห็น .env / rdu_database.json

git remote add origin https://github.com/YOUR_USERNAME/RDU.git
git branch -M main
git push -u origin main
# ใส่ username + paste token เป็น password
```

### 3.2 Update ปกติ — Push การเปลี่ยนแปลง

```powershell
cd C:\xampp\htdocs\RDU

git add .
git commit -m "อธิบายการเปลี่ยนแปลง"
git push
```

---

## 4. Target Machine (Office) — Clone ครั้งแรก

### 4.1 Clone code

```powershell
cd C:\xampp\htdocs

# Clone จาก GitHub — ใส่ username + paste token เป็น password
git clone https://github.com/YOUR_USERNAME/RDU.git

cd RDU
```

### 4.2 Setup PHP dependencies

```powershell
composer install --optimize-autoloader --no-dev
```

> **หมายเหตุ:** ใช้ `--no-dev` สำหรับ production เพื่อประหยัดพื้นที่ (ไม่ติดตั้ง phpunit ฯลฯ)
> ถ้าจะรัน test ให้ใช้ `composer install` เฉย ๆ

### 4.3 Setup Node dependencies + Build assets

```powershell
npm ci
npm run build
```

จะได้ไฟล์ `public/build/assets/*.css` และ `*.js` (Tailwind compiled)

### 4.4 Configure `.env`

```powershell
copy .env.example .env
notepad .env
```

**ค่าที่ต้องแก้:**
```env
APP_ENV=production                  # เปลี่ยนจาก local
APP_DEBUG=false                     # เปลี่ยนจาก true
APP_URL=http://192.168.1.XX         # IP ของเครื่องนี้ในวง VLAN

DB_HOST=127.0.0.1                   # PostgreSQL local (rdu_dashboard)
DB_DATABASE=rdu_dashboard
DB_USERNAME=postgres
DB_PASSWORD=your_local_postgres_password
```

### 4.5 Generate application key

```powershell
php artisan key:generate
```

### 4.6 สร้าง PostgreSQL Database

```powershell
# สร้าง database rdu_dashboard (สำหรับ users, sessions, audit_logs)
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -c "CREATE DATABASE rdu_dashboard;"
```

### 4.7 Run migrations + seed

```powershell
php artisan migrate --seed
```

จะสร้าง:
- Users table + user `admin/admin` (สำหรับ login ครั้งแรก)
- Sessions, Cache, Jobs tables
- Audit logs
- RDU Indicators (13 KPIs)

### 4.8 Setup HOSxP Connection

**วิธี A: ผ่าน UI (แนะนำ)**

1. เปิด browser: `http://localhost/RDU/public/login`
2. Login: `admin` / `admin`
3. ไปที่ **ตั้งค่าฐานข้อมูล HOSxP** (sidebar)
4. กรอก:
   - Host: `192.168.1.119` (IP ของ HOSxP replica)
   - Port: `5432`
   - Database: `chk_hosxe`
   - Schema: `rdu_kpi`
   - Username: `rdu_readonly`
   - Password: `xxx` (password ของ user rdu_readonly)
5. **Test Connection** — ต้องขึ้น "เชื่อมต่อสำเร็จ และยืนยัน Read-Only"
6. **บันทึก** — ระบบจะ encrypt password + เก็บใน `storage/app/private/config/rdu_database.json`

**วิธี B: Copy JSON จากเครื่องอื่น (ถ้ามี)**

```powershell
# Copy จากเครื่องอื่นที่ตั้งค่าไว้แล้ว
copy \\OTHER-PC\C$\xampp\htdocs\RDU\storage\app\private\config\rdu_database.json .\storage\app\private\config\
```

⚠️ **ห้าม** commit `rdu_database.json` ขึ้น git — password ถูก encrypt ด้วย `APP_KEY` ของเครื่องต้นทาง จะ decrypt ไม่ออกในเครื่องอื่น

### 4.9 Set folder permissions

```powershell
# Windows: ให้ Apache/IIS user เขียน storage + bootstrap/cache ได้
icacls storage /grant "Users:(OI)(CI)F" /T
icacls bootstrap\cache /grant "Users:(OI)(CI)F" /T
```

### 4.10 ทดสอบ

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

เปิด browser จากเครื่องอื่นใน VLAN: `http://192.168.1.XX:8000` → ต้อง login ได้

---

## 5. Target Machine — Update ปกติ

**เมื่อมีการ push code ใหม่จาก dev machine:**

```powershell
cd C:\xampp\htdocs\RDU

# วิธีเร็ว: ใช้สคริปต์อัตโนมัติ
.\deploy.ps1

# วิธี manual:
git pull origin main
composer install --optimize-autoloader --no-dev
npm ci && npm run build
php artisan migrate --force            # --force = ไม่ถามใน production
php artisan view:clear
php artisan cache:clear
php artisan config:clear
```

**หลัง update — ตรวจว่าใช้งานได้:**
```powershell
php artisan test --filter=DashboardTest
```

---

## 6. Configuration

### 6.1 `.env` — ใช้เฉพาะเครื่องนั้น (ไม่ต้อง sync ผ่าน git)

โดยพื้นฐานใช้จาก `.env.example` แล้วแก้ 4 ค่านี้ต่อเครื่อง:
- `APP_KEY` (สร้างด้วย `php artisan key:generate`)
- `APP_URL` (IP + port ของเครื่องนั้น)
- `DB_PASSWORD` (password PostgreSQL local)
- `APP_DEBUG=false` (production)

### 6.2 `storage/app/private/config/rdu_database.json` — HOSxP config

**อย่า commit** — เพราะ:
1. Password ถูก encrypt ด้วย `APP_KEY` เฉพาะเครื่องนั้น (portable ไม่ได้)
2. เก็บ credential ของ HOSxP (แม้ encrypt แล้วก็ไม่ควรอยู่ใน git)

**Template:** ดู `storage/app/private/config/rdu_database.json.example`

### 6.3 File permissions

```
storage/          → readable + writable โดย Apache/PHP user
bootstrap/cache/  → readable + writable
public/           → world-readable (Apache DocumentRoot)
```

---

## 7. Apache Setup

**แก้ไข `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:**

```apache
<VirtualHost *:80>
    ServerName rdu.local
    DocumentRoot "C:/xampp/htdocs/RDU/public"

    <Directory "C:/xampp/htdocs/RDU/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/rdu-error.log"
    CustomLog "logs/rdu-access.log" common
</VirtualHost>
```

**เพิ่มใน `C:\Windows\System32\drivers\etc\hosts`:**
```
127.0.0.1  rdu.local
```

**Restart Apache** ผ่าน XAMPP Control Panel

---

## 8. Troubleshooting

### 8.1 `php artisan` failed: `could not find driver`

PHP ยังไม่เปิด `pdo_pgsql` — แก้ `C:\xampp\php\php.ini` เอา `;` หน้า `extension=pdo_pgsql` ออก แล้ว restart Apache

### 8.2 `SQLSTATE[08006] password authentication failed for user "rdu_readonly"`

- **สาเหตุ:** JSON config ไม่ถูกต้อง หรือ user `rdu_readonly` ใน HOSxP ไม่มี/password ผิด
- **แก้:** ไปที่ **ตั้งค่าฐานข้อมูล HOSxP** ในระบบ กรอกใหม่ + Test Connection

### 8.3 `database "chk_hosxe" does not exist`

- **สาเหตุ:** ชื่อ database ใน JSON ผิด
- **แก้:** ตรวจกับ HOSxP admin ว่า database ชื่ออะไร แล้วแก้ใน UI

### 8.4 `relation "ovst" does not exist` (แต่ table มีจริง)

- **สาเหตุ:** `rdu_readonly` ไม่มี `USAGE` บน schema หรือ views ยังไม่ได้สร้าง
- **แก้:** SSH เข้า HOSxP server แล้วรัน:
  ```sql
  GRANT USAGE ON SCHEMA rdu_kpi TO rdu_readonly;
  GRANT SELECT ON ALL TABLES IN SCHEMA rdu_kpi TO rdu_readonly;
  ```

### 8.5 Icons/fonts ไม่โหลด (empty box)

- **สาเหตุ:** เครื่อง target ไม่ต่ออินเทอร์เน็ต (โหลด Google Fonts + Material Symbols ไม่ได้)
- **แก้:** ดาวน์โหลด fonts มาเก็บ local — ดูหัวข้อ **Offline Fonts** ใน `docs/` (จะทำในอนาคต)

### 8.6 `git pull` conflict

```powershell
# Reset local ให้ตรงกับ remote (ทิ้งการแก้ในเครื่อง)
git fetch origin
git reset --hard origin/main
```

⚠️ **ระวัง:** ทิ้งการแก้ใน `.env` / `rdu_database.json` ด้วยไหม? ไม่ทิ้ง — เพราะไฟล์เหล่านี้ gitignore แล้ว

---

## 9. Security Checklist

**ก่อน push ครั้งแรก — ตรวจให้ครบ:**

- [ ] `.gitignore` มี `/storage/app/private/config/*.json`
- [ ] `.gitignore` มี `.env`
- [ ] `.gitignore` มี `/vendor` และ `/node_modules`
- [ ] `.gitignore` มี `/public/build`
- [ ] Repo บน GitHub เป็น **Private** ไม่ใช่ Public
- [ ] ไม่มี password / API key / token ใน source code (grep หา `password.*=.*[^""]`)

**บน target machine (production):**

- [ ] `.env` มี `APP_DEBUG=false`
- [ ] `.env` มี `APP_ENV=production`
- [ ] File `.env` permission 600 (Windows: จำกัดสิทธิ์อ่าน)
- [ ] `storage/app/private/config/rdu_database.json` permission 600
- [ ] Default admin password (`admin/admin`) ถูกเปลี่ยนแล้ว
- [ ] Apache DocumentRoot ชี้ไป `public/` เท่านั้น (ไม่ใช่ root ของโปรเจกต์)
- [ ] HOSxP user `rdu_readonly` มีสิทธิ์ SELECT บน `rdu_kpi` schema เท่านั้น (ไม่ใช่ทั้งหมด)

---

## Appendix: Files ที่สำคัญ

| File | Commit ไหม? | หมายเหตุ |
|---|---|---|
| `.env` | ❌ | ใช้ `.env.example` เป็น template |
| `storage/app/private/config/rdu_database.json` | ❌ | encrypted per-machine |
| `storage/app/private/config/rdu_database.json.example` | ✅ | template ให้ target ดู structure |
| `vendor/` | ❌ | ติดตั้งด้วย `composer install` |
| `node_modules/` | ❌ | ติดตั้งด้วย `npm ci` |
| `public/build/` | ❌ | สร้างด้วย `npm run build` |
| `database/migrations/` | ✅ | ต้องมี — target รัน `php artisan migrate` |
| `database/seeders/` | ✅ | ต้องมี — target รัน `php artisan db:seed` (ครั้งแรก) |
| `resources/views/*.bak-*` | ❌ | backup ชั่วคราว (gitignored) |

---

**Maintainer:** Nuttapong (ckhossystem@gmail.com)
**Last updated:** 2026-09-09
