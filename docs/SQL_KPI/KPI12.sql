SELECT
	o.vstdate,
	o.hn,
	pa.CID,
	concat ( pa.pname, pa.fname, ' ', pa.lname ) ptname,
	vn.age_y,
	(
	SELECT COUNT
		( DISTINCT op.icode ) 
	FROM
		opitemrece op
		JOIN drugitems d ON d.icode = op.icode 
	WHERE
		op.vn = o.vn 
		AND op.icode IN ('1510003','1000122','1670015') 
	) total_ras,
	(
	SELECT COUNT
		( DISTINCT op.icode ) 
	FROM
		opitemrece op
		JOIN drugitems d ON d.icode = op.icode 
	WHERE
		op.vn = o.vn 
		AND op.icode IN ('1510003','1000122','1670015')
	) total_ena,
	(
	SELECT
		string_agg ( concat ( d.NAME, ' ', strength, ':', op.qty ), ',' ) 
	FROM
		opitemrece op
		JOIN drugitems d ON d.icode = op.icode 
	WHERE
		op.vn = o.vn 
		AND op.icode IN ('1510003','1000122','1670015')
	) raslist,
	(
	SELECT
		string_agg ( DISTINCT sp.NAME, ',' ) 
	FROM
		opitemrece op
		JOIN drugitems d ON d.icode = op.icode
		LEFT JOIN kskdepartment ksk ON ksk.depcode = op.dep_code
		LEFT JOIN spclty sp ON sp.spclty = ksk.spclty 
	WHERE
		op.vn = o.vn 
		AND op.icode IN ('1510003','1000122','1670015')
	) depname,
	(
	SELECT
		string_agg ( DISTINCT dr.NAME, ',' ) 
	FROM
		ovstdiag di
		JOIN doctor dr ON dr.code = di.doctor 
	WHERE
		di.vn = o.vn 
	) doctor_diage,
	(
	SELECT
		string_agg ( DISTINCT dr.NAME, ',' ) 
	FROM
		opitemrece op
		JOIN drugitems d ON d.icode = op.icode
		LEFT JOIN doctor dr ON dr.code = op.doctor 
	WHERE
		op.vn = o.vn 
	) doctor_drug 
FROM
	ovst o
	JOIN patient pa ON o.hn = pa.hn
	INNER JOIN opitemrece op ON ( o.vn = op.vn )
	LEFT JOIN vn_stat vn ON vn.vn = o.vn 
WHERE
	o.vstdate BETWEEN '2026-08-31' 
	AND '2026-09-30' 
	AND ( SELECT COUNT ( * ) FROM ovstdiag od WHERE od.vn = o.vn AND od.icd10 LIKE'I10%' ) > 0 
	AND op.icode IN ('1510003','1000122','1670015')
GROUP BY
	o.vstdate,
	o.hn,
	o.vn,
	pa.CID,
	concat ( pa.pname, pa.fname, ' ', pa.lname ),
	vn.age_y