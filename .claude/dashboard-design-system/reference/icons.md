# Icon System — Material Symbols Outlined

ต้นแบบจริง: MedAlert (รพ.เชียงกลาง) ใช้ **Material Symbols Outlined** (Google Fonts, variable font)
ทั้งระบบแทน SVG icon set — เลือกเพราะ: ฟรี, มีไอคอนให้เลือกหลักพัน, โหลดทีเดียวผ่าน `<link>`, ปรับ
weight/fill/grade/optical-size ได้ด้วย CSS ตัวเดียว ไม่ต้องมีไฟล์ icon แยกต่อไอคอน

## ติดตั้ง

```html
<link rel="stylesheet"
  href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
```

CSS class `.msi` (นิยามเต็มอยู่ใน `tokens.css` หมวด 2) — ใช้งาน:

```html
<span class="msi">home</span>            <!-- filled, ค่า default ของระบบนี้ -->
<span class="msi msi-o">home</span>      <!-- outline (ไม่ fill) -->
<span class="msi msi-2x">home</span>     <!-- ขนาด 2x -->
<span class="msi msi-spin">progress_activity</span>  <!-- หมุน loading -->
```

ชื่อไอคอน (icon name) หาได้จาก https://fonts.google.com/icons — พิมพ์ชื่อ snake_case ตรงๆ ลงใน
`<span class="msi">`

## หลักการเลือกไอคอนของระบบนี้ (คงไว้เวลาย้ายไปโปรเจกต์ใหม่)

- **1 ความหมาย = 1 ไอคอนเดียวทั้งระบบ** — เช่น `stethoscope` แปลว่า "การวินิจฉัย/PDX" เสมอ ไม่ว่าจะโผล่ที่ sidebar, KPI, หรือ dashboard card ใบไหน
- ไอคอนสถานะ 3 ตัวคงที่: `hourglass_empty`/`schedule` (รอ) · `check_circle` (สำเร็จ) · `warning` (ล้มเหลว)
- ไอคอนกรอบ (nav-ic, kpi-icon, dash-thumb, sc-icon) ทุกอันเป็นสี่เหลี่ยมมุมโค้ง พื้นอ่อน + ไอคอนสี ไม่ใช้ไอคอนลอยไม่มีกรอบ ยกเว้นใน topbar/breadcrumb

## รายการไอคอนที่ใช้จริง แยกตามหมวด (ใช้เป็นจุดเริ่มต้น ไม่ต้องคิดใหม่ทุกครั้ง)

### Navigation / โครงหลัก
`home` · `show_chart` (dashboard) · `menu` (toggle sidebar) · `chevron_right` (breadcrumb sep, card arrow) · `expand_more` (collapse/dropdown) · `logout` · `tune` (ตั้งค่า) · `storage` (ฐานข้อมูล) · `key` (API key) · `palette` (ธีม/สี) · `info` (เกี่ยวกับระบบ) · `grid_view` (ภาพรวม/เมนูลัด) · `notifications`

### สถานะ (status)
`check_circle` (สำเร็จ) · `hourglass_empty` / `schedule` (รอดำเนินการ) · `warning` (ล้มเหลว) · `mail` (ยังไม่มีประวัติ)

### KPI ทั่วไป
`checklist` (ทั้งหมด) · `today` (วันนี้) · `history` (ล่าสุด/ประวัติ)

### หมวดข้อมูล/โรค (ตัวอย่างจริง — ปรับชื่อ/ความหมายตามหน่วยงานคุณ แต่คงหลัก "1 ไอคอน 1 หมวด")
`falling` (พลัดตก/หกล้ม) · `personal_injury` / `stethoscope` (ผู้ป่วย/วินิจฉัย) · `medication_liquid` / `medication` (ยา) · `car_crash` (อุบัติเหตุ) · `bloodtype` (Lab เลือด) · `coronavirus` (โรคติดเชื้อ) · `bug_report` / `pest_control` (แมลง/พาหะ) · `water_drop` (ของเหลว/lab) · `health_and_safety` (สุขภาพ/ความปลอดภัย) · `shield_person` (คุ้มครอง/ล่วงละเมิด) · `science` (ผลตรวจ Lab) · `local_hospital` (สถานบริการ) · `badge` (สิทธิ/แพทย์ผู้รับผิดชอบ) · `category` (ประเภท)

### Action
`download` (export) · `list` (ดูรายการทั้งหมด) · `table_rows` (ตาราง)

## แผนผังการเลือกไอคอนใหม่ (ถ้าไม่มีในลิสต์ด้านบน)

1. ค้นใน https://fonts.google.com/icons ด้วยคำอังกฤษที่ตรงความหมายที่สุด (ไม่ใช่คำแปลตรงตัวจากไทย)
2. เลือกตัวที่เป็น "outlined" family เดียวกัน (สม่ำเสมอกับที่มีอยู่)
3. ห้ามใช้ไอคอนคนละชื่อสำหรับความหมายเดียวกันในหน้าอื่น — เช็คว่ามี alias ในลิสต์นี้อยู่แล้วหรือยัง
