-- KPI-07: ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน
-- ที่มา: query อ้างอิงจากเภสัชกรของโรงพยาบาล (2026-10, ต้นฉบับอยู่ในประวัติ git commit 1d973fe)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/NsaidDuplicationQuery.php
-- (ตัดคอลัมน์ CID / อายุ / แพทย์ผู้วินิจฉัย-ผู้สั่งยา / แผนกที่สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA
--  และใช้แพทย์ผู้ตรวจ + แผนกของ visit แบบเดียวกับ KPI อื่น)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-15 - ดู CONTEXT.md "NSAIDs ซ้ำซ้อน"):
--   - รายการยา NSAIDs 6 รหัส กำหนดโดยเภสัชกร ตามบัญชียาที่โรงพยาบาลใช้ปัจจุบัน
--     (ไม่นับ Norgesic = Orphenadrine+Paracetamol และไม่นับ Aspirin)
--   - ตัวส่วน = visit ผู้ป่วยนอก (o.an IS NULL) ที่ "จ่าย" NSAIDs อย่างน้อย 1 รายการ (qty > 0) ไม่จำกัดการวินิจฉัย
--   - ตัวเศษ = visit ในตัวส่วนที่จ่าย NSAIDs ตั้งแต่ 2 "รายการ" (รหัสยา) ที่ต่างกัน
--     (target_direction='lower', เป้า <= 5%)
--   - นับตามรหัสยา ไม่ใช่ตามตัวยา (ต่างจาก KPI12): Diclofenac INJ + Diclofenac TAB ใน visit เดียวกัน = ซ้ำซ้อน
--   - ต่างจากต้นฉบับ: นับเฉพาะ qty > 0 (ไม่นับรายการคืนยา) + ตัดเคสทดสอบ
--   - drugitems_name / atb_cost มีค่าเฉพาะ visit ในตัวเศษ (ว่าง = ไม่อยู่ในตัวเศษ - สัญญาข้อมูลรายกรณีของทุก KPI)
--     atb_cost = มูลค่ายา NSAIDs ของ visit นั้น จาก opitemrece.sum_price
--   - dx = ทุกการวินิจฉัยของ visit (KPI นี้ไม่มีเงื่อนไขการวินิจฉัย)
--   - total_drug_items นับยา "ทุกชนิด" ต่อ visit, coverage / department / room เหมือน KPI04
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 3,794 / ตัวเศษ 67 (1.8%)
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยากลุ่ม NSAIDs ที่โรงพยาบาลใช้ (เภสัชกรกำหนด)
nsaid_drug_list AS (
    SELECT * FROM (VALUES
        ('1610036'),   -- Ibuprofen 200 mg
        ('1483891'),   -- Ibuprofen 400 mg
        ('1483892'),   -- Ibuprofen SUSP 100 mg/5 ml
        ('1610052'),   -- Diclofenac INJ 75 mg/3 ml
        ('1000110'),   -- Diclofenac TAB 25 mg
        ('1000182')    -- Mefenamic acid 250 mg
    ) AS t(icode)
),

-- ตัวส่วน: visit ผู้ป่วยนอกที่จ่าย NSAIDs + จำนวนรายการ NSAIDs ที่ต่างกันใน visit นั้น
nsaid_visits AS (
    SELECT o.vn, COUNT(DISTINCT op.icode) AS nsaid_items
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND op.qty > 0
      AND op.icode IN (SELECT icode FROM nsaid_drug_list)
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
    GROUP BY o.vn
),

-- รายการยา NSAIDs ของ visit ในตัวเศษ (>= 2 รายการ)
drug_info AS (
    SELECT o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM nsaid_visits WHERE nsaid_items >= 2)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM nsaid_drug_list)
    GROUP BY o.vn
),

-- จำนวนยา "ทุกชนิด" ต่อ visit - สำหรับ "ยาเฉลี่ยต่อ VISIT ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM nsaid_visits)
      AND op.qty > 0
    GROUP BY op.vn
)

-- ตัวเลขสรุป: SELECT COUNT(*) AS denominator, COUNT(*) FILTER (WHERE nsaid_items >= 2) AS numerator FROM nsaid_visits;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    array_to_string(array_agg(DISTINCT od.icd10), ',') AS dx,
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
WHERE o.vn IN (SELECT vn FROM nsaid_visits)
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname, p.lname,
    attdoc.code, attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
