# DEPLOYMENT.md — การพัฒนาและ deploy RDU Dashboard
## โรงพยาบาลเชียงกลาง จ.น่าน

พัฒนาบนเครื่อง XAMPP (Windows) → push ขึ้น GitHub → server production (AlmaLinux 9) ดึงไปใช้ด้วย `scripts/deploy.sh`
GitHub private repo `nuttapong39/RDU` เป็น source of truth ของโค้ด

- ติดตั้ง server production ใหม่ทั้งเครื่อง (ทีละ phase พร้อมรายการตรวจผ่าน): **`docs/SERVER-SETUP-ALMALINUX.md`**
- เอกสารนี้: ภาพรวม, การอัปเดตประจำ, เครื่อง dev, ไฟล์ที่ไม่อยู่ใน git, ปัญหาที่เคยเจอ

---

## 1. Architecture

```
 Dev (XAMPP, Windows)              GitHub (private)            Production — dashboard-srv (192.168.1.244)
 C:\xampp\htdocs\RDU   ─git push─►  nuttapong39/RDU  ─fetch─►  Proxmox LXC · AlmaLinux 9
 Apache + PHP 8.2                                              nginx + PHP-FPM 8.3 + PostgreSQL 16
 PostgreSQL 18 (rdu_dashboard)                                 /var/www/rdu → http://rdu.ckhospital.local
          │                                                              │
          └──────────── SELECT only (rdu_readonly) ──► HOSxP 192.168.1.119 (chk_hosxe) ◄──┘
                                                       HDC: opendata.moph.go.th (HTTPS)
```

| | Dev | Production |
|---|---|---|
| URL | `http://localhost/...` (XAMPP) | `http://rdu.ckhospital.local` (DNS A record `rdu` ในโซน AD `ckhospital.local`) |
| เข้าเครื่อง | — | `ssh nuttapong@192.168.1.244` (SSH key เท่านั้น — root/รหัสผ่านถูกปิด) |
| ฐานข้อมูลแอป | `rdu_dashboard` (ผู้ใช้ `postgres`) | `rdu_dashboard` (ผู้ใช้ `rdu_app`, localhost เท่านั้น) |
| Test | รัน `php artisan test` ที่นี่ | **ไม่รัน test** (ติดตั้งแบบ `--no-dev`) |
| Backup | — | `pg_dump` ทุกคืน 01:30 → `/backup/rdu/` (เก็บ 14 วัน) |

บน server เดียวกันมีระบบอื่น (`ck-asset-registry`) ใช้ IP ตรงๆ อยู่ — RDU ใช้ชื่อเครื่อง `rdu.ckhospital.local` เท่านั้น

---

## 2. อัปเดตโค้ด (ทำประจำ)

**เครื่อง dev:**
```powershell
cd C:\xampp\htdocs\RDU
php artisan test                      # ต้องไม่มี test ใหม่ที่ fail
git add <ไฟล์ที่แก้>
git commit -m "อธิบายการเปลี่ยนแปลง"
git push origin main
```

**Server:**
```bash
ssh nuttapong@192.168.1.244
cd /var/www/rdu && ./scripts/deploy.sh
```

`scripts/deploy.sh` ตรวจความปลอดภัย (`APP_ENV=production`, `DB_DATABASE=rdu_dashboard`, ไม่มีไฟล์ที่ git ติดตามถูกแก้บน server)
→ เปิดหน้าปิดปรับปรุง → ดึงโค้ด → `composer install --no-dev` → `npm ci && npm run build` → `migrate --force`
→ cache → `reload php-fpm` → เปิดเว็บ

- ล้มเหลวก่อนเปลี่ยนโค้ด → เว็บกลับมาใช้โค้ดเดิมเอง
- ล้มเหลวหลังเปลี่ยนโค้ด → เว็บค้างหน้าปิดปรับปรุง แก้สาเหตุแล้วรัน `./scripts/deploy.sh` ซ้ำ จะทำขั้นที่เหลือจนจบ
- ต้องมีค่าใหม่ใน `.env` → เพิ่มบน server ด้วยมือ **ก่อน** รันสคริปต์ (สคริปต์ไม่แตะ `.env`)
- แก้โค้ดบนเครื่อง dev เสมอ — server รับโค้ดผ่าน GitHub เท่านั้น

---

## 3. ตั้งเครื่อง dev ใหม่ (XAMPP)

| Software | หมายเหตุ |
|---|---|
| XAMPP | Apache + PHP 8.2+ เปิด extension `openssl, pdo_pgsql, pgsql, mbstring, fileinfo, bcmath` (แนะนำ `intl` — ไม่มีแล้ว test ที่ใช้ factory จะ fail) ใน `C:\xampp\php\php.ini` |
| PostgreSQL | ติดตั้งแยก สร้างฐาน `rdu_dashboard` |
| Composer 2, Node.js 20.19+, Git | |

```powershell
git clone https://github.com/nuttapong39/RDU.git C:\xampp\htdocs\RDU
cd C:\xampp\htdocs\RDU
composer install
npm ci && npm run build
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RduIndicatorSeeder
```

แล้วตั้งค่า HOSxP ผ่านหน้า **ตั้งค่าฐานข้อมูล HOSxP** (เข้าได้เฉพาะผู้ใช้ใน `SETTINGS_ADMIN_USERNAMES`, ค่าเริ่มต้น `nuttapong`)
— host `192.168.1.119`, database `chk_hosxe`, user `rdu_readonly` → ทดสอบการเชื่อมต่อ → บันทึก

> `php artisan db:seed` (ตัวเต็ม) สร้างผู้ใช้ `admin` รหัส `admin` — ใช้เฉพาะเครื่อง dev ห้ามรันบน production

---

## 4. ไฟล์ที่ไม่อยู่ใน git (ตั้งแยกต่อเครื่อง)

| ไฟล์ | หมายเหตุ |
|---|---|
| `.env` | production: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=daily`, `RDU_DB_PASSWORD=` (ว่าง) |
| `storage/app/private/config/rdu_database.json` | รหัส HOSxP เข้ารหัสด้วย `APP_KEY` ของเครื่องนั้น — ใช้ข้ามเครื่องได้เฉพาะเมื่อ `APP_KEY` เหมือนกัน |
| `vendor/`, `node_modules/`, `public/build/` | สร้างด้วย `composer install` / `npm ci && npm run build` |

**`APP_KEY` ห้ามเปลี่ยนบนเครื่องที่ใช้งานแล้ว** — เปลี่ยนแล้วจะถอดรหัส `rdu_database.json` ไม่ได้ ต้องกรอกรหัส HOSxP ใหม่
สำรอง `.env` และ `rdu_database.json` ไว้ **คนละที่** กับ backup ฐานข้อมูล (สองไฟล์รวมกันถอดรหัส HOSxP ได้)

---

## 5. ปัญหาที่เคยเจอ

| อาการ | สาเหตุ | แก้ |
|---|---|---|
| หน้าตั้งค่า HOSxP กด **บันทึก** แล้ว 500 (`file_put_contents ... Permission denied`) | `rdu_database.json` ไม่ใช่ของผู้ใช้ `nginx` | `sudo chown nginx:nginx` + `sudo chmod 600` ไฟล์นั้น |
| `deploy.sh`: "Files tracked by git were edited on the server" แต่ไม่ได้แก้อะไร | ใช้ `chmod -R 775` → ไฟล์ได้สิทธิ์ execute | โฟลเดอร์ `775` / ไฟล์ `664` ด้วย `find` (ดู `docs/SERVER-SETUP-ALMALINUX.md` 6.5) |
| เปิดชื่อเครื่องแล้วเจอระบบอื่น / `nginx: conflicting server name` | `server_name` ซ้ำกับระบบอื่นบน server | ระบบละ 1 ชื่อเครื่อง ห้ามใส่ IP ใน `server_name` ของ RDU |
| เครื่องผู้ใช้เปิด `rdu.ckhospital.local` ไม่ได้ | ไม่มี A record ใน DNS ของ AD (Univention UCS) | หน้าเว็บ UCS → Domain → DNS → `ckhospital.local` → Host Record (ดู `docs/SERVER-SETUP-ALMALINUX.md` การตัดสินใจข้อ 1) |
| `could not find driver` (เครื่อง dev) | ยังไม่เปิด `pdo_pgsql` ใน `php.ini` | เปิด extension แล้ว restart Apache |
| `password authentication failed for user "rdu_readonly"` | รหัสใน `rdu_database.json` ผิด หรือ `APP_KEY` ไม่ตรงกับตอนบันทึก | กรอกใหม่ในหน้าตั้งค่า HOSxP |
| `no pg_hba.conf entry for host "<IP>"` | HOSxP ยังไม่อนุญาต IP เครื่องนั้น | ผู้ดูแล HOSxP เพิ่มบรรทัดใน `pg_hba.conf` ของ HOSxP สำหรับ `rdu_readonly` |
| ไอคอน/ฟอนต์ไม่ขึ้น | เครื่องผู้ใช้ออก internet ไม่ได้ (Google Fonts / Material Symbols) | เปิดให้เครื่องผู้ใช้เข้าถึง `fonts.googleapis.com` / `fonts.gstatic.com` |

---

## 6. Security checklist (production)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `.env` สิทธิ์ `640`, `rdu_database.json` เป็น `nginx:nginx 600`
- [ ] `bootstrap/cache/config.php` ไม่มีรหัส HOSxP:
      `php -r '$c=require "bootstrap/cache/config.php"; echo ($c["database"]["connections"]["rdu"]["password"] ?? "")==="" ? "clean\n" : "LEAK\n";'`
- [ ] nginx `root` ชี้ `public/` และ `/.env` ตอบ `403`
- [ ] ไม่มีผู้ใช้ `admin`/`admin` ในระบบ
- [ ] `rdu_readonly` ไม่มีสิทธิ์เขียน (ตรวจด้วย query ใน `docs/SERVER-SETUP-ALMALINUX.md` Phase 7)
- [ ] SSH: root และรหัสผ่านถูกปิด
- [ ] backup ล่าสุดใน `/backup/rdu/` อายุไม่เกิน 1 วัน

---

**Maintainer:** Nuttapong (ckhossystem@gmail.com)
**Last updated:** 2026-10-07
