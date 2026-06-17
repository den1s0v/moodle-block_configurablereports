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

require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Chain export job persistence and orchestration.
 *
 * @package   block_configurable_reports
 */
class export_job {

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TABLE = 'block_configurable_reports_cjob';

    /**
     * Create a new export job and queue background processing.
     *
     * @param object $parentreport
     * @param array<string, mixed> $chainelement
     * @param \context $context
     * @param int $userid
     * @param string $format
     * @param array<int, string> $selectedrowkeys
     * @param array<string, mixed> $parentfilters
     * @return int Job id.
     */
    public static function create_and_queue(
        object $parentreport,
        array $chainelement,
        \context $context,
        int $userid,
        string $format,
        array $selectedrowkeys,
        array $parentfilters
    ): int {
        global $DB;

        $formdata = definition::normalise_formdata((object) ($chainelement['formdata'] ?? new \stdClass()));
        $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $formdata->childreportid], '*', MUST_EXIST);
        $zipfilename = definition::build_zip_filename($parentreport, $childreport, $formdata);
        $ttlhours = (int) get_config('block_configurable_reports', 'chainexportjobttl');
        if ($ttlhours <= 0) {
            $ttlhours = 48;
        }
        $now = time();

        self::prepare_for_new_job($userid, (int) $parentreport->id, (string) $chainelement['id']);

        $record = (object) [
            'userid' => $userid,
            'parentreportid' => (int) $parentreport->id,
            'chainid' => (string) $chainelement['id'],
            'courseid' => (int) $parentreport->courseid,
            'status' => self::STATUS_QUEUED,
            'exportformat' => $format,
            'parentfilters' => json_encode($parentfilters),
            'selectedrowkeys' => json_encode(array_values($selectedrowkeys)),
            'progresstotal' => 0,
            'progressdone' => 0,
            'lastdurationms' => 0,
            'avgdurationms' => 0,
            'zipdownloaded' => 0,
            'cancelrequested' => 0,
            'exported' => json_encode([]),
            'skipped' => json_encode([]),
            'zippath' => null,
            'zipfilename' => $zipfilename,
            'errormessage' => null,
            'timecreated' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
            'timeexpires' => $now + ($ttlhours * 3600),
        ];

        $jobid = (int) $DB->insert_record(self::TABLE, $record);
        self::queue_task($jobid);
        return $jobid;
    }

    /**
     * Queue adhoc task for a job.
     *
     * @param int $jobid
     * @return void
     */
    public static function queue_task(int $jobid): void {
        $task = new \block_configurable_reports\task\chain_export_task();
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->set_component('block_configurable_reports');
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Load a job record.
     *
     * @param int $jobid
     * @return object|null
     */
    public static function get(int $jobid): ?object {
        global $DB;
        $job = $DB->get_record(self::TABLE, ['id' => $jobid], '*', IGNORE_MISSING);
        return $job ?: null;
    }

    /**
     * Active or downloadable job for user on a chain.
     *
     * @param int $userid
     * @param int $parentreportid
     * @param string $chainid
     * @return object|null
     */
    public static function get_resumable_for_user(int $userid, int $parentreportid, string $chainid): ?object {
        global $DB;

        self::reclaim_stale_running_jobs($parentreportid);

        $now = time();
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE userid = :userid
                   AND parentreportid = :parentreportid
                   AND chainid = :chainid
                   AND (
                        status IN (:queued, :running)
                        OR (status IN (:completed, :partial) AND zipdownloaded = 0 AND timeexpires > :now)
                   )
              ORDER BY timecreated DESC";
        $jobs = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'parentreportid' => $parentreportid,
            'chainid' => $chainid,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'completed' => self::STATUS_COMPLETED,
            'partial' => self::STATUS_PARTIAL,
            'now' => $now,
        ]);
        foreach ($jobs as $job) {
            if (in_array($job->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true)) {
                return $job;
            }
            if (self::is_downloadable($job)) {
                return $job;
            }
        }
        return null;
    }

    /**
     * Jobs to show on the chains configuration page.
     *
     * @param int $userid
     * @param int $parentreportid
     * @return array<int, object>
     */
    public static function get_user_jobs_for_report(int $userid, int $parentreportid): array {
        global $DB;

        self::reclaim_stale_running_jobs($parentreportid);

        $now = time();
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE userid = :userid
                   AND parentreportid = :parentreportid
                   AND (
                        status IN (:queued, :running, :failed, :cancelled)
                        OR (status IN (:completed, :partial) AND zipdownloaded = 0 AND timeexpires > :now)
                   )
              ORDER BY timecreated DESC";
        $jobs = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'parentreportid' => $parentreportid,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'failed' => self::STATUS_FAILED,
            'cancelled' => self::STATUS_CANCELLED,
            'completed' => self::STATUS_COMPLETED,
            'partial' => self::STATUS_PARTIAL,
            'now' => $now,
        ]);
        return array_filter($jobs, function(object $job): bool {
            if (in_array($job->status, [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
                return true;
            }
            return self::is_downloadable($job);
        });
    }

    /**
     * Whether another job is currently running for this parent report.
     *
     * @param int $parentreportid
     * @param int $excludejobid
     * @return object|null
     */
    public static function get_running_for_parent(int $parentreportid, int $excludejobid = 0): ?object {
        global $DB;
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE parentreportid = :parentreportid
                   AND status = :running";
        $params = [
            'parentreportid' => $parentreportid,
            'running' => self::STATUS_RUNNING,
        ];
        if ($excludejobid > 0) {
            $sql .= " AND id <> :excludeid";
            $params['excludeid'] = $excludejobid;
        }
        $sql .= " ORDER BY timestarted ASC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Queue position for a queued job (1-based).
     *
     * @param object $job
     * @return int
     */
    public static function get_queue_position(object $job): int {
        global $DB;
        if ($job->status !== self::STATUS_QUEUED) {
            return 0;
        }
        $count = $DB->count_records_select(
            self::TABLE,
            'parentreportid = :parentreportid AND status = :queued AND timecreated <= :timecreated',
            [
                'parentreportid' => (int) $job->parentreportid,
                'queued' => self::STATUS_QUEUED,
                'timecreated' => (int) $job->timecreated,
            ]
        );
        return max(1, (int) $count);
    }

    /**
     * Process a job (called from adhoc task).
     *
     * @param int $jobid
     * @return void
     */
    public static function process_job(int $jobid): void {
        global $DB;

        $job = self::get($jobid);
        if (!$job || in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            return;
        }

        self::reclaim_stale_running_jobs((int) $job->parentreportid);

        if ($job->status === self::STATUS_QUEUED) {
            $running = self::get_running_for_parent((int) $job->parentreportid);
            if ($running && (int) $running->id !== (int) $job->id) {
                self::queue_task($jobid);
                return;
            }
            $job->status = self::STATUS_RUNNING;
            $job->timestarted = time();
            $DB->update_record(self::TABLE, $job);
        }

        $parentreport = $DB->get_record('block_configurable_reports', ['id' => (int) $job->parentreportid], '*', MUST_EXIST);
        $chainelement = definition::get_chain_element_by_id($parentreport, $job->chainid);
        if (!$chainelement) {
            self::mark_failed($job, get_string('chainerror_invalid', 'block_configurable_reports'));
            return;
        }

        if ((int) $job->courseid === SITEID) {
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance((int) $job->courseid);
        }

        $parentfilters = json_decode($job->parentfilters ?? '[]', true) ?: [];
        $selectedrowkeys = json_decode($job->selectedrowkeys ?? '[]', true) ?: [];

        $runner = new runner($parentreport, $chainelement, $context, (int) $job->userid, $parentfilters);
        try {
            $runner->export_for_job($job);
        } catch (\Throwable $e) {
            self::mark_failed($job, $e->getMessage());
        }

        self::dispatch_next_queued((int) $job->parentreportid);
    }

    /**
     * Queue the next waiting job for a parent report.
     *
     * @param int $parentreportid
     * @return void
     */
    public static function dispatch_next_queued(int $parentreportid): void {
        global $DB;
        if (self::get_running_for_parent($parentreportid)) {
            return;
        }
        $next = $DB->get_records_select(
            self::TABLE,
            'parentreportid = :parentreportid AND status = :queued',
            ['parentreportid' => $parentreportid, 'queued' => self::STATUS_QUEUED],
            'timecreated ASC',
            '*',
            0,
            1
        );
        if ($next) {
            $job = reset($next);
            self::queue_task((int) $job->id);
        }
    }

    /**
     * Cancel a job for the owning user.
     *
     * @param int $jobid
     * @param int $userid
     * @return bool
     */
    public static function cancel(int $jobid, int $userid): bool {
        global $DB;
        $job = self::get($jobid);
        if (!$job || (int) $job->userid !== $userid) {
            return false;
        }
        if ($job->status === self::STATUS_QUEUED) {
            $job->status = self::STATUS_CANCELLED;
            $job->timefinished = time();
            $DB->update_record(self::TABLE, $job);
            return true;
        }
        if ($job->status === self::STATUS_RUNNING) {
            $job->cancelrequested = 1;
            $DB->update_record(self::TABLE, $job);
            return true;
        }
        return false;
    }

    /**
     * Whether a finished job has a ZIP archive ready to download.
     *
     * @param object $job
     * @return bool
     */
    public static function is_downloadable(object $job): bool {
        if (!in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL], true)) {
            return false;
        }
        if ((int) $job->timeexpires < time()) {
            return false;
        }
        $exported = json_decode($job->exported ?? '[]', true) ?: [];
        return count($exported) > 0 && self::resolve_zip_path($job) !== null;
    }

    /**
     * Whether the user can dismiss this job from active lists / resume.
     *
     * @param object $job
     * @return bool
     */
    public static function is_dismissable(object $job): bool {
        if (!empty($job->zipdownloaded)) {
            return false;
        }
        if (in_array($job->status, [self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            return true;
        }
        if (in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL], true)) {
            return !self::is_downloadable($job);
        }
        if ($job->status === self::STATUS_RUNNING) {
            return self::is_running_stale($job);
        }
        return $job->status === self::STATUS_QUEUED;
    }

    /**
     * @param object $job
     * @return bool
     */
    public static function is_running_stale(object $job): bool {
        if ($job->status !== self::STATUS_RUNNING || empty($job->timestarted)) {
            return false;
        }
        $cutoff = time() - self::get_stale_run_minutes() * 60;
        return (int) $job->timestarted < $cutoff;
    }

    /**
     * @return int
     */
    public static function get_stale_run_minutes(): int {
        $mins = (int) get_config('block_configurable_reports', 'chainexportstalerunminutes');
        return $mins > 0 ? $mins : 120;
    }

    /**
     * Mark long-running jobs as failed so the queue can continue.
     *
     * @param int $parentreportid
     * @return void
     */
    public static function reclaim_stale_running_jobs(int $parentreportid): void {
        global $DB;

        $cutoff = time() - self::get_stale_run_minutes() * 60;
        $stale = $DB->get_records_select(
            self::TABLE,
            'parentreportid = :parentreportid AND status = :running AND timestarted > 0 AND timestarted < :cutoff',
            [
                'parentreportid' => $parentreportid,
                'running' => self::STATUS_RUNNING,
                'cutoff' => $cutoff,
            ]
        );
        if (!$stale) {
            return;
        }
        foreach ($stale as $job) {
            $job->status = self::STATUS_FAILED;
            $job->errormessage = get_string('chainexportstale', 'block_configurable_reports');
            $job->timefinished = time();
            $job->zipdownloaded = 1;
            $DB->update_record(self::TABLE, $job);
        }
        self::dispatch_next_queued($parentreportid);
    }

    /**
     * Clear blocking jobs before starting a new export for the same chain.
     *
     * @param int $userid
     * @param int $parentreportid
     * @param string $chainid
     * @return void
     */
    public static function prepare_for_new_job(int $userid, int $parentreportid, string $chainid): void {
        global $DB;

        self::reclaim_stale_running_jobs($parentreportid);

        $blocking = $DB->get_records_select(
            self::TABLE,
            'userid = :userid AND parentreportid = :parentreportid AND chainid = :chainid AND zipdownloaded = 0',
            [
                'userid' => $userid,
                'parentreportid' => $parentreportid,
                'chainid' => $chainid,
            ]
        );
        foreach ($blocking as $job) {
            if ($job->status === self::STATUS_QUEUED) {
                self::cancel((int) $job->id, $userid);
            } else if (self::is_dismissable($job)) {
                self::dismiss_job((int) $job->id, $userid);
            }
        }
    }

    /**
     * Remove a job from resume/lists (user acknowledged or abandoned export).
     *
     * @param int $jobid
     * @param int $userid
     * @return bool
     */
    public static function dismiss_job(int $jobid, int $userid): bool {
        global $DB;

        $job = self::get($jobid);
        if (!$job || (int) $job->userid !== $userid || !self::is_dismissable($job)) {
            return false;
        }

        if ($job->status === self::STATUS_QUEUED) {
            return self::cancel($jobid, $userid);
        }

        if ($job->status === self::STATUS_RUNNING) {
            $job->status = self::STATUS_FAILED;
            $job->errormessage = get_string('chainexportdismissed', 'block_configurable_reports');
            $job->timefinished = time();
            $job->zipdownloaded = 1;
            $DB->update_record(self::TABLE, $job);
            self::dispatch_next_queued((int) $job->parentreportid);
            return true;
        }

        $job->zipdownloaded = 1;
        if (empty($job->timefinished)) {
            $job->timefinished = time();
        }
        $DB->update_record(self::TABLE, $job);
        return true;
    }

    /**
     * Mark job failed.
     *
     * @param object $job
     * @param string $message
     * @return void
     */
    public static function mark_failed(object $job, string $message): void {
        global $DB;
        $job->status = self::STATUS_FAILED;
        $job->errormessage = $message;
        $job->timefinished = time();
        $DB->update_record(self::TABLE, $job);
    }

    /**
     * Persist job progress fields.
     *
     * @param object $job
     * @return void
     */
    public static function save(object $job): void {
        global $DB;
        $DB->update_record(self::TABLE, $job);
    }

    /**
     * Reload job from database.
     *
     * @param object $job
     * @return object
     */
    public static function reload(object $job): object {
        return self::get((int) $job->id) ?? $job;
    }

    /**
     * Whether cancellation was requested.
     *
     * @param object $job
     * @return bool
     */
    public static function is_cancel_requested(object $job): bool {
        $fresh = self::reload($job);
        return !empty($fresh->cancelrequested);
    }

    /**
     * Canonical on-disk path for a job ZIP (short path, fits DB column).
     *
     * @param int $jobid
     * @return string
     */
    public static function job_zip_path(int $jobid): string {
        return temp_file_cleanup::get_temp_directory() . '/cjob_' . $jobid . '.zip';
    }

    /**
     * Whether a path points to a non-empty readable ZIP archive.
     *
     * @param string|null $path
     * @return bool
     */
    public static function is_valid_zip_file(?string $path): bool {
        if (empty($path) || !is_file($path)) {
            return false;
        }
        if (@filesize($path) <= 22) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $valid = $zip->numFiles > 0;
        $zip->close();
        return $valid;
    }

    /**
     * Resolve the ZIP file path for a job (handles legacy truncated DB values).
     *
     * @param object $job
     * @return string|null
     */
    public static function resolve_zip_path(object $job): ?string {
        if (!empty($job->zippath) && self::is_valid_zip_file($job->zippath)) {
            return $job->zippath;
        }
        $canonical = self::job_zip_path((int) $job->id);
        if (self::is_valid_zip_file($canonical)) {
            return $canonical;
        }
        // Legacy jobs may have a truncated path in the DB while the file still exists on disk.
        if (!empty($job->zippath)) {
            $dir = temp_file_cleanup::get_temp_directory();
            $prefix = $job->zippath;
            if (is_dir($dir) && strncmp($prefix, $dir, strlen($dir)) === 0) {
                $iterator = new \DirectoryIterator($dir);
                foreach ($iterator as $item) {
                    if ($item->isDot() || !$item->isFile()) {
                        continue;
                    }
                    $path = $item->getPathname();
                    if (strncmp($path, $prefix, strlen($prefix)) === 0 && self::is_valid_zip_file($path)) {
                        return $path;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Build status payload for AJAX.
     *
     * @param object $job
     * @param int $vieweruserid
     * @return array<string, mixed>
     */
    public static function build_status_payload(object $job, int $vieweruserid): array {
        global $DB;

        if ((int) $job->userid !== $vieweruserid) {
            throw new \moodle_exception('badpermissions', 'block_configurable_reports');
        }

        $exported = json_decode($job->exported ?? '[]', true) ?: [];
        $skipped = json_decode($job->skipped ?? '[]', true) ?: [];
        $remaining = max(0, (int) $job->progresstotal - (int) $job->progressdone);
        $etams = $remaining * (int) $job->avgdurationms;

        $queueposition = 0;
        $waitetams = 0;
        if ($job->status === self::STATUS_QUEUED) {
            $queueposition = self::get_queue_position($job);
            $running = self::get_running_for_parent((int) $job->parentreportid);
            if ($running) {
                $runningremaining = max(0, (int) $running->progresstotal - (int) $running->progressdone);
                $waitetams = $runningremaining * (int) $running->avgdurationms;
            }
        }

        $zippath = self::resolve_zip_path($job);
        $downloadable = self::is_downloadable($job);

        $progressdone = (int) $job->progressdone;
        $progresstotal = (int) $job->progresstotal;
        if ($job->status === self::STATUS_COMPLETED && $progresstotal > 0) {
            $progressdone = $progresstotal;
        }
        $progresspercent = $progresstotal > 0
            ? (int) round(($progressdone / $progresstotal) * 100)
            : ($job->status === self::STATUS_COMPLETED ? 100 : 0);

        return [
            'jobid' => (int) $job->id,
            'status' => $job->status,
            'progresstotal' => $progresstotal,
            'progressdone' => $progressdone,
            'progresspercent' => $progresspercent,
            'etaseconds' => (int) round(($etams + $waitetams) / 1000),
            'queueposition' => $queueposition,
            'exportedcount' => count($exported),
            'skippedcount' => count($skipped),
            'errormessage' => $job->errormessage ?? '',
            'downloadable' => $downloadable,
            'dismissable' => self::is_dismissable($job),
            'zipfilename' => $job->zipfilename ?? '',
            'zipdownloaded' => (bool) $job->zipdownloaded,
        ];
    }

    /**
     * Apply inter-iteration delay after a child report run.
     *
     * @param int $lastdurationms
     * @return void
     */
    public static function apply_iteration_delay(int $lastdurationms): void {
        $threshold = (int) get_config('block_configurable_reports', 'chainexportdelaythresholdms');
        if ($threshold <= 0) {
            $threshold = 5000;
        }
        if ($lastdurationms < $threshold) {
            return;
        }
        $percent = (int) get_config('block_configurable_reports', 'chainexportdelaypercent');
        if ($percent <= 0) {
            $percent = 15;
        }
        $delayms = (int) round($lastdurationms * $percent / 100);
        if ($delayms > 0) {
            usleep($delayms * 1000);
        }
    }

    /**
     * Mark ZIP as downloaded.
     *
     * @param int $jobid
     * @param int $userid
     * @return object|null
     */
    public static function mark_downloaded(int $jobid, int $userid): ?object {
        global $DB;
        $job = self::get($jobid);
        if (!$job || (int) $job->userid !== $userid) {
            return null;
        }
        $job->zipdownloaded = 1;
        $DB->update_record(self::TABLE, $job);
        return $job;
    }
}
