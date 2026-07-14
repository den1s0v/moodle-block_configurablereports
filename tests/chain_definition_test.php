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
     * Inactive bindings should not appear in chain summary lines.
     */
    public function test_format_filter_binding_summary_skips_inactive(): void {
        $labels = ['filter_searchtext' => 'Search'];
        $this->assertNull(definition::format_filter_binding_summary((object) [
            'targetfilter' => 'filter_searchtext',
            'mode' => filter_params::MODE_EMPTY,
        ], $labels));
        $this->assertNull(definition::format_filter_binding_summary((object) [
            'targetfilter' => 'filter_searchtext',
            'mode' => filter_params::MODE_COLUMN,
            'sourcecolumn' => '',
        ], $labels));
        $this->assertNull(definition::format_filter_binding_summary((object) [
            'targetfilter' => 'filter_searchtext',
            'mode' => filter_params::MODE_CONSTANT,
            'constantvalue' => '',
        ], $labels));
        $this->assertSame('groupid → Search', definition::format_filter_binding_summary((object) [
            'targetfilter' => 'filter_searchtext',
            'mode' => filter_params::MODE_COLUMN,
            'sourcecolumn' => 'groupid',
        ], $labels));
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
     * Filter view labels should match report view form captions.
     */
    public function test_get_filter_view_label_uses_configured_caption(): void {
        $label = filter_params::get_filter_view_label('searchtext', (object) [
            'label' => 'Group name',
            'idnumber' => 'group',
        ], 'filter_searchtext_group');
        $this->assertSame('Group name', $label);

        $courselabel = filter_params::get_filter_view_label('courses', new \stdClass(), 'filter_courses');
        $this->assertSame(get_string('course'), $courselabel);
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
     * ZIP filename builder should use pattern placeholders.
     */
    public function test_build_zip_filename_uses_pattern(): void {
        $parent = (object) ['name' => 'Source Report'];
        $child = (object) ['name' => 'Target Report'];
        $formdata = (object) [
            'chainname' => 'My chain',
            'zipfilenamepattern' => '##sourcereport##-##targetreport##-##chainname##',
        ];
        $filename = definition::build_zip_filename($parent, $child, $formdata);
        $this->assertStringEndsWith('.zip', $filename);
        $this->assertStringNotContainsString('/', $filename);
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

    /**
     * Missing child-filter bindings are implicit MODE_EMPTY (soft validation).
     */
    public function test_validate_filterbindings_allows_missing_as_empty(): void {
        $this->resetAfterTest();

        $child = $this->insert_report([
            'name' => 'Child with filters',
            'components' => $this->serialize_filters(['searchtext', 'semester']),
            'export' => 'csv,',
        ]);

        $formdata = definition::normalise_formdata((object) [
            'childreportid' => $child->id,
            'filterbindings' => [(object) [
                'targetfilter' => 'filter_searchtext',
                'mode' => filter_params::MODE_COLUMN,
                'sourcecolumn' => 'groupid',
            ]],
        ]);

        $result = definition::validate_filterbindings($child, $formdata);
        $this->assertTrue($result->valid);

        $bytarget = [];
        foreach ($formdata->filterbindings as $binding) {
            $bytarget[$binding->targetfilter] = $binding;
        }
        $this->assertArrayHasKey('filter_semester', $bytarget);
        $this->assertSame(filter_params::MODE_EMPTY, $bytarget['filter_semester']->mode);
    }

    /**
     * Runtime params use mapped values and empty string for new uncovered filters.
     */
    public function test_build_child_filter_params_covers_new_filters_as_empty(): void {
        $this->resetAfterTest();

        $child = $this->insert_report([
            'name' => 'Child filters runtime',
            'components' => $this->serialize_filters(['searchtext', 'semester']),
            'export' => 'csv,',
        ]);

        $table = new \stdClass();
        $table->head = ['groupid', 'groupname'];
        $table->data = [['42', 'Alpha']];

        $params = definition::build_child_filter_params_for_row($table, 0, (object) [
            'childreportid' => $child->id,
            'filterbindings' => [(object) [
                'targetfilter' => 'filter_searchtext',
                'mode' => filter_params::MODE_COLUMN,
                'sourcecolumn' => 'groupid',
            ]],
        ]);

        $this->assertSame('42', $params['filter_searchtext']);
        $this->assertSame('', $params['filter_semester']);
    }

    /**
     * Form save writes MODE_EMPTY for every child filter even when mode POST is absent.
     */
    public function test_prepare_filterbinding_data_covers_all_child_filters(): void {
        $this->resetAfterTest();
        global $CFG;
        require_once($CFG->dirroot . '/blocks/configurable_reports/components/chains/reportchain/form.php');

        $child = $this->insert_report([
            'name' => 'Child for form save',
            'components' => $this->serialize_filters(['searchtext', 'semester']),
            'export' => 'csv,',
        ]);

        $pluginstub = new class($child) {
            /** @var object */
            private $child;
            public function __construct(object $child) {
                $this->child = $child;
            }
            public function get_child_filter_options(int $childreportid): array {
                return filter_params::get_child_filter_options($this->child);
            }
        };

        $form = new class(null, ['pluginclass' => $pluginstub]) extends \reportchain_form {
            public function definition(): void {
                // UI not required for prepare_filterbinding_data().
            }
        };

        $data = (object) [
            'enabled' => 1,
            'childreportid' => $child->id,
            'filterbindingcount' => 1,
            'targetfilter0' => 'filter_searchtext',
            // Intentionally omit mode0 — must default to MODE_EMPTY, not skip the row.
            'sourcecolumn0' => '',
            'rowkeycolumns' => 'groupid',
        ];

        $prepared = $form->prepare_filterbinding_data($data);
        $bytarget = [];
        foreach ($prepared->filterbindings as $binding) {
            $bytarget[$binding->targetfilter] = $binding;
        }

        $this->assertCount(2, $prepared->filterbindings);
        $this->assertArrayHasKey('filter_searchtext', $bytarget);
        $this->assertArrayHasKey('filter_semester', $bytarget);
        $this->assertSame(filter_params::MODE_EMPTY, $bytarget['filter_searchtext']->mode);
        $this->assertSame(filter_params::MODE_EMPTY, $bytarget['filter_semester']->mode);
    }

    /**
     * Broken column binding keeps the chain in the enabled picker list as unusable.
     */
    public function test_enabled_export_list_keeps_invalid_chain_visible(): void {
        $this->resetAfterTest();

        $child = $this->insert_report([
            'name' => 'Target',
            'components' => $this->serialize_filters(['searchtext']),
            'export' => 'csv,',
        ]);

        $parent = $this->insert_report([
            'name' => 'Source',
            'export' => 'csv,',
            'components' => cr_serialize([
                'chains' => [
                    'elements' => [[
                        'id' => 'broken-chain',
                        'pluginname' => 'reportchain',
                        'formdata' => (object) [
                            'enabled' => 1,
                            'chainname' => 'Broken',
                            'childreportid' => $child->id,
                            'rowkeycolumns' => ['groupid'],
                            'filterbindings' => [(object) [
                                'targetfilter' => 'filter_searchtext',
                                'mode' => filter_params::MODE_COLUMN,
                                'sourcecolumn' => '',
                            ]],
                        ],
                    ]],
                ],
            ]),
        ]);

        $exportchains = definition::get_enabled_chain_elements_for_export($parent);
        $this->assertCount(1, $exportchains);
        $this->assertFalse($exportchains[0]->usable);
        $this->assertNotSame('', $exportchains[0]->unavailable_reason);
        $this->assertSame('broken-chain', $exportchains[0]->element['id']);

        $active = definition::get_active_chain_elements($parent);
        $this->assertCount(0, $active);

        // One enabled broken chain → zero usable → picker must not auto-redirect.
        $usable = array_values(array_filter($exportchains, static function(\stdClass $item): bool {
            return !empty($item->usable);
        }));
        $this->assertCount(0, $usable);
    }

    /**
     * After soft validation, a new child filter alone does not make the chain unusable.
     */
    public function test_new_child_filter_keeps_chain_usable(): void {
        $this->resetAfterTest();

        $child = $this->insert_report([
            'name' => 'Target with semester',
            'components' => $this->serialize_filters(['searchtext', 'semester']),
            'export' => 'csv,',
        ]);

        $parent = $this->insert_report([
            'name' => 'Source',
            'export' => 'csv,',
            'components' => cr_serialize([
                'chains' => [
                    'elements' => [[
                        'id' => 'ok-chain',
                        'pluginname' => 'reportchain',
                        'formdata' => (object) [
                            'enabled' => 1,
                            'chainname' => 'OK',
                            'childreportid' => $child->id,
                            'rowkeycolumns' => ['groupid'],
                            'filterbindings' => [(object) [
                                'targetfilter' => 'filter_searchtext',
                                'mode' => filter_params::MODE_COLUMN,
                                'sourcecolumn' => 'groupid',
                            ]],
                        ],
                    ]],
                ],
            ]),
        ]);

        $exportchains = definition::get_enabled_chain_elements_for_export($parent);
        $this->assertCount(1, $exportchains);
        $this->assertTrue($exportchains[0]->usable);
        $this->assertCount(1, definition::get_active_chain_elements($parent));
    }

    /**
     * Insert a block_configurable_reports row for tests.
     *
     * @param array<string, mixed> $overrides
     * @return object
     */
    private function insert_report(array $overrides = []): object {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = (object) array_merge([
            'courseid' => $course->id,
            'ownerid' => $user->id,
            'visible' => 1,
            'name' => 'Test report',
            'summary' => '',
            'summaryformat' => FORMAT_HTML,
            'type' => 'sql',
            'components' => '',
            'export' => 'csv,',
            'global' => 0,
            'lastexecutiontime' => 0,
            'cron' => 0,
            'requirefiltersubmit' => -1,
            'chainexportmode' => -1,
        ], $overrides);

        $record->id = $DB->insert_record('block_configurable_reports', $record);
        return $record;
    }

    /**
     * Serialize filter plugin names into report components.
     *
     * @param array<int, string> $pluginnames
     * @return string
     */
    private function serialize_filters(array $pluginnames): string {
        $elements = [];
        foreach ($pluginnames as $pluginname) {
            $elements[] = [
                'pluginname' => $pluginname,
                'pluginfullname' => $pluginname,
                'formdata' => new \stdClass(),
            ];
        }
        return cr_serialize(['filters' => ['elements' => $elements]]);
    }
}
