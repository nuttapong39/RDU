# โทนสี — HR-CENTER 4.0 Palette

ต้นแบบจริง: MedAlert (รพ.เชียงกลาง) — 11 module ใช้สีแยกกันทั้งใน sidebar icon, dashboard card,
และเส้นกราฟ Chart.js เพื่อให้ผู้ใช้ "จำสีแล้วรู้ทันทีว่าเป็นหมวดไหน" โดยไม่ต้องอ่าน label

## 1) Semantic tokens (สถานะ/แบรนด์ — ใช้ทั้งระบบ)

| Token | Hex | ใช้กับ |
|---|---|---|
| `--blue` (primary/brand) | `#1d4ed8` | ปุ่มหลัก, active nav, ลิงก์, กราฟ default |
| `--green` (success) | `#059669` | สถานะสำเร็จ, KPI บวก |
| `--red` (danger) | `#dc2626` | สถานะล้มเหลว/ผิดพลาด, logout |
| `--amber` (warning) | `#d97706` | สถานะรอดำเนินการ/ค้าง |
| `--indigo` | `#4f46e5` | KPI รอง/หมวดเสริม |
| `--teal` | `#0891b2` | KPI รอง/หมวดเสริม |
| `--purple` | `#7c3aed` | KPI รอง/หมวดเสริม |

**เปลี่ยนแบรนด์:** แก้แค่ `--blue` (+ `--blue-50`/`--blue-100` ให้เป็นเฉด 50/100 ของสีเดียวกัน) ทุก component
ในระบบ (active nav, ปุ่ม, กราฟ default, welcome banner, avatar gradient) จะเปลี่ยนตามอัตโนมัติ

## 2) KPI icon gradient (7 สี, ใช้กับ `.kpi-icon.bg-XXX`)

ใช้กับการ์ด KPI แถวบนสุดของ Dashboard (ทั้งหมด / สำเร็จ / ค้าง / ล้มเหลว ฯลฯ)

| Class | Gradient | ความหมายที่ใช้จริง |
|---|---|---|
| `.bg-slate`  | `linear-gradient(135deg,#64748b,#334155)` | จำนวนรวมทั้งหมด (เป็นกลาง) |
| `.bg-green`  | `linear-gradient(135deg,#10b981,#059669)` | สำเร็จ / ดำเนินการเสร็จ |
| `.bg-amber`  | `linear-gradient(135deg,#f59e0b,#d97706)` | รอดำเนินการ / ค้าง |
| `.bg-red`    | `linear-gradient(135deg,#ef4444,#dc2626)` | ล้มเหลว / ผิดพลาด / เร่งด่วน |
| `.bg-blue`   | `linear-gradient(135deg,#3b82f6,#1d4ed8)` | วันนี้ / รายการล่าสุด / brand |
| `.bg-indigo` | `linear-gradient(135deg,#818cf8,#4f46e5)` | หมวดเสริม |
| `.bg-teal`   | `linear-gradient(135deg,#22d3ee,#0891b2)` | หมวดเสริม |

## 3) Status badge / dot (3 สถานะมาตรฐาน)

| สถานะ | Badge (`.status-badge.status-XXX`) | Dot (`.dash-dot.dot-XXX` / `.status-item-dot.dot-XXX`) |
|---|---|---|
| รอดำเนินการ | `.status-pending` bg `#fef3c7` text `#92400e` | `.dot-pending` / `.dot-pend` bg `#f59e0b` |
| สำเร็จ | `.status-ok` bg `#dcfce7` text `#166534` | `.dot-sent` / `.dot-ok` bg `#10b981`/`#22c55e` |
| ล้มเหลว | `.status-fail` bg `#fee2e2` text `#991b1b` | `.dot-fail` bg `#ef4444` |

## 4) Categorical palette — สีแยกหมวด/หน่วยงาน (11 เฉด, distinct hue)

ใช้เมื่อ Dashboard มีหลายหมวด/หลายแผนกที่ต้องแยกสีให้จำง่ายและไม่ซ้ำกัน (module card thumb,
เส้นกราฟ multi-series ใน Chart.js, ไอคอนใน sidebar) — เรียงมาจาก 11 module จริงของระบบ,
**หยิบไปใช้ตามลำดับได้เลยเมื่อมีหมวดใหม่ในหน่วยงานคุณ** เพราะทุกเฉด hue ต่างกันพอที่ตาแยกออกในกราฟเดียวกัน:

| ลำดับ | Hex (solid, ใช้เป็น `borderColor` กราฟ) | Gradient (ใช้กับ `.dash-thumb`) | ตัวอย่างการใช้งานจริง |
|---|---|---|---|
| 1 | `#059669` | `135deg,#10b981,#059669` | พลัดตก/หกล้ม (เขียว) |
| 2 | `#0369a1` | `135deg,#0ea5e9,#0369a1` | กลุ่มเสี่ยงจิตเวช (ฟ้าเข้ม) |
| 3 | `#7c3aed` | `135deg,#8b5cf6,#7c3aed` | ยาอันตราย (ม่วง) |
| 4 | `#d97706` | `135deg,#f59e0b,#d97706` | อุบัติเหตุ/พ.ร.บ. (ส้มอำพัน) |
| 5 | `#9f1239` | `135deg,#f43f5e,#9f1239` | Hematocrit Alert (แดงกุหลาบ) |
| 6 | `#0e7490` | `135deg,#22d3ee,#0e7490` | High Alert Drug (ฟ้าเขียวเข้ม) |
| 7 | `#0891b2` | `135deg,#22d3ee,#0891b2` | เภสัชกรรม/Lab (ฟ้าเขียว) |
| 8 | `#ea580c` | `135deg,#f97316,#ea580c` | Covid-19 (ส้ม) |
| 9 | `#dc2626` | `135deg,#ef4444,#dc2626` | ไข้เลือดออก (แดง) |
| 10 | `#0f766e` | `135deg,#14b8a6,#0f766e` | เลปโตสไปโรซิส (เขียวเทียล) |
| 11 | `#854d0e` | `135deg,#d97706,#854d0e` | สครับไทฟัส (น้ำตาลทอง) |
| 12 | `#be185d` | `135deg,#ec4899,#be185d` | โรคติดต่อทางเพศสัมพันธ์ (ชมพูเข้ม) |

กติกาการเลือกเฉดเพิ่มเอง (ถ้าเกิน 12 หมวด): ใช้ HSL หมุน hue ห่างกัน ~30° จากเฉดข้างบน, คง
saturation 60–85% และ lightness 35–50% (โทนเข้มพออ่านตัวหนังสือขาวทับได้ และไม่หวานเกินไปสำหรับงานราชการ/รพ.)

## 5) Chart.js — วิธีผูกสีให้ตรงกับการ์ด

```js
new Chart(ctx, {
  type: 'line',
  data: {
    labels: months, // เช่น 12 เดือนล่าสุด
    datasets: modules.map(m => ({
      label: m.label,
      data: m.trend,
      borderColor: m.color,             // hex solid จากตาราง categorical ด้านบน
      backgroundColor: m.color + '18',  // '18' = ~9% alpha (hex 2 หลักท้าย) สำหรับพื้นที่ใต้เส้น
      tension: .3, fill: false, pointRadius: 2, borderWidth: 2,
    }))
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.15)' } },
      x: { grid: { display: false } },
    },
  },
});
```

**เทคนิค alpha ด้วย hex suffix:** เติมท้าย hex color 2 หลักเพื่อทำให้โปร่งใสโดยไม่ต้องแปลงเป็น `rgba()` —
`'18'` ≈ 9%, `'22'` ≈ 13%, `'33'` ≈ 20%. ใช้กับพื้นที่ใต้เส้นกราฟ (`fill:true`) หรือพื้นหลัง badge อ่อนๆ

**ข้อควรระวัง:** ต้องใช้ hex เท่านั้น (ไม่ใช่ชื่อสี CSS หรือ `rgb()`) ถึงจะต่อ suffix 2 หลักแบบนี้ได้ตรง
