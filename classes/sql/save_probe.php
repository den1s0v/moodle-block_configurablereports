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

namespace block_configurable_reports\sql;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Unified restrictive SQL probe for save-time validation and column metadata.
 *
 * @package   block_configurable_reports
 */
class save_probe {

    /**
     * Run restrictive probe: validate SQL and extract output column names.
     *
     * @param \report_sql $report
     * @param string $rawsql
     * @param callable $explainer function(string $sql): void
     * @return \stdClass valid, error, columns, detected, reason, columns_source
     */
    public static function run(\report_sql $report, string $rawsql, callable $explainer): \stdClass {
        global $remotedb;

        $result = (object) [
            'valid' => false,
            'error' => null,
            'columns' => [],
            'detected' => false,
            'reason' => 'none',
            'columns_source' => 'none',
        ];

        \core_php_time_limit::raise(60);

        try {
            $sql = $report->build_sql_from_config($rawsql, BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_RESTRICTIVE);
            $sql = $report->normalize_sql_prefixes_for_probe($sql);

            if (get_config('block_configurable_reports', 'validate_sql_with_explain')) {
                try {
                    $explainer($sql);
                } catch (\block_configurable_reports\exceptions\explain_unsupported_exception $e) {
                    // Fall through to execute with maxrows 1.
                } catch (\dml_exception $e) {
                    // Fall through to execute with maxrows 1.
                } catch (\Throwable $e) {
                    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
                        throw $e;
                    }
                }
            }

            $columnread = result_column_reader::probe_query_columns($remotedb, $sql, 1);

            $result->columns = $columnread->columns;
            $result->columns_source = $columnread->source;
            $result->detected = !empty($columnread->columns);
            $result->reason = $result->detected ? 'ok' : 'metadata_unavailable';
            $result->valid = true;
        } catch (\dml_read_exception $e) {
            $result->error = get_string('queryfailed', 'block_configurable_reports', $e->error);
            $result->reason = 'error';
        } catch (\moodle_exception $e) {
            $result->error = $e->getMessage();
            $result->reason = 'error';
        }

        return $result;
    }
}
