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
 * Resolve configured CSV delimiter character.
 *
 * @return string
 */
function block_configurable_reports_get_csv_delimiter_char(): string {
    global $CFG;

    $delimiter = get_config('block_configurable_reports', 'csvdelimiter');
    switch ($delimiter) {
        case 'colon':
            return ':';
        case 'semicolon':
            return ';';
        case 'tab':
            return "\t";
        case 'cfg':
            return $CFG->CSV_DELIMITER ?? ',';
        case 'comma':
        default:
            return ',';
    }
}

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

    $matrix = report_matrix::from_finalreport($report);

    if ($filepath !== null) {
        $delimiter = block_configurable_reports_get_csv_delimiter_char();
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

    require_once($CFG->libdir . '/csvlib.class.php');
    $csvdelimiter = get_config('block_configurable_reports', 'csvdelimiter');
    $filename = format_string($report->name) ?? 'report';
    $csvexport = new csv_export_writer("$csvdelimiter", '"', 'application/download', true);
    $csvexport->set_filename($filename);

    foreach ($matrix as $row) {
        $csvexport->add_data($row);
    }
    $csvexport->download_file();
}
