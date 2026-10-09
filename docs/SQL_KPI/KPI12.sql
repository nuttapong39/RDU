-- KPI-12: ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ RAS Blockade 2 ชนิดร่วมกันในการรักษา
-- ที่มา: query อ้างอิงจากทีม IT/รายงานของโรงพยาบาล (2026-10, ต้นฉบับอยู่ในประวัติ git commit d0c6675)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/RasDualBlockadeQuery.php
-- (ตัดคอลัมน์ CID / ชื่อแพทย์ผู้วินิจฉัย-ผู้สั่งยา / total_ena ที่ซ้ำกับ total_ras ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-09 - ดู CONTEXT.md "RAS blockade 2 ชนิดร่วมกัน"):
--   - ตัวส่วน = visit ผู้ป่วยนอกที่มีวินิจฉัย I10 (ตำแหน่งใดก็ได้) และ "จ่าย" ยา RAS อย่างน้อย 1 ชนิด (qty > 0)
--   - ตัวเศษ = visit ในตัวส่วนที่จ่ายยา RAS ตั้งแต่ 2 "ตัวยา" ที่ต่างกัน (target_direction='lower', เป้า 0%)
--   - ต่างจากต้นฉบับ 3 จุด:
--       1. นับ "ตัวยา" ไม่ใช่ "รหัสยา" - Enalapril 5 mg + 20 mg ใน visit เดียวกัน = 1 ชนิด (การปรับขนาดยา)
--          ส่วน Enalapril + Captopril (ACEI 2 ตัว) หรือ ACEI + ARB = 2 ชนิด
--       2. นับเฉพาะ qty > 0 - การคืน/ยกเลิกยาตอนเปลี่ยนยาในวันเดียวกันไม่ใช่การใช้ร่วมกัน
--          (ปีงบ 2569: ต้นฉบับ 31 visit -> เหลือ 8 visit หลังตัดการคืนยาและนับตามตัวยา)
--       3. เพิ่ม Captopril 25 mg และ Losartan รหัสเก่าที่ยกเลิกแล้ว 2 ตัว (ให้ปีงบเก่านับถูก) + ตัดเคสทดสอบ
--   - drugitems_name / atb_cost มีค่าเฉพาะ visit ในตัวเศษ (ว่าง = ไม่อยู่ในตัวเศษ - สัญญาข้อมูลรายกรณีของทุก KPI)
--     atb_cost = มูลค่ายา RAS ของ visit นั้น จาก opitemrece.sum_price
--   - total_drug_items นับยา "ทุกชนิด" ต่อ visit, coverage / department / room เหมือน KPI04
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 9,200 / ตัวเศษ 8
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยากลุ่ม RAS blockade ในบัญชียาของโรงพยาบาล: icode -> ตัวยา (ingredient)
ras_drug_list AS (
    SELECT * FROM (VALUES
        ('1510003', 'enalapril'),   -- Enalapril 5 mg (ACEI)
        ('1000122', 'enalapril'),   -- Enalapril 20 mg (ACEI)
        ('1530006', 'captopril'),   -- Captopril 25 mg (ACEI)
        ('1670015', 'losartan'),    -- Losartan 50 mg (ARB)
        ('1540023', 'losartan'),    -- Losartan 50 mg (ARB, รหัสยกเลิกแล้ว)
        ('1570019', 'losartan')     -- Losartan(a) 50 mg (ARB, รหัสยกเลิกแล้ว)
    ) AS t(icode, ingredient)
),

-- ตัวส่วน: visit ความดันโลหิตสูงทั่วไปที่จ่ายยา RAS + จำนวนตัวยา RAS ที่ต่างกันใน visit นั้น
ras_visits AS (
    SELECT o.vn, COUNT(DISTINCT r.ingredient) AS ras_drugs
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    INNER JOIN ras_drug_list r ON r.icode = op.icode
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND dg.icd10 LIKE 'I10%')
      AND op.qty > 0
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
    GROUP BY o.vn
),

-- รายการยา RAS ของ visit ในตัวเศษ (>= 2 ตัวยา)
drug_info AS (
    SELECT o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM ras_visits WHERE ras_drugs >= 2)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM ras_drug_list)
    GROUP BY o.vn
),

-- จำนวนยา "ทุกชนิด" ต่อ visit - สำหรับ "ยาเฉลี่ยต่อ VISIT ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM ras_visits)
      AND op.qty > 0
    GROUP BY op.vn
)

-- ตัวเลขสรุป: SELECT COUNT(*) AS denominator, COUNT(*) FILTER (WHERE ras_drugs >= 2) AS numerator FROM ras_visits;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    array_to_string(array_agg(DISTINCT od.icd10), ',') AS dx,
    concat(p.pname, p.fname, ' xxxx') AS fullname,  -- PDPA: ชื่อเต็ม + xxxx แทนนามสกุล ไม่แสดงนามสกุลจริง
    tt.drugitems_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    concat(attdoc.name, ' (', attdoc.licenseno, ')') AS doctor,  -- แพทย์ผู้ตรวจ (attending) = ovst.doctor
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
WHERE o.vn IN (SELECT vn FROM ras_visits)
  AND od.icd10 LIKE 'I10%'
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname,
    attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
