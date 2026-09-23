-- atb_cost ใช้ opitemrece.sum_price (ราคาจริงตอนจ่ายยา ไม่ใช่ drugitems.unitprice ซึ่งเป็นแค่ราคา
-- แคตตาล็อกปัจจุบัน ใช้ย้อนหลังไม่แม่นยำ) — total_drug_items นับยา "ทุกชนิด" ต่อ visit ไม่กรองด้วย
-- icode_list เลย (คนละตัวกับ drug_info ที่กรองเฉพาะ ATB)
-- coverage = pttype.name (สิทธิ์การรักษา), department = spclty.name (แผนก) — join ตรงจาก ovst.pttype/
-- ovst.spclty ทั้งคู่ — room = kskdepartment.department (ห้องตรวจ) join จาก ovst.main_dep **ไม่ใช่**
-- cur_dep (เป็น pointer คิวสด กลายเป็น "999 กลับบ้าน" เสมอสำหรับ visit ที่จบแล้ว)
-- แก้ช่วงวันที่ตรงนี้ก่อนรัน
WITH date_range AS (
    SELECT DATE '2026-08-01' AS ds1,
           DATE '2026-08-31' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- รายการ icode ยาปฏิชีวนะ (antibiotic) — รายการตายตัวจากทีม IT/รายงานของโรงพยาบาล (2026-09)
-- แทนที่วิธีเดิม (antibiotic='Y' + dosageform whitelist) เพราะยืนยันแล้วว่าเป็น superset ของวิธีเดิม:
-- ไม่ตกหล่นยาที่วิธีเดิมจับได้เลย แต่จับเพิ่มได้อีก 11 รายการที่วิธีเดิมพลาด (บางส่วนเพราะบั๊ก
-- dosageform เอกพจน์/พหูพจน์ไม่ตรงกัน เช่น 'TABLET' vs 'TABLETS' เดิม)
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

-- รหัส ICD-10 กลุ่ม "ลักษณะการบาดเจ็บ" (nature of injury — S/T-type codes)
-- ต้องมีรหัสจากกลุ่มนี้ "และ" กลุ่ม external_cause_code_list ด้านล่าง (ทั้งคู่) ในเคสเดียวกัน
-- ถึงจะนับเป็น "บาดแผลจากอุบัติเหตุ" — เปลี่ยนจากเดิมที่ใช้ "หรือ" (มีกลุ่มใดกลุ่มหนึ่งก็พอ) มาเป็น
-- "และ" ตามมาตรฐาน ICD-10 จริงที่รหัส nature-of-injury ต้องจับคู่กับรหัส external-cause เสมอ
-- (ดู docs/adr/0001-kpi01-injury-and-external-cause-required.md — ผลกระทบจริง: ตัวส่วนลดจาก 295
-- เหลือ 152 เคส ในช่วงทดสอบ 2026-08-22–2026-09-21)
injury_code_list AS (
    SELECT code
    FROM icd101
    WHERE UPPER(LEFT(code,3)) = ANY('{
            S00,S01,S16,S19,S20,S21,S29,S30,S31,S40,S41,S46,S49,S50,S51,S56,S59,S60,S61,S66,S69,S70,S71,S76,S79,
            S80,S81,S86,S89,S90,S91,S96,S99,T00,T01,T07,T20,T21,T22,T23,T24,T25,T29,T30,T31,T32
          }'::bpchar[])
       OR UPPER(LEFT(code,4)) = ANY('{
            S091,S098,S099,S107,S108,S109,S117,S118,S119,S390,S398,S399,
            T090,T091,T095,T110,T111,T115,T130,T131,T135,T140,T141,T146,T149
          }'::bpchar[])
),

-- รหัส ICD-10 กลุ่ม "สาเหตุอุบัติเหตุ" (external cause — V/W/X-type codes)
external_cause_code_list AS (
    SELECT code
    FROM icd101
    WHERE UPPER(LEFT(code,1)) IN ('V','W')
       OR UPPER(LEFT(code,3)) BETWEEN 'X00' AND 'X59'
),

-- VN ที่มีทั้งรหัสลักษณะการบาดเจ็บ "และ" รหัสสาเหตุอุบัติเหตุ ในช่วงวันที่กำหนด (ไม่รวม HN ทดสอบ)
vn_list AS (
    SELECT ovst.vn
    FROM ovst
    CROSS JOIN date_range dr
    WHERE ovst.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND ovst.hn NOT IN (SELECT hn FROM bms_hn)
      AND EXISTS (
          SELECT 1 FROM ovstdiag inj
          WHERE inj.vn = ovst.vn AND inj.icd10 IN (SELECT code FROM injury_code_list)
      )
      AND EXISTS (
          SELECT 1 FROM ovstdiag ext
          WHERE ext.vn = ovst.vn AND ext.icd10 IN (SELECT code FROM external_cause_code_list)
      )
),

-- รายการยาปฏิชีวนะที่สั่งจ่ายใน VN เหล่านั้น
drug_info AS (
    SELECT o.hn, o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           array_to_string(array_agg(DISTINCT concat(dd.name,' (',dd.licenseno,') ')), ';') AS doctor_name,
           SUM(o.sum_price) AS atb_cost
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    LEFT OUTER JOIN doctor dd ON dd.code = o.doctor
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
    1 AS bb,
    c.cc1,
    h.hpi1,
    CASE WHEN length(tt.vn) > 0 THEN 1 ELSE 0 END AS aa,
    tt.drugitems_name,
    tt.doctor_name,
    tt.atb_cost,
    COALESCE(adi.total_drug_items, 0) AS total_drug_items,
    pt.name AS coverage,
    sp.name AS department,
    kd.department AS room
FROM ovst o
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn
LEFT OUTER JOIN drug_info tt ON tt.vn = o.vn
LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
LEFT JOIN (SELECT vn, string_agg(cc, ', ') AS cc1 FROM opdscreen_cc_list GROUP BY vn) c ON o.vn = c.vn
LEFT JOIN (SELECT vn, string_agg(hpi_text, ', ') AS hpi1 FROM patient_history_hpi GROUP BY vn) h ON o.vn = h.vn
LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
CROSS JOIN date_range dr
WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
  AND o.vn IN (SELECT vn FROM vn_list)
  AND (od.icd10 IN (SELECT code FROM injury_code_list) OR od.icd10 IN (SELECT code FROM external_cause_code_list))
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.doctor_name, tt.atb_cost,
    adi.total_drug_items,
    p.pname, p.fname,
    c.cc1, h.hpi1,
    pt.name, sp.name, kd.department
ORDER BY aa DESC, o.vstdate, o.hn, o.vn;
