-- แก้ช่วงวันที่ตรงนี้ก่อนรัน
WITH date_range AS (
    SELECT DATE '2026-08-01' AS ds1,
           DATE '2026-08-31' AS ds2
),

-- HN ของเคสทดสอบ ที่ต้องการตัดออก
bms_hn AS (
    SELECT hn FROM patient WHERE fname LIKE '%ทดสอบ%'
),

-- รายการ icode ยาปฏิชีวนะ (antibiotic)
icode_list AS (
    SELECT icode
    FROM drugitems
    WHERE antibiotic = 'Y'
      AND istatus = 'Y'
      AND dosageform IN ('TABLETS','CAPSULES','SYRUPS','SUSPENSIONS','INJECTIONS')
),

-- รหัส ICD10 กลุ่มอุบัติเหตุ/บาดเจ็บที่สนใจ
icd_code_list AS (
    SELECT code
    FROM icd101
    WHERE UPPER(LEFT(code,3)) = ANY('{
            S00,S01,S16,S19,S20,S21,S29,S30,S31,S40,S46,S49,S50,S51,S56,S59,S60,S61,S66,S69,S70,S71,S76,S79,
            S80,S81,S86,S89,S90,S91,S96,S99,T00,T01,T07,T20,T21,T22,T23,T24,T25,T29,T30,T31,T32
          }'::bpchar[])
       OR UPPER(LEFT(code,4)) = ANY('{
            S091,S098,S099,S107,S108,S109,S117,S118,S119,S390,S398,S399,
            T090,T091,T095,T110,T111,T115,T130,T131,T135,T140,T141,T146,T149
          }'::bpchar[])
       OR UPPER(LEFT(code,3)) BETWEEN 'V01' AND 'V99'
       OR UPPER(LEFT(code,3)) BETWEEN 'W00' AND 'W99'
       OR UPPER(LEFT(code,3)) BETWEEN 'X00' AND 'X59'
),

-- VN ที่ตรงเงื่อนไข ICD10 กลุ่มนี้ ในช่วงวันที่กำหนด (ไม่รวม HN ทดสอบ)
vn_list AS (
    SELECT ovst.vn
    FROM ovst
    INNER JOIN ovstdiag ON ovstdiag.vn = ovst.vn
    CROSS JOIN date_range dr
    WHERE ovst.vstdate BETWEEN dr.ds1 AND dr.ds2
      AND ovstdiag.icd10 IN (SELECT code FROM icd_code_list)
      AND ovst.hn NOT IN (SELECT hn FROM bms_hn)
    GROUP BY ovst.vn
),

-- รายการยาปฏิชีวนะที่สั่งจ่ายใน VN เหล่านั้น
drug_info AS (
    SELECT o.hn, o.vn,
           array_to_string(array_agg(DISTINCT concat(d.name,' ',d.strength,' ',d.units)), ';') AS drugitems_name,
           array_to_string(array_agg(DISTINCT concat(dd.name,' (',dd.licenseno,') ')), ';') AS doctor_name
    FROM opitemrece o
    INNER JOIN drugitems d ON d.icode = o.icode
    LEFT OUTER JOIN doctor dd ON dd.code = o.doctor
    WHERE o.vn IN (SELECT vn FROM vn_list)
      AND o.qty > 0
      AND o.icode IN (SELECT icode FROM icode_list)
    GROUP BY o.hn, o.vn
)

SELECT
    o.hn,
    o.vn,
    o.vstdate,
    array_to_string(array_agg(DISTINCT od.icd10), ',') AS dx,
    concat(p.pname, p.fname, ' ', p.lname) AS fullname,
    1 AS bb,
    c.cc1,
    h.hpi1,
    CASE WHEN length(tt.vn) > 0 THEN 1 ELSE 0 END AS aa,
    tt.drugitems_name,
    tt.doctor_name
FROM ovst o
LEFT OUTER JOIN patient p ON p.hn = o.hn
LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn
LEFT OUTER JOIN drug_info tt ON tt.vn = o.vn
LEFT JOIN (SELECT vn, string_agg(cc, ', ') AS cc1 FROM opdscreen_cc_list GROUP BY vn) c ON o.vn = c.vn
LEFT JOIN (SELECT vn, string_agg(hpi_text, ', ') AS hpi1 FROM patient_history_hpi GROUP BY vn) h ON o.vn = h.vn
CROSS JOIN date_range dr
WHERE o.vstdate BETWEEN dr.ds1 AND dr.ds2
  AND od.icd10 IN (SELECT code FROM icd_code_list)
  AND o.vn IN (SELECT vn FROM vn_list)
  AND left(od.icd10, 1) NOT BETWEEN '0' AND '9'
GROUP BY
    o.hn, o.vn, o.vstdate,
    tt.vn, tt.drugitems_name, tt.doctor_name,
    concat(p.pname, p.fname, ' ', p.lname),
    c.cc1, h.hpi1
ORDER BY aa DESC, o.vstdate, o.hn, o.vn;