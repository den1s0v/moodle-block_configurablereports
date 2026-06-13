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

use block_configurable_reports\report\output_columns;

/**
 * Tests for SQL output column metadata helpers.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\report\output_columns
 * @covers    \report_sql::column_names_from_record
 */
class output_columns_test extends \advanced_testcase {

    /**
     * Column names should be read from serialized SQL report components.
     */
    public function test_get_column_names_from_sql_report(): void {
        $report = (object) [
            'type' => 'sql',
            'components' => cr_serialize([
                'customsql' => [
                    'config' => (object) [
                        'outputcolumns' => ['groupid', 'groupname'],
                    ],
                ],
            ]),
        ];

        $this->assertSame(['groupid', 'groupname'], output_columns::get_column_names($report));
        $this->assertTrue(output_columns::has_metadata($report));
        $this->assertTrue(output_columns::is_known_column($report, 'groupid'));
        $this->assertFalse(output_columns::is_known_column($report, 'missing'));
        $this->assertSame(
            ['groupid' => 'groupid', 'groupname' => 'groupname'],
            output_columns::get_select_options($report)
        );
    }

    /**
     * Non-SQL reports should not expose cached column metadata.
     */
    public function test_get_column_names_empty_for_non_sql(): void {
        $report = (object) [
            'type' => 'users',
            'components' => '',
        ];

        $this->assertSame([], output_columns::get_column_names($report));
        $this->assertFalse(output_columns::has_metadata($report));
    }

    /**
     * Diagnostic HTML should list detected columns without redundant status lines.
     */
    public function test_format_sql_save_diagnostic_detected(): void {
        $html = output_columns::format_diagnostic(
            ['courseid', 'name'],
            true,
            'ok',
            1700000000
        );

        $this->assertStringContainsString('courseid', $html);
        $this->assertStringContainsString('name', $html);
        $this->assertStringNotContainsString(
            get_string('sqloutputcolumns_status_yes', 'block_configurable_reports'),
            $html
        );
    }

    /**
     * Diagnostic HTML should explain when columns were not detected.
     */
    public function test_format_sql_save_diagnostic_not_detected(): void {
        $html = output_columns::format_diagnostic([], false, 'metadata_unavailable', 1700000000);

        $this->assertStringNotContainsString('courseid', $html);
    }

    /**
     * Record keys should be extracted in order without duplicates.
     */
    public function test_column_names_from_record(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');

        $row = (object) [
            'groupid' => 1,
            'groupname' => 'Alpha',
        ];

        $this->assertSame(['groupid', 'groupname'], \report_sql::column_names_from_record($row));
    }
}
