<?php
/*
 * Payments can be edited here whch includes deletion of an allocation, modifying the
 * same or adding a new allocation. Log is kept for the deleted ones.
 * The functions of this class support the billing process like the script billing_process.php.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Eldho Chacko <eldho@zhservices.com>
 * @author    Paul Simon K <paul@zhservices.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2010 Z&H Consultancy Services Private Limited <sam@zhservices.com>
 * @copyright Copyright (C) 2018-2020 Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2019-2020 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2020 Rod Roark <rod@sunsetsystems.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


global $TypeCode, $srcdir;
require_once("../globals.php");
require_once("../../custom/code_types.inc.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/payment.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('acct', 'bill', '', 'write') && !AclMain::aclCheckCore('acct', 'eob', '', 'write')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Confirm Payment")]);
    exit;
}

$screen = 'edit_payment';

// Deletion of payment distribution code
// echo "<pre>";
// print_r($_POST); die;
if (isset($_POST["mode"])) {
    // ************** HAROON INSURANCE CHANGE 09032025 START ***************
    $pid = $_POST['hidden_patient_code'];
    $noInsurance = false;
    $primaryInsurance = false;
    $secondaryInsurance = false;
    $tertiaryInsurance = false;
    $insuranceCheckerQuery = "select id.type from patient_data pd
                              inner JOIN insurance_data id on pd.pid=id.pid
                              inner join insurance_companies ic on id.provider =ic.id
                              where pd.pid=" .
        $pid;
    $resultSet = sqlStatement($insuranceCheckerQuery);
    if ($resultSet && sqlNumRows($resultSet) > 0) {
        while ($row = sqlFetchArray($resultSet)) {
            // echo "Complete row data:<br>";
            // print_r($row)['type']; // See all columns
            // echo "<hr>";
            if ($row['type']  == "primary") {
                $primaryInsurance = true;
            } else if ($row['type'] == "secondary") {
                $secondaryInsurance = true;
            } else if ($row['type'] == "tertiary") {
                $tertiaryInsurance = true;
            } else {
                $noInsurance = true;
            }
        }
    }

    else {
        $noInsurance = true;
    }
    // ************** HAROON INSURANCE CHANGE 09032025 END ***************

    if ($_POST["mode"] == "DeletePaymentDistribution") {
        $DeletePaymentDistributionId = (isset($_POST['DeletePaymentDistributionId']) ? trim($_POST['DeletePaymentDistributionId']) : '');
        $DeletePaymentDistributionIdArray = explode('_', $DeletePaymentDistributionId);
        $payment_id = $DeletePaymentDistributionIdArray[0];
        $PId = $DeletePaymentDistributionIdArray[1];
        $Encounter = $DeletePaymentDistributionIdArray[2];
        $Code = $DeletePaymentDistributionIdArray[3];
        $Modifier = $DeletePaymentDistributionIdArray[4];
        $Codetype = $DeletePaymentDistributionIdArray[5];
        //delete and log that action
        row_modify(
            "ar_activity",
            "deleted = NOW()",
            "session_id = '" . add_escape_custom($payment_id) . "' AND " .
            "pid = '" . add_escape_custom($PId) . "' AND " .
            "deleted IS NULL AND " .
            "encounter = '" . add_escape_custom($Encounter) . "' AND " .
            "code_type = '" . add_escape_custom($Codetype) . "' AND " .
            "code = '" . add_escape_custom($Code) . "' AND " .
            "modifier='" . add_escape_custom($Modifier) . "'"
        );
        $Message = 'Delete';
        //------------------
        $_POST["mode"] = "searchdatabase";
    }
}

//===============================================================================
//Modify Payment Code.
//===============================================================================

if (isset($_POST["mode"])) {
    if ($_POST["mode"] == "ModifyPayments" || $_POST["mode"] == "FinishPayments") {
        $payment_id = $_REQUEST['payment_id'];
        //ar_session Code
        //===============================================================================
        if (trim($_POST['type_name']) == 'insurance') {
            $QueryPart = "payer_id = '" . trim(formData('hidden_type_code')) .
                "', patient_id = '" . 0;
        } elseif (trim($_POST['type_name']) == 'patient') {
            $QueryPart = "payer_id = '" . 0 .
                "', patient_id = '" . trim(formData('hidden_type_code'));
        }

        $user_id = $_SESSION['authUserID'];
        $closed = 0;
        $modified_time = date('Y-m-d H:i:s');
        $check_date = DateToYYYYMMDD(formData('check_date'));
        $deposit_date = DateToYYYYMMDD(formData('deposit_date'));
        $post_to_date = DateToYYYYMMDD(formData('post_to_date'));
        if ($post_to_date == '') {
            $post_to_date = date('Y-m-d');
        }

        if ($_POST['deposit_date'] == '') {
            $deposit_date = $post_to_date;
        }

        $global_account = "";
        if (formData('global_reset') == '-0.00') {
            $global_account = "', global_amount = '" . trim(formData('global_reset'));
        }

        sqlStatement("update ar_session set " .
            $QueryPart .
            "', user_id = '" . trim(add_escape_custom($user_id)) .
            "', closed = '" . trim(add_escape_custom($closed)) .
            "', reference = '" . trim(formData('check_number')) .
            "', check_date = '" . trim(add_escape_custom($check_date)) .
            "', deposit_date = '" . trim(add_escape_custom($deposit_date)) .
            "', pay_total = '" . trim(formData('payment_amount')) .
            "', modified_time = '" . trim(add_escape_custom($modified_time)) .
            $global_account .
            "', payment_type = '" . trim(formData('type_name')) .
            "', description = '" . trim(formData('description')) .
            "', adjustment_code = '" . trim(formData('adjustment_code')) .
            "', post_to_date = '" . trim(add_escape_custom($post_to_date)) .
            "', payment_method = '" . trim(formData('payment_method')) .
            "'    where session_id='" . add_escape_custom($payment_id) . "'");

        //===============================================================================
        $CountIndexAbove = $_REQUEST['CountIndexAbove'];
        $CountIndexBelow = $_REQUEST['CountIndexBelow'];
        $hidden_patient_code = $_REQUEST['hidden_patient_code'];
        $user_id = $_SESSION['authUserID'];
        $created_time = date('Y-m-d H:i:s');
        //==================================================================
        //UPDATION
        //It is done with out deleting any old entries.
        //==================================================================
        $hiddenInsKeys = preg_grep('/^HiddenIns\d+$/', array_keys($_POST));

        $hasFive = false;
        foreach ($hiddenInsKeys as $k) {
            if ((string)$_POST[$k] === '5') {
                $hasFive = true;
                break;
            }
        }

        if ($hasFive) {
            foreach ($hiddenInsKeys as $k) {
                $_POST[$k] = '1';
            }
        }
        for ($CountRow = 1; $CountRow <= $CountIndexAbove; $CountRow++) {
            if (isset($_POST["HiddenEncounter$CountRow"])) {
                $where1 = "WHERE deleted IS NULL AND session_id = '" . add_escape_custom($payment_id) .
                    "' AND pid ='" . trim(formData("HiddenPId$CountRow")) .
                    "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                    "' AND code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                    "' AND code = '" . trim(formData("HiddenCode$CountRow")) .
                    "' AND modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                    "'";

                if($hasFive){
                    sqlStatement(
                        "UPDATE form_encounter SET last_level_billed = 0, " .
                        "last_level_closed = 0, stmt_count = 0, last_stmt_date = NULL " .
                        "WHERE pid = ? AND encounter = ?",
                        array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow")))
                    );
                }


                $where = "$where1 AND pay_amount > 0";
                if (!empty($_POST["Payment$CountRow"])) {
                    if (trim($_POST['type_name']) == 'insurance') {
                        if (trim($_POST["HiddenIns$CountRow"]) == 1) {
                            $AccountCode = "IPP";
                        }
                        if (trim($_POST["HiddenIns$CountRow"]) == 2) {
                            $AccountCode = "ISP";
                        }
                        if (trim($_POST["HiddenIns$CountRow"]) == 3) {
                            $AccountCode = "ITP";
                        }
                    } elseif (trim($_POST['type_name']) == 'patient') {
                        $AccountCode = "PP";
                    }
                    $resPayment = sqlStatement("SELECT * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("UPDATE ar_activity SET deleted = NOW() $where");
                    }
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment " .
                        "FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', reason_code = '" . trim(formData("ReasonCode$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . trim(formData("Payment$CountRow")) .
                        "', adj_amount = '" . 0 .
                        "', account_code = '" . add_escape_custom($AccountCode) .
                        "'");

                    sqlCommitTrans();
                } else {
                    sqlStatement("UPDATE ar_activity SET deleted = NOW() $where");
                }

                //==============================================================================================================================

                $where = "$where1 AND adj_amount != 0";
                if (isset($_POST["AdjAmount$CountRow"]) && floatval($_POST["AdjAmount$CountRow"]) !== 0) {
                    if (trim($_POST['type_name']) == 'insurance') {
                        $AdjustString = "Ins adjust Ins" . trim($_POST["HiddenIns$CountRow"]);
                        $AccountCode = "IA";
                    } elseif (trim($_POST['type_name']) == 'patient') {
                        $AdjustString = "Pt adjust";
                        $AccountCode = "PA";
                    }
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . 0 .
                        "', adj_amount = '" . trim(formData("AdjAmount$CountRow")) .
                        "', memo = '" . add_escape_custom($AdjustString) .
                        "', account_code = '" . add_escape_custom($AccountCode) .
                        "'");

                    sqlCommitTrans();
                } else {
                    sqlStatement("update ar_activity set deleted = NOW() $where");
                }

                //==============================================================================================================================

                $where = "$where1 AND (memo LIKE 'Deductable%' OR memo LIKE 'Deductible%')";
                if (!empty($_POST["Deductible$CountRow"])) {
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");

                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . 0 .
                        "', adj_amount = '" . 0 .
                        "', memo = '" . "Deductible $" . trim(formData("Deductible$CountRow")) .
                        "', account_code = '" . "Deduct" .
                        "'");
                    sqlCommitTrans();
                } else {
                    sqlStatement("delete from ar_activity $where");
                }

                $where = "$where1 AND (memo LIKE 'Co-ins%' OR memo LIKE 'Co-ins%')";
                if (!empty($_POST["Co-ins$CountRow"])) {
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    echo "<script> console.log('Im here 4');</script>";
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . 0 .
                        "', adj_amount = '" . 0 .
                        "', memo = '" . "Co-ins $" . trim(formData("Co-ins$CountRow")) .
                        "', account_code = '" . "Deduct" .
                        "'");
                    sqlCommitTrans();
                } else {
                    sqlStatement("delete from ar_activity $where");
                }

                $where = "$where1 AND (memo LIKE 'Total%' OR memo LIKE 'Total%')";
                if (!empty($_POST["Total$CountRow"])) {
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    echo "<script> console.log('Im here 5');</script>";
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . 0 .
                        "', adj_amount = '" . 0 .
                        "', memo = '" . "Total $" . trim((float) formData("Co-ins$CountRow")+(float) formData("Deductible$CountRow")) .
                        "', account_code = '" . "Deduct" .
                        "'");
                    sqlCommitTrans();
                } else {
                    sqlStatement("delete from ar_activity $where");
                }

                //==============================================================================================================================

                $where = "$where1 AND pay_amount < 0";
                if (!empty($_POST["Takeback$CountRow"])) {
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    echo "<script> console.log('Im here 6');</script>";
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . trim(formData("Takeback$CountRow")) * -1 .
                        "', adj_amount = '" . 0 .
                        "', account_code = '" . "Takeback" .
                        "'");
                    sqlCommitTrans();
                } else {
                    sqlStatement("delete from ar_activity $where");
                }

                //==============================================================================================================================

                $where = "$where1 AND follow_up = 'y'";
                if (isset($_POST["FollowUp$CountRow"]) && $_POST["FollowUp$CountRow"] == 'y') {
                    $resPayment = sqlStatement("SELECT  * from ar_activity $where");
                    if (sqlNumRows($resPayment) > 0) {
                        sqlStatement("update ar_activity set deleted = NOW() $where");
                    }
                    sqlBeginTrans();
                    $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = '" . trim(formData("HiddenPId$CountRow")) . "' AND encounter = '" . trim(formData("HiddenEncounter$CountRow")) . "'");
                    sqlStatement("insert into ar_activity set " .
                        "pid = '" . trim(formData("HiddenPId$CountRow")) .
                        "', encounter = '" . trim(formData("HiddenEncounter$CountRow")) .
                        "', sequence_no = '" . add_escape_custom($sequence_no['increment']) .
                        "', code_type = '" . trim(formData("HiddenCodetype$CountRow")) .
                        "', code = '" . trim(formData("HiddenCode$CountRow")) .
                        "', modifier = '" . trim(formData("HiddenModifier$CountRow")) .
                        "', payer_type = '" . trim(formData("HiddenIns$CountRow")) .
                        "', post_time = '" . trim(add_escape_custom($created_time)) .
                        "', post_user = '" . trim(add_escape_custom($user_id)) .
                        "', session_id = '" . trim(formData('payment_id')) .
                        "', modified_time = '" . trim(add_escape_custom($created_time)) .
                        "', pay_amount = '" . 0 .
                        "', adj_amount = '" . 0 .
                        "', follow_up = '" . "y" .
                        "', follow_up_note = '" . trim(formData("FollowUpReason$CountRow")) .
                        "'");
                    sqlCommitTrans();
                } else {
                    sqlStatement("delete from ar_activity $where");
                }

                //==============================================================================================================================
            } else {
                break;
            }
        }

        //=========
        //INSERTION of new entries,continuation of modification.
        //=========
        $hiddenInsKeysNew = preg_grep('/^HiddenIns\d+$/', array_keys($_POST));
        $hasFiveNew = false;
        foreach ($hiddenInsKeysNew as $k) {
            if ((string)$_POST[$k] === '5') {
                $hasFive = true;
                break;
            }
        }

        if ($hasFiveNew) {
            foreach ($hiddenInsKeysNew as $k) {
                $_POST[$k] = '1';
            }
        }
        for ($CountRow = $CountIndexAbove + 1; $CountRow <= $CountIndexAbove + $CountIndexBelow; $CountRow++) {
            if (isset($_POST["HiddenEncounter$CountRow"])) {
                if($hasFiveNew){
                    sqlStatement(
                        "UPDATE form_encounter SET last_level_billed = 0, " .
                        "last_level_closed = 0, stmt_count = 0, last_stmt_date = NULL " .
                        "WHERE pid = ? AND encounter = ?",
                        array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow")))
                    );
                }
                DistributionInsert($CountRow, $created_time, $user_id);
            } else {
                break;
            }
        }

        if ($_REQUEST['global_amount'] == 'yes') {
            sqlStatement(
                "update ar_session set global_amount=? where session_id =?",
                [(isset($_POST["HidUnappliedAmount"]) ? floatval($_POST["HidUnappliedAmount"]) : 0), $payment_id]
            );
        } elseif ($_REQUEST['global_reset'] == '-0.00') {
            sqlStatement("update ar_session set global_amount=? where session_id =?", [0, $payment_id]);
        }

        if ($_POST["mode"] == "FinishPayments") {
            $Message = 'Finish';
        }

        $_POST["mode"] = "searchdatabase";
        $Message = 'Modify';
    }
}

//==============================================================================
//Search Code
//===============================================================================
$payment_id = !empty($payment_id) ? (int)$payment_id : (int)$_REQUEST['payment_id'];
$ResultSearchSub = sqlStatement(
    "SELECT DISTINCT encounter, code_type, code, modifier, pid " .
    "FROM ar_activity WHERE deleted IS NULL AND session_id = ? " .
    "ORDER BY pid, encounter, code, modifier",
    [$payment_id]
);

//==============================================================================

//==============================================================================
//===============================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Confirm Payment'); ?></title>
    <?php Header::setupHeader(['datetime-picker', 'common']); ?>

    <?php include_once("{$GLOBALS['srcdir']}/payment_jav.inc.php"); ?>
    <?php include_once("{$GLOBALS['srcdir']}/ajax/payment_ajax_jav.inc.php"); ?>
    <script>


        function ModifyPayments() {//Used while modifying the allocation
            if (!FormValidations())//FormValidations contains the form checks
            {
                return false;
            }
            if (CompletlyBlankAbove())//The distribution rows already in the database are checked.
            {
                alert(<?php echo xlj('None of the Top Distribution Row Can be Completly Blank.'); ?> +"\n" + <?php echo xlj('Use Delete Option to Remove.'); ?>);
                return false;
            }
            if (!CheckPayingEntityAndDistributionPostFor()) {
                //Ensures that Insurance payment is distributed under Ins1,Ins2,Ins3 and Patient paymentat under Pat.
                return false;
            }
            if (CompletlyBlankBelow()) {
                //The newly added distribution rows are checked.
                alert(<?php echo xlj('Fill any of the Below Row.'); ?>);
                return false;
            }
            let PostValue = CheckUnappliedAmount();//Decides TdUnappliedAmount >0, or <0 or =0
            if (PostValue == 1) {
                alert(<?php echo xlj('Cannot Modify Payments.Undistributed is Negative.'); ?>);
                return false;
            }
            dialog.confirm(<?php echo xlj('Would you like to Modify Payments?'); ?>).then(returned => {
                if (returned === true) {
                    document.getElementById('mode').value = 'ModifyPayments';
                    top.restoreSession();
                    document.forms[0].submit();
                } else {
                    dialog.close();
                    return false;
                }
            });
        }

        function FinishPayments() {
            if (!FormValidations())//FormValidations contains the form checks
            {
                return false;
            }
            if (CompletlyBlankAbove())//The distribution rows already in the database are checked.
            {
                alert(<?php echo xlj('None of the Top Distribution Row Can be Completly Blank.'); ?> +"\n" + <?php echo xlj('Use Delete Option to Remove.'); ?>);
                return false;
            }
            if (!CheckPayingEntityAndDistributionPostFor())//Ensures that Insurance payment is distributed under Ins1,Ins2,Ins3 and Patient paymentat under Pat.
            {
                return false;
            }
            if (CompletlyBlankBelow())//The newly added distribution rows are checked.
            {
                alert(<?php echo xlj('Fill any of the Below Row.'); ?>);
                return false;
            }
            let PostValue = CheckUnappliedAmount();//Decides TdUnappliedAmount >0, or <0 or =0
            if (PostValue == 1) {
                alert(<?php echo xlj('Cannot Modify Payments.Undistributed is Negative.'); ?>);
                return false;
            }
            if (PostValue == 2) {
                if (confirm(<?php echo xlj('Would you like to Modify and Finish Payments?'); ?>)) {
                    UnappliedAmount = document.getElementById('TdUnappliedAmount').innerHTML * 1;
                    if (confirm(<?php echo xlj('Undistributed is'); ?> +' ' + UnappliedAmount + '.' + '\n' + <?php echo xlj('Would you like the balance amount to apply to Global Account?'); ?>)) {
                        document.getElementById('mode').value = 'FinishPayments';
                        document.getElementById('global_amount').value = 'yes';
                        top.restoreSession();
                        document.forms[0].submit();
                    } else {
                        document.getElementById('mode').value = 'FinishPayments';
                        top.restoreSession();
                        document.forms[0].submit();
                    }
                } else
                    return false;
            } else {
                if (confirm(<?php echo xlj('Would you like to Modify and Finish Payments?'); ?>)) {
                    document.getElementById('mode').value = 'FinishPayments';
                    top.restoreSession();
                    document.forms[0].submit();
                } else
                    return false;
            }

        }

        function CompletlyBlankAbove() {//The distribution rows already in the database are checked.
            //It is not allowed to be made completly empty.If needed delete option need to be used.
            let CountIndexAbove = document.getElementById('CountIndexAbove').value * 1;
            for (RowCount = 1; RowCount <= CountIndexAbove; RowCount++) {
                if (document.getElementById('Allowed' + RowCount).value == '' && document.getElementById('Payment' + RowCount).value == '' && document.getElementById('AdjAmount' + RowCount).value == '' && document.getElementById('Deductible' + RowCount).value == '' && document.getElementById('Takeback' + RowCount).value == '' && document.getElementById('FollowUp' + RowCount).checked == false) {
                    return true;
                }
            }
            return false;
        }

        function CompletlyBlankBelow() {//The newly added distribution rows are checked.
            //It is not allowed to be made completly empty.
            let CountIndexAbove = document.getElementById('CountIndexAbove').value * 1;
            let CountIndexBelow = document.getElementById('CountIndexBelow').value * 1;
            if (CountIndexBelow == 0)
                return false;
            for (RowCount = CountIndexAbove + 1; RowCount <= CountIndexAbove + CountIndexBelow; RowCount++) {
                if (document.getElementById('Allowed' + RowCount).value == '' && document.getElementById('Payment' + RowCount).value == '' && document.getElementById('AdjAmount' + RowCount).value == '' && document.getElementById('Deductible' + RowCount).value == '' && document.getElementById('Takeback' + RowCount).value == '' && document.getElementById('FollowUp' + RowCount).checked == false) {

                } else
                    return false;
            }
            return true;
        }

        function OnloadAction() {//Displays message while loading after some action.
            let after_value = document.getElementById('ActionStatus').value;
            if (after_value == 'Delete') {
                alert(<?php echo xlj('Successfully Deleted'); ?>);
                return true;
            }
            if (after_value == 'Modify' || after_value == 'Finish') {
                alert(<?php echo xlj('Successfully Modified'); ?>);
                return true;
            }
            after_value = document.getElementById('after_value').value;
            let payment_id = document.getElementById('payment_id').value;
            if (after_value == 'distribute') {
            } else if (after_value == 'new_payment') {
                if (document.getElementById('TablePatientPortion')) {
                    document.getElementById('TablePatientPortion').style.display = 'none';
                }
                if (confirm(<?php echo xlj('Successfully Saved.Would you like to Distribute?'); ?>)) {
                    if (document.getElementById('TablePatientPortion')) {
                        document.getElementById('TablePatientPortion').style.display = '';
                    }
                }
            }

        }

        function DeletePaymentDistribution(DeleteId) {//Confirms deletion of payment distribution.
            if (confirm(<?php echo xlj('Would you like to Delete Payment Distribution?'); ?>)) {
                document.getElementById('mode').value = 'DeletePaymentDistribution';
                document.getElementById('DeletePaymentDistributionId').value = DeleteId;
                top.restoreSession();
                document.forms[0].submit();
            } else
                return false;
        }

        //========================================================================================

        $(function () {
            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        });

        document.onclick = HideTheAjaxDivs;
    </script>
    <style>
        .amt_input {
            max-width: 75px;
        }

        .bottom {
            border-bottom: 1px solid var(--black);
        }

        .top {
            border-top: 1px solid var(--black);
        }

        .left {
            border-left: 1px solid var(--black);
        }

        .right {
            border-right: 1px solid var(--black);
        }

        .form-group {
            margin-bottom: 5px;
        }

        legend {
            border-bottom: 2px solid #E5E5E5;
            background: #E5E5E5;
            padding-left: 10px;
        }

        .form-horizontal .control-label {
            padding-top: 2px;
        }

        fieldset {
            border-color: #68171A !important;
            background-color: #f2f2f2;
            margin-bottom: 10px;
            padding-bottom: 15px;
        }

        @media only screen and (max-width: 768px) {
            [class*="col-"] {
                width: 100%;
                text-align: left !important;
            }
        }
    </style>
    <?php Header::setupHeader(['datetime-picker', 'common']); ?>
</head>
<body class="body_top" onload="OnloadAction()">
<div class="container-fluid">
    <?php
    if ($_REQUEST['ParentPage'] ?? '' == 'new_payment') {
        ?>
        <div class="row">
            <div class="col-12">
                <h2><?php echo xlt('Payments'); ?></h2>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <nav class="navbar navbar-nav navbar-expand-md navbar-light text-body bg-light static-top">
                    <button class="navbar-toggler icon-bar" data-target="#myNavbar" data-toggle="collapse"
                            type="button"><span class="navbar-toggler-icon"></span></button>
                    <div class="collapse navbar-collapse" id="myNavbar">
                        <ul class="navbar-nav mr-auto">
                            <li class="nav-item active">
                                <a class="nav-link font-weight-bold"
                                   href='new_payment.php'><?php echo xlt('New Payment'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link font-weight-bold"
                                   href='search_payments.php'><?php echo xlt('Search Payment'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link font-weight-bold"
                                   href='era_payments.php'><?php echo xlt('ERA Posting'); ?></a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
        <?php
    }
    ?>
    <?php
    if (empty($payment_id)) {
        $onclick = "top.restoreSession();return SavePayment();";
    } else {
        $onclick = "return false;";
    }
    ?>

    <form class="form" name='new_payment' method='post' action="edit_payment.php" onsubmit='<?php echo $onclick; ?>'>
        <?php
        if (!empty($payment_id)) { ?>
        <fieldset>
            <?php
            require_once("payment_master.inc.php");  //Check/cash details are entered here.
            ?>

            <?php }//End of if($payment_id*1>0) ?>
            <?php


            if (!empty($payment_id)) {//Distribution rows already in the database are displayed.
                ?>
                <?php //
                $resCount = sqlStatement(
                    "SELECT DISTINCT encounter, code_type, code, modifier FROM ar_activity " .
                    "WHERE deleted IS NULL AND session_id = ?",
                    [$payment_id]
                );
                $TotalRows = sqlNumRows($resCount);
                $CountPatient = 0;
                $CountIndex = 0;
                $CountIndexAbove = 0;
                $paymenttot = 0;
                $adjamttot = 0;
                $deductibletot = 0;

                $coInsuranceVal = 0;
                $coInsurancetot = 0;
                $deductibleCopay = 0;
                $TotalAmt_t = 0;
                $takebacktot = 0;
                $allowedtot = 0;
                if ($RowSearchSub = sqlFetchArray($ResultSearchSub)) {

                    do {
                        $CountPatient++;
                        $PId = $RowSearchSub['pid'];
                        $EncounterMaster = $RowSearchSub['encounter'];
                        // Only use the code_type in the queries below if it is specified in the ar_activity table.
                        // If it is not specified in the ar_activity table, also note it is not requested from the
                        // billing table in below query, thus making it blank in all queries below in this script.
                        $CodetypeMaster = $RowSearchSub['code_type'];
                        $sql_select_part_codetype = "";
                        $sql_where_part_codetype = "";
                        if (!empty($CodetypeMaster)) {
                            $sql_select_part_codetype = "billing.code_type,";
                            $sql_where_part_codetype = "and billing.code_type ='" . add_escape_custom($CodetypeMaster) . "'";
                        }
                        $CodeMaster = $RowSearchSub['code'];
                        $ModifierMaster = $RowSearchSub['modifier'];
                        $res = sqlStatement("SELECT fname,lname,mname FROM patient_data where pid =?", [$PId]);
                        $row = sqlFetchArray($res);
                        $fname = $row['fname'];
                        $lname = $row['lname'];
                        $mname = $row['mname'];
                        $NameDB = $lname . ' ' . $fname . ' ' . $mname;
                        $ResultSearch = sqlStatement("SELECT billing.id,last_level_closed,billing.encounter,form_encounter.`date`,$sql_select_part_codetype billing.code,billing.modifier,fee
                             FROM billing ,form_encounter
                             where billing.encounter=form_encounter.encounter and billing.pid=form_encounter.pid and
                             code_type !='ICD9' and  code_type !='COPAY' and billing.activity !=0 and
                             form_encounter.pid ='" . add_escape_custom($PId) . "' and billing.pid ='" . add_escape_custom($PId) . "' and billing.encounter ='" . add_escape_custom($EncounterMaster) . "'
                             $sql_where_part_codetype
                             and billing.code ='" . add_escape_custom($CodeMaster) . "'
                             and billing.modifier ='" . add_escape_custom($ModifierMaster) . "'
                             ORDER BY form_encounter.`date`,form_encounter.encounter,billing.code,billing.modifier");
                        if (sqlNumRows($ResultSearch) > 0) {
                            if ($CountPatient === 1) {
                                $Table = 'yes';
                                ?>
                                <br/><br/>
                                <div class="row" id="tableRow">
                                <legend><?php echo xlt("Distributed Edits") ?></legend>
                                <div class="table-responsive-lg">
                                <table class="table table-sm table-bordered table-light" id="TableDistributedEdit" >
                                <thead class="bg-dark text-light">
                                <tr>
                                    <th>&nbsp;</th>
                                    <th><?php echo xlt('Patient Name'); ?></th>
                                    <th style="width: 100px;"><?php echo xlt('Post For'); ?></th>
                                    <th><?php echo xlt('Service Date'); ?></th>
                                    <th><?php echo xlt('Enc#'); ?></th>
                                    <th><?php echo xlt('Code'); ?></th>
                                    <th><?php echo xlt('Charge'); ?></th>
                                    <th><?php echo xlt('Copay'); ?></th>
                                    <th><?php echo xlt('Bal-Due'); ?></th>
                                    <th><?php echo xlt('Allowed(c)'); ?></th>
                                    <!-- (c) means it is calculated.Not stored one. -->
                                    <th><?php echo xlt('Payment'); ?></th>
                                    <th><?php echo xlt('Adj Amount'); ?></th>
                                    <th><?php echo xlt('Deductible/Copay'); ?></th>
                                    <th><?php echo xlt('Co-ins'); ?></th>
                                    <th style="display: none;"><?php echo xlt('Total'); ?></th>
                                    <th><?php echo xlt('Takeback'); ?></th>
                                    <th><?php echo xlt('MSP'); ?></th>
                                    <th><?php echo xlt('Resn'); ?></th>
                                    <th><?php echo xlt('Follow Up Reason'); ?></th>
                                </tr>
                                </thead>
                            <?php }
                            while ($RowSearch = sqlFetchArray($ResultSearch)) {
                                $CountIndex++;
                                $CountIndexAbove++;
                                $ServiceDateArray = explode(' ', $RowSearch['date']);
                                $ServiceDate = oeFormatShortDate($ServiceDateArray[0]);
                                $Codetype = $RowSearch['code_type'];
                                $Code = $RowSearch['code'];
                                $Modifier = $RowSearch['modifier'];
                                if ($Modifier != '') {
                                    $ModifierString = ", $Modifier";
                                } else {
                                    $ModifierString = "";
                                }
                                $Fee = $RowSearch['fee'];
                                $Encounter = $RowSearch['encounter'];

                                $resPayer = sqlStatement(
                                    "SELECT payer_type FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? " .
                                    "AND code_type = ? AND code = ? AND modifier = ?",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayer = sqlFetchArray($resPayer);
                                $Ins = $rowPayer['payer_type'];

                                //Always associating the copay to a particular charge.
                                $BillingId = $RowSearch['id'];
                                $resId = sqlStatement("SELECT id FROM billing where code_type != 'ICD9' and code_type != 'COPAY' and
                                    pid =? and encounter =? and billing.activity!=0 order by id", [$PId, $Encounter]);
                                $rowId = sqlFetchArray($resId);
                                $Id = $rowId['id'];

                                if ($BillingId != $Id) {//multiple cpt in single encounter
                                    $Copay = 0.00;
                                } else {
                                    $resCopay = sqlStatement("SELECT sum(fee) as copay FROM billing where
                                    code_type='COPAY' and pid =? and encounter =? and billing.activity!=0", [$PId, $Encounter]);
                                    $rowCopay = sqlFetchArray($resCopay);
                                    $Copay = $rowCopay['copay'] * -1;

                                    $resMoneyGot = sqlStatement(
                                        "SELECT sum(pay_amount) AS PatientPay FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and encounter = ? AND " .
                                        "payer_type = 0 AND account_code = 'PCP'",
                                        [$PId, $Encounter]
                                    ); //new fees screen copay gives account_code='PCP'
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $PatientPay = $rowMoneyGot['PatientPay'];
                                    $Copay = $Copay + $PatientPay;
                                }

                                //For calculating Remainder
                                if ($Ins == 0) {//Fetch all values
                                    $resMoneyGot = sqlStatement(
                                        "SELECT sum(pay_amount) as MoneyGot FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and code_type = ? AND code = ? AND " .
                                        "modifier = ? AND encounter = ? AND " .
                                        "!(payer_type = 0 AND account_code = 'PCP')",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter]
                                    );
                                    //new fees screen copay gives account_code='PCP'
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot = $rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement(
                                        "SELECT sum(adj_amount) AS MoneyAdjusted FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and code_type = ? and code = ? AND " .
                                        "modifier = ? AND encounter = ?",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter]
                                    );
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted = $rowMoneyAdjusted['MoneyAdjusted'];
                                } else {
                                    //Fetch till that much got
                                    //Fetch the HIGHEST sequence_no till this session.
                                    //Used maily in  the case if primary/others pays once more.
                                    $resSequence = sqlStatement("SELECT sequence_no from ar_activity where session_id=? and
                                    pid=? and encounter=? order by sequence_no desc ", [$payment_id, $PId, $Encounter]);
                                    $rowSequence = sqlFetchArray($resSequence);
                                    $Sequence = $rowSequence['sequence_no'];

                                    $resMoneyGot = sqlStatement(
                                        "SELECT sum(pay_amount) as MoneyGot FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and code_type = ? AND code = ? AND " .
                                        "modifier = ? and encounter = ? AND " .
                                        "payer_type > 0 and payer_type <= ? and sequence_no <= ?",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter, $Ins, $Sequence]
                                    );
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot = $rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement(
                                        "SELECT sum(adj_amount) AS MoneyAdjusted FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and code_type = ? and code = ? AND " .
                                        "modifier = ? AND encounter = ? AND payer_type > 0 AND " .
                                        "payer_type <= ? and sequence_no <= ?",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter, $Ins, $Sequence]
                                    );
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted = $rowMoneyAdjusted['MoneyAdjusted'];
                                }
                                $Remainder = $Fee - $Copay - $MoneyGot - $MoneyAdjusted;
                                //For calculating RemainderJS.Used while restoring back the values.
                                if ($Ins == 0) {//Got just before Patient
                                    $resMoneyGot = sqlStatement(
                                        "SELECT sum(pay_amount) AS MoneyGot FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? AND code_type = ? AND code = ? AND " .
                                        "modifier = ? AND encounter = ? and payer_type != 0",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter]
                                    );
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot = $rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement(
                                        "SELECT sum(adj_amount) AS MoneyAdjusted FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? AND code_type = ? AND code = ? AND " .
                                        "modifier = ? and encounter = ? and payer_type != 0",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter]
                                    );
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted = $rowMoneyAdjusted['MoneyAdjusted'];
                                } else {
                                    //Got just before the previous
                                    //Fetch the LOWEST sequence_no till this session.
                                    //Used maily in  the case if primary/others pays once more.
                                    $resSequence = sqlStatement(
                                        "SELECT sequence_no FROM ar_activity WHERE " .
                                        "session_id = ? AND deleted IS NULL AND pid = ? AND encounter = ? " .
                                        "order by sequence_no",
                                        [$payment_id, $PId, $Encounter]
                                    );
                                    $rowSequence = sqlFetchArray($resSequence);
                                    $Sequence = $rowSequence['sequence_no'];

                                    $resMoneyGot = sqlStatement(
                                        "SELECT sum(pay_amount) as MoneyGot FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? AND code_type = ? AND code = ? " .
                                        "AND modifier = ? AND encounter = ? AND payer_type > 0 AND " .
                                        "payer_type <= ? AND sequence_no < ?",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter, $Ins, $Sequence]
                                    );
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot = $rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement(
                                        "SELECT sum(adj_amount) as MoneyAdjusted FROM ar_activity WHERE " .
                                        "deleted IS NULL AND pid = ? and code_type = ? and code = ? AND " .
                                        "modifier = ? AND encounter = ? AND payer_type <= ? AND sequence_no < ?",
                                        [$PId, $Codetype, $Code, $Modifier, $Encounter, $Ins, $Sequence]
                                    );
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted = $rowMoneyAdjusted['MoneyAdjusted'];
                                }
                                //Stored in hidden so that can be used while restoring back the values.
                                $RemainderJS = $Fee - $Copay - $MoneyGot - $MoneyAdjusted;

                                $resPayment = sqlStatement(
                                    "SELECT pay_amount FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id=? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND pay_amount > 0",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $PaymentDB = floatval($rowPayment['pay_amount'] ?? null);

                                $resPayment = sqlStatement(
                                    "SELECT pay_amount FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND pay_amount < 0",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $TakebackDB = floatval($rowPayment['pay_amount'] ?? null);

                                $resPayment = sqlStatement(
                                    "SELECT adj_amount FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND adj_amount != 0",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $AdjAmountDB = floatval($rowPayment['adj_amount'] ?? null);

                                $resPayment = sqlStatement(
                                    "SELECT memo FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND " .
                                    "(memo LIKE 'Total%' OR memo LIKE 'Total%')",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $DeductibleDB = $rowPayment['memo'] ?? '';
                                $DeductibleDB = str_replace('Total $', '', $DeductibleDB);
                                $DeductibleDB = str_replace('Total $', '', $DeductibleDB);

                                $resPayment = sqlStatement(
                                    "SELECT memo FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND " .
                                    "(memo LIKE 'Co-ins%' OR memo LIKE 'Co-ins%')",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $coInsuranceVal = $rowPayment['memo'] ?? '';
                                $coInsuranceVal = str_replace('Co-ins $', '', $coInsuranceVal);
                                $coInsuranceVal = str_replace('Co-ins $', '', $coInsuranceVal);

                                $resPayment = sqlStatement(
                                    "SELECT memo FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND " .
                                    "(memo LIKE 'Deductible%' OR memo LIKE 'Deductible%')",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $DeductibleDB = $rowPayment['memo'] ?? '';
                                $DeductibleDB = str_replace('Deductible $', '', $DeductibleDB);
                                $DeductibleDB = str_replace('Deductible $', '', $DeductibleDB);


                                $resPayment = sqlStatement(
                                    "SELECT follow_up, follow_up_note FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ? AND follow_up = 'y'",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $FollowUpDB = $rowPayment['follow_up'] ?? '';
                                $FollowUpReasonDB = $rowPayment['follow_up_note'] ?? '';

                                $resPayment = sqlStatement(
                                    "SELECT reason_code FROM ar_activity WHERE " .
                                    "deleted IS NULL AND session_id = ? AND pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code = ? AND modifier = ?",
                                    [$payment_id, $PId, $Encounter, $Codetype, $Code, $Modifier]
                                );
                                $rowPayment = sqlFetchArray($resPayment);
                                $ReasonCodeDB = $rowPayment['reason_code'];

                                if ($Ins == 1) {
                                    $AllowedDB = number_format($Fee - floatval($AdjAmountDB), 2);
                                } else {
                                    $AllowedDB = 0;
                                }

                                if ($Ins == 1) {
                                    $bgcolor = '#ddddff';
                                } elseif ($Ins == 2) {
                                    $bgcolor = '#ffdddd';
                                } elseif ($Ins == 3) {
                                    $bgcolor = '#F2F1BC';
                                } elseif ($Ins == 0) {
                                    $bgcolor = '#AAFFFF';
                                }
                                $paymenttot = $paymenttot + floatval($PaymentDB);
                                $adjamttot = $adjamttot + floatval($AdjAmountDB);
                                $deductibletot = $deductibletot + floatval($DeductibleDB);
                                $coInsurancetot = $coInsurancetot + floatval($coInsuranceVal);
                                $takebacktot = $takebacktot + floatval($TakebackDB);
                                $allowedtot = $allowedtot + floatval($AllowedDB);
                                ?>

                            <tr class="border-dark" bgcolor='<?php echo attr($bgcolor); ?>' class="text"
                                id="trCharges<?php echo attr($CountIndex); ?>">
                                <td align="left">
                                    <a href="#"
                                       onclick="javascript:return DeletePaymentDistribution(<?php echo attr_js($payment_id . '_' . $PId . '_' . $Encounter . '_' . $Code . '_' . $Modifier . '_' . $Codetype); ?>);"><img border="0" src="../pic/Delete.gif"></a>
                                </td>
                                <td align="left">
                                    <?php echo text($NameDB); ?><input name="HiddenPId<?php echo attr($CountIndex); ?>" type="hidden" value="<?php echo attr($PId); ?>" />
                                </td>
                                <td align="left">
                                    <input id="HiddenIns<?php echo attr($CountIndex); ?>"
                                           name="HiddenIns<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Ins); ?>"/><?php echo generate_select_list("payment_ins$CountIndex", "payment_ins", "$Ins", "Insurance/Patient", '', 'w-100', 'ActionOnInsPat("' . $CountIndex . '")'); ?>
                                </td>
                                <td>
                                    <?php echo text($ServiceDate); ?>
                                </td>
                                <td align="right">
                                    <input name="HiddenEncounter<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Encounter); ?>"/><?php echo text($Encounter); ?>
                                </td>
                                <td>
                                    <input name="HiddenCodetype<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Codetype); ?>"/>
                                    <input name="HiddenCode<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Code); ?>"/><?php echo text($Codetype . "-" . $Code . $ModifierString); ?>
                                    <input name="HiddenModifier<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Modifier); ?>"/>
                                </td>
                                <td align="right">
                                    <input id="HiddenChargeAmount<?php echo attr($CountIndex); ?>"
                                           name="HiddenChargeAmount<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Fee); ?>"/><?php echo text($Fee); ?>
                                </td>
                                <td align="right">
                                    <input id="HiddenCopayAmount<?php echo attr($CountIndex); ?>"
                                           name="HiddenCopayAmount<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($Copay); ?>"><?php echo text(number_format($Copay, 2)); ?>
                                </td>
                                <td align="right"
                                    id="RemainderTd<?php echo attr($CountIndex); ?>"> <?php echo text(round($Remainder, 2)); ?> </td>
                                <input name="HiddenRemainderTd<?php echo attr($CountIndex); ?>"
                                       id="HiddenRemainderTd<?php echo attr($CountIndex); ?>"
                                       value="<?php echo attr(round($Remainder, 2)); ?>" type="hidden"/>
                                <td>
                                    <input autocomplete="off" id="Allowed<?php echo attr($CountIndex); ?>"
                                           name="Allowed<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo attr_js($CountIndex); ?>);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Allowed','allowtotal');UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Payment','paymenttotal');UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'AdjAmount','AdjAmounttotal');RestoreValues(<?php echo attr_js($CountIndex); ?>)"
                                           onkeydown="PreventIt(event)" class="text-right input-sm w-100" type="text"
                                           value="<?php echo attr($AllowedDB); ?>"/>
                                </td>

                                <td>
                                    <input autocomplete="off" id="Payment<?php echo attr($CountIndex); ?>"
                                           name="Payment<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo attr_js($CountIndex); ?>);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Payment','paymenttotal');RestoreValues(<?php echo attr_js($CountIndex); ?>)"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr($PaymentDB); ?>"/>
                                </td>
                                <td>
                                    <input autocomplete="off" id="AdjAmount<?php echo attr($CountIndex); ?>"
                                           name="AdjAmount<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo attr_js($CountIndex); ?>);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'AdjAmount','AdjAmounttotal');RestoreValues(<?php echo attr_js($CountIndex); ?>)"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr($AdjAmountDB); ?>"/>
                                </td>
                                <td>
                                    <input autocomplete="off" id="Deductible<?php echo attr($CountIndex); ?>"
                                           name="Deductible<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Deductible','deductibletotal');"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr($DeductibleDB); ?>"/>
                                </td>

                                <td>
                                    <input autocomplete="off" id="Co-ins<?php echo attr($CountIndex); ?>"
                                           name="Co-ins<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Co-ins_Field','coInstotal');"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr($coInsuranceVal); ?>"/>
                                           <input name="HiddenTotal<?php echo attr($CountIndex); ?>" type="hidden" value="<?php echo attr(floatval($coInsuranceVal) + floatval($DeductibleDB)); ?>" />
                                    <input readonly=true style="display: none;" autocomplete="off"
                                           id="Total<?php echo attr($CountIndex); ?>"
                                           name="Total<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Total','Total_Field');"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr(floatval($coInsuranceVal) + floatval($DeductibleDB)); ?>"/>
                                </td>

                                <td>
                                    <input autocomplete="off" id="Takeback<?php echo attr($CountIndex); ?>"
                                           name="Takeback<?php echo attr($CountIndex); ?>"
                                           onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo attr_js($CountIndex); ?>);UpdateTotalValues(1,<?php echo attr_js($TotalRows); ?>,'Takeback','takebacktotal');RestoreValues(<?php echo attr_js($CountIndex); ?>)"
                                           onkeydown="PreventIt(event)" class="text-right  input-sm w-100" type="text"
                                           value="<?php echo attr($TakebackDB); ?>"/>
                                </td>
                                <td align="left">
                                    <input id="HiddenReasonCode<?php echo attr($CountIndex); ?>"
                                           name="HiddenReasonCode<?php echo attr($CountIndex); ?>" type="hidden"
                                           value="<?php echo attr($ReasonCodeDB); ?>"/><?php echo generate_select_list("ReasonCode$CountIndex", "msp_remit_codes", "$ReasonCodeDB", "MSP", '', 'w-100'); ?>
                                </td>
                                <td align="center">
                                    <input id="FollowUp<?php echo attr($CountIndex); ?>"
                                           name="FollowUp<?php echo attr($CountIndex); ?>"
                                           onclick="ActionFollowUp(<?php echo attr_js($CountIndex); ?>)" type="checkbox"
                                           value="y"/>
                                </td>
                                <td>
                                    <select name="FollowUpReason<?php echo attr($CountIndex); ?>" class=" input-sm w-100"  id="FollowUpReason<?php echo attr($CountIndex); ?>">
                                        <option value="">Select Follow Up Reason</option>
                                    <option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("1 - Deductible Amount", ENT_QUOTES)) echo "selected"; ?> value="1 - Deductible Amount">1 - Deductible Amount</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("2 - Coinsurance Amount", ENT_QUOTES)) echo "selected"; ?> value="2 - Coinsurance Amount">2 - Coinsurance Amount</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("3 - Co-payment Amount", ENT_QUOTES)) echo "selected"; ?> value="3 - Co-payment Amount">3 - Co-payment Amount</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("4 - The procedure code is inconsistent with the modifier used. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="4 - The procedure code is inconsistent with the modifier used. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">4 - The procedure code is inconsistent with the modifier used. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("5 - The procedure code/type of bill is inconsistent with the place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="5 - The procedure code/type of bill is inconsistent with the place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">5 - The procedure code/type of bill is inconsistent with the place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("6 - The procedure/revenue code is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="6 - The procedure/revenue code is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">6 - The procedure/revenue code is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("7 - The procedure/revenue code is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="7 - The procedure/revenue code is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">7 - The procedure/revenue code is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("8 - The procedure code is inconsistent with the provider type/specialty (taxonomy). Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="8 - The procedure code is inconsistent with the provider type/specialty (taxonomy). Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">8 - The procedure code is inconsistent with the provider type/specialty (taxonomy). Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("9 - The diagnosis is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="9 - The diagnosis is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">9 - The diagnosis is inconsistent with the patient&#x27;s age. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("10 - The diagnosis is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="10 - The diagnosis is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">10 - The diagnosis is inconsistent with the patient&#x27;s gender. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("11 - The diagnosis is inconsistent with the procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="11 - The diagnosis is inconsistent with the procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">11 - The diagnosis is inconsistent with the procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("12 - The diagnosis is inconsistent with the provider type. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="12 - The diagnosis is inconsistent with the provider type. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">12 - The diagnosis is inconsistent with the provider type. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("13 - The date of death precedes the date of service.", ENT_QUOTES)) echo "selected"; ?> value="13 - The date of death precedes the date of service.">13 - The date of death precedes the date of service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("14 - The date of birth follows the date of service.", ENT_QUOTES)) echo "selected"; ?> value="14 - The date of birth follows the date of service.">14 - The date of birth follows the date of service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("16 - Claim/service lacks information or has submission/billing error(s). Usage: Do not use this code for claims attachment(s)/other documentation. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="16 - Claim/service lacks information or has submission/billing error(s). Usage: Do not use this code for claims attachment(s)/other documentation. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">16 - Claim/service lacks information or has submission/billing error(s). Usage: Do not use this code for claims attachment(s)/other documentation. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("18 - Exact duplicate claim/service (Use only with Group Code OA except where state workers&#x27; compensation regulations requires CO)", ENT_QUOTES)) echo "selected"; ?> value="18 - Exact duplicate claim/service (Use only with Group Code OA except where state workers&#x27; compensation regulations requires CO)">18 - Exact duplicate claim/service (Use only with Group Code OA except where state workers&#x27; compensation regulations requires CO)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("19 - This is a work-related injury/illness and thus the liability of the Worker&#x27;s Compensation Carrier.", ENT_QUOTES)) echo "selected"; ?> value="19 - This is a work-related injury/illness and thus the liability of the Worker&#x27;s Compensation Carrier.">19 - This is a work-related injury/illness and thus the liability of the Worker&#x27;s Compensation Carrier.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("20 - This injury/illness is covered by the liability carrier.", ENT_QUOTES)) echo "selected"; ?> value="20 - This injury/illness is covered by the liability carrier.">20 - This injury/illness is covered by the liability carrier.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("21 - This injury/illness is the liability of the no-fault carrier.", ENT_QUOTES)) echo "selected"; ?> value="21 - This injury/illness is the liability of the no-fault carrier.">21 - This injury/illness is the liability of the no-fault carrier.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("22 - This care may be covered by another payer per coordination of benefits.", ENT_QUOTES)) echo "selected"; ?> value="22 - This care may be covered by another payer per coordination of benefits.">22 - This care may be covered by another payer per coordination of benefits.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("23 - The impact of prior payer(s) adjudication including payments and/or adjustments. (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="23 - The impact of prior payer(s) adjudication including payments and/or adjustments. (Use only with Group Code OA)">23 - The impact of prior payer(s) adjudication including payments and/or adjustments. (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("24 - Charges are covered under a capitation agreement/managed care plan.", ENT_QUOTES)) echo "selected"; ?> value="24 - Charges are covered under a capitation agreement/managed care plan.">24 - Charges are covered under a capitation agreement/managed care plan.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("26 - Expenses incurred prior to coverage.", ENT_QUOTES)) echo "selected"; ?> value="26 - Expenses incurred prior to coverage.">26 - Expenses incurred prior to coverage.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("27 - Expenses incurred after coverage terminated.", ENT_QUOTES)) echo "selected"; ?> value="27 - Expenses incurred after coverage terminated.">27 - Expenses incurred after coverage terminated.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("29 - The time limit for filing has expired.", ENT_QUOTES)) echo "selected"; ?> value="29 - The time limit for filing has expired.">29 - The time limit for filing has expired.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("31 - Patient cannot be identified as our insured.", ENT_QUOTES)) echo "selected"; ?> value="31 - Patient cannot be identified as our insured.">31 - Patient cannot be identified as our insured.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("32 - Our records indicate the patient is not an eligible dependent.", ENT_QUOTES)) echo "selected"; ?> value="32 - Our records indicate the patient is not an eligible dependent.">32 - Our records indicate the patient is not an eligible dependent.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("33 - Insured has no dependent coverage.", ENT_QUOTES)) echo "selected"; ?> value="33 - Insured has no dependent coverage.">33 - Insured has no dependent coverage.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("34 - Insured has no coverage for newborns.", ENT_QUOTES)) echo "selected"; ?> value="34 - Insured has no coverage for newborns.">34 - Insured has no coverage for newborns.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("35 - Lifetime benefit maximum has been reached.", ENT_QUOTES)) echo "selected"; ?> value="35 - Lifetime benefit maximum has been reached.">35 - Lifetime benefit maximum has been reached.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("39 - Services denied at the time authorization/pre-certification was requested.", ENT_QUOTES)) echo "selected"; ?> value="39 - Services denied at the time authorization/pre-certification was requested.">39 - Services denied at the time authorization/pre-certification was requested.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("40 - Charges do not meet qualifications for emergent/urgent care. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="40 - Charges do not meet qualifications for emergent/urgent care. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">40 - Charges do not meet qualifications for emergent/urgent care. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("44 - Prompt-pay discount.", ENT_QUOTES)) echo "selected"; ?> value="44 - Prompt-pay discount.">44 - Prompt-pay discount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("45 - Charge exceeds fee schedule/maximum allowable or contracted/legislated fee arrangement. Usage: This adjustment amount cannot equal the total service or claim charge amount; and must not duplicate provider adjustment amounts (payments and contractual reductions) that have resulted from prior payer(s) adjudication. (Use only with Group Codes PR or CO depending upon liability)", ENT_QUOTES)) echo "selected"; ?> value="45 - Charge exceeds fee schedule/maximum allowable or contracted/legislated fee arrangement. Usage: This adjustment amount cannot equal the total service or claim charge amount; and must not duplicate provider adjustment amounts (payments and contractual reductions) that have resulted from prior payer(s) adjudication. (Use only with Group Codes PR or CO depending upon liability)">45 - Charge exceeds fee schedule/maximum allowable or contracted/legislated fee arrangement. Usage: This adjustment amount cannot equal the total service or claim charge amount; and must not duplicate provider adjustment amounts (payments and contractual reductions) that have resulted from prior payer(s) adjudication. (Use only with Group Codes PR or CO depending upon liability)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("49 - This is a non-covered service because it is a routine/preventive exam or a diagnostic/screening procedure done in conjunction with a routine/preventive exam. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="49 - This is a non-covered service because it is a routine/preventive exam or a diagnostic/screening procedure done in conjunction with a routine/preventive exam. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">49 - This is a non-covered service because it is a routine/preventive exam or a diagnostic/screening procedure done in conjunction with a routine/preventive exam. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("50 - These are non-covered services because this is not deemed a &#x27;medical necessity&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="50 - These are non-covered services because this is not deemed a &#x27;medical necessity&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">50 - These are non-covered services because this is not deemed a &#x27;medical necessity&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("51 - These are non-covered services because this is a pre-existing condition. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="51 - These are non-covered services because this is a pre-existing condition. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">51 - These are non-covered services because this is a pre-existing condition. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("53 - Services by an immediate relative or a member of the same household are not covered.", ENT_QUOTES)) echo "selected"; ?> value="53 - Services by an immediate relative or a member of the same household are not covered.">53 - Services by an immediate relative or a member of the same household are not covered.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("54 - Multiple physicians/assistants are not covered in this case. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="54 - Multiple physicians/assistants are not covered in this case. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">54 - Multiple physicians/assistants are not covered in this case. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("55 - Procedure/treatment/drug is deemed experimental/investigational by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="55 - Procedure/treatment/drug is deemed experimental/investigational by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">55 - Procedure/treatment/drug is deemed experimental/investigational by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("56 - Procedure/treatment has not been deemed &#x27;proven to be effective&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="56 - Procedure/treatment has not been deemed &#x27;proven to be effective&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">56 - Procedure/treatment has not been deemed &#x27;proven to be effective&#x27; by the payer. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("58 - Treatment was deemed by the payer to have been rendered in an inappropriate or invalid place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="58 - Treatment was deemed by the payer to have been rendered in an inappropriate or invalid place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">58 - Treatment was deemed by the payer to have been rendered in an inappropriate or invalid place of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("59 - Processed based on multiple or concurrent procedure rules. (For example multiple surgery or diagnostic imaging, concurrent anesthesia.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="59 - Processed based on multiple or concurrent procedure rules. (For example multiple surgery or diagnostic imaging, concurrent anesthesia.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">59 - Processed based on multiple or concurrent procedure rules. (For example multiple surgery or diagnostic imaging, concurrent anesthesia.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("60 - Charges for outpatient services are not covered when performed within a period of time prior to or after inpatient services.", ENT_QUOTES)) echo "selected"; ?> value="60 - Charges for outpatient services are not covered when performed within a period of time prior to or after inpatient services.">60 - Charges for outpatient services are not covered when performed within a period of time prior to or after inpatient services.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("61 - Adjusted for failure to obtain second surgical opinion", ENT_QUOTES)) echo "selected"; ?> value="61 - Adjusted for failure to obtain second surgical opinion">61 - Adjusted for failure to obtain second surgical opinion</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("66 - Blood Deductible.", ENT_QUOTES)) echo "selected"; ?> value="66 - Blood Deductible.">66 - Blood Deductible.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("69 - Day outlier amount.", ENT_QUOTES)) echo "selected"; ?> value="69 - Day outlier amount.">69 - Day outlier amount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("70 - Cost outlier - Adjustment to compensate for additional costs.", ENT_QUOTES)) echo "selected"; ?> value="70 - Cost outlier - Adjustment to compensate for additional costs.">70 - Cost outlier - Adjustment to compensate for additional costs.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("74 - Indirect Medical Education Adjustment.", ENT_QUOTES)) echo "selected"; ?> value="74 - Indirect Medical Education Adjustment.">74 - Indirect Medical Education Adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("75 - Direct Medical Education Adjustment.", ENT_QUOTES)) echo "selected"; ?> value="75 - Direct Medical Education Adjustment.">75 - Direct Medical Education Adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("76 - Disproportionate Share Adjustment.", ENT_QUOTES)) echo "selected"; ?> value="76 - Disproportionate Share Adjustment.">76 - Disproportionate Share Adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("78 - Non-Covered days/Room charge adjustment.", ENT_QUOTES)) echo "selected"; ?> value="78 - Non-Covered days/Room charge adjustment.">78 - Non-Covered days/Room charge adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("85 - Patient Interest Adjustment (Use Only Group code PR)", ENT_QUOTES)) echo "selected"; ?> value="85 - Patient Interest Adjustment (Use Only Group code PR)">85 - Patient Interest Adjustment (Use Only Group code PR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("89 - Professional fees removed from charges.", ENT_QUOTES)) echo "selected"; ?> value="89 - Professional fees removed from charges.">89 - Professional fees removed from charges.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("90 - Ingredient cost adjustment. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="90 - Ingredient cost adjustment. Usage: To be used for pharmaceuticals only.">90 - Ingredient cost adjustment. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("91 - Dispensing fee adjustment.", ENT_QUOTES)) echo "selected"; ?> value="91 - Dispensing fee adjustment.">91 - Dispensing fee adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("94 - Processed in Excess of charges.", ENT_QUOTES)) echo "selected"; ?> value="94 - Processed in Excess of charges.">94 - Processed in Excess of charges.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("95 - Plan procedures not followed.", ENT_QUOTES)) echo "selected"; ?> value="95 - Plan procedures not followed.">95 - Plan procedures not followed.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("96 - Non-covered charge(s). At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="96 - Non-covered charge(s). At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">96 - Non-covered charge(s). At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("97 - The benefit for this service is included in the payment/allowance for another service/procedure that has already been adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="97 - The benefit for this service is included in the payment/allowance for another service/procedure that has already been adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">97 - The benefit for this service is included in the payment/allowance for another service/procedure that has already been adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("100 - Payment made to patient/insured/responsible party.", ENT_QUOTES)) echo "selected"; ?> value="100 - Payment made to patient/insured/responsible party.">100 - Payment made to patient/insured/responsible party.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("101 - Predetermination: anticipated payment upon completion of services or claim adjudication.", ENT_QUOTES)) echo "selected"; ?> value="101 - Predetermination: anticipated payment upon completion of services or claim adjudication.">101 - Predetermination: anticipated payment upon completion of services or claim adjudication.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("102 - Major Medical Adjustment.", ENT_QUOTES)) echo "selected"; ?> value="102 - Major Medical Adjustment.">102 - Major Medical Adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("103 - Provider promotional discount (e.g., Senior citizen discount).", ENT_QUOTES)) echo "selected"; ?> value="103 - Provider promotional discount (e.g., Senior citizen discount).">103 - Provider promotional discount (e.g., Senior citizen discount).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("104 - Managed care withholding.", ENT_QUOTES)) echo "selected"; ?> value="104 - Managed care withholding.">104 - Managed care withholding.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("105 - Tax withholding.", ENT_QUOTES)) echo "selected"; ?> value="105 - Tax withholding.">105 - Tax withholding.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("106 - Patient payment option/election not in effect.", ENT_QUOTES)) echo "selected"; ?> value="106 - Patient payment option/election not in effect.">106 - Patient payment option/election not in effect.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("107 - The related or qualifying claim/service was not identified on this claim. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="107 - The related or qualifying claim/service was not identified on this claim. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">107 - The related or qualifying claim/service was not identified on this claim. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("108 - Rent/purchase guidelines were not met. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="108 - Rent/purchase guidelines were not met. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">108 - Rent/purchase guidelines were not met. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("109 - Claim/service not covered by this payer/contractor. You must send the claim/service to the correct payer/contractor.", ENT_QUOTES)) echo "selected"; ?> value="109 - Claim/service not covered by this payer/contractor. You must send the claim/service to the correct payer/contractor.">109 - Claim/service not covered by this payer/contractor. You must send the claim/service to the correct payer/contractor.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("110 - Billing date predates service date.", ENT_QUOTES)) echo "selected"; ?> value="110 - Billing date predates service date.">110 - Billing date predates service date.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("111 - Not covered unless the provider accepts assignment.", ENT_QUOTES)) echo "selected"; ?> value="111 - Not covered unless the provider accepts assignment.">111 - Not covered unless the provider accepts assignment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("112 - Service not furnished directly to the patient and/or not documented.", ENT_QUOTES)) echo "selected"; ?> value="112 - Service not furnished directly to the patient and/or not documented.">112 - Service not furnished directly to the patient and/or not documented.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("114 - Procedure/product not approved by the Food and Drug Administration.", ENT_QUOTES)) echo "selected"; ?> value="114 - Procedure/product not approved by the Food and Drug Administration.">114 - Procedure/product not approved by the Food and Drug Administration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("115 - Procedure postponed, canceled, or delayed.", ENT_QUOTES)) echo "selected"; ?> value="115 - Procedure postponed, canceled, or delayed.">115 - Procedure postponed, canceled, or delayed.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("116 - The advance indemnification notice signed by the patient did not comply with requirements.", ENT_QUOTES)) echo "selected"; ?> value="116 - The advance indemnification notice signed by the patient did not comply with requirements.">116 - The advance indemnification notice signed by the patient did not comply with requirements.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("117 - Transportation is only covered to the closest facility that can provide the necessary care.", ENT_QUOTES)) echo "selected"; ?> value="117 - Transportation is only covered to the closest facility that can provide the necessary care.">117 - Transportation is only covered to the closest facility that can provide the necessary care.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("118 - ESRD network support adjustment.", ENT_QUOTES)) echo "selected"; ?> value="118 - ESRD network support adjustment.">118 - ESRD network support adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("119 - Benefit maximum for this time period or occurrence has been reached.", ENT_QUOTES)) echo "selected"; ?> value="119 - Benefit maximum for this time period or occurrence has been reached.">119 - Benefit maximum for this time period or occurrence has been reached.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("121 - Indemnification adjustment - compensation for outstanding member responsibility.", ENT_QUOTES)) echo "selected"; ?> value="121 - Indemnification adjustment - compensation for outstanding member responsibility.">121 - Indemnification adjustment - compensation for outstanding member responsibility.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("122 - Psychiatric reduction.", ENT_QUOTES)) echo "selected"; ?> value="122 - Psychiatric reduction.">122 - Psychiatric reduction.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("128 - Newborn&#x27;s services are covered in the mother&#x27;s Allowance.", ENT_QUOTES)) echo "selected"; ?> value="128 - Newborn&#x27;s services are covered in the mother&#x27;s Allowance.">128 - Newborn&#x27;s services are covered in the mother&#x27;s Allowance.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("129 - Prior processing information appears incorrect. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="129 - Prior processing information appears incorrect. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">129 - Prior processing information appears incorrect. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("130 - Claim submission fee.", ENT_QUOTES)) echo "selected"; ?> value="130 - Claim submission fee.">130 - Claim submission fee.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("131 - Claim specific negotiated discount.", ENT_QUOTES)) echo "selected"; ?> value="131 - Claim specific negotiated discount.">131 - Claim specific negotiated discount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("132 - Prearranged demonstration project adjustment.", ENT_QUOTES)) echo "selected"; ?> value="132 - Prearranged demonstration project adjustment.">132 - Prearranged demonstration project adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("133 - The disposition of this service line is pending further review. (Use only with Group Code OA). Usage: Use of this code requires a reversal and correction when the service line is finalized (use only in Loop 2110 CAS segment of the 835 or Loop 2430 of the 837).", ENT_QUOTES)) echo "selected"; ?> value="133 - The disposition of this service line is pending further review. (Use only with Group Code OA). Usage: Use of this code requires a reversal and correction when the service line is finalized (use only in Loop 2110 CAS segment of the 835 or Loop 2430 of the 837).">133 - The disposition of this service line is pending further review. (Use only with Group Code OA). Usage: Use of this code requires a reversal and correction when the service line is finalized (use only in Loop 2110 CAS segment of the 835 or Loop 2430 of the 837).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("134 - Technical fees removed from charges.", ENT_QUOTES)) echo "selected"; ?> value="134 - Technical fees removed from charges.">134 - Technical fees removed from charges.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("135 - Interim bills cannot be processed.", ENT_QUOTES)) echo "selected"; ?> value="135 - Interim bills cannot be processed.">135 - Interim bills cannot be processed.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("136 - Failure to follow prior payer&#x27;s coverage rules. (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="136 - Failure to follow prior payer&#x27;s coverage rules. (Use only with Group Code OA)">136 - Failure to follow prior payer&#x27;s coverage rules. (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("137 - Regulatory Surcharges, Assessments, Allowances or Health Related Taxes.", ENT_QUOTES)) echo "selected"; ?> value="137 - Regulatory Surcharges, Assessments, Allowances or Health Related Taxes.">137 - Regulatory Surcharges, Assessments, Allowances or Health Related Taxes.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("139 - Contracted funding agreement - Subscriber is employed by the provider of services. Use only with Group Code CO.", ENT_QUOTES)) echo "selected"; ?> value="139 - Contracted funding agreement - Subscriber is employed by the provider of services. Use only with Group Code CO.">139 - Contracted funding agreement - Subscriber is employed by the provider of services. Use only with Group Code CO.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("140 - Patient/Insured health identification number and name do not match.", ENT_QUOTES)) echo "selected"; ?> value="140 - Patient/Insured health identification number and name do not match.">140 - Patient/Insured health identification number and name do not match.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("142 - Monthly Medicaid patient liability amount.", ENT_QUOTES)) echo "selected"; ?> value="142 - Monthly Medicaid patient liability amount.">142 - Monthly Medicaid patient liability amount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("143 - Portion of payment deferred.", ENT_QUOTES)) echo "selected"; ?> value="143 - Portion of payment deferred.">143 - Portion of payment deferred.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("144 - Incentive adjustment, e.g. preferred product/service.", ENT_QUOTES)) echo "selected"; ?> value="144 - Incentive adjustment, e.g. preferred product/service.">144 - Incentive adjustment, e.g. preferred product/service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("146 - Diagnosis was invalid for the date(s) of service reported.", ENT_QUOTES)) echo "selected"; ?> value="146 - Diagnosis was invalid for the date(s) of service reported.">146 - Diagnosis was invalid for the date(s) of service reported.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("147 - Provider contracted/negotiated rate expired or not on file.", ENT_QUOTES)) echo "selected"; ?> value="147 - Provider contracted/negotiated rate expired or not on file.">147 - Provider contracted/negotiated rate expired or not on file.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("148 - Information from another provider was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="148 - Information from another provider was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">148 - Information from another provider was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("149 - Lifetime benefit maximum has been reached for this service/benefit category.", ENT_QUOTES)) echo "selected"; ?> value="149 - Lifetime benefit maximum has been reached for this service/benefit category.">149 - Lifetime benefit maximum has been reached for this service/benefit category.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("150 - Payer deems the information submitted does not support this level of service.", ENT_QUOTES)) echo "selected"; ?> value="150 - Payer deems the information submitted does not support this level of service.">150 - Payer deems the information submitted does not support this level of service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("151 - Payment adjusted because the payer deems the information submitted does not support this many/frequency of services.", ENT_QUOTES)) echo "selected"; ?> value="151 - Payment adjusted because the payer deems the information submitted does not support this many/frequency of services.">151 - Payment adjusted because the payer deems the information submitted does not support this many/frequency of services.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("152 - Payer deems the information submitted does not support this length of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="152 - Payer deems the information submitted does not support this length of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">152 - Payer deems the information submitted does not support this length of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("153 - Payer deems the information submitted does not support this dosage.", ENT_QUOTES)) echo "selected"; ?> value="153 - Payer deems the information submitted does not support this dosage.">153 - Payer deems the information submitted does not support this dosage.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("154 - Payer deems the information submitted does not support this day&#x27;s supply.", ENT_QUOTES)) echo "selected"; ?> value="154 - Payer deems the information submitted does not support this day&#x27;s supply.">154 - Payer deems the information submitted does not support this day&#x27;s supply.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("155 - Patient refused the service/procedure.", ENT_QUOTES)) echo "selected"; ?> value="155 - Patient refused the service/procedure.">155 - Patient refused the service/procedure.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("157 - Service/procedure was provided as a result of an act of war.", ENT_QUOTES)) echo "selected"; ?> value="157 - Service/procedure was provided as a result of an act of war.">157 - Service/procedure was provided as a result of an act of war.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("158 - Service/procedure was provided outside of the United States.", ENT_QUOTES)) echo "selected"; ?> value="158 - Service/procedure was provided outside of the United States.">158 - Service/procedure was provided outside of the United States.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("159 - Service/procedure was provided as a result of terrorism.", ENT_QUOTES)) echo "selected"; ?> value="159 - Service/procedure was provided as a result of terrorism.">159 - Service/procedure was provided as a result of terrorism.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("160 - Injury/illness was the result of an activity that is a benefit exclusion.", ENT_QUOTES)) echo "selected"; ?> value="160 - Injury/illness was the result of an activity that is a benefit exclusion.">160 - Injury/illness was the result of an activity that is a benefit exclusion.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("161 - Provider performance bonus", ENT_QUOTES)) echo "selected"; ?> value="161 - Provider performance bonus">161 - Provider performance bonus</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("163 - Attachment/other documentation referenced on the claim was not received.", ENT_QUOTES)) echo "selected"; ?> value="163 - Attachment/other documentation referenced on the claim was not received.">163 - Attachment/other documentation referenced on the claim was not received.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("164 - Attachment/other documentation referenced on the claim was not received in a timely fashion.", ENT_QUOTES)) echo "selected"; ?> value="164 - Attachment/other documentation referenced on the claim was not received in a timely fashion.">164 - Attachment/other documentation referenced on the claim was not received in a timely fashion.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("166 - These services were submitted after this payers responsibility for processing claims under this plan ended.", ENT_QUOTES)) echo "selected"; ?> value="166 - These services were submitted after this payers responsibility for processing claims under this plan ended.">166 - These services were submitted after this payers responsibility for processing claims under this plan ended.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("167 - This (these) diagnosis(es) is (are) not covered. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="167 - This (these) diagnosis(es) is (are) not covered. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">167 - This (these) diagnosis(es) is (are) not covered. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("169 - Alternate benefit has been provided.", ENT_QUOTES)) echo "selected"; ?> value="169 - Alternate benefit has been provided.">169 - Alternate benefit has been provided.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("170 - Payment is denied when performed/billed by this type of provider. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="170 - Payment is denied when performed/billed by this type of provider. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">170 - Payment is denied when performed/billed by this type of provider. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("171 - Payment is denied when performed/billed by this type of provider in this type of facility. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="171 - Payment is denied when performed/billed by this type of provider in this type of facility. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">171 - Payment is denied when performed/billed by this type of provider in this type of facility. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("172 - Payment is adjusted when performed/billed by a provider of this specialty. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="172 - Payment is adjusted when performed/billed by a provider of this specialty. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">172 - Payment is adjusted when performed/billed by a provider of this specialty. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("173 - Service/equipment was not prescribed by a physician.", ENT_QUOTES)) echo "selected"; ?> value="173 - Service/equipment was not prescribed by a physician.">173 - Service/equipment was not prescribed by a physician.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("174 - Service was not prescribed prior to delivery.", ENT_QUOTES)) echo "selected"; ?> value="174 - Service was not prescribed prior to delivery.">174 - Service was not prescribed prior to delivery.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("175 - Prescription is incomplete.", ENT_QUOTES)) echo "selected"; ?> value="175 - Prescription is incomplete.">175 - Prescription is incomplete.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("176 - Prescription is not current.", ENT_QUOTES)) echo "selected"; ?> value="176 - Prescription is not current.">176 - Prescription is not current.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("177 - Patient has not met the required eligibility requirements.", ENT_QUOTES)) echo "selected"; ?> value="177 - Patient has not met the required eligibility requirements.">177 - Patient has not met the required eligibility requirements.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("178 - Patient has not met the required spend down requirements.", ENT_QUOTES)) echo "selected"; ?> value="178 - Patient has not met the required spend down requirements.">178 - Patient has not met the required spend down requirements.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("179 - Patient has not met the required waiting requirements. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="179 - Patient has not met the required waiting requirements. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">179 - Patient has not met the required waiting requirements. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("180 - Patient has not met the required residency requirements.", ENT_QUOTES)) echo "selected"; ?> value="180 - Patient has not met the required residency requirements.">180 - Patient has not met the required residency requirements.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("181 - Procedure code was invalid on the date of service.", ENT_QUOTES)) echo "selected"; ?> value="181 - Procedure code was invalid on the date of service.">181 - Procedure code was invalid on the date of service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("182 - Procedure modifier was invalid on the date of service.", ENT_QUOTES)) echo "selected"; ?> value="182 - Procedure modifier was invalid on the date of service.">182 - Procedure modifier was invalid on the date of service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("183 - The referring provider is not eligible to refer the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="183 - The referring provider is not eligible to refer the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">183 - The referring provider is not eligible to refer the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("184 - The prescribing/ordering provider is not eligible to prescribe/order the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="184 - The prescribing/ordering provider is not eligible to prescribe/order the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">184 - The prescribing/ordering provider is not eligible to prescribe/order the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("185 - The rendering provider is not eligible to perform the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="185 - The rendering provider is not eligible to perform the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">185 - The rendering provider is not eligible to perform the service billed. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("186 - Level of care change adjustment.", ENT_QUOTES)) echo "selected"; ?> value="186 - Level of care change adjustment.">186 - Level of care change adjustment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("187 - Consumer Spending Account payments (includes but is not limited to Flexible Spending Account, Health Savings Account, Health Reimbursement Account, etc.)", ENT_QUOTES)) echo "selected"; ?> value="187 - Consumer Spending Account payments (includes but is not limited to Flexible Spending Account, Health Savings Account, Health Reimbursement Account, etc.)">187 - Consumer Spending Account payments (includes but is not limited to Flexible Spending Account, Health Savings Account, Health Reimbursement Account, etc.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("188 - This product/procedure is only covered when used according to FDA recommendations.", ENT_QUOTES)) echo "selected"; ?> value="188 - This product/procedure is only covered when used according to FDA recommendations.">188 - This product/procedure is only covered when used according to FDA recommendations.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("189 - &#x27;Not otherwise classified&#x27; or &#x27;unlisted&#x27; procedure code (CPT/HCPCS) was billed when there is a specific procedure code for this procedure/service", ENT_QUOTES)) echo "selected"; ?> value="189 - &#x27;Not otherwise classified&#x27; or &#x27;unlisted&#x27; procedure code (CPT/HCPCS) was billed when there is a specific procedure code for this procedure/service">189 - &#x27;Not otherwise classified&#x27; or &#x27;unlisted&#x27; procedure code (CPT/HCPCS) was billed when there is a specific procedure code for this procedure/service</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("190 - Payment is included in the allowance for a Skilled Nursing Facility (SNF) qualified stay.", ENT_QUOTES)) echo "selected"; ?> value="190 - Payment is included in the allowance for a Skilled Nursing Facility (SNF) qualified stay.">190 - Payment is included in the allowance for a Skilled Nursing Facility (SNF) qualified stay.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("192 - Non standard adjustment code from paper remittance. Usage: This code is to be used by providers/payers providing Coordination of Benefits information to another payer in the 837 transaction only. This code is only used when the non-standard code cannot be reasonably mapped to an existing Claims Adjustment Reason Code, specifically Deductible, Coinsurance and Co-payment.", ENT_QUOTES)) echo "selected"; ?> value="192 - Non standard adjustment code from paper remittance. Usage: This code is to be used by providers/payers providing Coordination of Benefits information to another payer in the 837 transaction only. This code is only used when the non-standard code cannot be reasonably mapped to an existing Claims Adjustment Reason Code, specifically Deductible, Coinsurance and Co-payment.">192 - Non standard adjustment code from paper remittance. Usage: This code is to be used by providers/payers providing Coordination of Benefits information to another payer in the 837 transaction only. This code is only used when the non-standard code cannot be reasonably mapped to an existing Claims Adjustment Reason Code, specifically Deductible, Coinsurance and Co-payment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("193 - Original payment decision is being maintained. Upon review, it was determined that this claim was processed properly.", ENT_QUOTES)) echo "selected"; ?> value="193 - Original payment decision is being maintained. Upon review, it was determined that this claim was processed properly.">193 - Original payment decision is being maintained. Upon review, it was determined that this claim was processed properly.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("194 - Anesthesia performed by the operating physician, the assistant surgeon or the attending physician.", ENT_QUOTES)) echo "selected"; ?> value="194 - Anesthesia performed by the operating physician, the assistant surgeon or the attending physician.">194 - Anesthesia performed by the operating physician, the assistant surgeon or the attending physician.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("195 - Refund issued to an erroneous priority payer for this claim/service.", ENT_QUOTES)) echo "selected"; ?> value="195 - Refund issued to an erroneous priority payer for this claim/service.">195 - Refund issued to an erroneous priority payer for this claim/service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("197 - Precertification/authorization/notification/pre-treatment absent.", ENT_QUOTES)) echo "selected"; ?> value="197 - Precertification/authorization/notification/pre-treatment absent.">197 - Precertification/authorization/notification/pre-treatment absent.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("198 - Precertification/notification/authorization/pre-treatment exceeded.", ENT_QUOTES)) echo "selected"; ?> value="198 - Precertification/notification/authorization/pre-treatment exceeded.">198 - Precertification/notification/authorization/pre-treatment exceeded.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("199 - Revenue code and Procedure code do not match.", ENT_QUOTES)) echo "selected"; ?> value="199 - Revenue code and Procedure code do not match.">199 - Revenue code and Procedure code do not match.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("200 - Expenses incurred during lapse in coverage", ENT_QUOTES)) echo "selected"; ?> value="200 - Expenses incurred during lapse in coverage">200 - Expenses incurred during lapse in coverage</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("201 - Patient is responsible for amount of this claim/service through &#x27;set aside arrangement&#x27; or other agreement. (Use only with Group Code PR) At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="201 - Patient is responsible for amount of this claim/service through &#x27;set aside arrangement&#x27; or other agreement. (Use only with Group Code PR) At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">201 - Patient is responsible for amount of this claim/service through &#x27;set aside arrangement&#x27; or other agreement. (Use only with Group Code PR) At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("202 - Non-covered personal comfort or convenience services.", ENT_QUOTES)) echo "selected"; ?> value="202 - Non-covered personal comfort or convenience services.">202 - Non-covered personal comfort or convenience services.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("203 - Discontinued or reduced service.", ENT_QUOTES)) echo "selected"; ?> value="203 - Discontinued or reduced service.">203 - Discontinued or reduced service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("204 - This service/equipment/drug is not covered under the patient&#x27;s current benefit plan", ENT_QUOTES)) echo "selected"; ?> value="204 - This service/equipment/drug is not covered under the patient&#x27;s current benefit plan">204 - This service/equipment/drug is not covered under the patient&#x27;s current benefit plan</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("205 - Pharmacy discount card processing fee", ENT_QUOTES)) echo "selected"; ?> value="205 - Pharmacy discount card processing fee">205 - Pharmacy discount card processing fee</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("206 - National Provider Identifier - missing.", ENT_QUOTES)) echo "selected"; ?> value="206 - National Provider Identifier - missing.">206 - National Provider Identifier - missing.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("207 - National Provider identifier - Invalid format", ENT_QUOTES)) echo "selected"; ?> value="207 - National Provider identifier - Invalid format">207 - National Provider identifier - Invalid format</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("208 - National Provider Identifier - Not matched.", ENT_QUOTES)) echo "selected"; ?> value="208 - National Provider Identifier - Not matched.">208 - National Provider Identifier - Not matched.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("209 - Per regulatory or other agreement. The provider cannot collect this amount from the patient. However, this amount may be billed to subsequent payer. Refund to patient if collected. (Use only with Group code OA)", ENT_QUOTES)) echo "selected"; ?> value="209 - Per regulatory or other agreement. The provider cannot collect this amount from the patient. However, this amount may be billed to subsequent payer. Refund to patient if collected. (Use only with Group code OA)">209 - Per regulatory or other agreement. The provider cannot collect this amount from the patient. However, this amount may be billed to subsequent payer. Refund to patient if collected. (Use only with Group code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("210 - Payment adjusted because pre-certification/authorization not received in a timely fashion", ENT_QUOTES)) echo "selected"; ?> value="210 - Payment adjusted because pre-certification/authorization not received in a timely fashion">210 - Payment adjusted because pre-certification/authorization not received in a timely fashion</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("211 - National Drug Codes (NDC) not eligible for rebate, are not covered.", ENT_QUOTES)) echo "selected"; ?> value="211 - National Drug Codes (NDC) not eligible for rebate, are not covered.">211 - National Drug Codes (NDC) not eligible for rebate, are not covered.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("212 - Administrative surcharges are not covered", ENT_QUOTES)) echo "selected"; ?> value="212 - Administrative surcharges are not covered">212 - Administrative surcharges are not covered</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("213 - Non-compliance with the physician self referral prohibition legislation or payer policy.", ENT_QUOTES)) echo "selected"; ?> value="213 - Non-compliance with the physician self referral prohibition legislation or payer policy.">213 - Non-compliance with the physician self referral prohibition legislation or payer policy.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("215 - Based on subrogation of a third party settlement", ENT_QUOTES)) echo "selected"; ?> value="215 - Based on subrogation of a third party settlement">215 - Based on subrogation of a third party settlement</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("216 - Based on the findings of a review organization or the payer&#x27;s findings.", ENT_QUOTES)) echo "selected"; ?> value="216 - Based on the findings of a review organization or the payer&#x27;s findings.">216 - Based on the findings of a review organization or the payer&#x27;s findings.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("219 - Based on extent of injury. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF).", ENT_QUOTES)) echo "selected"; ?> value="219 - Based on extent of injury. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF).">219 - Based on extent of injury. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("222 - Exceeds the contracted maximum number of hours/days/units by this provider for this period. This is not patient specific. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="222 - Exceeds the contracted maximum number of hours/days/units by this provider for this period. This is not patient specific. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">222 - Exceeds the contracted maximum number of hours/days/units by this provider for this period. This is not patient specific. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("223 - Adjustment code for mandated federal, state or local law/regulation that is not already covered by another code and is mandated before a new code can be created.", ENT_QUOTES)) echo "selected"; ?> value="223 - Adjustment code for mandated federal, state or local law/regulation that is not already covered by another code and is mandated before a new code can be created.">223 - Adjustment code for mandated federal, state or local law/regulation that is not already covered by another code and is mandated before a new code can be created.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("224 - Patient identification compromised by identity theft. Identity verification required for processing this and future claims.", ENT_QUOTES)) echo "selected"; ?> value="224 - Patient identification compromised by identity theft. Identity verification required for processing this and future claims.">224 - Patient identification compromised by identity theft. Identity verification required for processing this and future claims.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("225 - Penalty or Interest Payment by Payer (Only used for plan to plan encounter reporting within the 837)", ENT_QUOTES)) echo "selected"; ?> value="225 - Penalty or Interest Payment by Payer (Only used for plan to plan encounter reporting within the 837)">225 - Penalty or Interest Payment by Payer (Only used for plan to plan encounter reporting within the 837)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("226 - Information requested from the Billing/Rendering Provider was not provided or not provided timely or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="226 - Information requested from the Billing/Rendering Provider was not provided or not provided timely or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">226 - Information requested from the Billing/Rendering Provider was not provided or not provided timely or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("227 - Information requested from the patient/insured/responsible party was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="227 - Information requested from the patient/insured/responsible party was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">227 - Information requested from the patient/insured/responsible party was not provided or was insufficient/incomplete. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("228 - Denied for failure of this provider, another provider or the subscriber to supply requested information to a previous payer for their adjudication", ENT_QUOTES)) echo "selected"; ?> value="228 - Denied for failure of this provider, another provider or the subscriber to supply requested information to a previous payer for their adjudication">228 - Denied for failure of this provider, another provider or the subscriber to supply requested information to a previous payer for their adjudication</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("229 - Partial charge amount not considered by Medicare due to the initial claim Type of Bill being 12X. Usage: This code can only be used in the 837 transaction to convey Coordination of Benefits information when the secondary payer&#x27;s cost avoidance policy allows providers to bypass claim submission to a prior payer. (Use only with Group Code PR)", ENT_QUOTES)) echo "selected"; ?> value="229 - Partial charge amount not considered by Medicare due to the initial claim Type of Bill being 12X. Usage: This code can only be used in the 837 transaction to convey Coordination of Benefits information when the secondary payer&#x27;s cost avoidance policy allows providers to bypass claim submission to a prior payer. (Use only with Group Code PR)">229 - Partial charge amount not considered by Medicare due to the initial claim Type of Bill being 12X. Usage: This code can only be used in the 837 transaction to convey Coordination of Benefits information when the secondary payer&#x27;s cost avoidance policy allows providers to bypass claim submission to a prior payer. (Use only with Group Code PR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("231 - Mutually exclusive procedures cannot be done in the same day/setting. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="231 - Mutually exclusive procedures cannot be done in the same day/setting. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">231 - Mutually exclusive procedures cannot be done in the same day/setting. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("232 - Institutional Transfer Amount. Usage: Applies to institutional claims only and explains the DRG amount difference when the patient care crosses multiple institutions.", ENT_QUOTES)) echo "selected"; ?> value="232 - Institutional Transfer Amount. Usage: Applies to institutional claims only and explains the DRG amount difference when the patient care crosses multiple institutions.">232 - Institutional Transfer Amount. Usage: Applies to institutional claims only and explains the DRG amount difference when the patient care crosses multiple institutions.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("233 - Services/charges related to the treatment of a hospital-acquired condition or preventable medical error.", ENT_QUOTES)) echo "selected"; ?> value="233 - Services/charges related to the treatment of a hospital-acquired condition or preventable medical error.">233 - Services/charges related to the treatment of a hospital-acquired condition or preventable medical error.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("234 - This procedure is not paid separately. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="234 - This procedure is not paid separately. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">234 - This procedure is not paid separately. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("235 - Sales Tax", ENT_QUOTES)) echo "selected"; ?> value="235 - Sales Tax">235 - Sales Tax</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("236 - This procedure or procedure/modifier combination is not compatible with another procedure or procedure/modifier combination provided on the same day according to the National Correct Coding Initiative or workers compensation state regulations/ fee schedule requirements.", ENT_QUOTES)) echo "selected"; ?> value="236 - This procedure or procedure/modifier combination is not compatible with another procedure or procedure/modifier combination provided on the same day according to the National Correct Coding Initiative or workers compensation state regulations/ fee schedule requirements.">236 - This procedure or procedure/modifier combination is not compatible with another procedure or procedure/modifier combination provided on the same day according to the National Correct Coding Initiative or workers compensation state regulations/ fee schedule requirements.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("237 - Legislated/Regulatory Penalty. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="237 - Legislated/Regulatory Penalty. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">237 - Legislated/Regulatory Penalty. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("238 - Claim spans eligible and ineligible periods of coverage, this is the reduction for the ineligible period. (Use only with Group Code PR)", ENT_QUOTES)) echo "selected"; ?> value="238 - Claim spans eligible and ineligible periods of coverage, this is the reduction for the ineligible period. (Use only with Group Code PR)">238 - Claim spans eligible and ineligible periods of coverage, this is the reduction for the ineligible period. (Use only with Group Code PR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("239 - Claim spans eligible and ineligible periods of coverage. Rebill separate claims.", ENT_QUOTES)) echo "selected"; ?> value="239 - Claim spans eligible and ineligible periods of coverage. Rebill separate claims.">239 - Claim spans eligible and ineligible periods of coverage. Rebill separate claims.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("240 - The diagnosis is inconsistent with the patient&#x27;s birth weight. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="240 - The diagnosis is inconsistent with the patient&#x27;s birth weight. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">240 - The diagnosis is inconsistent with the patient&#x27;s birth weight. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("241 - Low Income Subsidy (LIS) Co-payment Amount", ENT_QUOTES)) echo "selected"; ?> value="241 - Low Income Subsidy (LIS) Co-payment Amount">241 - Low Income Subsidy (LIS) Co-payment Amount</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("242 - Services not provided by network/primary care providers.", ENT_QUOTES)) echo "selected"; ?> value="242 - Services not provided by network/primary care providers.">242 - Services not provided by network/primary care providers.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("243 - Services not authorized by network/primary care providers.", ENT_QUOTES)) echo "selected"; ?> value="243 - Services not authorized by network/primary care providers.">243 - Services not authorized by network/primary care providers.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("245 - Provider performance program withhold.", ENT_QUOTES)) echo "selected"; ?> value="245 - Provider performance program withhold.">245 - Provider performance program withhold.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("246 - This non-payable code is for required reporting only.", ENT_QUOTES)) echo "selected"; ?> value="246 - This non-payable code is for required reporting only.">246 - This non-payable code is for required reporting only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("247 - Deductible for Professional service rendered in an Institutional setting and billed on an Institutional claim.", ENT_QUOTES)) echo "selected"; ?> value="247 - Deductible for Professional service rendered in an Institutional setting and billed on an Institutional claim.">247 - Deductible for Professional service rendered in an Institutional setting and billed on an Institutional claim.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("248 - Coinsurance for Professional service rendered in an Institutional setting and billed on an Institutional claim.", ENT_QUOTES)) echo "selected"; ?> value="248 - Coinsurance for Professional service rendered in an Institutional setting and billed on an Institutional claim.">248 - Coinsurance for Professional service rendered in an Institutional setting and billed on an Institutional claim.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("249 - This claim has been identified as a readmission. (Use only with Group Code CO)", ENT_QUOTES)) echo "selected"; ?> value="249 - This claim has been identified as a readmission. (Use only with Group Code CO)">249 - This claim has been identified as a readmission. (Use only with Group Code CO)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("250 - The attachment/other documentation that was received was the incorrect attachment/document. The expected attachment/document is still missing. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).", ENT_QUOTES)) echo "selected"; ?> value="250 - The attachment/other documentation that was received was the incorrect attachment/document. The expected attachment/document is still missing. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).">250 - The attachment/other documentation that was received was the incorrect attachment/document. The expected attachment/document is still missing. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("251 - The attachment/other documentation that was received was incomplete or deficient. The necessary information is still needed to process the claim. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).", ENT_QUOTES)) echo "selected"; ?> value="251 - The attachment/other documentation that was received was incomplete or deficient. The necessary information is still needed to process the claim. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).">251 - The attachment/other documentation that was received was incomplete or deficient. The necessary information is still needed to process the claim. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("252 - An attachment/other documentation is required to adjudicate this claim/service. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).", ENT_QUOTES)) echo "selected"; ?> value="252 - An attachment/other documentation is required to adjudicate this claim/service. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).">252 - An attachment/other documentation is required to adjudicate this claim/service. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("253 - Sequestration - reduction in federal payment", ENT_QUOTES)) echo "selected"; ?> value="253 - Sequestration - reduction in federal payment">253 - Sequestration - reduction in federal payment</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("254 - Claim received by the dental plan, but benefits not available under this plan. Submit these services to the patient&#x27;s medical plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="254 - Claim received by the dental plan, but benefits not available under this plan. Submit these services to the patient&#x27;s medical plan for further consideration.">254 - Claim received by the dental plan, but benefits not available under this plan. Submit these services to the patient&#x27;s medical plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("256 - Service not payable per managed care contract.", ENT_QUOTES)) echo "selected"; ?> value="256 - Service not payable per managed care contract.">256 - Service not payable per managed care contract.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("257 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="257 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)">257 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("258 - Claim/service not covered when patient is in custody/incarcerated. Applicable federal, state or local authority may cover the claim/service.", ENT_QUOTES)) echo "selected"; ?> value="258 - Claim/service not covered when patient is in custody/incarcerated. Applicable federal, state or local authority may cover the claim/service.">258 - Claim/service not covered when patient is in custody/incarcerated. Applicable federal, state or local authority may cover the claim/service.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("259 - Additional payment for Dental/Vision service utilization.", ENT_QUOTES)) echo "selected"; ?> value="259 - Additional payment for Dental/Vision service utilization.">259 - Additional payment for Dental/Vision service utilization.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("260 - Processed under Medicaid ACA Enhanced Fee Schedule", ENT_QUOTES)) echo "selected"; ?> value="260 - Processed under Medicaid ACA Enhanced Fee Schedule">260 - Processed under Medicaid ACA Enhanced Fee Schedule</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("261 - The procedure or service is inconsistent with the patient&#x27;s history.", ENT_QUOTES)) echo "selected"; ?> value="261 - The procedure or service is inconsistent with the patient&#x27;s history.">261 - The procedure or service is inconsistent with the patient&#x27;s history.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("262 - Adjustment for delivery cost. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="262 - Adjustment for delivery cost. Usage: To be used for pharmaceuticals only.">262 - Adjustment for delivery cost. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("263 - Adjustment for shipping cost. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="263 - Adjustment for shipping cost. Usage: To be used for pharmaceuticals only.">263 - Adjustment for shipping cost. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("264 - Adjustment for postage cost. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="264 - Adjustment for postage cost. Usage: To be used for pharmaceuticals only.">264 - Adjustment for postage cost. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("265 - Adjustment for administrative cost. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="265 - Adjustment for administrative cost. Usage: To be used for pharmaceuticals only.">265 - Adjustment for administrative cost. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("266 - Adjustment for compound preparation cost. Usage: To be used for pharmaceuticals only.", ENT_QUOTES)) echo "selected"; ?> value="266 - Adjustment for compound preparation cost. Usage: To be used for pharmaceuticals only.">266 - Adjustment for compound preparation cost. Usage: To be used for pharmaceuticals only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("267 - Claim/service spans multiple months. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)", ENT_QUOTES)) echo "selected"; ?> value="267 - Claim/service spans multiple months. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)">267 - Claim/service spans multiple months. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("268 - The Claim spans two calendar years. Please resubmit one claim per calendar year.", ENT_QUOTES)) echo "selected"; ?> value="268 - The Claim spans two calendar years. Please resubmit one claim per calendar year.">268 - The Claim spans two calendar years. Please resubmit one claim per calendar year.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("269 - Anesthesia not covered for this service/procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="269 - Anesthesia not covered for this service/procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">269 - Anesthesia not covered for this service/procedure. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("270 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s dental plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="270 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s dental plan for further consideration.">270 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s dental plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("271 - Prior contractual reductions related to a current periodic payment as part of a contractual payment schedule when deferred amounts have been previously reported. (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="271 - Prior contractual reductions related to a current periodic payment as part of a contractual payment schedule when deferred amounts have been previously reported. (Use only with Group Code OA)">271 - Prior contractual reductions related to a current periodic payment as part of a contractual payment schedule when deferred amounts have been previously reported. (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("272 - Coverage/program guidelines were not met.", ENT_QUOTES)) echo "selected"; ?> value="272 - Coverage/program guidelines were not met.">272 - Coverage/program guidelines were not met.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("273 - Coverage/program guidelines were exceeded.", ENT_QUOTES)) echo "selected"; ?> value="273 - Coverage/program guidelines were exceeded.">273 - Coverage/program guidelines were exceeded.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("274 - Fee/Service not payable per patient Care Coordination arrangement.", ENT_QUOTES)) echo "selected"; ?> value="274 - Fee/Service not payable per patient Care Coordination arrangement.">274 - Fee/Service not payable per patient Care Coordination arrangement.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("275 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered. (Use only with Group Code PR)", ENT_QUOTES)) echo "selected"; ?> value="275 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered. (Use only with Group Code PR)">275 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered. (Use only with Group Code PR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("276 - Services denied by the prior payer(s) are not covered by this payer.", ENT_QUOTES)) echo "selected"; ?> value="276 - Services denied by the prior payer(s) are not covered by this payer.">276 - Services denied by the prior payer(s) are not covered by this payer.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("277 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance SHOP Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="277 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance SHOP Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)">277 - The disposition of the claim/service is undetermined during the premium payment grace period, per Health Insurance SHOP Exchange requirements. This claim/service will be reversed and corrected when the grace period ends (due to premium payment or lack of premium payment). (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("278 - Performance program proficiency requirements not met. (Use only with Group Codes CO or PI) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="278 - Performance program proficiency requirements not met. (Use only with Group Codes CO or PI) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">278 - Performance program proficiency requirements not met. (Use only with Group Codes CO or PI) Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("279 - Services not provided by Preferred network providers. Usage: Use this code when there are member network limitations. For example, using contracted providers not in the member&#x27;s &#x27;narrow&#x27; network.", ENT_QUOTES)) echo "selected"; ?> value="279 - Services not provided by Preferred network providers. Usage: Use this code when there are member network limitations. For example, using contracted providers not in the member&#x27;s &#x27;narrow&#x27; network.">279 - Services not provided by Preferred network providers. Usage: Use this code when there are member network limitations. For example, using contracted providers not in the member&#x27;s &#x27;narrow&#x27; network.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("280 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Pharmacy plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="280 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Pharmacy plan for further consideration.">280 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Pharmacy plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("281 - Deductible waived per contractual agreement. Use only with Group Code CO.", ENT_QUOTES)) echo "selected"; ?> value="281 - Deductible waived per contractual agreement. Use only with Group Code CO.">281 - Deductible waived per contractual agreement. Use only with Group Code CO.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("282 - The procedure/revenue code is inconsistent with the type of bill. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="282 - The procedure/revenue code is inconsistent with the type of bill. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">282 - The procedure/revenue code is inconsistent with the type of bill. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("283 - Attending provider is not eligible to provide direction of care.", ENT_QUOTES)) echo "selected"; ?> value="283 - Attending provider is not eligible to provide direction of care.">283 - Attending provider is not eligible to provide direction of care.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("284 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the billed services.", ENT_QUOTES)) echo "selected"; ?> value="284 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the billed services.">284 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the billed services.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("285 - Appeal procedures not followed", ENT_QUOTES)) echo "selected"; ?> value="285 - Appeal procedures not followed">285 - Appeal procedures not followed</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("286 - Appeal time limits not met", ENT_QUOTES)) echo "selected"; ?> value="286 - Appeal time limits not met">286 - Appeal time limits not met</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("287 - Referral exceeded", ENT_QUOTES)) echo "selected"; ?> value="287 - Referral exceeded">287 - Referral exceeded</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("288 - Referral absent", ENT_QUOTES)) echo "selected"; ?> value="288 - Referral absent">288 - Referral absent</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("289 - Services considered under the dental and medical plans, benefits not available.", ENT_QUOTES)) echo "selected"; ?> value="289 - Services considered under the dental and medical plans, benefits not available.">289 - Services considered under the dental and medical plans, benefits not available.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("290 - Claim received by the dental plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s medical plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="290 - Claim received by the dental plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s medical plan for further consideration.">290 - Claim received by the dental plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s medical plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("291 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s dental plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="291 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s dental plan for further consideration.">291 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s dental plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("292 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s pharmacy plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="292 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s pharmacy plan for further consideration.">292 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s pharmacy plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("293 - Payment made to employer.", ENT_QUOTES)) echo "selected"; ?> value="293 - Payment made to employer.">293 - Payment made to employer.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("294 - Payment made to attorney.", ENT_QUOTES)) echo "selected"; ?> value="294 - Payment made to attorney.">294 - Payment made to attorney.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("295 - Pharmacy Direct/Indirect Remuneration (DIR)", ENT_QUOTES)) echo "selected"; ?> value="295 - Pharmacy Direct/Indirect Remuneration (DIR)">295 - Pharmacy Direct/Indirect Remuneration (DIR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("296 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the provider.", ENT_QUOTES)) echo "selected"; ?> value="296 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the provider.">296 - Precertification/authorization/notification/pre-treatment number may be valid but does not apply to the provider.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("297 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s vision plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="297 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s vision plan for further consideration.">297 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s vision plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("298 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s vision plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="298 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s vision plan for further consideration.">298 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s vision plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("299 - The billing provider is not eligible to receive payment for the service billed.", ENT_QUOTES)) echo "selected"; ?> value="299 - The billing provider is not eligible to receive payment for the service billed.">299 - The billing provider is not eligible to receive payment for the service billed.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("300 - Claim received by the Medical Plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s Behavioral Health Plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="300 - Claim received by the Medical Plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s Behavioral Health Plan for further consideration.">300 - Claim received by the Medical Plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s Behavioral Health Plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("301 - Claim received by the Medical Plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Behavioral Health Plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="301 - Claim received by the Medical Plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Behavioral Health Plan for further consideration.">301 - Claim received by the Medical Plan, but benefits not available under this plan. Submit these services to the patient&#x27;s Behavioral Health Plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("302 - Precertification/notification/authorization/pre-treatment time limit has expired.", ENT_QUOTES)) echo "selected"; ?> value="302 - Precertification/notification/authorization/pre-treatment time limit has expired.">302 - Precertification/notification/authorization/pre-treatment time limit has expired.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("303 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered for Qualified Medicare and Medicaid Beneficiaries. (Use only with Group Code CO)", ENT_QUOTES)) echo "selected"; ?> value="303 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered for Qualified Medicare and Medicaid Beneficiaries. (Use only with Group Code CO)">303 - Prior payer&#x27;s (or payers&#x27;) patient responsibility (deductible, coinsurance, co-payment) not covered for Qualified Medicare and Medicaid Beneficiaries. (Use only with Group Code CO)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("304 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s hearing plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="304 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s hearing plan for further consideration.">304 - Claim received by the medical plan, but benefits not available under this plan. Submit these services to the patient&#x27;s hearing plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("305 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s hearing plan for further consideration.", ENT_QUOTES)) echo "selected"; ?> value="305 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s hearing plan for further consideration.">305 - Claim received by the medical plan, but benefits not available under this plan. Claim has been forwarded to the patient&#x27;s hearing plan for further consideration.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("306 - Type of bill is inconsistent with the patient status. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="306 - Type of bill is inconsistent with the patient status. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">306 - Type of bill is inconsistent with the patient status. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("307 - Medicare Maximum Fair Price Standard Default Refund Amount Adjustment. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: To be used only for the Medicare Drug Price Negotiation Program.", ENT_QUOTES)) echo "selected"; ?> value="307 - Medicare Maximum Fair Price Standard Default Refund Amount Adjustment. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: To be used only for the Medicare Drug Price Negotiation Program.">307 - Medicare Maximum Fair Price Standard Default Refund Amount Adjustment. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: To be used only for the Medicare Drug Price Negotiation Program.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("A0 - Patient refund amount.", ENT_QUOTES)) echo "selected"; ?> value="A0 - Patient refund amount.">A0 - Patient refund amount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("A1 - Claim/Service denied. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Use this code only when a more specific Claim Adjustment Reason Code is not available.", ENT_QUOTES)) echo "selected"; ?> value="A1 - Claim/Service denied. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Use this code only when a more specific Claim Adjustment Reason Code is not available.">A1 - Claim/Service denied. At least one Remark Code must be provided (may be comprised of either the NCPDP Reject Reason Code, or Remittance Advice Remark Code that is not an ALERT.) Usage: Use this code only when a more specific Claim Adjustment Reason Code is not available.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("A5 - Medicare Claim PPS Capital Cost Outlier Amount.", ENT_QUOTES)) echo "selected"; ?> value="A5 - Medicare Claim PPS Capital Cost Outlier Amount.">A5 - Medicare Claim PPS Capital Cost Outlier Amount.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("A6 - Prior hospitalization or 30 day transfer requirement not met.", ENT_QUOTES)) echo "selected"; ?> value="A6 - Prior hospitalization or 30 day transfer requirement not met.">A6 - Prior hospitalization or 30 day transfer requirement not met.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("A8 - Ungroupable DRG.", ENT_QUOTES)) echo "selected"; ?> value="A8 - Ungroupable DRG.">A8 - Ungroupable DRG.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B1 - Non-covered visits.", ENT_QUOTES)) echo "selected"; ?> value="B1 - Non-covered visits.">B1 - Non-covered visits.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B4 - Late filing penalty.", ENT_QUOTES)) echo "selected"; ?> value="B4 - Late filing penalty.">B4 - Late filing penalty.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B7 - This provider was not certified/eligible to be paid for this procedure/service on this date of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="B7 - This provider was not certified/eligible to be paid for this procedure/service on this date of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">B7 - This provider was not certified/eligible to be paid for this procedure/service on this date of service. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B8 - Alternative services were available, and should have been utilized. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="B8 - Alternative services were available, and should have been utilized. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">B8 - Alternative services were available, and should have been utilized. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B9 - Patient is enrolled in a Hospice.", ENT_QUOTES)) echo "selected"; ?> value="B9 - Patient is enrolled in a Hospice.">B9 - Patient is enrolled in a Hospice.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B10 - Allowed amount has been reduced because a component of the basic procedure/test was paid. The beneficiary is not liable for more than the charge limit for the basic procedure/test.", ENT_QUOTES)) echo "selected"; ?> value="B10 - Allowed amount has been reduced because a component of the basic procedure/test was paid. The beneficiary is not liable for more than the charge limit for the basic procedure/test.">B10 - Allowed amount has been reduced because a component of the basic procedure/test was paid. The beneficiary is not liable for more than the charge limit for the basic procedure/test.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B11 - The claim/service has been transferred to the proper payer/processor for processing. Claim/service not covered by this payer/processor.", ENT_QUOTES)) echo "selected"; ?> value="B11 - The claim/service has been transferred to the proper payer/processor for processing. Claim/service not covered by this payer/processor.">B11 - The claim/service has been transferred to the proper payer/processor for processing. Claim/service not covered by this payer/processor.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B12 - Services not documented in patient&#x27;s medical records.", ENT_QUOTES)) echo "selected"; ?> value="B12 - Services not documented in patient&#x27;s medical records.">B12 - Services not documented in patient&#x27;s medical records.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B13 - Previously paid. Payment for this claim/service may have been provided in a previous payment.", ENT_QUOTES)) echo "selected"; ?> value="B13 - Previously paid. Payment for this claim/service may have been provided in a previous payment.">B13 - Previously paid. Payment for this claim/service may have been provided in a previous payment.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B14 - Only one visit or consultation per physician per day is covered.", ENT_QUOTES)) echo "selected"; ?> value="B14 - Only one visit or consultation per physician per day is covered.">B14 - Only one visit or consultation per physician per day is covered.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B15 - This service/procedure requires that a qualifying service/procedure be received and covered. The qualifying other service/procedure has not been received/adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.", ENT_QUOTES)) echo "selected"; ?> value="B15 - This service/procedure requires that a qualifying service/procedure be received and covered. The qualifying other service/procedure has not been received/adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.">B15 - This service/procedure requires that a qualifying service/procedure be received and covered. The qualifying other service/procedure has not been received/adjudicated. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B16 - &#x27;New Patient&#x27; qualifications were not met.", ENT_QUOTES)) echo "selected"; ?> value="B16 - &#x27;New Patient&#x27; qualifications were not met.">B16 - &#x27;New Patient&#x27; qualifications were not met.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B20 - Procedure/service was partially or fully furnished by another provider.", ENT_QUOTES)) echo "selected"; ?> value="B20 - Procedure/service was partially or fully furnished by another provider.">B20 - Procedure/service was partially or fully furnished by another provider.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B22 - This payment is adjusted based on the diagnosis.", ENT_QUOTES)) echo "selected"; ?> value="B22 - This payment is adjusted based on the diagnosis.">B22 - This payment is adjusted based on the diagnosis.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("B23 - Procedure billed is not authorized per your Clinical Laboratory Improvement Amendment (CLIA) proficiency test.", ENT_QUOTES)) echo "selected"; ?> value="B23 - Procedure billed is not authorized per your Clinical Laboratory Improvement Amendment (CLIA) proficiency test.">B23 - Procedure billed is not authorized per your Clinical Laboratory Improvement Amendment (CLIA) proficiency test.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P1 - State-mandated Requirement for Property and Casualty, see Claim Payment Remarks Code for specific explanation. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P1 - State-mandated Requirement for Property and Casualty, see Claim Payment Remarks Code for specific explanation. To be used for Property and Casualty only.">P1 - State-mandated Requirement for Property and Casualty, see Claim Payment Remarks Code for specific explanation. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P2 - Not a work related injury/illness and thus not the liability of the workers&#x27; compensation carrier Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only.", ENT_QUOTES)) echo "selected"; ?> value="P2 - Not a work related injury/illness and thus not the liability of the workers&#x27; compensation carrier Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only.">P2 - Not a work related injury/illness and thus not the liability of the workers&#x27; compensation carrier Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P3 - Workers&#x27; Compensation case settled. Patient is responsible for amount of this claim/service through WC &#x27;Medicare set aside arrangement&#x27; or other agreement. To be used for Workers&#x27; Compensation only. (Use only with Group Code PR)", ENT_QUOTES)) echo "selected"; ?> value="P3 - Workers&#x27; Compensation case settled. Patient is responsible for amount of this claim/service through WC &#x27;Medicare set aside arrangement&#x27; or other agreement. To be used for Workers&#x27; Compensation only. (Use only with Group Code PR)">P3 - Workers&#x27; Compensation case settled. Patient is responsible for amount of this claim/service through WC &#x27;Medicare set aside arrangement&#x27; or other agreement. To be used for Workers&#x27; Compensation only. (Use only with Group Code PR)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P4 - Workers&#x27; Compensation claim adjudicated as non-compensable. This Payer not liable for claim or service/treatment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only", ENT_QUOTES)) echo "selected"; ?> value="P4 - Workers&#x27; Compensation claim adjudicated as non-compensable. This Payer not liable for claim or service/treatment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only">P4 - Workers&#x27; Compensation claim adjudicated as non-compensable. This Payer not liable for claim or service/treatment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Workers&#x27; Compensation only</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P5 - Based on payer reasonable and customary fees. No maximum allowable defined by legislated fee arrangement. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P5 - Based on payer reasonable and customary fees. No maximum allowable defined by legislated fee arrangement. To be used for Property and Casualty only.">P5 - Based on payer reasonable and customary fees. No maximum allowable defined by legislated fee arrangement. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P6 - Based on entitlement to benefits. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P6 - Based on entitlement to benefits. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.">P6 - Based on entitlement to benefits. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P7 - The applicable fee schedule/fee database does not contain the billed code. Please resubmit a bill with the appropriate fee schedule/fee database code(s) that best describe the service(s) provided and supporting documentation if required. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P7 - The applicable fee schedule/fee database does not contain the billed code. Please resubmit a bill with the appropriate fee schedule/fee database code(s) that best describe the service(s) provided and supporting documentation if required. To be used for Property and Casualty only.">P7 - The applicable fee schedule/fee database does not contain the billed code. Please resubmit a bill with the appropriate fee schedule/fee database code(s) that best describe the service(s) provided and supporting documentation if required. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P8 - Claim is under investigation. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P8 - Claim is under investigation. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.">P8 - Claim is under investigation. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) for the jurisdictional regulation. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF). To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P9 - No available or correlating CPT/HCPCS code to describe this service. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P9 - No available or correlating CPT/HCPCS code to describe this service. To be used for Property and Casualty only.">P9 - No available or correlating CPT/HCPCS code to describe this service. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P10 - Payment reduced to zero due to litigation. Additional information will be sent following the conclusion of litigation. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P10 - Payment reduced to zero due to litigation. Additional information will be sent following the conclusion of litigation. To be used for Property and Casualty only.">P10 - Payment reduced to zero due to litigation. Additional information will be sent following the conclusion of litigation. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P11 - The disposition of the related Property &amp; Casualty claim (injury or illness) is pending due to litigation. To be used for Property and Casualty only. (Use only with Group Code OA)", ENT_QUOTES)) echo "selected"; ?> value="P11 - The disposition of the related Property &amp; Casualty claim (injury or illness) is pending due to litigation. To be used for Property and Casualty only. (Use only with Group Code OA)">P11 - The disposition of the related Property &amp; Casualty claim (injury or illness) is pending due to litigation. To be used for Property and Casualty only. (Use only with Group Code OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P12 - Workers&#x27; compensation jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.", ENT_QUOTES)) echo "selected"; ?> value="P12 - Workers&#x27; compensation jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.">P12 - Workers&#x27; compensation jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P13 - Payment reduced or denied based on workers&#x27; compensation jurisdictional regulations or payment policies, use only if no other code is applicable. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.", ENT_QUOTES)) echo "selected"; ?> value="P13 - Payment reduced or denied based on workers&#x27; compensation jurisdictional regulations or payment policies, use only if no other code is applicable. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.">P13 - Payment reduced or denied based on workers&#x27; compensation jurisdictional regulations or payment policies, use only if no other code is applicable. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Workers&#x27; Compensation only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P14 - The Benefit for this Service is included in the payment/allowance for another service/procedure that has been performed on the same day. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P14 - The Benefit for this Service is included in the payment/allowance for another service/procedure that has been performed on the same day. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present. To be used for Property and Casualty only.">P14 - The Benefit for this Service is included in the payment/allowance for another service/procedure that has been performed on the same day. Usage: Refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment Information REF), if present. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P15 - Workers&#x27; Compensation Medical Treatment Guideline Adjustment. To be used for Workers&#x27; Compensation only.", ENT_QUOTES)) echo "selected"; ?> value="P15 - Workers&#x27; Compensation Medical Treatment Guideline Adjustment. To be used for Workers&#x27; Compensation only.">P15 - Workers&#x27; Compensation Medical Treatment Guideline Adjustment. To be used for Workers&#x27; Compensation only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P16 - Medical provider not authorized/certified to provide treatment to injured workers in this jurisdiction. To be used for Workers&#x27; Compensation only. (Use with Group Code CO or OA)", ENT_QUOTES)) echo "selected"; ?> value="P16 - Medical provider not authorized/certified to provide treatment to injured workers in this jurisdiction. To be used for Workers&#x27; Compensation only. (Use with Group Code CO or OA)">P16 - Medical provider not authorized/certified to provide treatment to injured workers in this jurisdiction. To be used for Workers&#x27; Compensation only. (Use with Group Code CO or OA)</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P17 - Referral not authorized by attending physician per regulatory requirement. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P17 - Referral not authorized by attending physician per regulatory requirement. To be used for Property and Casualty only.">P17 - Referral not authorized by attending physician per regulatory requirement. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P18 - Procedure is not listed in the jurisdiction fee schedule. An allowance has been made for a comparable service. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P18 - Procedure is not listed in the jurisdiction fee schedule. An allowance has been made for a comparable service. To be used for Property and Casualty only.">P18 - Procedure is not listed in the jurisdiction fee schedule. An allowance has been made for a comparable service. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P19 - Procedure has a relative value of zero in the jurisdiction fee schedule, therefore no payment is due. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P19 - Procedure has a relative value of zero in the jurisdiction fee schedule, therefore no payment is due. To be used for Property and Casualty only.">P19 - Procedure has a relative value of zero in the jurisdiction fee schedule, therefore no payment is due. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P20 - Service not paid under jurisdiction allowed outpatient facility fee schedule. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P20 - Service not paid under jurisdiction allowed outpatient facility fee schedule. To be used for Property and Casualty only.">P20 - Service not paid under jurisdiction allowed outpatient facility fee schedule. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P21 - Payment denied based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P21 - Payment denied based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P21 - Payment denied based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P22 - Payment adjusted based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P22 - Payment adjusted based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P22 - Payment adjusted based on the Medical Payments Coverage (MPC) and/or Personal Injury Protection (PIP) Benefits jurisdictional regulations, or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P23 - Medical Payments Coverage (MPC) or Personal Injury Protection (PIP) Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P23 - Medical Payments Coverage (MPC) or Personal Injury Protection (PIP) Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P23 - Medical Payments Coverage (MPC) or Personal Injury Protection (PIP) Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P24 - Payment adjusted based on Preferred Provider Organization (PPO). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. Use only with Group Code CO.", ENT_QUOTES)) echo "selected"; ?> value="P24 - Payment adjusted based on Preferred Provider Organization (PPO). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. Use only with Group Code CO.">P24 - Payment adjusted based on Preferred Provider Organization (PPO). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. Use only with Group Code CO.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P25 - Payment adjusted based on Medical Provider Network (MPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).", ENT_QUOTES)) echo "selected"; ?> value="P25 - Payment adjusted based on Medical Provider Network (MPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).">P25 - Payment adjusted based on Medical Provider Network (MPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P26 - Payment adjusted based on Voluntary Provider network (VPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).", ENT_QUOTES)) echo "selected"; ?> value="P26 - Payment adjusted based on Voluntary Provider network (VPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).">P26 - Payment adjusted based on Voluntary Provider network (VPN). Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty only. (Use only with Group Code CO).</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P27 - Payment denied based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P27 - Payment denied based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P27 - Payment denied based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P28 - Payment adjusted based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P28 - Payment adjusted based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P28 - Payment adjusted based on the Liability Coverage Benefits jurisdictional regulations and/or payment policies. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Insurance Policy Number Segment (Loop 2100 Other Claim Related Information REF qualifier &#x27;IG&#x27;) if the jurisdictional regulation applies. If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P29 - Liability Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.", ENT_QUOTES)) echo "selected"; ?> value="P29 - Liability Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.">P29 - Liability Benefits jurisdictional fee schedule adjustment. Usage: If adjustment is at the Claim Level, the payer must send and the provider should refer to the 835 Class of Contract Code Identification Segment (Loop 2100 Other Claim Related Information REF). If adjustment is at the Line Level, the payer must send and the provider should refer to the 835 Healthcare Policy Identification Segment (loop 2110 Service Payment information REF) if the regulations apply. To be used for Property and Casualty Auto only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P30 - Payment denied for exacerbation when supporting documentation was not complete. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P30 - Payment denied for exacerbation when supporting documentation was not complete. To be used for Property and Casualty only.">P30 - Payment denied for exacerbation when supporting documentation was not complete. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P31 - Payment denied for exacerbation when treatment exceeds time allowed. To be used for Property and Casualty only.", ENT_QUOTES)) echo "selected"; ?> value="P31 - Payment denied for exacerbation when treatment exceeds time allowed. To be used for Property and Casualty only.">P31 - Payment denied for exacerbation when treatment exceeds time allowed. To be used for Property and Casualty only.</option>
<option <?php if(trim((string)$FollowUpReasonDB) === html_entity_decode("P32 - Payment adjusted due to Apportionment.", ENT_QUOTES)) echo "selected"; ?> value="P32 - Payment adjusted due to Apportionment.">P32 - Payment adjusted due to Apportionment.</option></select>

                                    <!-- <input id="FollowUpReason<?php echo attr($CountIndex); ?>"
                                           name="FollowUpReason<?php echo attr($CountIndex); ?>"
                                           onkeydown="PreventIt(event)" class=" input-sm w-100" type="text"
                                           value="<?php echo attr($FollowUpReasonDB); ?>" readonly> -->
                                </td>
                                </tr><?php
                            }//End of while ($RowSearch = sqlFetchArray($ResultSearch))
                            ?>
                            <?php
                        }//End of if(sqlNumRows($ResultSearch)>0)
                    } while ($RowSearchSub = sqlFetchArray($ResultSearchSub));
                    if ($Table == 'yes') { ?>
                        <tr>
                            <td class="text-right text-dark" align="left" colspan="9">
                                <b><?php echo(xlt("Totals") . ": ") ?></b></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="allowtotal"><?php echo text(number_format($allowedtot, 2)); ?></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="paymenttotal"><?php echo text(number_format($paymenttot, 2)); ?></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="AdjAmounttotal"><?php echo text(number_format($adjamttot, 2)); ?></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="deductibletotal"><?php echo text(number_format($deductibletot, 2)); ?></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="coInstotal"><?php echo text(number_format($coInsurancetot, 2)); ?></td>
                            <td class="bg-dark text-secondary" align="center"
                                id="takebacktotal"><?php echo text(number_format($takebacktot, 2)); ?></td>
                            <td align="center" colspan="2">&nbsp;</td>
                            <td align="right">
                                <button type="button" class="btn btn-sm btn-secondary btn-refresh pull-right"
                                        onclick="updateAllFormTotals(<?php echo attr_js($TotalRows); ?>);"><?php echo xlt("Recalculate"); ?></button>
                            </td>
                        </tr>
                        </table>
                        <?php
                    }
                    ?>
                    <?php
                    echo '<br/>';
                }//End of if($RowSearchSub = sqlFetchArray($ResultSearchSub))
                ?>
                </div>
                </div>
                <div>
                    <?php
                    require_once("payment_pat_sel.inc.php"); //Patient ajax section and listing of charges.
                    ?>
                </div>
                <?php
            }//End of if($payment_id*1>0)
            ?>
            <?php //can change position of buttons by creating a class 'position-override' and adding rule text-align:center or right as the case may be in individual stylesheets ?>
            <div class="form-group clearfix">
                <div class="col-sm-12 text-left position-override">
                    <div class="btn-group" role="group">
                        <a class="btn btn-secondary btn-save" href="#"
                           onclick="return ModifyPayments();"><span><?php echo xlt('Modify Payments'); ?></span></a>
                        <a class="btn btn-secondary btn-save" href="#"
                           onclick="return FinishPayments();"><span><?php echo xlt('Finish Payments'); ?></span></a>
                    </div>
                </div>
            </div>
            <div class="row">
                <input type="hidden" name="hidden_patient_code" id="hidden_patient_code"
                       value="<?php echo attr($hidden_patient_code ?? ''); ?>"/>
                <input type='hidden' name='mode' id='mode' value=''/>
                <input type='hidden' name='ajax_mode' id='ajax_mode' value=''/>
                <input type="hidden" name="after_value" id="after_value"
                       value="<?php echo attr($_POST["mode"] ?? ''); ?>"/>
                <input type="hidden" name="payment_id" id="payment_id" value="<?php echo attr($payment_id); ?>"/>
                <input type="hidden" name="hidden_type_code" id="hidden_type_code"
                       value="<?php echo attr($TypeCode); ?>"/>
                <input type='hidden' name='global_amount' id='global_amount' value=''/>
                <input type='hidden' name='DeletePaymentDistributionId' id='DeletePaymentDistributionId' value=''/>
                <input type="hidden" name="ActionStatus" id="ActionStatus" value="<?php echo attr($Message ?? ''); ?>"/>
                <input type='hidden' name='CountIndexAbove' id='CountIndexAbove'
                       value='<?php echo (int)attr($CountIndexAbove); ?>'/>
                <input type='hidden' name='CountIndexBelow' id='CountIndexBelow'
                       value='<?php echo (int)attr($CountIndexBelow); ?>'/>
                <input type="hidden" name="ParentPage" id="ParentPage"
                       value="<?php echo attr($_REQUEST['ParentPage'] ?? ''); ?>"/>
            </div>
    </form>
</div><!-- End of container div-->
<script>

    function ResetForm() {//Resets form used in the 'Cancel Changes' button in the master screen.
        document.forms[0].reset();
        document.getElementById('TdUnappliedAmount').innerHTML = '0.00';
        document.getElementById('div_insurance_or_patient').innerHTML = '&nbsp;';
        CheckVisible('yes');//Payment Method is made 'Check Payment' and the Check box is made visible.
        PayingEntityAction();//Paying Entity is made 'insurance' and Payment Category is 'Insurance Payment'
    }

    $(function () {
        if (document.getElementById("TableDistributePortion")) {
            $("html").animate({scrollTop: $("#TableDistributePortion").offset().top}, 800);
        } else if (document.getElementById("TableDistributedEdit")) {
            $("html").animate({scrollTop: $("#TableDistributedEdit").offset().top}, 800);
        }
    });
</script>
</body>
</html>