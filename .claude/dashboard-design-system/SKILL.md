---
name: dashboard-design-system
description: >
  ระบบดีไซน์ UI/UX "HR-CENTER 4.0" (sidebar+topbar shell, KPI card, module/department summary card,
  Chart.js trend, status badge, welcome banner) ที่ใช้จริงในระบบ MedAlert (รพ.เชียงกลาง) — สกัดเป็น
  design tokens + component library พร้อม copy ไปสร้าง Dashboard/KPI ของหน่วยงานอื่นให้โทนสี/ไอคอน/
  ฟอนต์เหมือนกันทันที. ใช้เมื่อ: สร้างเว็บ Dashboard หรือหน้าสรุป KPI ของหน่วยงานใหม่, อยากได้ระบบสี+ไอคอน
  เดียวกับระบบนี้ในโปรเจกต์อื่น, หรือทำหน้า admin ที่ต้องมี sidebar+topbar+การ์ดสรุปตัวเลข. ไฟล์พร้อม copy
  อยู่ใน reference/ (tokens.css, palette.md, icons.md, dashboard-kpi-template.html)
argument-hint: "[project-path]"
---

# HR-CENTER 4.0 Design System — Portable UI Kit สำหรับ Dashboard/KPI หน่วยงาน

ระบบดีไซน์นี้สกัดมาจาก MedAlert (รพ.เชียงกลาง) จริง — ใช้งานมาแล้วกับ Dashboard 11+ module,
หน้า home, และหน้า admin ต่างๆ เป้าหมายของ skill นี้คือให้ **project ใหม่ที่ไม่เกี่ยวกับ รพ. เลย**
(เช่น ระบบ KPI ฝ่ายบุคคล, การเงิน, พัสดุ, หรือหน่วยงานราชการทั่วไป) ได้หน้าตาเดียวกัน — โทนสีเดียวกัน,
ไอคอนเซ็ตเดียวกัน, ฟอนต์เดียวกัน, และ component สำเร็จรูปเดียวกัน โดยไม่ต้องออกแบบใหม่ตั้งแต่ศูนย์

> ต้นแบบใช้งานจริงในเครื่องนี้: `partials/header.php`, `partials/footer.php`, `index.php`, `dashboard.php`,
> `dashboard_modules.php` — ไฟล์ที่ **generalize แล้วพร้อม copy** อยู่ใน `reference/` ของ skill นี้

## สถาปัตยกรรมดีไซน์ 4 ชั้น

```
 Design tokens (สี/ฟอนต์/ระยะ) ──► Layout shell (sidebar+topbar) ──► Component library (KPI/card/badge) ──► Data layer (Chart.js/ตาราง)
   CSS variables, ปรับธีมได้        fixed sidebar 260px + topbar 64px    class สำเร็จรูป copy-paste ได้        ผูกสีจาก palette เดียวกับการ์ด
```

| ชั้น | ไฟล์อ้างอิง | หน้าที่ |
|---|---|---|
| 1. Tokens | `reference/tokens.css` | CSS variables (สี 4 ธีม, ฟอนต์, shadow, radius) + component class ทั้งหมด |
| 2. Palette | `reference/palette.md` | ตารางสี semantic + categorical (แยกหมวด/หน่วยงาน) + วิธีผูกสีกับ Chart.js |
| 3. Icons | `reference/icons.md` | Material Symbols Outlined — วิธีติดตั้ง + รายการไอคอนแยกหมวดที่ใช้จริง |
| 4. Template | `reference/dashboard-kpi-template.html` | หน้า Dashboard ตัวอย่างที่ประกอบทุกชั้นเข้าด้วยกัน รันได้ทันที (ไม่มี backend) |

## ติดตั้งใน project ใหม่ (checklist)

1. **โหลดฟอนต์ + ไอคอน + Bootstrap + Chart.js** ผ่าน CDN ใน `<head>` (ดู `<head>` ของ
   `reference/dashboard-kpi-template.html` เป็นตัวอย่าง copy ตรงๆ ได้เลย)
2. **copy `reference/tokens.css`** เข้า project แล้ว link ต่อจาก Bootstrap — ได้ design tokens +
   component class ทั้งหมดในไฟล์เดียว (สี, sidebar/topbar shell, KPI card, module card, badge, banner)
3. **เปลี่ยนสีแบรนด์:** แก้แค่ `--blue` / `--blue-50` / `--blue-100` ใน `:root` ของ tokens.css ให้เป็น
   เฉด 500/50/100 ของสีหน่วยงานคุณ — ปุ่ม, active nav, กราฟ default, welcome banner, avatar gradient
   จะเปลี่ยนตามทั้งหมดโดยไม่ต้องแก้ที่อื่น
4. **สร้างโครง HTML** ตาม `reference/dashboard-kpi-template.html`: `#sidebarOverlay` →
   `#ckh-sidebar` (brand + nav) → `#ckh-main` (`#ckh-topbar` + `#ckh-content > .content-inner`)
5. **ใส่ KPI row** ด้วย `.kpi-card` (ดูหมวด 6 ใน tokens.css) — เลือก `.kpi-icon.bg-XXX` ให้ตรง
   ความหมาย (slate=รวม, green=สำเร็จ, amber=รอ, red=ล้มเหลว, blue=วันนี้/เด่น)
6. **ถ้ามีหลายหมวด/หลายหน่วยงานย่อย** ใช้ `.dash-grid` + `.dash-card` (หมวด 11) — หยิบสีจาก
   ตาราง categorical palette ใน `reference/palette.md` ข้อ 4 ตามลำดับ ไม่ต้องคิดสีเอง
7. **กราฟเทรนด์** ใช้ Chart.js ผูก `borderColor` ให้ตรงกับสีการ์ดหมวดเดียวกัน (สูตร alpha suffix
   ใน `palette.md` ข้อ 5) — ผู้ใช้จะจำสีแล้วโยงเข้าใจได้ทันทีโดยไม่ต้องอ่าน legend
8. **ไอคอน** ใช้ `<span class="msi">icon_name</span>` ทุกที่ — เลือกชื่อจาก `reference/icons.md`
   ก่อน ถ้าไม่มีค่อยค้นใหม่จาก fonts.google.com/icons (กติกาเลือกอยู่ท้าย icons.md)
9. **JS ที่ต้องมีคู่กับ shell:** sidebar toggle (mobile), live clock ภาษาไทย (พ.ศ.), collapsible nav
   group — คัดลอกจาก `<script>` ท้าย `reference/dashboard-kpi-template.html` ได้ตรงๆ
10. **Responsive breakpoint:** sidebar พับที่ `991.98px` (Bootstrap `lg`) — อย่าเปลี่ยนเลขนี้ถ้าจะใช้
    grid class ของ Bootstrap (`col-sm-*`, `col-lg-*`) ร่วมกับ shell นี้เพราะ CSS breakpoint ผูกกันไว้แล้ว

## หลักการออกแบบที่ต้องคงไว้ (สิ่งที่ทำให้ระบบนี้ "ดูเป็นชุดเดียวกัน")

- **สีมีความหมายคงที่**: เขียว=สำเร็จ, อำพัน=รอ, แดง=ล้มเหลว/เร่งด่วน, น้ำเงิน(--blue)=แบรนด์/เด่น —
  ห้ามสลับความหมายสีข้ามหน้า แม้จะเปลี่ยนคำ label
- **การ์ดทุกชนิดใช้ shadow/radius เดียวกัน**: `border-radius:12–14px`, `box-shadow: var(--card-shadow)`,
  hover ยก `var(--card-shadow-hover)` — ทำให้ทุกการ์อดในหน้าเดียวกันดู "เซ็ตเดียวกัน" แม้เนื้อหาต่างกัน
  (KPI card, module card, shortcut card, status card ใช้ค่าเดียวกันหมด)
  (ดู tokens.css หมวด 6, 9, 10, 11)
- **ไอคอนอยู่ในกรอบสี่เหลี่ยมมุมโค้งเสมอ** (พื้นอ่อน + ไอคอนสี) ยกเว้นใน topbar ที่เป็นปุ่มกลม/โปร่ง
- **1 หมวด = 1 สี = 1 ไอคอนคงที่** ทั้งใน sidebar, การ์ดสรุป, และเส้นกราฟ — ดู `palette.md` ข้อ 4
- **รองรับ 4 ธีม** (light/dark/pastel/classic) ผ่าน `html[data-theme]` โดยไม่ต้องแก้ class ใน HTML
  เลย เพราะ component ทุกตัวอ้างอิง CSS variable ไม่ hardcode สี (ยกเว้นสี semantic เช่น success/danger)
- **ฟอนต์ Kanit** (รองรับไทย+ละติน, weight 300–700) — ถ้าไม่มีไทยในโปรเจกต์ก็เปลี่ยน font-family
  ได้ แต่คง `font-weight:300` เป็น base weight ของ body (เป็นเอกลักษณ์ความ "บาง สะอาด" ของระบบนี้)

## ตัวอย่างการใช้งานเร็ว — ดูผลลัพธ์ก่อนเริ่ม

เปิด `reference/dashboard-kpi-template.html` ตรงๆ ในเบราว์เซอร์ได้เลย (ต้องมี `tokens.css` อยู่
โฟลเดอร์เดียวกัน) — เป็นตัวอย่าง Dashboard หน่วยงานสมมติ (ฝ่ายบุคคล/การเงิน/พัสดุ) ที่ประกอบทุก
component เข้าด้วยกันแล้ว ใช้เป็นจุดตั้งต้น copy ไปแก้ข้อมูล/label ต่อได้ทันที
