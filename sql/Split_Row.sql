-- AR Report Issue Start
SELECT
	f.id,
	f.date,
	f.pid,
	CONCAT(w.lname, ', ', w.fname) AS provider_id,
	f.encounter,
	f.last_level_billed,
	IF(b.billed = 0,
	'Unbilled',
	'Billed') AS billing_status,
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
	a.pay_amount AS payments,
	a.adj_amount AS adjustments,
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
LEFT JOIN ar_activity AS a ON
	a.pid = f.pid
	AND a.encounter = f.encounter
	AND a.deleted IS NULL
	AND /* haroon start */
	b.code_type like '%' /* haroon end */
	AND f.date >= '2025-08-03'
	AND f.date <= '2025-09-03';
	
	SELECT f.id, f.date, f.pid, CONCAT(w.lname, ', ', w.fname) AS provider_id, f.encounter, f.last_level_billed, f.last_level_closed, f.last_stmt_date, f.stmt_count, f.invoice_refno, f.in_collection, p.fname, p.mname, p.lname, p.street, p.city, p.state, p.postal_code, p.phone_home, p.ss, p.billing_note, p.pubpid, p.DOB, CONCAT(u.lname, ', ', u.fname) AS referrer, ( SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date, ( SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges, ( SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, ( SELECT SUM(s.fee) FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales, ( SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, ( SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments, cpt.cpt_codes FROM form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/ JOIN 	billing AS b ON f.pid=b.pid /** haroon end **/LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id LEFT OUTER JOIN users AS w ON w.id = f.provider_id LEFT JOIN (
            SELECT
                pid,
                encounter,
                GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR ',') AS cpt_codes
            FROM billing
            WHERE code_type = 'CPT4' AND activity = 1
            GROUP BY pid, encounter
            ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter WHERE  /** haroon start **/ b.code_type like '%' /** haroon end **/  ORDER BY f.pid, f.encounter;

	-- without cpt codes splitted start
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
           
           
           
-- witout cpt codes splitted end

--  with cpt codes splitted start A1

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
-- 	(SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date,
	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays,
-- 	(SELECT s.fee FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales
-- 	(SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments,
-- 	(SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments,
	cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT code AS cpt_codes, pid , encounter /*, GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR ',') AS cpt_codes */
FROM
	billing
WHERE
	code_type = 'CPT4' AND activity = 1 /* GROUP BY 	pid, encounter */  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
WHERE
	/** haroon start **/
	b.code_type like '%' /** haroon end **/
	/** SPLIT ROWS START **/ 	AND p.fname like '%karee%'

/** SPLIT ROWS END **/
ORDER BY
	f.pid,
	f.encounter ;

-- with cpt codes splitted end A1


--  with cpt codes splitted start A2

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
	b.fee /*b.fee AAA*/,s.fee /*s.fee CCC*/,a.pay_amount /*a.pay_amount DDD*/ , a.adj_amount /*a.adj_amount EEE*/ 
-- 	(SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges --  AAA,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, --- BBB
-- 	(SELECT s.fee FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales --- CCC
-- 	(SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, --- DDD
-- 	(SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments, --- EEE
	,cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT code AS cpt_codes, pid , encounter FROM billing WHERE code_type = 'CPT4' AND activity = 1  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
/* CCC START */ JOIN drug_sales s on s.pid = f.pid AND s.encounter = f.encounter /* CCC END */
/* DDD START */ JOIN ar_activity a on s.pid = f.encounter /* DDD END */
WHERE
  /* AAA START */ b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND ( b.code_type != 'COPAY' /* AAA END */  
  /** BBB START  **/ OR b.code_type = 'COPAY')  /** BBB START  **/ AND   
  /* DDD START */  a.deleted IS NULL /* DDD END */
  AND b.code_type like '%' AND p.fname like '%karee%'

ORDER BY
	f.pid,
	f.encounter ;

-- with cpt codes splitted end A2

--  with cpt codes splitted start A2 Cleanup before running in Staging Or Augustus

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
	b.fee /*b.fee AAA*//*,s.fee *s.fee CCC*/,a.pay_amount /*a.pay_amount DDD*/ , a.adj_amount /*a.adj_amount EEE*/ 
-- 	(SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges --  AAA,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, --- BBB
-- 	(SELECT s.fee FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales --- CCC
-- 	(SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, --- DDD
-- 	(SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments, --- EEE
	,cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT code AS cpt_codes, pid , encounter FROM billing WHERE code_type = 'CPT4' AND activity = 1  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
-- /* CCC START */ JOIN drug_sales s on  s.encounter = f.encounter /* CCC END */
/* DDD START */ JOIN ar_activity a on a.pid = f.encounter /* DDD END */
WHERE
  /* AAA START */ b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND ( b.code_type != 'COPAY' /* AAA END */  
  /** BBB START  **/ OR b.code_type = 'COPAY')  /** BBB START  **/ AND   
  /* DDD START */  a.deleted IS NULL /* DDD END */
  AND b.code_type like '%' 
--   AND p.fname like '%karee%'
--   AND s.pid = f.pid

ORDER BY
	f.pid,
	f.encounter ;

--  with cpt codes splitted start A2 Cleanup before running in Staging Or Augustus


--  with cpt codes splitted START A3 Before Cleanup after successful run in ALAB - Removed Drug Sales

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
	b.fee /*b.fee AAA*//*,s.fee *s.fee CCC*/,a.pay_amount /*a.pay_amount DDD*/ , a.adj_amount /*a.adj_amount EEE*/ 
-- 	(SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' ) AS charges --  AAA,
-- 	(SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, --- BBB
-- 	(SELECT s.fee FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter ) AS sales --- CCC
-- 	(SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, --- DDD
-- 	(SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments, --- EEE
	,cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT code AS cpt_codes, pid , encounter FROM billing WHERE code_type = 'CPT4' AND activity = 1  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
-- /* CCC START */ JOIN drug_sales s on  s.encounter = f.encounter /* CCC END */
/* DDD START */ JOIN ar_activity a on a.pid = f.encounter /* DDD END */
WHERE
  /* AAA START */ b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND ( b.code_type != 'COPAY' /* AAA END */  
  /** BBB START  **/ OR b.code_type = 'COPAY')  /** BBB START  **/ AND   
  /* DDD START */  a.deleted IS NULL /* DDD END */
  AND b.code_type like '%' 
--   AND p.fname like '%karee%'
--   AND s.pid = f.pid

ORDER BY
	f.pid,
	f.encounter ;

--  with cpt codes splitted START A3 End Cleanup after successful run in ALAB - Removed Drug Sales
           
--  with cpt codes splitted START A4 After Cleanup after successful run in ALAB - Removed Drug Sales

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
	b.fee ,a.pay_amount  , a.adj_amount  
	,cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT code AS cpt_codes, pid , encounter FROM billing WHERE code_type = 'CPT4' AND activity = 1  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
JOIN ar_activity a on a.pid = f.encounter 
WHERE
  b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND ( b.code_type != 'COPAY' OR b.code_type = 'COPAY')   AND   
  a.deleted IS NULL 
  AND b.code_type like '%' 
ORDER BY
	f.pid,
	f.encounter ;

--  with cpt codes splitted END A4 After Cleanup after successful run in ALAB - Removed Drug Sales      

--  with cpt codes splitted START A5 Removing repeating CPT

   SELECT
	f.id,f.date,f.pid,CONCAT(w.lname, ', ', w.fname) AS provider_id,f.encounter,f.last_level_billed,f.last_level_closed,f.last_stmt_date,f.stmt_count,f.invoice_refno,f.in_collection,p.fname,
	p.mname,p.lname,p.street,p.city,p.state,p.postal_code,p.phone_home,p.ss,p.billing_note,p.pubpid,p.DOB,CONCAT(u.lname, ', ', u.fname) AS referrer,
	b.fee ,a.pay_amount  , a.adj_amount  
	,cpt.cpt_codes
FROM
	form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid /** haroon start **/
JOIN billing AS b ON f.pid = b.pid /** haroon end **/
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
LEFT JOIN ( SELECT distinct code AS cpt_codes, pid , encounter FROM billing WHERE code_type = 'CPT4' AND activity = 1  ) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter
JOIN ar_activity a on a.pid = f.encounter 
WHERE
  b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND ( b.code_type != 'COPAY' OR b.code_type = 'COPAY')   AND   
  a.deleted IS NULL 
  AND b.code_type like '%' 
ORDER BY
	f.pid,
	f.encounter 

--  with cpt codes splitted E A5 Removing repeating CPT
           
           
	
	-- AR Report Issue end
            
            
            SELECT f.id, f.date, f.pid, CONCAT(w.lname, ', ', w.fname) AS provider_id, f.encounter, f.last_level_billed,IF(b.billed = 0, 'Unbilled', 'Billed') AS billing_status,  f.last_level_closed, f.last_stmt_date, f.stmt_count, f.invoice_refno, f.in_collection, p.fname, p.mname, p.lname, p.street, p.city, p.state, p.postal_code, p.phone_home, p.ss, p.billing_note, p.pubpid, p.DOB, CONCAT(u.lname, ', ', u.fname) AS referrer, (SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date, (SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY') AS charges, (SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY') AS copays, (SELECT SUM(s.fee) FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter) AS sales, a.pay_amount AS payments, a.adj_amount AS adjustments, cpt.code AS cpt_codes FROM form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid JOIN billing AS b ON f.pid = b.pid LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id LEFT OUTER JOIN users AS w ON w.id = f.provider_id LEFT JOIN (SELECT pid, encounter, code FROM billing WHERE code_type = 'CPT4' AND activity = 1) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter LEFT JOIN ar_activity AS a ON a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL AND  /** haroon start **/ b.code_type like '%' /** haroon end **/  AND f.date >= '2025-08-01' AND f.date <='2025-08-29'  ;
	
           
           select fe.referring_provider_id,fe.* from form_encounter fe ;
          
          select * from insurance_numbers in2 ;
         
         select * from billing;
	
            
            
	
	
	






















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
where aa.encounter=bg.encounter;



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


-- TEST START REMOVE 	SUM(a.pay_amount) FROM ar_activity

SELECT f.id, f.date, f.pid, CONCAT(w.lname, ', ', w.fname) AS provider_id, f.encounter, f.last_level_billed, f.last_level_closed, f.last_stmt_date, f.stmt_count, f.invoice_refno, f.in_collection, p.fname, p.mname, p.lname, p.street, p.city, p.state, p.postal_code, p.phone_home, p.ss, p.billing_note, p.pubpid, p.DOB, CONCAT(u.lname, ', ', u.fname) AS referrer, (SELECT bill_date FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS bill_date, (SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type != 'COPAY') AS charges, (SELECT SUM(b.fee) FROM billing AS b WHERE b.pid = f.pid AND b.encounter = f.encounter AND b.activity = 1 AND b.code_type = 'COPAY') AS copays, (SELECT SUM(s.fee) FROM drug_sales AS s WHERE s.pid = f.pid AND s.encounter = f.encounter) AS sales, a.pay_amount AS payments, a.adj_amount AS adjustments, cpt.code AS cpt_codes FROM form_encounter AS f JOIN patient_data AS p ON p.pid = f.pid JOIN billing AS b ON f.pid = b.pid LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id LEFT OUTER JOIN users AS w ON w.id = f.provider_id LEFT JOIN (SELECT pid, encounter, code FROM billing WHERE code_type = 'CPT4' AND activity = 1) cpt ON cpt.pid = f.pid AND cpt.encounter = f.encounter LEFT JOIN ar_activity AS a ON a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL WHERE b.code_type like '%' AND cpt.code is not null ORDER BY f.pid, f.encounter, cpt.code;

-- TEST END REMOVE 	SUM(a.pay_amount) FROM ar_activity
