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

use block_configurable_reports\chain\definition;

/**
 * Tests for chain definition helpers.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\chain\definition
 */
class chain_definition_test extends \advanced_testcase {

    /**
     * Row key hash should be stable for the same values.
     */
    public function test_build_row_key_hash_is_stable(): void {
        $hash1 = definition::build_row_key_hash(['10', 'Group A']);
        $hash2 = definition::build_row_key_hash(['10', 'Group A']);
        $this->assertSame($hash1, $hash2);
        $this->assertNotSame($hash1, definition::build_row_key_hash(['11', 'Group A']));
    }

    /**
     * Filename builder should sanitise output and add extension.
     */
    public function test_build_filename_sanitises_and_adds_extension(): void {
        $child = (object) ['name' => 'Grades / Group'];
        $formdata = (object) ['filenamepattern' => 'grades_##groupname##'];
        $filename = definition::build_filename($child, $formdata, ['groupname' => 'A/B'], 'xls');
        $this->assertStringEndsWith('.xls', $filename);
        $this->assertStringNotContainsString('/', $filename);
    }

    /**
     * Self-reference should fail validation.
     */
    public function test_validate_element_rejects_self_reference(): void {
        $parent = (object) [
            'id' => 5,
            'export' => 'xls,',
            'components' => '',
        ];
        $element = [
            'formdata' => (object) [
                'enabled' => 1,
                'childreportid' => 5,
                'rowkeycolumns' => ['groupid'],
                'mappings' => [(object) ['sourcecolumn' => 'groupid', 'targetfilter' => 'filter_searchtext']],
            ],
        ];
        $result = definition::validate_element($parent, $element);
        $this->assertFalse($result->valid);
    }

    /**
     * Mapping should read parent row values by column name.
     */
    public function test_build_filter_params_for_row(): void {
        $table = (object) [
            'head' => ['groupid', 'groupname'],
            'data' => [
                ['42', 'Alpha'],
            ],
        ];
        $formdata = definition::normalise_formdata((object) [
            'mappings' => [(object) ['sourcecolumn' => 'groupid', 'targetfilter' => 'filter_searchtext']],
        ]);
        $params = definition::build_filter_params_for_row($table, 0, $formdata);
        $this->assertSame('42', $params['filter_searchtext']);
    }

    /**
     * Unknown source columns should fail validation when SQL metadata is present.
     */
    public function test_validate_source_columns_rejects_unknown_column(): void {
        $parent = (object) [
            'id' => 5,
            'type' => 'sql',
            'components' => cr_serialize([
                'customsql' => [
                    'config' => (object) [
                        'outputcolumns' => ['groupid', 'groupname'],
                    ],
                ],
            ]),
        ];
        $formdata = definition::normalise_formdata((object) [
            'rowkeycolumns' => ['groupid'],
            'mappings' => [(object) ['sourcecolumn' => 'unknown_col', 'targetfilter' => 'filter_searchtext']],
        ]);

        $result = definition::validate_source_columns($parent, $formdata);
        $this->assertFalse($result->valid);
    }
}
