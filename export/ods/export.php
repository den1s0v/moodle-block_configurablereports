<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use block_configurable_reports\export\report_matrix;

/**
 * Export report
 *
 * @param object $report
 * @return void
 */
function export_report($report) {
    $reportname = format_string($report->name) ?? 'report';
    $filename = $reportname . time() . '.ods';
    export_report_to_path($report, null, clean_filename($filename));
    exit;
}

/**
 * Export report to a file path or browser.
 *
 * @param object $report
 * @param string|null $filepath
 * @param string|null $downloadfilename
 * @return void
 */
function export_report_to_path($report, ?string $filepath, ?string $downloadfilename = null): void {
    global $CFG;
    require_once($CFG->dirroot . '/lib/odslib.class.php');

    $matrix = report_matrix::from_finalreport($report);
    $target = $filepath ?? '-';
    $downloadfilename = $downloadfilename ?? ((format_string($report->name) ?? 'report') . time() . '.ods');

    $workbook = new MoodleODSWorkbook($target);
    if ($filepath === null) {
        $workbook->send($downloadfilename);
    }

    $sheetname = format_string($report->name) ?? 'report';
    $myxls = $workbook->add_worksheet($sheetname);

    foreach ($matrix as $ri => $col) {
        foreach ($col as $ci => $cv) {
            $myxls->write_string($ri, $ci, $cv);
        }
    }

    $workbook->close();
}
