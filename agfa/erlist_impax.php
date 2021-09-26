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
//*************************************************************************************/
$all_studies = array();
$ct_studies  = array();
$cr_studies  = array();
$us_studies  = array();
$mr_studies  = array();

// CUSTOMIZE: Change the timezone to the appropriate timezone for your region
date_default_timezone_set('America/Los_Angeles');

// CUSTOMIZE: Change the IP address in '<ip-address>' below to be the IP address of the Impax SQL Server
 // Also set the USERNAME/PASSWORD to be whatever the SQL passwords are
$conn = oci_connect('USERNAME', 'PASSWORD', '<ip-address>/MVF');
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

$yesterday = date("Ymd", time()-86400);
$today = date("Ymd");

// CUSTOMIZE: Change the the '<INSTITUTION_x>' below to be the institution code for your institution in Impax
 // e.g. 'MGH', 'TGH', etc
$institution_1   = "<INSTITUTION_1>";
$institution_2   = "<INSTITUTION_2>";

// CUSTOMIZE: Change the '<PATIENT LOCATION x>' below to be the patient location codes you want to query in Impax, e.g. 'EMERG'
$pat_loc_1      = "<PATIENT LOCATION 1>";
$pat_loc_1      = "<PATIENT LOCATION 2>";

$stat_in_cr_count = "";
$stat_in_ct_count = "";

// Just for debugging
//ini_set('display_errors', 'On');
//ini_set('html_errors', 'On');

//*************************************************************************************
// Start Main Function here
//*************************************************************************************

// CUSTOMIZE: Change 'blank_logo.png' to be the institution logos (if you want them) to display on your display
echo "<table align=\"center\" class=\"title\" border=\"0\"><tr><td width=\"200\"><img class=\"float_left\" height=\"42\" width=\"139\" src=\"blank_logo.png\"></td><td class=\"heading\">Emergency/Trauma Radiology</td><td width=\"200\"><img class=\"float_right\" height=\"42\" width=\"132\" src=\"blank_logo.png\"></td></tr></table>";

//echo "<pre>\n";           //DEBUG
//echo "\n";                //DEBUG

// The first step is to query Impax to find all the studies done today. 
get_studies();
get_stat_count();

// Third: clean up the data to make it more readable/presentable, and sort the arrays in 
// most recent studies come first
sort_clean();
//print "<pre>\n";          //DEBUG
//print_r($all_studies);    //DEBUG

create_arrays();
//print_r($ct_studies);     //DEBUG

// Finally, present the data as HTML tables
print "<table align=\"center\" border=\"0\" width=\"1900\">\n";
print "<tr><td valign=top><br>";
draw_ct_table();
print "<br>\n";
draw_us_table();
print "</td><td valign=top>";
draw_cr_table();
print "<br>\n";
draw_mr_table();
print "</td></tr>";
print "<tr><td colspan=2>";
print "</td></tr>\n";
print "</table>\n";

// CUSTOMIZE: Change 'blank_logo.png' below if you want to display another logo
print "<table align=\"center\" class=\"title\" border=\"0\"><tr><td><h2>Unread STAT Inpatient CR Studies: $stat_in_cr_count</h2></td><td><img class=\"float_left\" height=\"42\" width=\"139\" src=\"blank_logo.png\"></td><td><h2>Unread STAT Inpatient CT Studies: $stat_in_ct_count</h2></td></tr></table>\n";
oci_close($conn);

//print "<pre>\n";          //DEBUG
//print_r($cr_studies);     //DEBUG

//*************************************************************************************
// Start Subroutines here
//*************************************************************************************/

function get_studies() {
    global $conn, $yesterday, $institution_1, $institution_2, $all_studies, $pat_loc_1, $pat_loc_2;
    $count = 0;
    $row = array();
    $statement = "SELECT STUDY_DATE, DATE_TIME_VERIFIED, STUDY_TIME, CURRENT_PATIENT_LOCATION, ACCESSION_NUMBER, STUDY_DESCRIPTION, PATIENT_NAME, MODALITY, PHYSICIAN_READING_STUDY, STATUS, DATE_TIME_CREATED, INSTITUTION_NAME FROM Dosr_study WHERE STUDY_DATE >= $yesterday AND (INSTITUTION_NAME='$institution_1' OR INSTITUTION_NAME='$institution_2') AND (STUDY_PATIENT_LOCATION='$pat_loc_1' OR STUDY_PATIENT_LOCATION='$pat_loc_2') ORDER BY DATE_TIME_CREATED DESC";
    $stid = oci_parse($conn, $statement);
    if (!$stid) {
        $e = oci_error($conn);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }

//  echo "$statement\n";        //DEBUG

    // Perform the logic of the query
    $r = oci_execute($stid);
    if (!$r) {
        $e = oci_error($stid);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }

    // Fetch the results of the query
    while ($row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS)) {
        foreach ($row as $a=>$b) {
        $all_studies[$count][$a] = $b;
        }
        $count++;
    }
    oci_free_statement($stid);
}

function get_stat_count() {
    // This function is for where the ER radiologists also have to read the STAT inpatient cases.
      // It retrieves the # of STAT inpatient case counts

    global $yesterday, $conn, $institution_1, $stat_in_cr_count, $stat_in_ct_count;
    $statement = "SELECT COUNT(*) AS NUM_ROWS FROM Dosr_study WHERE STUDY_DATE >= $yesterday AND INSTITUTION_NAME='$institution_1' AND MODALITY='CR' and STUDY_PATIENT_LOCATION='IN' AND STUDY_PRIORITY_ID='HIGH' AND STATUS='N'";
    $stid = oci_parse($conn, $statement);
    if (!$stid) {
        $e = oci_error($conn);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }
    oci_define_by_name($stid, 'NUM_ROWS', $stat_in_cr_count);
        oci_execute($stid);
        oci_fetch($stid);
        oci_free_statement($stid);

    $statement = "SELECT COUNT(*) AS NUM_ROWS FROM Dosr_study WHERE STUDY_DATE >= $yesterday AND INSTITUTION_NAME='$institution_1' AND MODALITY='CT' and STUDY_PATIENT_LOCATION='IN' AND STUDY_PRIORITY_ID='HIGH' AND STATUS='N'";
    $stid2 = oci_parse($conn, $statement);
    if (!$stid2) {
        $e = oci_error($conn);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }
    oci_define_by_name($stid2, 'NUM_ROWS', $stat_in_ct_count);
        oci_execute($stid2);
        oci_fetch($stid2);
        oci_free_statement($stid2);
}

function sort_clean() { 
    global $all_studies;

    // next we can clean up the dates, times, and study descriptions to be human-readable:
    foreach($all_studies as $key => $value)  {
        foreach ($value as $iKey => $iValue) {
            # This is to put dashes into the dates
            if ((preg_match ("/STUDY_DATE/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, '-', 4, 0);
                $iValue = substr_replace($iValue, '-', 7, 0);
                $all_studies[$key][$iKey] = $iValue;
                }
            # This is to put colons into the times
            if ((preg_match ("/STUDY_TIME/", $iKey)) > 0) {
                $iValue = substr_replace($iValue, ':', 2, 0);
                $iValue = substr_replace($iValue, ' ', 5);
                $all_studies[$key][$iKey] = $iValue;
                }
            if ((preg_match ("/PATIENT_NAME/", $iKey)) > 0) {
                $iValue = preg_replace("/\^/",",",$iValue,1);
                $iValue = preg_replace("/\^/"," ",$iValue,1);
                $iValue = preg_replace("/\^\^$/","",$iValue,1);
                $iValue = substr($iValue,0,17);
                $all_studies[$key][$iKey] = $iValue;
                }
            if ((preg_match ("/STUDY_DESCRIPTION/", $iKey)) > 0) {
                $iValue = substr($iValue,0,17);
                $all_studies[$key][$iKey] = $iValue;
                }
            if ((preg_match ("/PHYSICIAN_READING_STUDY/", $iKey)) > 0) {
                $iValue = substr($iValue,0,17);
                $all_studies[$key][$iKey] = $iValue;
                }
        }
    }
}

function create_arrays()  {
    global $all_studies, $ct_studies, $cr_studies, $us_studies, $mr_studies;

    // This function breaks up the all studies array into modality arrays
    // First, create the CT array
    foreach ($all_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "MODALITY" && $iValue == "CT") {
                array_push($ct_studies, $value);
            }
        }
    }
    // Then the CR array
    foreach ($all_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "MODALITY" && $iValue == "CR") {
                array_push($cr_studies, $value);
            }
        }
    }

    // Then the US array
    foreach ($all_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "MODALITY" && $iValue == "US") {
                array_push($us_studies, $value);
            }
        }
    }

    // Then the MR array
    foreach ($all_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "MODALITY" && $iValue == "MR") {
                array_push($mr_studies, $value);
            }
        }
    }
}


function draw_ct_table()  {
    global $ct_studies;
    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern

    $ct_num_array_elements = count($ct_studies);
    if ($ct_num_array_elements < 21)  {
            $max_ct_studies = $ct_num_array_elements;
    }
    else {
            $max_ct_studies = 20;
    }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class=\"ctlist2\" width=\"898\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent ER CT Studies</th></tr>\n";;
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Site</th><th>Radiologist</th><th>Status</th></tr>\n";

//  Some suspected status codes:
//  'd' - dictation started
//  'N' - claimed
//  'R' - finalized
    for ($x = 0; $x < $max_ct_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $ct_studies[$x]["STUDY_TIME"];
        echo "</td><td>";
        echo $ct_studies[$x]["PATIENT_NAME"];
        echo "</td><td>";
        echo $ct_studies[$x]["STUDY_DESCRIPTION"];
        echo "</td><td align=\"center\">";
        echo $ct_studies[$x]["INSTITUTION_NAME"];
        echo "</td><td>";
        echo $ct_studies[$x]["PHYSICIAN_READING_STUDY"];
        echo "</td>";
        //<td>";
        if (($ct_studies[$x]["STATUS"] == "N") && ($ct_studies[$x]["PHYSICIAN_READING_STUDY"] == ""))  {
            echo "<td class=\"red\" align=\"center\">UNREAD</td></tr>\n";
        } 
        elseif (($ct_studies[$x]["STATUS"] == "N") || ($ct_studies[$x]["STATUS"] == "d")) {
            echo "<td class=\"yellow\" align=\"center\">IN REVIEW</td></tr>\n";
        }
        elseif ($ct_studies[$x]["STATUS"] == "D") {
            echo "<td class=\"lightgreen\" align=\"center\">DICTATED</td></tr>\n";
        }
        elseif ($ct_studies[$x]["STATUS"] == "R")  {
            echo "<td class=\"darkgreen\" align=\"center\">FINALIZED</td></tr>\n";
        }
    }
    echo "</table><br>\n";
}

function draw_cr_table()  {
    global $cr_studies;
    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
//  $cr_num_array_elements = count($cr_array);
        $cr_num_array_elements = count($cr_studies);
        if ($cr_num_array_elements < 21)  {
                $max_cr_studies = $cr_num_array_elements;
        }
        else {
                $max_cr_studies = 20;
        }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"898\">\n";
    echo "<tr class=\"heading\"><th colspan=6>Recent ER X-Ray Studies</th></tr>\n";
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Site</th><th>Radiologist</th><th>Status</th></tr><br><strong>\n";
    for ($x = 0; $x < $max_cr_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $cr_studies[$x]["STUDY_TIME"];
        echo "</td><td>";
        echo $cr_studies[$x]["PATIENT_NAME"];
        echo "</td><td>";
        echo $cr_studies[$x]["STUDY_DESCRIPTION"];
        echo "</td><td align=\"center\">";
        echo $cr_studies[$x]["INSTITUTION_NAME"];
        echo "</td><td>";
        echo $cr_studies[$x]["PHYSICIAN_READING_STUDY"];
        echo "</td>";
        //<td>";
        if (($cr_studies[$x]["STATUS"] == "N") && ($cr_studies[$x]["PHYSICIAN_READING_STUDY"] == ""))  {
            echo "<td class=\"red\" align=\"center\">UNREAD</td></tr>\n";
        } 
        elseif (($cr_studies[$x]["STATUS"] == "N") || ($cr_studies[$x]["STATUS"] == "d")) {
            echo "<td class=\"yellow\" align=\"center\">IN REVIEW</td></tr>\n";
        }
        elseif ($cr_studies[$x]["STATUS"] == "D") {
            echo "<td class=\"lightgreen\" align=\"center\">DICTATED</td></tr>\n";
        }
        elseif ($cr_studies[$x]["STATUS"] == "R")  {
            echo "<td class=\"darkgreen\" align=\"center\">FINALIZED</td></tr>\n";
        }
    }
    echo "</strong></table><br>\n";
}

function draw_us_table()  {
    global $us_studies;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
        $us_num_array_elements = count($us_studies);
        if ($us_num_array_elements < 6)  {
                $max_us_studies = $us_num_array_elements;
        }
        else {
                $max_us_studies = 5;
        }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"898\">\n";
    echo "<tr class=\"heading\"><th colspan=5>Recent ER Ultrasound Studies</th></tr>\n";
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Radiologist</th><th>Status</th></tr><br><strong>\n";
    for ($x = 0; $x < $max_us_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $us_studies[$x]["STUDY_TIME"];
        echo "</td><td>";
        echo $us_studies[$x]["PATIENT_NAME"];
        echo "</td><td>";
        echo $us_studies[$x]["STUDY_DESCRIPTION"];
        echo "</td><td>";
        echo $us_studies[$x]["PHYSICIAN_READING_STUDY"];
        echo "</td>";
        //<td>";
        if (($us_studies[$x]["STATUS"] == "N") && ($us_studies[$x]["PHYSICIAN_READING_STUDY"] == ""))  {
            echo "<td class=\"red\" align=\"center\">UNREAD</td></tr>\n";
        } 
        elseif (($us_studies[$x]["STATUS"] == "N") || ($us_studies[$x]["STATUS"] == "d"))  {
            echo "<td class=\"yellow\" align=\"center\">IN REVIEW</td></tr>\n";
        }
        elseif ($us_studies[$x]["STATUS"] == "D") {
            echo "<td class=\"lightgreen\" align=\"center\">DICTATED</td></tr>\n";
        }
        elseif ($us_studies[$x]["STATUS"] == "R")  {
            echo "<td class=\"darkgreen\" align=\"center\">FINALIZED</td></tr>\n";
        }
    }
    echo "</strong></table><br>\n";
}

function draw_mr_table()  {
    global $mr_studies;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
    $mr_num_array_elements = count($mr_studies);
    if ($mr_num_array_elements < 6)  {
        $max_mr_studies = $mr_num_array_elements;
    }
    else {
        $max_mr_studies = 5;
    }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class= \"ctlist2\" width=\"898\">\n";
    echo "<tr class=\"heading\"><th colspan=5>Recent ER MRI Studies</th></tr>\n";
    echo "<tr><th>Time</th><th>Patient Name</th><th width=\"130\">Study Type</th><th>Radiologist</th><th>Status</th></tr><br><strong>\n";
    for ($x = 0; $x < $max_mr_studies; $x++)  {
        echo "<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $mr_studies[$x]["STUDY_TIME"];
        echo "</td><td>";
        echo $mr_studies[$x]["PATIENT_NAME"];
        echo "</td><td>";
        echo $mr_studies[$x]["STUDY_DESCRIPTION"];
        echo "</td><td>";
        echo $mr_studies[$x]["PHYSICIAN_READING_STUDY"];
        echo "</td>";
        //<td>";
        if (($mr_studies[$x]["STATUS"] == "N") && ($mr_studies[$x]["PHYSICIAN_READING_STUDY"] == ""))  {
            echo "<td class=\"red\" align=\"center\">UNREAD</td></tr>\n";
        } 
        elseif (($mr_studies[$x]["STATUS"] == "N") || ($mr_studies[$x]["STATUS"] == "d")) {
            echo "<td class=\"yellow\" align=\"center\">IN REVIEW</td></tr>\n";
        }
        elseif ($mr_studies[$x]["STATUS"] == "D") {
            echo "<td class=\"lightgreen\" align=\"center\">DICTATED</td></tr>\n";
        }
        elseif ($mr_studies[$x]["STATUS"] == "R")  {
            echo "<td class=\"darkgreen\" align=\"center\">FINALIZED</td></tr>\n";
        }
    }
    echo "</strong></table><br>\n";
}
?>

</BODY>
</HTML>
