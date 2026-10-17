-- KPI-13: ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine
-- ที่มา: query อ้างอิงจากเภสัชกร (2026-10, ต้นฉบับอยู่ในประวัติ git commit 1dd5498)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/ElderlyLongActingBenzodiazepineQuery.php
-- (ตัดคอลัมน์ CID / ชื่อแพทย์ผู้วินิจฉัย-ผู้สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-17 ให้ตรงกับนิยามของ HDC s_rdu16 - ดู CONTEXT.md
-- "ผู้สูงอายุที่ได้ยากลุ่ม Long-acting Benzodiazepine"):
--   - นับเป็น visit (VN) ทุก visit ตามวันที่มารับบริการ รวม visit ที่ต่อมารับไว้นอนโรงพยาบาล (ไม่กรอง ovst.an)
--   - ตัวส่วน = ทุก visit ของผู้สูงอายุ 65 ปีเต็มขึ้นไป ณ วันที่มารับบริการ ไม่จำกัดโรคหรือยา
--   - ตัวเศษ = visit ในตัวส่วนที่ "จ่าย" Diazepam เม็ด (1000107, 1460009) หรือฉีด (1000108) (qty > 0)
--     target_direction='lower', เป้า 5%
--   - ต่างจากต้นฉบับ 5 จุด:
--       1. อายุ >= 65 ปี (เดิม >= 60) ตามเกณฑ์ของ HDC
--       2. ไม่นับ Clonazepam 1483866 - เภสัชกรตัดออก
--       3. ไม่กรอง o.an IS NULL - HDC นับ visit ที่ต่อมารับไว้นอนโรงพยาบาลด้วย
--       4. อายุคิดจาก patient.birthday แทน vn_stat.age_y (role rdu_readonly ไม่มีสิทธิ์อ่าน vn_stat)
--       5. เพิ่มตัวส่วน, นับเฉพาะ qty > 0 และตัดเคสทดสอบ
--   - drugitems_name / atb_cost มีค่าเฉพาะ visit ในตัวเศษ (ว่าง = ไม่อยู่ในตัวเศษ - สัญญาข้อมูลรายกรณีของทุก KPI)
--     atb_cost = มูลค่ายา Diazepam ของ visit นั้น จาก opitemrece.sum_price
--   - dx = ทุกการวินิจฉัยของ visit (ตัวชี้วัดนี้ไม่มีเงื่อนไขการวินิจฉัย)
--   - total_drug_items นับยา "ทุกชนิด" ต่อ visit, coverage / department / room เหมือน KPI04
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 31,517 / ตัวเศษ 97 (HDC 31,453 / 93)
--     ปีงบ 2568: ตัวส่วน 25,956 / ตัวเศษ 127 (HDC 25,951 / 118)
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยากลุ่ม Long-acting Benzodiazepine ที่เภสัชกรกำหนด
long_acting_bzd_drugs AS (
    SELECT * FROM (VALUES
        ('1000107'),   -- (5 mg)Diazepam (สีเหลือง) ยาเม็ด
        ('1460009'),   -- DiazePAM (สีขาว) 2 mg ยาเม็ด
        ('1000108')    -- DIAZEPAM INJ 10 mg/2ml ยาฉีด
    ) AS t(icode)
),

-- ตัวส่วน: ทุก visit ของผู้สูงอายุ 65 ปีขึ้นไป
vn_list AS (
    SELECT o.vn
    FROM ovst o
    INNER JOIN patient pa ON pa.hn = o.hn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND date_part('year', age(o.vstdate, pa.birthday)) >= 65
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- รายการยา Long-acting Benzodiazepine ของ visit ในตัวเศษ
drug_info AS (
    SELECT o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM vn_list)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM long_acting_bzd_drugs)
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
WHERE o.vn IN (SELECT vn FROM vn_list)
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname, p.lname,
    attdoc.code, attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
