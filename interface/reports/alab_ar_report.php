<?php

/**
 * Collections report: various options to report on the current billing status of encounters
 *
 * (TLH) Added payor,provider,fixed cvs download to included selected fields
 * (TLH) Added ability to download selected invoices only or all for patient
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Terry Hill <terry@lillysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2006-2020 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2015 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019-2022 Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2019 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("../../library/patient.inc.php");
require_once "$srcdir/options.inc.php";

//new logic start
ini_set('memory_limit', -1);
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('America/Chicago');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

//new logic end

use OpenEMR\Billing\InvoiceSummary;
use OpenEMR\Billing\SLEOB;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Common\Utils\FormatMoney;
use OpenEMR\Core\Header;

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

if (!AclMain::aclCheckCore('acct', 'rep_a')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("alab ar report Report")]);
    exit;
}

$alertmsg = '';
$bgcolor = "#aaaaaa";
$export_patient_count = 0;
$export_dollars = 0;

$form_date      = (isset($_POST['form_date'])) ? DateToYYYYMMDD($_POST['form_date']) : "";
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : "";


function endPatient($ptrow)
{
    global $export_patient_count, $export_dollars, $bgcolor;
    global $grand_total_charges, $grand_total_adjustments, $grand_total_paid;
    global $grand_total_agedbal, $is_due_ins, $form_age_cols;
    global $initial_colspan, $final_colspan, $form_cb_idays, $form_cb_err;
    global $encounters;

    if (!$ptrow['pid']) {
        return;
    }

    $pt_balance = $ptrow['amount'] - $ptrow['paid'];

    if ($_POST['form_export']) {
        // This is a fixed-length format used by Transworld Systems.  Your
        // needs will surely be different, so consider this just an example.
        //
        echo "1896H"; // client number goes here
        echo "000";   // filler
        echo sprintf("%-30s", substr($ptrow['ptname'], 0, 30));
        echo sprintf("%-30s", " ");
        echo sprintf("%-30s", substr($ptrow['address1'], 0, 30));
        echo sprintf("%-15s", substr($ptrow['city'], 0, 15));
        echo sprintf("%-2s", substr($ptrow['state'], 0, 2));
        echo sprintf("%-5s", $ptrow['zipcode'] ? substr($ptrow['zipcode'], 0, 5) : '00000');
        echo "1";                      // service code
        echo sprintf("%010.0f", $ptrow['pid']); // transmittal number = patient id
        echo " ";                      // filler
        echo sprintf("%-15s", substr($ptrow['ss'], 0, 15));
        echo substr($ptrow['dos'], 5, 2) . substr($ptrow['dos'], 8, 2) . substr($ptrow['dos'], 2, 2);
        echo sprintf("%08.0f", $pt_balance * 100);
        echo sprintf("%-9s\n", " ");

        if (empty($_POST['form_without'])) {
            foreach ($encounters as $key => $item) {
                sqlStatement("UPDATE form_encounter SET in_collection = 1 WHERE encounter = ?", [$item]);
            }
        }

        $export_patient_count += 1;
        $export_dollars += $pt_balance;
    } elseif ($_POST['form_csvexport']) {
        $export_patient_count += 1;
        $export_dollars += $pt_balance;
    } else {
        if ($ptrow['count'] > 1 && !$is_due_ins) {
            echo " <tr bgcolor='" . attr($bgcolor) . "'>\n";
            /***************************************************************
          echo "  <td class='detail' colspan='$initial_colspan'>";
          echo "&nbsp;</td>\n";
          echo "  <td class='detotal' colspan='$final_colspan'>&nbsp;Total Patient Balance:</td>\n";
             ***************************************************************/
            echo "  <td class='detotal' colspan='" . attr(($initial_colspan + $final_colspan + 1)) .
                "'>&nbsp;" . xlt('Total Patient Balance') . ":</td>\n";
            /**************************************************************/
            if ($form_age_cols) {
                for ($c = 0; $c < $form_age_cols; ++$c) {
                    echo "  <td class='detotal' align='left'>&nbsp;" .
                        text(oeFormatMoney($ptrow['agedbal'][$c] ?? '')) . "&nbsp;</td>\n";
                }
            } else {
                echo "  <td class='detotal' align='left'>&nbsp;" .
                    text(oeFormatMoney($pt_balance)) . "&nbsp;</td>\n";
            }

            if ($form_cb_idays) {
                echo "  <td class='detail'>&nbsp;</td>\n";
            }

            echo "  <td class='detail' colspan='2'>&nbsp;</td>\n";
            if ($form_cb_err) {
                echo "  <td class='detail'>&nbsp;</td>\n";
            }

            echo " </tr>\n";
        }
    }

    $grand_total_charges     += $ptrow['charges'];
    $grand_total_adjustments += $ptrow['adjustments'];
    $grand_total_paid        += $ptrow['paid'];
    for ($c = 0; $c < $form_age_cols; ++$c) {
        $grand_total_agedbal[$c] += ($ptrow['agedbal'][$c] ?? null);
    }
}




function endInsurance($insrow)
{
    global $export_patient_count, $export_dollars, $bgcolor;
    global $grand_total_charges, $grand_total_adjustments, $grand_total_paid;
    global $grand_total_agedbal, $is_due_ins, $form_age_cols;
    global $initial_colspan, $form_cb_idays, $form_cb_err;
    if (!$insrow['pid']) {
        return;
    }

    $ins_balance = $insrow['amount'] - $insrow['paid'];
    if ($_POST['form_export'] || $_POST['form_csvexport']) {
        // No exporting of insurance summaries.
        $export_patient_count += 1;
        $export_dollars += $ins_balance;
    } else {
        echo " <tr bgcolor='" . attr($bgcolor) . "'>\n";
        echo "  <td class='detail'>" . text($insrow['insname']) . "</td>\n";
        echo "  <td class='detotal' align='left'>&nbsp;" .
            text(oeFormatMoney($insrow['charges'])) . "&nbsp;</td>\n";
        echo "  <td class='detotal' align='left'>&nbsp;" .
            text(oeFormatMoney($insrow['adjustments'])) . "&nbsp;</td>\n";
        echo "  <td class='detotal' align='left'>&nbsp;" .
            text(oeFormatMoney($insrow['paid'])) . "&nbsp;</td>\n";
        if ($form_age_cols) {
            for ($c = 0; $c < $form_age_cols; ++$c) {
                echo "  <td class='detotal' align='left'>&nbsp;" .
                    text(oeFormatMoney($insrow['agedbal'][$c])) . "&nbsp;</td>\n";
            }
        } else {
            echo "  <td class='detotal' align='left'>&nbsp;" .
                text(oeFormatMoney($ins_balance)) . "&nbsp;</td>\n";
        }

        echo " </tr>\n";
    }

    $grand_total_charges     += $insrow['charges'];
    $grand_total_adjustments += $insrow['adjustments'];
    $grand_total_paid        += $insrow['paid'];
    for ($c = 0; $c < $form_age_cols; ++$c) {
        $grand_total_agedbal[$c] += $insrow['agedbal'][$c];
    }
}

function getInsName($payerid)
{
    $tmp = sqlQuery("SELECT name FROM insurance_companies WHERE id = ? ", array($payerid));
    return $tmp['name'];
}

$ins_co_name = '';
function insuranceSelect()
{
    global $ins_co_name;
    $insurancei = getInsuranceProviders();
    if (!empty($_POST['form_csvexport'])) {
        foreach ($insurancei as $iid => $iname) {
            if ($iid == ($_POST['form_payer_id'] ?? null)) {
                $ins_co_name = $iname;
            }
        }
    } else {
        // added dropdown for payors (TLH)
        echo "   <select name='form_payer_id' class='form-control'>\n";
        echo "    <option value='0'>-- " . xlt('All') . " --</option>\n";
        foreach ($insurancei as $iid => $iname) {
            echo "<option value='" . attr($iid) . "'";
            if ($iid == ($_POST['form_payer_id'] ?? null)) {
                echo " selected";
            }
            echo ">" . text($iname) . "</option>\n";
            if ($iid == ($_POST['form_payer_id'] ?? null)) {
                $ins_co_name = $iname;
            }
        }
        echo "   </select>\n";
    }
}

$sqlArray = array();
if (!empty($_POST['form_refresh'])) {
    $_POST['download_csv'] = '1';
}



if (!empty($_POST['download_csv'])) {
    // ====== DB CONFIG ======
    $DB_HOST = '172.17.0.1:8320';
    $DB_NAME = 'openemr';
    $DB_USER = 'openemr';
    $DB_PASS = 'openemr';
    $DB_CHARSET = 'utf8mb4';

    // ====== OUTPUT CONFIG ======
    $OUTPUT_FORMAT = 'csv'; // 'csv' or 'xlsx'
    $CSV_PATH = __DIR__ . '/patient_billing_export.csv';
    $XLSX_PATH = __DIR__ . '/patient_billing_export.xlsx';

    // ====== CONNECT PDO ======
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);

    // ====== FETCH ALL PIDs ======
    $pidSql = "SELECT pid FROM patient_data ORDER BY pid";
    $pidStmt = $pdo->query($pidSql);
    $pids = $pidStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // ====== YOUR ORIGINAL QUERY (parameterized on :pid) ======
    // NOTE: Kept exactly as provided, only b.pid = :pid is parameterized.
    // If you encounter warning #1292 (empty string to INT), you can optionally add
    //   AND b.code <> ''
    // at the JOIN and correlated subqueries comparing codes — but we are not changing
    // your logic here.
    $sql = <<<SQL
SELECT f.id, f.date, f.pid,
       CONCAT(w.lname, ', ', w.fname) AS provider_id,
       f.encounter, f.last_level_billed, f.last_level_closed, f.last_stmt_date,
       f.stmt_count, f.invoice_refno, f.in_collection,
       p.fname, p.mname, p.lname, p.street, p.city, p.state, p.postal_code,
       p.phone_home, p.ss, p.billing_note, p.pubpid, p.DOB,
       CONCAT(u.lname, ', ', u.fname) AS referrer,
       (SELECT insurance_companies.name
          FROM insurance_data
          JOIN insurance_companies ON insurance_companies.id = insurance_data.provider
         WHERE insurance_data.type = 'primary'
           AND insurance_data.pid = p.pid
         LIMIT 1) AS insurance_name,
       (SELECT bill_date
          FROM billing AS b
         WHERE b.pid = f.pid
           AND b.encounter = f.encounter
           AND b.activity = 1
           AND b.code_type != 'COPAY'
         LIMIT 1) AS bill_date,
       (SELECT SUM(b.fee)
          FROM billing AS b
         WHERE b.pid = f.pid
           AND b.encounter = f.encounter
           AND b.activity = 1
           AND b.code_type != 'COPAY') AS charges,
       (SELECT SUM(b.fee)
          FROM billing AS b
         WHERE b.pid = f.pid
           AND b.encounter = f.encounter
           AND b.activity = 1
           AND b.code_type = 'COPAY') AS copays,
       (SELECT SUM(s.fee)
          FROM drug_sales AS s
         WHERE s.pid = f.pid
           AND s.encounter = f.encounter) AS sales,
       b.code,
       (SELECT SUM(a.pay_amount)
          FROM ar_activity AS a
         WHERE a.pid = f.pid
           AND a.encounter = f.encounter
           AND a.deleted IS NULL) AS payments,
       (SELECT SUM(a.adj_amount)
          FROM ar_activity AS a
         WHERE a.pid = f.pid
           AND a.encounter = f.encounter
           AND a.deleted IS NULL) AS adjustments,
       CASE WHEN b.billed = 1 THEN 'Billed' ELSE 'Not billed' END AS billing_status,
       b.fee AS chg,
       (SELECT a.pay_amount
          FROM ar_activity AS a
         WHERE a.encounter = b.encounter
           AND a.code = b.code
           AND a.pay_amount > 0
         LIMIT 1) AS paid,
       (SELECT a.adj_amount
          FROM ar_activity AS a
         WHERE a.encounter = b.encounter
           AND a.code = b.code
           AND a.adj_amount > 0
         LIMIT 1) AS adj
FROM billing b
JOIN patient_data AS p ON p.pid = b.pid
JOIN form_encounter AS f ON f.pid = p.pid
JOIN ar_activity ON ar_activity.encounter = b.encounter AND ar_activity.code = b.code
LEFT OUTER JOIN users AS u ON u.id = f.referring_provider_id
LEFT OUTER JOIN users AS w ON w.id = f.provider_id
WHERE ar_activity.deleted IS NULL
  AND b.code_type = 'CPT4'
  AND b.pid = :pid
GROUP BY ar_activity.code
SQL;

    $detailStmt = $pdo->prepare($sql);

    // ====== OUTPUT HANDLERS ======
    $headerWritten = false;
    $columnsOrder = [];

    if ($OUTPUT_FORMAT === 'csv') {
        $fp = fopen($CSV_PATH, 'w');
        // $fp = fopen($CSV_PATH, file_exists($CSV_PATH) ? 'a' : 'w');
        if (!$fp) {
            throw new RuntimeException('Unable to open CSV for writing: ' . $CSV_PATH);
        }
        // If appending to existing file that already has content, don't re-write header
        $headerWritten = (filesize($CSV_PATH) > 0);
    } elseif ($OUTPUT_FORMAT === 'xlsx') {

        if (file_exists($XLSX_PATH)) {
            $spreadsheet = IOFactory::load($XLSX_PATH);
            $sheet = $spreadsheet->getActiveSheet();
            $startRow = $sheet->getHighestRow(); // last used row
            $headerWritten = ($startRow >= 1);
        } else {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $startRow = 0;
        }
    } else {
        throw new InvalidArgumentException('Invalid $OUTPUT_FORMAT; use csv or xlsx');
    }

    // ====== MAIN LOOP ======
    $totalRows = 0;
    foreach ($pids as $pid) {
        $detailStmt->execute([':pid' => $pid]);

        while ($row = $detailStmt->fetch()) {
            // Lazily write header once based on first row's keys
            if (!$headerWritten) {
                $columnsOrder = array_keys($row);
                if ($OUTPUT_FORMAT === 'csv') {
                    fputcsv($fp, $columnsOrder);
                } else { // xlsx
                    $col = 1;
                    $hdrRow = 1;
                    foreach ($columnsOrder as $colName) {
                        $sheet->setCellValueByColumnAndRow($col++, $hdrRow, $colName);
                    }
                    $headerWritten = true;
                }
                $headerWritten = true;
            }

            // Ensure consistent column order
            if (empty($columnsOrder)) {
                $columnsOrder = array_keys($row);
            }
            $ordered = [];
            foreach ($columnsOrder as $k) {
                $ordered[] = $row[$k];
            }

            if ($OUTPUT_FORMAT === 'csv') {
                fputcsv($fp, $ordered);
            } else { // xlsx
                $startRow = $sheet->getHighestRow();
                $writeRow = max(2, $startRow + 1);
                $col = 1;
                foreach ($ordered as $val) {
                    $sheet->setCellValueByColumnAndRow($col++, $writeRow, $val);
                }
            }
            $totalRows++;
        }
    }
    // ====== SAVE FILE ======
    if ($OUTPUT_FORMAT === 'csv') {
        fclose($fp);
        $source = $CSV_PATH;
        chdir("../../sites/default/documents/temp/");
        $destination = getcwd() . "/" . basename($source);

        if (copy($source, $destination)) {
            echo "File copied successfully";
        } else {
            echo "Failed to copy file";
        }
        echo "CSV export complete: {$CSV_PATH} (rows: {$totalRows})\n";
        unlink($source);
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "alab_ar_report_export_{$timestamp}.csv";
        if (file_exists($destination)) {
            // Set headers to force download

            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($destination));

            // Clear output buffer
            ob_clean();
            flush();

            // Read and output file
            readfile($destination);
            exit;
        } else {
            echo "File not found.";
        }
    } else {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($XLSX_PATH);
        echo "XLSX export complete: {$XLSX_PATH} (rows: {$totalRows})\n";
    }


} else {
?>
    <html>

    <head>

        <title><?php echo xlt('Alab AR Report') ?></title>

        <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

        <style>
            @media print {
                #report_parameters {
                    visibility: hidden;
                    display: none;
                }

                #report_parameters_daterange {
                    visibility: visible;
                    display: inline;
                }

                #report_results {
                    margin-top: 30px;
                }
            }

            /* specifically exclude some from the screen */
            @media screen {
                #report_parameters_daterange {
                    visibility: hidden;
                    display: none;
                }
            }
        </style>


        <script>
            function reSubmit() {
                $("#form_refresh").attr("value", "true");
                $("#form_export").val("");
                $("#form_csvexport").val("");
                $("#form_clear_ins_debt").val("");
                $("#theform").submit();
            }
            // open dialog to edit an invoice w/o opening encounter.
            function editInvoice(e, id) {
                e.stopPropagation();
                e.preventDefault();
                $("#form_page_y").val(e.pageY);
                $("#form_offset_y").val(e.offsetY);
                $("#form_y").val(e.y);
                let url = './../billing/sl_eob_invoice.php?id=' + encodeURIComponent(id);
                dlgopen(url, '', 'modal-lg', 750, false, '', {
                    onClosed: 'reSubmit'
                });
            }

            function toEncounter(newpid, enc) {
                top.restoreSession();
                top.RTop.location = "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid) + "&set_encounterid=" + encodeURIComponent(enc);
            }

            $(function() {
                let Y = parseFloat($("#form_page_y").val()) - parseFloat($("#form_offset_y").val()) - parseFloat($("#form_y").val());
                $("html, body").animate({
                    scrollTop: Y
                }, 800);
            });

            $(function() {
                oeFixedHeaderSetup(document.getElementById('mymaintable'));
                var win = top.printLogSetup ? top : opener.top;
                win.printLogSetup(document.getElementById('printbutton'));

                $('.datepicker').datetimepicker({
                    <?php $datetimepicker_timepicker = false; ?>
                    <?php $datetimepicker_showseconds = false; ?>
                    <?php $datetimepicker_formatInput = true; ?>
                    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma 
                    ?>
                });
            });

            function checkAll(checked) {
                var f = document.forms[0];
                for (var i = 0; i < f.elements.length; ++i) {
                    var ename = f.elements[i].name;
                    if (ename.indexOf('form_cb[') == 0)
                        f.elements[i].checked = checked;
                }
            }
        </script>

    </head>

    <body class="body_top">

        <span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('ALAB AR Report'); ?></span>

        <form method='post' action='alab_ar_report.php' enctype='multipart/form-data' id='theform' onsubmit='return top.restoreSession()'>
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
            <input type="hidden" name="download_csv" value="1">

            <div id="report_parameters">
                <input type='hidden' name='form_refresh' id='form_refresh' value='' />

                <!-- HAROON_CHANGE_2_START_08262025 -->

                <input type='hidden' name='unbilled' value='<?php echo attr($form_unbilled == "0" ? "on" : ""); ?>' />

                <!-- HAROON_CHANGE_2_END_08262025  -->


                <table>

                    <tr>
                        <td hidden class='col-form-label'>
                            <?php echo xlt('Service Date'); ?>:
                        </td>
                        <td>
                            <input hidden type='text' class='datepicker form-control' name='form_date' id="form_date" size='10' value='<?php echo attr(oeFormatShortDate($form_date)); ?>'>
                        </td>
                        <td hidden class='col-form-label'>
                            <?php echo xlt('To{{Range}}'); ?>:
                        </td>
                        <td>
                            <input hidden type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
                        </td>



                    </tr>

                </table>
                <div class="text-center">
                    <div class="btn-group" role="group">
                        <a href='#' class='btn btn-secondary btn-save' onclick='$("#form_refresh").attr("value","true"); $("#form_csvexport").val(""); $("#form_export").val(""); $("#form_clear_ins_debt").val(""); $("#theform").submit();'>
                            <?php echo xlt('Generate AR Activity Report'); ?>
                        </a>
                        <?php if (!empty($_POST['form_refresh'])) { ?>
                            <a href='#' class='btn btn-secondary btn-print' onclick='window.print()'>
                                <?php echo xlt('Print'); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>


            </div>

            </td>
            <td align='left' valign='middle' height="100%">
                <table style='border-left:1px solid; width:100%; height:100%'>
                    <tr>
                        <td>

                        </td>
                    </tr>
                </table>
            </td>
            </tr>
            </table>
            </div>
        </form>
        <iframe name="downloadFrame" style="display:none;"></iframe>

        <script>
            <?php
            if ($alertmsg) {
                echo "alert(" . js_escape($alertmsg) . ");\n";
            }
            ?>

            // function downloadCSV() {
            //     document.getElementById('downloadForm').submit();
            // }
        </script>
    </body>

    </html>
<?php
} // end not form_csvexport
?>