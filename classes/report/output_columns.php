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

    /**
     * Read SQL column extraction metadata from report config.
     *
     * @param object $report
     * @return \stdClass
     */
    public static function get_sql_column_metadata(object $report): \stdClass {
        $meta = (object) [
            'columns' => self::get_column_names($report),
            'detected' => false,
            'reason' => '',
            'source' => 'none',
            'updated' => 0,
        ];

        if (($report->type ?? '') !== 'sql') {
            return $meta;
        }

        $components = cr_unserialize($report->components ?? '');
        $config = $components['customsql']['config'] ?? new \stdClass();

        if (!empty($config->outputcolumns_detected)) {
            $meta->detected = true;
        } else if (!empty($meta->columns)) {
            $meta->detected = true;
        }

        $meta->reason = (string) ($config->outputcolumns_reason ?? '');
        $meta->source = (string) ($config->outputcolumns_source ?? 'none');
        $meta->updated = (int) ($config->outputcolumns_updated ?? 0);

        return $meta;
    }

    /**
     * Diagnostic HTML for the custom SQL save page.
     *
     * @param object $report
     * @return string
     */
    public static function format_sql_save_diagnostic(object $report): string {
        $meta = self::get_sql_column_metadata($report);

        $lines = [];
        if ($meta->detected && !empty($meta->columns)) {
            $lines[] = get_string('sqloutputcolumns_status_yes', 'block_configurable_reports');
            $lines[] = get_string('sqloutputcolumns_list', 'block_configurable_reports',
                implode(', ', array_map('s', $meta->columns)));
            if ($meta->source === 'metadata') {
                $lines[] = get_string('sqloutputcolumns_source_metadata', 'block_configurable_reports');
            } else if ($meta->source === 'first_row') {
                $lines[] = get_string('sqloutputcolumns_source_firstrow', 'block_configurable_reports');
            }
        } else {
            $lines[] = get_string('sqloutputcolumns_status_no', 'block_configurable_reports');
            $reasonkey = 'sqloutputcolumns_reason_' . ($meta->reason ?: 'metadata_unavailable');
            if (get_string_manager()->string_exists($reasonkey, 'block_configurable_reports')) {
                $lines[] = get_string($reasonkey, 'block_configurable_reports');
            } else {
                $lines[] = get_string('sqloutputcolumns_reason_metadata_unavailable', 'block_configurable_reports');
            }
        }

        if ($meta->updated > 0) {
            $lines[] = get_string('sqloutputcolumns_updated', 'block_configurable_reports',
                userdate($meta->updated));
        } else {
            $lines[] = get_string('sqloutputcolumns_not_saved_yet', 'block_configurable_reports');
        }

        return \html_writer::alist($lines, null, 'ul');
    }
}
