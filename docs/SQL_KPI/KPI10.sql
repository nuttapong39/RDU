-- KPI-10: ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่นเพื่อควบคุมระดับน้ำตาลโดยไม่มีข้อห้ามใช้
-- ที่มา: query อ้างอิงจากเภสัชกรของโรงพยาบาล (2026-10, ต้นฉบับอยู่ในประวัติ git commit 0f5ca16)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/ChronicDisease/MetforminDiabetesQuery.php และนิยาม HDC (s_rdu12)
-- (ตัดคอลัมน์ CID / อายุ / แพทย์ผู้วินิจฉัย-ผู้สั่งยา / แผนกที่สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-16 - ดู CONTEXT.md "ผู้ป่วยเบาหวานชนิดที่ 2 ที่ใช้ Metformin โดยไม่มีข้อห้ามใช้"):
--   - นับเป็น "ผู้ป่วย" (HN ไม่ซ้ำ) ไม่ใช่ visit - ต้นฉบับแสดงเป็น visit ที่วินิจฉัยเบาหวานและได้ Metformin (ไม่มีตัวส่วน)
--   - ตัวส่วน = ผู้ป่วยที่ผ่านครบ 3 ข้อ
--       1. มี visit ผู้ป่วยนอก (o.an IS NULL) วินิจฉัย E11* (เบาหวานชนิดที่ 2) ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
--          (ไม่นับ E10 ชนิดที่ 1 / E13 ชนิดอื่น / E14 ไม่ระบุชนิด ซึ่งต้นฉบับนับ)
--       2. ได้ยาลดระดับน้ำตาล 7 รหัส (qty > 0) อย่างน้อย 1 ชนิด ใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
--          (ผู้ที่คุมด้วยอาหารอย่างเดียวไม่อยู่ในคำถาม "ใช้ Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่น")
--       3. ไม่มีข้อห้ามใช้: ผล eGFR (lab_items_code 200) ล่าสุด ในช่วง 12 เดือนที่สิ้นสุดวันสุดท้ายของช่วงเวลา >= 30
--          ผู้ที่ไม่มีผล eGFR ในช่วงนั้นไม่อยู่ในตัวส่วน / ผลจากแล็บผู้ป่วยนอกหรือตอนนอนโรงพยาบาลก็ได้
--          อ่านตัวเลขหน้าผล ("19.49  R" = 19.49, ">90" = 90, "<30" = ต่ำกว่า 30) ผลที่ไม่มีตัวเลข (เช่น ข้อความอายุไม่ถึง 18 ปี) ข้ามไปใช้ผลก่อนหน้า
--          ต้องมีสิทธิ์ SELECT ตาราง lab_order (GRANT ให้ rdu_readonly แล้ว 2026-10-16)
--   - ตัวเศษ = ผู้ป่วยในตัวส่วนที่ได้ Metformin (qty > 0) ใน visit ผู้ป่วยนอก "ครั้งใดก็ได้" ในช่วงเวลาเดียวกัน
--     ต่างจากต้นฉบับที่ดูเฉพาะ visit ที่วินิจฉัยเบาหวาน - target_direction='higher', เป้า >= 80%
--   - ต่างจากต้นฉบับ: นับเฉพาะ qty > 0 (ไม่นับรายการคืนยา) + ตัดเคสทดสอบ
--   - 1 แถวต่อผู้ป่วย ใช้ "visit ตัวแทน" = visit ล่าสุดที่ได้ Metformin (ถ้าอยู่ในตัวเศษ) มิฉะนั้นคือ visit เบาหวานครั้งล่าสุด
--     แพทย์ผู้ตรวจ / สิทธิ์ / แผนก / ห้อง มาจาก visit ตัวแทน
--   - dx = รหัส E11* ของผู้ป่วยในช่วงเวลานั้น (visit ตัวแทนอาจไม่มีรหัสเบาหวาน)
--   - drugitems_name / atb_cost = Metformin ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น (ว่าง = ไม่อยู่ในตัวเศษ)
--   - egfr / egfr_date = ผล eGFR ล่าสุดที่ทำให้อยู่ในตัวส่วน
--   - ยอดไตรมาส / ทั้งปี ต้องนับคนไม่ซ้ำตลอดช่วง ห้ามรวมยอดรายเดือน
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 1478 / ตัวเศษ 1400 (94.7%)
--     HDC s_rdu12: 1425 / 1482 (96.2%) / ถ้าไม่มีเงื่อนไขข้อ 2 ได้ 1400 / 1665 (84.1%)
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยาลดระดับน้ำตาล (เภสัชกรยืนยัน 2026-10-16) - Metformin คือยาที่วัด
glucose_lowering_drug_list AS (
    SELECT * FROM (VALUES
        ('1000184'),   -- metformin 500 mg
        ('1019001'),   -- glipizide 5 mg
        ('1680038'),   -- pioglitazone 30 mg
        ('1510002'),   -- Penfill Mixtard
        ('1481213'),   -- regular insulin (คิดราคาต่อ unit)
        ('1590023'),   -- NPH vial (insulin ฉีดที่บ้าน)
        ('1483896')    -- regular insulin 1000 iu
    ) AS t(icode)
),

-- visit ผู้ป่วยนอกที่วินิจฉัยเบาหวานชนิดที่ 2 (ตำแหน่งใดก็ได้)
dm_visits AS (
    SELECT o.vn, o.hn, o.vstdate
    FROM ovst o
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND dg.icd10 LIKE 'E11%')
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- ผู้ป่วยที่ได้ยาลดระดับน้ำตาลใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
glucose_lowering AS (
    SELECT DISTINCT o.hn
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND op.qty > 0
      AND op.icode IN (SELECT icode FROM glucose_lowering_drug_list)
),

-- ผล eGFR ล่าสุดที่อ่านเป็นตัวเลขได้ ในช่วง 12 เดือนที่สิ้นสุดวันสุดท้ายของช่วงเวลา
latest_egfr AS (
    SELECT DISTINCT ON (lh.hn)
           lh.hn,
           substring(lo.lab_order_result FROM '^\s*[<>]{0,1}\s*([0-9]+(\.[0-9]+){0,1})')::numeric
               - CASE WHEN lo.lab_order_result ~ '^\s*<' THEN 0.01 ELSE 0 END AS egfr,   -- "<30" = ต่ำกว่า 30
           lh.order_date AS egfr_date
    FROM lab_head lh
    INNER JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
    CROSS JOIN date_range dr
    WHERE lo.lab_items_code = 200
      AND lh.order_date BETWEEN (dr.ds2 - INTERVAL '12 months' + INTERVAL '1 day')::date AND dr.ds2
      AND lh.hn IN (SELECT hn FROM dm_visits)
      AND lo.lab_order_result ~ '^\s*[<>]{0,1}\s*[0-9]'
    ORDER BY lh.hn, lh.order_date DESC, lh.lab_order_number DESC
),

-- ตัวส่วน: เบาหวานชนิดที่ 2 + ได้ยาลดระดับน้ำตาล + eGFR ล่าสุด >= 30
cohort AS (
    SELECT e.hn, e.egfr, e.egfr_date
    FROM latest_egfr e
    WHERE e.egfr >= 30
      AND e.hn IN (SELECT hn FROM glucose_lowering)
),

-- Metformin ที่จ่ายจริงใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
metformin_dispensed AS (
    SELECT o.vn, o.hn, o.vstdate, op.icode, op.sum_price
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND op.qty > 0
      AND op.icode = '1000184'
),

-- visit ตัวแทนของผู้ป่วยแต่ละคน = visit ล่าสุดที่ได้ Metformin ถ้ามี มิฉะนั้นคือ visit เบาหวานครั้งล่าสุด
representative_visit AS (
    SELECT DISTINCT ON (hn) hn, vn
    FROM (
        SELECT m.hn, m.vn, m.vstdate, 1 AS priority
        FROM metformin_dispensed m
        WHERE m.hn IN (SELECT hn FROM cohort)
        UNION ALL
        SELECT d.hn, d.vn, d.vstdate, 2 AS priority
        FROM dm_visits d
        WHERE d.hn IN (SELECT hn FROM cohort)
    ) candidates
    ORDER BY hn, priority, vstdate DESC, vn DESC
),

-- รหัสเบาหวานชนิดที่ 2 ของผู้ป่วยในช่วงเวลานั้น
dm_codes AS (
    SELECT d.hn, array_to_string(array_agg(DISTINCT dg.icd10 ORDER BY dg.icd10), ',') AS dx
    FROM dm_visits d
    INNER JOIN ovstdiag dg ON dg.vn = d.vn AND dg.icd10 LIKE 'E11%'
    WHERE d.hn IN (SELECT hn FROM cohort)
    GROUP BY d.hn
),

-- Metformin ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น
drug_info AS (
    SELECT m.hn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(m.sum_price) AS atb_cost
    FROM metformin_dispensed m
    INNER JOIN drugitems d ON d.icode = m.icode
    WHERE m.hn IN (SELECT hn FROM cohort)
    GROUP BY m.hn
),

-- จำนวนยา "ทุกชนิด" ของ visit ตัวแทน
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM representative_visit)
      AND op.qty > 0
    GROUP BY op.vn
)

-- ตัวเลขสรุป:
-- SELECT COUNT(DISTINCT hn) AS denominator,
--        COUNT(DISTINCT hn) FILTER (WHERE hn IN (SELECT hn FROM metformin_dispensed)) AS numerator
-- FROM cohort;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    dc.dx,
    concat(p.pname, ' ', p.fname, CASE WHEN trim(coalesce(p.lname, '')) <> '' THEN ' xxxx' END) AS fullname,  -- PDPA: ชื่อ + xxxx แทนนามสกุล
    tt.drugitems_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, ' (', attdoc.licenseno, ')') END AS doctor,  -- แพทย์ผู้ตรวจของ visit ตัวแทน
    attdoc.code AS doctor_code,
    pt.name AS coverage,
    sp.name AS department,
    kd.department AS room,
    c.egfr,
    c.egfr_date
FROM representative_visit rv
INNER JOIN ovst o ON o.vn = rv.vn
INNER JOIN cohort c ON c.hn = rv.hn
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN dm_codes dc ON dc.hn = o.hn
LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = '01' AND attdoc.active = 'Y'
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
ORDER BY o.vstdate, o.hn, o.vn;
