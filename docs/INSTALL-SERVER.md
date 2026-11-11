# ติดตั้ง RDU Dashboard บน Server (AlmaLinux 9 + nginx + PHP-FPM) — ตั้งแต่เครื่องเปล่า

คู่มือนี้สำหรับโรงพยาบาลที่ติดตั้งบน **server Linux** (ไม่ใช้ XAMPP) อ่านจากบนลงล่างตามลำดับ
ถ้าต้องการแบบ XAMPP ดู [INSTALLATION.md](./INSTALLATION.md) · รายละเอียดเชิงลึก/LXC/backup ดู [SERVER-SETUP-ALMALINUX.md](./SERVER-SETUP-ALMALINUX.md)

**สิ่งที่ต้องมี:** AlmaLinux 9 (หรือ Rocky/RHEL 9), RAM ≥ 2 GB, ดิสก์ ≥ 10 GB, สิทธิ์ `sudo`, เครื่องออก internet ได้
(ดึง package และ GitHub), และเครื่องนี้ต่อพอร์ต 5432 ไปยังเครื่อง HOSxP ได้

ค่าที่ต้องแทนในคู่มือ: `<ADMIN_USER>` = ผู้ใช้ Linux ของคุณ · `<APP_DB_PASSWORD>` = รหัสฐานข้อมูลของแอป (ตั้งเอง) ·
`<DOMAIN>` = ชื่อเว็บ เช่น `rdu.hospital.local` · `<SERVER_IP>` = IP เครื่องนี้

> **หลักความปลอดภัย:** RDU อ่านฐาน HOSxP แบบ **SELECT เท่านั้น** ห้ามใช้ user ที่มีสิทธิ์เขียน

---

## 0. เตรียมกับผู้ดูแล HOSxP (ทำก่อน หรือขนานกัน)

ขอจากผู้ดูแลฐานข้อมูล HOSxP: IP/พอร์ต, ชื่อฐานข้อมูล, user `rdu_readonly` + รหัส, และให้ **อนุญาต `<SERVER_IP>`** ใน `pg_hba.conf`
(`host <db> rdu_readonly <SERVER_IP>/32 scram-sha-256`) พร้อมเปิด firewall พอร์ต 5432 ให้ IP นี้ ถ้ายังไม่มี user:

```sql
CREATE USER rdu_readonly WITH PASSWORD 'รหัสที่เดายาก';
GRANT CONNECT ON DATABASE hosxp TO rdu_readonly;
GRANT USAGE ON SCHEMA public TO rdu_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO rdu_readonly;
```

เตรียมข้อมูลโรงพยาบาลไว้กรอกในระบบ: ชื่อ, ชื่อย่อ, จังหวัด, รหัสหน่วยบริการ 5 หลัก, ไฟล์โลโก้ และชื่อผู้ใช้ HOSxP ของผู้ดูแลระบบ/เภสัชกร

---

## 1. ระบบพื้นฐาน

```bash
sudo dnf -y update
sudo dnf -y install epel-release dnf-plugins-core
sudo dnf config-manager --set-enabled crb
sudo dnf -y install git vim unzip tar curl policycoreutils-python-utils
sudo timedatectl set-timezone Asia/Bangkok
```

Firewall (เปิดเฉพาะเว็บ — **ไม่ต้องเปิด 5432** เพราะฐานข้อมูลของแอปอยู่เครื่องเดียวกัน):

```bash
sudo systemctl enable --now firewalld
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

## 2. PHP 8.3 + PHP-FPM

```bash
sudo dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf -y module reset php
sudo dnf -y module enable php:remi-8.3
sudo dnf -y install php-fpm php-cli php-common php-pgsql php-mbstring php-intl php-bcmath \
     php-xml php-opcache php-process php-zip php-gd php-sodium

# PHP-FPM รันเป็นผู้ใช้ nginx
sudo sed -i 's/^user = .*/user = nginx/; s/^group = .*/group = nginx/' /etc/php-fpm.d/www.conf
sudo sed -i 's/^;\?listen.owner = .*/listen.owner = nginx/; s/^;\?listen.group = .*/listen.group = nginx/' /etc/php-fpm.d/www.conf

sudo tee /etc/php.d/99-rdu.ini >/dev/null <<'EOF'
date.timezone = Asia/Bangkok
expose_php = Off
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 20M
opcache.enable = 1
opcache.validate_timestamps = 0
EOF
```

> `opcache.validate_timestamps = 0` ทำให้เร็วขึ้น แต่หลังแก้โค้ดทุกครั้งต้อง `sudo systemctl reload php-fpm` (สคริปต์ deploy ทำให้เอง)

## 3. PostgreSQL 16 (ฐานข้อมูลของแอป `rdu_dashboard` — คนละตัวกับ HOSxP)

```bash
sudo dnf -y module enable postgresql:16
sudo dnf -y install postgresql-server postgresql-contrib
sudo postgresql-setup --initdb
sudo systemctl enable --now postgresql
```

แก้ `/var/lib/pgsql/data/pg_hba.conf` ให้บรรทัด `host` ของ `127.0.0.1/32` และ `::1/128` ใช้ METHOD `scram-sha-256` แล้ว:

```bash
sudo systemctl reload postgresql
sudo -u postgres psql <<'EOF'
CREATE ROLE rdu_app LOGIN PASSWORD '<APP_DB_PASSWORD>';
CREATE DATABASE rdu_dashboard OWNER rdu_app ENCODING 'UTF8' TEMPLATE template0;
EOF
```

ตรวจ: `psql -h 127.0.0.1 -U rdu_app -d rdu_dashboard -c 'select version();'` ต้องได้เวอร์ชัน 16.x

## 4. nginx

```bash
sudo dnf -y module enable nginx:1.24
sudo dnf -y install nginx
sudo systemctl enable --now nginx php-fpm
```

สร้าง `/etc/nginx/conf.d/rdu.conf`:

```nginx
server {
    listen 80;
    server_name <DOMAIN>;
    root /var/www/rdu/public;
    index index.php;
    charset utf-8;
    server_tokens off;
    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

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
sudo nginx -t        # ต้องขึ้น "syntax is ok"
```

(ยัง reload ไม่ได้จนกว่าจะมีโฟลเดอร์แอปในข้อ 6 — ถ้า `nginx -t` ผ่านก็พอ)

## 5. Composer + Node.js

```bash
sudo dnf -y install composer
sudo dnf -y module enable nodejs:22
sudo dnf -y install nodejs
composer --version; node -v; npm -v     # Composer 2.x, Node ≥ 20.19
```

ให้ผู้ใช้ `<ADMIN_USER>` รัน `sudo systemctl reload php-fpm` ได้โดยไม่ถามรหัส (สคริปต์ deploy ใช้ขั้นนี้):

```bash
echo '<ADMIN_USER> ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm' | sudo tee /etc/sudoers.d/rdu-deploy
sudo chmod 440 /etc/sudoers.d/rdu-deploy
```

## 6. ติดตั้งแอป

```bash
sudo mkdir -p /var/www && sudo chown <ADMIN_USER>:nginx /var/www
git clone https://github.com/nuttapong39/RDU.git /var/www/rdu
cd /var/www/rdu
cp .env.example .env
nano .env
```

แก้ `.env` อย่างน้อย:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<DOMAIN>
LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rdu_dashboard          # ห้ามชี้ไป HOSxP — deploy.sh จะปฏิเสธถ้าไม่ใช่ชื่อนี้
DB_USERNAME=rdu_app
DB_PASSWORD=<APP_DB_PASSWORD>

RDU_DB_PASSWORD=                   # เว้นว่าง — ตั้งรหัส HOSxP ในหน้าเว็บ
SETTINGS_ADMIN_USERNAMES=setup,<ชื่อผู้ใช้HOSxPของผู้ดูแล>   # ตัวพิมพ์เล็ก คั่นด้วย ,
```

ติดตั้ง สร้างตาราง และ build:

```bash
chmod 640 .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RduIndicatorSeeder --force
```

> **ห้าม `php artisan db:seed` แบบเต็ม** (สร้างผู้ใช้ `admin/admin`) · **ห้าม `migrate` กับฐาน HOSxP**
> **`APP_KEY` ห้ามเปลี่ยนหลังเริ่มใช้งาน** — รหัส HOSxP ที่บันทึกถูกเข้ารหัสด้วยคีย์นี้

## 7. สิทธิ์ไฟล์ (พลาดบ่อยที่สุด)

```bash
cd /var/www/rdu
sudo chown -R <ADMIN_USER>:nginx .
sudo find . -path ./node_modules -prune -o -path ./vendor -prune -o -path ./.git -prune -o -type d -exec chmod 775 {} +
sudo find . -path ./node_modules -prune -o -path ./vendor -prune -o -path ./.git -prune -o -type f -exec chmod 664 {} +
chmod +x scripts/deploy.sh
chmod 640 .env

sudo mkdir -p storage/app/private/config storage/app/private/facility
sudo chown -R nginx:nginx storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
git config core.fileMode false
```

> **ห้ามใช้ `chmod -R 775` กับทุกไฟล์** — ไฟล์จะมีสิทธิ์ execute ทำให้ git เห็นว่าถูกแก้ แล้ว `deploy.sh` ไม่ยอม deploy
> (ถ้าเกิดแล้วดูตารางแก้ปัญหา)

**SELinux** (เครื่องจริง/VM ที่เปิด SELinux — ถ้าเป็น LXC ข้ามได้):

```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/rdu/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/rdu/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/rdu
sudo setsebool -P httpd_can_network_connect_db 1   # ให้ PHP ต่อ HOSxP (5432)
sudo setsebool -P httpd_can_network_connect 1      # ให้ PHP เรียก HDC (HTTPS)
```

## 8. บัญชีชั่วคราว, cache, เปิดเว็บ

ยังไม่ได้ตั้งค่า HOSxP จึงล็อกอินด้วยบัญชี HOSxP ไม่ได้ ให้สร้างบัญชี `setup` (ตั้งรหัสผ่านเอง อย่าใช้ `admin`):

```bash
cd /var/www/rdu
php artisan tinker --execute="App\Models\User::create(['name'=>'Setup','username'=>'setup','email'=>'setup@rdu.local','password'=>Illuminate\Support\Facades\Hash::make('ตั้งรหัสผ่านที่นี่')]);"
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo nginx -t && sudo systemctl reload nginx php-fpm
```

ให้เครื่องผู้ใช้เข้า `<DOMAIN>` ได้ (A record ใน DNS ของโรงพยาบาล หรือใส่ใน hosts) แล้วเปิด `http://<DOMAIN>`
→ ต้องเห็นหน้า Login

## 9. ตั้งค่าครั้งแรกในระบบ

1. ล็อกอินด้วย `setup` → ระบบพาไปหน้า **ตั้งค่าฐานข้อมูล HOSxP** → กรอก host/port/ฐานข้อมูล/schema/user/รหัส
   → **ทดสอบการเชื่อมต่อ** → **ตรวจสอบ Read-Only** (INSERT/UPDATE/DELETE/CREATE/DROP ต้อง BLOCK ทั้งหมด) → **บันทึก**
2. ออกจากระบบ แล้วล็อกอินด้วยบัญชี **HOSxP** ของผู้ดูแล (ต้องอยู่ใน `SETTINGS_ADMIN_USERNAMES`)
3. เมนู **ข้อมูลสถานบริการ** — ชื่อ, ชื่อย่อ, จังหวัด, รหัสหน่วยบริการ, โลโก้
4. เมนู **ผู้ตั้งค่ารหัสยา** — เพิ่มเภสัชกรเป็นผู้มีสิทธิ์
5. เภสัชกรเปิด **ตั้งค่ารหัสยา** — ลากรหัสยา (icode) ของ รพ. เข้าแต่ละชุดของแต่ละตัวชี้วัด
   (ตัวที่ยังไม่ตั้งแสดง "ยังไม่ตั้งค่า"); ปิดตัวชี้วัดที่ไม่ใช้ด้วยสวิตช์ "ใช้งานตัวชี้วัดนี้"
6. **ลบบัญชี `setup`** (`php artisan tinker --execute="App\Models\User::where('username','setup')->delete();"`)
   แล้วเอา `setup,` ออกจาก `SETTINGS_ADMIN_USERNAMES` ใน `.env` → `php artisan config:cache`

> **อย่ารัน `php artisan rdu:import-drug-code-sets`** — คำสั่งนั้นนำเข้ารายการรหัสยาของโรงพยาบาลเชียงกลางเท่านั้น

## 10. HTTPS (แนะนำ — ระบบมีหน้า login)

มีโดเมนจริง: `sudo dnf -y install certbot python3-certbot-nginx && sudo certbot --nginx -d <DOMAIN>`
ใช้ใน LAN: ขอ certificate จาก CA ภายในของโรงพยาบาล แล้วเพิ่ม `listen 443 ssl;`, `ssl_certificate`, `ssl_certificate_key`
และ redirect 80 → 443 แล้วแก้ `APP_URL` เป็น `https://...` → `php artisan config:cache`

## 11. สำรองข้อมูล (ทำทันทีหลังใช้งาน)

```bash
sudo mkdir -p /backup/rdu && sudo chown <ADMIN_USER> /backup/rdu
# ฐานข้อมูลของแอป ทุกคืน 01:30 เก็บ 14 วัน
( crontab -l 2>/dev/null; echo '30 1 * * * PGPASSWORD="<APP_DB_PASSWORD>" pg_dump -h 127.0.0.1 -U rdu_app rdu_dashboard | gzip > /backup/rdu/rdu_$(date +\%F).sql.gz && find /backup/rdu -name "rdu_*.sql.gz" -mtime +14 -delete' ) | crontab -
```

สำเนา `.env` และ `storage/app/private/config/rdu_database.json` เก็บ **คนละที่** กับ backup ฐานข้อมูล (สองไฟล์รวมกันถอดรหัส HOSxP ได้)

---

## อัปเดตระบบครั้งถัดไป

```bash
cd /var/www/rdu && ./scripts/deploy.sh
```

สคริปต์ดึงโค้ดล่าสุดจาก GitHub → ติดตั้ง dependency → build → migrate → cache → reload PHP-FPM ให้เอง
ปฏิเสธการทำงานถ้า `APP_ENV` ไม่ใช่ `production` หรือมีไฟล์ที่ track ใน git ถูกแก้บน server (ให้แก้โค้ดที่เครื่องพัฒนาเสมอ)
ไม่แตะ `.env` และ `rdu_database.json` · ถ้ามีค่าใหม่ใน `.env` ต้องเพิ่มด้วยมือ **ก่อน** รัน

## เช็กลิสต์หลังติดตั้ง

- [ ] `systemctl is-active nginx php-fpm postgresql` → `active` ทั้งสาม
- [ ] หน้า Login ขึ้น (CSS/ฟอนต์/ไอคอนครบ) และล็อกอินด้วยบัญชี HOSxP ได้
- [ ] "ตรวจสอบ Read-Only" → SELECT ผ่าน, ที่เหลือ BLOCK
- [ ] ชื่อ/โลโก้โรงพยาบาลแสดง, หน้าภาพรวมมีตัวเลข KPI ที่ตั้งชุดรหัสยาแล้ว
- [ ] ไม่มีบัญชี `setup` / `admin` ค้างอยู่
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `.env` สิทธิ์ `640`
- [ ] `curl -sI http://<DOMAIN>/.env` → 403/404
- [ ] `curl -sI http://<DOMAIN> | grep -i x-powered-by` → ไม่มีผลลัพธ์
- [ ] `ls -la storage/app/private/config/` → `rdu_database.json` เป็น `-rw------- nginx nginx`
- [ ] ตั้ง backup แล้ว และทดสอบกู้คืนอย่างน้อยหนึ่งครั้ง

## แก้ปัญหา

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| 502 Bad Gateway | `php-fpm` ไม่ทำงาน หรือ socket ไม่ตรง → `systemctl status php-fpm`; ตรวจ `fastcgi_pass unix:/run/php-fpm/www.sock` |
| 500 / หน้าขาว | ดู `storage/logs/laravel-*.log`; ตรวจสิทธิ์ `storage` และ `bootstrap/cache` (เจ้าของ `nginx`) และว่ารัน `key:generate` แล้ว |
| หน้าไม่มีสไตล์/ไอคอน | ยังไม่ `npm run build` หรือเครื่องผู้ใช้เข้า `fonts.googleapis.com` / `fonts.gstatic.com` ไม่ได้ |
| `could not find driver` | ไม่ได้ติดตั้ง `php-pgsql` → `sudo dnf install php-pgsql && sudo systemctl reload php-fpm` |
| `password authentication failed for user "rdu_readonly"` | รหัสผิด หรือเปลี่ยน `APP_KEY` หลังบันทึก → กรอกในหน้าตั้งค่า HOSxP ใหม่ |
| `no pg_hba.conf entry for host ...` | ผู้ดูแล HOSxP ยังไม่ได้อนุญาต `<SERVER_IP>` ใน `pg_hba.conf` / firewall 5432 |
| `Connection refused` / timeout ไป HOSxP (SELinux เปิด) | `sudo setsebool -P httpd_can_network_connect_db 1` |
| กด "บันทึก" หน้าตั้งค่า HOSxP แล้ว 500 (`Permission denied`) | `sudo chown nginx:nginx storage/app/private/config/rdu_database.json && sudo chmod 600 storage/app/private/config/rdu_database.json` |
| `deploy.sh`: "Files tracked by git were edited on the server" | เคย `chmod -R 775` → `git config core.fileMode false` หรือตั้งสิทธิ์ใหม่ตามข้อ 7 |
| `deploy.sh` ค้างที่ `sudo systemctl reload php-fpm` | ยังไม่ได้ตั้ง sudoers ในข้อ 5 |
| เมนูตั้งค่า (HOSxP / สถานบริการ) ไม่ขึ้น | ชื่อผู้ใช้ไม่อยู่ใน `SETTINGS_ADMIN_USERNAMES` (ตัวพิมพ์เล็ก) → แก้ `.env` แล้ว `php artisan config:cache` |
| KPI ขึ้น "ยังไม่ตั้งค่า" | ยังไม่ได้ตั้งชุดรหัสยาของตัวชี้วัดนั้น (ข้อ 9.5) |
| แก้ `.env` แล้วไม่มีผล | รัน `php artisan config:cache` แล้ว `sudo systemctl reload php-fpm` |
