# คู่มือติดตั้ง RDU Dashboard สำหรับโรงพยาบาลอื่น

คู่มือนี้สำหรับโรงพยาบาลที่จะนำ RDU Dashboard ไปติดตั้งใช้เอง เลือกวิธีติดตั้ง **อย่างใดอย่างหนึ่ง**:

| | แบบ A — XAMPP (Windows) | แบบ B — Server (AlmaLinux 9) |
|---|---|---|
| เหมาะกับ | ทดลองใช้ / เครื่องเดียว / หน่วยงานที่ไม่มี server | ใช้งานจริงระยะยาว หลายผู้ใช้ |
| Web server | Apache (XAMPP) | nginx + PHP-FPM |
| ความยาก | ง่าย | ปานกลาง (ต้องใช้ command line) |
| อัปเดตโค้ด | `.\deploy.ps1` | `./scripts/deploy.sh` |

ทั้งสองแบบต้องทำ **ส่วนที่ 0 (เตรียมข้อมูล)** ก่อน และทำ **ส่วนที่ 3 (ตั้งค่าครั้งแรกในระบบ)** หลังติดตั้งเสร็จ

> **หลักความปลอดภัย:** ระบบอ่านฐานข้อมูล HOSxP แบบ **SELECT เท่านั้น** (`rdu_readonly`) ไม่เขียนข้อมูลลง HOSxP เด็ดขาด
> อย่าใช้ user ที่มีสิทธิ์เขียนในหน้าตั้งค่า HOSxP

---

## ส่วนที่ 0 — เตรียมก่อนติดตั้ง

### 0.1 สิ่งที่ต้องขอจากผู้ดูแลฐานข้อมูล HOSxP ของโรงพยาบาล

| รายการ | ตัวอย่าง |
|---|---|
| IP / พอร์ตของ HOSxP (PostgreSQL) | `192.168.1.119` / `5432` |
| ชื่อฐานข้อมูล HOSxP | `hosxp` |
| user สิทธิ์ **SELECT อย่างเดียว** | `rdu_readonly` + รหัสผ่าน |
| อนุญาต IP เครื่องที่ลง RDU ใน `pg_hba.conf` ของ HOSxP | บรรทัด `host <db> rdu_readonly <IP เครื่อง RDU>/32 scram-sha-256` |

ถ้ายังไม่มี user อ่านอย่างเดียว ให้ผู้ดูแล HOSxP รัน (ปรับ schema/ตารางตามที่โรงพยาบาลใช้):

```sql
CREATE USER rdu_readonly WITH PASSWORD 'รหัสผ่านที่เดายาก';
GRANT CONNECT ON DATABASE hosxp TO rdu_readonly;
GRANT USAGE ON SCHEMA public TO rdu_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO rdu_readonly;
```

รายละเอียดเพิ่ม: [DATABASE-CONFIGURATION.md](./DATABASE-CONFIGURATION.md)

### 0.2 ข้อมูลโรงพยาบาลที่จะกรอกในระบบ

ชื่อโรงพยาบาล, ชื่อย่อ, จังหวัด, รหัสหน่วยบริการ 5 หลัก, ไฟล์โลโก้ (PNG/JPG)

### 0.3 บัญชีผู้ใช้

ผู้ใช้ **เข้าสู่ระบบด้วยบัญชี HOSxP เดิม** (ตาราง `officer`) ไม่ต้องสร้างบัญชีใหม่ในระบบ
ให้เตรียมชื่อผู้ใช้ HOSxP ของ **ผู้ดูแลระบบ** (จะใส่ใน `SETTINGS_ADMIN_USERNAMES`) และของ **เภสัชกร** ที่จะตั้งรหัสยา

### 0.4 ซอฟต์แวร์ที่ต้องมี (ทั้งสองแบบ)

PHP 8.2+ (extension: `pdo_pgsql, pgsql, mbstring, intl, bcmath, openssl, fileinfo, zip, sodium`), PostgreSQL 15+ (ฐานข้อมูลของแอปเอง),
Composer 2, Node.js 20.19+ และ npm, Git

---

## แบบ A — ติดตั้งผ่าน XAMPP (Windows)

### A1. ติดตั้งโปรแกรม

1. **XAMPP** (PHP 8.2 ขึ้นไป) → ติดตั้งที่ `C:\xampp`
2. **PostgreSQL** (15+) → จดรหัสผู้ใช้ `postgres` ที่ตั้งตอนติดตั้ง
3. **Composer**, **Node.js LTS**, **Git for Windows**

เปิด extension ใน `C:\xampp\php\php.ini` (ลบ `;` หน้าบรรทัด):

```ini
extension=pdo_pgsql
extension=pgsql
extension=mbstring
extension=intl
extension=bcmath
extension=openssl
extension=fileinfo
extension=zip
extension=sodium
date.timezone = Asia/Bangkok
```

จากนั้น **Restart Apache** ใน XAMPP Control Panel แล้วตรวจ:

```powershell
C:\xampp\php\php.exe -m | Select-String "pdo_pgsql|intl|mbstring|bcmath"
```

ต้องขึ้นครบทุกตัว (ถ้าไม่ขึ้น → ดูหัวข้อ "แก้ปัญหา" ด้านล่าง)

### A2. ดึงโค้ดและติดตั้ง

```powershell
cd C:\xampp\htdocs
git clone https://github.com/nuttapong39/RDU.git
cd RDU
composer install --no-dev --optimize-autoloader
npm ci
npm run build
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

### A3. สร้างฐานข้อมูลของแอป

```powershell
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -c "CREATE DATABASE rdu_dashboard ENCODING 'UTF8' TEMPLATE template0;"
```

(เปลี่ยนเลข `16` ตามเวอร์ชันที่ติดตั้ง)

### A4. แก้ไฟล์ `.env`

เปิดด้วย Notepad แล้วแก้อย่างน้อย:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost/RDU/public        # หรือ URL ที่ใช้จริง

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rdu_dashboard                  # ห้ามชี้ไป HOSxP
DB_USERNAME=postgres
DB_PASSWORD=รหัสของ postgres

RDU_DB_PASSWORD=                           # เว้นว่าง — รหัส HOSxP ตั้งในหน้าเว็บ
SETTINGS_ADMIN_USERNAMES=setup,ชื่อผู้ใช้HOSxPของผู้ดูแล   # คั่นด้วย , ตัวพิมพ์เล็ก
```

> `setup` คือบัญชีชั่วคราวสำหรับเข้าครั้งแรก (ขั้น A6) — ลบทิ้งหลังตั้งค่าเสร็จ

### A5. สร้างตารางและข้อมูลตั้งต้น

```powershell
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan db:seed --class=RduIndicatorSeeder
```

> **ห้ามรัน `php artisan db:seed` แบบเต็ม** — จะสร้างผู้ใช้ `admin` รหัส `admin`
> ห้าม `migrate` กับฐาน HOSxP (`DB_DATABASE` ต้องเป็น `rdu_dashboard` เสมอ)

### A6. สร้างบัญชีชั่วคราวสำหรับเข้าครั้งแรก

ก่อนตั้งค่า HOSxP ยังล็อกอินด้วยบัญชี HOSxP ไม่ได้ จึงต้องมีบัญชีในเครื่องหนึ่งบัญชี (ตั้งรหัสผ่านเอง อย่าใช้ `admin`):

```powershell
C:\xampp\php\php.exe artisan tinker --execute="App\Models\User::create(['name'=>'Setup','username'=>'setup','email'=>'setup@rdu.local','password'=>Illuminate\Support\Facades\Hash::make('ตั้งรหัสผ่านที่นี่')]);"
```

### A7. ตั้ง Apache

**วิธีง่าย (เข้าผ่าน `http://localhost/RDU/public`)** — ไม่ต้องตั้งอะไรเพิ่ม ถ้า `mod_rewrite` เปิดอยู่ (ค่าเริ่มต้นของ XAMPP เปิดอยู่แล้ว)

**วิธีชื่อโดเมน (`http://rdu.local`)** — เพิ่มใน `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (ดูตัวอย่างใน `vhost.conf`):

```apache
<VirtualHost *:80>
    ServerName rdu.local
    DocumentRoot "C:/xampp/htdocs/RDU/public"
    <Directory "C:/xampp/htdocs/RDU/public">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

แล้วเพิ่ม `127.0.0.1 rdu.local` ใน `C:\Windows\System32\drivers\etc\hosts` (เปิด Notepad แบบ Run as administrator) และ Restart Apache
อย่าลืมแก้ `APP_URL` ใน `.env` ให้ตรง

### A8. ทดสอบ

เปิดเบราว์เซอร์ไปที่ URL ที่ตั้งไว้ → ควรเห็นหน้า Login → ล็อกอินด้วย `setup` → ไปต่อ **ส่วนที่ 3**

### A9. อัปเดตโค้ดครั้งถัดไป

```powershell
cd C:\xampp\htdocs\RDU
.\deploy.ps1
```

---

## แบบ B — ติดตั้งบน Server (AlmaLinux 9 + nginx + PHP-FPM)

ขั้นตอนเต็ม (ตั้งระบบปฏิบัติการ, firewall, SSH, backup, HTTPS) อยู่ใน
[SERVER-SETUP-ALMALINUX.md](./SERVER-SETUP-ALMALINUX.md) — ส่วนนี้สรุปเฉพาะลำดับที่โรงพยาบาลใหม่ต้องทำ
(ตัวอย่างใช้ path `/var/www/rdu`, ผู้ดูแล `<ADMIN_USER>`)

### B1. ติดตั้งแพ็กเกจพื้นฐาน

ทำตาม SERVER-SETUP **Phase 1–5**: อัปเดตระบบ → PHP 8.3 + PHP-FPM → PostgreSQL 16 → nginx → Composer/Node.js/Git
ตรวจให้ได้ว่า:

```bash
php -v                                   # 8.3.x
php -m | grep -Ei 'pdo_pgsql|intl|mbstring|bcmath|openssl|fileinfo|sodium|zip'
systemctl is-active nginx php-fpm postgresql
```

### B2. สร้างฐานข้อมูลของแอป

```bash
sudo -u postgres psql <<'EOF'
CREATE ROLE rdu_app LOGIN PASSWORD '<APP_DB_PASSWORD>';
CREATE DATABASE rdu_dashboard OWNER rdu_app ENCODING 'UTF8' TEMPLATE template0;
EOF
```

### B3. ดึงโค้ด

```bash
sudo mkdir -p /var/www && sudo chown <ADMIN_USER>:nginx /var/www
git clone https://github.com/nuttapong39/RDU.git /var/www/rdu
cd /var/www/rdu
```

> ถ้า fork เป็น repo private ให้ตั้ง Deploy key แบบอ่านอย่างเดียว (SERVER-SETUP Phase 5) แล้ว clone ด้วย SSH URL

### B4. สร้าง `.env`

```bash
cp .env.example .env
nano .env
```

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://rdu.โดเมนของโรงพยาบาล
LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rdu_dashboard          # ห้ามชี้ไป HOSxP — deploy.sh ตรวจและปฏิเสธถ้าไม่ใช่ชื่อนี้
DB_USERNAME=rdu_app
DB_PASSWORD=<APP_DB_PASSWORD>

RDU_DB_PASSWORD=                   # เว้นว่าง
SETTINGS_ADMIN_USERNAMES=setup,ชื่อผู้ใช้HOSxPของผู้ดูแล
```

### B5. ติดตั้ง dependency และสร้างตาราง

```bash
chmod 640 .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RduIndicatorSeeder --force
```

> **`APP_KEY` ห้ามเปลี่ยนหลังเริ่มใช้งาน** — รหัส HOSxP ที่บันทึกไว้เข้ารหัสด้วยคีย์นี้ เปลี่ยนแล้วถอดไม่ได้ ต้องกรอกใหม่
> สำรอง `.env` ไว้คนละที่กับ backup ฐานข้อมูล

### B6. สิทธิ์ไฟล์ (สำคัญ — พลาดบ่อย)

```bash
sudo chown -R <ADMIN_USER>:nginx /var/www/rdu
sudo find /var/www/rdu -type d -exec chmod 775 {} \;
sudo find /var/www/rdu -type f -exec chmod 664 {} \;
chmod +x scripts/deploy.sh
chmod 640 .env

# โฟลเดอร์ที่ PHP-FPM (ผู้ใช้ nginx) ต้องเขียนได้
sudo mkdir -p storage/app/private/config storage/app/private/facility
sudo chown -R nginx:nginx storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

> **ห้ามใช้ `chmod -R 775` กับทุกไฟล์** — ไฟล์จะได้สิทธิ์ execute ทำให้ git เห็นว่าถูกแก้ แล้ว `deploy.sh` ไม่ยอม deploy
> ถ้าเปิด SELinux (เครื่องจริงที่ไม่ใช่ LXC) ต้องตั้ง context เพิ่ม ดู SERVER-SETUP 6.5

### B7. ตั้ง nginx

สร้าง `/etc/nginx/conf.d/rdu.conf` (ตัวเต็มใน SERVER-SETUP Phase 4):

```nginx
server {
    listen 80;
    server_name rdu.โดเมนของโรงพยาบาล;
    root /var/www/rdu/public;
    index index.php;
    server_tokens off;
    client_max_body_size 20m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo firewall-cmd --permanent --add-service=http && sudo firewall-cmd --reload   # และ https ถ้ามี
# ให้ PHP ต่อออกไปยัง HOSxP / HDC ได้ (เฉพาะเครื่องที่เปิด SELinux)
# sudo setsebool -P httpd_can_network_connect_db 1 && sudo setsebool -P httpd_can_network_connect 1
```

ระบบมีหน้า login ควรเปิด **HTTPS** (certbot หรือ certificate จาก CA ภายใน) — ดู SERVER-SETUP Phase 4

### B8. สร้างบัญชีชั่วคราว, cache, ทดสอบ

```bash
php artisan tinker --execute="App\Models\User::create(['name'=>'Setup','username'=>'setup','email'=>'setup@rdu.local','password'=>Illuminate\Support\Facades\Hash::make('ตั้งรหัสผ่านที่นี่')]);"
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php-fpm
```

เปิดเบราว์เซอร์ไปที่ `APP_URL` → ล็อกอินด้วย `setup` → ไปต่อ **ส่วนที่ 3**

### B9. อัปเดตโค้ดครั้งถัดไป

```bash
cd /var/www/rdu && ./scripts/deploy.sh
```

สคริปต์ดึงโค้ดล่าสุดจาก GitHub, ติดตั้ง dependency, build, migrate, cache และ reload PHP-FPM ให้เอง
(ปฏิเสธการทำงานถ้า `APP_ENV` ไม่ใช่ `production` หรือมีไฟล์ในเครื่องถูกแก้) — แก้โค้ดที่เครื่องพัฒนาเสมอ ไม่แก้บน server

---

## ส่วนที่ 3 — ตั้งค่าครั้งแรกในระบบ (ทั้งสองแบบ)

ล็อกอินด้วย `setup` (ระบบจะพาไปหน้า "ตั้งค่าฐานข้อมูล HOSxP" อัตโนมัติ)

1. **ตั้งค่าฐานข้อมูล HOSxP** — กรอก host, port, ชื่อฐานข้อมูล, schema, user (`rdu_readonly`), รหัสผ่าน
   → กด **ทดสอบการเชื่อมต่อ** → **ตรวจสอบ Read-Only** (INSERT/UPDATE/DELETE/CREATE/DROP ต้องถูก BLOCK ทั้งหมด) → **บันทึก**
2. **ออกจากระบบ แล้วล็อกอินใหม่ด้วยบัญชี HOSxP** ของผู้ดูแล (ชื่อผู้ใช้ต้องอยู่ใน `SETTINGS_ADMIN_USERNAMES`)
3. เมนู **ข้อมูลสถานบริการ** — กรอกชื่อ, ชื่อย่อ, จังหวัด, รหัสหน่วยบริการ 5 หลัก, อัปโหลดโลโก้
   (ก่อนตั้ง ทุกหน้าจะแสดงเพียง "RDU Dashboard")
4. เมนู **ผู้ตั้งค่ารหัสยา** — เพิ่มเภสัชกรเป็นผู้มีสิทธิ์แก้ชุดรหัสยา
5. เภสัชกรเปิด **ตั้งค่ารหัสยา** — ลากรหัสยา (icode) ของโรงพยาบาลเข้าแต่ละชุดของแต่ละตัวชี้วัด
   ตัวชี้วัดที่ยังไม่ตั้งชุดรหัสยาจะแสดง "ยังไม่ตั้งค่า" จนกว่าจะครบ
6. ปิดตัวชี้วัดที่โรงพยาบาลไม่ใช้ด้วยสวิตช์ "ใช้งานตัวชี้วัดนี้" ในหน้าเดียวกัน
7. **ลบบัญชี `setup` ทิ้ง** แล้วเอา `setup,` ออกจาก `SETTINGS_ADMIN_USERNAMES` ใน `.env`
   (แบบ B: `php artisan config:cache` ใหม่)

> **อย่ารัน `php artisan rdu:import-drug-code-sets`** — คำสั่งนั้นนำเข้ารายการรหัสยาของโรงพยาบาลเชียงกลางเท่านั้น
> โรงพยาบาลอื่นต้องตั้งรหัสยาของตนเองตามข้อ 5

### เช็กลิสต์หลังติดตั้ง

- [ ] หน้า Login ขึ้น และล็อกอินด้วยบัญชี HOSxP ได้
- [ ] "ตรวจสอบ Read-Only" → SELECT ผ่าน, INSERT/UPDATE/DELETE/CREATE/DROP ถูก BLOCK
- [ ] ชื่อและโลโก้โรงพยาบาลแสดงที่ header, แถบด้านข้าง และหน้า login
- [ ] หน้าภาพรวมมีตัวเลข KPI (ตัวที่ตั้งชุดรหัสยาแล้ว)
- [ ] ไม่มีบัญชี `setup` / `admin` ค้างอยู่
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] (แบบ B) เปิด `http://<โดเมน>/.env` แล้วได้ 403/404, `.env` สิทธิ์ `640`
- [ ] สำรอง `.env` และ `storage/app/private/config/rdu_database.json` ไว้ (คนละที่กับ backup ฐานข้อมูล)

---

## แก้ปัญหา

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| `could not find driver` | ยังไม่เปิด `pdo_pgsql` ใน `php.ini` (แบบ A: `C:\xampp\php\php.ini` แล้ว Restart Apache) ตรวจด้วย `php -m` — ระวังว่า `php` ใน PATH อาจเป็นคนละตัวกับของ XAMPP ให้เรียก `C:\xampp\php\php.exe` ตรงๆ |
| หน้าเว็บขึ้น 500 / หน้าขาว | ดู `storage/logs/laravel.log`; ตรวจว่า `storage` และ `bootstrap/cache` เขียนได้ และรัน `php artisan key:generate` แล้ว |
| หน้าเว็บไม่มีสไตล์/ไอคอน | ยังไม่ได้ `npm run build`, หรือเครื่องผู้ใช้เข้า `fonts.googleapis.com` / `fonts.gstatic.com` ไม่ได้ |
| `password authentication failed for user "rdu_readonly"` | รหัสผิด หรือเปลี่ยน `APP_KEY` หลังบันทึก → กรอกในหน้าตั้งค่า HOSxP ใหม่ |
| `no pg_hba.conf entry for host ...` | ผู้ดูแล HOSxP ยังไม่อนุญาต IP เครื่อง RDU ใน `pg_hba.conf` (และ firewall พอร์ต 5432) |
| กด "บันทึก" ในหน้าตั้งค่า HOSxP แล้ว 500 (`Permission denied`) | แบบ B: `sudo chown nginx:nginx storage/app/private/config/rdu_database.json && sudo chmod 600 ...` |
| `deploy.sh`: "Files tracked by git were edited on the server" | เคยใช้ `chmod -R 775` → ตั้งสิทธิ์ใหม่ตามขั้น B6 (โฟลเดอร์ 775 / ไฟล์ 664) |
| ล็อกอินด้วยบัญชี HOSxP ไม่ได้ | ตั้งค่า HOSxP ยังไม่สำเร็จ หรือรหัสผิด; ระหว่างนั้นใช้บัญชี `setup` เข้าไปแก้ได้ |
| เมนูตั้งค่า (HOSxP / สถานบริการ) ไม่ขึ้น | ชื่อผู้ใช้ยังไม่อยู่ใน `SETTINGS_ADMIN_USERNAMES` (ตัวพิมพ์เล็ก) → แก้ `.env` แล้ว `php artisan config:clear` |
| KPI ขึ้น "ยังไม่ตั้งค่า" | ยังไม่ได้ตั้งชุดรหัสยาของตัวชี้วัดนั้น (ขั้นตอนที่ 5 ในส่วนที่ 3) |
| ล็อกอินแล้วเด้งออกเมื่อเปิดอีกเครื่อง | ใช้เวอร์ชันล่าสุด (commit `c7886eb` ขึ้นไป) — อัปเดตด้วย `deploy.ps1` / `deploy.sh` |

เอกสารอ้างอิงเพิ่มเติม: [DEPLOYMENT.md](../DEPLOYMENT.md) · [SERVER-SETUP-ALMALINUX.md](./SERVER-SETUP-ALMALINUX.md) ·
[DATABASE-CONFIGURATION.md](./DATABASE-CONFIGURATION.md) · [DATABASE-SECURITY.md](./DATABASE-SECURITY.md)
