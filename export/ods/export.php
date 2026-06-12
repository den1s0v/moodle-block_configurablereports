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

/**
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

    $matrix = report_matrix::from_finalreport($report);
    $sheetname = format_string($report->name) ?? 'report';
    $sheetname = \core_text::substr($sheetname, 0, 31);

    if ($filepath !== null) {
        require_once($CFG->dirroot . '/lib/odslib.class.php');
        $workbook = new \block_configurable_reports\export\ods_file_workbook('-');
        $myxls = $workbook->add_worksheet($sheetname);
        foreach ($matrix as $ri => $col) {
            foreach ($col as $ci => $cv) {
                $myxls->write_string($ri, $ci, $cv);
            }
        }
        $workbook->save_to_path($filepath);
        return;
    }

    require_once($CFG->dirroot . '/lib/odslib.class.php');
    $downloadfilename = $downloadfilename ?? ((format_string($report->name) ?? 'report') . time() . '.ods');

    $workbook = new MoodleODSWorkbook('-');
    $workbook->send($downloadfilename);
    $myxls = $workbook->add_worksheet($sheetname);

    foreach ($matrix as $ri => $col) {
        foreach ($col as $ci => $cv) {
            $myxls->write_string($ri, $ci, $cv);
        }
    }

    $workbook->close();
}
