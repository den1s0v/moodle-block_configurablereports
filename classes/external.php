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

/**
 * Configurable Reports - A Moodle block for creating customizable reports
 *
 * @package    block_configurable_reports
 * @copyright  Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use block_configurable_reports\chain\export_job;
use context_course;
use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * This is the external API for this component.
 *
 * @copyright  Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Shared export row preview (exported file).
     *
     * @return external_multiple_structure
     */
    private static function exported_preview_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Row label'),
                'filename' => new external_value(PARAM_TEXT, 'File name'),
            ]),
            'Exported rows preview'
        );
    }

    /**
     * Shared export row preview (skipped).
     *
     * @return external_multiple_structure
     */
    private static function skipped_preview_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Row label'),
                'reason' => new external_value(PARAM_TEXT, 'Skip reason'),
            ]),
            'Skipped rows preview'
        );
    }

    /**
     * Base chain export status fields for web service responses.
     *
     * @return array<string, external_description>
     */
    private static function chain_export_status_base_returns(): array {
        return [
            'jobid' => new external_value(PARAM_INT, 'Job id'),
            'status' => new external_value(PARAM_ALPHA, 'Job status'),
            'progresstotal' => new external_value(PARAM_INT, 'Total iterations'),
            'progressdone' => new external_value(PARAM_INT, 'Completed iterations'),
            'progresspercent' => new external_value(PARAM_INT, 'Progress percent'),
            'etaseconds' => new external_value(PARAM_INT, 'ETA seconds'),
            'queueposition' => new external_value(PARAM_INT, 'Queue position'),
            'exportedcount' => new external_value(PARAM_INT, 'Exported file count'),
            'skippedcount' => new external_value(PARAM_INT, 'Skipped count'),
            'exportedpreview' => self::exported_preview_returns(),
            'skippedpreview' => self::skipped_preview_returns(),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message'),
            'downloadable' => new external_value(PARAM_BOOL, 'Whether ZIP can be downloaded'),
            'dismissable' => new external_value(PARAM_BOOL, 'Whether job can be dismissed'),
            'deletable' => new external_value(PARAM_BOOL, 'Whether job can be deleted'),
            'resumable' => new external_value(PARAM_BOOL, 'Whether export can be resumed'),
            'zipfilename' => new external_value(PARAM_TEXT, 'ZIP filename'),
            'zipdownloaded' => new external_value(PARAM_BOOL, 'Whether ZIP was downloaded'),
            'redownloadable' => new external_value(PARAM_BOOL, 'Whether ZIP is within re-download grace'),
        ];
    }

    /**
     * get_report_data parameters.
     *
     * @return external_function_parameters
     */
    public static function get_report_data_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'reportid' => new external_value(PARAM_INT, 'The report id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'The course id', VALUE_DEFAULT, 1),
            ]
        );
    }

    /**
     * Returns data of given report id.
     *
     * @param int $reportid the report id
     * @param int $courseid course id (default to site)
     * @return array An array with a 'data' JSON string and a 'warnings' string
     */
    public static function get_report_data($reportid, int $courseid = 1): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(
            self::get_report_data_parameters(),
            ['reportid' => $reportid, 'courseid' => $courseid]
        );

        if ($courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($courseid);
        }

        self::validate_context($context);

        $json = [];
        $warnings = '';
        if (!$report = $DB->get_record('block_configurable_reports', ['id' => $reportid])) {
            $warnings = get_string('reportdoesnotexists', 'block_configurable_reports');
        } else {

            require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
            require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
            require_once($CFG->dirroot . '/blocks/configurable_reports/reports/' . $report->type . '/report.class.php');

            $reportclassname = 'report_' . $report->type;
            $reportclass = new $reportclassname($report);
            if (!$reportclass->check_permissions($USER->id, $context)) {
                $warnings = get_string('badpermissions', 'block_configurable_reports');
            }

            $reportclass->create_report();
            $table = $reportclass->finalreport->table;
            $headers = $table->head;
            foreach ($table->data as $data) {
                $jsonobject = [];
                foreach ($data as $index => $value) {
                    $jsonobject[$headers[$index]] = $value;
                }
                $json[] = $jsonobject;
            }
        }

        return [
            'data' => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'warnings' => $warnings,
        ];
    }

    /**
     * get_report_data return
     *
     * @return external_single_structure
     */
    public static function get_report_data_returns(): external_single_structure {
        return new external_single_structure(
            [
                'data' => new external_value(PARAM_RAW, 'JSON-formatted report data'),
                'warnings' => new external_value(PARAM_TEXT, 'Warning message'),
            ]
        );
    }

    /**
     * get_chain_export_status parameters.
     *
     * @return external_function_parameters
     */
    public static function get_chain_export_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Export job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Return chain export job status for polling.
     *
     * @param int $jobid
     * @return array
     */
    public static function get_chain_export_status(int $jobid): array {
        global $USER;

        self::validate_parameters(self::get_chain_export_status_parameters(), ['jobid' => $jobid]);

        $job = export_job::get($jobid);
        if (!$job) {
            throw new \moodle_exception('chainerror_jobnotfound', 'block_configurable_reports');
        }

        $report = $GLOBALS['DB']->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        if ((int) $report->courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance((int) $job->courseid ?: (int) $report->courseid);
        }
        self::validate_context($context);
        require_capability('block/configurable_reports:viewreports', $context);

        return export_job::build_status_payload($job, (int) $USER->id);
    }

    /**
     * get_chain_export_status return structure.
     *
     * @return external_single_structure
     */
    public static function get_chain_export_status_returns(): external_single_structure {
        return new external_single_structure(self::chain_export_status_base_returns());
    }

    /**
     * cancel_chain_export parameters.
     *
     * @return external_function_parameters
     */
    public static function cancel_chain_export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Export job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Cancel the current user's chain export job.
     *
     * @param int $jobid
     * @return array
     */
    public static function cancel_chain_export(int $jobid): array {
        global $USER;

        self::validate_parameters(self::cancel_chain_export_parameters(), ['jobid' => $jobid]);

        $job = export_job::get($jobid);
        if (!$job) {
            throw new \moodle_exception('chainerror_jobnotfound', 'block_configurable_reports');
        }

        $report = $GLOBALS['DB']->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        if ((int) $report->courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance((int) $job->courseid ?: (int) $report->courseid);
        }
        self::validate_context($context);
        require_capability('block/configurable_reports:viewreports', $context);

        $cancelled = export_job::cancel($jobid, (int) $USER->id);
        $job = export_job::get($jobid);
        return export_job::build_status_payload($job, (int) $USER->id) + ['cancelled' => $cancelled];
    }

    /**
     * cancel_chain_export return structure.
     *
     * @return external_single_structure
     */
    public static function cancel_chain_export_returns(): external_single_structure {
        return new external_single_structure(self::chain_export_status_base_returns() + [
            'cancelled' => new external_value(PARAM_BOOL, 'Whether cancel was accepted'),
        ]);
    }

    /**
     * dismiss_chain_export parameters.
     *
     * @return external_function_parameters
     */
    public static function dismiss_chain_export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Export job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Dismiss a stuck or finished export job so a new export can start.
     *
     * @param int $jobid
     * @return array
     */
    public static function dismiss_chain_export(int $jobid): array {
        global $USER;

        self::validate_parameters(self::dismiss_chain_export_parameters(), ['jobid' => $jobid]);

        $job = export_job::get($jobid);
        if (!$job) {
            throw new \moodle_exception('chainerror_jobnotfound', 'block_configurable_reports');
        }

        $report = $GLOBALS['DB']->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        if ((int) $report->courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance((int) $job->courseid ?: (int) $report->courseid);
        }
        self::validate_context($context);
        require_capability('block/configurable_reports:viewreports', $context);

        $dismissed = export_job::dismiss_job($jobid, (int) $USER->id);
        $job = export_job::get($jobid);
        return export_job::build_status_payload($job, (int) $USER->id) + ['dismissed' => $dismissed];
    }

    /**
     * dismiss_chain_export return structure.
     *
     * @return external_single_structure
     */
    public static function dismiss_chain_export_returns(): external_single_structure {
        return new external_single_structure(self::chain_export_status_base_returns() + [
            'dismissed' => new external_value(PARAM_BOOL, 'Whether dismiss was accepted'),
        ]);
    }

    /**
     * resume_chain_export parameters.
     *
     * @return external_function_parameters
     */
    public static function resume_chain_export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Export job id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Resume an interrupted or partial chain export job.
     *
     * @param int $jobid
     * @return array
     */
    public static function resume_chain_export(int $jobid): array {
        global $USER;

        self::validate_parameters(self::resume_chain_export_parameters(), ['jobid' => $jobid]);

        $job = export_job::get($jobid);
        if (!$job) {
            throw new \moodle_exception('chainerror_jobnotfound', 'block_configurable_reports');
        }

        $report = $GLOBALS['DB']->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        if ((int) $report->courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance((int) $job->courseid ?: (int) $report->courseid);
        }
        self::validate_context($context);
        require_capability('block/configurable_reports:viewreports', $context);

        $resumed = export_job::resume_job($jobid, (int) $USER->id);
        $job = export_job::get($jobid);
        return export_job::build_status_payload($job, (int) $USER->id) + ['resumed' => $resumed];
    }

    /**
     * resume_chain_export return structure.
     *
     * @return external_single_structure
     */
    public static function resume_chain_export_returns(): external_single_structure {
        return new external_single_structure(self::chain_export_status_base_returns() + [
            'resumed' => new external_value(PARAM_BOOL, 'Whether resume was accepted'),
        ]);
    }

    /**
     * delete_chain_export parameters.
     *
     * @return external_function_parameters
     */
    public static function delete_chain_export_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Export job id', VALUE_REQUIRED),
            'returnurl' => new external_value(PARAM_LOCALURL, 'Redirect URL after delete', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Permanently delete a chain export job.
     *
     * @param int $jobid
     * @param string $returnurl
     * @return array
     */
    public static function delete_chain_export(int $jobid, string $returnurl = ''): array {
        global $USER;

        self::validate_parameters(self::delete_chain_export_parameters(), [
            'jobid' => $jobid,
            'returnurl' => $returnurl,
        ]);

        $job = export_job::get($jobid);
        if (!$job) {
            throw new \moodle_exception('chainerror_jobnotfound', 'block_configurable_reports');
        }

        $report = $GLOBALS['DB']->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        if ((int) $report->courseid === SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance((int) $job->courseid ?: (int) $report->courseid);
        }
        self::validate_context($context);
        require_capability('block/configurable_reports:viewreports', $context);

        $chainid = $job->chainid;
        $parentreportid = (int) $job->parentreportid;
        $jobcourseid = (int) $job->courseid;

        $deleted = export_job::delete_job($jobid, (int) $USER->id);
        $redirecturl = '';
        if ($deleted) {
            $redirecturl = export_job::resolve_return_url_after_job_delete(
                $returnurl !== '' ? $returnurl : null,
                $jobid,
                $parentreportid,
                $jobcourseid,
                $chainid
            )->out(false);
        }
        return [
            'deleted' => $deleted,
            'jobid' => $jobid,
            'redirecturl' => $redirecturl,
        ];
    }

    /**
     * delete_chain_export return structure.
     *
     * @return external_single_structure
     */
    public static function delete_chain_export_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether delete succeeded'),
            'jobid' => new external_value(PARAM_INT, 'Deleted job id'),
            'redirecturl' => new external_value(PARAM_LOCALURL, 'Redirect URL after delete', VALUE_OPTIONAL),
        ]);
    }

}
