-- KPI-02: ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก
-- ที่มา: query อ้างอิงจากเภสัชกร (2026-10, ต้นฉบับอยู่ในประวัติ git commit 0f565ab)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/Antibiotic/FreshAccidentWoundAntibioticQuery.php
-- (ตัดคอลัมน์ CID / อายุ / เพศ / ชื่อแพทย์ผู้วินิจฉัย-ผู้สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA และ role
--  rdu_readonly ไม่มีสิทธิ์อ่าน vn_stat)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-17 - ดู CONTEXT.md "บาดแผลสดจากอุบัติเหตุ"):
--   - นับเป็น visit (VN) ผู้ป่วยนอกเท่านั้น (ovst.an IS NULL)
--   - ตัวส่วน = visit ที่มี "ทั้ง" รหัสลักษณะการบาดเจ็บ (injury_prefix) "และ" รหัสสาเหตุภายนอก V / W / X00-X59
--     (ตำแหน่งใดก็ได้ อาจอยู่คนละแถวของ ovstdiag) - เหมือนหลักของ KPI01 (docs/adr/0001)
--     แล้วตัดออกทั้ง visit ถ้ามีรหัสใดขึ้นต้นด้วย excluded_prefix (สัตว์กัด / ไฟไหม้น้ำร้อนลวก / กล้ามเนื้อ-เอ็น)
--   - ตัวเศษ = visit ในตัวส่วนที่ "จ่าย" ยาปฏิชีวนะ 28 รหัสของเภสัชกรอย่างน้อย 1 รายการ (qty > 0)
--     target_direction='lower', เป้า 40%
--   - ต่างจากต้นฉบับ 2 จุด:
--       1. ต้นฉบับใช้ "รหัสแผล OR รหัสสาเหตุ" ทำให้นับ visit ที่มีแค่รหัสสาเหตุ (ไม่มีรหัสแผล) ด้วย
--          ได้ตัวส่วน 2,401 ห่าง HDC (1,309) เกือบเท่าตัว - เปลี่ยนเป็น AND ตามหลักยึด HDC ได้ 1,437 (docs/adr/0003)
--       2. นับเฉพาะ qty > 0 และตัดเคสทดสอบ
--   - รายการรหัสแผลตัด S16 และ T20-T32 ออก เพราะอยู่ในรายการตัดออกอยู่แล้ว (ผลเท่าเดิม)
--   - drugitems_name / atb_cost มีค่าเฉพาะ visit ในตัวเศษ (ว่าง = ไม่อยู่ในตัวเศษ - สัญญาข้อมูลรายกรณีของทุก KPI)
--   - total_drug_items นับยา "ทุกชนิด" ต่อ visit, coverage / department / room เหมือน KPI04
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 1,437 / ตัวเศษ 750 (HDC 1,309 / 655)
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- รหัสลักษณะการบาดเจ็บ (3 ตัวแรก) ของ KPI02 - ไม่ใช่ชุดเดียวกับ KPI01
injury_prefix AS (
    SELECT * FROM (VALUES
        ('S00'),('S01'),('S05'),('S07'),('S08'),('S09'),
        ('S10'),('S11'),('S15'),('S17'),('S18'),('S19'),
        ('S20'),('S21'),('S25'),('S27'),('S28'),('S29'),
        ('S30'),('S31'),('S35'),('S37'),('S38'),('S39'),
        ('S40'),('S41'),('S45'),('S47'),('S48'),('S49'),
        ('S50'),('S51'),('S55'),('S57'),('S58'),('S59'),
        ('S60'),('S61'),('S65'),('S67'),('S68'),('S69'),
        ('S70'),('S71'),('S75'),('S77'),('S78'),('S79'),
        ('S80'),('S81'),('S85'),('S87'),('S88'),('S89'),
        ('S90'),('S91'),('S95'),('S97'),('S98'),('S99'),
        ('T00'),('T01'),('T09'),('T11'),('T13'),('T14')
    ) AS t(code)
),

-- รหัสที่ทำให้ตัดออกทั้ง visit (ขึ้นต้นด้วย)
excluded_prefix AS (
    SELECT * FROM (VALUES
        ('W54'),('W55'),('W56'),                                   -- แผลสัตว์กัด
        ('T20'),('T21'),('T22'),('T23'),('T24'),('T25'),('T26'),
        ('T27'),('T28'),('T29'),('T30'),('T31'),('T32'),           -- แผลไฟไหม้น้ำร้อนลวก
        ('S16'),('S46'),('S56'),('S66'),('S76'),('S86'),('S96'),
        ('S390'),('T095'),('T115'),('T135'),('T146')               -- แผลลึกถึงกล้ามเนื้อ/เอ็น
    ) AS t(code)
),

-- ยาปฏิชีวนะชนิดกิน/ฉีดที่เภสัชกรกำหนด (28 รหัส)
atb_drug_list AS (
    SELECT * FROM (VALUES
        ('1000030'),('1000028'),('1580003'),('1000034'),('1610058'),('1483847'),('1483857'),('1530013'),
        ('1000060'),('1460109'),('1510018'),('1000082'),('1483877'),('1530023'),('1540024'),('1000084'),
        ('1000085'),('1670021'),('1670027'),('1000125'),('1000267'),('1000140'),('1640002'),('1000213'),
        ('1500906'),('1680036'),('1430203'),('1000231')
    ) AS t(icode)
),

-- ตัวส่วน: visit ผู้ป่วยนอกที่มีทั้งรหัสแผลและรหัสสาเหตุ และไม่มีรหัสที่ตัดออก
vn_list AS (
    SELECT o.vn
    FROM ovst o
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn
                  AND UPPER(LEFT(dg.icd10, 3)) IN (SELECT code FROM injury_prefix))
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn
                  AND (UPPER(LEFT(dg.icd10, 1)) IN ('V','W') OR UPPER(LEFT(dg.icd10, 3)) BETWEEN 'X00' AND 'X59'))
      AND NOT EXISTS (SELECT 1 FROM ovstdiag dg JOIN excluded_prefix ep ON UPPER(dg.icd10) LIKE ep.code || '%'
                      WHERE dg.vn = o.vn)
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- รายการยาปฏิชีวนะของ visit ในตัวเศษ
drug_info AS (
    SELECT o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM vn_list)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM atb_drug_list)
    GROUP BY o.vn
),

-- จำนวนยา "ทุกชนิด" ต่อ visit - สำหรับ "ยาเฉลี่ยต่อ VISIT ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM vn_list)
      AND op.qty > 0
    GROUP BY op.vn
)

-- ตัวเลขสรุป: SELECT (SELECT COUNT(*) FROM vn_list) AS denominator, (SELECT COUNT(*) FROM drug_info) AS numerator;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    array_to_string(array_agg(DISTINCT od.icd10), ',') AS dx,   -- รหัสแผลและรหัสสาเหตุของ visit
    concat(p.pname, ' ', p.fname, CASE WHEN trim(coalesce(p.lname, '')) <> '' THEN ' xxxx' END) AS fullname,  -- PDPA: ชื่อ + xxxx แทนนามสกุล
    tt.drugitems_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, ' (', attdoc.licenseno, ')') END AS doctor,  -- แพทย์ผู้ตรวจ (attending) = ovst.doctor
    attdoc.code AS doctor_code,
    pt.name AS coverage,
    sp.name AS department,
    kd.department AS room
FROM ovst o
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn
LEFT OUTER JOIN drug_info tt ON tt.vn = o.vn
LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = '01' AND attdoc.active = 'Y'
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
WHERE o.vn IN (SELECT vn FROM vn_list)
  AND (UPPER(LEFT(od.icd10, 3)) IN (SELECT code FROM injury_prefix)
       OR UPPER(LEFT(od.icd10, 1)) IN ('V','W') OR UPPER(LEFT(od.icd10, 3)) BETWEEN 'X00' AND 'X59')
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname, p.lname,
    attdoc.code, attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
