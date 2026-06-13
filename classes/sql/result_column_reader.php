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
 * Read SQL result column names from native driver metadata or first row.
 *
 * Moodle recordsets fetch (and close) the native result in their constructor when
 * no rows are returned, so metadata must be read before the recordset is created.
 *
 * @package   block_configurable_reports
 */
class result_column_reader {

    /**
     * Execute probe SQL once, validate it runs, and return column names.
     *
     * @param \moodle_database $db
     * @param string $sql Normalized SQL without bound parameters.
     * @param int $maxrows Maximum rows to fetch for first-row fallback.
     * @return \stdClass columns, source (metadata|first_row|none)
     * @throws \dml_exception
     */
    public static function probe_query_columns(\moodle_database $db, string $sql, int $maxrows = 1): \stdClass {
        $native = self::probe_native_columns($db, $sql, $maxrows);
        if ($native !== null) {
            return $native;
        }

        $rs = $db->get_recordset_sql($sql, null, 0, $maxrows);
        $read = self::read_with_source($rs, $db);
        if ($rs) {
            $rs->close();
        }

        return (object) [
            'columns' => $read->columns,
            'source' => $read->source,
        ];
    }

    /**
     * Read column names from an existing recordset (first-row fallback only).
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
     * Driver-native probe: one query, metadata before recordset wrapper.
     *
     * @param \moodle_database $db
     * @param string $sql
     * @param int $maxrows
     * @return \stdClass|null
     */
    protected static function probe_native_columns(\moodle_database $db, string $sql, int $maxrows): ?\stdClass {
        $family = $db->get_dbfamily();
        if (in_array($family, ['mysql', 'mysqli', 'mariadb'], true)) {
            return self::probe_mysqli_columns($db, $sql, $maxrows);
        }
        if (in_array($family, ['postgres', 'pgsql'], true)) {
            return self::probe_pgsql_columns($db, $sql, $maxrows);
        }
        if ($family === 'sqlite') {
            return self::probe_sqlite_columns($db, $sql, $maxrows);
        }
        return null;
    }

    /**
     * @param \moodle_database $db
     * @param string $sql
     * @param int $maxrows
     * @return \stdClass|null
     */
    protected static function probe_mysqli_columns(\moodle_database $db, string $sql, int $maxrows): ?\stdClass {
        $mysqli = self::get_reflected_property($db, 'mysqli');
        if (!$mysqli instanceof \mysqli) {
            return null;
        }

        $probesql = self::append_limit_clause($db, $sql, $maxrows);
        self::invoke_db_query_start($db, $probesql);

        $driverresult = $mysqli->query($probesql, MYSQLI_STORE_RESULT);
        self::invoke_db_query_end($db, $driverresult);

        if ($driverresult === false) {
            return (object) [
                'columns' => [],
                'source' => 'none',
            ];
        }

        return self::finalize_native_result($driverresult, 'mysqli');
    }

    /**
     * @param \moodle_database $db
     * @param string $sql
     * @param int $maxrows
     * @return \stdClass|null
     */
    protected static function probe_pgsql_columns(\moodle_database $db, string $sql, int $maxrows): ?\stdClass {
        if (!function_exists('pg_query') || !function_exists('pg_num_fields')) {
            return null;
        }

        $connection = self::get_reflected_property($db, 'pgsql');
        if ($connection === null) {
            $connection = self::get_reflected_property($db, 'connection');
        }
        if ($connection === null) {
            return null;
        }

        $probesql = self::append_limit_clause($db, $sql, $maxrows);
        self::invoke_db_query_start($db, $probesql);

        $driverresult = @pg_query($connection, $probesql);
        self::invoke_db_query_end($db, $driverresult);

        if ($driverresult === false) {
            return (object) [
                'columns' => [],
                'source' => 'none',
            ];
        }

        return self::finalize_native_result($driverresult, 'pgsql');
    }

    /**
     * @param \moodle_database $db
     * @param string $sql
     * @param int $maxrows
     * @return \stdClass|null
     */
    protected static function probe_sqlite_columns(\moodle_database $db, string $sql, int $maxrows): ?\stdClass {
        $sqlite = self::get_reflected_property($db, 'sqlite3');
        if (!$sqlite instanceof \SQLite3) {
            return null;
        }

        $probesql = self::append_limit_clause($db, $sql, $maxrows);
        self::invoke_db_query_start($db, $probesql);

        $driverresult = $sqlite->query($probesql);
        self::invoke_db_query_end($db, $driverresult);

        if ($driverresult === false) {
            return (object) [
                'columns' => [],
                'source' => 'none',
            ];
        }

        return self::finalize_native_result($driverresult, 'sqlite');
    }

    /**
     * @param mixed $driverresult
     * @param string $driver
     * @return \stdClass
     */
    protected static function finalize_native_result($driverresult, string $driver): \stdClass {
        $columns = [];
        $source = 'none';

        switch ($driver) {
            case 'mysqli':
                $columns = self::read_mysqli_columns($driverresult);
                break;
            case 'pgsql':
                $columns = self::read_pgsql_columns($driverresult);
                break;
            case 'sqlite':
                $columns = self::read_sqlite_columns($driverresult);
                break;
        }

        if (!empty($columns)) {
            $source = 'metadata';
        } else if ($driver === 'mysqli' && $driverresult instanceof \mysqli_result) {
            $row = $driverresult->fetch_assoc();
            if (!empty($row)) {
                $columns = \report_sql::column_names_from_record($row);
                $source = 'first_row';
            }
        } else if ($driver === 'pgsql' && function_exists('pg_fetch_assoc')) {
            $row = pg_fetch_assoc($driverresult);
            if (!empty($row)) {
                $columns = \report_sql::column_names_from_record($row);
                $source = 'first_row';
            }
        } else if ($driver === 'sqlite' && $driverresult instanceof \SQLite3Result) {
            $row = $driverresult->fetchArray(SQLITE3_ASSOC);
            if (!empty($row)) {
                $columns = \report_sql::column_names_from_record($row);
                $source = 'first_row';
            }
        }

        self::free_native_result($driverresult, $driver);

        return (object) [
            'columns' => $columns,
            'source' => $source,
        ];
    }

    /**
     * @param mixed $driverresult
     * @param string $driver
     * @return void
     */
    protected static function free_native_result($driverresult, string $driver): void {
        if ($driver === 'mysqli' && $driverresult instanceof \mysqli_result) {
            $driverresult->free();
            return;
        }
        if ($driver === 'pgsql' && function_exists('pg_free_result')) {
            pg_free_result($driverresult);
            return;
        }
        if ($driver === 'sqlite' && $driverresult instanceof \SQLite3Result) {
            $driverresult->finalize();
        }
    }

    /**
     * Append a driver-appropriate LIMIT clause when needed.
     *
     * @param \moodle_database $db
     * @param string $sql
     * @param int $maxrows
     * @return string
     */
    protected static function append_limit_clause(\moodle_database $db, string $sql, int $maxrows): string {
        if ($maxrows < 1) {
            return $sql;
        }

        if (method_exists($db, 'get_limit_clause')) {
            return $sql . ' ' . $db->get_limit_clause($maxrows);
        }

        $family = $db->get_dbfamily();
        if (in_array($family, ['mysql', 'mysqli', 'mariadb', 'postgres', 'pgsql', 'sqlite'], true)) {
            return $sql . ' LIMIT ' . (int) $maxrows;
        }

        return $sql;
    }

    /**
     * @param \moodle_database $db
     * @param string $sql
     * @return void
     */
    protected static function invoke_db_query_start(\moodle_database $db, string $sql): void {
        if (!method_exists($db, 'query_start')) {
            return;
        }

        $method = new \ReflectionMethod($db, 'query_start');
        $method->setAccessible(true);
        $method->invoke($db, $sql, [], SQL_QUERY_SELECT);
    }

    /**
     * @param \moodle_database $db
     * @param mixed $result
     * @return void
     */
    protected static function invoke_db_query_end(\moodle_database $db, $result): void {
        if (!method_exists($db, 'query_end')) {
            return;
        }

        $method = new \ReflectionMethod($db, 'query_end');
        $method->setAccessible(true);
        $method->invoke($db, $result);
    }

    /**
     * Read column names from native result metadata on an existing recordset.
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
            case 'mysqli':
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
     * @param object $object
     * @param string $property
     * @return mixed|null
     */
    protected static function get_reflected_property(object $object, string $property) {
        $reflection = new \ReflectionClass($object);
        while ($reflection !== false) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                return $prop->getValue($object);
            }
            $reflection = $reflection->getParentClass();
        }
        return null;
    }

    /**
     * @param object $recordset
     * @return mixed|null
     */
    protected static function get_native_result(object $recordset) {
        foreach (['result', 'sqlite3result', 'statement'] as $property) {
            $value = self::get_reflected_property($recordset, $property);
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
        if (!is_object($result)) {
            return [];
        }

        if (method_exists($result, 'fetch_fields')) {
            $fields = $result->fetch_fields();
        } else if (function_exists('mysqli_fetch_fields') && $result instanceof \mysqli_result) {
            $fields = mysqli_fetch_fields($result);
        } else {
            return [];
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
        if ($count === false || $count === 0) {
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
