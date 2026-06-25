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

use block_configurable_reports\chain\export_job;
use block_configurable_reports\external;

/**
 * Tests for chain export jobs.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\chain\export_job
 */
class chain_export_job_test extends \advanced_testcase {

    /**
     * Insert a minimal job row for tests.
     *
     * @param array<string, mixed> $overrides
     * @param object|null $context Shared user/report context from create_job_context().
     * @return object
     */
    private function insert_job(array $overrides = [], ?object $context = null): object {
        global $DB;

        if ($context === null) {
            $context = $this->create_job_context();
        }
        $now = time();
        $record = (object) array_merge([
            'userid' => $context->userid,
            'parentreportid' => $context->parentreportid,
            'chainid' => 'chain1',
            'courseid' => $context->courseid,
            'status' => export_job::STATUS_QUEUED,
            'exportformat' => 'csv',
            'parentfilters' => json_encode([]),
            'selectedrowkeys' => json_encode(['a']),
            'progresstotal' => 5,
            'progressdone' => 0,
            'lastdurationms' => 0,
            'avgdurationms' => 0,
            'zipdownloaded' => 0,
            'cancelrequested' => 0,
            'exported' => json_encode([]),
            'skipped' => json_encode([]),
            'zippath' => null,
            'zipfilename' => 'test.zip',
            'errormessage' => null,
            'timecreated' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
            'timeexpires' => $now + DAYSECS,
            'timelastprogress' => 0,
            'timezipdownloaded' => 0,
        ], $overrides);

        $record->id = $DB->insert_record(export_job::TABLE, $record);
        return $record;
    }

    /**
     * @return object Context with userid, parentreportid, courseid.
     */
    private function create_job_context(): object {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $reportid = $DB->insert_record('block_configurable_reports', (object) [
            'courseid' => $course->id,
            'ownerid' => $user->id,
            'visible' => 1,
            'name' => 'Chain parent',
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
        ]);

        return (object) [
            'userid' => $user->id,
            'parentreportid' => $reportid,
            'courseid' => $course->id,
        ];
    }

    /**
     * Queue position should be FIFO within a parent report.
     */
    public function test_queue_position_fifo(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $first = $this->insert_job(['timecreated' => 100], $context);
        $second = $this->insert_job(['timecreated' => 200, 'chainid' => 'chain2'], $context);

        $this->assertSame(1, export_job::get_queue_position($first));
        $this->assertSame(2, export_job::get_queue_position($second));
    }

    /**
     * Users should only cancel their own queued jobs.
     */
    public function test_cancel_queued_job(): void {
        $this->resetAfterTest();

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $job = $this->insert_job(['userid' => $owner->id]);

        $this->assertFalse(export_job::cancel((int) $job->id, (int) $other->id));
        $this->assertTrue(export_job::cancel((int) $job->id, (int) $owner->id));

        $updated = export_job::get((int) $job->id);
        $this->assertSame(export_job::STATUS_CANCELLED, $updated->status);
    }

    /**
     * User job listing should hide expired and downloaded archives.
     */
    public function test_get_user_jobs_for_report_filters(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $context = $this->create_job_context();
        $context->userid = $user->id;
        $active = $this->insert_job([
            'userid' => $user->id,
            'parentreportid' => $context->parentreportid,
            'courseid' => $context->courseid,
            'status' => export_job::STATUS_RUNNING,
        ], $context);
        $this->insert_job([
            'userid' => $user->id,
            'parentreportid' => $context->parentreportid,
            'courseid' => $context->courseid,
            'status' => export_job::STATUS_COMPLETED,
            'zipdownloaded' => 1,
            'timeexpires' => $now + DAYSECS,
        ], $context);
        $this->insert_job([
            'userid' => $user->id,
            'parentreportid' => $context->parentreportid,
            'courseid' => $context->courseid,
            'status' => export_job::STATUS_COMPLETED,
            'zipdownloaded' => 0,
            'timeexpires' => $now - 10,
        ], $context);

        $jobs = export_job::get_user_jobs_for_report((int) $user->id, (int) $context->parentreportid);
        $this->assertCount(1, $jobs);
        $this->assertSame((int) $active->id, (int) reset($jobs)->id);
    }

    /**
     * Stale running jobs should become interrupted.
     */
    public function test_detect_interrupted_jobs(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_RUNNING,
            'timestarted' => time() - 3600,
            'timelastprogress' => time() - 3600,
            'progressdone' => 2,
            'progresstotal' => 5,
        ], $context);

        export_job::detect_interrupted_jobs((int) $context->parentreportid);
        $updated = export_job::get((int) $job->id);
        $this->assertSame(export_job::STATUS_INTERRUPTED, $updated->status);
        $this->assertNotEmpty($updated->errormessage);
    }

    /**
     * delete_job should remove the database row.
     */
    public function test_delete_job(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job(['status' => export_job::STATUS_FAILED], $context);

        $this->assertTrue(export_job::delete_job((int) $job->id, (int) $context->userid));
        $this->assertNull(export_job::get((int) $job->id));
    }

    /**
     * Processed row keys should include exported and skipped entries.
     */
    public function test_get_processed_rowkeys(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'exported' => json_encode([['rowkey' => 'rk1', 'label' => 'A', 'filename' => 'a.csv']]),
            'skipped' => json_encode([['rowkey' => 'rk2', 'label' => 'B', 'reason' => 'skip']]),
        ], $context);

        $keys = export_job::get_processed_rowkeys($job);
        $this->assertArrayHasKey('rk1', $keys);
        $this->assertArrayHasKey('rk2', $keys);
    }

    /**
     * Interrupted jobs with remaining work should be resumable.
     */
    public function test_can_resume_interrupted_job(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_INTERRUPTED,
            'progressdone' => 2,
            'progresstotal' => 5,
        ], $context);

        $this->assertTrue(export_job::can_resume_export($job));
        $this->assertTrue(export_job::resume_job((int) $job->id, (int) $context->userid));
        $updated = export_job::get((int) $job->id);
        $this->assertSame(export_job::STATUS_QUEUED, $updated->status);
    }

    /**
     * delete_all should remove non-running jobs, reclaim orphaned running jobs, and report active running count.
     */
    public function test_delete_all_jobs_for_user_report(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $running = $this->insert_job([
            'status' => export_job::STATUS_RUNNING,
            'timestarted' => time(),
            'timelastprogress' => time(),
        ], $context);
        $failed = $this->insert_job(['status' => export_job::STATUS_FAILED, 'chainid' => 'chain2'], $context);
        $cancelled = $this->insert_job(['status' => export_job::STATUS_CANCELLED, 'chainid' => 'chain3'], $context);

        $result = export_job::delete_all_jobs_for_user_report((int) $context->userid, (int) $context->parentreportid);
        $this->assertSame(3, $result['deleted']);
        $this->assertSame(0, $result['skippedrunning']);
        $this->assertNull(export_job::get((int) $failed->id));
        $this->assertNull(export_job::get((int) $cancelled->id));
        $this->assertNull(export_job::get((int) $running->id));
    }

    /**
     * Orphaned running jobs without a worker lock should be interrupted.
     */
    public function test_detect_orphaned_running_job(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_RUNNING,
            'timestarted' => time(),
            'timelastprogress' => time(),
            'progressdone' => 1,
            'progresstotal' => 5,
        ], $context);

        $this->assertTrue(export_job::is_orphaned_running_job($job));
        export_job::detect_interrupted_jobs((int) $context->parentreportid);
        $updated = export_job::get((int) $job->id);
        $this->assertSame(export_job::STATUS_INTERRUPTED, $updated->status);
    }

    /**
     * Queued jobs should not show queue-blocked state when nothing is running.
     */
    public function test_build_status_payload_queue_not_blocked(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job(['status' => export_job::STATUS_QUEUED], $context);

        $payload = export_job::build_status_payload($job, (int) $context->userid);
        $this->assertFalse($payload['queueblocked']);
        $this->assertGreaterThan(0, $payload['queueposition']);
    }

    /**
     * Queued jobs should report queue-blocked when another export is running.
     */
    public function test_build_status_payload_queue_blocked(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $running = $this->insert_job([
            'status' => export_job::STATUS_RUNNING,
            'timestarted' => time(),
            'timelastprogress' => time(),
            'chainid' => 'chain-running',
        ], $context);

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_configurable_reports');
        $lock = $lockfactory->get_lock('chainjob_' . (int) $running->id, 0);
        $this->assertNotFalse($lock);

        try {
            $queued = $this->insert_job([
                'status' => export_job::STATUS_QUEUED,
                'chainid' => 'chain-queued',
                'timecreated' => time() + 1,
            ], $context);

            $payload = export_job::build_status_payload($queued, (int) $context->userid);
            $this->assertTrue($payload['queueblocked']);
        } finally {
            $lock->release();
        }
    }

    /**
     * resolve_return_url should accept local URLs and reject external ones.
     */
    public function test_resolve_return_url(): void {
        global $CFG;

        $this->resetAfterTest();
        $context = $this->create_job_context();
        $fallback = export_job::resolve_return_url(null, (int) $context->parentreportid, (int) $context->courseid);
        $this->assertStringContainsString('editcomp.php', $fallback->out(false));

        $local = $CFG->wwwroot . '/blocks/configurable_reports/editcomp.php?id=' . $context->parentreportid .
            '&comp=chains&courseid=' . $context->courseid;
        $resolved = export_job::resolve_return_url($local, (int) $context->parentreportid, (int) $context->courseid);
        $this->assertSame($local, $resolved->out(false));

        $external = export_job::resolve_return_url('https://evil.example.com/', (int) $context->parentreportid);
        $this->assertStringContainsString('editcomp.php', $external->out(false));
    }

    /**
     * After delete, return URL must not point back to the deleted job page.
     */
    public function test_resolve_return_url_after_job_delete(): void {
        global $CFG;

        $this->resetAfterTest();
        $context = $this->create_job_context();
        $job = $this->insert_job(['status' => export_job::STATUS_COMPLETED], $context);
        $progressurl = $CFG->wwwroot . '/blocks/configurable_reports/chainexport.php?id=' . $context->parentreportid .
            '&chainid=chain1&courseid=' . $context->courseid . '&jobid=' . $job->id;

        $redirect = export_job::resolve_return_url_after_job_delete(
            $progressurl,
            (int) $job->id,
            (int) $context->parentreportid,
            (int) $context->courseid,
            'chain1'
        );
        $this->assertStringNotContainsString('jobid=' . $job->id, $redirect->out(false));
        $this->assertStringContainsString('newexport=1', $redirect->out(false));
    }

    /**
     * Archives should be re-downloadable within grace period only.
     */
    public function test_redownload_grace_period(): void {
        $this->resetAfterTest();
        set_config('chainexportredownloadminutes', 15, 'block_configurable_reports');

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_COMPLETED,
            'exported' => json_encode([['label' => 'A', 'filename' => 'a.csv', 'rowkey' => 'rk1']]),
            'progressdone' => 1,
            'progresstotal' => 1,
            'zipdownloaded' => 1,
            'timezipdownloaded' => time(),
        ], $context);
        $zippath = export_job::job_zip_path((int) $job->id);
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('a.csv', 'a,b,c');
        $zip->close();
        $job->zippath = $zippath;
        $GLOBALS['DB']->update_record(export_job::TABLE, $job);
        $job = export_job::get((int) $job->id);

        $this->assertTrue(export_job::is_downloadable($job));

        $job->timezipdownloaded = time() - (20 * 60);
        $GLOBALS['DB']->update_record(export_job::TABLE, $job);
        $job = export_job::get((int) $job->id);
        $this->assertFalse(export_job::is_downloadable($job));
    }

    /**
     * Short grace periods (e.g. 1 minute) should be honoured.
     */
    public function test_redownload_grace_one_minute(): void {
        $this->resetAfterTest();
        set_config('chainexportredownloadminutes', 1, 'block_configurable_reports');
        $this->assertSame(60, export_job::get_redownload_grace_seconds());

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_COMPLETED,
            'exported' => json_encode([['label' => 'A', 'filename' => 'a.csv', 'rowkey' => 'rk1']]),
            'progressdone' => 1,
            'progresstotal' => 1,
            'zipdownloaded' => 1,
            'timezipdownloaded' => time() - 30,
        ], $context);
        $zippath = export_job::job_zip_path((int) $job->id);
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('a.csv', 'a,b,c');
        $zip->close();
        $job->zippath = $zippath;
        $GLOBALS['DB']->update_record(export_job::TABLE, $job);
        $job = export_job::get((int) $job->id);

        $this->assertTrue(export_job::is_within_redownload_grace($job));

        $job->timezipdownloaded = time() - 90;
        $GLOBALS['DB']->update_record(export_job::TABLE, $job);
        $job = export_job::get((int) $job->id);
        $this->assertFalse(export_job::is_within_redownload_grace($job));
    }

    /**
     * Status payload should include export statistics previews.
     */
    public function test_build_status_payload_includes_summary(): void {
        $this->resetAfterTest();

        $context = $this->create_job_context();
        $job = $this->insert_job([
            'status' => export_job::STATUS_FAILED,
            'exported' => json_encode([['label' => 'Row 1', 'filename' => 'r1.csv', 'rowkey' => 'rk1']]),
            'skipped' => json_encode([['label' => 'Row 2', 'reason' => 'No data', 'rowkey' => 'rk2']]),
            'progressdone' => 1,
            'progresstotal' => 2,
        ], $context);

        $payload = export_job::build_status_payload($job, (int) $context->userid);
        $this->assertSame(1, $payload['exportedcount']);
        $this->assertSame(1, $payload['skippedcount']);
        $this->assertSame('Row 2', $payload['skippedpreview'][0]['label']);
        $this->assertSame('No data', $payload['skippedpreview'][0]['reason']);
    }

    /**
     * Return URL should yield report context for idempotent delete redirects.
     */
    public function test_parse_return_url_context(): void {
        global $CFG;

        $this->resetAfterTest();
        $context = $this->create_job_context();
        $url = $CFG->wwwroot . '/blocks/configurable_reports/chainexport.php?id=' . $context->parentreportid .
            '&chainid=chain1&courseid=' . $context->courseid . '&jobid=42';

        $parsed = export_job::parse_return_url_context($url);
        $this->assertNotNull($parsed);
        $this->assertSame((int) $context->parentreportid, $parsed['reportid']);
        $this->assertSame((int) $context->courseid, $parsed['courseid']);
        $this->assertSame('chain1', $parsed['chainid']);
    }

    /**
     * Deleting an already removed job should succeed idempotently (e.g. duplicate tab).
     */
    public function test_delete_chain_export_idempotent_when_job_gone(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $context = $this->create_job_context();
        $job = $this->insert_job(['status' => export_job::STATUS_COMPLETED], $context);
        $this->getDataGenerator()->enrol_user($context->userid, $context->courseid, 'editingteacher');
        $this->setUser($DB->get_record('user', ['id' => $context->userid]));

        $this->assertTrue(export_job::delete_job((int) $job->id, (int) $context->userid));

        $returnurl = $CFG->wwwroot . '/blocks/configurable_reports/chainexport.php?id=' . $context->parentreportid .
            '&chainid=chain1&courseid=' . $context->courseid . '&jobid=' . $job->id;

        $result = external::delete_chain_export((int) $job->id, $returnurl);
        $this->assertTrue($result['deleted']);
        $this->assertNotEmpty($result['redirecturl']);
        $this->assertStringNotContainsString('jobid=' . $job->id, $result['redirecturl']);
    }
}
