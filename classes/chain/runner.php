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

namespace block_configurable_reports\chain;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');

/**
 * Chain execution: parent rows -> child report exports.
 *
 * @package   block_configurable_reports
 */
class runner {

    /** @var object */
    private object $parentreport;

    /** @var array<string, mixed> */
    private array $chainelement;

    /** @var object */
    private object $formdata;

    /** @var \context */
    private \context $context;

    /** @var int */
    private int $userid;

    /**
     * Constructor.
     *
     * @param object $parentreport
     * @param array<string, mixed> $chainelement
     * @param \context $context
     * @param int $userid
     */
    public function __construct(object $parentreport, array $chainelement, \context $context, int $userid) {
        $this->parentreport = $parentreport;
        $this->chainelement = $chainelement;
        $this->formdata = definition::normalise_formdata((object) ($chainelement['formdata'] ?? new \stdClass()));
        $this->context = $context;
        $this->userid = $userid;
    }

    /**
     * Execute parent report and return table rows with selection metadata.
     *
     * @return array<int, object> List of row descriptors.
     */
    public function get_parent_row_descriptors(): array {
        $reportclass = $this->create_parent_report_instance();
        if ($reportclass->should_defer_execution()) {
            throw new \moodle_exception('filtersubmitrequired', 'block_configurable_reports');
        }
        $reportclass->create_report();

        if (empty($reportclass->finalreport->table->data)) {
            return [];
        }

        $table = $reportclass->finalreport->table;
        $rows = [];
        foreach (array_keys($table->data) as $rowindex) {
            $keyvalues = definition::extract_row_key_values($table, $rowindex, $this->formdata->rowkeycolumns);
            $rows[] = (object) [
                'index' => $rowindex,
                'rowkey' => definition::build_row_key_hash($keyvalues),
                'keyvalues' => $keyvalues,
                'label' => implode(' / ', array_filter($keyvalues)),
                'cells' => $table->data[$rowindex],
            ];
        }
        return $rows;
    }

    /**
     * Resolve selected row keys against freshly executed parent data.
     *
     * @param array<int, string> $selectedrowkeys
     * @return array<int, int> Parent table row indexes.
     */
    public function resolve_selected_row_indexes(array $selectedrowkeys): array {
        $reportclass = $this->create_parent_report_instance();
        if ($reportclass->should_defer_execution()) {
            throw new \moodle_exception('filtersubmitrequired', 'block_configurable_reports');
        }
        $reportclass->create_report();

        $table = $reportclass->finalreport->table;
        $indexes = [];
        foreach (array_keys($table->data) as $rowindex) {
            $keyvalues = definition::extract_row_key_values($table, $rowindex, $this->formdata->rowkeycolumns);
            $hash = definition::build_row_key_hash($keyvalues);
            if (in_array($hash, $selectedrowkeys, true)) {
                $indexes[] = $rowindex;
            }
        }

        if (count($indexes) > definition::get_max_export_rows()) {
            throw new \moodle_exception('chainerror_toomanyrows', 'block_configurable_reports',
                '', definition::get_max_export_rows());
        }

        return $indexes;
    }

    /**
     * Export selected parent rows to a ZIP archive of child reports.
     *
     * @param array<int, string> $selectedrowkeys
     * @param string $format
     * @return export_result
     */
    public function export_selected_rows(array $selectedrowkeys, string $format): export_result {
        global $DB;

        $result = new export_result();
        $result->zipfilename = clean_filename(format_string($this->parentreport->name) . '_chainexport.zip');

        $validation = definition::validate_element($this->parentreport, $this->chainelement);
        if (!$validation->valid) {
            throw new \moodle_exception('chainerror_invalid', 'block_configurable_reports', '', $validation->error);
        }

        $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $this->formdata->childreportid], '*', MUST_EXIST);
        $allowedformats = definition::get_allowed_export_formats($childreport);
        if (!isset($allowedformats[$format])) {
            throw new \moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }

        if (!cr_check_report_permissions($childreport, $this->userid, $this->context)) {
            throw new \moodle_exception('badpermissions', 'block_configurable_reports');
        }

        $parentclass = $this->create_parent_report_instance();
        if ($parentclass->should_defer_execution()) {
            throw new \moodle_exception('filtersubmitrequired', 'block_configurable_reports');
        }
        $parentclass->create_report();
        $table = $parentclass->finalreport->table;

        $rowindexes = $this->resolve_selected_row_indexes($selectedrowkeys);
        if (empty($rowindexes)) {
            throw new \moodle_exception('chainerror_norows', 'block_configurable_reports');
        }

        $exporter = new exporter();
        $files = [];
        $rownum = 0;
        foreach ($rowindexes as $rowindex) {
            $rownum++;
            $keyvalues = definition::extract_row_key_values($table, $rowindex, $this->formdata->rowkeycolumns);
            $rowlabel = implode(' / ', array_filter($keyvalues));
            if ($rowlabel === '') {
                $rowlabel = get_string('chainexportrownumber', 'block_configurable_reports', $rownum);
            }

            $filterparams = definition::build_filter_params_for_row($table, $rowindex, $this->formdata);
            $childfinal = $this->execute_child_report($childreport, $filterparams);

            if (!definition::finalreport_has_data($childfinal)) {
                $result->skipped[] = (object) [
                    'label' => $rowlabel,
                    'reason' => get_string('chainexportskippednodata', 'block_configurable_reports'),
                ];
                continue;
            }

            $placeholders = [];
            foreach ($this->formdata->rowkeycolumns as $i => $column) {
                $placeholders['col' . ($i + 1)] = definition::extract_row_key_values($table, $rowindex, [$column])[0] ?? '';
                $placeholders[$column] = $placeholders['col' . ($i + 1)];
            }
            $placeholders['row'] = (string) $rownum;

            $filename = definition::build_filename($childreport, $this->formdata, $placeholders, $format);
            try {
                $temppath = $exporter->export_child_report_to_tempfile($childfinal, $format, $filename);
            } catch (\moodle_exception $e) {
                if ($e->errorcode === 'chainerror_exportempty') {
                    $result->skipped[] = (object) [
                        'label' => $rowlabel,
                        'reason' => get_string('chainexportskippednodata', 'block_configurable_reports'),
                    ];
                    continue;
                }
                throw $e;
            }

            $files[] = [
                'name' => $filename,
                'path' => $temppath,
            ];
            $result->exported[] = (object) [
                'label' => $rowlabel,
                'filename' => $filename,
            ];
        }

        if (!empty($files)) {
            $result->zippath = $exporter->create_zip_archive($files, $result->zipfilename);
        }

        return $result;
    }

    /**
     * Execute child report with injected filters.
     *
     * @param object $childreport
     * @param array<string, mixed> $filterparams
     * @return object finalreport
     */
    private function execute_child_report(object $childreport, array $filterparams): object {
        require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/reports/' . $childreport->type . '/report.class.php');
        $classname = 'report_' . $childreport->type;
        /** @var \report_base $childclass */
        $childclass = new $classname($childreport);

        $childclass->set_injected_filter_params($filterparams);
        try {
            if ($childreport->type === 'sql') {
                $childclass->set_forexport(true);
            }
            if ($childclass->should_defer_execution()) {
                throw new \moodle_exception('chainerror_childfilters', 'block_configurable_reports');
            }
            $childclass->create_report();
            return $childclass->finalreport;
        } finally {
            $childclass->clear_injected_filter_params();
        }
    }

    /**
     * Create parent report class with current request filter context.
     *
     * @return \report_base
     */
    private function create_parent_report_instance(): \report_base {
        require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/reports/' . $this->parentreport->type . '/report.class.php');
        $classname = 'report_' . $this->parentreport->type;
        return new $classname($this->parentreport);
    }
}
