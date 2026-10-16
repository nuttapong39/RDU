-- KPI-09: ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs
-- ที่มา: query อ้างอิงจากเภสัชกรของโรงพยาบาล (2026-10, ต้นฉบับอยู่ในประวัติ git commit 4cd7925)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/CkdNsaidQuery.php
-- (ตัดคอลัมน์ CID / อายุ / แพทย์ผู้วินิจฉัย-ผู้สั่งยา / แผนกที่สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-16 - ดู CONTEXT.md "ผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้ NSAIDs"):
--   - นับเป็น "ผู้ป่วย" (HN ไม่ซ้ำ) ไม่ใช่ visit - ต้นฉบับแสดงเป็น visit ที่วินิจฉัยโรคไตและได้ NSAIDs
--   - ตัวส่วน = ผู้ป่วยที่มี visit ผู้ป่วยนอก (o.an IS NULL) วินิจฉัย N183 / N184 / N185 ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
--     (ไม่นับ N182 ระดับ 2 และ N189 ไม่ระบุระดับ / ไม่มีเงื่อนไขว่าต้องได้ยาอย่างน้อย 1 ชนิด)
--   - ตัวเศษ = ผู้ป่วยในตัวส่วนที่ได้ NSAIDs (qty > 0) ใน visit ผู้ป่วยนอก "ครั้งใดก็ได้" ในช่วงเวลาเดียวกัน
--     ต่างจากต้นฉบับที่ดูเฉพาะ visit ที่วินิจฉัยโรคไต - เพื่อให้เห็น NSAIDs ที่ได้จากห้องตรวจอื่น เช่น ห้องฉุกเฉิน
--     target_direction='lower', เป้า <= 10%
--   - ยา NSAIDs 6 รหัส ชุดเดียวกับ KPI07 (docs/SQL_KPI/KPI7.sql) - แก้ต้องแก้พร้อมกัน
--   - ต่างจากต้นฉบับ: นับเฉพาะ qty > 0 (ไม่นับรายการคืนยา) + ตัดเคสทดสอบ
--   - 1 แถวต่อผู้ป่วย ใช้ "visit ตัวแทน" = visit ล่าสุดที่ได้ NSAIDs (ถ้าอยู่ในตัวเศษ) มิฉะนั้นคือ visit โรคไตครั้งล่าสุด
--     แพทย์ผู้ตรวจ / สิทธิ์ / แผนก / ห้อง มาจาก visit ตัวแทน
--   - dx = รหัสระดับโรคไต (N183/N184/N185) ของผู้ป่วยในช่วงเวลานั้น (visit ตัวแทนอาจไม่มีรหัสโรคไต)
--   - drugitems_name / atb_cost = NSAIDs ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น (ว่าง = ไม่อยู่ในตัวเศษ)
--   - ยอดไตรมาส / ทั้งปี ต้องนับคนไม่ซ้ำตลอดช่วง ห้ามรวมยอดรายเดือน
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 892 / ตัวเศษ 23 (2.6%)
--     ถ้านับเฉพาะ visit ที่วินิจฉัยโรคไตแบบต้นฉบับได้ 7 คน - HDC s_rdu14: 7 / 714
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยา NSAIDs ที่โรงพยาบาลใช้ (เภสัชกรกำหนด - ชุดเดียวกับ KPI07)
nsaid_drug_list AS (
    SELECT * FROM (VALUES
        ('1610036'),   -- Ibuprofen 200 mg
        ('1483891'),   -- Ibuprofen 400 mg
        ('1483892'),   -- Ibuprofen suspension 100 mg/5 ml
        ('1610052'),   -- Diclofenac injection 75 mg/3 ml
        ('1000110'),   -- Diclofenac 25 mg tablet
        ('1000182')    -- Mefenamic acid 250 mg
    ) AS t(icode)
),

-- visit ผู้ป่วยนอกที่วินิจฉัยโรคไตเรื้อรังระดับ 3-5 (ตำแหน่งใดก็ได้)
ckd_visits AS (
    SELECT o.vn, o.hn, o.vstdate
    FROM ovst o
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND dg.icd10 IN ('N183','N184','N185'))
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- NSAIDs ที่จ่ายจริงใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
nsaid_dispensed AS (
    SELECT o.vn, o.hn, o.vstdate, op.icode, op.sum_price
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND op.qty > 0
      AND op.icode IN (SELECT icode FROM nsaid_drug_list)
),

-- visit ตัวแทนของผู้ป่วยแต่ละคน = visit ล่าสุดที่ได้ NSAIDs ถ้ามี มิฉะนั้นคือ visit โรคไตครั้งล่าสุด
representative_visit AS (
    SELECT DISTINCT ON (hn) hn, vn
    FROM (
        SELECT n.hn, n.vn, n.vstdate, 1 AS priority
        FROM nsaid_dispensed n
        WHERE n.hn IN (SELECT hn FROM ckd_visits)
        UNION ALL
        SELECT c.hn, c.vn, c.vstdate, 2 AS priority
        FROM ckd_visits c
    ) candidates
    ORDER BY hn, priority, vstdate DESC, vn DESC
),

-- รหัสระดับโรคไตของผู้ป่วยในช่วงเวลานั้น
ckd_stage AS (
    SELECT c.hn, array_to_string(array_agg(DISTINCT dg.icd10 ORDER BY dg.icd10), ',') AS dx
    FROM ckd_visits c
    INNER JOIN ovstdiag dg ON dg.vn = c.vn AND dg.icd10 IN ('N183','N184','N185')
    GROUP BY c.hn
),

-- NSAIDs ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น
drug_info AS (
    SELECT n.hn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(n.sum_price) AS atb_cost
    FROM nsaid_dispensed n
    INNER JOIN drugitems d ON d.icode = n.icode
    WHERE n.hn IN (SELECT hn FROM representative_visit)
    GROUP BY n.hn
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
--        COUNT(DISTINCT hn) FILTER (WHERE hn IN (SELECT hn FROM nsaid_dispensed)) AS numerator
-- FROM ckd_visits;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    cs.dx,
    concat(p.pname, ' ', p.fname, CASE WHEN trim(coalesce(p.lname, '')) <> '' THEN ' xxxx' END) AS fullname,  -- PDPA: ชื่อ + xxxx แทนนามสกุล
    tt.drugitems_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, ' (', attdoc.licenseno, ')') END AS doctor,  -- แพทย์ผู้ตรวจของ visit ตัวแทน
    attdoc.code AS doctor_code,
    pt.name AS coverage,
    sp.name AS department,
    kd.department AS room
FROM representative_visit rv
INNER JOIN ovst o ON o.vn = rv.vn
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN ckd_stage cs ON cs.hn = o.hn
LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = '01' AND attdoc.active = 'Y'
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
ORDER BY o.vstdate, o.hn, o.vn;
