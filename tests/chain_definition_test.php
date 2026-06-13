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
use block_configurable_reports\chain\filter_params;

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
                'filterbindings' => [(object) [
                    'targetfilter' => 'filter_searchtext',
                    'mode' => filter_params::MODE_COLUMN,
                    'sourcecolumn' => 'groupid',
                ]],
            ],
        ];
        $result = definition::validate_element($parent, $element);
        $this->assertFalse($result->valid);
    }

    /**
     * Legacy mappings should migrate to column-mode filter bindings.
     */
    public function test_normalise_filterbindings_migrates_mappings(): void {
        $formdata = definition::normalise_formdata((object) [
            'mappings' => [(object) ['sourcecolumn' => 'groupid', 'targetfilter' => 'filter_searchtext']],
        ]);
        $this->assertCount(1, $formdata->filterbindings);
        $binding = $formdata->filterbindings[0];
        $this->assertSame('filter_searchtext', $binding->targetfilter);
        $this->assertSame(filter_params::MODE_COLUMN, $binding->mode);
        $this->assertSame('groupid', $binding->sourcecolumn);
        $this->assertObjectNotHasProperty('mappings', $formdata);
    }

    /**
     * Column binding should read parent row values by column name.
     */
    public function test_build_child_filter_params_column_mode(): void {
        $table = (object) [
            'head' => ['groupid', 'groupname'],
            'data' => [
                ['42', 'Alpha'],
            ],
        ];
        $formdata = definition::normalise_formdata((object) [
            'filterbindings' => [(object) [
                'targetfilter' => 'filter_searchtext',
                'mode' => filter_params::MODE_COLUMN,
                'sourcecolumn' => 'groupid',
            ]],
        ]);
        $params = definition::build_child_filter_params_for_row($table, 0, $formdata);
        $this->assertSame('42', $params['filter_searchtext']);
    }

    /**
     * Empty and constant binding modes should inject expected values.
     */
    public function test_build_child_filter_params_empty_and_constant_modes(): void {
        $table = (object) [
            'head' => ['groupid'],
            'data' => [['42']],
        ];
        $formdata = definition::normalise_formdata((object) [
            'filterbindings' => [
                (object) [
                    'targetfilter' => 'filter_searchtext',
                    'mode' => filter_params::MODE_EMPTY,
                ],
                (object) [
                    'targetfilter' => 'filter_courses',
                    'mode' => filter_params::MODE_CONSTANT,
                    'constantvalue' => '2024-1',
                ],
            ],
        ]);
        $params = definition::build_child_filter_params_for_row($table, 0, $formdata);
        $this->assertSame('', $params['filter_searchtext']);
        $this->assertSame('2024-1', $params['filter_courses']);
    }

    /**
     * Rows with identical column bindings should group for deduplicated export.
     */
    public function test_group_row_indexes_by_column_mapping(): void {
        $table = (object) [
            'head' => ['courseid', 'userid'],
            'data' => [
                ['10', '1'],
                ['10', '2'],
                ['20', '3'],
            ],
        ];
        $formdata = definition::normalise_formdata((object) [
            'filterbindings' => [(object) [
                'targetfilter' => 'filter_courses',
                'mode' => filter_params::MODE_COLUMN,
                'sourcecolumn' => 'courseid',
            ]],
        ]);
        $groups = definition::group_row_indexes_by_column_mapping($table, [0, 1, 2], $formdata);
        $this->assertCount(2, $groups);
        $counts = array_map(function($group) {
            return $group->count;
        }, $groups);
        sort($counts);
        $this->assertSame([1, 2], $counts);
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
            'filterbindings' => [(object) [
                'targetfilter' => 'filter_searchtext',
                'mode' => filter_params::MODE_COLUMN,
                'sourcecolumn' => 'unknown_col',
            ]],
        ]);

        $result = definition::validate_source_columns($parent, $formdata);
        $this->assertFalse($result->valid);
    }
}
