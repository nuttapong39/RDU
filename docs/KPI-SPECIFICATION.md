# KPI SPECIFICATION
## RDU Dashboard — Key Performance Indicators

---

## ภาพรวม

ระบบ RDU Dashboard ติดตาม **12 ตัวชี้วัด (KPI)** การใช้ยาอย่างสมเหตุผล (Rational Drug Use)
— รหัส KPI01–KPI13 โดยไม่มี KPI06 ซึ่งตัดออกเมื่อ 2026-10-17 (ดู KPI-06 ด้านล่าง)

ข้อมูลทั้งหมดอ่านมาจาก HOSxP Database ผ่าน **Read-Only Connection** เท่านั้น

> **หมายเหตุ:** KPI แต่ละตัวมี Query แยกเฉพาะ — จะ Plan Query รายละเอียดหลังจาก Roadmap นี้เสร็จสิ้น
> ชื่อ KPI และ Target ที่ระบุเป็น **Placeholder** รอยืนยันจากทีมโรงพยาบาล

---

## KPI Data Model

ตัวชี้วัดแต่ละตัวประกอบด้วย:

```
id            — รหัส KPI (KPI-01 ถึง KPI-13)
code          — รหัสย่อ เช่น uri_antibiotic_free
name          — ชื่อตัวชี้วัด (ภาษาไทย)
name_en       — ชื่อตัวชี้วัด (ภาษาอังกฤษ)
numerator     — ตัวเศษ (จำนวนที่สำเร็จตามเกณฑ์)
denominator   — ตัวส่วน (จำนวนทั้งหมดที่นับ)
percentage    — ร้อยละ (Numerator / Denominator × 100)
target        — เป้าหมาย (ร้อยละที่กำหนด)
direction     — สูงดี (higher_better) / ต่ำดี (lower_better)
status        — ผ่าน (pass) / ไม่ผ่าน (fail) / ใกล้เป้าหมาย (near)
trend         — แนวโน้ม (up / down / stable)
category      — หมวดหมู่ KPI
```

---

## Application Tables สำหรับ KPI

```
rdu_indicators          — KPI Definition (ชื่อ, สูตร, Target, Category)
rdu_indicator_results   — ผลการคำนวณ KPI รายเดือน/รายปี
```

### Schema: rdu_indicators (ตัวอย่าง)
```
id, code, name, name_en, target, direction, category,
description, numerator_desc, denominator_desc,
is_active, created_at, updated_at
```

### Schema: rdu_indicator_results (ตัวอย่าง)
```
id, indicator_id, period_year, period_month,
numerator, denominator, percentage, status,
trend, calculated_at, created_at
```

---

## รายการ KPI (KPI01–KPI13 ยกเว้น KPI06)

### หมวด A: ยาปฏิชีวนะใน OPD (Antibiotic in Outpatient)

---

#### KPI-01: การใช้ยาปฏิชีวนะในบาดแผลจากอุบัติเหตุ (ทุกชนิด)
```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในบาดแผลจากอุบัติเหตุ
              ครอบคลุมแผลทุกชนิด
Code:         uri_no_antibiotic
Numerator:    จำนวนผู้ป่วย URI ที่ไม่ได้รับยาปฏิชีวนะ
Denominator:  จำนวนผู้ป่วย URI ทั้งหมด
Target:       ≥ 80%
Direction:    สูงดี (higher_better)
Category:     antibiotic_opd
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-02: การใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ (ไม่รวมบางประเภท) ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ
              และแผลไฟไหม้น้ำร้อนลวก
Numerator:    visit ในตัวส่วนที่ได้รับยาปฏิชีวนะชนิดกินหรือฉีดตามรายการ (qty > 0)
Denominator:  visit ผู้ป่วยนอก (ovst.an IS NULL) ที่มี "ทั้ง" รหัสลักษณะการบาดเจ็บตามชุดรหัสของ KPI02 "และ"
              รหัสสาเหตุภายนอก (V / W / X00-X59) ในวิสิตเดียวกัน ตำแหน่งใดก็ได้ — เหมือนหลักของ KPI01 (ADR 0001)
              ตัดออกทั้งวิสิตถ้ามีรหัสขึ้นต้นด้วย W54-W56 (สัตว์กัด), T20-T32 (ไฟไหม้น้ำร้อนลวก) หรือ
              S16/S46/S56/S66/S76/S86/S96/S390/T095/T115/T135/T146 (กล้ามเนื้อ/เอ็น)
Target:       ≤ 40%
Direction:    ต่ำดี (lower)
Category:     Antibiotic · กลุ่มยาของตัวชี้วัด: ยาปฏิชีวนะ
ยาปฏิชีวนะ:   เภสัชกรกำหนด 28 รหัส ชนิดกิน/ฉีดล้วน — ต่างจากลิสต์ของ KPI01 (มี Clindamycin, Levofloxacin,
              Nitrofurantoin · ไม่มียาตา/หู และยาที่ไม่ใช่ปฏิชีวนะ)
ชุดรหัสแผล:  ต่างจาก KPI01 (รวม S05 แผลที่ตา, S07/S08, T13/T14 ทั้งหมด) จึงไม่ใช่ส่วนย่อยของ KPI01 พอดี
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/Antibiotic/FreshAccidentWoundAntibioticQuery.php · docs/SQL_KPI/KPI2.sql
              (ต้นฉบับของเภสัชกรอยู่ใน git commit 0f565ab — ใช้ "แผล OR สาเหตุ" ได้ 899 / 2,401 ห่าง HDC เกือบเท่าตัว
              — เหตุผลที่เปลี่ยนเป็น AND และใช้รายการยาของตัวเอง: docs/adr/0003)
HDC:          s_rdu8 (target1 / result1) ใช้ในปุ่ม "เทียบ HDC"
ผลปีงบ 2569:  750 / 1,437 visit (52.2%) — HDC 655 / 1,309 (50.0%) · ไตรมาส 185/301, 168/289, 230/410, 167/437
ผลปีงบ 2568:  801 / 1,516 visit (52.8%) — HDC 704 / 1,348 (52.2%)
```

---

#### KPI-03: การใช้ยาปฏิชีวนะในหญิงคลอดปกติครบกำหนด
```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในหญิงคลอดปกติครบกำหนด
Code:         wound_no_antibiotic
Numerator:    จำนวนผู้ป่วยแผลสดสะอาดที่ไม่ได้รับยาปฏิชีวนะ
Denominator:  จำนวนผู้ป่วยแผลสดสะอาดทั้งหมด
Target:       ≥ 80%
Direction:    สูงดี (higher_better)
Category:     antibiotic_opd
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

### หมวด B: ยาปฏิชีวนะใน IPD (Antibiotic in Inpatient)

---

#### KPI-04: การใช้ยาปฏิชีวนะใน URI และหลอดลมอักเสบเฉียบพลัน (OPD)
```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในโรคติดเชื้อที่ระบบการหายใจ
              ช่วงบนและหลอดลมอักเสบเฉียบพลันในผู้ป่วยนอก
Code:         ipd_antibiotic_use
Numerator:    จำนวนผู้ป่วยในที่ได้รับยาปฏิชีวนะ
Denominator:  จำนวนผู้ป่วยในทั้งหมด
Target:       ≤ 30%
Direction:    ต่ำดี (lower_better)
Category:     antibiotic_ipd
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-05: การใช้ยาปฏิชีวนะในโรคอุจจาระร่วงเฉียบพลัน
```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในโรคอุจจาระร่วงเฉียบพลัน
Code:         cap_appropriate_antibiotic
Numerator:    จำนวนผู้ป่วย CAP ที่ได้รับยาถูกต้อง
Denominator:  จำนวนผู้ป่วย CAP ทั้งหมด
Target:       ≥ 80%
Direction:    สูงดี (higher_better)
Category:     antibiotic_ipd
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

### หมวด C: คุณภาพการสั่งใช้ยา (Drug Use Quality)

---

#### KPI-06: การใช้ยาปฏิชีวนะใน Pyelonephritis / UTI เฉียบพลัน (OPD) ⛔ ตัดออกแล้ว (2026-10-17)

ไม่ติดตามแล้ว — ไม่มีแหล่งข้อมูลที่ใช้ได้ (ยังไม่เคยมี query) แถว KPI06 ใน `rdu_indicators` คงไว้แต่ `is_active = false`
(seeder ตั้งไว้) ทุกหน้าจึงไม่แสดง และ URL ของ KPI06 ตอบ 404 · รหัสของ KPI อื่นไม่เลื่อน · ไฟล์ stub ของ query ถูกถอดการลงทะเบียน
ข้อมูลเดิมด้านล่างเก็บไว้เป็นประวัติ

```
ชื่อ:         ร้อยละการใช้ยาปฏิชีวนะในผู้ป่วยกลุ่ม Pylonephritis,
              ปัสสาวะอักเสบเฉียบพลัน (ผู้ป่วยนอก)
Code:         national_drug_list_value
Numerator:    มูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ
Denominator:  มูลค่าการใช้ยาทั้งหมด
Target:       ≥ 80%
Direction:    สูงดี (higher_better)
Category:     drug_quality
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-07: การใช้ยา NSAIDs ซ้ำซ้อนในผู้ป่วยนอก ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน
Numerator:    visit ในตัวส่วนที่ได้รับยากลุ่ม NSAIDs ตั้งแต่ 2 รายการ (รหัสยา) ที่ต่างกัน (qty > 0)
Denominator:  visit ผู้ป่วยนอก (ovst.an IS NULL) ที่ได้รับ NSAIDs อย่างน้อย 1 รายการ (qty > 0) ไม่จำกัดการวินิจฉัย
Target:       ≤ 5%
Direction:    ต่ำดี (lower)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: ยากลุ่ม NSAIDs (config rdu.drug_groups)
ยา NSAIDs:    เภสัชกรกำหนด 6 รหัส — Ibuprofen 200/400 mg, Ibuprofen SUSP, Diclofenac INJ, Diclofenac TAB,
              Mefenamic acid · ไม่นับ Norgesic (Orphenadrine+Paracetamol) และ Aspirin
              นับตาม "รายการ" (รหัสยา) — Diclofenac INJ + Diclofenac TAB = ซ้ำซ้อน (ต่างจาก KPI12 ที่นับตามตัวยา)
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/PatientSafety/NsaidDuplicationQuery.php · docs/SQL_KPI/KPI7.sql
HDC:          ไม่มีตารางที่นิยามตรงกัน
ผลปีงบ 2569:  67 / 3,794 visit (1.8%) — 38 ใน 67 เป็น Diclofenac INJ + Diclofenac TAB, 27 เป็น Diclofenac INJ + Ibuprofen
```

---

#### KPI-08: การได้รับ Inhaled Corticosteroid ในผู้ป่วยนอกโรคหืด ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid
หน่วยนับ:     ผู้ป่วย (HN ไม่ซ้ำ) — ไม่ใช่ visit · ยอดไตรมาส/สะสมนับคนไม่ซ้ำตลอดช่วง ไม่รวมยอดรายเดือน
Numerator:    ผู้ป่วยในตัวส่วนที่ได้ ICS (qty > 0) ใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
Denominator:  ผู้ป่วยที่มี visit ผู้ป่วยนอก (ovst.an IS NULL) วินิจฉัย J45% / J46% ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
Target:       ≥ 80%
Direction:    สูงดี (higher) — ตัวเศษคือคนที่ "ได้" ยา (ต่างจาก KPI03 ที่ตัวเศษคือคนที่ไม่ได้ยา)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: ยา ICS · หน่วยนับ: patient (config rdu.kpi_count_unit)
ยา ICS:       เภสัชกรกำหนด 4 รหัส — Budesonide MDI, Seretide Evohaler, Seretide Accuhaler + รหัส Seretide เก่า
ข้อมูลรายกรณี: 1 แถวต่อผู้ป่วย ใช้ visit โรคหืดครั้งล่าสุดในช่วงเวลานั้นเป็นตัวแทน (แพทย์ สิทธิ์ แผนก ห้อง)
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/PatientSafety/AsthmaInhaledCorticosteroidQuery.php · docs/SQL_KPI/KPI8.sql
HDC:          s_rdu15 (target / result — นับเป็นคนเหมือนกัน) ใช้ในปุ่ม "เทียบ HDC"
ผลปีงบ 2569:  71 / 87 คน (81.6%) — HDC 72 / 87 (82.8%) · ถ้านับเป็น visit จะได้ 178 / 255 (69.8%)
```

---

### หมวด D: ความปลอดภัยของผู้ป่วย (Patient Safety)

---

#### KPI-09: การใช้ NSAIDs ในผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไป ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs
Code:         ckd_nsaids
หน่วยนับ:     ผู้ป่วย (HN ไม่ซ้ำ) — ไม่ใช่ visit · ยอดไตรมาส/สะสมนับคนไม่ซ้ำตลอดช่วง ไม่รวมยอดรายเดือน
Numerator:    ผู้ป่วยในตัวส่วนที่ได้ NSAIDs (qty > 0) ใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
              (รวม visit ที่ไม่ได้ลงรหัสโรคไต เช่น ห้องฉุกเฉิน — query ต้นฉบับดูเฉพาะ visit ที่วินิจฉัยโรคไต)
Denominator:  ผู้ป่วยที่มี visit ผู้ป่วยนอก (ovst.an IS NULL) วินิจฉัย N183 / N184 / N185 ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
              ไม่นับ N182 / N189 และไม่มีเงื่อนไขว่าต้องได้ยาอย่างน้อย 1 ชนิด
Target:       ≤ 10%
Direction:    ต่ำดี (lower)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: nsaid_ckd · หน่วยนับ: patient (config rdu.kpi_count_unit)
ยา NSAIDs:    6 รหัส ชุดเดียวกับ KPI-07 (NsaidDuplicationQuery::NSAID_ICODES — แก้ที่เดียวเปลี่ยนทั้งสองตัวชี้วัด)
ข้อมูลรายกรณี: 1 แถวต่อผู้ป่วย visit ตัวแทน = visit ล่าสุดที่ได้ NSAIDs (ถ้าอยู่ในตัวเศษ) มิฉะนั้น visit โรคไตครั้งล่าสุด
              dx = รหัสระดับโรคไตของผู้ป่วยในช่วงเวลานั้น
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/PatientSafety/CkdNsaidQuery.php · docs/SQL_KPI/KPI9.sql
HDC:          s_rdu14 (target / result) ใช้ในปุ่ม "เทียบ HDC"
ผลปีงบ 2569:  23 / 892 คน (2.6%) — ถ้านับเฉพาะ visit ที่วินิจฉัยโรคไตแบบต้นฉบับ 7 คน · HDC 7 / 714 (1.0%)
```

---

#### KPI-10: การใช้ Metformin ควบคุมเบาหวานโดยไม่มีข้อห้ามใช้ ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียว
              หรือร่วมกับยาอื่นเพื่อควบคุมระดับน้ำตาลโดยไม่มีข้อห้ามใช้
Code:         dm_metformin
หน่วยนับ:     ผู้ป่วย (HN ไม่ซ้ำ) — ไม่ใช่ visit · ยอดไตรมาส/สะสมนับคนไม่ซ้ำตลอดช่วง ไม่รวมยอดรายเดือน
Numerator:    ผู้ป่วยในตัวส่วนที่ได้ Metformin (1000184, qty > 0) ใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
              (query ต้นฉบับดูเฉพาะ visit ที่วินิจฉัยเบาหวาน)
Denominator:  ผู้ป่วยที่ผ่านครบ 3 ข้อ
              1. มี visit ผู้ป่วยนอก (ovst.an IS NULL) วินิจฉัย E11* ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
                 (ไม่นับ E10 / E13 / E14 ซึ่ง query ต้นฉบับนับ)
              2. ได้ยาลดระดับน้ำตาล 7 รหัสอย่างน้อย 1 ชนิด ใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
              3. ผล eGFR (lab 200) ล่าสุดในช่วง 12 เดือนที่สิ้นสุดวันสุดท้ายของช่วงเวลา ≥ 30
                 (ไม่มีผล = ไม่อยู่ในตัวส่วน · นับแล็บทั้ง OPD และ IPD · อ่านตัวเลขหน้าผล)
Target:       ≥ 80%
Direction:    สูงดี (higher)
Category:     ChronicDisease · กลุ่มยาของตัวชี้วัด: metformin · หน่วยนับ: patient (config rdu.kpi_count_unit)
ยาลดระดับน้ำตาล: 1000184 metformin, 1019001 glipizide, 1680038 pioglitazone, 1510002 Penfill Mixtard,
              1481213 / 1483896 regular insulin, 1590023 NPH (MetforminDiabetesQuery::GLUCOSE_LOWERING_ICODES)
ข้อมูลรายกรณี: 1 แถวต่อผู้ป่วย visit ตัวแทน = visit ล่าสุดที่ได้ Metformin (ถ้าอยู่ในตัวเศษ) มิฉะนั้น visit เบาหวานครั้งล่าสุด
              dx = รหัส E11* ของผู้ป่วยในช่วงเวลานั้น · egfr / egfr_date = ผล eGFR ล่าสุดที่ใช้ตัดสิน
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, lab_head, lab_order (ต้อง GRANT SELECT), patient, doctor,
              pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/ChronicDisease/MetforminDiabetesQuery.php · docs/SQL_KPI/KPI10.sql
HDC:          s_rdu12 (target / result) ใช้ในปุ่ม "เทียบ HDC"
ผลปีงบ 2569:  1,400 / 1,478 คน (94.7%) — HDC 1,425 / 1,482 (96.2%) · ถ้าไม่มีเงื่อนไขยาลดระดับน้ำตาล 1,400 / 1,665 (84.1%)
```

---

#### KPI-11: ยาน้ำ non-sedating ในเด็กที่ติดเชื้อทางเดินหายใจ ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็น
              โรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating
Numerator:    visit ในตัวส่วนที่ได้รับยาแก้แพ้ชนิด non-sedating (qty > 0)
Denominator:  visit ผู้ป่วยนอก (ovst.an IS NULL) ของเด็กอายุต่ำกว่า 15 ปีเต็ม ณ วันที่มารับบริการ (จาก patient.birthday)
              ที่มีวินิจฉัย J0x / J20 / J21 / J22 (ตำแหน่งใดก็ได้) ไม่ว่าจะได้ยาอะไร
Target:       ≤ 20% — ตามเกณฑ์ผ่านของ HDC: ตารางสรุป s_rdu21 ตัวชี้วัดที่ 12 (ตัวส่วน b12 = ยอดรวมของ s_rdu18)
              ผ่านสูงสุด 19.78%, ไม่ผ่านต่ำสุด 20.27% จาก 347 โรงพยาบาล
Direction:    ต่ำดี (lower)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: antihistamine (config rdu.drug_groups) · หน่วยนับ: visit
ยา:           1510009 CETIRIZINE SYR. 5 mg/5ml (ยาน้ำ), 1560013 Cetirizine 10 mg (ยาเม็ด) — นับยาเม็ดด้วยตามที่เภสัชกรกำหนด
              ไม่นับ CPM (ยาแก้แพ้ชนิดง่วง) · ไม่ตัด visit ที่มีโรคภูมิแพ้ร่วม
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
              (ไม่ใช้ vn_stat ของ query ต้นฉบับ — rdu_readonly ไม่มีสิทธิ์อ่าน)
Query:        app/Queries/Kpi/PatientSafety/ChildRespiratoryNonSedatingQuery.php · docs/SQL_KPI/KPI11.sql
HDC:          ยังไม่ผูก — s_rdu18 ตัวส่วนใกล้เคียง (542 visit) แต่ตัวเศษของโรงพยาบาลเป็น 0 ทั้งปี
              (น่าจะเพราะ HDC ไม่รู้จักรหัสยามาตรฐานของ Cetirizine ที่ส่ง) จึงยังไม่แสดงเทียบ
ผลปีงบ 2569:  58 / 476 visit (12.2%) — ยาน้ำ 22 · ยาเม็ด 36 · ปีงบ 2568: 66 / 406 (16.3%)
```

---

#### KPI-12: RAS Blockade 2 ชนิดร่วมกันในโรคความดันโลหิตสูง ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ
              RAS Blockade 2 ชนิดร่วมกันในการรักษา
Numerator:    visit ในตัวส่วนที่ได้รับยากลุ่ม RAS blockade ตั้งแต่ 2 ตัวยาที่ต่างกัน (qty > 0)
Denominator:  visit ผู้ป่วยนอกที่มีวินิจฉัย I10 (ตำแหน่งใดก็ได้) และได้รับยา RAS อย่างน้อย 1 ชนิด (qty > 0)
Target:       ≤ 0% — ไม่มีระดับ "ใกล้เป้าหมาย" (มากกว่า 0 = ไม่ผ่าน)
Direction:    ต่ำดี (lower)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: ยากลุ่ม RAS blockade (config rdu.drug_groups)
ยา RAS:       Enalapril 5/20 mg, Captopril 25 mg (ACEI) · Losartan 50 mg + รหัสเก่า 2 ตัว (ARB)
              นับตาม "ตัวยา" — Enalapril 5 mg + 20 mg = 1 ชนิด
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/PatientSafety/RasDualBlockadeQuery.php · docs/SQL_KPI/KPI12.sql
HDC:          s_rdu10 (รวม t10..t09 = ตัวส่วน, r10..r09 = ตัวเศษ)
ผลปีงบ 2569:  8 / 9,200 visit (0.09%) — 6 ใน 8 เป็น Enalapril + Captopril
```

---

### หมวด E: โรคเรื้อรัง (Chronic Disease)

---

#### KPI-13: การใช้ Long-acting Benzodiazepine ในผู้สูงอายุ ✅ implement แล้ว (2026-10)
```
ชื่อ:         ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine
Numerator:    visit ในตัวส่วนที่ได้รับ Diazepam (qty > 0)
Denominator:  ทุก visit ของผู้สูงอายุ 65 ปีเต็มขึ้นไป ณ วันที่มารับบริการ (จาก patient.birthday) ไม่จำกัดโรคหรือยา
              รวม visit ที่ต่อมารับไว้นอนโรงพยาบาล (ไม่กรอง ovst.an) — ตามนิยามของ HDC
Target:       ≤ 5% — HDC s_rdu21 ตัวชี้วัดที่ 10: ทุกโรงพยาบาลผ่าน (สูงสุด 4.43% ปีงบ 2569) จึงยืนยันเกณฑ์ตัดจากข้อมูลไม่ได้
Direction:    ต่ำดี (lower)
Category:     PatientSafety · กลุ่มยาของตัวชี้วัด: long_acting_bzd (config rdu.drug_groups) · หน่วยนับ: visit
ยา:           1000107 Diazepam 5 mg เม็ด, 1460009 Diazepam 2 mg เม็ด, 1000108 Diazepam ฉีด 10 mg/2ml
              ไม่นับ Clonazepam 1483866 (เภสัชกรตัดออก) และยาออกฤทธิ์สั้น-ปานกลาง (Lorazepam, Alprazolam)
ต่างจาก query ต้นฉบับ: อายุ ≥ 65 (เดิม ≥ 60), ไม่นับ Clonazepam, ไม่กรอง o.an, คิดอายุจากวันเกิดแทน vn_stat
HOSxP Tables: ovst, ovstdiag, opitemrece, drugitems, patient, doctor, pttype, spclty, kskdepartment
Query:        app/Queries/Kpi/PatientSafety/ElderlyLongActingBenzodiazepineQuery.php · docs/SQL_KPI/KPI13.sql
HDC:          s_rdu16 (target / result) ใช้ในปุ่ม "เทียบ HDC"
ผลปีงบ 2569:  97 / 31,517 visit (0.31%) — HDC 93 / 31,453 (0.30%) · ปีงบ 2568: 127 / 25,956 — HDC 118 / 25,951
```

---

## สรุปตาราง KPI

| # | Code | ชื่อย่อ | Target | Direction | Category |
|---|---|---|---|---|---|
| KPI-01 | uri_no_antibiotic | ATB ในแผลอุบัติเหตุ (ทุกชนิด) | ≥ 80% | สูงดี | antibiotic_opd |
| KPI-02 | fresh_accident_wound_antibiotic | ATB ในบาดแผลสดจากอุบัติเหตุ (ไม่รวมสัตว์กัด/ไฟไหม้/แผลลึก) | ≤ 40% | ต่ำดี | antibiotic_opd |
| KPI-03 | wound_no_antibiotic | ATB ในหญิงคลอดปกติครบกำหนด | ≥ 80% | สูงดี | antibiotic_opd |
| KPI-04 | ipd_antibiotic_use | ATB ใน URI/หลอดลมอักเสบเฉียบพลัน (OPD) | ≤ 30% | ต่ำดี | antibiotic_ipd |
| KPI-05 | cap_appropriate_antibiotic | ATB ในอุจจาระร่วงเฉียบพลัน | ≥ 80% | สูงดี | antibiotic_ipd |
| ~~KPI-06~~ | — | ⛔ ตัดออก 2026-10-17 (ATB ใน Pyelonephritis/UTI เฉียบพลัน) | — | — | — |
| KPI-07 | nsaid_duplication | NSAIDs ซ้ำซ้อน (OPD) | ≤ 5% | ต่ำดี | patient_safety |
| KPI-08 | asthma_ics | Inhaled Corticosteroid ในผู้ป่วยหืด (นับเป็นคน) | ≥ 80% | สูงดี | patient_safety |
| KPI-09 | ckd_nsaids | NSAIDs ในโรคไตเรื้อรังระดับ 3+ (นับเป็นคน) | ≤ 10% | ต่ำดี | patient_safety |
| KPI-10 | dm_metformin | Metformin ในเบาหวานชนิดที่ 2 ไม่มีข้อห้ามใช้ (นับเป็นคน) | ≥ 80% | สูงดี | chronic_disease |
| KPI-11 | child_respiratory_non_sedating | ยาน้ำ non-sedating ในเด็กติดเชื้อทางเดินหายใจ | ≤ 20% | ต่ำดี | patient_safety |
| KPI-12 | ras_dual_blockade | RAS Blockade 2 ชนิดร่วมกัน (HT) | ≤ 0% | ต่ำดี | patient_safety |
| KPI-13 | elderly_long_acting_bzd | Long-acting Benzodiazepine ผู้สูงอายุ | ≤ 5% | ต่ำดี | patient_safety |

---

## Query Architecture (โครงสร้าง — ก่อน Plan รายละเอียด)

แต่ละ KPI มี Query class แยกต่างหาก:

```
app/
└── Queries/
    └── Kpi/
        ├── KpiQueryInterface.php          ← Interface กำหนด contract
        ├── Antibiotic/
        │   ├── AccidentWoundAntibioticQuery.php ← KPI-01
        │   ├── FreshAccidentWoundAntibioticQuery.php ← KPI-02
        │   ├── NormalDeliveryAntibioticQuery.php ← KPI-03
        │   ├── RespiratoryInfectionAntibioticQuery.php ← KPI-04
        │   └── AcuteDiarrheaAntibioticQuery.php ← KPI-05
        ├── PatientSafety/
        │   ├── NsaidDuplicationQuery.php       ← KPI-07
        │   ├── AsthmaInhaledCorticosteroidQuery.php ← KPI-08
        │   ├── CkdNsaidQuery.php               ← KPI-09
        │   ├── ChildRespiratoryNonSedatingQuery.php ← KPI-11
        │   ├── RasDualBlockadeQuery.php        ← KPI-12
        │   └── ElderlyLongActingBenzodiazepineQuery.php ← KPI-13
        └── ChronicDisease/
            └── MetforminDiabetesQuery.php      ← KPI-10
```

### KpiQueryInterface (โครงสร้าง)
```php
interface KpiQueryInterface
{
    public function getNumerator(int $year, int $month): int;
    public function getDenominator(int $year, int $month): int;
    public function getDetails(int $year, int $month): Collection;  // รายกรณี
}
```

---

## Services ที่เกี่ยวข้องกับ KPI

### RduQueryService
- Inject Query class ตาม KPI ที่ต้องการ
- ทุก Query ต้องใช้ `DB::connection('rdu')` เท่านั้น
- ห้าม Controller เรียก Query โดยตรง

### RduCalculationService
- รับ Numerator, Denominator จาก Query
- คำนวณ Percentage, Status, Trend
- เปรียบเทียบกับ Target จาก `rdu_indicators` table

### RduDataSourceInterface
- รองรับ `MockRduDataSource` สำหรับ Testing
- รองรับ `HosxpRduDataSource` สำหรับ Production

---

## Data Flow

```
HOSxP Database
      ↓ (SELECT ONLY via connection 'rdu')
KpiQueryInterface (แต่ละ KPI มี Query ของตัวเอง)
      ↓
RduQueryService
      ↓
RduCalculationService
      ↓
Cache (key: kpi_{code}_{year}_{month})
      ↓
Controller
      ↓
Blade View
      ↓
Chart.js
```

---

## Performance Requirements

- แต่ละ KPI Query ทำงานอิสระ — ไม่ block กัน
- Cache key: `kpi_{code}_{year}_{month}` TTL: [TBD]
- ตรวจสอบ N+1 Query Problem
- ใช้ Pagination สำหรับรายการข้อมูลรายกรณี
- ตรวจสอบ Chart Payload ไม่ให้ใหญ่เกินไป

---

## Development Phases สำหรับ KPI

| Phase | เนื้อหา |
|---|---|
| Phase 5 | Dashboard Foundation: Layout, Sidebar, KPI Cards, Filters (Mock Data) |
| Phase 6 | KPI Engine: KpiQueryInterface, Query classes (ทั้ง 13 ตัว), RduCalculationService |
| Phase 7 | KPI Detail: Detail Page ทุก KPI พร้อม Chart และ Data Table |
| Phase 8 | Reports: Excel, CSV, PDF Export |

> **Note:** Query รายละเอียดของแต่ละ KPI จะ Plan แยกต่างหากหลัง Roadmap นี้เสร็จสิ้น
