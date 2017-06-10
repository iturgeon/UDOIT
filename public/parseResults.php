<?php
/**
*	Copyright (C) 2014 University of Central Florida, created by Jacob Bates, Eric Colon, Fenel Joseph, and Emily Sachs.
*
*	This program is free software: you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation, either version 3 of the License, or
*	(at your option) any later version.
*
*	This program is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*	GNU General Public License for more details.
*
*	You should have received a copy of the GNU General Public License
*	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*	Primary Author Contact:  Jacob Bates <jacob.bates@ucf.edu>
*/
require_once('../config/settings.php');

$get_input = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

if ( ! isset($get_input['path'])) $get_input['path'] = '';

// Load the test report in test/dev mode and no other report is selected
if ($UDOIT_ENV !== ENV_PROD && !isset($get_input['report_id'])) {
	$get_input['report_id'] = 'TEST'; // TEST is the id of the test report
}

// @TODO: make sure this user's got a session
if ( ! isset($get_input['report_id'])) {
	UdoitUtils::exitWithPartialError('No report requested');
}

$sth = UdoitDB::prepare("SELECT * FROM {$db_reports_table} WHERE id = :report_id");
$sth->bindValue(':report_id', $get_input['report_id'], PDO::PARAM_INT);

if ( ! $sth->execute()) {
	error_log(print_r($sth->errorInfo(), true));
	UdoitUtils::exitWithPartialError('Error searching for report');
}

$report_json = $sth->fetch(PDO::FETCH_OBJ)->report_json;

$report = json_decode($report_json);

if (is_null($report)) {
	$json_error = json_last_error_msg();
	UdoitUtils::exitWithPartialError("Cannot parse this report. JSON error $json_error.");
}

$results = [
	'course'              => $report->course,
	'error_count'         => $report->total_results->errors,
	'suggestion_count'    => $report->total_results->suggestions,
	'report_groups'       => $report->content,
	'post_path'           => $get_input['path'],
	'fixable_error_types' => ["cssTextHasContrast", "imgNonDecorativeHasAlt", "tableDataShouldHaveTh", "tableThShouldHaveScope", "headersHaveText", "aMustContainText", "imgAltIsDifferent", "imgAltIsTooLong"],
	'fixable_suggestions' => ["aSuspiciousLinkText", "imgHasAlt", "aLinkTextDoesNotBeginWithRedundantWord", "cssTextStyleEmphasize"]

];

$templates  = new League\Plates\Engine('../templates');
echo($templates->render('partials/results', $results));
