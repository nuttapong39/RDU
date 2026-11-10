# แผน: ชุดรหัสยาที่เภสัชกรตั้งค่าเอง + ข้อมูลสถานบริการ

สถานะ: ร่างที่ผู้ใช้ยืนยันความเข้าใจร่วมแล้ว (2026-11-10) · ยังไม่ได้ implement
อ้างอิง: [ADR 0004](../adr/0004-drug-code-sets-are-configured-per-hospital.md), [ADR 0003](../adr/0003-kpi02-follows-hdc-not-the-pharmacists-or.md), `CONTEXT.md` (ชุดรหัสยา, ยังไม่ตั้งค่า, ผู้ตั้งค่ารหัสยา, ข้อมูลสถานบริการ)

## 1. เป้าหมาย

ระบบจะ share ให้ รพ. อื่น แต่ icode และ KPI ที่ใช้ต่างกันในแต่ละ รพ. จึงให้เภสัชกรกำหนดรหัสยาของแต่ละ KPI เองผ่านหน้าเว็บ
(ลากวางจากรายการยา HOSxP) แทนค่าคงที่ในโค้ด ถ้ายังไม่ตั้งค่า KPI นั้นว่างและมีปุ่มพาไปตั้งค่า และให้ผู้ดูแลระบบตั้งชื่อ/Logo/รหัสหน่วยบริการได้

## 2. ข้อตกลงที่ผูกมัดแผนนี้

1. **หน่วยตั้งค่าคือ "ชุดรหัสยา"** ผูก KPI→ชุดไว้ในโค้ด เภสัชกรแก้เฉพาะสมาชิกของชุด
2. **หนึ่งการติดตั้ง = หนึ่ง รพ.** (ไม่ multi-tenant)
3. **รพ. ใหม่เริ่มจากชุดว่าง** ชุดของเชียงกลางย้ายเข้าครั้งเดียวด้วยคำสั่ง artisan (ข้อมูลอยู่ในไฟล์ใน repo, ไม่ทับชุดที่มีข้อมูลแล้ว, **ย้ายตามเดิมทุกรหัส** รวม Budesonide/Albendazole/Acyclovir/ยาหยอดตา-หู)
4. **คำสั่งนำเข้ารันเอง หลัง `deploy.sh`** ไม่ผูกใน `deploy.sh` (ข้อมูลเฉพาะ รพ. ไม่ปนในขั้นตอนที่ใช้ร่วม)
5. **ขาดชุดใดชุดหนึ่ง = KPI ยังไม่ตั้งค่าทั้งตัว** (KPI10 ต้องมีชุด Metformin + ชุดยาลดระดับน้ำตาล + รหัส lab eGFR)
6. **ยังไม่ตั้งค่า ≠ ไม่มีข้อมูล:** ไม่มีผล ไม่มีสถานะ ไม่นับในภาพรวม/แถบเฝ้าระวัง/หน้า login, `rdu:calculate` ข้าม, รายงานแสดง "ยังไม่ตั้งค่า"
7. **การลากวางเป็นร่าง** toast "เพิ่มในรายการแล้ว (ยังไม่บันทึก)" → ปุ่มบันทึกเดียว → AuditLog 1 รายการ/การบันทึก, เตือนเมื่อออกจากหน้าโดยไม่บันทึก
8. **บันทึกชุดว่างได้ หลังยืนยัน** ("KPI xx จะกลับเป็นยังไม่ตั้งค่า")
9. **แก้พร้อมกัน:** ตรวจเวอร์ชันของชุด ถ้าไม่ตรงปฏิเสธและให้โหลดใหม่
10. **แก้ชุดแล้วผลเก่าไม่ถูกใช้ ไม่ลบอะไร:** cache key มีเวอร์ชันชุด และไม่ใช้แถว `rdu_indicator_results` ที่ `calculated_at` ก่อนการแก้ชุดผ่านหน้าจอครั้งล่าสุด การนำเข้าครั้งแรกเป็น baseline (ไม่ทำให้แถวเก่าใช้ไม่ได้)
11. **สิทธิ์:** `ผู้ตั้งค่ารหัสยา` ให้/ถอนโดยผู้ดูแลระบบผ่านหน้าเว็บ (เลือกจากผู้ใช้ที่เคย login), ผู้ดูแลระบบได้สิทธิ์นี้อัตโนมัติ, คนอื่นดูได้อย่างเดียว
12. **สถานบริการ** ผู้ดูแลระบบเท่านั้น: ชื่อเต็ม, ชื่อย่อ, จังหวัด, รหัสหน่วยบริการ 5 หลัก (ย้ายจาก `.env`), Logo
13. **ไม่ตั้งค่าสถานบริการ:** ชื่อ "RDU Dashboard" + ไอคอนกลาง, ซ่อนการเทียบ HDC, ผู้ดูแลระบบเห็นแถบเตือน
14. **ไม่แตะ:** ICD-10, เกณฑ์อายุ (15/65), eGFR ≥ 30, บรรทัดเครดิตผู้พัฒนาท้ายหน้า login
15. **HOSxP read-only** อ่านผ่าน `RduQueryService` เท่านั้น อ่านได้เฉพาะตาราง `drugitems` และ `lab_items`

## 3. ชุดรหัสยา 9 ชุด (คงที่ในโค้ด)

| key | ใช้ใน | ค่าเสริมต่อรหัส | เดิมอยู่ที่ |
|---|---|---|---|
| `antibiotic` | KPI01, 03, 04, 05 | – | `ANTIBIOTIC_ICODES` (66) ซ้ำ 4 class |
| `antibiotic_fresh_wound` | KPI02 | – | FreshAccidentWoundAntibioticQuery (28) |
| `nsaid` | KPI07, 09 | – | `NSAID_ICODES` (6) |
| `ics` | KPI08 | – | `ICS_ICODES` (4) |
| `metformin` | KPI10 ตัวตั้ง | – | `METFORMIN_ICODE` |
| `glucose_lowering` | KPI10 ตัวหาร | – | `GLUCOSE_LOWERING_ICODES` (7) |
| `non_sedating_antihistamine` | KPI11 | รูปแบบยา | `NON_SEDATING_DRUGS` (2) |
| `ras_blockade` | KPI12 | ตัวยา (นับ ≥ 2 ตัวยาต่างกัน) | `RAS_DRUGS` (6) |
| `long_acting_bzd` | KPI13 | รูปแบบยา | `LONG_ACTING_BZD_DRUGS` (3) |
| `egfr_lab` (1 รายการ, lab) | KPI10 | – | `EGFR_LAB_ITEM = 200` |

ค่าเสริม (ตัวยา/รูปแบบ) เริ่มจาก `drugitems.generic_name` / `dosageform` และเภสัชกรแก้ได้ (Q11 ค)

## 4. โครงสร้างข้อมูล (`rdu_dashboard`, connection `pgsql`)

- `drug_code_sets` — `key` PK, `version` int (เพิ่มทุกครั้งที่บันทึกผ่านหน้าจอ), `changed_at` nullable (เวลาแก้ผ่านหน้าจอครั้งล่าสุด; **ไม่ตั้งตอน import**), `updated_by`, timestamps
- `drug_code_set_items` — `set_key`, `code` (icode หรือ lab_items_code เป็น string), `attribute` nullable, unique(`set_key`,`code`)
- `drug_code_set_editors` — `user_id` unique (ผู้ตั้งค่ารหัสยา)
- ข้อมูลสถานบริการเก็บในตาราง `settings` เดิม (`facility.name`, `facility.short_name`, `facility.province_code`, `facility.hospcode`, `facility.logo_path`), ไฟล์ Logo ใน `storage/app/private/facility/<ชื่อสุ่ม>`
- ทะเบียนชุดรหัสยา (`DrugCodeSetRegistry`) ในโค้ด: key, ชื่อ, ชนิด (drug/lab), ชนิดค่าเสริม, KPI ที่ใช้ → ใช้ทั้งหน้าตั้งค่าและกฎ "ตั้งค่าครบหรือยัง"

## 5. Phase

แต่ละ Phase: PLAN → IMPLEMENT → TEST → SECURITY REVIEW → REFACTOR → TEST AGAIN แล้ว commit แยก ไม่เข้า Phase ถัดไปถ้า test ยังไม่ผ่าน
**กฎ UI:** ถ้า Phase ใดแก้หรือเพิ่ม UI (Blade, CSS, JS) ต้อง**ตรวจในเบราว์เซอร์ด้วย Claude in Chrome ทุกครั้ง**บน UAT (http://192.168.1.25/RDU/public) ก่อนถือว่าเสร็จ: เปิดหน้าจริง, ลองใช้งาน (ลากวาง, บันทึก, toast, สิทธิ์ที่ต่างกัน), ดูหน้าจอแคบ และเช็ค console error — rebuild assets ด้วย `npx vite build --emptyOutDir=false` ก่อน การรัน test อย่างเดียวไม่พอ ห้ามกรอกรหัสผ่านหรือกดส่งฟอร์ม login (ขอให้ผู้ใช้ login เอง) และถ้า Claude in Chrome ใช้ไม่ได้ให้แจ้งผู้ใช้ ไม่ข้ามเงียบๆ
(Phase 1 มีหน้า `admin/drug-code-editors` และลิงก์ sidebar ที่ยังไม่ได้ตรวจในเบราว์เซอร์ — ต้องตรวจก่อน deploy)

เกณฑ์เทียบ baseline: ชุด test ทั้งหมดมี ~63 failed เดิม (Faker intl, admin 403, KpiEngine 85.2, sidebar 'คุณภาพยา', DatabaseConfig) ห้ามเพิ่ม

### Phase 1 — ฐานข้อมูล สิทธิ์ และทะเบียนชุด
- Migration 3 ตาราง, models, `DrugCodeSetRegistry`, `DrugCodeSetService` (อ่านชุด, ตั้งค่าครบหรือยัง, version/changed_at)
- Gate `edit-drug-code-sets` (ผู้ดูแลระบบ หรืออยู่ใน editors) ที่ `AppServiceProvider`
- หน้า `admin/drug-code-editors`: เลือกผู้ใช้ให้/ถอนสิทธิ์ + AuditLog (route ใน group `can:manage-settings` เดิม)
- Test: ผู้ดูแลระบบได้สิทธิ์อัตโนมัติ, ผู้ใช้ทั่วไปไม่ได้, ให้/ถอนมี AuditLog, ทะเบียนครบ 10 รายการและ KPI ที่ใช้ตรงตารางข้อ 3
- เสร็จเมื่อ: ยังไม่กระทบ KPI ใดๆ, test ใหม่ผ่าน, ไม่เพิ่ม failure เดิม

### Phase 2 — ให้ KPI อ่านชุดรหัสยา + "ยังไม่ตั้งค่า" + นำเข้าเชียงกลาง
- ไฟล์ข้อมูลเชียงกลาง `database/data/chiangklang-drug-code-sets.php` (ค่าคงที่เดิมทุกตัว รวมค่าเสริม) + คำสั่ง `php artisan rdu:import-drug-code-sets` (ข้ามชุดที่มีข้อมูลแล้ว, ไม่ตั้ง `changed_at`)
- แทนค่าคงที่ใน query class ของ KPI01–05, 07–13 ให้อ่านจาก `DrugCodeSetService` (ตาม map ที่ `AppServiceProvider:33-44`) ลบค่าคงที่ซ้ำ **(ขอ user ก่อนลบโค้ดจริง ตามกฎ "ลบต้องถาม")**
- cache key `rdu.kpi.*` เพิ่มเวอร์ชันของชุดที่ KPI นั้นใช้; `RduCalculationService` ไม่ใช้แถว `rdu_indicator_results` ที่ `calculated_at < changed_at` ของชุดใดชุดหนึ่ง
- สถานะ "ยังไม่ตั้งค่า": `RduCalculationService` คืน `status = 'not_configured'` ไม่คำนวณ; ภาพรวม/hero/แถบเฝ้าระวัง/`StatusSnapshotService` (หน้า login แสดง "ยังไม่ตั้งค่า N ตัวชี้วัด")/`rdu:calculate`/รายงาน Excel-PDF จัดการสถานะนี้; หน้า KPI แสดง card เทาพร้อมปุ่ม → หน้าตั้งค่า (ชี้ไป route ของ Phase 3, ใช้ placeholder ชั่วคราวได้)
- `counting-method.blade.php` ตาราง "6. รหัสยาที่นับ" อ่านจากชุด
- Test: (ก) **regression เทียบผล** — seed ชุดจากไฟล์นำเข้าแล้ว mock data source ต้องได้ตัวเลขเท่าเดิมทุก KPI; (ข) ชุดว่าง → not_configured และไม่นับในภาพรวม; (ค) KPI10 ขาดชุดใดชุดหนึ่ง → not_configured; (ง) แก้ผ่านหน้าจอ → cache เก่าและแถวเก่าไม่ถูกใช้; import ไม่ทำให้แถวเก่าใช้ไม่ได้; (จ) คำสั่งนำเข้าไม่ทับชุดที่มีข้อมูล
- เสร็จเมื่อ: UAT รันคำสั่งนำเข้าแล้วตัวเลขทุก KPI/ทุกปีงบตรงกับก่อนเปลี่ยน (เทียบ FY2569/2570 ที่บันทึกไว้)

### Phase 3 — หน้าตั้งค่ารหัสยา
- เมนู sidebar "ตั้งค่ารหัสยา" + ป้ายจำนวน KPI ที่ยังไม่ตั้งค่า (ทุกผู้ใช้เห็น; ไม่มีสิทธิ์ = โหมดดูอย่างเดียว)
- หน้า: แถบเลือก KPI 12 ตัว (จุดสถานะ), แต่ละ KPI แสดงชุดที่ใช้ (ป้าย "ใช้ร่วมกับ KPIxx — แก้ที่นี่เปลี่ยนทุกตัว"), KPI10 มี 2 กล่อง + ช่องเลือก lab eGFR (ค้นหา ไม่ลากวาง)
- Endpoint รายการยา: `RduQueryService::drugCatalog()` อ่าน `drugitems` เท่านั้น (icode, name, strength, units, dosageform, generic_name, trade_name, drugcategory, therapeuticgroup, antibiotic, istatus) โหลดครั้งเดียว ~764 แถว; ค้นหา/กรองฝั่งเบราว์เซอร์ (icode/ชื่อ/ชื่อสามัญ/ชื่อการค้า, กรองหมวด, `antibiotic='Y'`, สวิตช์แสดงยาที่ยกเลิก — `istatus='N'`); ยาที่อยู่ในชุดแล้วซ่อนจากฝั่งซ้าย; `lab_items` เป็นอีก endpoint
- SortableJS (npm + Vite) ลากวาง + ปุ่ม "+"/"×" + เลือกหลายรายการ "เพิ่มที่เลือก"; toast SweetAlert2 (ติดตั้งผ่าน npm แทน CDN ถ้าทำได้โดยไม่กระทบหน้า KPI); ฝั่งขวาแก้ค่าเสริม (ตัวยา/รูปแบบ) ได้
- บันทึก: `POST` พร้อม `version`; ตรวจ icode มีอยู่ใน `drugitems`, ค่าเสริมครบ, เตือน (ไม่ห้าม) ยาซ้ำข้ามชุด; version ไม่ตรง → 409 "มีผู้อื่นแก้ไขชุดนี้แล้ว"; ชุดว่าง → ต้องยืนยัน; เพิ่ม version + `changed_at` + AuditLog
- Test: สิทธิ์ (403/ดูอย่างเดียว), ตรวจความถูกต้อง, 409, ยืนยันชุดว่าง, ค่าเสริมครบ, endpoint ไม่คืนคอลัมน์อื่นนอกรายการ, ไม่มีการเขียนบน connection `rdu`; ทดสอบใน UAT ด้วยเบราว์เซอร์ (ลากวางจริง, จอสัมผัสถ้าได้)
- Rebuild assets UAT: `npx vite build --emptyOutDir=false`

### Phase 4 — เปิด/ปิดตัวชี้วัด
- สวิตช์ `rdu_indicators.is_active` ในหน้าตั้งค่า (เฉพาะ KPI ที่มี query แล้ว ไม่รวม KPI06), gate เดียวกับ Phase 3, AuditLog, ล้าง cache ที่เกี่ยวข้อง
- Test: ปิดแล้วหายจาก sidebar/ภาพรวม/snapshot, เปิดกลับได้, สิทธิ์

### Phase 5 — ข้อมูลสถานบริการ
- `FacilityProfileService` อ่าน/เขียน `settings`; หน้า `admin/facility` (ชื่อเต็ม ชื่อย่อ จังหวัด 77 รายการได้ชื่อ+รหัส, รหัสหน่วยบริการ `^\d{5}$`, อัปโหลด Logo) + AuditLog
- Logo: PNG/JPG/WebP ≤ 1 MB ตรวจเนื้อไฟล์ (ไม่รับ SVG), เก็บชื่อสุ่มใน `storage/app/private/facility/`, ส่งผ่าน route ของแอป (ไม่ใช้ `storage:link`), ไฟล์เก่าไม่ลบ, ตัวอย่างในกรอบวงกลม
- แทนข้อความ/Logo ตายตัว: `auth/login.blade.php` (title, 2 Logo, h1, ข้อความ, ©; เก็บบรรทัดเครดิตผู้พัฒนา), `layouts/dashboard.blade.php:39,46`, `layouts/admin.blade.php:39`, `dashboard/index.blade.php:49`, `dashboard/kpi.blade.php:1370,1372`, `admin/database/show.blade.php:260`
- HDC: `config('rdu.hdc.hospcode')`/province → อ่านจากสถานบริการ (เลิกใช้ `.env`); แทน `NAN_DISTRICTS` ด้วยไฟล์รหัสอำเภอทั้งประเทศ (หาแหล่งทางการตอน implement, ไม่เจอ → แสดงรหัสหน่วยบริการ); ยังไม่ตั้งรหัส → ซ่อนการเทียบ HDC + ข้อความ
- ยังไม่ตั้งค่า: ชื่อ "RDU Dashboard" + ไอคอนกลาง + แถบเตือนผู้ดูแลระบบ
- ขยายคำสั่งนำเข้าให้ตั้งสถานบริการเชียงกลาง (ชื่อ, `logo_ck.png` เดิม, 11179, น่าน) เฉพาะเมื่อยังไม่ตั้ง
- Test: validation, ชนิดไฟล์/ขนาด/SVG ถูกปฏิเสธ, สิทธิ์ผู้ดูแลระบบเท่านั้น, ค่าเริ่มต้นเมื่อไม่ตั้ง, ซ่อน HDC, ป้ายอำเภอ, หลังนำเข้าหน้าตาเท่าเดิม

### Phase 6 — เอกสารและ deploy
- `docs/KPI-REFERENCE-IMPLEMENTATION.md`: KPI ใหม่ต้องประกาศชุดรหัสยาใน registry (ห้ามค่าคงที่ icode ใน query), `DEPLOYMENT.md`: ลำดับ `deploy.sh` → `php artisan rdu:import-drug-code-sets` (เชียงกลาง/UAT) และคู่มือติดตั้ง รพ. ใหม่ (ชุดว่าง → ตั้งสถานบริการ → ให้สิทธิ์เภสัชกร → ตั้งชุด), อัปเดต `docs/KPI-SPECIFICATION.md`/`CLAUDE.md` ส่วนที่อ้างค่าคงที่/`.env`
- ผู้ใช้ deploy เอง: `ssh -t nuttapong@192.168.1.244 /var/www/rdu/scripts/deploy.sh` แล้วรันคำสั่งนำเข้า, ผมช่วยตรวจหลัง deploy ได้

## 6. ความเสี่ยงและข้อควรระวัง

- **ช่วง deploy→import:** หน้า KPI ว่างจนกว่ารันคำสั่ง (ไม่กี่วินาที) บอกผู้ใช้ให้รันต่อทันที
- **Phase 2 ใหญ่สุด** (แตะทุก query class) — ตัวกันพลาดคือ regression test เทียบตัวเลขเดิม ห้าม merge ถ้าตัวเลขต่างแม้ 1 วิสิต
- **ตัวเลขหน้า login** เก่าได้จนกว่าจะเปิดภาพรวมครั้งแรกหลัง deploy/แก้ชุด (พฤติกรรมเดิมของ snapshot)
- **Test ต้อง seed ชุดเอง** — test เดิมที่พึ่งค่าคงที่ต้องแก้ ใช้ helper กลาง
- **ความปลอดภัย:** รายการยาส่งเฉพาะคอลัมน์ที่ระบุ ไม่อ่าน `officer`/`patient`; Logo ตรวจเนื้อไฟล์ ไม่รับ SVG; ไม่เขียนบน `rdu`; การลบไฟล์/ข้อมูลใดๆ ต้องถามผู้ใช้ก่อน
- **งานแยก (ไม่อยู่ในแผน):** `rdu_readonly` เห็นคอลัมน์รหัสผ่านใน `officer` และ view `rdu_kpi.officer` → ให้ DBA จำกัดคอลัมน์; ทบทวนรายการ 66 รหัสยาปฏิชีวนะโดยเภสัชกร; KPI13 doctor attribution; stub KPI06

## 7. ลำดับที่แนะนำ

Phase 1 → 2 → 3 (เภสัชกรใช้ได้จริง) → 5 → 4 → 6 · Phase 2 และ 3 ควร deploy พร้อมกันเพราะ Phase 2 อย่างเดียวทำให้หน้า KPI พึ่งคำสั่งนำเข้าโดยไม่มีหน้าแก้ไข
