# PACS_study_display
Web-based tools to display PACS study status

## Introduction

The programs in this repository have been written to query the back-end databases of Radiology Department PACS systems, and display the results on a webpage. The typical use case is to display the cases and their study statuses of a time-sensitive hospital department such as an emergency radiology department, where a large-format display (such as a large screen on a wall in a reading room or nursing station) would be a beneficial visual control system. 

The programs in this repository have been written for two different PACS systems, from different vendors:

* Agfa Impax - the programs in the `agfa` directory have been written and validated on Agfa Impax 6.5 using an Oracle SQL back-end
* GE Centricity RA1000 - the programs in the `ge-centricity` directory have been written and validated on the GE Centricity PACS system using a Sybase SQL back-end

To use these programs, you will need to be familiar with:

* Programming PHP - the programs will all require customization to work at your institution
* PACS - your PACS system, and how it works
* Databases - the SQL database on which your PACS system runs and how to write and use SQL queries

## Requirements

In order to use this software, you will need the following:

* A web-server in your hospital that is running PHP, with the appropriate database drivers installed for the SQL back-end that you are trying to conenct to (e.g. Oracle with the OCI drivers, or Sybase via ODBC).
* The username/password/IP address of your PACS server's SQL database (and access to this user/IP set up in the database) - NB that some vendors do not grant access to this back-end to all institutions.
* Help from your PACS admin team and possibly help from your PACS vendor.

## Programs

### erlist_x.php
These programs have been written to look for studies of several different modality types (or different patient locations) performed in the recent past, and display them on a 'dashboard' so that different users (e.g. radiologists, nurses, ER doctors) can all see and understand what the case's status is (e.g. performed, in review, dictated, or finalized)

### erlist_x.html
This is just an AJAX wrapper that keeps loading the relevant `erlist_x.php` script every minute, whether it previously loaded or not.  Networks/servers can always temporarily go down, and without a wrapper script like this, if the page fails to load once, it would require someone to manually reload it.  By making a simple AJAX wrapper that forces a reload of the script every 60 seconds, if the page didn't load once, it will again try to refresh it in 60 seconds. 

### erlist.css
The CSS field for formatting the display board; it is created with a 'dark theme' for low-light reading rooms, but can easily be customized.

### blank_logo.png
A small black rectangular image that is a placeholder.  It can easily be replaced with an image file to display a department or institution logo. 

### erlist_studycomments.php
This program is just for Agfa Impax.  In Impax, clinicians can leave 'study comments', such as their interpretation of the study (e.g. 'nil acute').  A radiologist can use this page to quickly see which studies have comments left by the clinical team, and dictate these studies first, to prevent patients with incorrect diagnoses leaving the department.

## Warning
Direct access to the SQL back-end of a production PACS system is potentially dangerous; unconstrained PACS queries can 'lock up' a PACS system if executed.  The programs in this repo are provided with no warranty of any kind and if you run them against your PACS system without editing them properly, they may crash your PACS system, with bad consequences for patients in your department. You absolutely need to use these queries in conjunction with your PACS management team and with the express knowledge of your entire radiology department / hospital before using these queries/scripts against a production PACS system. They must be edited prior to use, and should be reviewed by the PACS admin team, and possibly the vendor, and tested on a test system, prior to being put into use on a production PACS system. 
