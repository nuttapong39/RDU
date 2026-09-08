# KPI SPECIFICATION
## RDU Dashboard — Key Performance Indicators

---

## ภาพรวม

ระบบ RDU Dashboard ติดตาม **13 ตัวชี้วัด (KPI)** การใช้ยาอย่างสมเหตุผล (Rational Drug Use)

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

## รายการ 13 KPI (Placeholder — รอยืนยัน)

### หมวด A: ยาปฏิชีวนะใน OPD (Antibiotic in Outpatient)

---

#### KPI-01: URI ไม่ได้รับยาปฏิชีวนะ
```
ชื่อ:         ร้อยละผู้ป่วยนอกโรคติดเชื้อระบบทางเดินหายใจส่วนต้น
              ที่ไม่ได้รับยาปฏิชีวนะ
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

#### KPI-02: อุจจาระร่วงเฉียบพลัน ไม่ได้รับยาปฏิชีวนะ
```
ชื่อ:         ร้อยละผู้ป่วยนอกโรคอุจจาระร่วงเฉียบพลัน
              ที่ไม่ได้รับยาปฏิชีวนะ
Code:         diarrhea_no_antibiotic
Numerator:    จำนวนผู้ป่วยอุจจาระร่วงที่ไม่ได้รับยาปฏิชีวนะ
Denominator:  จำนวนผู้ป่วยอุจจาระร่วงเฉียบพลันทั้งหมด
Target:       ≥ 80%
Direction:    สูงดี (higher_better)
Category:     antibiotic_opd
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-03: แผลสดสะอาด ไม่ได้รับยาปฏิชีวนะ
```
ชื่อ:         ร้อยละผู้ป่วยแผลสดสะอาดที่ไม่ได้รับยาปฏิชีวนะ
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

#### KPI-04: ผู้ป่วยในที่ได้รับยาปฏิชีวนะ
```
ชื่อ:         ร้อยละผู้ป่วยในที่ได้รับยาปฏิชีวนะ
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

#### KPI-05: ปอดอักเสบ (CAP) ได้รับยาปฏิชีวนะที่เหมาะสม
```
ชื่อ:         ร้อยละผู้ป่วยโรคปอดอักเสบชุมชน (CAP)
              ที่ได้รับยาปฏิชีวนะตามแนวทาง
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

#### KPI-06: มูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ
```
ชื่อ:         ร้อยละมูลค่าการใช้ยาในบัญชียาหลักแห่งชาติ
              ต่อมูลค่ายาทั้งหมด
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

#### KPI-07: มูลค่าการใช้ยาสามัญ
```
ชื่อ:         ร้อยละมูลค่าการใช้ยาสามัญต่อมูลค่ายาทั้งหมด
Code:         generic_drug_value
Numerator:    มูลค่าการใช้ยาสามัญ
Denominator:  มูลค่าการใช้ยาทั้งหมด
Target:       ≥ 60%
Direction:    สูงดี (higher_better)
Category:     drug_quality
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-08: มูลค่าการใช้ยาต้านจุลชีพ
```
ชื่อ:         ร้อยละมูลค่าการใช้ยาต้านจุลชีพต่อมูลค่ายาทั้งหมด
Code:         antimicrobial_value_ratio
Numerator:    มูลค่าการใช้ยาต้านจุลชีพ
Denominator:  มูลค่าการใช้ยาทั้งหมด
Target:       ≤ 25%
Direction:    ต่ำดี (lower_better)
Category:     drug_quality
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

### หมวด D: ความปลอดภัยของผู้ป่วย (Patient Safety)

---

#### KPI-09: การใช้ NSAIDs ในผู้สูงอายุ
```
ชื่อ:         ร้อยละผู้ป่วยอายุ 60 ปีขึ้นไปที่ได้รับยา NSAIDs
Code:         elderly_nsaids_use
Numerator:    จำนวนผู้สูงอายุที่ได้รับยา NSAIDs
Denominator:  จำนวนผู้ป่วยอายุ ≥ 60 ปีทั้งหมด
Target:       ≤ 10%
Direction:    ต่ำดี (lower_better)
Category:     patient_safety
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-10: การใช้ยาฉีดวิตามิน B1 โดยไม่มีข้อบ่งชี้
```
ชื่อ:         ร้อยละผู้ป่วยที่ได้รับยาฉีดวิตามิน B1 โดยไม่มีข้อบ่งชี้
Code:         vit_b1_injection_no_indication
Numerator:    จำนวนผู้ป่วยที่ได้รับวิตามิน B1 ฉีดโดยไม่มีข้อบ่งชี้
Denominator:  จำนวนผู้ป่วยที่ได้รับวิตามิน B1 ฉีดทั้งหมด
Target:       ≤ 5%
Direction:    ต่ำดี (lower_better)
Category:     patient_safety
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-11: การใช้ Corticosteroid ฉีดโดยไม่มีข้อบ่งชี้
```
ชื่อ:         ร้อยละผู้ป่วยที่ได้รับ Corticosteroid ชนิดฉีด
              โดยไม่มีข้อบ่งชี้
Code:         corticosteroid_injection_no_indication
Numerator:    จำนวนผู้ป่วยที่ได้รับ Corticosteroid ฉีดโดยไม่มีข้อบ่งชี้
Denominator:  จำนวนผู้ป่วยที่ได้รับ Corticosteroid ฉีดทั้งหมด
Target:       ≤ 5%
Direction:    ต่ำดี (lower_better)
Category:     patient_safety
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

#### KPI-12: Polypharmacy ในผู้สูงอายุ
```
ชื่อ:         ร้อยละผู้ป่วยสูงอายุที่ได้รับยา ≥ 5 รายการต่อครั้ง
Code:         elderly_polypharmacy
Numerator:    จำนวนผู้สูงอายุที่ได้รับยา ≥ 5 รายการ
Denominator:  จำนวนผู้สูงอายุที่มารับการรักษาทั้งหมด
Target:       ≤ 10%
Direction:    ต่ำดี (lower_better)
Category:     patient_safety
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

### หมวด E: โรคเรื้อรัง (Chronic Disease)

---

#### KPI-13: ผู้ป่วยเบาหวานควบคุมน้ำตาลได้ดี
```
ชื่อ:         ร้อยละผู้ป่วยเบาหวานที่มีค่า HbA1c < 7%
Code:         dm_hba1c_controlled
Numerator:    จำนวนผู้ป่วยเบาหวานที่มี HbA1c < 7%
Denominator:  จำนวนผู้ป่วยเบาหวานที่มีการตรวจ HbA1c ทั้งหมด
Target:       ≥ 40%
Direction:    สูงดี (higher_better)
Category:     chronic_disease
HOSxP Tables: [TBD]
Query:        [TBD — Plan แยกต่างหาก]
```

---

## สรุปตาราง 13 KPI

| # | Code | ชื่อย่อ | Target | Direction | Category |
|---|---|---|---|---|---|
| KPI-01 | uri_no_antibiotic | URI ไม่ใช้ AB | ≥ 80% | สูงดี | antibiotic_opd |
| KPI-02 | diarrhea_no_antibiotic | อุจจาระร่วง ไม่ใช้ AB | ≥ 80% | สูงดี | antibiotic_opd |
| KPI-03 | wound_no_antibiotic | แผลสะอาด ไม่ใช้ AB | ≥ 80% | สูงดี | antibiotic_opd |
| KPI-04 | ipd_antibiotic_use | IPD ใช้ AB | ≤ 30% | ต่ำดี | antibiotic_ipd |
| KPI-05 | cap_appropriate_antibiotic | CAP ใช้ AB ถูกต้อง | ≥ 80% | สูงดี | antibiotic_ipd |
| KPI-06 | national_drug_list_value | มูลค่ายา NDF | ≥ 80% | สูงดี | drug_quality |
| KPI-07 | generic_drug_value | มูลค่ายาสามัญ | ≥ 60% | สูงดี | drug_quality |
| KPI-08 | antimicrobial_value_ratio | มูลค่า Antimicrobial | ≤ 25% | ต่ำดี | drug_quality |
| KPI-09 | elderly_nsaids_use | NSAIDs ผู้สูงอายุ | ≤ 10% | ต่ำดี | patient_safety |
| KPI-10 | vit_b1_injection_no_indication | Vit B1 ฉีดไม่มีข้อบ่งชี้ | ≤ 5% | ต่ำดี | patient_safety |
| KPI-11 | corticosteroid_injection_no_indication | Steroid ฉีดไม่มีข้อบ่งชี้ | ≤ 5% | ต่ำดี | patient_safety |
| KPI-12 | elderly_polypharmacy | Polypharmacy ผู้สูงอายุ | ≤ 10% | ต่ำดี | patient_safety |
| KPI-13 | dm_hba1c_controlled | DM ควบคุมน้ำตาลได้ | ≥ 40% | สูงดี | chronic_disease |

---

## Query Architecture (โครงสร้าง — ก่อน Plan รายละเอียด)

แต่ละ KPI มี Query class แยกต่างหาก:

```
app/
└── Queries/
    └── Kpi/
        ├── KpiQueryInterface.php          ← Interface กำหนด contract
        ├── Antibiotic/
        │   ├── UriNoAntibioticQuery.php   ← KPI-01
        │   ├── DiarrheaNoAntibioticQuery.php  ← KPI-02
        │   ├── WoundNoAntibioticQuery.php      ← KPI-03
        │   ├── IpdAntibioticUseQuery.php       ← KPI-04
        │   └── CapAppropriateAntibioticQuery.php ← KPI-05
        ├── DrugQuality/
        │   ├── NationalDrugListValueQuery.php  ← KPI-06
        │   ├── GenericDrugValueQuery.php       ← KPI-07
        │   └── AntimicrobialValueQuery.php     ← KPI-08
        ├── PatientSafety/
        │   ├── ElderlyNsaidsQuery.php          ← KPI-09
        │   ├── VitB1InjectionQuery.php         ← KPI-10
        │   ├── CorticosteroidInjectionQuery.php ← KPI-11
        │   └── ElderlyPolypharmacyQuery.php    ← KPI-12
        └── ChronicDisease/
            └── DmHba1cControlledQuery.php      ← KPI-13
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
| Phase 5 | Dashboard Foundation: Layout, Sidebar, 13 KPI Cards, Filters (Mock Data) |
| Phase 6 | KPI Engine: KpiQueryInterface, Query classes (ทั้ง 13 ตัว), RduCalculationService |
| Phase 7 | KPI Detail: Detail Page ทั้ง 13 KPI พร้อม Chart และ Data Table |
| Phase 8 | Reports: Excel, CSV, PDF Export |

> **Note:** Query รายละเอียดของแต่ละ KPI จะ Plan แยกต่างหากหลัง Roadmap นี้เสร็จสิ้น
