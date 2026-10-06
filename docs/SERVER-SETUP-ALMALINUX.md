# SERVER-SETUP-ALMALINUX.md — ย้าย RDU Dashboard ไป AlmaLinux 9

แผนติดตั้งเครื่อง server ใหม่ (AlmaLinux 9.8, ลง OS สะอาดแล้ว) ให้รัน RDU Dashboard และ dashboard อื่นในอนาคต
แทน XAMPP บน Windows แบ่งเป็น phase ตามกฎของโปรเจกต์: **แต่ละ phase มีข้อ "ตรวจผ่าน" ห้ามข้ามไป phase ถัดไปถ้ายังไม่ผ่าน**

Stack บนเครื่องใหม่:

| ส่วน | เดิม (XAMPP) | ใหม่ (AlmaLinux) |
|---|---|---|
| Web server | Apache (XAMPP) | nginx |
| PHP | PHP 8.2 (XAMPP) | PHP 8.3 + PHP-FPM (Remi repo) |
| ฐานข้อมูลของแอป | PostgreSQL บน Windows | PostgreSQL 16 (AppStream) |
| Build asset | Node บน Windows | Node.js 22 (ใช้ตอน deploy เท่านั้น) |
| ความปลอดภัยระดับ OS | — | firewalld + SELinux (Enforcing) |

ค่าในเอกสารที่ต้องแทนด้วยค่าจริง: `<ADMIN_USER>`, `<SERVER_IP>`, `<APP_DB_PASSWORD>`, `<GITHUB_REPO>`
**ห้ามใส่รหัสผ่านจริงลงในเอกสารนี้หรือใน git**

---

## การตัดสินใจ (ยืนยันแล้ว 2026-10-06)

1. **URL:** `https://rdu.ckhospital.local` — 1 ชื่อเครื่องต่อ 1 dashboard (ระบบถัดไปใช้แบบเดียวกัน เช่น `xxx.ckhospital.local`)
   - โดเมน AD ขององค์กรคือ `ckhospital.local` → เพิ่ม A record `rdu` → `<SERVER_IP>` ในโซนเดิมของ AD (ไม่ต้องสร้างโซนใหม่)
   - ระหว่างรอ DNS ทดสอบได้โดยเพิ่ม `<SERVER_IP>   rdu.ckhospital.local` ในไฟล์ `hosts` ของเครื่องที่ใช้ทดสอบ แล้วลบออกเมื่อ DNS พร้อม
   - เครื่องนี้มีระบบ `ck-asset-registry` ใช้ IP `<SERVER_IP>` ตรงๆ อยู่แล้ว → RDU ต้องใช้ชื่อเครื่องเท่านั้น (`server_name` ห้ามใส่ IP ซ้ำ)
   - `.local` ใช้ Let's Encrypt ไม่ได้ → HTTPS ใช้ certificate จาก CA ภายในของโรงพยาบาล (หรือ self-signed ที่ติดตั้งให้เครื่องผู้ใช้เชื่อถือ)
2. **ข้อมูลแอปเดิม:** ย้ายด้วย `pg_dump` **เฉพาะฐาน `rdu_dashboard`** (ฐานของแอป RDU — บน PostgreSQL เครื่อง XAMPP
   มีแค่ `postgres` กับ `rdu_dashboard`) ไม่ใช้ `pg_dumpall` และไม่ย้าย role/ผู้ใช้ของ PostgreSQL (สร้างใหม่ใน Phase 3)
3. **`APP_KEY`** — **ต้องใช้ค่าเดิมจาก `.env` เครื่อง XAMPP** เพราะรหัสผ่าน HOSxP ใน `rdu_database.json` เข้ารหัสด้วยคีย์นี้
   ถ้าสร้างคีย์ใหม่ ต้องเข้าหน้าตั้งค่าฐานข้อมูล HOSxP แล้วกรอกรหัสใหม่

---

## เครื่องเป็น LXC container (Proxmox) — ข้อแตกต่างจากแผนหลัก

ตรวจด้วย `systemd-detect-virt` → ถ้าได้ `lxc` (เครื่อง dashboard-srv ตรวจแล้ว 2026-10-06: `lxc`, SELinux `Disabled`, firewalld ทำงาน):

| เรื่อง | บน LXC |
|---|---|
| เวลา (chronyd) | **ไม่ใช้** — container ตั้งนาฬิกาเองไม่ได้ (`adjtimex ... Operation not permitted`) ใช้เวลาจาก host: `systemctl disable --now chronyd` แล้วตรวจ `timedatectl` → `System clock synchronized: yes` |
| SELinux | `Disabled` และเปิดไม่ได้ → **ข้ามคำสั่ง `semanage` / `restorecon` / `setsebool` / `ausearch` ทั้งหมด** ใช้สิทธิ์ไฟล์ (6.5) + firewall แทน |
| firewalld | ใช้ได้ตามแผน (+ เปิด firewall ของ Proxmox ที่ระดับ container ได้อีกชั้น) |
| อัปเดต kernel | ทำที่เครื่อง Proxmox host (container ใช้ kernel ของ host) |

## Phase 1 — ตั้งค่าพื้นฐาน OS และความปลอดภัย

**เป้าหมาย:** เครื่องอัปเดตล่าสุด, IP คงที่, เวลาถูกต้อง, เข้าได้ผ่าน SSH key เท่านั้น, เปิดเฉพาะพอร์ตที่ใช้

```bash
# อัปเดตทั้งระบบ + repo เสริมที่ต้องใช้
sudo dnf -y update
sudo dnf -y install epel-release dnf-plugins-core
sudo dnf config-manager --set-enabled crb
sudo dnf -y install vim git unzip tar curl policycoreutils-python-utils
sudo reboot

# เวลา/ชื่อเครื่อง
sudo timedatectl set-timezone Asia/Bangkok
sudo hostnamectl set-hostname dashboard-srv
systemctl is-active chronyd          # VM: ต้องได้ active  |  LXC: ข้าม ดูหัวข้อ LXC ด้านบน

# IP คงที่ (ดูชื่อ connection ด้วย: nmcli con show)
sudo nmcli con mod <CONN_NAME> ipv4.method manual ipv4.addresses <SERVER_IP>/24 \
     ipv4.gateway <GATEWAY> ipv4.dns "<DNS1> <DNS2>"
sudo nmcli con up <CONN_NAME>

# ผู้ดูแล (ไม่ใช้ root ทำงานประจำ)
sudo useradd -m -G wheel <ADMIN_USER> && sudo passwd <ADMIN_USER>
# จากเครื่องผู้ดูแล: ssh-copy-id <ADMIN_USER>@<SERVER_IP>
```

ทดสอบว่า login ด้วย key ได้แล้ว **จึง** ปิด root login และรหัสผ่าน SSH:

```bash
sudo tee /etc/ssh/sshd_config.d/10-hardening.conf >/dev/null <<'EOF'
PermitRootLogin no
PasswordAuthentication no
EOF
sudo systemctl reload sshd

# Firewall: เปิด http/https (ssh เปิดอยู่แล้วโดย default)
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload

# อัปเดตความปลอดภัยอัตโนมัติ
sudo dnf -y install dnf-automatic
sudo sed -i 's/^upgrade_type.*/upgrade_type = security/; s/^apply_updates.*/apply_updates = yes/' /etc/dnf/automatic.conf
sudo systemctl enable --now dnf-automatic.timer
```

**SELinux ให้คงเป็น Enforcing** (ไม่ปิด) — phase ถัดๆ ไปจะตั้งสิทธิ์ให้ถูกต้องแทน (LXC: เป็น `Disabled` เปิดไม่ได้ ข้ามได้)

**ตรวจผ่าน**
- [ ] `getenforce` → `Enforcing` (LXC: `Disabled` — ยอมรับได้)
- [ ] `sudo firewall-cmd --list-services` → มี `http https ssh`
- [ ] `timedatectl` → `Asia/Bangkok` และ `System clock synchronized: yes`
- [ ] SSH ด้วย root หรือด้วยรหัสผ่าน → ถูกปฏิเสธ, SSH ด้วย key ของ `<ADMIN_USER>` → เข้าได้
- [ ] `ip a` → IP เป็น `<SERVER_IP>` และยังคงเดิมหลัง reboot

---

## Phase 2 — PHP 8.3 + PHP-FPM

**เป้าหมาย:** PHP 8.3 พร้อม extension ที่โปรเจกต์ต้องใช้ (รวม `intl` ที่เครื่อง XAMPP ขาด)

```bash
sudo dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf -y module reset php
sudo dnf -y module enable php:remi-8.3
sudo dnf -y install php-fpm php-cli php-common php-pgsql php-mbstring php-intl php-bcmath \
     php-xml php-opcache php-process php-zip php-gd php-sodium
```

ตั้ง PHP-FPM ให้รันในชื่อผู้ใช้ `nginx` (ค่า default ของ AlmaLinux คือ `apache`):

```bash
sudo sed -i 's/^user = .*/user = nginx/; s/^group = .*/group = nginx/' /etc/php-fpm.d/www.conf
sudo sed -i 's/^;\?listen.owner = .*/listen.owner = nginx/; s/^;\?listen.group = .*/listen.group = nginx/' /etc/php-fpm.d/www.conf

sudo tee /etc/php.d/99-rdu.ini >/dev/null <<'EOF'
date.timezone = Asia/Bangkok
expose_php = Off
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 20M
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
EOF
```

> `opcache.validate_timestamps = 0` ทำให้เร็วขึ้น แต่หลัง deploy ทุกครั้งต้อง `sudo systemctl reload php-fpm` (อยู่ในขั้นตอน deploy แล้ว)

PHP-FPM จะ start หลังติดตั้ง nginx ใน Phase 4 (ต้องมีผู้ใช้ `nginx` ก่อน)

**ตรวจผ่าน**
- [ ] `php -v` → `PHP 8.3.x`
- [ ] `php -m | grep -Ei 'pdo_pgsql|intl|mbstring|bcmath|openssl|fileinfo|sodium|zip'` → ครบทุกตัว
- [ ] `php -i | grep -E 'date.timezone|expose_php'` → `Asia/Bangkok`, `Off`

---

## Phase 3 — PostgreSQL 16 (ฐานข้อมูลของแอป `rdu_dashboard`)

**เป้าหมาย:** ฐานข้อมูลของแอปบนเครื่องนี้ รับการเชื่อมต่อจาก localhost เท่านั้น
(ฐานข้อมูล HOSxP อยู่ที่เครื่อง 192.168.1.119 ตามเดิม — **ไม่ย้าย ไม่แตะ**)

```bash
sudo dnf -y module enable postgresql:16
sudo dnf -y install postgresql-server postgresql-contrib
sudo postgresql-setup --initdb
sudo systemctl enable --now postgresql
```

ให้ localhost ใช้รหัสผ่าน (scram) แทน `ident` — แก้ `/var/lib/pgsql/data/pg_hba.conf` บรรทัด `host` ของ `127.0.0.1/32` และ `::1/128`
ให้คอลัมน์ METHOD เป็น `scram-sha-256` แล้ว:

```bash
sudo systemctl reload postgresql

# ผู้ใช้และฐานข้อมูลของแอป (ไม่ใช้ผู้ใช้ postgres ในแอป)
sudo -u postgres psql <<'EOF'
CREATE ROLE rdu_app LOGIN PASSWORD '<APP_DB_PASSWORD>';
CREATE DATABASE rdu_dashboard OWNER rdu_app ENCODING 'UTF8' TEMPLATE template0;
EOF
```

`listen_addresses` ค่า default คือ `localhost` อยู่แล้ว **ไม่ต้องเปิดพอร์ต 5432 ใน firewall**

**ตรวจผ่าน**
- [ ] `psql -h 127.0.0.1 -U rdu_app -d rdu_dashboard -c 'select version();'` → ใส่รหัสแล้วได้ PostgreSQL 16.x
- [ ] `sudo ss -ltnp | grep 5432` → ฟังที่ `127.0.0.1` / `[::1]` เท่านั้น

---

## Phase 4 — nginx

**เป้าหมาย:** nginx ส่งคำขอ PHP ไปที่ PHP-FPM ผ่าน unix socket

```bash
sudo dnf -y module enable nginx:1.24
sudo dnf -y install nginx
sudo systemctl enable --now nginx php-fpm
```

`/etc/nginx/conf.d/rdu.conf` (สร้างไฟล์นี้หลัง Phase 6 มีโฟลเดอร์แอปแล้วก็ได้):

```nginx
server {
    listen 80;
    server_name rdu.ckhospital.local;
    root /var/www/rdu/public;
    index index.php;
    charset utf-8;
    server_tokens off;
    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;   # query KPI บางตัวใช้เวลานาน
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

HTTPS: ถ้ามีโดเมนจริงใช้ `certbot` (`dnf install certbot python3-certbot-nginx`), ถ้าใช้ในวง LAN อย่างเดียวใช้ certificate
จาก CA ภายในของโรงพยาบาล แล้วเพิ่ม `listen 443 ssl;` + redirect 80 → 443 (ระบบมีหน้า login ควรใช้ HTTPS)

**ตรวจผ่าน**
- [ ] `sudo nginx -t` → `syntax is ok` / `test is successful`
- [ ] `systemctl is-active nginx php-fpm` → `active` ทั้งคู่
- [ ] จากเครื่องอื่นใน LAN: `curl -I http://<SERVER_IP>` → ได้ `HTTP/1.1` (ยังไม่ใช่หน้าแอปก็ได้) และไม่มี header `Server: nginx/<เลขเวอร์ชัน>`

---

## Phase 5 — เครื่องมือ deploy (Composer, Node.js, GitHub)

```bash
sudo dnf -y install composer                 # จาก Remi repo
sudo dnf -y module enable nodejs:22
sudo dnf -y install nodejs

# Deploy key (อ่านอย่างเดียว) สำหรับดึงโค้ดจาก GitHub private repo
ssh-keygen -t ed25519 -C "dashboard-srv deploy" -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub     # นำไปใส่ GitHub → repo → Settings → Deploy keys (ไม่ติ๊ก write access)
cat >> ~/.ssh/config <<'EOF'
Host github.com
    IdentityFile ~/.ssh/github_deploy
    IdentitiesOnly yes
EOF
```

**ตรวจผ่าน**
- [ ] `composer --version` → 2.x, `node -v` → v22.x, `npm -v` ได้ค่า
- [ ] `ssh -T git@github.com` → ข้อความ "successfully authenticated"

---

## Phase 6 — ติดตั้งแอป RDU Dashboard

**เป้าหมาย:** แอปรันบนเครื่องใหม่ ใช้ข้อมูลและการตั้งค่าเดิมจากเครื่อง XAMPP

### 6.1 โค้ดและสิทธิ์ไฟล์

```bash
sudo mkdir -p /var/www && sudo chown <ADMIN_USER>:nginx /var/www
git clone git@github.com:<GITHUB_REPO>.git /var/www/rdu
cd /var/www/rdu
```

### 6.2 ไฟล์ที่ไม่อยู่ใน git — คัดลอกจากเครื่อง XAMPP

| ไฟล์ | วิธี | หมายเหตุ |
|---|---|---|
| `.env` | คัดลอกแล้วแก้ค่า (ด้านล่าง) | **คง `APP_KEY` เดิม** |
| `storage/app/private/config/rdu_database.json` | `scp` จากเครื่องเดิม | รหัส HOSxP ที่เข้ารหัสไว้ — ห้ามอยู่ใน `public/` |

ค่าที่ต้องแก้ใน `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rdu.ckhospital.local
LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=rdu_dashboard
DB_USERNAME=rdu_app
DB_PASSWORD=<APP_DB_PASSWORD>

# การเชื่อม HOSxP มาจาก rdu_database.json — ไม่ใส่รหัส HOSxP ใน .env
RDU_DB_PASSWORD=
```

```bash
chmod 640 .env
# แอป (PHP-FPM รันเป็น nginx) ต้องเขียนไฟล์นี้ได้ตอนกด "บันทึก" ในหน้าตั้งค่า HOSxP — ไม่งั้นได้ 500
sudo chown nginx:nginx storage/app/private/config/rdu_database.json
sudo chmod 600 storage/app/private/config/rdu_database.json
```

### 6.3 ย้ายฐานข้อมูลแอปจากเครื่องเดิม (แนะนำ)

Dump **เฉพาะฐาน `rdu_dashboard`** (ไม่ใช่ `pg_dumpall`) บนเครื่อง XAMPP (PowerShell — ปรับ path ตามเวอร์ชัน PostgreSQL ที่ติดตั้ง):

```powershell
& "C:\Program Files\PostgreSQL\<เวอร์ชัน>\bin\pg_dump.exe" -h 127.0.0.1 -U <ผู้ใช้เดิม> -Fc --no-owner --no-privileges `
    -d rdu_dashboard -f rdu_dashboard.dump
scp rdu_dashboard.dump <ADMIN_USER>@<SERVER_IP>:~/
```

บนเครื่องใหม่ (ฐาน `rdu_dashboard` ต้องว่าง — เพิ่งสร้างใน Phase 3):

```bash
pg_restore -h 127.0.0.1 -U rdu_app -d rdu_dashboard --no-owner --no-privileges --role=rdu_app rdu_dashboard.dump
```

ตรวจว่าข้อมูลมาครบ (เทียบจำนวนกับเครื่องเดิม):

```bash
psql -h 127.0.0.1 -U rdu_app -d rdu_dashboard -c "select (select count(*) from users) users, (select count(*) from rdu_indicators) indicators, (select count(*) from audit_logs) audit_logs;"
```

> ⚠️ **ห้ามรัน `php artisan db:seed` (ตัวเต็ม) บนเครื่อง production** — `DatabaseSeeder` สร้างผู้ใช้ `admin` รหัส `admin`
> ถ้าต้องสร้างฐานใหม่แทนการย้าย ให้ใช้ `php artisan db:seed --class=RduIndicatorSeeder --force` เท่านั้น

### 6.4 ติดตั้ง dependency, build และ cache

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # ตรวจก่อนว่า DB_CONNECTION/DB_DATABASE ชี้ rdu_dashboard (ห้ามชี้ HOSxP)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 6.5 สิทธิ์ไฟล์ + SELinux (LXC: ทำเฉพาะ `chown`/`chmod` ข้ามส่วน SELinux)

```bash
sudo chown -R <ADMIN_USER>:nginx /var/www/rdu
sudo chmod -R 775 /var/www/rdu/storage /var/www/rdu/bootstrap/cache

# nginx/PHP-FPM เขียนได้เฉพาะ storage และ bootstrap/cache
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/rdu/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/rdu/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/rdu

# ให้ PHP ต่อออกไปฐานข้อมูล HOSxP (5432) และ HDC (HTTPS) ได้
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_can_network_connect 1

sudo nginx -t && sudo systemctl reload nginx php-fpm
```

**ตรวจผ่าน**
- [ ] เปิด `http(s)://rdu.ckhospital.local` → หน้า login แสดงครบ (CSS/ฟอนต์/โลโก้)
- [ ] login ด้วยผู้ใช้จริงได้ (ผู้ใช้จาก officer ใน HOSxP)
- [ ] `sudo ausearch -m avc -ts recent` → ไม่มี SELinux denial ของ nginx/php-fpm (LXC: ข้าม)
- [ ] `curl -sI https://rdu.ckhospital.local | grep -i x-powered-by` → ไม่มีผลลัพธ์
- [ ] `ls -la storage/app/private/config/` → `rdu_database.json` เป็น `-rw------- nginx nginx`
- [ ] หน้าตั้งค่าฐานข้อมูล HOSxP กด **บันทึก** ได้ (ไม่ขึ้น 500)

---

## Phase 7 — เชื่อมต่อ HOSxP และ HDC

**ต้องทำฝั่งเครื่อง HOSxP (ให้ผู้ดูแล HOSxP ทำ — เราไม่แก้เครื่อง HOSxP เอง):**
เพิ่ม IP `<SERVER_IP>` ใน `pg_hba.conf` ของ HOSxP สำหรับ role `rdu_readonly` เท่านั้น และเปิด firewall พอร์ต 5432 ให้ IP นี้

```bash
# ทดสอบจากเครื่องใหม่ (client psql ติดตั้งมากับ Phase 3)
psql -h 192.168.1.119 -U rdu_readonly -d chk_hosxe -c 'select 1;'

# HDC Open Data
curl -sS -m 15 -X POST https://opendata.moph.go.th/api/report_data \
     -H 'Content-Type: application/json' \
     -d '{"tableName":"s_rdu8","year":"2569","province":"55","type":"json"}' | head -c 200
```

จากนั้นในแอป: หน้าตั้งค่าฐานข้อมูล HOSxP → **ทดสอบการเชื่อมต่อ** และ **ตรวจสอบ Read-Only**

**ตรวจผ่าน** (ตาราง Read-Only Tests ใน `CLAUDE.md`)
- [ ] SELECT บน `rdu` → PASS
- [ ] INSERT / UPDATE / DELETE / CREATE TABLE / DROP TABLE บน `rdu` → BLOCK ทั้งหมด
- [ ] หน้าภาพรวม: ตัวเลข KPI01/03/04/05 ตรงกับเครื่อง XAMPP (ปีงบเดียวกัน)
- [ ] KPI ที่มีป้าย "HDC · ทั้งปี" แสดงตัวเลข (ไม่ขึ้น "HDC ไม่ตอบสนอง"), ปุ่ม HDC เปิด modal ได้

> ชุดทดสอบ (`php artisan test`) ให้รันบนเครื่องพัฒนา **ไม่รันบน production** (ติดตั้งแบบ `--no-dev` และไม่ควรมีการสร้าง/ลบไฟล์ทดสอบบนเครื่องจริง)

---

## Phase 8 — Backup, log และการเฝ้าระวัง

```bash
sudo mkdir -p /backup/rdu && sudo chown postgres:postgres /backup/rdu

# dump ฐานข้อมูลแอปทุกคืน 01:30 เก็บ 14 วัน
sudo tee /etc/cron.d/rdu-backup >/dev/null <<'EOF'
30 1 * * * postgres pg_dump -Fc rdu_dashboard -f /backup/rdu/rdu_dashboard_$(date +\%F).dump
0 2 * * *  postgres find /backup/rdu -name 'rdu_dashboard_*.dump' -mtime +14 -delete
EOF
```

- คัดลอก `/backup/rdu` ออกนอกเครื่องเป็นประจำ (NAS/เครื่อง backup ของโรงพยาบาล)
- เก็บ `.env` และ `rdu_database.json` สำรองไว้ **คนละที่** กับ backup ฐานข้อมูล (สองไฟล์นี้รวมกันถอดรหัส HOSxP ได้)
- Log แอป: `LOG_CHANNEL=daily` (ตั้งไว้ใน Phase 6) — Laravel ลบ log เก่ากว่า 14 วันเอง
- Log nginx/PHP-FPM: logrotate ของระบบจัดการให้แล้ว

**ตรวจผ่าน**
- [ ] รัน `pg_dump` ด้วยมือ 1 ครั้ง แล้ว `pg_restore` ลงฐานทดสอบ (`createdb rdu_restore_test`) ได้ครบ
- [ ] วันถัดมามีไฟล์ `/backup/rdu/rdu_dashboard_<วันที่>.dump`

---

## Phase 9 — สลับใช้งานจริง (Cutover)

1. แจ้งผู้ใช้ช่วงเวลาสลับ (ใช้เวลาประมาณ 30 นาที)
2. เครื่อง XAMPP: หยุดรับงาน แล้ว `pg_dump` ฐานแอปรอบสุดท้าย → restore ทับบนเครื่องใหม่ (ทำ 6.3 ซ้ำ)
3. ยืนยัน DNS `rdu.ckhospital.local → <SERVER_IP>` และแจ้งผู้ใช้ให้ใช้ URL ใหม่ `https://rdu.ckhospital.local` (แทน `http://192.168.1.25/rdu`)
4. ตรวจ Phase 7 ซ้ำบนระบบจริงอีกครั้ง
5. **เครื่อง XAMPP เก็บไว้สภาพเดิมอย่างน้อย 2 สัปดาห์** เป็นแผนถอยกลับ (แค่หยุด Apache ไม่ลบอะไร)

**ตรวจผ่าน**
- [ ] ผู้ใช้อย่างน้อย 2 คนจากเครื่องอื่นใน LAN login และเปิดหน้า KPI ได้
- [ ] ไม่มี error ใหม่ใน `storage/logs/laravel-<วันที่>.log` หลังใช้งาน 1 วัน

---

## Phase 10 — เพิ่ม dashboard อื่นบนเครื่องเดียวกัน

ทำซ้ำต่อ dashboard (แยกกันทุกอย่างเพื่อไม่ให้ระบบหนึ่งกระทบอีกระบบ):

| สิ่งที่แยก | ตัวอย่างสำหรับ dashboard ชื่อ `xxx` |
|---|---|
| โฟลเดอร์ | `/var/www/xxx` + SELinux context แบบ 6.5 |
| ฐานข้อมูล + ผู้ใช้ | `xxx_dashboard` / `xxx_app` (Phase 3) |
| nginx | `/etc/nginx/conf.d/xxx.conf`, `server_name xxx.ckhospital.local` |
| PHP-FPM pool (แนะนำเมื่อมี 3 ระบบขึ้นไป) | `/etc/php-fpm.d/xxx.conf` socket `/run/php-fpm/xxx.sock` |
| Backup | เพิ่มบรรทัดใน `/etc/cron.d/` |

---

## งานต่อเนื่อง (ทำหลังใช้งานจริงแล้ว)

- **Redis เป็น cache** แทน `CACHE_STORE=database` เมื่อมีหลาย dashboard หรือหน้าเริ่มช้า
  (`dnf module enable redis:7`, `dnf install redis php-pecl-redis6`, ตั้ง `CACHE_STORE=redis`)
- **สคริปต์ deploy สำหรับ Linux** แทน `deploy.ps1` (git pull → composer → npm build → migrate → cache → reload php-fpm)
- **อัปเดตเอกสาร** `CLAUDE.md` (Technology Stack, XAMPP Setup), `DEPLOYMENT.md` และ `vhost.conf` ให้ตรงกับ stack ใหม่
