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

namespace block_configurable_reports\export;

defined('MOODLE_INTERNAL') || die();

/**
 * Build export matrix from report table data.
 *
 * @package   block_configurable_reports
 */
class report_matrix {

    /**
     * Convert a final report table to a 2D matrix of plain text cell values.
     *
     * @param object $finalreport Report finalreport object.
     * @return array<int, array<int, string>>
     */
    public static function from_finalreport(object $finalreport): array {
        $table = $finalreport->table;
        $matrix = [];

        if (!empty($table->head)) {
            foreach ($table->head as $key => $heading) {
                $matrix[0][$key] = self::cell_to_plain_text($heading);
            }
        }

        if (!empty($table->data)) {
            foreach ($table->data as $rkey => $row) {
                foreach ($row as $key => $item) {
                    $matrix[$rkey + 1][$key] = self::cell_to_plain_text($item);
                }
            }
        }

        return $matrix;
    }

    /**
     * Strip HTML and normalise a table cell for export.
     *
     * @param mixed $value
     * @return string
     */
    public static function cell_to_plain_text($value): string {
        return str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br(format_string((string) $value)))));
    }
}
