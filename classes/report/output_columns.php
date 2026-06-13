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

namespace block_configurable_reports\report;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Cached SQL report output column metadata.
 *
 * @package   block_configurable_reports
 */
class output_columns {

    /**
     * Column names stored when custom SQL was last saved.
     *
     * @param object $report Report record from block_configurable_reports.
     * @return array<int, string>
     */
    public static function get_column_names(object $report): array {
        if (($report->type ?? '') !== 'sql') {
            return [];
        }

        $components = cr_unserialize($report->components ?? '');
        $config = $components['customsql']['config'] ?? new \stdClass();
        $columns = $config->outputcolumns ?? [];

        if (!is_array($columns)) {
            return [];
        }

        $names = [];
        foreach ($columns as $column) {
            $name = trim((string) $column);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Whether the report has cached output column metadata.
     *
     * @param object $report
     * @return bool
     */
    public static function has_metadata(object $report): bool {
        return !empty(self::get_column_names($report));
    }

    /**
     * Select options for moodleform (value => label).
     *
     * @param object $report
     * @return array<string, string>
     */
    public static function get_select_options(object $report): array {
        $options = [];
        foreach (self::get_column_names($report) as $name) {
            $options[$name] = $name;
        }
        return $options;
    }

    /**
     * Whether a column name is present in cached metadata.
     *
     * @param object $report
     * @param string $name
     * @return bool
     */
    public static function is_known_column(object $report, string $name): bool {
        return in_array(trim($name), self::get_column_names($report), true);
    }
}
