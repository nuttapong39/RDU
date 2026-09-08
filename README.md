<h1 align="center">RDU Dashboard</h1>
<p align="center">
  <b>ระบบ Dashboard ตัวชี้วัดการใช้ยาอย่างสมเหตุผล (Rational Drug Use)</b><br>
  โรงพยาบาลเชียงกลาง จ.น่าน
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-red?style=flat-square&logo=laravel" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.3+-blue?style=flat-square&logo=php" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PostgreSQL-15+-blue?style=flat-square&logo=postgresql" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/HOSxP-Read--Only-orange?style=flat-square" alt="HOSxP Read-Only">
  <img src="https://img.shields.io/badge/Tests-199%20passing-success?style=flat-square" alt="Tests">
</p>

---

## 📖 คู่มือการติดตั้ง (Install Guide)

เลือกดูตามแบบที่คุณสะดวก:

### 🎨 แบบ Interactive (แนะนำ) — install.html

Interactive install wizard พร้อม copy-to-clipboard, step tracker, progress bar

**ดูออนไลน์ทันที (GitHub Pages):**
> 👉 https://nuttapong39.github.io/RDU/install.html

**หรือดู offline (หลัง clone):**
```powershell
cd C:\xampp\htdocs\RDU
start install.html
```

### 📄 แบบ Markdown — DEPLOYMENT.md

Comprehensive step-by-step guide + troubleshooting + security checklist

> 👉 [DEPLOYMENT.md](./DEPLOYMENT.md)

---

## ⚡ Quick Start (สำหรับคนที่รีบ)

```powershell
# 1. Clone
cd C:\xampp\htdocs
git clone https://github.com/nuttapong39/RDU.git
cd RDU

# 2. Install
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# 3. Configure
copy .env.example .env
php artisan key:generate
notepad .env    # แก้ APP_URL, DB_PASSWORD

# 4. Database
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -c "CREATE DATABASE rdu_dashboard;"
php artisan migrate --seed

# 5. Serve + Login admin/admin
php artisan serve --host=0.0.0.0 --port=8000
```

Login: `admin` / `admin` → ตั้งค่า HOSxP connection ผ่าน Admin UI

---

## 🔄 Update (บนเครื่อง production)

```powershell
cd C:\xampp\htdocs\RDU
.\deploy.ps1
```

Script อัจฉริยะ: ตรวจว่าอะไรเปลี่ยน แล้วรันเฉพาะที่จำเป็น (composer / npm / migrate / cache clear)

---

## 🎯 Features

- **13 KPI Indicators** — Antibiotic (5), DrugQuality (3), PatientSafety (4), ChronicDisease (1)
- **HOSxP Integration** — Read-only ผ่าน VIEW schema (`rdu_kpi`) ที่จำกัดสิทธิ์เฉพาะ column ที่ใช้
- **Dashboard**  — Welcome banner, category summary, KPI grid grouped by category, trend charts (Chart.js)
- **KPI Detail** — Hero metric card, 12-month trend, drill-down table, export (Excel/CSV/PDF)
- **Design System** — Sarabun + Material Symbols Outlined + Tailwind v4 + custom tokens
- **Auth** — HOSxP officer table (primary) + local admin fallback
- **Security** — 3-layer read-only enforcement + encrypted DB config + audit log
- **Responsive** — Collapsible sidebar with gradient + medicine pattern (works mobile & desktop)

---

## 🏗 Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 · PHP 8.3+ |
| Frontend | Blade · Tailwind v4 · Chart.js 4.4 · Material Symbols |
| Application DB | PostgreSQL 15+ (`rdu_dashboard`) |
| Hospital DB | HOSxP / PostgreSQL — **SELECT ONLY** via `rdu_kpi` schema |
| Build | Vite + npm |
| Web Server | Apache (XAMPP) |

---

## 🔐 Security

RDU Dashboard เชื่อมต่อ HOSxP แบบ **อ่านอย่างเดียวเท่านั้น** ผ่าน 3 ชั้น:

1. **Laravel layer** — `ReadonlyHosxpModel` throws exception หากมี write
2. **Query layer** — `ReadonlyConnection` block ทุก INSERT/UPDATE/DELETE/CREATE/DROP
3. **PostgreSQL layer** — user `rdu_readonly` มีสิทธิ์ `SELECT` เท่านั้น + VIEW จำกัด column

**Sensitive files ที่ไม่อยู่ใน git:**
- `.env` (APP_KEY, DB password)
- `storage/app/private/config/rdu_database.json` (HOSxP encrypted credentials)

ดู [DEPLOYMENT.md § 9](./DEPLOYMENT.md#9-security-checklist) สำหรับ security checklist ครบชุด

---

## 📚 Documentation

| ไฟล์ | เนื้อหา |
|---|---|
| [install.html](./install.html) | Interactive install guide |
| [DEPLOYMENT.md](./DEPLOYMENT.md) | Deployment + troubleshooting คู่มือละเอียด |
| [CLAUDE.md](./CLAUDE.md) | Project overview + coding conventions |
| [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) | Data flow + architecture |
| [docs/KPI-SPECIFICATION.md](./docs/KPI-SPECIFICATION.md) | 13 KPI definitions + formulas |
| [docs/DATABASE-SECURITY.md](./docs/DATABASE-SECURITY.md) | Read-only enforcement rules |

---

## 🧪 Testing

```powershell
php artisan test                           # ทั้งหมด (199 tests)
php artisan test --filter=DashboardTest    # เฉพาะ Dashboard (19 tests)
```

---

## 📝 License

Internal use — โรงพยาบาลเชียงกลาง จ.น่าน
Framework: Laravel [MIT License](https://opensource.org/licenses/MIT)

---

<p align="center">
  <b>Maintainer:</b> Nuttapong ·
  <a href="mailto:ckhossystem@gmail.com">ckhossystem@gmail.com</a>
</p>
