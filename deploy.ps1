# deploy.ps1 — RDU Dashboard Update Script
# ใช้บน target machine ในโรงพยาบาลเพื่ออัปเดตจาก GitHub อัตโนมัติ
# วิธีใช้: cd C:\xampp\htdocs\RDU  แล้ว  .\deploy.ps1

$ErrorActionPreference = 'Stop'

Write-Host ""
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host "  RDU Dashboard — Deployment Script" -ForegroundColor Cyan
Write-Host "  โรงพยาบาลเชียงกลาง จ.น่าน" -ForegroundColor Cyan
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host ""

# ---- 1. ตรวจ prerequisites ----
Write-Host "[1/8] ตรวจ prerequisites..." -ForegroundColor Yellow
foreach ($cmd in @('git', 'php', 'composer', 'npm')) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        Write-Host "  ❌ ไม่พบ $cmd — กรุณาติดตั้งก่อน" -ForegroundColor Red
        exit 1
    }
}
Write-Host "  ✓ พบทุกเครื่องมือครบ" -ForegroundColor Green

# ---- 2. ตรวจว่าอยู่ใน git repo ----
Write-Host "[2/8] ตรวจสถานะ git..." -ForegroundColor Yellow
if (-not (Test-Path ".git")) {
    Write-Host "  ❌ ไม่ใช่ git repo — กรุณา clone จาก GitHub ก่อน" -ForegroundColor Red
    exit 1
}

$dirty = git status --porcelain
if ($dirty) {
    Write-Host "  ⚠️  พบไฟล์ที่แก้ไขในเครื่อง — จะไม่ทับของเดิม" -ForegroundColor Yellow
    Write-Host "     ต้องการ reset ก่อน pull? (y/N)" -ForegroundColor Yellow
    $answer = Read-Host
    if ($answer -eq 'y') {
        git stash push -u -m "auto-stash before deploy $(Get-Date -Format 'yyyyMMdd-HHmm')"
        Write-Host "  ✓ Stashed local changes" -ForegroundColor Green
    }
}

# ---- 3. Pull code ----
Write-Host "[3/8] Pull code จาก GitHub..." -ForegroundColor Yellow
$before = git rev-parse HEAD
git pull origin main
$after = git rev-parse HEAD

if ($before -eq $after) {
    Write-Host "  ✓ Code เป็นเวอร์ชันล่าสุดอยู่แล้ว — ไม่มีการเปลี่ยนแปลง" -ForegroundColor Green
    Write-Host ""
    Write-Host "เสร็จเรียบร้อย (ไม่มีอะไรอัปเดต)" -ForegroundColor Cyan
    exit 0
}
Write-Host "  ✓ Pull สำเร็จ ($before → $after)" -ForegroundColor Green

# ---- 4. Composer install (ถ้า composer.lock เปลี่ยน) ----
Write-Host "[4/8] ตรวจ composer.lock..." -ForegroundColor Yellow
$composerChanged = git diff --name-only $before $after | Select-String -Pattern "^composer\.lock$"
if ($composerChanged) {
    Write-Host "  → composer.lock เปลี่ยน — รัน composer install" -ForegroundColor Yellow
    composer install --optimize-autoloader --no-dev
    Write-Host "  ✓ Composer packages อัปเดตเรียบร้อย" -ForegroundColor Green
} else {
    Write-Host "  ✓ composer.lock ไม่เปลี่ยน — ข้าม" -ForegroundColor Green
}

# ---- 5. NPM install + build (ถ้า package-lock.json เปลี่ยน หรือ assets เปลี่ยน) ----
Write-Host "[5/8] ตรวจ npm dependencies + assets..." -ForegroundColor Yellow
$npmChanged = git diff --name-only $before $after | Select-String -Pattern "^(package-lock\.json|resources/(css|js)/.*)$"
if ($npmChanged) {
    Write-Host "  → package-lock หรือ CSS/JS เปลี่ยน — build ใหม่" -ForegroundColor Yellow
    $pkgLockChanged = git diff --name-only $before $after | Select-String -Pattern "^package-lock\.json$"
    if ($pkgLockChanged) {
        npm ci
        Write-Host "  ✓ NPM packages อัปเดต" -ForegroundColor Green
    }
    npm run build
    Write-Host "  ✓ Assets build เรียบร้อย" -ForegroundColor Green
} else {
    Write-Host "  ✓ ไม่มีการเปลี่ยนแปลง frontend — ข้าม" -ForegroundColor Green
}

# ---- 6. Migrations ----
Write-Host "[6/8] ตรวจ migrations ใหม่..." -ForegroundColor Yellow
$migrationsChanged = git diff --name-only $before $after | Select-String -Pattern "^database/migrations/"
if ($migrationsChanged) {
    Write-Host "  → มี migration ใหม่ — รัน php artisan migrate --force" -ForegroundColor Yellow
    php artisan migrate --force
    Write-Host "  ✓ Migrations สำเร็จ" -ForegroundColor Green
} else {
    Write-Host "  ✓ ไม่มี migration ใหม่ — ข้าม" -ForegroundColor Green
}

# ---- 7. Clear caches ----
Write-Host "[7/8] ล้าง cache..." -ForegroundColor Yellow
php artisan view:clear   | Out-Null
php artisan cache:clear  | Out-Null
php artisan config:clear | Out-Null
php artisan route:clear  | Out-Null
Write-Host "  ✓ Cache ทั้งหมดถูกล้างแล้ว" -ForegroundColor Green

# ---- 8. Summary ----
Write-Host "[8/8] สรุปการเปลี่ยนแปลง:" -ForegroundColor Yellow
Write-Host ""
git log --oneline "$before..$after" | ForEach-Object { Write-Host "  • $_" -ForegroundColor White }

Write-Host ""
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  ✅ Deployment เสร็จสมบูรณ์" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green
Write-Host ""
Write-Host "แนะนำ: ทดสอบระบบก่อนแจ้งผู้ใช้ — เปิด browser และ login" -ForegroundColor Cyan
Write-Host ""
