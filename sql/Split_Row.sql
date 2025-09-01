select * from all_facities_all_billing_status afabs group by pubpid;

select count(*) from (SELECT f.id, f.date, f.pid, CONCAT(w.lname, ', ', w.fname) AS provider_id, f.encounter, f.last_level_billed, f.last_level_closed, f.last_stmt_date, f.stmt_count, f.invoice_refno, f.in_collection, p.fname, p.mname, p.lname, p.street, p.city, p.state, p.postal_code, p.phone_home, p.ss, p.billing_note, p.pubpid, p.DOB, CONCAT(u.lname, ', ', u.fname) AS referrer, ( SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date, ( SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges, ( SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, ( SELECT SUM(s.fee) FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales, ( SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, ( SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments, cpt.cpt_codes FROM form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/ JOIN billing AS b ON f.pid=b.pid /** haroon end **/LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id LEFT OUTER JOIN users AS w ON w.id = f.provider_id LEFT JOIN ( SELECT pid, encounter, GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR ',') AS cpt_codes FROM billing WHERE code_type = 'CPT4' AND activity = 1 GROUP BY pid, encounter ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter WHERE /** haroon start **/ b.code_type like '%' /** haroon end **/ ORDER BY f.pid, f.encounter) AS sub;

-- Before splitting up start
	
SELECT
	f.id,
	f.date,
	f.pid,
	CONCAT(w.lname, ', ', w.fname) AS provider_id,
	f.encounter,
	f.last_level_billed,
	f.last_level_closed,
	f.last_stmt_date,
	f.stmt_count,
	f.invoice_refno,
	f.in_collection,
	p.fname,
	p.mname,
	p.lname,
	p.street,
	p.city,
	p.state,
	p.postal_code,
	p.phone_home,
	p.ss,
	p.billing_note,
	p.pubpid,
	p.DOB,
	CONCAT(u.lname, ', ', u.fname) AS referrer,
	(
	SELECT
		bill_date
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY'
	LIMIT 1) AS bill_date,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY' ) AS charges,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type = 'COPAY' ) AS copays,
	(
	SELECT
		s.fee
	FROM
		drug_sales AS s
	WHERE
		s.pid = f.pid
		AND s.encounter = f.encounter ) AS sales,
	(
	SELECT
		SUM(a.pay_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS payments,
	(
	SELECT
		SUM(a.adj_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS adjustments,
	cpt.cpt_codes
FROM
	form_encounter AS f
JOIN patient_data AS p ON
	p.pid = f.pid /** haroon start **/
JOIN billing AS b ON
	f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON
	u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON
	w.id = f.provider_id
LEFT JOIN (
	SELECT
		pid,
		encounter, GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR '|') AS cpt_codes
FROM
	billing
WHERE
	code_type = 'CPT4'
	AND activity = 1
GROUP BY
	pid,
	encounter ) cpt ON
	cpt.pid = f.pid
	AND cpt.encounter = f.encounter
WHERE
	/** haroon start **/
	b.code_type like '%' /** haroon end **/
	/** SPLIT ROWS START **/
	AND cpt.cpt_codes is not null

/** SPLIT ROWS END **/
ORDER BY
	f.pid,
	f.encounter ;
	
-- Before splitting up end	
	
select bg.pid,bg.encounter as billing_eid ,aa.encounter as ar_activity_eid, bg.billed ,bg.code , bg.code_text ,bg.fee ,bg.units, aa.pay_amount , aa.adj_amount  from billing bg 
inner join ar_activity aa on aa.code =bg.code 
where aa.encounter=bg.encounter and ;



where bg.code_type ='CPT4';


-- After splitting up start
SELECT
	f.id,
	f.date,
	f.pid,
	CONCAT(w.lname, ', ', w.fname) AS provider_id,
	f.encounter,
	f.last_level_billed,
	f.last_level_closed,
	f.last_stmt_date,
	f.stmt_count,
	f.invoice_refno,
	f.in_collection,
	p.fname,
	p.mname,
	p.lname,
	p.street,
	p.city,
	p.state,
	p.postal_code,
	p.phone_home,
	p.ss,
	p.billing_note,
	p.pubpid,
	p.DOB,
	CONCAT(u.lname, ', ', u.fname) AS referrer,
	(
	SELECT
		bill_date
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY'
	LIMIT 1) AS bill_date,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY' ) AS charges,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type = 'COPAY' ) AS copays,
	(
	SELECT
		SUM(s.fee)
	FROM
		drug_sales AS s
	WHERE
		s.pid = f.pid
		AND s.encounter = f.encounter ) AS sales,
	(
	SELECT
		SUM(a.pay_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS payments,
	(
	SELECT
		SUM(a.adj_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS adjustments,
	cpt.cpt_codes
FROM
	form_encounter AS f
JOIN patient_data AS p ON
	p.pid = f.pid /** haroon start **/
JOIN billing AS b ON
	f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON
	u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON
	w.id = f.provider_id
LEFT JOIN (
	SELECT
		
		pid,
		encounter,code  AS cpt_codes
FROM
	billing
WHERE
	code_type = 'CPT4'
	AND activity = 1
GROUP BY
	pid,
	encounter ) cpt ON
	cpt.pid = f.pid
	AND cpt.encounter = f.encounter
WHERE
	/** haroon start **/
	b.code_type like '%' /** haroon end **/
	/** SPLIT ROWS START **/
	AND cpt.cpt_codes is not null

/** SPLIT ROWS END **/
ORDER BY
	f.pid,
	f.encounter ;
-- -- After splitting up END	

SELECT
       distinct code  AS cpt_codes,
		pid,
		encounter
FROM
	billing;
	
-- Splitted start NEW
SELECT
	f.id,
	f.date,
	f.pid,
	CONCAT(w.lname, ', ', w.fname) AS provider_id,
	f.encounter,
	f.last_level_billed,
	f.last_level_closed,
	f.last_stmt_date,
	f.stmt_count,
	f.invoice_refno,
	f.in_collection,
	p.fname,
	p.mname,
	p.lname,
	p.street,
	p.city,
	p.state,
	p.postal_code,
	p.phone_home,
	p.ss,
	p.billing_note,
	p.pubpid,
	p.DOB,
	CONCAT(u.lname, ', ', u.fname) AS referrer,
	(
	SELECT
		bill_date
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY'
	LIMIT 1) AS bill_date,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY') AS charges,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type = 'COPAY') AS copays,
	(
	SELECT
		SUM(s.fee)
	FROM
		drug_sales AS s
	WHERE
		s.pid = f.pid
		AND s.encounter = f.encounter) AS sales,
	(
	SELECT
		SUM(a.pay_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS payments,
	(
	SELECT
		SUM(a.adj_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS adjustments,
	cpt.code AS cpt_codes
FROM
	form_encounter AS f
JOIN patient_data AS p ON
	p.pid = f.pid
JOIN billing AS b ON
	f.pid = b.pid
LEFT OUTER JOIN users AS u ON
	u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON
	w.id = f.provider_id
LEFT JOIN (
	SELECT
		pid,
		encounter,
		code
	FROM
		billing
	WHERE
		code_type = 'CPT4'
		AND activity = 1) cpt ON
	cpt.pid = f.pid
	AND cpt.encounter = f.encounter
WHERE
	b.code_type like '%'
	AND cpt.code is not null
ORDER BY
	f.pid,
	f.encounter,
	cpt.code;
-- Splitted end NEW





SELECT
	f.id,
	f.date,
	f.pid,
	CONCAT(w.lname, ', ', w.fname) AS provider_id,
	f.encounter,
	f.last_level_billed,
	f.last_level_closed,
	IF(b.billed = 0, 'Unbilled', 'Billed') AS billing_status,
	f.last_stmt_date,
	f.stmt_count,
	f.invoice_refno,
	f.in_collection,
	p.fname,
	p.mname,
	p.lname,
	p.street,
	p.city,
	p.state,
	p.postal_code,
	p.phone_home,
	p.ss,
	p.billing_note,
	p.pubpid,
	p.DOB,
	CONCAT(u.lname, ', ', u.fname) AS referrer,
	(
	SELECT
		bill_date
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY'
	LIMIT 1) AS bill_date,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type != 'COPAY' ) AS charges,
	(
	SELECT
		SUM(b.fee)
	FROM
		billing AS b
	WHERE
		b.pid = f.pid
		AND b.encounter = f.encounter
		AND b.activity = 1
		AND b.code_type = 'COPAY' ) AS copays,
	(
	SELECT
		SUM(s.fee)
	FROM
		drug_sales AS s
	WHERE
		s.pid = f.pid
		AND s.encounter = f.encounter ) AS sales,
	(
	SELECT
		SUM(a.pay_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS payments,
	(
	SELECT
		SUM(a.adj_amount)
	FROM
		ar_activity AS a
	WHERE
		a.pid = f.pid
		AND a.encounter = f.encounter
		AND a.deleted IS NULL) AS adjustments,
	cpt.cpt_codes
FROM
	form_encounter AS f
JOIN patient_data AS p ON
	p.pid = f.pid /** haroon start **/
JOIN billing AS b ON
	f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON
	u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON
	w.id = f.provider_id
LEFT JOIN (
	SELECT
		pid,
		encounter,
		GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR ',') AS cpt_codes
	FROM
		billing
	WHERE
		code_type = 'CPT4'
		AND activity = 1
	GROUP BY
		pid,
		encounter ) cpt ON
	cpt.pid = f.pid
	AND cpt.encounter = f.encounter
WHERE
	/** haroon start **/
	b.code_type like '%' /** haroon end **/
ORDER BY
	f.pid,
	f.encounter;
