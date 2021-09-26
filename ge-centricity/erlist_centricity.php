<HTML>
<link rel="stylesheet" type="text/css" href="erlist.css">
<BODY bgcolor="#000000">
<div id="main">

<?php
    /*
    Copyright (C) 2021 Tim O'Connell

    This file is part of PACS-study-display.

    PACS-study-display is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PACS-study-display is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with PACS-study-display.  If not, see <http://www.gnu.org/licenses/>.
    */

//*************************************************************************************
// Declare our variables and time logic
//*************************************************************************************
$ct_array = array();
$cr_array = array();
$us_array = array();
$oied_array = array();
$ord_array = array();

$userTimezone = "America/New_York";
date_default_timezone_set($userTimezone);

$database_name = "ims";

// CUSTOMIZE: Change the USERNAME/PASSWORD to be whatever the Sybase SQL passwords are below
$database_username = "USERNAME";
$database_password = "PASSWORD";
$error_message = "ERROR: Could not connect to Centricity Sybase Database.  \n";
date_default_timezone_set('America/New_York');

// CUSTOMIZE: This example is created to run on Windows, and thus an ODBC connection to the Centricity Sybase
  // back-end should be created in the Windows ODBC manager called 'centricity32'
  // or determine what Sybase/Centricity connection type you will use.
$connection = odbc_connect('centricity32',$database_username,$database_password) or die($error_message);

// CUSTOMIZE: customize the time logic for however your ER department runs; the below was created to show
  // how this script would have to run for an ER department that starts reading other site cases at 5 pm
$globaltime_now = date("M  j Y  h:i:sA");
$globaltime_ago = date(("M  j Y  h:i:sA"), time()-21600); // 6 hours
$globaltime_ago_5pm = date("M  j Y  ") . "05:00:00PM"; 
$globaltime_future = date(("M  j Y  h:i:sA"), time()+10800);

// The 'end_of_time' variable below is to calculate times properly based on how Centricity stores times
$end_of_time = strtotime("2030-01-01 00:00:00");
$now = strtotime("now");
$four_hours_ago =  strtotime("-4 hours");
$subtract_now = $end_of_time - $now;
$subtract_ago = $subtract_now - 28800;
$dow = date("l");
$hour = date("H");

// Just for debugging
//ini_set('display_errors', 'On');
//ini_set('html_errors', 'On');

//*************************************************************************************
// Start Main Function here
//*************************************************************************************

// CUSTOMIZE: Change 'blank_logo.png' to be the institution logo(s) (if you want them) to display on your display
echo "<table align=\"center\" class=\"title\" border=\"0\"><tr><td width=\"200\"><img class=\"float_left\" src=\"blank_logo.png\"></td><td class=\"heading\">ER Radiology Studies</td><td width=\"200\"><img class=\"float_right\" height=\"42\" width=\"113\" src=\"blank_logo.png\"></td></tr></table>";
echo "\n";

// The first step is to perform 3 separate SQL queries on the Centricity DB to get the 
// CT scans, CR studies, and ordered studies in the time windows that we're looking for
get_ct();
get_cr();
get_us();
get_oied();
get_ordered();

// Next, we create DICOM-like times/dates and add them to the arrays, which helps for sorting
ct_create_times();
cr_create_times();
us_create_times();
oied_create_times();
ord_create_times();

// Third: clean up the data to make it more readable/presentable, and sort the arrays in 
// most recent studies come first
ct_sort_clean();
cr_sort_clean();
us_sort_clean();
oied_sort_clean();
ordered_sort_clean();

// Now, we have to find out who the radiologist is that has read the report and the study status
// we perform another SQL query here
ct_get_rad_status(); 
cr_get_rad_status();
us_get_rad_status();
oied_get_rad_status();

//print "<pre>\n";              //DEBUG
//print_r($oied_array);         //DEBUG

// Finally, present the data as HTML tables
echo "<table align=\"center\" border=\"0\" width=\"1280\">\n";
echo "<tr><td valign=top>";
draw_ct_table();
echo "<br>\n";
draw_us_table();
echo "</td>\n<td valign=top>";
draw_cr_table();
echo "<br>\n";
draw_oied_table();
//echo "<br>\n";
echo "</td></tr>";
echo "<tr><td valign=top><br>";
draw_ordered_us_table();
echo "</td><td><br>";
draw_ordered_ct_table();
echo "</td></tr></table>";
odbc_close($connection);

//*************************************************************************************
// Start Subroutines here
//*************************************************************************************
// NB: we may need to use inv_acq_time (an indexed column) here as there is a discrepancy between study time and acquisition time
// for the SQL query range and acq_dttm isn't indexed, so queries take too long

function get_ct() {
    // This is a complex function that runs different queries for performed CT scan depending on 
      // time of day and day of week
    global $connection, $ct_array, $globaltime_ago, $globaltime_now, $globaltime_ago_5pm, $dow, $hour;
    $count = 0;
    $row = array();

    // The logic below is to deal with having to display studies from other sites on Saturdays/Sundays
    if (($dow == "Saturday") || ($dow == "Sunday")) {
        $ct_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name,
            patient.pat_loc_ckey
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CT' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0;
    ";
    }
    elseif (($hour >= 17) && ($hour <= 22)) {
        $ct_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name,
            patient.pat_loc_ckey
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CT' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            )
            AND total_frames > 0 
            UNION ALL
            SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name,
            patient.pat_loc_ckey
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago_5pm . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CT' 
            AND (examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0
    ";  
    }
    elseif (($hour >= 23) || ($hour < 8)) {
        $ct_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name,
            patient.pat_loc_ckey
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CT' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0;
            ";
    }
    else {
    $ct_query = "SELECT 
        examination.exam_ckey, 
        examination.dept_ckey, 
        examination.procedure_ckey, 
        convert(char(27), study_dttm, 9) as study_dttm, 
        convert(char(27), acq_dttm, 9) as acq_dttm, 
        ris_exam_id, 
        examination.pat_ckey, 
        clinical_cmnt_text, 
        exam_procedure.procedure_code, 
        exam_procedure.procedure_desc, 
        pat_name 
        FROM 
        examination, 
        patient, 
        exam_procedure 
        WHERE 
        examination.pat_ckey=patient.pat_ckey 
        AND examination.procedure_ckey=exam_procedure.procedure_ckey 
        AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
        AND exam_procedure.modality_code='CT' 
        AND (examination.dept_ckey=61 OR examination.dept_ckey=62)
        AND total_frames > 0;";
    }

    $find_ct = odbc_exec($connection, $ct_query);
    if (!$find_ct) {
        echo "Could not run query: $ct_query";
        exit;
    }

    // echo "<pre>\n";       //DEBUG
    while ($row = odbc_fetch_array($find_ct)) {
        // print_r($row);      //DEBUG
        foreach ($row as $a=>$b) {
            $ct_array[$count][$a] = $b; 
        }
        $count++;
    }
    odbc_free_result($find_ct);
}

function get_cr() {
    global $connection, $cr_array, $globaltime_now, $globaltime_ago_5pm, $globaltime_ago, $dow, $hour;

    $count = 0;
    $cr_row = array();

    // First, check if we should be displaying other-site studies
    if (($dow == "Saturday") || ($dow == "Sunday")) {
        $cr_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CR' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=39
            OR examination.dept_ckey=42
            )
            AND total_frames > 0;"; 
    }
    elseif (($hour >= 17) && ($hour <= 22)) {
        $cr_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CR' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            )
            AND total_frames > 0
            UNION ALL
            SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago_5pm . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CR' 
            AND (examination.dept_ckey=54
            OR examination.dept_ckey=39
            OR examination.dept_ckey=42
            )
            AND total_frames > 0;"; 
    }
    elseif (($hour >= 23) || ($hour < 8)) {
        $cr_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CR' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=39
            OR examination.dept_ckey=42
            )
            AND total_frames > 0;"; 
    }
    else {
        $cr_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='CR' 
            AND (examination.dept_ckey=61 OR examination.dept_ckey=62)
            AND total_frames > 0;"; 
    }

    $find_cr = odbc_exec($connection, $cr_query);
    if (!$find_cr) {
        echo "Could not run query: $cr_query";
        exit;
        }
        while ($cr_row = odbc_fetch_array($find_cr)) {
            foreach ($cr_row as $a=>$b) {
                $cr_array[$count][$a] = $b; 
        }
        $count++;
    }
    odbc_free_result($find_cr);
}

function get_us() {
    global $connection, $us_array, $globaltime_ago, $globaltime_now, $globaltime_ago_5pm, $dow, $hour;
    $count = 0;
    $row = array();

    // First, check if we should be displaying other-site studies
    if (($dow == "Saturday") || ($dow == "Sunday")) {
        $us_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey
            AND exam_procedure.procedure_desc NOT LIKE '%BEDSIDE%'
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='US' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0;";
    }
    elseif (($hour >= 17) && ($hour <= 22)) {
        $us_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey
            AND exam_procedure.procedure_desc NOT LIKE '%BEDSIDE%'
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='US' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62
            )
            AND total_frames > 0
            UNION ALL 
            SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey
            AND exam_procedure.procedure_desc NOT LIKE '%BEDSIDE%'
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago_5pm . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='US' 
            AND (examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0;"; 
    }
    elseif (($hour >= 23) || ($hour < 8)) {
        $us_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey
            AND exam_procedure.procedure_desc NOT LIKE '%BEDSIDE%'
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='US' 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62 
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37
            OR examination.dept_ckey=39
            )
            AND total_frames > 0;"; 
    }
    else {
        $us_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM 
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND exam_procedure.procedure_desc NOT LIKE '%BEDSIDE%'
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_now ."') 
            AND exam_procedure.modality_code='US' 
            AND (examination.dept_ckey=61 OR examination.dept_ckey=62)
            AND total_frames > 0;";
    }

    $find_us = odbc_exec($connection, $us_query);
    if (!$find_us) {
        echo "Could not run query: $us_query";
        exit;
    }
    while ($row = odbc_fetch_array($find_us)) {
        if (isset($row)) {
            foreach ($row as $a=>$b) {
                $us_array[$count][$a] = $b; 
            }
        $count++;
        }
    }
    odbc_free_result($find_us);
}

function get_oied() {
    global $connection, $oied_array, $globaltime_ago, $globaltime_now, $subtract_now, $subtract_ago, $dow, $hour;
    $count = 0;
    $oied_row = array();
        $oied_query = "SELECT 
            examination.exam_ckey, 
            examination.dept_ckey, 
            examination.procedure_ckey, 
            examination.image_cnt, 
            convert(char(27), study_dttm, 9) as study_dttm, convert(char(27), acq_dttm, 9) as acq_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.modality_code, 
            exam_procedure.procedure_desc, 
            pat_name,
            patient.pat_loc_ckey
            FROM examination, 
            patient, 
            exam_procedure
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey
            AND inv_acq_time BETWEEN $subtract_ago AND $subtract_now
            AND patient.pat_loc_ckey=515
            AND exam_procedure.procedure_desc like '%OUTSIDE%' 
            AND total_frames > 0;"; 

    $find_oied = odbc_exec($connection, $oied_query);

    if (!$find_oied) {
        echo "Could not run query: $oied_query";
        exit;
    }

    //echo "<pre>\n";           //DEBUG
    //echo "$oied_query\n";     //DEBUG

    while ($row = odbc_fetch_array($find_oied)) {
        //  print_r($row); //DEBUG
        foreach ($row as $a=>$b) {
            $oied_array[$count][$a] = $b; 
        }
        $count++;
    }
    odbc_free_result($find_oied);
}

function get_ordered() {
    global $ord_array, $connection, $globaltime_ago, $globaltime_future, $dow, $hour;
    $count = 0;
    $ord_row = array();

    // First, check if we should be displaying other-site studies
    if (($dow == "Saturday") || ($dow == "Sunday")) {
        $ord_query = "SELECT 
            examination.exam_ckey,
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), scheduled_dttm, 9) as scheduled_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.modality_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_future ."') 
            AND exam_procedure.modality_code in ('CT','US') 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37 
            OR examination.dept_ckey=39)
            AND exam_procedure.procedure_code NOT LIKE 'US.NE.V%' 
            AND exam_procedure.procedure_desc NOT LIKE 'US BEDSIDE%'
            AND exam_stat=20;
            ";
    }
    elseif (($hour >= 17) && ($hour <= 22)) {
        $ord_query = "SELECT 
            examination.exam_ckey,
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), scheduled_dttm, 9) as scheduled_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.modality_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_future ."') 
            AND exam_procedure.modality_code in ('CT','US') 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37 
            OR examination.dept_ckey=39)
            AND exam_procedure.procedure_code NOT LIKE 'US.NE.V%' 
            AND exam_procedure.procedure_desc NOT LIKE 'US BEDSIDE%'
            AND exam_stat=20;
            ";  
    }
    elseif (($hour >= 23) || ($hour < 8)) {
        $ord_query = "SELECT 
            examination.exam_ckey,
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), scheduled_dttm, 9) as scheduled_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.modality_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_future ."') 
            AND exam_procedure.modality_code in ('CT','US') 
            AND (examination.dept_ckey=61 
            OR examination.dept_ckey=62
            OR examination.dept_ckey=54
            OR examination.dept_ckey=37 
            OR examination.dept_ckey=39)
            AND exam_procedure.procedure_code NOT LIKE 'US.NE.V%' 
            AND exam_procedure.procedure_desc NOT LIKE 'US BEDSIDE%'
            AND exam_stat=20;
            ";  
    }
    else {
        $ord_query = "SELECT 
            examination.exam_ckey,
            examination.dept_ckey, 
            examination.procedure_ckey, 
            convert(char(27), study_dttm, 9) as study_dttm, 
            convert(char(27), scheduled_dttm, 9) as scheduled_dttm, 
            ris_exam_id, 
            examination.pat_ckey, 
            clinical_cmnt_text, 
            exam_procedure.procedure_code, 
            exam_procedure.modality_code, 
            exam_procedure.procedure_desc, 
            pat_name 
            FROM
            examination, 
            patient, 
            exam_procedure 
            WHERE 
            examination.pat_ckey=patient.pat_ckey 
            AND examination.procedure_ckey=exam_procedure.procedure_ckey 
            AND study_dttm BETWEEN convert(datetime, '" . $globaltime_ago . "') AND convert(datetime, '". $globaltime_future ."') 
            AND exam_procedure.modality_code in ('CT','US') 
            AND (examination.dept_ckey=61 OR examination.dept_ckey=62)
            AND exam_procedure.procedure_code not like 'US.NE.V%' 
            AND exam_procedure.procedure_desc NOT LIKE 'US BEDSIDE%'
            AND exam_stat=20;
            ";  
    }

    $find_ord = odbc_exec($connection, $ord_query);
    
    if (!$find_ord) {
        echo "Could not run query: $ord_query";
        exit;
    }
    while ($ord_row = odbc_fetch_array($find_ord)) {
        foreach ($ord_row as $a=>$b) {
            $ord_array[$count][$a] = $b; 
        }
        $count++;
    }
    odbc_free_result($find_ord);
}

function ct_create_times() {
    global $ct_array;

    foreach ($ct_array as $a=>$b) {
        foreach ($b as $key=>$element) {
            if ($key == "acq_dttm" ) {
                $temp_date = substr($element, 0, 11);
                $temp_time = substr($element, 12, 14);
                $temp_time = preg_replace("/\:\d\d\d/", "", $temp_time);

                // This may end up truncating times with a two-digit hour; check back and manipulate if needed
                $temp2_date = date_create_from_format('M  j Y', $temp_date);
                $temp2_time = date_create_from_format(' h:i:sA', $temp_time); 
                $new_date = date_format($temp2_date, 'Ymd');
                $new_time = date_format($temp2_time, 'His');
                $ct_array[$a]["0008,0020"] = $new_date;
                $ct_array[$a]["0008,0030"] = $new_time;

                //print "date: $temp_date  new date: $new_date time: $temp_time  new time: $new_time\n"; //DEBUG
                //print "\n";       //DEBUG
            }
        }
    }
}

function cr_create_times() {
    global $cr_array;

    foreach ($cr_array as $a=>$b) {
        foreach ($b as $key=>$element) {
            if ($key == "acq_dttm" ) {
                $temp_date = substr($element, 0, 11);
                $temp_time = substr($element, 12, 14);
                $temp_time = preg_replace("/\:\d\d\d/", "", $temp_time);

                // This may end up truncating times with a two-digit hour; check back and manipulate if needed
                $temp2_date = date_create_from_format('M  j Y', $temp_date);
                $temp2_time = date_create_from_format(' h:i:sA', $temp_time); 
                $new_date = date_format($temp2_date, 'Ymd');
                $new_time = date_format($temp2_time, 'His');
                $cr_array[$a]["0008,0020"] = $new_date;
                $cr_array[$a]["0008,0030"] = $new_time;

                //print "date: $temp_date  new date: $new_date time: $temp_time  new time: $new_time\n";
                //print "\n";
            }
        }
    }
}

function us_create_times() {
    global $us_array;

    foreach ($us_array as $a=>$b) {
        foreach ($b as $key=>$element) {
            if ($key == "acq_dttm" ) {
                $temp_date = substr($element, 0, 11);
                $temp_time = substr($element, 12, 14);
                $temp_time = preg_replace("/\:\d\d\d/", "", $temp_time);

                // This may end up truncating times with a two-digit hour; check back and manipulate if needed
                $temp2_date = date_create_from_format('M  j Y', $temp_date);
                $temp2_time = date_create_from_format(' h:i:sA', $temp_time); 
                $new_date = date_format($temp2_date, 'Ymd');
                $new_time = date_format($temp2_time, 'His');
                $us_array[$a]["0008,0020"] = $new_date;
                $us_array[$a]["0008,0030"] = $new_time;
                
                //print "date: $temp_date  new date: $new_date time: $temp_time  new time: $new_time\n";
                //print "\n";
            }
        }
    }
}

function oied_create_times() {
    global $oied_array;

    foreach ($oied_array as $a=>$b) {
        foreach ($b as $key=>$element) {
            if ($key == "acq_dttm" ) {
                $temp_date = substr($element, 0, 11);
                $temp_time = substr($element, 12, 14);
                $temp_time = preg_replace("/\:\d\d\d/", "", $temp_time);
                // This may end up truncating times with a two-digit hour; check back and manipulate if needed
                $temp2_date = date_create_from_format('M  j Y', $temp_date);
                $temp2_time = date_create_from_format(' h:i:sA', $temp_time); 
                $new_date = date_format($temp2_date, 'Ymd');
                $new_time = date_format($temp2_time, 'His');
                $oied_array[$a]["0008,0020"] = $new_date;
                $oied_array[$a]["0008,0030"] = $new_time;
                //print "date: $temp_date  new date: $new_date time: $temp_time  new time: $new_time\n";
                //print "\n";
            }
        }
    }
}

function ord_create_times() {
    global $ord_array;

    foreach ($ord_array as $a=>$b) {
        foreach ($b as $key=>$element) {
            if ($key == "scheduled_dttm" ) {
                $temp_date = substr($element, 0, 11);
                $temp_time = substr($element, 12, 14);
                $temp_time = preg_replace("/\:\d\d\d/", "", $temp_time);
                // This may end up truncating times with a two-digit hour; check back and manipulate if needed
                $temp2_date = date_create_from_format('M  j Y', $temp_date);
                $temp2_time = date_create_from_format(' h:i:sA', $temp_time); 
                $new_date = date_format($temp2_date, 'Ymd');
                $new_time = date_format($temp2_time, 'His');
                $ord_array[$a]["0008,0020"] = $new_date;
                $ord_array[$a]["0008,0030"] = $new_time;
            }
        }
    }
}

function ct_sort_clean() {  
    global $ct_array;

    foreach ($ct_array as $key => $row) {
        $date[$key]  = $row['0008,0020'];
        $time[$key] = $row['0008,0030'];
    }

    array_multisort($date, SORT_DESC, $time, SORT_DESC, $ct_array);
    
    // next we can clean up the dates, times, and study descriptions to be human-readable:
    foreach($ct_array as $key => $value)  {
        foreach ($value as $iKey => $iValue) {

            # This is to put dashes into the dates
            if ((preg_match ("/0008\,0020/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $ct_array[$key][$iKey] = $iValue;
            }

            # This is to put colons into the times
            if ((preg_match ("/0008\,0030/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $ct_array[$key][$iKey] = $iValue;
            }

            if ((preg_match ("/procedure_desc/", $iKey)) > 0) {
                $iValue = preg_replace("/ECT\d+/","",$iValue,1);
                $iValue = preg_replace("/W CONTRAST/","",$iValue,1);
                $iValue = preg_replace("/WO CONTRAST/","",$iValue,1);
                $iValue = substr($iValue, 0, 17);
                $ct_array[$key][$iKey] = $iValue;
            }

            if ((preg_match ("/pat_name/", $iKey)) > 0) {
                $iValue = substr($iValue, 0, 18);
                $ct_array[$key][$iKey] = $iValue;
            
            }

            // We'll set the "radiologist" value of each retrieved study to a null value
            // so that it will come up blank if the study hasn't yet been dictated
            $ct_array[$key]["radiologist"] = " ";
        }
    }
}

function cr_sort_clean() {  
    global $cr_array;
    
    foreach ($cr_array as $key => $row) {
        $date[$key]  = $row['0008,0020'];
        $time[$key] = $row['0008,0030'];
    }

    array_multisort($date, SORT_DESC, $time, SORT_DESC, $cr_array);
    
    // next we can clean up the dates, times, and names to be human-readable:
    foreach($cr_array as $key => $value)  {
        foreach ($value as $iKey => $iValue) {
        
            # This is to put dashes into the dates
            if ((preg_match ("/0008\,0020/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $cr_array[$key][$iKey] = $iValue;
            }

            # This is to put colons into the times
            if ((preg_match ("/0008\,0030/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $cr_array[$key][$iKey] = $iValue;
            }

            if ((preg_match ("/procedure_desc/", $iKey)) > 0) {
                $iValue = preg_replace("/E\d\d\d/","",$iValue,1);
                $iValue = substr($iValue, 0, 17);
                $cr_array[$key][$iKey] = $iValue;
            }

            if ((preg_match ("/pat_name/", $iKey)) > 0) {
                $iValue = substr($iValue, 0, 18);
                $cr_array[$key][$iKey] = $iValue;
            }

            // We'll set the "radiologist" value of each retrieved study to a null value
            // so that it will come up blank if the study hasn't yet been dictated
            $cr_array[$key]["radiologist"] = " ";
        }
    } 
}

function us_sort_clean() {  
    global $us_array;
    
    foreach ($us_array as $key => $row) {
        $date[$key]  = $row['0008,0020'];
        $time[$key] = $row['0008,0030'];
    }

    if (isset($date)) {
        array_multisort($date, SORT_DESC, $time, SORT_DESC, $us_array);
    }
    
    // next we can clean up the dates, times, and names to be human-readable:
    foreach($us_array as $key => $value)  {
        foreach ($value as $iKey => $iValue) {

            # This is to put dashes into the dates
            if ((preg_match ("/0008\,0020/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $us_array[$key][$iKey] = $iValue;
            }

            # This is to put colons into the times
            if ((preg_match ("/0008\,0030/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $us_array[$key][$iKey] = $iValue;
            }
            if ((preg_match ("/procedure_desc/", $iKey)) > 0) {
                $iValue = preg_replace("/E\d\d\d/","",$iValue,1);
                $iValue = substr($iValue, 0, 17);
                $us_array[$key][$iKey] = $iValue;
            }
            if ((preg_match ("/pat_name/", $iKey)) > 0) {
                $iValue = substr($iValue, 0, 18);
                $us_array[$key][$iKey] = $iValue;
            }

            // We'll set the "radiologist" value of each retrieved study to a null value
            // so that it will come up blank if the study hasn't yet been dictated
            $us_array[$key]["radiologist"] = " ";
        }
    } 
}

function oied_sort_clean() {    
    global $oied_array;

    foreach ($oied_array as $key => $row) {
        $date[$key]  = $row['0008,0020'];
        $time[$key] = $row['0008,0030'];
    }

    if (isset($date)) {
        array_multisort($date, SORT_DESC, $time, SORT_DESC, $oied_array);
    }
    
    // next we can clean up the dates, times, and study descriptions to be human-readable:
    foreach($oied_array as $key => $value)  {
        foreach ($value as $iKey => $iValue) {

            # This is to put dashes into the dates
            if ((preg_match ("/0008\,0020/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $oied_array[$key][$iKey] = $iValue;
            }
            # This is to put colons into the times
            if ((preg_match ("/0008\,0030/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $oied_array[$key][$iKey] = $iValue;
            }
            if ((preg_match ("/procedure_desc/", $iKey)) > 0) {
                $iValue = preg_replace("/EUS\d+/","",$iValue,1);
                $oied_array[$key][$iKey] = $iValue;
            }

            // We'll set the "radiologist" value of each retrieved study to a null value
            // so that it will come up blank if the study hasn't yet been dictated
            $oied_array[$key]["radiologist"] = " ";
        }
    }
}

function ordered_sort_clean()  {    
    global $ord_array;

    foreach ($ord_array as $key => $row) {
        $date[$key]  = $row['0008,0020'];
        $time[$key] = $row['0008,0030'];
    }

    if (isset($date)) {
        array_multisort($date, SORT_DESC, $time, SORT_DESC, $ord_array);
    }
    
    // next we can clean up the dates, times, and names to be human-readable:
    foreach($ord_array as $key => $value)  {
        foreach ($value as $iKey => $iValue) {

            # This is to put dashes into the dates
            if ((preg_match ("/0008,0020/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $ord_array[$key][$iKey] = $iValue;
            }
            # This is to put colons into the times
            if ((preg_match ("/0008,0030/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $ord_array[$key][$iKey] = $iValue;
            }
            // And clean up the study type/name requested
            if ((preg_match ("/procedure_desc/", $iKey)) > 0) {
                $iValue = preg_replace("/ECT\d+/","",$iValue,1);
                $iValue = preg_replace("/EUS\d+/","",$iValue,1);
                $ord_array[$key][$iKey] = $iValue;
            }
        }   
    }
}

function ct_get_rad_status()  {
    global $ct_array, $connection; 

    $count = 0;
    $status = "UNREAD";
    $radiologist = "";
    $ct_events=array();

    foreach($ct_array as $key => $value)  {
        if ($ct_array[$key]["ris_exam_id"]) {
            $ris_exam_id = $ct_array[$key]["ris_exam_id"];
            // DANGER: DON'T MESS WITH THE QUERY BELOW. YOU RUN THE RISK OF RETRIEVING A HUGE DATASET
            $ct_event_query = "select event_description, convert(char(27), event_dttm, 9) as event_dttm, ev.user_name, host_name, ev.var1, ev.var2, ev.var3, ev.var4, s.formal_name from event ev, event_lkup el, examination e, staff s where ev.event_type_id=el.event_type_id and ev.user_name=s.staff_id and ev.var2 = convert(varchar, e.exam_ckey) and e.exam_ckey in (select exam_ckey from examination where ris_exam_id='$ris_exam_id') and event_description='ExamStatusChange';";
    
            $find_ct_events = odbc_exec($connection, $ct_event_query);
            while ($ct_event_row = odbc_fetch_array($find_ct_events)) {
                foreach ($ct_event_row as $a=>$b) {
                    $ct_events[$ris_exam_id][$count][$a] = $b; 
                }
                $count++;
            }

            // This next foreach loop looks for the 'highest' status, and breaks out once found
            foreach ($ct_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $ct_event_key=>$ct_event_value) {
                        if ($ct_events[$acces][$array_num]["var3"] == "90") {
                            $status = "FINALIZED";
                            break;
                        }
                        elseif ($ct_events[$acces][$array_num]["var3"] == "70") {
                            $status = "PRELIMINARY";
                            break;
                        }
                        elseif ($ct_events[$acces][$array_num]["var3"] == "60") {
                            $status = "IN REVIEW";
                            break;
                        }
                    }
                }
            }

            // But then we have to go back and find who dictated the study, so we specifically look 
            // for who set the study to 'dictated'
            foreach ($ct_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $ct_event_key=>$ct_event_value) {
                        if ($ct_events[$acces][$array_num]["var3"] == "60") {
                            $radiologist = $ct_events[$acces][$array_num]["formal_name"];
                        }
                    }
                }
            }
        }

        // And just clean up the names and set the variables
        $radiologist = preg_replace("/\, M\.D\./","",$radiologist,1);
        $radiologist = preg_replace("/\, DO/","",$radiologist,1);
        $radiologist = preg_replace("/\, MD/","",$radiologist,1);
        $radiologist = preg_replace("/\, md/","",$radiologist,1);
        $radiologist = preg_replace("/\, m.d./","",$radiologist,1);
        $radiologist = preg_replace("/,/",", ",$radiologist,1);
        $radiologist = substr($radiologist, 0, 18);
        $ct_array[$key]["radiologist"] = "$radiologist";
        $ct_array[$key]["status"] = "$status";
        odbc_free_result($find_ct_events);
        $status = "UNREAD";
        $radiologist = "";
        $ct_events=array();
    }
}

function us_get_rad_status()  {
    global $us_array, $connection; 

    $count = 0;
    $status = "UNREAD";
    $radiologist = "";
    $us_events=array();

    foreach($us_array as $key => $value)  {
        if ($us_array[$key]["ris_exam_id"]) {
            $ris_exam_id = $us_array[$key]["ris_exam_id"];
            // DANGER: DON'T MESS WITH THE QUERY BELOW. YOU RUN THE RISK OF RETRIEVING A HUGE DATASET
            $us_event_query = "SELECT
                event_description, 
                convert(char(27), event_dttm, 9) as event_dttm, 
                ev.user_name, 
                host_name, 
                ev.var1, 
                ev.var2, 
                ev.var3, 
                ev.var4, 
                s.formal_name 
                FROM 
                event ev, 
                event_lkup el,
                examination e, 
                staff s 
                WHERE 
                ev.event_type_id=el.event_type_id 
                AND ev.user_name=s.staff_id 
                AND ev.var2 = convert(varchar, e.exam_ckey) 
                AND e.exam_ckey IN 
                (SELECT exam_ckey FROM examination WHERE ris_exam_id='$ris_exam_id') 
                AND event_description='ExamStatusChange';";
    
                $find_us_events = odbc_exec($connection, $us_event_query);
                while ($us_event_row = odbc_fetch_array($find_us_events)) {
                    foreach ($us_event_row as $a=>$b) {
                        $us_events[$ris_exam_id][$count][$a] = $b; 
                    }
                    $count++;
                }

                // This next foreach loop looks for the 'highest' status, and breaks out once found
                foreach ($us_events as $acces=>$sub_array) {
                    foreach ($sub_array as $array_num=>$subsub_array) {
                        foreach ($subsub_array as $us_event_key=>$us_event_value) {
                            if ($us_events[$acces][$array_num]["var3"] == "90") {
                                $status = "FINALIZED";
                                break;
                            }
                            elseif ($us_events[$acces][$array_num]["var3"] == "70") {
                                $status = "PRELIMINARY";
                                break;
                            }
                            elseif ($us_events[$acces][$array_num]["var3"] == "60") {
                                $status = "IN REVIEW";
                                break;
                            }
                        }
                    }
                }

                // But then we have to go back and find who dictated the study, so we specifically look 
                // for who set the study to 'dictated'
                //print "<pre>\n"; //DEBUG
                //print_r($us_events); //DEBUG
                foreach ($us_events as $acces=>$sub_array) {
                    foreach ($sub_array as $array_num=>$subsub_array) {
                        foreach ($subsub_array as $us_event_key=>$us_event_value) {
                            if ($us_events[$acces][$array_num]["var3"] == "60") {
                                $radiologist = $us_events[$acces][$array_num]["formal_name"];
                            }
                        }
                    }
                }
            }

        // And just clean up the names and set the variables
        $radiologist = preg_replace("/\,\s*M\.D\./","",$radiologist,1);
        $radiologist = preg_replace("/\, MD/","",$radiologist,1);
        $radiologist = preg_replace("/\, DO/","",$radiologist,1);
        $radiologist = preg_replace("/\, md/","",$radiologist,1);
        $radiologist = preg_replace("/\, m.d./","",$radiologist,1);
        $radiologist = preg_replace("/,/",", ",$radiologist,1);
        $radiologist = substr($radiologist, 0, 18);
        $us_array[$key]["radiologist"] = "$radiologist";
        $us_array[$key]["status"] = "$status";
        odbc_free_result($find_us_events);
        $status = "UNREAD";
        $radiologist = "";
        $us_events=array();
    }
}

function cr_get_rad_status()  {
    global $cr_array, $connection; 

    $count = 0;
    $cr_status = "UNREAD";
    $radiologist = "";
    $cr_events=array();

    foreach($cr_array as $key => $value)  {
        if ($cr_array[$key]["ris_exam_id"]) {
            $ris_exam_id = $cr_array[$key]["ris_exam_id"];
            // DANGER: DON'T MESS WITH THE QUERY BELOW. YOU RUN THE RISK OF RETRIEVING A HUGE DATASET
            $cr_event_query = "select event_description, convert(char(27), event_dttm, 9) as event_dttm, ev.user_name, host_name, ev.var1, ev.var2, ev.var3, ev.var4, s.formal_name from event ev, event_lkup el, examination e, staff s where ev.event_type_id=el.event_type_id and ev.user_name=s.staff_id and ev.var2 = convert(varchar, e.exam_ckey) and e.exam_ckey in (select exam_ckey from examination where ris_exam_id='$ris_exam_id') and event_description='ExamStatusChange';";
            
            $find_cr_events = odbc_exec($connection, $cr_event_query);
            while ($cr_event_row = odbc_fetch_array($find_cr_events)) {
                foreach ($cr_event_row as $a=>$b) {
                    $cr_events[$ris_exam_id][$count][$a] = $b; 
                }
                $count++;
            }
    
            // This next foreach loop looks for the 'highest' status, and breaks out once found
            foreach ($cr_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $cr_event_key=>$cr_event_value) {
                        if ($cr_events[$acces][$array_num]["var3"] == "90") {
                            $cr_status = "FINALIZED";
                            break;
                        }
                        elseif ($cr_events[$acces][$array_num]["var3"] == "70") {
                            $cr_status = "PRELIMINARY";
                            break;
                        }
                        elseif ($cr_events[$acces][$array_num]["var3"] == "60") {
                            $cr_status = "IN REVIEW";
                            break;
                        }
                    }
                }
            }

            // But then we have to go back and find who dictated the study, so we specifically look 
            // for who set the study to 'dictated'
            foreach ($cr_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $cr_event_key=>$cr_event_value) {
                        if ($cr_events[$acces][$array_num]["var3"] == "60") {
                        $radiologist = $cr_events[$acces][$array_num]["formal_name"];
                    }
                }
            }
        }
    }
    
    // And just clean up the names and set the variables
    $radiologist = preg_replace("/\, M\.D\./","",$radiologist,1);
    $radiologist = preg_replace("/\, DO/","",$radiologist,1);
    $radiologist = preg_replace("/\, MD/","",$radiologist,1);
    $radiologist = preg_replace("/\, md/","",$radiologist,1);
    $radiologist = preg_replace("/\, m.d./","",$radiologist,1);
    $radiologist = preg_replace("/,/",", ",$radiologist,1);
    $radiologist = substr($radiologist, 0, 18);
    $cr_array[$key]["radiologist"] = "$radiologist";
    $cr_array[$key]["status"] = "$cr_status";
    odbc_free_result($find_cr_events);
    $cr_status = "UNREAD";
    $radiologist = "";
    $cr_events=array();

    }
}

function oied_get_rad_status()  {
    global $oied_array, $connection; 

    $count = 0;
    $status = "UNREAD";
    $radiologist = "";
    $oied_events=array();

    foreach($oied_array as $key => $value)  {
        if ($oied_array[$key]["ris_exam_id"]) {
            $ris_exam_id = $oied_array[$key]["ris_exam_id"];
            // DANGER: DON'T MESS WITH THE QUERY BELOW. YOU RUN THE RISK OF RETRIEVING A HUGE DATASET
            $oied_event_query = "select event_description, convert(char(27), event_dttm, 9) as event_dttm, ev.user_name, host_name, ev.var1, ev.var2, ev.var3, ev.var4, s.formal_name from event ev, event_lkup el, examination e, staff s where ev.event_type_id=el.event_type_id and ev.user_name=s.staff_id and ev.var2 = convert(varchar, e.exam_ckey) and e.exam_ckey in (select exam_ckey from examination where ris_exam_id='$ris_exam_id') and event_description='ExamStatusChange';";
        
            $find_oied_events = odbc_exec($connection, $oied_event_query);
        
            while ($oied_event_row = odbc_fetch_array($find_oied_events)) {
                foreach ($oied_event_row as $a=>$b) {
                    $oied_events[$ris_exam_id][$count][$a] = $b; 
                }
                $count++;
            }

            // This next foreach loop looks for the 'highest' status, and breaks out once found
            foreach ($oied_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $oied_event_key=>$oied_event_value) {
                        if ($oied_events[$acces][$array_num]["var3"] == "90") {
                            $status = "FINALIZED";
                            break;
                        }
                        elseif ($oied_events[$acces][$array_num]["var3"] == "70") {
                            $status = "PRELIMINARY";
                            break;
                        }
                        elseif ($oied_events[$acces][$array_num]["var3"] == "60") {
                            $status = "IN REVIEW";
                            break;
                        }
                    }
                }
            }

            // But then we have to go back and find who dictated the study, so we specifically look 
            // for who set the study to 'dictated'
            foreach ($oied_events as $acces=>$sub_array) {
                foreach ($sub_array as $array_num=>$subsub_array) {
                    foreach ($subsub_array as $oied_event_key=>$oied_event_value) {
                        if ($oied_events[$acces][$array_num]["var3"] == "60") {
                            $radiologist = $oied_events[$acces][$array_num]["formal_name"];
                        }
                    }
                }
            }
        }

        // And just clean up the names and set the variables
        $radiologist = preg_replace("/\, M\.D\./","",$radiologist,1);
        $radiologist = preg_replace("/\, MD/","",$radiologist,1);
        $radiologist = preg_replace("/\, DO/","",$radiologist,1);
        $radiologist = preg_replace("/\, md/","",$radiologist,1);
        $radiologist = preg_replace("/\, m.d./","",$radiologist,1);
        $radiologist = preg_replace("/,/",", ",$radiologist,1);
        $oied_array[$key]["radiologist"] = "$radiologist";
        $oied_array[$key]["status"] = "$status";
        odbc_free_result($find_oied_events);
        $status = "UNREAD";
        $radiologist = "";
        $oied_events=array();   
    }
}

function draw_ct_table()  {
    global $ct_array;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $ct_num_array_elements = count($ct_array);
    if ($ct_num_array_elements < 16)  {
        $max_ct_studies = $ct_num_array_elements;
    }
    else {
        $max_ct_studies = 15;
    }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class=\"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent CT Studies</th></tr>\n";;
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Site</th><th>Radiologist</th><th>Status</th></tr>\n";

    for ($x = 0; $x < $max_ct_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $ct_array[$x]["0008,0030"];
        echo "</td><td>";
        echo $ct_array[$x]["pat_name"];
        echo "</td><td>";
        echo $ct_array[$x]["procedure_desc"];
        echo "</td><td>";
        if ($ct_array[$x]["dept_ckey"] == "61")  {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_1";
        }
        elseif ($ct_array[$x]["dept_ckey"] == "62") {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_2";
        }
        else {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_3";
        }
        echo "</td><td>";
        echo ucwords(strtolower($ct_array[$x]["radiologist"]));
        echo "</td>";
        //<td>";
        if ($ct_array[$x]["status"] == "UNREAD")  {
            echo "<td class=\"red\" align=\"center\">";
        } 
        elseif ($ct_array[$x]["status"] == "IN REVIEW") {
            echo "<td class=\"yellow\" align=\"center\">";
        }
        elseif ($ct_array[$x]["status"] == "PRELIMINARY") {
            echo "<td class=\"lightgreen\" align=\"center\">";
        }
        elseif ($ct_array[$x]["status"] == "FINALIZED")  {
            echo "<td class=\"darkgreen\" align=\"center\">";
        }

        echo $ct_array[$x]["status"];
        echo "</td></tr>\n";
    }
    echo "</table>\n";
}

function draw_us_table()  {
    global $us_array;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $us_num_array_elements = count($us_array);
    if ($us_num_array_elements < 6)  {
        $max_us_studies = $us_num_array_elements;
    }
    else {
        $max_us_studies = 5;
    }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class=\"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent US Studies</th></tr>\n";;
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Site</th><th>Status</th></tr>\n";

    for ($x = 0; $x < $max_us_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $us_array[$x]["0008,0030"];
        echo "</td><td>";
        echo $us_array[$x]["pat_name"];
        echo "</td><td>";
        echo $us_array[$x]["procedure_desc"];
        echo "</td><td>";
        if ($us_array[$x]["dept_ckey"] == "61")  {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_1";
        }
        elseif ($us_array[$x]["dept_ckey"] == "62") {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_2";
        }
        else {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_3";
        }
        echo "</td>";

        if ($us_array[$x]["status"] == "UNREAD")  {
            echo "<td class=\"red\" align=\"center\">";
        } 
        elseif ($us_array[$x]["status"] == "IN REVIEW") {
            echo "<td class=\"yellow\" align=\"center\">";
        }
        elseif ($us_array[$x]["status"] == "PRELIMINARY") {
            echo "<td class=\"lightgreen\" align=\"center\">";
        }
        elseif ($us_array[$x]["status"] == "FINALIZED")  {
            echo "<td class=\"darkgreen\" align=\"center\">";
        }   
        echo $us_array[$x]["status"];
        echo "</td></tr>\n";
    }
    echo "</table>\n";
}

function draw_oied_table()  {
    global $oied_array;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $num_array_elements = count($oied_array);

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent Outside Import Studies</th></tr>\n";;
    echo "<tr><th>Time</th><th>Patient Name</th><th># of Images</th><th>Radiologist</th><th>Status</th></tr>\n";

    for ($x = 0; $x < $num_array_elements; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $oied_array[$x]["0008,0030"];
        echo "</td><td>";
        echo $oied_array[$x]["pat_name"];
        echo "</td><td align=\"center\">";
        echo $oied_array[$x]["image_cnt"];
        echo "</td><td>";
        echo ucwords(strtolower($oied_array[$x]["radiologist"]));
        echo "</td>";
        //<td>";
        if ($oied_array[$x]["status"] == "UNREAD")  {
            echo "<td class=\"red\" align=\"center\">";
        } 
        elseif ($oied_array[$x]["status"] == "IN REVIEW") {
            echo "<td class=\"yellow\" align=\"center\">";
        }
        elseif ($oied_array[$x]["status"] == "PRELIMINARY") {
            echo "<td class=\"lightgreen\" align=\"center\">";
        }
        elseif ($oied_array[$x]["status"] == "FINALIZED")  {
            echo "<td class=\"darkgreen\" align=\"center\">";
        }   
        echo $oied_array[$x]["status"];
        echo "</td></tr>\n";
        }
    echo "</strong></table>\n";
}

function draw_cr_table()  {
    global $cr_array;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $cr_num_array_elements = count($cr_array);
    if ($cr_num_array_elements < 16)  {
        $max_cr_studies = $cr_num_array_elements;
    }
    else {
        $max_cr_studies = 15;
    }
    
    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent X-Ray Studies</th></tr>\n";
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Site</th><th>Radiologist</th><th>Status</th></tr>\n";
    for ($x = 0; $x < $max_cr_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $cr_array[$x]["0008,0030"];
        echo "</td><td>";
        echo $cr_array[$x]["pat_name"];
        echo "</td><td>";
        echo $cr_array[$x]["procedure_desc"];
        echo "</td><td>";
        if ($cr_array[$x]["dept_ckey"] == "61")  {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_1";
        }
        elseif ($cr_array[$x]["dept_ckey"] == "62") {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_2";
        }
        else {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_3";
        }
        echo "</td><td>";
        echo ucwords(strtolower($cr_array[$x]["radiologist"]));
        echo "</td>";
        if ($cr_array[$x]["status"] == "UNREAD")  {
            echo "<td class=\"red\" align=\"center\">";
        } 
        elseif ($cr_array[$x]["status"] == "IN REVIEW") {
            echo "<td class=\"yellow\" align=\"center\">";
        }
        elseif ($cr_array[$x]["status"] == "PRELIMINARY") {
            echo "<td class=\"lightgreen\" align=\"center\">";
        }
        elseif ($cr_array[$x]["status"] == "FINALIZED")  {
            echo "<td class=\"darkgreen\" align=\"center\">";
        }   
        echo $cr_array[$x]["status"];
        echo "</td></tr>\n";
    }
    echo "</table>\n";
}

function draw_ordered_us_table()  {
    global $ord_array;
    $us_count = 0;
    
    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $ord_num_array_elements = count($ord_array);

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Scheduled ER Ultrasound Studies</th></tr>\n";
    echo "<tr><th>Site</th><th>Patient Name</th><th>Study Type</th></tr>\n";

    for ($x = 0; $x < $ord_num_array_elements; $x++)  {
        if($ord_array[$x]["modality_code"] == "US") {
        echo "<tr class=\"d".($us_count & 1)."\" align=\"center\">";
        //echo "<td align=\"center\">";
        //echo $ord_array[$x]["0008,0020"];
        //echo "</td><td align=\"center\">";
        //echo $ord_array[$x]["0008,0030"];
        echo "</td>";
        echo "<td>";
        if ($ord_array[$x]["dept_ckey"] == "61")  {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_1";
        }
        elseif ($ord_array[$x]["dept_ckey"] == "62") {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_2";
        }
        else {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_3";
        }
        echo "</td><td>";
        echo $ord_array[$x]["pat_name"];
        echo "</td><td>";
        echo $ord_array[$x]["procedure_desc"];
        echo "</td></tr>\n";
        $us_count++;
        }
    }
    echo "</table>\n";
}

function draw_ordered_ct_table()  {
    global $ord_array;
    $ct_count = 0;
    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $ord_num_array_elements = count($ord_array);

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"640\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Scheduled ER CT Studies</th></tr>\n";
    echo "<tr><th>Site</th><th>Patient Name</th><th>Study Type</th></tr>\n";

    for ($x = 0; $x < $ord_num_array_elements; $x++)  {
        if($ord_array[$x]["modality_code"] == "CT") {
            echo "<tr class=\"d".($ct_count & 1)."\" align=\"center\">";
            echo "</td>";
            echo "<td>";
        if ($ord_array[$x]["dept_ckey"] == "61")  {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_1";
        }
        elseif ($ord_array[$x]["dept_ckey"] == "62") {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_2";
        }
        else {
            // CUSTOMIZE: Change this to your site ID (e.g. 'MGH', 'TGH', etc.)
            echo "SITE_3"
        }
        echo "</td><td>";
        echo $ord_array[$x]["pat_name"];
        echo "</td><td>";
        echo $ord_array[$x]["procedure_desc"];
        echo "</td></tr>\n";
        $ct_count++;
        }
    }
    echo "</table>\n";
}
?>

</BODY>
</HTML>