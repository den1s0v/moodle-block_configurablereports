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
 * export_report
 *
 * @param object $report
 * @return void
 */
function export_report($report) {
    export_report_to_path($report, null);
    exit;
}

/**
 * Export report to a file path or browser.
 *
 * @param object $report
 * @param string|null $filepath
 * @return void
 */
function export_report_to_path($report, ?string $filepath): void {
    global $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');

    $matrix = report_matrix::from_finalreport($report);
    $filename = format_string($report->name) ?? 'report';
    $csvdelimiter = get_config('block_configurable_reports', 'csvdelimiter');
    $csvexport = new csv_export_writer("$csvdelimiter", '"', 'application/download', true);
    $csvexport->set_filename($filename);
    $delimiter = $csvexport->delimiter ?? ',';

    if ($filepath !== null) {
        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            throw new moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }
        foreach ($matrix as $row) {
            fputcsv($handle, $row, $delimiter, '"');
        }
        fclose($handle);
        return;
    }

    foreach ($matrix as $row) {
        $csvexport->add_data($row);
    }
    $csvexport->download_file();
}
