-- KPI-04: ร้อยละการใช้ยาปฏิชีวนะในโรคติดเชื้อที่ระบบการหายใจช่วงบนและหลอดลมอักเสบเฉียบพลันในผู้ป่วยนอก
-- ที่มา: query อ้างอิงจากทีม IT/รายงานของโรงพยาบาล (2026-09) ปรับให้ตรงกับสิ่งที่ implement จริงใน
-- app/Queries/Kpi/Antibiotic/RespiratoryInfectionAntibioticQuery.php — ไม่ใช่ query ดิบที่ได้รับมาทั้งหมด
-- (ตัดคอลัมน์ CID/ราคายา/แผนก/ward ออก เพราะไม่จำเป็นต่อการวัด KPI และ CID เป็นข้อมูลอ่อนไหวตาม PDPA)
--
-- หมายเหตุสำคัญ:
--   - ตัวเศษ = ผู้ป่วยที่ "ได้รับ" ยาปฏิชีวนะ (target_direction='lower' — ยิ่งใช้น้อยยิ่งดี เหมือน KPI01)
--   - เงื่อนไขวินิจฉัยเป็น list เดียว (ไม่ใช่ AND ของ 2 กลุ่มแบบ KPI01) — เข้าเกณฑ์ถ้ามีรหัสใดรหัสหนึ่ง
--     ในกลุ่ม H65-H72 (หูอักเสบ) หรือ J00-J21 (ติดเชื้อทางเดินหายใจส่วนบน/หลอดลมอักเสบเฉียบพลัน)
--   - เป็นข้อมูล outpatient (ovst/vn) เหมือน KPI01 แต่ปริมาณเคสสูงกว่ามาก (~2,400 เคส/ปีงบ)
--   - atb_cost ใช้ opitemrece.sum_price (ราคาจริงตอนจ่ายยา ไม่ใช่ drugitems.unitprice ซึ่งเป็นแค่ราคา
--     แคตตาล็อกปัจจุบัน ใช้ย้อนหลังไม่แม่นยำ) — total_drug_items นับยา "ทุกชนิด" ต่อ visit ไม่กรองด้วย
--     icode_list เลย (คนละตัวกับ drug_info ที่กรองเฉพาะ ATB)
--   - coverage = pttype.name (สิทธิ์การรักษา), department = spclty.name (แผนก) — join ตรงจาก ovst.pttype/
--     ovst.spclty ทั้งคู่ — room = kskdepartment.department (ห้องตรวจ) join จาก ovst.main_dep **ไม่ใช่**
--     cur_dep (เป็น pointer คิวสด กลายเป็น "999 กลับบ้าน" เสมอสำหรับ visit ที่จบแล้ว)
--   - แก้ช่วงวันที่ตรงนี้ก่อนรัน

WITH date_range AS (
    SELECT DATE '2026-08-01' AS ds1,
           DATE '2026-08-31' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- รหัส ICD-10 กลุ่มติดเชื้อทางเดินหายใจส่วนบน/หลอดลมอักเสบเฉียบพลัน (+ หูอักเสบที่เกี่ยวข้อง)
diagnosis_code_list AS (
    SELECT unnest(ARRAY[
        'H650','H651','H659','H660','H664','H669','H670','H671','H678',
        'H720','H721','H722','H728','H729',
        'J00','J010','J011','J012','J013','J014','J018','J019',
        'J020','J029','J030','J038','J039','J040','J041','J042',
        'J050','J051','J060','J068','J069','J101','J111',
        'J200','J201','J202','J203','J204','J205','J206','J207','J208','J209',
        'J210','J218','J219'
    ]) AS code
),

-- รายการ icode ยาปฏิชีวนะ (antibiotic) — รายการตายตัวจากทีม IT/รายงานของโรงพยาบาล (เดียวกับ KPI01/KPI03)
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

-- VN ที่มีรหัสวินิจฉัยตรงกลุ่มนี้ ในช่วงวันที่กำหนด (ไม่รวม HN ทดสอบ)
vn_list AS (
    SELECT ovst.vn
    FROM ovst
    CROSS JOIN date_range dr
    WHERE ovst.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND ovst.hn NOT IN (SELECT hn FROM bms_hn)
      AND EXISTS (
          SELECT 1 FROM ovstdiag od
          WHERE od.vn = ovst.vn AND od.icd10 IN (SELECT code FROM diagnosis_code_list)
      )
),

-- รายการยาปฏิชีวนะที่สั่งจ่ายใน VN เหล่านั้น
drug_info AS (
    SELECT o.hn, o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    WHERE o.vn IN (SELECT vn FROM vn_list)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM icode_list)
    GROUP BY o.hn, o.vn
),

-- จำนวนยา "ทุกชนิด" ต่อ VN (ไม่กรอง icode_list) — สำหรับ "ยาเฉลี่ยต่อ VISIT ทุกชนิดรวมกัน"
all_drug_info AS (
    SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
    FROM opitemrece op
    WHERE op.vn IN (SELECT vn FROM vn_list)
      AND op.qty > 0
    GROUP BY op.vn
)

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
CROSS JOIN date_range dr
WHERE o.vn IN (SELECT vn FROM vn_list)
  AND od.icd10 IN (SELECT code FROM diagnosis_code_list)
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname,
    attdoc.name, attdoc.licenseno,
    pt.name, sp.name, kd.department
ORDER BY o.vstdate, o.hn, o.vn;
