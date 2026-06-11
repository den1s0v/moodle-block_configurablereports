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
 * Configurable Reports
 * A Moodle block for creating customizable reports
 *
 * @package blocks
 * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @date: 2009
 */

use block_configurable_reports\export\report_matrix;

/**
 * Export report
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
    require_once($CFG->libdir . '/moodlelib.php');

    $content = slk_build_content($report);
    $filename = clean_filename(($report->name ?? 'report') . '-' . gmdate('Ymd_Hi') . '.slk');

    if ($filepath !== null) {
        file_put_contents($filepath, $content);
        return;
    }

    if (strpos($CFG->wwwroot, 'https://') === 0) {
        header('Cache-Control: max-age=10');
        header('Pragma: ');
    } else {
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Pragma: no-cache');
    }
    header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
    header('Content-Type: application/download; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $content;
}

/**
 * Build SLK content for a report.
 *
 * @param object $report
 * @return string
 */
function slk_build_content($report): string {
    $table = $report->table;
    $lines = ["ID;P\n"];
    $rownum = 1;

    if (!empty($table->head)) {
        $lines[] = slk_format_row($table->head, $rownum++);
    }

    foreach ($table->data ?? [] as $row) {
        $lines[] = slk_format_row($row, $rownum++);
    }

    $lines[] = "E\n";
    return implode('', $lines);
}

/**
 * Format one SLK row.
 *
 * @param array $data
 * @param int $row
 * @return string
 */
function slk_format_row(array $data, int $row): string {
    $col = 1;
    $output = '';
    foreach ($data as $datum) {
        $datum = report_matrix::cell_to_plain_text($datum);
        $datum = str_replace('"', "'", $datum);
        if (preg_match('!!u', $datum)) {
            $datum = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $datum);
        }
        $output .= "C;Y{$row};X{$col};K\"{$datum}\"\n";
        $col++;
    }
    return $output;
}
