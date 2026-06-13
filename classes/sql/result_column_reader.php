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

require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');

/**
 * Read SQL result column names from recordset metadata or first row.
 *
 * @package   block_configurable_reports
 */
class result_column_reader {

    /**
     * Read column names, preferring result-set metadata over first row.
     *
     * @param mixed $recordset Moodle recordset or false.
     * @param \moodle_database $db
     * @return \stdClass columns, source (metadata|first_row|none)
     */
    public static function read_with_source($recordset, \moodle_database $db): \stdClass {
        if (empty($recordset)) {
            return (object) [
                'columns' => [],
                'source' => 'none',
            ];
        }

        $metadata = self::read_metadata_columns($recordset, $db);
        if (!empty($metadata)) {
            return (object) [
                'columns' => $metadata,
                'source' => 'metadata',
            ];
        }

        foreach ($recordset as $row) {
            return (object) [
                'columns' => \report_sql::column_names_from_record($row),
                'source' => 'first_row',
            ];
        }

        return (object) [
            'columns' => [],
            'source' => 'none',
        ];
    }

    /**
     * Read column names from native result metadata.
     *
     * @param mixed $recordset
     * @param \moodle_database $db
     * @return array<int, string>
     */
    public static function read_metadata_columns($recordset, \moodle_database $db): array {
        if (!is_object($recordset)) {
            return [];
        }

        $native = self::get_native_result($recordset);
        if ($native === null) {
            return [];
        }

        switch ($db->get_dbfamily()) {
            case 'mysql':
            case 'mariadb':
                return self::read_mysqli_columns($native);
            case 'postgres':
            case 'pgsql':
                return self::read_pgsql_columns($native);
            case 'sqlite':
                return self::read_sqlite_columns($native);
            default:
                return [];
        }
    }

    /**
     * Extract native driver result handle from a Moodle recordset.
     *
     * @param object $recordset
     * @return mixed|null
     */
    protected static function get_native_result(object $recordset) {
        foreach (['result', 'sqlite3result', 'statement'] as $property) {
            if (!property_exists($recordset, $property)) {
                continue;
            }
            $reflection = new \ReflectionProperty($recordset, $property);
            $reflection->setAccessible(true);
            $value = $reflection->getValue($recordset);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param mixed $result mysqli_result
     * @return array<int, string>
     */
    protected static function read_mysqli_columns($result): array {
        if (!is_object($result) || !method_exists($result, 'fetch_fields')) {
            if (function_exists('mysqli_fetch_fields') && $result instanceof \mysqli_result) {
                $fields = mysqli_fetch_fields($result);
            } else {
                return [];
            }
        } else {
            $fields = $result->fetch_fields();
        }

        if (empty($fields)) {
            return [];
        }

        $names = [];
        foreach ($fields as $field) {
            if (!empty($field->name)) {
                $names[] = (string) $field->name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * @param mixed $result PostgreSQL result resource/object
     * @return array<int, string>
     */
    protected static function read_pgsql_columns($result): array {
        if (!function_exists('pg_num_fields') || !function_exists('pg_field_name')) {
            return [];
        }

        $count = @pg_num_fields($result);
        if (!$count) {
            return [];
        }

        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $name = pg_field_name($result, $i);
            if ($name !== false && $name !== '') {
                $names[] = (string) $name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * @param mixed $result sqlite3_result or PDOStatement
     * @return array<int, string>
     */
    protected static function read_sqlite_columns($result): array {
        if ($result instanceof \SQLite3Result) {
            $names = [];
            for ($i = 0, $count = $result->numColumns(); $i < $count; $i++) {
                $name = $result->columnName($i);
                if ($name !== false && $name !== '') {
                    $names[] = (string) $name;
                }
            }
            return array_values(array_unique($names));
        }

        if ($result instanceof \PDOStatement) {
            $count = $result->columnCount();
            $names = [];
            for ($i = 0; $i < $count; $i++) {
                $meta = $result->getColumnMeta($i);
                if (!empty($meta['name'])) {
                    $names[] = (string) $meta['name'];
                }
            }
            return array_values(array_unique($names));
        }

        return [];
    }
}
