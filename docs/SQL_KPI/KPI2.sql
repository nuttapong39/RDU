WITH
v AS (
  SELECT o.vn, o.hn, o.vstdate
  FROM ovst o
  WHERE o.vstdate BETWEEN '2025-10-01' AND '2026-09-30'
    AND o.an IS NULL
),
wound AS (
  -- แผลสดจากอุบัติเหตุ: S/T ตามรายการ + external cause V00-X59
  SELECT od.vn, string_agg(DISTINCT od.icd10, ',') AS wound_dx
  FROM ovstdiag od
  JOIN v ON v.vn = od.vn
  WHERE LEFT(UPPER(od.icd10), 3) = ANY (ARRAY[
          'S00','S01','S05','S07','S08','S09',
          'S10','S11','S15','S16','S17','S18','S19',
          'S20','S21','S25','S27','S28','S29',
          'S30','S31','S35','S37','S38','S39',
          'S40','S41','S45','S47','S48','S49',
          'S50','S51','S55','S57','S58','S59',
          'S60','S61','S65','S67','S68','S69',
          'S70','S71','S75','S77','S78','S79',
          'S80','S81','S85','S87','S88','S89',
          'S90','S91','S95','S97','S98','S99',
          'T00','T01','T09','T11','T13','T14',
          'T20','T21','T22','T23','T24','T25',
          'T29','T30','T31','T32'])
     OR UPPER(od.icd10) ~ '^(V|W|X[0-5])'
  GROUP BY od.vn
),
excl AS (
  -- ตัดออก: สัตว์กัด W54-W56 / แผลไหม้ T20-T32 / บาดเจ็บกล้ามเนื้อ-เอ็น
  SELECT DISTINCT od.vn
  FROM ovstdiag od
  JOIN v ON v.vn = od.vn
  WHERE UPPER(od.icd10) ~ '^(W5[4-6]|T2[0-9]|T3[0-2]|S(16|46|56|66|76|86|96)|S390|T095|T115|T135|T146)'
),
atb_use AS (
  SELECT op.vn,
         COUNT(DISTINCT op.icode) AS total_atb,
         string_agg(concat(d.name, ' ', d.strength, ':', op.qty), ',') AS atblist,
         string_agg(DISTINCT d.atc_code, ',') AS atc_list,
         string_agg(DISTINCT sp.name, ',') AS depname
  FROM opitemrece op
  JOIN v ON v.vn = op.vn
  JOIN drugitems d ON d.icode = op.icode
  LEFT JOIN kskdepartment ksk ON ksk.depcode = op.dep_code
  LEFT JOIN spclty sp ON sp.spclty = ksk.spclty
  WHERE op.icode = ANY (ARRAY[
    '1000030','1000028','1580003','1000034','1610058','1483847','1483857','1530013',
    '1000060','1460109','1510018','1000082','1483877','1530023','1540024','1000084',
    '1000085','1670021','1670027','1000125','1000267','1000140','1640002','1000213',
    '1500906','1680036','1430203','1000231'])
  GROUP BY op.vn
)
SELECT
  v.vstdate,
  v.hn,
  pa.cid,
  concat(pa.pname, pa.fname, ' ', pa.lname) AS ptname,
  vs.age_y,
  vs.sex,
  w.wound_dx,
  COALESCE(a.total_atb, 0) AS total_atb,
  a.atblist,
  a.atc_list,
  a.depname,
  ( SELECT string_agg(DISTINCT dr.name, ',')
    FROM ovstdiag di JOIN doctor dr ON dr.code = di.doctor
    WHERE di.vn = v.vn ) AS doctor_diage,
  ( SELECT string_agg(DISTINCT dr.name, ',')
    FROM opitemrece op JOIN doctor dr ON dr.code = op.doctor
    WHERE op.vn = v.vn ) AS doctor_drug
FROM v
JOIN wound w         ON w.vn = v.vn
JOIN patient pa      ON pa.hn = v.hn
LEFT JOIN vn_stat vs ON vs.vn = v.vn
LEFT JOIN atb_use a  ON a.vn = v.vn
WHERE NOT EXISTS (SELECT 1 FROM excl e WHERE e.vn = v.vn)
ORDER BY v.vstdate, v.hn;
