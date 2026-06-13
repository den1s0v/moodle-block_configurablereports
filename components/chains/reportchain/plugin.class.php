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

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/blocks/configurable_reports/plugin.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/classes/chain/filter_params.php');

use block_configurable_reports\chain\filter_params;

/**
 * Report chain plugin.
 *
 * @package   block_configurable_reports
 */
class plugin_reportchain extends plugin_base {

    /**
     * Init plugin metadata.
     *
     * @return void
     */
    public function init(): void {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('reportchain', 'block_configurable_reports');
        $this->reporttypes = ['sql', 'courses', 'users', 'categories', 'timeline'];
    }

    /**
     * Summary for chain element list.
     *
     * @param object $data
     * @return string
     */
    public function summary(object $data): string {
        global $DB;

        $data = \block_configurable_reports\chain\definition::normalise_formdata($data);
        if (empty($data->childreportid)) {
            return get_string('reportchain_summary_empty', 'block_configurable_reports');
        }

        $child = $DB->get_record('block_configurable_reports', ['id' => (int) $data->childreportid], 'id,name', IGNORE_MISSING);
        if (!$child) {
            return get_string('reportchain_summary_missing', 'block_configurable_reports');
        }

        $childname = format_string($child->name);
        $mappingparts = [];
        foreach ($data->filterbindings as $binding) {
            $binding = (object) $binding;
            $target = trim((string) ($binding->targetfilter ?? ''));
            if ($target === '') {
                continue;
            }
            $mode = $binding->mode ?? filter_params::MODE_EMPTY;
            switch ($mode) {
                case filter_params::MODE_COLUMN:
                    $source = trim((string) ($binding->sourcecolumn ?? ''));
                    if ($source !== '') {
                        $mappingparts[] = s($source) . ' → ' . s($target);
                    }
                    break;
                case filter_params::MODE_CONSTANT:
                    $constant = (string) ($binding->constantvalue ?? '');
                    $mappingparts[] = "'" . s($constant) . "' → " . s($target);
                    break;
                case filter_params::MODE_EMPTY:
                default:
                    $mappingparts[] = '∅ → ' . s($target);
                    break;
            }
        }

        $a = (object) [
            'target' => $childname,
            'mappings' => !empty($mappingparts)
                ? implode('; ', $mappingparts)
                : get_string('reportchain_summary_nomappings', 'block_configurable_reports'),
        ];

        return get_string('reportchain_summary_full', 'block_configurable_reports', $a);
    }

    /**
     * Reports available as chain children.
     *
     * @return array<int, object>
     */
    public function get_available_child_reports(): array {
        global $USER, $DB;

        $reports = cr_get_my_reports($this->report->courseid, $USER->id);
        $available = [];
        foreach ($reports as $report) {
            if ((int) $report->id === (int) $this->report->id) {
                continue;
            }
            $available[$report->id] = $report;
        }

        return $available;
    }

    /**
     * Filter parameter names configured on a child report.
     *
     * @param int $childreportid
     * @return array<string, string>
     */
    public function get_child_filter_options(int $childreportid): array {
        global $DB;

        $child = $DB->get_record('block_configurable_reports', ['id' => $childreportid], '*', IGNORE_MISSING);
        if (!$child) {
            return [];
        }

        return filter_params::get_child_filter_options($child);
    }
}
