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

    /** @var array<string, mixed> */
    private array $parentfilterparams;

    /** @var \report_base|null */
    private ?\report_base $parentreportclass = null;

    /**
     * Constructor.
     *
     * @param object $parentreport
     * @param array<string, mixed> $chainelement
     * @param \context $context
     * @param int $userid
     * @param array<string, mixed> $parentfilterparams
     */
    public function __construct(
        object $parentreport,
        array $chainelement,
        \context $context,
        int $userid,
        array $parentfilterparams = []
    ) {
        $this->parentreport = $parentreport;
        $this->chainelement = $chainelement;
        $this->formdata = definition::normalise_formdata((object) ($chainelement['formdata'] ?? new \stdClass()));
        $this->context = $context;
        $this->userid = $userid;
        $this->parentfilterparams = $parentfilterparams;
    }

    /**
     * Parent report table column headings for preview UI.
     *
     * @return array<int|string, string>
     */
    public function get_parent_table_head(): array {
        $table = $this->load_parent_report()->finalreport->table;
        $head = [];
        foreach ($table->head ?? [] as $key => $heading) {
            $head[$key] = \block_configurable_reports\export\report_matrix::cell_to_plain_text($heading);
        }
        return $head;
    }

    /**
     * Execute parent report and return table rows with selection metadata.
     *
     * @return array<int, object> List of row descriptors.
     */
    public function get_parent_row_descriptors(): array {
        $reportclass = $this->load_parent_report();

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
        $reportclass = $this->load_parent_report();
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
     * Export selected parent rows to a ZIP archive of child reports (sync).
     *
     * @param array<int, string> $selectedrowkeys
     * @param string $format
     * @return export_result
     */
    public function export_selected_rows(array $selectedrowkeys, string $format): export_result {
        global $DB;

        $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $this->formdata->childreportid], '*', MUST_EXIST);
        $result = new export_result();
        $result->zipfilename = definition::build_zip_filename($this->parentreport, $childreport, $this->formdata);

        $validation = definition::validate_element($this->parentreport, $this->chainelement);
        if (!$validation->valid) {
            throw new \moodle_exception('chainerror_invalid', 'block_configurable_reports', '', $validation->error);
        }

        $allowedformats = definition::get_allowed_export_formats($childreport);
        if (!isset($allowedformats[$format])) {
            throw new \moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }

        if (!cr_check_report_permissions($childreport, $this->userid, $this->context)) {
            throw new \moodle_exception('badpermissions', 'block_configurable_reports');
        }

        temp_file_cleanup::cleanup_stale_files();
        return $this->run_export_loop($selectedrowkeys, $format, $childreport, null, $result);
    }

    /**
     * Export for a background job (incremental ZIP + progress).
     *
     * @param object $job
     * @return void
     */
    public function export_for_job(object $job): void {
        global $DB;

        $selectedrowkeys = json_decode($job->selectedrowkeys ?? '[]', true) ?: [];
        $format = $job->exportformat;
        $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $this->formdata->childreportid], '*', MUST_EXIST);

        $validation = definition::validate_element($this->parentreport, $this->chainelement);
        if (!$validation->valid) {
            export_job::mark_failed($job, $validation->error);
            return;
        }

        if (!cr_check_report_permissions($childreport, $this->userid, $this->context)) {
            export_job::mark_failed($job, get_string('badpermissions', 'block_configurable_reports'));
            return;
        }

        temp_file_cleanup::cleanup_stale_files();
        $result = new export_result();
        $result->zipfilename = $job->zipfilename ?: definition::build_zip_filename($this->parentreport, $childreport, $this->formdata);
        $this->run_export_loop($selectedrowkeys, $format, $childreport, $job, $result);
    }

    /**
     * Core export loop (sync or job-backed).
     *
     * @param array<int, string> $selectedrowkeys
     * @param string $format
     * @param object $childreport
     * @param object|null $job
     * @param export_result $result
     * @return export_result
     */
    private function run_export_loop(
        array $selectedrowkeys,
        string $format,
        object $childreport,
        ?object $job,
        export_result $result
    ): export_result {
        global $DB;

        $parentclass = $this->load_parent_report();
        $table = $parentclass->finalreport->table;
        $rowindexes = $this->resolve_selected_row_indexes($selectedrowkeys);
        if (empty($rowindexes)) {
            if ($job) {
                export_job::mark_failed($job, get_string('chainerror_norows', 'block_configurable_reports'));
            } else {
                throw new \moodle_exception('chainerror_norows', 'block_configurable_reports');
            }
            return $result;
        }

        $groups = definition::group_row_indexes_by_column_mapping($table, $rowindexes, $this->formdata);
        $exporter = new exporter();
        $rownum = 0;
        $incremental = ($job !== null);
        $temppaths = [];

        if ($incremental) {
            $job->progresstotal = count($groups);
            $job->exported = $job->exported ?? json_encode([]);
            $job->skipped = $job->skipped ?? json_encode([]);
            export_job::save($job);
            $exporter->open_zip_archive($result->zipfilename, (int) $job->id);
        }

        try {
            foreach ($groups as $groupindex => $group) {
                if ($job && export_job::is_cancel_requested($job)) {
                    break;
                }

                $rownum++;
                $rowindex = $group->rowindex;
                $keyvalues = definition::extract_row_key_values($table, $rowindex, $this->formdata->rowkeycolumns);
                $rowlabel = implode(' / ', array_filter($keyvalues));
                if ($rowlabel === '') {
                    $rowlabel = get_string('chainexportrownumber', 'block_configurable_reports', $rownum);
                }
                if ($group->count > 1) {
                    $rowlabel .= ' ' . get_string('chainexportmergedrows', 'block_configurable_reports', $group->count);
                }

                $start = microtime(true);
                $filterparams = definition::build_child_filter_params_for_row($table, $rowindex, $this->formdata);
                $childfinal = $this->execute_child_report($childreport, $filterparams);
                $durationms = (int) round((microtime(true) - $start) * 1000);

                if (!definition::finalreport_has_data($childfinal)) {
                    $skip = (object) [
                        'label' => $rowlabel,
                        'reason' => get_string('chainexportskippednodata', 'block_configurable_reports'),
                    ];
                    $result->skipped[] = $skip;
                    if ($job) {
                        $this->append_job_skipped($job, $skip);
                        $this->update_job_progress($job, $durationms);
                    }
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
                        $skip = (object) [
                            'label' => $rowlabel,
                            'reason' => get_string('chainexportskippednodata', 'block_configurable_reports'),
                        ];
                        $result->skipped[] = $skip;
                        if ($job) {
                            $this->append_job_skipped($job, $skip);
                            $this->update_job_progress($job, $durationms);
                        }
                        continue;
                    }
                    throw $e;
                }

                $exported = (object) [
                    'label' => $rowlabel,
                    'filename' => $filename,
                ];
                $result->exported[] = $exported;

                if ($incremental) {
                    $exporter->add_file_to_zip($filename, $temppath);
                    temp_file_cleanup::delete_file_if_exists($temppath);
                    $this->append_job_exported($job, $exported);
                    $this->update_job_progress($job, $durationms);
                    if ($groupindex < count($groups) - 1) {
                        export_job::apply_iteration_delay($durationms);
                    }
                } else {
                    $temppaths[] = $temppath;
                    $result->pendingfiles[] = [
                        'name' => $filename,
                        'path' => $temppath,
                    ];
                }
            }

            if ($incremental) {
                $cancelled = $job && export_job::is_cancel_requested($job);
                if (!empty($result->exported)) {
                    $result->zippath = $exporter->close_zip_archive();
                    $job->zippath = export_job::job_zip_path((int) $job->id);
                    $job->zipfilename = $result->zipfilename;
                    $job->timefinished = time();
                    if ($cancelled) {
                        $job->status = export_job::STATUS_PARTIAL;
                    } else {
                        $job->progressdone = (int) $job->progresstotal;
                        $job->status = export_job::STATUS_COMPLETED;
                    }
                } else if ($cancelled) {
                    $job->status = export_job::STATUS_CANCELLED;
                    $job->timefinished = time();
                    if ($exporter->is_zip_open()) {
                        $exporter->close_zip_archive();
                        temp_file_cleanup::delete_file_if_exists(export_job::job_zip_path((int) $job->id));
                    }
                    $job->zippath = null;
                } else {
                    $job->progressdone = (int) $job->progresstotal;
                    $job->timefinished = time();
                    if ($exporter->is_zip_open()) {
                        $exporter->close_zip_archive();
                        temp_file_cleanup::delete_file_if_exists(export_job::job_zip_path((int) $job->id));
                    }
                    $job->status = export_job::STATUS_FAILED;
                    $job->errormessage = get_string('chainexportnoexported', 'block_configurable_reports');
                    $job->zippath = null;
                }
                export_job::save($job);
            } else if (!empty($result->pendingfiles)) {
                $result->zippath = $exporter->create_zip_archive($result->pendingfiles, $result->zipfilename);
            }
        } catch (\Throwable $e) {
            if ($incremental && $exporter->is_zip_open()) {
                $exporter->close_zip_archive();
            }
            temp_file_cleanup::cleanup_temp_files($temppaths);
            if ($job) {
                export_job::mark_failed($job, $e->getMessage());
            } else {
                throw $e;
            }
        } finally {
            if (!empty($this->parentfilterparams)) {
                filter_injection::clear((int) $this->parentreport->id);
            }
        }

        return $result;
    }

    /**
     * @param object $job
     * @param object $item
     * @return void
     */
    private function append_job_exported(object $job, object $item): void {
        $exported = json_decode($job->exported ?? '[]', true) ?: [];
        $exported[] = ['label' => $item->label, 'filename' => $item->filename];
        $job->exported = json_encode($exported);
    }

    /**
     * @param object $job
     * @param object $item
     * @return void
     */
    private function append_job_skipped(object $job, object $item): void {
        $skipped = json_decode($job->skipped ?? '[]', true) ?: [];
        $skipped[] = ['label' => $item->label, 'reason' => $item->reason];
        $job->skipped = json_encode($skipped);
    }

    /**
     * @param object $job
     * @param int $durationms
     * @param bool $countasdone Whether to advance the iteration counter (default true).
     * @return void
     */
    private function update_job_progress(object $job, int $durationms, bool $countasdone = true): void {
        if ($countasdone) {
            $job->progressdone = (int) $job->progressdone + 1;
        }
        $job->lastdurationms = $durationms;
        if ((int) $job->progressdone > 0) {
            $prevavg = (int) $job->avgdurationms;
            $job->avgdurationms = (int) round(
                ($prevavg * ((int) $job->progressdone - 1) + $durationms) / (int) $job->progressdone
            );
        } else {
            $job->avgdurationms = $durationms;
        }
        export_job::save($job);
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
     * Execute parent report once and cache the instance.
     *
     * @return \report_base
     */
    private function load_parent_report(): \report_base {
        if ($this->parentreportclass === null) {
            $reportclass = $this->create_parent_report_instance();
            if ($reportclass->should_defer_execution()) {
                throw new \moodle_exception('filtersubmitrequired', 'block_configurable_reports');
            }
            $reportclass->create_report();
            $this->parentreportclass = $reportclass;
        }
        return $this->parentreportclass;
    }

    /**
     * Create parent report class with current request filter context.
     *
     * @return \report_base
     */
    private function create_parent_report_instance(): \report_base {
        if (!empty($this->parentfilterparams)) {
            filter_injection::set((int) $this->parentreport->id, $this->parentfilterparams);
        }
        require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/reports/' . $this->parentreport->type . '/report.class.php');
        $classname = 'report_' . $this->parentreport->type;
        return new $classname($this->parentreport);
    }
}
