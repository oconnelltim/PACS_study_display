<HTML>
<link rel="stylesheet" type="text/css" href="erlist.css">
<meta http-equiv="refresh" content="60">
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
$cr_studies = array();

// CUSTOMIZE: The example below is a list of 'patient locations' of 'emergency' patientsfrom Impax;
  // your institution will have different names so change the list below to use the location values
  // relevant for your institution
$er_locations = array('!WAIT', 'AAA', 'AC', 'ACA', 'ACB', 'ACWR', 'AWA', 'BED', 'BLUE', 'DTU', 'DTUE', 'ED', 'EM', 'EMRG', 'EMRGS', 'OBS', 'TR', 'TRWR', 'UED', 'VNT', 'XHD', 'ZD');

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
$institution = "VGH";

// Just for debugging
//ini_set('display_errors', 'On');
//ini_set('html_errors', 'On');

//*************************************************************************************
// Start Main Function here
//*************************************************************************************/
echo "<h2>Emergency/Trauma Radiology Study Comments</h2>\n";
get_studies();

// Third: clean up the data to make it more readable/presentable, and sort the arrays in 
// most recent studies come first
clean_xml();
sort_clean();
create_arrays();

// Finally, present the data as HTML tables
draw_cr_table();
oci_close($conn);

//*************************************************************************************
// Start Subroutines here
//*************************************************************************************/

function get_studies() {
    global $conn, $yesterday, $institution, $all_studies;

    $count = 0;
    $row = array();
    $statement = "SELECT STUDY_DATE, DATE_TIME_VERIFIED, STUDY_TIME, CURRENT_PATIENT_LOCATION, ACCESSION_NUMBER, STUDY_DESCRIPTION, PATIENT_NAME, MODALITY, PHYSICIAN_READING_STUDY, STATUS, STUDY_COMMENTS, STUDY_COMMENTS_UTF8, DATE_TIME_CREATED FROM Dosr_study WHERE STUDY_DATE >= $yesterday AND STUDY_COMMENTS IS NOT NULL AND INSTITUTION_NAME='$institution' AND (MODALITY='CR') ORDER BY DATE_TIME_CREATED DESC";
    
    $stid = oci_parse($conn, $statement);
    if (!$stid) {
        $e = oci_error($conn);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }

    // Perform the logic of the query
    $r = oci_execute($stid);
    if (!$r) {
        $e = oci_error($stid);
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }

    // Fetch the results of the query
    while ($row = oci_fetch_assoc($stid)) {
        foreach ($row as $a=>$b) {
            $all_studies[$count][$a] = $b;

            $real_comments = $row['STUDY_COMMENTS']->load();
            $all_studies[$count]['real_comments'] = $real_comments; //to deal with the CLOB object
            unset($real_comments);
        }
        $count++;
    }
    oci_free_statement($stid);
}

function clean_xml() {
    global $all_studies;

    // Clean up the XML; there are better ways of doing this
    foreach ($all_studies as $key => $value) {
        preg_match("/.+<Text>(.+)<\/Text>.+<Author>(.+)<\/Author>.+<ChangeDate>(.+)<\/ChangeDate>/s", $value['real_comments'], $matches);
        $all_studies[$key]['text'] = $matches[1];
        $all_studies[$key]['author'] = $matches[2];
        $all_studies[$key]['timestamp'] = $matches[3];
        unset($matches);
    }
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

            # Get rid of carets in patient names
            if ((preg_match ("/PATIENT_NAME/", $iKey)) > 0) {
                $iValue = preg_replace("/\^/",",",$iValue,1);
                $iValue = preg_replace("/\^/"," ",$iValue,1);
                $iValue = preg_replace("/\^\^$/","",$iValue,1);
                $iValue = substr($iValue,0,20);
                $all_studies[$key][$iKey] = $iValue;
                }

            # Create a shortened study description name
            if ((preg_match ("/STUDY_DESCRIPTION/", $iKey)) > 0) {
                    $iValue = substr($iValue,0,20);
                    $all_studies[$key][$iKey] = $iValue;
            }
        }
    }
}

function create_arrays()  {
    global $all_studies, $ct_studies, $cr_studies, $us_studies, $mr_studies, $er_locations;

    // This function breaks up the all studies array into modality arrays
    // And gets rid of any non-ER patients
    // First, create the CR array
    foreach ($all_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "MODALITY" && $iValue == "CR") {
                array_push($cr_studies, $value);
            }
        }
    }

    // Get rid of any non-ER studies
    foreach ($cr_studies as $key=>$value)  {
        foreach ($value as $iKey => $iValue) {
            if ($iKey == "CURRENT_PATIENT_LOCATION" && ( ! in_array($iValue, $er_locations))) {
                unset($cr_studies[$key]);
            } 
        }
    }
    
    // Reindex the array
    $cr_studies = array_values(array_filter($cr_studies));
}

function draw_cr_table()  {
    global $cr_studies;

    // next, we count the array to find out how many rows we can generate for
    // our alternating background pattern
        $cr_num_array_elements = count($cr_studies);
        if ($cr_num_array_elements < 21)  {
                $max_cr_studies = $cr_num_array_elements;
        }
        else {
                $max_cr_studies = 20;
        }

    // And finally, generate the table:
    echo "<table align=\"center\" border=\"1\" class=\"comments\">\n";
    echo "\t<tr><th width=\"70px\">Time</th><th width=\"300px\">Patient Name</th><th width=\"250px\">Study Type</th><th>Study Comments</th></tr>\n";
    for ($x = 0; $x < $max_cr_studies; $x++)  {
        echo "\t<tr class=\"d".($x & 1)."\"><td align=\"center\">";
        echo $cr_studies[$x]["STUDY_TIME"];
        echo "</td><td>";
        echo $cr_studies[$x]["PATIENT_NAME"];
        echo "</td><td>";
        echo $cr_studies[$x]["STUDY_DESCRIPTION"];
        echo "</td><td>";
        echo nl2br($cr_studies[$x]["text"]);
        echo "<br>\n";
        echo nl2br($cr_studies[$x]["author"]);
        echo "<br>\n";
        echo str_replace("T", " ", nl2br(substr($cr_studies[$x]["timestamp"], 0, 19)));
        echo "</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
}

?>

</BODY>
</HTML>
