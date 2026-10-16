-- KPI-11: ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็นโรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating
-- ที่มา: query อ้างอิงจากเภสัชกร (2026-10, ต้นฉบับอยู่ในประวัติ git commit 2fcdb72)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/ChildRespiratoryNonSedatingQuery.php
-- (ตัดคอลัมน์ CID / ชื่อแพทย์ผู้วินิจฉัย-ผู้สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-16 - ดู CONTEXT.md "เด็กติดเชื้อทางเดินหายใจที่ได้ยาแก้แพ้ชนิด non-sedating"):
--   - นับเป็น visit (VN) ผู้ป่วยนอกเท่านั้น (ovst.an IS NULL)
--   - ตัวส่วน = visit ของเด็กอายุต่ำกว่า 15 ปีเต็ม ณ วันที่มารับบริการ ที่มีวินิจฉัย J0x / J20 / J21 / J22
--     (ตำแหน่งใดก็ได้) ไม่ว่าจะได้ยาอะไร
--   - ตัวเศษ = visit ในตัวส่วนที่ "จ่าย" Cetirizine ยาน้ำ (1510009) หรือยาเม็ด (1560013) (qty > 0)
--     target_direction='lower', เป้า 20% ตามเกณฑ์ผ่านของ HDC - ตารางสรุป s_rdu21 ตัวชี้วัดที่ 12 (ตัวส่วน b12 =
--     ยอดรวมของตาราง s_rdu18 ซึ่งเป็นตารางรายเดือนของตัวชี้วัดนี้) ผ่านสูงสุด 19.78% / ไม่ผ่านต่ำสุด 20.27%
--   - ต่างจากต้นฉบับ 3 จุด:
--       1. อายุคิดจาก patient.birthday ณ วันที่มารับบริการ แทน vn_stat.age_y
--          (role rdu_readonly ไม่มีสิทธิ์อ่าน vn_stat)
--       2. เพิ่มตัวส่วน - ต้นฉบับเป็นรายการตัวเศษอย่างเดียว
--       3. นับเฉพาะ qty > 0 และตัดเคสทดสอบ
--   - นับยาเม็ดด้วยแม้ชื่อตัวชี้วัดเขียนว่า "ยาน้ำ" (เภสัชกรกำหนดรายการยาเอง - เด็กโตได้ยาเม็ด)
--   - drugitems_name / atb_cost มีค่าเฉพาะ visit ในตัวเศษ (ว่าง = ไม่อยู่ในตัวเศษ - สัญญาข้อมูลรายกรณีของทุก KPI)
--     atb_cost = มูลค่ายา Cetirizine ของ visit นั้น จาก opitemrece.sum_price
--   - total_drug_items นับยา "ทุกชนิด" ต่อ visit, coverage / department / room เหมือน KPI04
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 476 / ตัวเศษ 58
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยาแก้แพ้ชนิด non-sedating ที่เภสัชกรกำหนด
nonsed_drug_list AS (
    SELECT * FROM (VALUES
        ('1510009'),   -- CETIRIZINE SYR. 5 mg/5ml (ยาน้ำ)
        ('1560013')    -- Cetirizine 10 mg (ยาเม็ด)
    ) AS t(icode)
),

-- ตัวส่วน: visit ผู้ป่วยนอกเด็กอายุต่ำกว่า 15 ปี ที่วินิจฉัยโรคติดเชื้อทางเดินหายใจ
vn_list AS (
    SELECT o.vn
    FROM ovst o
    INNER JOIN patient pa ON pa.hn = o.hn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND date_part('year', age(o.vstdate, pa.birthday)) < 15
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn
                  AND (dg.icd10 LIKE 'J0%' OR dg.icd10 LIKE 'J20%' OR dg.icd10 LIKE 'J21%' OR dg.icd10 LIKE 'J22%'))
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- รายการยา non-sedating ของ visit ในตัวเศษ
drug_info AS (
    SELECT o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM vn_list)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM nonsed_drug_list)
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
  AND (od.icd10 LIKE 'J0%' OR od.icd10 LIKE 'J20%' OR od.icd10 LIKE 'J21%' OR od.icd10 LIKE 'J22%')
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname, p.lname,
    attdoc.code, attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
