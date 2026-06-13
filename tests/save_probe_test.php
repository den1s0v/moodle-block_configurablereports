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

namespace block_configurable_reports;

defined('MOODLE_INTERNAL') || die();

use block_configurable_reports\sql\result_column_reader;

/**
 * Tests for unified save probe column reading.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\sql\result_column_reader
 * @covers    \report_sql::probe_on_save
 */
class save_probe_test extends \advanced_testcase {

    /**
     * Empty recordset should report no columns.
     */
    public function test_read_with_source_empty_recordset(): void {
        global $DB;

        $recordset = new fake_empty_recordset();
        $read = result_column_reader::read_with_source($recordset, $DB);

        $this->assertSame([], $read->columns);
        $this->assertSame('none', $read->source);
    }

    /**
     * First row fallback should be used when metadata is unavailable.
     */
    public function test_read_with_source_first_row_fallback(): void {
        global $DB;

        $recordset = new fake_row_recordset([
            (object) ['courseid' => 1, 'name' => 'Alpha'],
        ]);
        $read = result_column_reader::read_with_source($recordset, $DB);

        $this->assertSame(['courseid', 'name'], $read->columns);
        $this->assertSame('first_row', $read->source);
    }

    /**
     * probe_on_save should memoize results for the same SQL within one request.
     */
    public function test_probe_on_save_memoization(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');

        $report = $this->getMockBuilder(\report_sql::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['build_sql_from_config', 'normalize_sql_prefixes_for_probe', 'execute_query'])
            ->getMock();

        $report->method('build_sql_from_config')->willReturn('SELECT 1 AS id');
        $report->method('normalize_sql_prefixes_for_probe')->willReturnArgument(0);
        $report->expects($this->once())
            ->method('execute_query')
            ->willReturn(new fake_row_recordset([
                (object) ['id' => 1],
            ]));

        $first = $report->probe_on_save('SELECT 1 AS id');
        $second = $report->probe_on_save('SELECT 1 AS id');

        $this->assertTrue($first->valid);
        $this->assertSame($first, $second);
        $this->assertSame(['id'], $first->columns);
        $this->assertSame('first_row', $first->columns_source);
    }
}

/**
 * Minimal iterator recordset with no rows.
 */
class fake_empty_recordset implements \Iterator {

    /** @var int */
    private int $position = 0;

    public function current(): mixed {
        return null;
    }

    public function key(): mixed {
        return $this->position;
    }

    public function next(): void {
        $this->position++;
    }

    public function rewind(): void {
        $this->position = 0;
    }

    public function valid(): bool {
        return false;
    }

    public function close(): void {
    }
}

/**
 * Minimal iterator recordset backed by in-memory rows.
 */
class fake_row_recordset implements \Iterator {

    /** @var array<int, object> */
    private array $rows;

    /** @var int */
    private int $position = 0;

    /**
     * @param array<int, object> $rows
     */
    public function __construct(array $rows) {
        $this->rows = array_values($rows);
    }

    public function current(): mixed {
        return $this->rows[$this->position] ?? null;
    }

    public function key(): mixed {
        return $this->position;
    }

    public function next(): void {
        $this->position++;
    }

    public function rewind(): void {
        $this->position = 0;
    }

    public function valid(): bool {
        return isset($this->rows[$this->position]);
    }

    public function close(): void {
    }
}
