# KPI Reference Implementation
## แบบอ้างอิงสำหรับพัฒนาตัวชี้วัด (KPI) ตัวถัดไป

KPI01, KPI03, KPI04 และ KPI05 คือตัวชี้วัดที่ implement จริงและผ่านการตรวจกับข้อมูล HOSxP แล้ว (ณ 2026-09)
ทั้ง 4 ตัวถือเป็น **แบบอ้างอิง (reference)** ของ KPI อื่นทั้งหมด KPI ใหม่ต้องทำตามรูปแบบในเอกสารนี้
ทั้งฝั่ง query, ข้อมูลรายกรณี และหน้าแสดงผล ถ้าต้องต่างจากนี้ ให้บันทึกเหตุผลเป็น ADR ใน `docs/adr/`

กฎความปลอดภัยใน `CLAUDE.md` (HOSxP อ่านอย่างเดียว, query ผ่าน `RduQueryService` เท่านั้น) มีผลเหนือเอกสารนี้เสมอ
คำศัพท์โดเมน (จำนวนการใช้ยา, เป้าหมาย, สถานะ, แพทย์ผู้ตรวจ ฯลฯ) ดูที่ `CONTEXT.md`

---

## 1. ตัวอ้างอิงทั้ง 4 ตัว — ใช้ตัวไหนเป็นต้นแบบ

| KPI | Query class | Reference SQL | ประเภทข้อมูล | ตัวตั้ง (ตัวเศษ) | เป้าหมาย | ใช้เป็นต้นแบบเมื่อ |
|---|---|---|---|---|---|---|
| **KPI04** | `Antibiotic/RespiratoryInfectionAntibioticQuery` | `docs/SQL_KPI/KPI4.sql` | OPD (`ovst`, VN) | visit ที่**ได้รับ**ยาปฏิชีวนะ | ≤ 40% (`lower`) | **แม่แบบหลัก** — OPD + รายการ ICD-10 ชุดเดียว |
| **KPI05** | `Antibiotic/AcuteDiarrheaAntibioticQuery` | `docs/SQL_KPI/KPI5.sql` | OPD (`ovst`, VN) | visit ที่**ได้รับ**ยาปฏิชีวนะ | ≤ 20% (`lower`) | โครงเดียวกับ KPI04 ทุกอย่าง ต่างแค่รหัสวินิจฉัย |
| **KPI01** | `Antibiotic/AccidentWoundAntibioticQuery` | `docs/SQL_KPI/KPI1.sql` | OPD (`ovst`, VN) | visit ที่**ได้รับ**ยาปฏิชีวนะ | ≤ 40% (`lower`) | เงื่อนไขวินิจฉัย 2 กลุ่มต้องมีคู่กัน (ADR 0001), แพทย์ 2 บทบาท, มี CC/HPI |
| **KPI03** | `Antibiotic/NormalDeliveryAntibioticQuery` | `docs/SQL_KPI/KPI3.sql` | IPD (`ipt` + `an_stat`, AN) | admission ที่**ไม่ได้รับ**ยาปฏิชีวนะ | ≥ 80% (`higher`) | ผู้ป่วยใน และตัวชี้วัดกลับทิศ (ยิ่งสูงยิ่งดี) |

**เริ่มจาก KPI04 เสมอ** แล้วนำส่วนที่ต่างจาก KPI01 (เงื่อนไขหลายกลุ่ม) หรือ KPI03 (IPD / กลับทิศ) มาใช้เฉพาะเมื่อจำเป็น
ตัวชี้วัดที่ยังไม่ implement (KPI02, KPI06-KPI13) ตอนนี้เป็น stub ที่คืน 0 / collection ว่างทุก method

---

## 2. ขั้นตอนเพิ่ม KPI ใหม่ (checklist ตามลำดับ)

### 2.1 ก่อนเขียนโค้ด — ตรวจ reference SQL และ seed
- [ ] รับ reference SQL จากทีม IT/รายงานของโรงพยาบาล
- [ ] **ตรวจ `WHERE` ของ query ว่ามีเงื่อนไขเปรียบเทียบร้อยละ/อัตรา (`>=`, `<=`) หรือไม่ แล้วรายงานผลให้ชัดทั้งกรณีมีและไม่มี**
      เพราะเป็นตัวตัดสินว่าตัวตั้งคืออะไร และ `target_direction` ใดคำนวณได้จริง
- [ ] เทียบ `name` / `numerator_desc` / `denominator_desc` / `target_value` / `target_direction` ใน
      `database/seeders/RduIndicatorSeeder.php` กับ query **อย่าเชื่อ seed** (เคยพบข้อความสลับ/คัดลอกผิดใน KPI02, KPI05)
- [ ] ตัดสินว่าตัวตั้งเป็น "ได้รับยา" (`lower`) หรือ "ไม่ได้รับยา" (`higher` แบบ KPI03) ให้ผู้ใช้ยืนยัน

### 2.2 Reference SQL — `docs/SQL_KPI/KPI{n}.sql`
- ชื่อไฟล์ไม่เติมศูนย์: `KPI1.sql`, `KPI4.sql` (ไม่ใช่ `KPI01.sql`)
- เป็น SQL ที่**ตรงกับสิ่งที่ implement จริง** ไม่ใช่ query ดิบที่ได้รับมา (ตัดคอลัมน์ที่ไม่ใช้และข้อมูลอ่อนไหวเช่น CID ออก)
- ส่วนหัวเป็น comment ภาษาไทยตามแบบ `KPI4.sql`: ชื่อตัวชี้วัด, ที่มา, และ "หมายเหตุสำคัญ" ได้แก่
  ความหมายของตัวเศษและทิศทาง, เงื่อนไขวินิจฉัย, OPD/IPD, ที่มาของ `atb_cost` / `total_drug_items` /
  `coverage` / `department` / `room`
- ใช้ CTE `date_range` ให้แก้ช่วงวันที่ที่เดียวก่อนรันตรวจใน HOSxP

### 2.3 Query class — `app/Queries/Kpi/{Category}/{Name}Query.php`
แทนที่ stub เดิมของ KPI นั้น (หรือสร้างใหม่ถ้าชื่อ stub ไม่ตรงกับนิยามจริง แบบที่ KPI05 เคยเปลี่ยนจาก `CapAppropriateAntibioticQuery`)
รายละเอียดดูหัวข้อ 3

### 2.4 ลงทะเบียนและตั้งค่า
- [ ] `app/Providers/AppServiceProvider.php` — map รหัส KPI กับ class ใน `HosxpRduDataSource`
- [ ] `tests/Feature/HosxpQueryTest.php` — แก้ data provider ของ `test_query_class_can_be_instantiated`
- [ ] `database/seeders/RduIndicatorSeeder.php` — ข้อความตัวเศษ/ตัวส่วน, `target_value`, `target_direction` ที่ยืนยันแล้ว
- [ ] `app/Http/Controllers/DashboardController.php` — เพิ่มรหัสใน `DEEP_DIVE_KPI_CODES` เพื่อเปิดโซน "การวิเคราะห์เชิงลึก"
- [ ] `resources/views/dashboard/kpi.blade.php` — เพิ่มรหัสใน `$numeratorLabel` (ข้อความ legend ของแท่งจำนวนการใช้ยา)
      และตรวจข้อความเฉพาะ KPI ตามหัวข้อ 5.2
- [ ] `CONTEXT.md` — เพิ่มคำนิยามถ้ามีแนวคิดโดเมนใหม่ (เช่น นิยามกลุ่มผู้ป่วยที่ไม่ชัด)
- [ ] `docs/adr/` — เขียน ADR เมื่อเป็นการตัดสินใจที่ย้อนยาก ไม่ชัดเจนถ้าไม่มีบริบท และมีทางเลือกจริง (แบบ ADR 0001)
- [ ] `docs/KPI-SPECIFICATION.md` — อัปเดตหัวข้อของ KPI นั้นให้ตรงกับที่ implement

### 2.5 ตรวจสอบก่อน commit
- [ ] `php artisan cache:clear` ก่อนทุกครั้ง (ผลลัพธ์ query ถูก cache 1 ชั่วโมง)
- [ ] เทียบ `getNumerator` / `getDenominator` รายเดือนผ่าน tinker กับผลของ reference SQL ที่รันตรงใน HOSxP ช่วงเดียวกัน
- [ ] หลังเพิ่ม JOIN ใดๆ ใน `getDetailsBetween` จำนวนแถวต้อง**เท่าเดิม** (JOIN ต้องไม่ทำให้แถวซ้ำ)
- [ ] จำนวนแถวรายกรณีต้องเท่ากับตัวส่วน และแถวที่ `drugitems_name` ไม่ว่าง (สำหรับ `lower`) ต้องเท่ากับตัวตั้ง
- [ ] render หน้า KPI นั้นและหน้า KPI อื่นทั้งหมดผ่าน kernel/tinker ได้ 200
- [ ] ตรวจปีงบประมาณให้ถูก: ปีงบ พ.ศ. X = ต.ค. (X-1) ถึง ก.ย. X (ใช้ `FiscalDateRange` เท่านั้น ห้ามคำนวณเอง)
- [ ] ถ้า query พังด้วย `permission denied for table ...` ให้ขอ `GRANT SELECT` ให้ role ที่แอปใช้จริง
      (ตรวจด้วย `SELECT current_user`) ไม่ใช่ role ที่เข้าใจเอา

---

## 3. สัญญาของ Query class

### 3.1 โครงสร้าง method (เหมือนกันทุกตัว)
`implements KpiQueryInterface` มี 6 method:
- `getNumerator` / `getDenominator` / `getDetails` (ปีงบ + เดือน) → แปลงเป็นช่วงวันที่ด้วย
  `FiscalDateRange::toGregorian($year, $month)` แล้วส่งต่อให้ method `…Between` เท่านั้น ไม่มี logic ของตัวเอง
- `getNumeratorBetween` / `getDenominatorBetween` / `getDetailsBetween` (Y-m-d) → มี SQL จริง
- `getDetailsBetween` **ไม่มี LIMIT** ผู้เรียกจัดการเอง

### 3.2 กติกา SQL
| เรื่อง | กติกา | ตัวอย่างใน reference |
|---|---|---|
| Connection | `DB::connection('rdu')->select(...)` เท่านั้น (SELECT) | ทุกตัว |
| วันที่ | bind เป็น parameter `?` เสมอ | `WHERE ovst.vstdate BETWEEN ? AND ?` |
| ช่วงวันที่ | OPD ใช้ `ovst.vstdate`, IPD ใช้ `ipt.dchdate` (วันจำหน่าย) | KPI04 / KPI03 |
| หน่วยนับ | OPD นับ `COUNT(DISTINCT vn)`, IPD นับ `COUNT(DISTINCT an)` | KPI04 / KPI03 |
| ตัดเคสทดสอบ | `hn NOT IN (SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%')` ทั้งตัวตั้ง ตัวส่วน และรายกรณี | ทุกตัว |
| รายการรหัส | เก็บเป็น `private const` แล้วต่อด้วย `quotedList()` (ค่าคงที่ภายใน ไม่ใช่ input ผู้ใช้) | `DIAGNOSIS_CODES`, `ANTIBIOTIC_ICODES` |
| กรองวินิจฉัยระดับ visit | `EXISTS (SELECT 1 FROM ovstdiag … )` ผ่าน helper `diagnosisExists()` | KPI04/05 |
| ยาปฏิชีวนะ | `drugitems.icode IN (ANTIBIOTIC_ICODES)` และ `opitemrece.qty > 0` (66 รหัสชุดเดียวกันทุก KPI) | ทุกตัว |
| ตัวตั้งแบบกลับทิศ | `an NOT IN (… รายการที่ได้ยา …)` | KPI03 |
| โครง CTE รายกรณี | `vn_list` (หรือ `an_list`) → `drug_info` (เฉพาะยาปฏิชีวนะ) → `all_drug_info` (ยาทุกชนิด) | ทุกตัว |
| ค่ายา | `SUM(opitemrece.sum_price)` (ราคาตอนจ่ายจริง) **ห้ามใช้** `drugitems.unitprice` | ทุกตัว |
| แพทย์ผู้ตรวจ | `JOIN doctor … AND council_code = '01' AND active = 'Y'` จาก `ovst.doctor` (OPD) / `ipt.admdoctor` (IPD) | KPI04 / KPI03 |
| สิทธิ์การรักษา | `pttype.name` จาก `ovst.pttype` / `ipt.pttype` | ทุกตัว |
| แผนก | `spclty.name` จาก `spclty` ของ visit/admission | ทุกตัว |
| ห้องตรวจ / หอผู้ป่วย | OPD: `kskdepartment.department` จาก `ovst.main_dep` (**ห้ามใช้** `cur_dep` จะเป็น "กลับบ้าน" เสมอ) · IPD: `ward.name` จาก `ipt.ward` | KPI04 / KPI03 |
| การเรียงลำดับ | `ORDER BY วันที่, hn, vn/an` (KPI01 เรียงเคสได้ยาขึ้นก่อน) | ทุกตัว |

### 3.3 แถวข้อมูลรายกรณี (`getDetailsBetween` คืนค่า)
หน้าแสดงผลอ่าน key เหล่านี้โดยตรง **ห้ามเปลี่ยนชื่อ key** — ถ้าขาด key ใด ส่วนที่ใช้ key นั้นจะว่างหรือคำนวณผิด

| Key | ชนิด | ความหมาย | ถูกใช้โดย |
|---|---|---|---|
| `HN` | string | HN | - |
| `VN` หรือ `AN` | string | เลข visit (OPD) / admission (IPD) | - |
| `vstdate` หรือ `dchdate` | date | วันที่รับบริการ / วันจำหน่าย | - |
| `dx` | string | รหัส ICD-10 ของเคส คั่นด้วย `,` | อันดับ ICD-10 |
| `fullname` | string | ชื่อที่ปกปิดแล้ว (หัวข้อ 3.4) | - |
| `drugitems_name` | string\|null | ยาปฏิชีวนะที่ได้ คั่นด้วย `;` — **ว่าง/NULL = ไม่ได้รับยาปฏิชีวนะ** | อัตรารายแพทย์, สัดส่วนยา, สิทธิ์, แผนก, ห้อง, สถิติ visit |
| `atb_cost` | float | ค่ายาปฏิชีวนะของเคส | สถิติ visit |
| `total_drug_items` | int | จำนวนรายการยา**ทุกชนิด**ของเคส | สถิติ visit |
| `doctor` + `doctor_code` | string\|null | แพทย์ผู้ตรวจ (KPI03/04/05) | อัตรารายแพทย์ (รหัสปกปิด DR-XX) |
| `attending_doctor` + `attending_doctor_code` | string\|null | แพทย์ผู้ตรวจ เมื่อ KPI มีแพทย์ 2 บทบาท (KPI01 ใช้ `doctor` = แพทย์ผู้สั่งยา) | อัตรารายแพทย์ (อ่าน key นี้ก่อน `doctor`) |
| `coverage` | string\|null | สิทธิ์การรักษา | ตารางสิทธิ์การรักษา |
| `department` | string\|null | แผนก | ตารางแผนก |
| `room` | string\|null | ห้องตรวจ (OPD) / หอผู้ป่วย (IPD) | ตารางห้อง/หอ |
| `CC`, `HPI` | string\|null | อาการสำคัญ / ประวัติ (เฉพาะ KPI ที่ต้องใช้ เช่น KPI01) | - |

### 3.4 PDPA
- ใช้ `maskThaiName()`: คำนำหน้า + ชื่อแสดงเต็ม นามสกุลแทนด้วย `xxxx`
- ห้ามดึง CID หรือข้อมูลระบุตัวตนอื่นที่ไม่จำเป็นต่อการวัด KPI
- ชื่อแพทย์ในหน้าเว็บแสดงเป็นรหัสปกปิด `DR-XX` โดยอัตโนมัติ (ADR 0002) ไม่ต้องทำใน query

### 3.5 สิ่งที่ซ้ำกันโดยตั้งใจ (ต้องแก้ให้ตรงกันทุกไฟล์)
`ANTIBIOTIC_ICODES`, `quotedList()` และ `maskThaiName()` ยังคัดลอกอยู่ในทั้ง 4 class ถ้าแก้รายการยาปฏิชีวนะ
ต้องแก้ทุก class และทุกไฟล์ใน `docs/SQL_KPI/` พร้อมกัน ถ้าจะแยกเป็นส่วนกลาง ให้ทำเป็นงาน refactor แยก
ไม่ปนกับการเพิ่ม KPI

---

## 4. ตัวชี้วัดกลับทิศ (`target_direction = 'higher'` แบบ KPI03)

ส่วนที่ปรับตามทิศทางให้อัตโนมัติแล้ว (ไม่ต้องทำเพิ่ม):
- สถานะ ผ่าน/ใกล้เป้าหมาย/ไม่ผ่าน (`RduCalculationService::getStatus()`)
- สีและเส้นเกณฑ์ในตารางอัตรารายแพทย์ (`isDoctorRateRed()`, `doctorRateThreshold()`)
- โดนัท "ได้ยา/ไม่ได้ยา" (สลับตัวตั้งให้เมื่อทิศทางเป็น `higher`)
- ข้อความ "ยิ่งสูงยิ่งดี/ยิ่งต่ำยิ่งดี" และบรรทัดอธิบายใต้กราฟแนวโน้ม

ส่วนที่ต้องทำเอง:
- เพิ่มรหัสใน `$numeratorLabel` เป็น "visit ที่ไม่ได้รับยาปฏิชีวนะ" (หรือข้อความตามนิยามจริง)
- ตรวจว่าตัวเลข "ได้ ATB" ในตารางสิทธิ์/แผนก/ห้อง ยังหมายถึงเคสที่ได้ยาจริง (นับจาก `drugitems_name` ไม่ใช่จากตัวตั้ง)

---

## 5. หน้าแสดงผล KPI

### 5.1 ทุก KPI ใช้หน้าเดียวกัน
`resources/views/dashboard/kpi.blade.php` เป็นหน้าเดียวสำหรับทุก KPI **ห้ามสร้าง layout แยกต่อ KPI**
ส่วนที่แสดงกับทุก KPI (รวม stub): การ์ดไตรมาส, ตัวกรอง, ตัวเลขหลัก, กราฟแนวโน้มรายเดือน, กราฟอัตราสะสม,
โดนัทสัดส่วน visit, การ์ดสถิติ visit, สัดส่วนรายไตรมาส และคำอธิบายตัวชี้วัด
ส่วนที่แสดงเฉพาะรหัสใน `DEEP_DIVE_KPI_CODES`: อัตรารายแพทย์, ICD-10 ที่พบบ่อย 10 อันดับ, สัดส่วนยาปฏิชีวนะ,
สิทธิ์การรักษา, แผนก และห้องตรวจ/หอผู้ป่วย

กราฟแนวโน้มรายเดือน (รูปแบบที่ยืนยันแล้ว): แท่ง visit ทั้งหมด (เทา) + แท่งจำนวนการใช้ยา (indigo) บนแกนจำนวน
ด้านขวา และเส้นผลลัพธ์ % (จุดสีตามสถานะ) + เส้นประเป้าหมายคงที่บนแกน % ด้านซ้าย

### 5.2 จุดที่ผูกกับรหัส KPI (ตรวจทุกครั้งที่เพิ่ม KPI)
| ตำแหน่ง | ผูกกับ | สิ่งที่ต้องทำ |
|---|---|---|
| `DashboardController::DEEP_DIVE_KPI_CODES` | KPI ที่มีข้อมูลรายกรณีจริง | เพิ่มรหัส |
| `$numeratorLabel` ใน `kpi.blade.php` | ข้อความตัวตั้ง | เพิ่มรหัสพร้อมข้อความ |
| หัวตาราง "ห้องตรวจ" / "หอผู้ป่วย" | IPD ใช้ "หอผู้ป่วย" (ตอนนี้ผูกกับ `KPI03`) | ถ้าเป็น IPD เพิ่มรหัส |
| แถวจำนวนเคสทั้งปีในการ์ดตัวเลขหลัก | เฉพาะ `KPI01` | เพิ่มถ้าต้องการแสดงแบบเดียวกัน |
| แจ้งเตือนกรณีไม่มีเคส/ไม่มีการใช้ยาเลย | เฉพาะ `KPI03` (กลุ่มผู้ป่วยที่เกิดน้อย) | เพิ่มถ้า KPI ใหม่มีตัวส่วนเป็น 0 บ่อย |

---

## 6. อ้างอิง
- `CLAUDE.md` — กฎความปลอดภัยและโครงสร้างโปรเจกต์
- `CONTEXT.md` — คำศัพท์โดเมน
- `docs/adr/0001-kpi01-injury-and-external-cause-required.md` — เงื่อนไขวินิจฉัยคู่ของ KPI01
- `docs/adr/0002-doctor-identity-masked-in-web-ui-only.md` — การปกปิดชื่อแพทย์ในหน้าเว็บ
- `docs/KPI-SPECIFICATION.md` — รายการและนิยาม KPI ทั้ง 13 ตัว
- `docs/SQL_KPI/` — reference SQL ของ KPI ที่ implement แล้ว
