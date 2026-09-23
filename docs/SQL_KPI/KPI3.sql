-- KPI-03: ร้อยละการใช้ยาปฏิชีวนะในหญิงคลอดปกติครบกำหนด
-- ที่มา: query อ้างอิงจากทีม IT/รายงานของโรงพยาบาล (2026-09) ปรับให้ตรงกับสิ่งที่ implement จริงใน
-- app/Queries/Kpi/Antibiotic/NormalDeliveryAntibioticQuery.php — ไม่ใช่ query ดิบที่ได้รับมาทั้งหมด
-- (ตัดคอลัมน์ CID/ราคายา/แผนก/ward ออก เพราะไม่จำเป็นต่อการวัด KPI และ CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ:
--   - ตัวเศษ = ผู้ป่วยที่ "ไม่ได้รับ" ยาปฏิชีวนะ (target_direction='higher' — ยิ่งสูงยิ่งดี)
--     ตรงข้ามทิศทางกับ KPI01 ที่ตัวเศษคือผู้ป่วยที่ได้รับยา
--   - เป็นข้อมูล inpatient (ipt/an) ไม่ใช่ outpatient (ovst/vn) แบบ KPI01/KPI04
--   - atb_cost ใช้ opitemrece.sum_price (ราคาจริงตอนจ่ายยา ไม่ใช่ drugitems.unitprice ซึ่งเป็นแค่ราคา
--     แคตตาล็อกปัจจุบัน ใช้ย้อนหลังไม่แม่นยำ) — total_drug_items นับยา "ทุกชนิด" ต่อ AN ไม่กรองด้วย
--     icode_list เลย (คนละตัวกับ drug_info ที่กรองเฉพาะ ATB)
--   - coverage = pttype.name (สิทธิ์การรักษา), department = spclty.name (แผนก) — join ตรงจาก ipt.pttype/
--     ipt.spclty ทั้งคู่ — room ที่นี่คือ "ward" ไม่ใช่ "ห้องตรวจ" แบบ KPI01/04/05 (inpatient ไม่มีแนวคิด
--     ห้องตรวจ) join จาก ipt.ward ไปยัง ward.name แทน kskdepartment
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2026-08-01' AS ds1,
           DATE '2026-08-31' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- รายการ icode ยาปฏิชีวนะ (antibiotic) — รายการตายตัวจากทีม IT/รายงานของโรงพยาบาล (เดียวกับ KPI01/KPI04)
icode_list AS (
    SELECT unnest(ARRAY[
        '1000125','1483847','1000028','1000030','1500906','1000213','1530023','1460340',
        '1483916','1483852','1000034','1610058','1610031','1000084','1440403','1460312',
        '1530013','1000082','1483877','1000085','1000060','1483876','1483867','1520008',
        '1000140','1540024','1483857','1000267','1460047','1000163','1430203','1510018',
        '1483898','1460057','1460106','1520010','1570021','1000011','1580013','1483849',
        '1483788','1510042','1015001','1600008','1590006','1640030','1440208','1510032',
        '1460109','1580003','1000221','1510041','1510044','1000202','1000188','1000231',
        '1460110','1520011','1000126','1460177','1460300','1483815','1510004','1510007',
        '1530015','1550002'
    ]) AS icode
),

-- AN ที่วินิจฉัยหลัก (principal diagnosis) = O800 "การคลอดปกติ" ในช่วงวันที่กำหนด (ไม่รวม HN ทดสอบ)
an_list AS (
    SELECT o.an
    FROM ipt o
    INNER JOIN an_stat vn ON vn.an = o.an
    CROSS JOIN date_range dr
    WHERE o.dchdate BETWEEN dr.ds1 AND dr.ds2
      AND o.an IS NOT NULL
      AND vn.pdx = 'O800'
      AND o.hn NOT IN (SELECT hn FROM bms_hn)
),

-- รายการยาปฏิชีวนะที่สั่งจ่ายใน AN เหล่านั้น
drug_info AS (
    SELECT op.an,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(op.sum_price) AS atb_cost
    FROM opitemrece op
    INNER JOIN drugitems d ON d.icode = op.icode
    WHERE op.an IN (SELECT an FROM an_list)
      AND op.qty > 0
      AND op.icode IN (SELECT icode FROM icode_list)
    GROUP BY op.an
),

-- จำนวนยา "ทุกชนิด" ต่อ AN (ไม่กรอง icode_list) — สำหรับ "ยาเฉลี่ยต่อ VISIT ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.an, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.an IN (SELECT an FROM an_list)
      AND op.qty > 0
    GROUP BY op.an
)

SELECT
    o.hn,
    o.an,
    o.dchdate,
    vn.pdx AS dx,
    concat(p.pname, p.fname, ' xxxx') AS fullname,  -- PDPA: ชื่อเต็ม + xxxx แทนนามสกุล ไม่แสดงนามสกุลจริง
    di.drugitems_name,
    di.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    concat(dd.name, ' (', dd.licenseno, ')') AS doctor,  -- แพทย์ผู้ตรวจ (attending) = ipt.admdoctor
    pt.name AS coverage,
    sp.name AS department,
    w.name AS room  -- "ward" ของ IPT — คนละแนวคิดกับ "ห้องตรวจ" ของ OPD
FROM ipt o
CROSS JOIN date_range dr
INNER JOIN an_stat vn ON vn.an = o.an
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN drug_info di ON di.an = o.an
LEFT OUTER JOIN all_drug_info adi ON adi.an = o.an
LEFT OUTER JOIN doctor dd ON dd.code = o.admdoctor AND dd.council_code = '01' AND dd.active = 'Y'
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN ward w ON w.ward = o.ward
WHERE o.an IN (SELECT an FROM an_list)
ORDER BY o.dchdate, o.hn, o.an;
