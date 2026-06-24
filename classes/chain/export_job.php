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
    public const STATUS_INTERRUPTED = 'interrupted';

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
            'timelastprogress' => 0,
            'timezipdownloaded' => 0,
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

        self::detect_interrupted_jobs($parentreportid);

        $now = time();
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE userid = :userid
                   AND parentreportid = :parentreportid
                   AND chainid = :chainid
                   AND (
                        status IN (:queued, :running, :interrupted)
                        OR (status IN (:completed, :partial) AND zipdownloaded = 0 AND timeexpires > :now)
                   )
              ORDER BY timecreated DESC";
        $jobs = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'parentreportid' => $parentreportid,
            'chainid' => $chainid,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'interrupted' => self::STATUS_INTERRUPTED,
            'completed' => self::STATUS_COMPLETED,
            'partial' => self::STATUS_PARTIAL,
            'now' => $now,
        ]);
        foreach ($jobs as $job) {
            $job = self::refresh_job_state($job);
            if (in_array($job->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true)) {
                return $job;
            }
            if ($job->status === self::STATUS_INTERRUPTED || self::can_resume_export($job)) {
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

        self::detect_interrupted_jobs($parentreportid);
        self::cleanup_expired_download_archives($parentreportid);

        $now = time();
        $gracethreshold = $now - self::get_redownload_grace_seconds();
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE userid = :userid
                   AND parentreportid = :parentreportid
                   AND (
                        status IN (:queued, :running, :failed, :cancelled, :interrupted)
                        OR (status IN (:completed, :partial) AND zipdownloaded = 0 AND timeexpires > :now)
                        OR (status IN (:completed2, :partial2) AND zipdownloaded = 1
                            AND timezipdownloaded > 0 AND timezipdownloaded > :gracethreshold)
                        OR (status IN (:completed3, :partial3) AND zipdownloaded = 1
                            AND timezipdownloaded > 0 AND timezipdownloaded <= :gracethreshold2)
                   )
              ORDER BY timecreated DESC";
        $jobs = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'parentreportid' => $parentreportid,
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'failed' => self::STATUS_FAILED,
            'cancelled' => self::STATUS_CANCELLED,
            'interrupted' => self::STATUS_INTERRUPTED,
            'completed' => self::STATUS_COMPLETED,
            'partial' => self::STATUS_PARTIAL,
            'completed2' => self::STATUS_COMPLETED,
            'partial2' => self::STATUS_PARTIAL,
            'completed3' => self::STATUS_COMPLETED,
            'partial3' => self::STATUS_PARTIAL,
            'now' => $now,
            'gracethreshold' => $gracethreshold,
            'gracethreshold2' => $gracethreshold,
        ]);
        return array_filter($jobs, function(object $job): bool {
            $job = self::refresh_job_state($job);
            if (in_array($job->status, [
                self::STATUS_QUEUED,
                self::STATUS_RUNNING,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
                self::STATUS_INTERRUPTED,
            ], true)) {
                return true;
            }
            return self::is_downloadable($job) || self::can_resume_export($job) || self::is_deletable($job);
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

        self::detect_interrupted_jobs($parentreportid);

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
        if (!$job || in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            return;
        }

        self::detect_interrupted_jobs((int) $job->parentreportid);
        $job = self::get($jobid);
        if (!$job || in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_INTERRUPTED], true)) {
            if ($job && $job->status === self::STATUS_INTERRUPTED) {
                self::dispatch_next_queued((int) $job->parentreportid);
            }
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_configurable_reports');
        $lock = $lockfactory->get_lock('chainjob_' . $jobid, 0);
        if (!$lock) {
            self::queue_task($jobid);
            return;
        }

        try {
            $job = self::get($jobid);
            if (!$job) {
                return;
            }

            if ($job->status === self::STATUS_RUNNING && self::is_running_without_progress($job)) {
                self::finalize_as_interrupted($job);
                return;
            }

            if ($job->status === self::STATUS_QUEUED) {
                $running = self::get_running_for_parent((int) $job->parentreportid);
                if ($running && (int) $running->id !== (int) $job->id) {
                    self::queue_task($jobid);
                    return;
                }
                $job->status = self::STATUS_RUNNING;
                $job->timestarted = time();
                $job->timelastprogress = time();
                $DB->update_record(self::TABLE, $job);
            } else if ($job->status !== self::STATUS_RUNNING) {
                return;
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

            $runner = new runner($parentreport, $chainelement, $context, (int) $job->userid, $parentfilters);
            try {
                $runner->export_for_job($job);
            } catch (\Throwable $e) {
                self::mark_failed($job, $e->getMessage());
            }
        } finally {
            $lock->release();
            $job = self::get($jobid);
            if ($job) {
                self::dispatch_next_queued((int) $job->parentreportid);
            }
        }
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
     * Re-download grace period in seconds (site setting, 1+ minutes).
     *
     * @return int
     */
    public static function get_redownload_grace_seconds(): int {
        $minutes = (int) get_config('block_configurable_reports', 'chainexportredownloadminutes');
        if ($minutes <= 0) {
            $minutes = 15;
        } else if ($minutes > 1440) {
            $minutes = 1440;
        }
        return $minutes * 60;
    }

    /**
     * Timestamp when the archive was first marked downloaded (legacy-safe).
     *
     * @param object $job
     * @return int
     */
    public static function get_download_marked_time(object $job): int {
        if (!empty($job->timezipdownloaded)) {
            return (int) $job->timezipdownloaded;
        }
        if (!empty($job->timefinished)) {
            return (int) $job->timefinished;
        }
        return (int) ($job->timecreated ?? 0);
    }

    /**
     * Whether a previously downloaded archive may be fetched again within grace.
     *
     * @param object $job
     * @return bool
     */
    public static function is_within_redownload_grace(object $job): bool {
        if (empty($job->zipdownloaded)) {
            return false;
        }
        $downloadedat = self::get_download_marked_time($job);
        if ($downloadedat <= 0) {
            return false;
        }
        return time() < $downloadedat + self::get_redownload_grace_seconds();
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
        if (count($exported) === 0 || self::resolve_zip_path($job) === null) {
            return false;
        }
        if (!empty($job->zipdownloaded)) {
            return self::is_within_redownload_grace($job);
        }
        return true;
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
        if ($job->status === self::STATUS_INTERRUPTED) {
            return !self::can_resume_export($job) && !self::is_downloadable($job);
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
     * Minutes without progress before a running job is treated as interrupted.
     *
     * @return int
     */
    public static function get_no_progress_minutes(): int {
        $mins = (int) get_config('block_configurable_reports', 'chainexportnoprogressminutes');
        return $mins > 0 ? $mins : 10;
    }

    /**
     * Whether a running job has not reported progress recently.
     *
     * @param object $job
     * @return bool
     */
    public static function is_running_without_progress(object $job): bool {
        if ($job->status !== self::STATUS_RUNNING) {
            return false;
        }
        $last = (int) ($job->timelastprogress ?? 0);
        if ($last <= 0) {
            $last = (int) $job->timestarted;
        }
        if ($last <= 0) {
            return false;
        }
        $cutoff = time() - self::get_no_progress_minutes() * 60;
        if ($last < $cutoff) {
            return true;
        }
        $stalecutoff = time() - self::get_stale_run_minutes() * 60;
        return (int) $job->timestarted > 0 && (int) $job->timestarted < $stalecutoff;
    }

    /**
     * Whether a running job has no active worker (lock not held).
     *
     * @param object $job
     * @return bool
     */
    public static function is_orphaned_running_job(object $job): bool {
        if ($job->status !== self::STATUS_RUNNING) {
            return false;
        }
        $lockfactory = \core\lock\lock_config::get_lock_factory('block_configurable_reports');
        $lock = $lockfactory->get_lock('chainjob_' . (int) $job->id, 0);
        if ($lock) {
            $lock->release();
            return true;
        }
        return false;
    }

    /**
     * Detect and finalize running jobs that stopped making progress.
     *
     * @param int $parentreportid
     * @return void
     */
    public static function detect_interrupted_jobs(int $parentreportid): void {
        global $DB;

        $running = $DB->get_records_select(
            self::TABLE,
            'parentreportid = :parentreportid AND status = :running',
            ['parentreportid' => $parentreportid, 'running' => self::STATUS_RUNNING]
        );
        foreach ($running as $job) {
            $shouldinterrupt = self::is_orphaned_running_job($job) || self::is_running_without_progress($job);
            if (!$shouldinterrupt) {
                continue;
            }
            $lockfactory = \core\lock\lock_config::get_lock_factory('block_configurable_reports');
            $lock = $lockfactory->get_lock('chainjob_' . (int) $job->id, 0);
            if (!$lock) {
                continue;
            }
            try {
                $fresh = self::get((int) $job->id);
                if (!$fresh || $fresh->status !== self::STATUS_RUNNING) {
                    continue;
                }
                if (self::is_orphaned_running_job($fresh) || self::is_running_without_progress($fresh)) {
                    self::finalize_as_interrupted($fresh);
                }
            } finally {
                $lock->release();
            }
        }
    }

    /**
     * Reload job and apply interruption detection for its parent report.
     *
     * @param object $job
     * @return object
     */
    public static function refresh_job_state(object $job): object {
        self::detect_interrupted_jobs((int) $job->parentreportid);
        return self::get((int) $job->id) ?? $job;
    }

    /**
     * Mark a stalled running job as interrupted (or partial when ZIP is valid).
     *
     * @param object $job
     * @return void
     */
    public static function finalize_as_interrupted(object $job): void {
        global $DB;

        if ($job->status !== self::STATUS_RUNNING) {
            return;
        }

        $exported = json_decode($job->exported ?? '[]', true) ?: [];
        $zippath = self::resolve_zip_path($job);
        $job->timefinished = time();
        $job->errormessage = get_string('chainexportinterrupted', 'block_configurable_reports', (object) [
            'done' => (int) $job->progressdone,
            'total' => (int) $job->progresstotal,
        ]);

        if ($zippath !== null && count($exported) > 0) {
            $job->status = self::STATUS_PARTIAL;
            $job->zippath = $zippath;
        } else {
            $job->status = self::STATUS_INTERRUPTED;
        }

        $DB->update_record(self::TABLE, $job);
        self::dispatch_next_queued((int) $job->parentreportid);
    }

    /**
     * @deprecated Use detect_interrupted_jobs().
     * @param int $parentreportid
     * @return void
     */
    public static function reclaim_stale_running_jobs(int $parentreportid): void {
        self::detect_interrupted_jobs($parentreportid);
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

        $job = self::refresh_job_state($job);

        $exported = json_decode($job->exported ?? '[]', true) ?: [];
        $skipped = json_decode($job->skipped ?? '[]', true) ?: [];
        $remaining = max(0, (int) $job->progresstotal - (int) $job->progressdone);
        $etams = $remaining * (int) $job->avgdurationms;

        $queueposition = 0;
        $queueblocked = false;
        $waitetams = 0;
        if ($job->status === self::STATUS_QUEUED) {
            $queueposition = self::get_queue_position($job);
            $running = self::get_running_for_parent((int) $job->parentreportid, (int) $job->id);
            if ($running) {
                $queueblocked = true;
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

        $previewlimit = 50;
        $exportedpreview = [];
        foreach (array_slice($exported, 0, $previewlimit) as $item) {
            $exportedpreview[] = [
                'label' => (string) ($item['label'] ?? ''),
                'filename' => (string) ($item['filename'] ?? ''),
            ];
        }
        $skippedpreview = [];
        foreach (array_slice($skipped, 0, $previewlimit) as $item) {
            $skippedpreview[] = [
                'label' => (string) ($item['label'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }

        return [
            'jobid' => (int) $job->id,
            'status' => $job->status,
            'progresstotal' => $progresstotal,
            'progressdone' => $progressdone,
            'progresspercent' => $progresspercent,
            'etaseconds' => (int) round(($etams + $waitetams) / 1000),
            'queueposition' => $queueposition,
            'queueblocked' => $queueblocked,
            'exportedcount' => count($exported),
            'skippedcount' => count($skipped),
            'exportedpreview' => $exportedpreview,
            'skippedpreview' => $skippedpreview,
            'errormessage' => $job->errormessage ?? '',
            'downloadable' => $downloadable,
            'dismissable' => self::is_dismissable($job),
            'deletable' => self::is_deletable($job),
            'resumable' => self::can_resume_export($job),
            'zipfilename' => $job->zipfilename ?? '',
            'zipdownloaded' => (bool) $job->zipdownloaded,
            'redownloadable' => self::is_within_redownload_grace($job),
        ];
    }

    /**
     * Validate a local return URL or fall back to the chains tab.
     *
     * @param string|null $returnurl
     * @param int $reportid Parent report id.
     * @param int|null $courseid Optional course id for the fallback URL.
     * @return \moodle_url
     */
    public static function resolve_return_url(?string $returnurl, int $reportid, ?int $courseid = null): \moodle_url {
        global $CFG;

        $fallbackparams = [
            'id' => $reportid,
            'comp' => 'chains',
        ];
        if ($courseid !== null) {
            $fallbackparams['courseid'] = $courseid;
        }
        $fallback = new \moodle_url('/blocks/configurable_reports/editcomp.php', $fallbackparams);

        if ($returnurl === null || $returnurl === '') {
            return $fallback;
        }

        try {
            $cleanurl = clean_param($returnurl, PARAM_LOCALURL);
            if ($cleanurl === '') {
                return $fallback;
            }
            $url = new \moodle_url($cleanurl);
            if (strpos($url->out(false), $CFG->wwwroot) !== 0) {
                return $fallback;
            }
            return $url;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * Return URL after deleting a job (never back to the deleted job page).
     *
     * @param string|null $returnurl
     * @param int $jobid
     * @param int $reportid
     * @param int|null $courseid
     * @param string|null $chainid
     * @return \moodle_url
     */
    public static function resolve_return_url_after_job_delete(
        ?string $returnurl,
        int $jobid,
        int $reportid,
        ?int $courseid = null,
        ?string $chainid = null
    ): \moodle_url {
        $url = self::resolve_return_url($returnurl, $reportid, $courseid);
        $params = $url->params();
        if (!empty($params['jobid']) && (int) $params['jobid'] === $jobid) {
            if ($chainid !== null && $chainid !== '') {
                $newexportparams = [
                    'id' => $reportid,
                    'chainid' => $chainid,
                    'newexport' => 1,
                ];
                if ($courseid !== null) {
                    $newexportparams['courseid'] = $courseid;
                }
                return new \moodle_url('/blocks/configurable_reports/chainexport.php', $newexportparams);
            }
            return self::resolve_return_url(null, $reportid, $courseid);
        }
        return $url;
    }

    /**
     * Whether export can continue from the last completed iteration.
     *
     * @param object $job
     * @return bool
     */
    public static function can_resume_export(object $job): bool {
        if ((int) $job->timeexpires < time()) {
            return false;
        }
        if ($job->status === self::STATUS_INTERRUPTED) {
            return (int) $job->progressdone < (int) $job->progresstotal;
        }
        if ($job->status === self::STATUS_PARTIAL) {
            return (int) $job->progressdone < (int) $job->progresstotal;
        }
        return false;
    }

    /**
     * Whether the user may delete this job record.
     *
     * @param object $job
     * @return bool
     */
    public static function is_deletable(object $job): bool {
        return $job->status !== self::STATUS_RUNNING;
    }

    /**
     * Row keys already exported or skipped for resume.
     *
     * @param object $job
     * @return array<string, bool>
     */
    public static function get_processed_rowkeys(object $job): array {
        $keys = [];
        foreach (json_decode($job->exported ?? '[]', true) ?: [] as $item) {
            if (!empty($item['rowkey'])) {
                $keys[$item['rowkey']] = true;
            }
        }
        foreach (json_decode($job->skipped ?? '[]', true) ?: [] as $item) {
            if (!empty($item['rowkey'])) {
                $keys[$item['rowkey']] = true;
            }
        }
        return $keys;
    }

    /**
     * Whether runner should continue an in-progress job.
     *
     * @param object $job
     * @return bool
     */
    public static function should_resume_job(object $job): bool {
        return self::can_resume_export($job) || (
            $job->status === self::STATUS_RUNNING
            && ((int) $job->progressdone > 0 || count(json_decode($job->exported ?? '[]', true) ?: []) > 0)
        );
    }

    /**
     * Re-queue an interrupted or partial job.
     *
     * @param int $jobid
     * @param int $userid
     * @return bool
     */
    public static function resume_job(int $jobid, int $userid): bool {
        global $DB;

        $job = self::get($jobid);
        if (!$job || (int) $job->userid !== $userid || !self::can_resume_export($job)) {
            return false;
        }

        $job->status = self::STATUS_QUEUED;
        $job->errormessage = null;
        $job->timefinished = 0;
        $job->cancelrequested = 0;
        $DB->update_record(self::TABLE, $job);
        self::queue_task($jobid);
        return true;
    }

    /**
     * Remove ZIP files associated with a job.
     *
     * @param object $job
     * @return void
     */
    public static function cleanup_job_files(object $job): void {
        $zippath = self::resolve_zip_path($job);
        if ($zippath !== null) {
            temp_file_cleanup::delete_file_if_exists($zippath);
        }
        temp_file_cleanup::delete_file_if_exists(self::job_zip_path((int) $job->id));
    }

    /**
     * Permanently delete a job record without dispatching the next queued job.
     *
     * @param int $jobid
     * @param int $userid
     * @return bool
     */
    public static function delete_job_record(int $jobid, int $userid): bool {
        global $DB;

        $job = self::get($jobid);
        if (!$job || (int) $job->userid !== $userid || !self::is_deletable($job)) {
            return false;
        }

        if ($job->status === self::STATUS_QUEUED) {
            self::cancel($jobid, $userid);
            $job = self::get($jobid);
            if (!$job) {
                return true;
            }
        }

        self::cleanup_job_files($job);
        $DB->delete_records(self::TABLE, ['id' => $jobid, 'userid' => $userid]);
        return true;
    }

    /**
     * Permanently delete a job owned by the user.
     *
     * @param int $jobid
     * @param int $userid
     * @return bool
     */
    public static function delete_job(int $jobid, int $userid): bool {
        $job = self::get($jobid);
        if (!$job) {
            return false;
        }
        $parentreportid = (int) $job->parentreportid;
        if (!self::delete_job_record($jobid, $userid)) {
            return false;
        }
        self::dispatch_next_queued($parentreportid);
        return true;
    }

    /**
     * Delete all non-running export jobs for a user on a parent report.
     *
     * @param int $userid
     * @param int $parentreportid
     * @return array{deleted: int, skippedrunning: int}
     */
    public static function delete_all_jobs_for_user_report(int $userid, int $parentreportid): array {
        global $DB;

        self::detect_interrupted_jobs($parentreportid);

        $running = $DB->count_records_select(
            self::TABLE,
            'userid = :userid AND parentreportid = :parentreportid AND status = :running',
            [
                'userid' => $userid,
                'parentreportid' => $parentreportid,
                'running' => self::STATUS_RUNNING,
            ]
        );

        $jobs = $DB->get_records_select(
            self::TABLE,
            'userid = :userid AND parentreportid = :parentreportid AND status <> :running',
            [
                'userid' => $userid,
                'parentreportid' => $parentreportid,
                'running' => self::STATUS_RUNNING,
            ]
        );

        $deleted = 0;
        foreach ($jobs as $job) {
            if (self::delete_job_record((int) $job->id, $userid)) {
                $deleted++;
            }
        }

        self::dispatch_next_queued($parentreportid);

        return [
            'deleted' => $deleted,
            'skippedrunning' => (int) $running,
        ];
    }

    /**
     * Remove ZIP files for jobs whose re-download grace period has expired.
     *
     * @param int|null $parentreportid Limit cleanup to one parent report.
     * @return void
     */
    public static function cleanup_expired_download_archives(?int $parentreportid = null): void {
        global $DB;

        $gracethreshold = time() - self::get_redownload_grace_seconds();
        $select = 'zipdownloaded = 1 AND (
            (timezipdownloaded > 0 AND timezipdownloaded < :gracethreshold)
            OR (timezipdownloaded = 0 AND timefinished > 0 AND timefinished < :gracethresholdfinished)
        )';
        $params = [
            'gracethreshold' => $gracethreshold,
            'gracethresholdfinished' => $gracethreshold,
        ];
        if ($parentreportid !== null) {
            $select .= ' AND parentreportid = :parentreportid';
            $params['parentreportid'] = $parentreportid;
        }

        $jobs = $DB->get_records_select(self::TABLE, $select, $params);
        foreach ($jobs as $job) {
            self::cleanup_job_files($job);
        }
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
        $job->timezipdownloaded = time();
        $DB->update_record(self::TABLE, $job);
        return $job;
    }
}
