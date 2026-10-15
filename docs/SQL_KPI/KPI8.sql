-- KPI-08: ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid
-- ที่มา: query อ้างอิงจากเภสัชกรของโรงพยาบาล (2026-10, ต้นฉบับอยู่ในประวัติ git commit 531fd17)
-- ปรับให้ตรงกับสิ่งที่ implement จริงใน app/Queries/Kpi/PatientSafety/AsthmaInhaledCorticosteroidQuery.php
-- (ตัดคอลัมน์ CID / อายุ / แพทย์ผู้วินิจฉัย-ผู้สั่งยา / แผนกที่สั่งยา ออก - CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ (ตกลงกับผู้ใช้ 2026-10-15 - ดู CONTEXT.md "ผู้ป่วยโรคหืดที่ได้ ICS"):
--   - นับเป็น "ผู้ป่วย" (HN ไม่ซ้ำ) ไม่ใช่ visit - ต้นฉบับแสดงเป็น visit ที่วินิจฉัยหืดและได้ ICS
--   - ตัวส่วน = ผู้ป่วยที่มี visit ผู้ป่วยนอก (o.an IS NULL) วินิจฉัย J45% / J46% ตำแหน่งใดก็ได้ ในช่วงเวลานั้น
--   - ตัวเศษ = ผู้ป่วยในตัวส่วนที่ได้ ICS (qty > 0) ใน visit ผู้ป่วยนอก "ครั้งใดก็ได้" ในช่วงเวลาเดียวกัน
--     (ไม่จำเป็นต้องเป็น visit ที่วินิจฉัยหืด) - target_direction='higher', เป้า >= 80%
--   - ยา ICS 4 รหัส กำหนดโดยเภสัชกร (รวมรหัส Seretide เก่าที่ยกเลิกแล้ว)
--   - ต่างจากต้นฉบับ: นับเฉพาะ qty > 0 (ไม่นับรายการคืนยา) + ตัดเคสทดสอบ
--   - 1 แถวต่อผู้ป่วย ใช้ "visit โรคหืดครั้งล่าสุด" ในช่วงเวลานั้นเป็นตัวแทน (VN, วันที่, แพทย์ผู้ตรวจ, สิทธิ์, แผนก, ห้อง, dx)
--   - drugitems_name / atb_cost = ICS ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น (ว่าง = ไม่อยู่ในตัวเศษ)
--   - ยอดไตรมาส / ทั้งปี ต้องนับคนไม่ซ้ำตลอดช่วง ห้ามรวมยอดรายเดือน (คนเดียวมาหลายเดือน)
--   - ตรวจกับ HOSxP ปีงบ 2569 (2025-10-01 ถึง 2026-09-30): ตัวส่วน 87 / ตัวเศษ 71 (81.6%) - HDC s_rdu15: 72 / 87
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2025-10-01' AS ds1,
           DATE '2026-09-30' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- ยา Inhaled Corticosteroid ที่โรงพยาบาลใช้ (เภสัชกรกำหนด)
ics_drug_list AS (
    SELECT * FROM (VALUES
        ('1483852'),   -- Budesonide MDI 200 mcg/dose
        ('1590017'),   -- Seretide Evohaler (fluticasone + salmeterol) 25+250 mcg/dose
        ('1540020'),   -- Seretide Accuhaler 50+250 mcg/dose
        ('1530002')    -- Seretide Accuhaler (รหัสเก่า ยกเลิกแล้ว)
    ) AS t(icode)
),

-- visit ผู้ป่วยนอกที่วินิจฉัยโรคหืด (J45 / J46 ตำแหน่งใดก็ได้)
asthma_visits AS (
    SELECT o.vn, o.hn, o.vstdate
    FROM ovst o
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND (dg.icd10 LIKE 'J45%' OR dg.icd10 LIKE 'J46%'))
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- ICS ที่จ่ายจริงใน visit ผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน
ics_dispensed AS (
    SELECT o.hn, op.icode, op.sum_price
    FROM ovst o
    INNER JOIN opitemrece op ON op.vn = o.vn
    CROSS JOIN date_range dr
    WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NULL
      AND op.qty > 0
      AND op.icode IN (SELECT icode FROM ics_drug_list)
),

-- visit ตัวแทนของผู้ป่วยแต่ละคน = visit โรคหืดครั้งล่าสุด
latest_visit AS (
    SELECT DISTINCT ON (hn) hn, vn
    FROM asthma_visits
    ORDER BY hn, vstdate DESC, vn DESC
),

-- ICS ทั้งหมดที่ผู้ป่วยได้ในช่วงเวลานั้น
drug_info AS (
    SELECT i.hn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(i.sum_price) AS atb_cost
    FROM ics_dispensed i
    INNER JOIN drugitems d ON d.icode = i.icode
    WHERE i.hn IN (SELECT hn FROM latest_visit)
    GROUP BY i.hn
),

-- จำนวนยา "ทุกชนิด" ของ visit ตัวแทน - สำหรับ "ยาเฉลี่ยต่อผู้ป่วย ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM latest_visit)
      AND op.qty > 0
    GROUP BY op.vn
)

-- ตัวเลขสรุป:
-- SELECT COUNT(DISTINCT hn) AS denominator,
--        COUNT(DISTINCT hn) FILTER (WHERE hn IN (SELECT hn FROM ics_dispensed)) AS numerator
-- FROM asthma_visits;

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    array_to_string(array_agg(DISTINCT od.icd10), ',') AS dx,
    concat(p.pname, ' ', p.fname, CASE WHEN trim(coalesce(p.lname, '')) <> '' THEN ' xxxx' END) AS fullname,  -- PDPA: ชื่อ + xxxx แทนนามสกุล
    tt.drugitems_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, ' (', attdoc.licenseno, ')') END AS doctor,  -- แพทย์ผู้ตรวจของ visit ตัวแทน
    attdoc.code AS doctor_code,
    pt.name AS coverage,
    sp.name AS department,
    kd.department AS room
FROM latest_visit lv
INNER JOIN ovst o ON o.vn = lv.vn
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn AND (od.icd10 LIKE 'J45%' OR od.icd10 LIKE 'J46%')
LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = '01' AND attdoc.active = 'Y'
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname, p.lname,
    attdoc.code, attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
