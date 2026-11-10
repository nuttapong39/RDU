# DEPLOY-PROMPT-TEMPLATE.md — Prompt สำหรับ deploy โปรเจกต์ขึ้น server ตั้งแต่เริ่มจนสำเร็จ

เทมเพลตนี้สรุปจากการ deploy **RDU Dashboard** (Laravel 12 → AlmaLinux 9 LXC, nginx + PHP-FPM + PostgreSQL)
ให้นำไปใช้กับโปรเจกต์อื่นได้ โดยคัดลอกส่วน **"PROMPT"** (หัวข้อที่ 2) ไปวางใน Claude Code ที่เปิดอยู่ในโฟลเดอร์โปรเจกต์
หลังกรอกค่าในส่วน **"ข้อมูลโปรเจกต์"** ให้ครบ

- ที่มาของขั้นตอนละเอียด: `docs/SERVER-SETUP-ALMALINUX.md`, `DEPLOYMENT.md`, `scripts/deploy.sh`
- ปรับ stack ได้ (เช่น Ubuntu/Apache/MySQL/Node) — ส่วน "หลักการ" และ "ด่านตรวจผ่าน" ใช้ได้กับทุก stack
  เปลี่ยนเฉพาะคำสั่งในแต่ละ phase

---

## 1. วิธีใช้

1. กรอกตาราง **ข้อมูลโปรเจกต์** ด้านล่าง (ค่าที่ไม่รู้ให้เขียน `ยังไม่ทราบ` — Claude จะถามหรือไปตรวจเอง)
2. คัดลอกบล็อก **PROMPT** ทั้งหมดไปวาง แทนที่ `<...>` ด้วยค่าจริง
3. Claude จะทำทีละ phase และ **หยุดรอคุณ** ที่จุดที่ต้องใช้สิทธิ์ของคนจริง (รหัสผ่าน, sudo, DNS, firewall ฝั่งอื่น)
4. เมื่อจบ ได้ไฟล์ `DEPLOYMENT.md` + `scripts/deploy.sh` ของโปรเจกต์นั้น และรายงานผลตรวจผ่านทุก phase

### ข้อมูลโปรเจกต์ (กรอกก่อน)

| หัวข้อ | ค่า | ตัวอย่าง (RDU) |
|---|---|---|
| ชื่อโปรเจกต์ / slug | `<APP_NAME>` / `<APP_SLUG>` | RDU Dashboard / `rdu` |
| Framework + เวอร์ชัน | | Laravel 12, PHP 8.3 |
| Git repo (private/public) | `<GITHUB_REPO>` | `nuttapong39/RDU` |
| Branch ที่ deploy | `main` | `main` |
| Server: IP / OS / ชนิด | `<SERVER_IP>` / | 192.168.1.244 / AlmaLinux 9 / Proxmox LXC |
| ผู้ใช้ผู้ดูแล (ไม่ใช่ root) | `<ADMIN_USER>` | `nuttapong` |
| URL ที่ผู้ใช้เปิด | `<APP_URL>` | `http://rdu.ckhospital.local` |
| DNS / ใครจัดการ | | A record ในโซน AD (Univention UCS) |
| HTTPS | ไม่ใช้ / CA ภายใน / Let's Encrypt | HTTP ในวง LAN (วางแผน CA ภายใน) |
| ฐานข้อมูลของแอป | ชนิด / ชื่อ / ผู้ใช้ | PostgreSQL 16 / `rdu_dashboard` / `rdu_app` |
| ฐานข้อมูลภายนอกที่ต้องต่อ | host / สิทธิ์ที่ต้องการ | HOSxP 192.168.1.119 **SELECT only** |
| บริการภายนอก (internet) | | HDC `opendata.moph.go.th`, Google Fonts |
| มีระบบอื่นบน server เดียวกันไหม | ชื่อ / พอร์ต | `ck-asset-registry` (ใช้ IP ตรง) |
| ข้อมูลเดิมที่ต้องย้าย | ไม่มี / `pg_dump` ฐานไหน | ฐาน `rdu_dashboard` จาก XAMPP |
| ไฟล์ลับนอก git | | `.env`, `storage/app/private/config/rdu_database.json` |
| ข้อห้ามเฉพาะโปรเจกต์ | | HOSxP เขียนไม่ได้, ห้าม `db:seed` เต็มบน prod |
| รันคำสั่ง deploy จากที่ไหน | | dev ผ่าน `ssh -t` (ผู้ใช้รันเอง) |

---

## 2. PROMPT (คัดลอกตั้งแต่บรรทัดนี้)

````markdown
คุณคือวิศวกร DevOps ช่วยฉัน **deploy โปรเจกต์ <APP_NAME> ขึ้น server ตั้งแต่ต้นจนใช้งานได้จริง**
ตอบเป็นภาษาไทย อ่านโค้ดและเอกสารของโปรเจกต์ก่อนลงมือ แล้วทำตามแผนด้านล่างทีละ phase

## ข้อมูลที่รู้แล้ว
- โปรเจกต์: <APP_NAME> (<APP_SLUG>) · <Framework + เวอร์ชัน> · repo `<GITHUB_REPO>` branch `<BRANCH>`
- Server: <SERVER_IP> · <OS> · ผู้ดูแล `<ADMIN_USER>` · URL ที่ผู้ใช้เปิด `<APP_URL>`
- ฐานข้อมูลของแอป: <ชนิด/ชื่อ/ผู้ใช้> · ฐานข้อมูลภายนอก: <host + สิทธิ์ที่ต้องการ>
- ระบบอื่นบน server เดียวกัน: <รายการหรือ "ไม่มี">
- ข้อห้ามเฉพาะโปรเจกต์: <เช่น DB ภายนอกต้อง SELECT only>
- ข้อมูลที่ยังไม่ทราบ: <รายการ> → ตรวจเองจากโค้ด/เซิร์ฟเวอร์ก่อน ถ้าตรวจไม่ได้ค่อยถามฉัน

## กฎที่ห้ามละเมิด
1. **ห้ามพิมพ์รหัสผ่าน/โทเคน/คีย์** แทนฉัน และห้ามแสดงค่าจาก `.env` ในคำตอบ — ถ้าต้องใช้ ให้บอกฉันรันเอง
   (แนะนำให้ฉันใช้ `! <คำสั่ง>` ใน prompt เพื่อให้ผลลัพธ์เข้า session)
2. **ถามก่อนทุกครั้งที่จะลบ/ทับ/ทำลาย**: ลบไฟล์, DROP/TRUNCATE/DELETE, `migrate:fresh`, `git reset --hard`,
   force push, ปิดบริการที่มีผู้ใช้อยู่ — บอกว่าจะลบอะไร แล้วรอฉันตอบอนุญาตก่อน แม้เป็นส่วนของแผนที่อนุมัติไว้
3. **ห้ามรัน seed/migration ที่สร้างผู้ใช้ทดสอบหรือข้อมูลปลอมบน production** และห้ามให้ migration ชี้ฐานข้อมูลภายนอก
4. **commit/push/ลงมือบน server เฉพาะเมื่อฉันสั่ง** — คำสั่งที่ต้อง `sudo` แบบ interactive หรือรหัสผ่าน ฉันรันเองในเทอร์มินัลของฉัน
5. **ทุก phase ต้องมี "ตรวจผ่าน"** รันจริงและรายงานผลจริง (ผ่าน/ไม่ผ่าน พร้อมผลลัพธ์) ห้ามข้ามไป phase ถัดไปถ้ายังไม่ผ่าน
   ถ้าอะไรไม่ผ่านหรือข้ามไป ให้บอกตรงๆ ไม่สรุปว่าเรียบร้อย
6. **ไม่ใส่ credential ใน git หรือเอกสาร** — ใช้ placeholder เช่น `<APP_DB_PASSWORD>`; `.env` และไฟล์ลับอยู่นอก git และ `.gitignore`
7. แก้โค้ดที่เครื่อง dev เสมอ — server รับโค้ดผ่าน git เท่านั้น ห้ามแก้ไฟล์ที่ git ติดตามบน server

## วิธีทำงาน
- เริ่มด้วย **Phase 0 (สำรวจ)** แล้วสรุปแผนสั้นๆ ให้ฉันยืนยัน ก่อนแตะ server
- ทำทีละ phase: อธิบายสั้นๆ → รันคำสั่ง (บน server ผ่าน SSH key; ถ้าเข้าไม่ได้ให้ฉันรัน) → รันรายการ "ตรวจผ่าน" → รายงาน
- ไม่สะสมสรุปยาว ให้รายงานเป็นตาราง/เช็กลิสต์ ผ่าน/ไม่ผ่าน
- เจอ error: หา root cause ก่อนลองใหม่ ห้ามวนคำสั่งเดิมซ้ำ ถ้าแก้ 2–3 ครั้งไม่หาย ให้หยุดและเล่าสิ่งที่ลอง
- บันทึกปัญหาที่เจอและวิธีแก้ลงตาราง "ปัญหาที่เคยเจอ" ใน `DEPLOYMENT.md` ของโปรเจกต์

## Phase 0 — สำรวจโปรเจกต์ (ยังไม่แตะ server)
อ่าน `composer.json`/`package.json`, `.env.example`, `config/database.php`, migrations/seeders, `.gitignore`, ไฟล์ docs ที่มี
สรุป: เวอร์ชัน PHP/Node ที่ต้องใช้ + extension, ฐานข้อมูลทุกตัวที่แอปต่อ, ไฟล์ลับนอก git, ปลายทางภายนอกที่ต้องออก internet,
seeder ที่ไม่ปลอดภัยบน prod, ขั้นตอน build asset, ตัวแปร `.env` ที่ production ต้องตั้ง
**ตรวจผ่าน:** `git status` สะอาด, test ผ่านบน dev, repo push ขึ้น `<BRANCH>` แล้ว

## Phase 1 — OS พื้นฐานและความปลอดภัย
อัปเดตระบบ, timezone/hostname, IP คงที่, ผู้ดูแลไม่ใช่ root, **SSH key ก่อนแล้วจึงปิด root + password login**,
firewall เปิดเฉพาะ ssh/http(/https), อัปเดตความปลอดภัยอัตโนมัติ
- ตรวจ `systemd-detect-virt` — ถ้าเป็น LXC: ไม่ใช้ chronyd (ใช้เวลาจาก host), SELinux มักเป็น Disabled ให้ข้ามคำสั่ง
  `semanage/restorecon/setsebool` ทั้งหมดและใช้สิทธิ์ไฟล์แทน
**ตรวจผ่าน:** login ด้วย key ได้/ด้วย root หรือ password ไม่ได้ · `firewall-cmd --list-services` มีแค่ที่ต้องใช้ ·
`timedatectl` ถูกโซนและ sync · IP คงเดิมหลัง reboot

## Phase 2 — Runtime (PHP-FPM / Node ฯลฯ)
ติดตั้งเวอร์ชันตามที่ Phase 0 สรุป พร้อม extension ครบ (pdo ของ DB ที่ใช้, mbstring, intl, bcmath, xml, zip, gd, sodium, opcache),
ตั้ง FPM ให้รันเป็นผู้ใช้เดียวกับ web server, ตั้ง `php.ini` (timezone, `expose_php=Off`, opcache)
- ถ้าเปิด `opcache.validate_timestamps=0` ต้อง reload php-fpm หลัง deploy ทุกครั้ง (ใส่ใน deploy.sh)
**ตรวจผ่าน:** `php -v`, `php -m` ครบ extension, `php -i` ได้ timezone/expose_php ตามตั้ง

## Phase 3 — ฐานข้อมูลของแอป
ติดตั้ง DB บน server, **ฟังเฉพาะ localhost** (ไม่เปิดพอร์ตใน firewall), สร้างผู้ใช้เฉพาะแอป (ไม่ใช้ superuser) และฐานข้อมูลเปล่า
PostgreSQL: เปลี่ยน `pg_hba.conf` localhost เป็น `scram-sha-256`
**ตรวจผ่าน:** เชื่อมด้วยผู้ใช้แอปผ่าน 127.0.0.1 ได้ · `ss -ltnp` พอร์ต DB ฟังที่ loopback เท่านั้น

## Phase 4 — Web server
nginx → PHP-FPM ผ่าน unix socket, `root` ชี้ `public/` เท่านั้น, `server_tokens off`, header ความปลอดภัย, `deny` ไฟล์ซ่อน
- **`server_name` เป็นชื่อเครื่องของโปรเจกต์นี้ชื่อเดียว ห้ามใส่ IP** ถ้ามีระบบอื่นบน server (ชนกันจะเจอ `conflicting server name`
  หรือเปิดชื่อแล้วเจอระบบอื่น)
- query ช้า ให้ตั้ง `fastcgi_read_timeout`
**ตรวจผ่าน:** `nginx -t` · nginx และ php-fpm `active` · `curl -I` จากเครื่องอื่นใน LAN ได้ และ header ไม่เปิดเผยเวอร์ชัน ·
`/.env` ตอบ 403/404

## Phase 5 — เครื่องมือ deploy
ติดตั้ง Composer/Node, สร้าง **deploy key แบบอ่านอย่างเดียว** (ed25519, ไม่ติ๊ก write access) ให้ฉันนำ public key ไปใส่ GitHub
**ตรวจผ่าน:** `composer --version`, `node -v`, `ssh -T git@github.com` สำเร็จ

## Phase 6 — ติดตั้งแอป
1. `git clone` ไป `/var/www/<APP_SLUG>` (เจ้าของ `<ADMIN_USER>:nginx`)
2. ไฟล์นอก git: สร้าง/คัดลอก `.env` (ค่า production: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=daily`,
   `APP_URL`, DB ของแอป) — **คง `APP_KEY` เดิมถ้ามีข้อมูลที่เข้ารหัสด้วยคีย์นั้น** · ไฟล์ลับอื่นให้ฉัน `scp` เอง
3. ย้ายข้อมูลเดิม (ถ้ามี): `pg_dump -Fc --no-owner --no-privileges` เฉพาะฐานของแอป → `pg_restore` เข้าฐานเปล่า แล้วเทียบจำนวนแถวกับต้นทาง
   (ฐานว่างเท่านั้น — ถ้าไม่ว่าง ถามฉันก่อน)
4. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`
   (ตรวจ `DB_DATABASE` ก่อน), seeder เฉพาะตัวที่ปลอดภัยบน prod
5. `config:cache route:cache view:cache` — ระวังอย่าให้ config cache เขียนรหัสลับที่ถอดแล้วลงไฟล์ cache
6. **สิทธิ์ไฟล์:** `chown -R <ADMIN_USER>:nginx`; ใช้ `find` ตั้งโฟลเดอร์ `775`/ไฟล์ `664` เฉพาะ `storage` และ `bootstrap/cache`
   **ห้าม `chmod -R 775`** (ไฟล์ได้ bit execute → git เห็นว่าถูกแก้ → deploy.sh ปฏิเสธ) · ไฟล์ลับที่แอปเขียนเอง → เจ้าของ `nginx`, `600`
   · `.env` เป็น `640` · VM ที่มี SELinux ให้ตั้ง context `httpd_sys_rw_content_t` และ boolean ตามที่แอปต้องการ
**ตรวจผ่าน:** เปิด `<APP_URL>` แล้วหน้า login ครบ (CSS/ฟอนต์/ไอคอน) · login ได้ · ฟีเจอร์ที่เขียนไฟล์/session/cache ทำงาน ·
ไม่มี `X-Powered-By` · `git status` บน server สะอาด

## Phase 7 — เชื่อมระบบภายนอก
สำหรับ DB/API ภายนอก: บอกฉันว่าฝั่งนั้นต้องแก้อะไร (เช่น `pg_hba.conf`, firewall) — **อย่าแก้เครื่องของคนอื่นเอง**
ทดสอบจาก server ด้วย client จริง (`psql`, `curl`)
ถ้าเป็นสิทธิ์อ่านอย่างเดียว ต้องทดสอบว่า SELECT ผ่าน และ INSERT/UPDATE/DELETE/CREATE/DROP **ถูกบล็อกจริง**
**ตรวจผ่าน:** ตัวเลขหน้าหลักตรงกับเครื่อง dev (ปี/ช่วงเดียวกัน) · ปลายทาง internet ตอบได้

## Phase 8 — Backup / log
cron `pg_dump` ทุกคืน เก็บ 14 วัน · log แบบ daily · เก็บ `.env` และไฟล์ลับสำรอง **คนละที่กับ backup DB**
ถ้ามี `find ... -delete` ใน cron ให้บอกฉันและขออนุญาตก่อนสร้าง
**ตรวจผ่าน:** รัน dump มือ 1 ครั้งแล้ว restore ลงฐานทดสอบได้ครบ

## Phase 9 — สคริปต์ deploy ครั้งถัดไป (`scripts/deploy.sh`)
สร้างสคริปต์ idempotent ตามแม่แบบหัวข้อที่ 3 ของ `docs/DEPLOY-PROMPT-TEMPLATE.md` (หรือที่ใกล้เคียง) ที่:
ตรวจความปลอดภัย (`APP_ENV`, ชื่อ DB, ไม่มีไฟล์ที่ git ติดตามถูกแก้) → `down` → ff-only merge → composer → build → migrate →
seed ที่ปลอดภัย → cache → reload php-fpm → `up`
ล้มเหลวก่อนเปลี่ยนโค้ดต้องกลับเว็บเดิมเอง · ล้มเหลวหลังเปลี่ยนโค้ดต้องค้างหน้าปิดปรับปรุงและรันซ้ำแล้วทำต่อได้
ต้องไม่แตะ `.env` และไฟล์ลับ · ขั้น `sudo` ต้องตั้ง sudoers ให้ผู้ดูแลรัน `systemctl reload php-fpm` แบบไม่ถามรหัสผ่าน
(ถ้าไม่ตั้ง ให้ฉันรันแบบ `ssh -t`)
**ตรวจผ่าน:** deploy "ไม่มีอะไรเปลี่ยน" แล้วออกด้วยข้อความ Already up to date · deploy commit จริง 1 ครั้งแล้วเว็บกลับมาใช้ได้

## Phase 10 — ส่งมอบ
เขียน/อัปเดต `DEPLOYMENT.md` (สถาปัตยกรรม, วิธี deploy ประจำ, ไฟล์นอก git, ปัญหาที่เคยเจอ, security checklist)
และ `CLAUDE.md` ส่วน Environments & Deploy · รายงานเช็กลิสต์ทุก phase และสิ่งที่ฉันต้องทำเองที่เหลือ
**ห้าม commit/push เอกสารจนกว่าฉันจะสั่ง**
````

(จบ PROMPT)

---

## 3. แม่แบบ `scripts/deploy.sh` (ปรับชื่อ DB / คำสั่ง seed / service ตามโปรเจกต์)

หลักการ: **หยุดที่ขั้นแรกที่พัง** — พังก่อนเปลี่ยนโค้ดให้เว็บกลับเดิม, พังหลังเปลี่ยนโค้ดให้ค้างหน้าปิดปรับปรุงแล้วรันซ้ำต่อจนจบ

```bash
#!/usr/bin/env bash
# Deploy the latest <BRANCH> on the production server.
#   cd /var/www/<APP_SLUG> && ./scripts/deploy.sh
#   ssh -t <ADMIN_USER>@<SERVER_IP> /var/www/<APP_SLUG>/scripts/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

# ---- Safety checks ----
[ -f .env ] || fail ".env not found — this is not a configured install"
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"'; }
[ "$(env_value APP_ENV)" = "production" ]   || fail "APP_ENV is not production"
[ "$(env_value DB_DATABASE)" = "<APP_DB>" ] || fail "DB_DATABASE is not <APP_DB> — migrations must never hit another DB"
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    fail "Files tracked by git were edited on the server — change them on the dev machine"
fi

# ---- Fetch ----
step "Fetching"
before=$(git rev-parse --short HEAD)
git fetch --quiet origin <BRANCH>
after=$(git rev-parse --short origin/<BRANCH>)
resuming=false
if [ "$before" = "$after" ]; then
    if [ -f storage/framework/down ]; then
        resuming=true; echo "Already at $after but in maintenance — finishing the previous deploy."
    else
        echo "Already up to date ($before)."; exit 0
    fi
else
    git --no-pager log --oneline "$before..$after"
fi

# ---- Deploy ----
if [ "$resuming" = false ]; then
    step "Maintenance mode on"
    php artisan down --retry=15
    trap 'php artisan up >/dev/null 2>&1; fail "Failed before the code changed — site is back on $before."' ERR
    step "Updating code ($before → $after)"
    git merge --ff-only --quiet origin/<BRANCH>
fi
trap 'fail "Failed on $after — site is still in maintenance. Fix the error and re-run."' ERR

step "PHP dependencies";  composer install --no-dev --optimize-autoloader --no-interaction
step "Front-end build";   npm ci --no-audit --no-fund && npm run build
step "Migrations";        php artisan migrate --force
step "Safe seeders";      php artisan db:seed --class=<SafeSeeder> --force   # ห้ามใช้ seeder ที่สร้างผู้ใช้ทดสอบ
step "Caches";            php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan cache:clear
step "Reload PHP-FPM";    sudo systemctl reload php-fpm   # OPcache ไม่ดูไฟล์เปลี่ยน

trap - ERR
step "Maintenance mode off"; php artisan up
printf '\n\033[1;32m✓ Deployed %s → %s\033[0m\n' "$before" "$after"
```

ข้อควรระวัง: ขั้น `sudo` ที่ไม่มี sudoers แบบ NOPASSWD จะค้างรอรหัสผ่าน — เมื่อรันแบบไม่มี TTY สคริปต์จะพังหลังเปลี่ยนโค้ด
(เว็บค้างหน้าปิดปรับปรุง) จึงให้รันด้วย `ssh -t` เสมอ หรือตั้ง sudoers เฉพาะคำสั่ง reload

---

## 4. ปัญหาที่เจอจริงตอน deploy RDU (ใส่ไว้ให้ Claude ระวัง)

| อาการ | สาเหตุ | แก้ / ป้องกัน |
|---|---|---|
| หน้าตั้งค่า "บันทึก" แล้ว 500 `file_put_contents ... Permission denied` | ไฟล์ config ที่แอปเขียนเองไม่ใช่ของผู้ใช้ FPM | `chown nginx:nginx` + `chmod 600` |
| deploy.sh: "Files tracked by git were edited" ทั้งที่ไม่ได้แก้ | ใช้ `chmod -R 775` ทำให้ไฟล์มี bit execute | `find` แยก dir `775` / file `664` |
| เปิดชื่อเครื่องแล้วเจอระบบอื่น / `conflicting server name` | `server_name` ซ้ำหรือใส่ IP | 1 ชื่อเครื่องต่อ 1 ระบบ ห้ามใส่ IP |
| ผู้ใช้เปิดชื่อเครื่องไม่ได้ | ไม่มี A record ใน DNS ขององค์กร | ขอผู้ดูแล DNS เพิ่ม; ระหว่างรอใช้ไฟล์ `hosts` ทดสอบ |
| `chronyd` / `adjtimex: Operation not permitted` | server เป็น LXC ตั้งนาฬิกาเองไม่ได้ | ปิด chronyd ใช้เวลาจาก host |
| `semanage` / `setsebool` ใช้ไม่ได้ | LXC ไม่มี SELinux | ข้ามทั้งชุด ใช้สิทธิ์ไฟล์ |
| `could not find driver` | ยังไม่เปิด extension `pdo_*` | ติดตั้ง/เปิด แล้ว restart service |
| `password authentication failed` ต่อ DB ภายนอก | รหัสในไฟล์ลับผิด หรือ `APP_KEY` ไม่ตรงกับตอนเข้ารหัส | กรอกใหม่ผ่านหน้าตั้งค่า (ห้ามเปลี่ยน `APP_KEY` บนเครื่องที่ใช้อยู่) |
| `no pg_hba.conf entry for host` | DB ภายนอกยังไม่อนุญาต IP server | ผู้ดูแล DB เพิ่มบรรทัดให้ role ที่อ่านอย่างเดียว |
| ไอคอน/ฟอนต์ไม่ขึ้น | เครื่องผู้ใช้ออก internet ไม่ได้ (Google Fonts) | เปิดให้เข้าถึง `fonts.googleapis.com` / `fonts.gstatic.com` หรือ host ฟอนต์เอง |
| artisan บน server อ่าน `bootstrap/cache/config.php` ไม่ได้ | `config:cache` เขียนรหัสที่ถอดแล้วลงไฟล์ cache ที่เป็นของ web user | อย่าใส่รหัสลับลง `config()` ตอน boot — สร้าง connection ตอนใช้งานครั้งแรก |
| รัน test แล้วไฟล์ config จริงหาย | test เขียน/ลบไฟล์จริง | ให้ test ใช้ storage ชั่วคราว; ไม่รัน test บน production (`--no-dev`) |
| `db:seed` เต็มบน production สร้างผู้ใช้ `admin/admin` | seeder ออกแบบไว้สำหรับ dev | ใช้ `--class=` เฉพาะ seeder ที่ปลอดภัยเท่านั้น |
| `sudo` ใน deploy.sh ค้างแบบไม่มี TTY | ต้องใส่รหัสผ่าน | รัน `ssh -t` หรือตั้ง sudoers NOPASSWD เฉพาะคำสั่ง reload |

---

## 5. Security checklist ก่อนบอกว่า "เสร็จ"

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `.env` สิทธิ์ `640`, ไฟล์ลับที่แอปเขียนเองเป็นของ web user สิทธิ์ `600`
- [ ] `bootstrap/cache/config.php` ไม่มีรหัสลับที่ถอดแล้ว
- [ ] nginx `root` ชี้ `public/` และ `/.env` ตอบ 403/404
- [ ] ไม่มีผู้ใช้/รหัสเริ่มต้น (`admin/admin`) ในระบบ
- [ ] สิทธิ์ของผู้ใช้ DB ภายนอกเป็นอ่านอย่างเดียวจริง (ทดสอบเขียนแล้วถูกบล็อก)
- [ ] SSH: root และรหัสผ่านถูกปิด · firewall เปิดเฉพาะพอร์ตที่ใช้ · พอร์ต DB ไม่เปิดสู่ภายนอก
- [ ] backup ล่าสุดอายุไม่เกิน 1 วัน และ restore ทดสอบแล้ว
- [ ] `git status` บน server สะอาด และ `deploy.sh` รันซ้ำแล้วขึ้น "Already up to date"

---

## 6. ต่อยอด

- เพิ่ม dashboard/ระบบที่ 2 บน server เดียวกัน: แยกโฟลเดอร์, DB + ผู้ใช้, `server_name`, cron backup (ระบบ 3 ตัวขึ้นไปแนะนำแยก PHP-FPM pool)
- เปลี่ยน cache เป็น Redis เมื่อหลายระบบหรือหน้าเริ่มช้า
- ใส่ HTTPS ด้วย CA ภายใน (`.local` ใช้ Let's Encrypt ไม่ได้) แล้ว redirect 80 → 443
- เพิ่ม CI (GitHub Actions) รัน test ก่อน merge เข้า `main` แทนการรันบนเครื่อง dev อย่างเดียว
- เปลี่ยน prompt เป็น skill ของ Claude Code (`.claude/skills/deploy-server/SKILL.md`) เพื่อเรียกด้วย `/deploy-server`
