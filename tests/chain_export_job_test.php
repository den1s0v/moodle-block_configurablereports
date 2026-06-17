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
}
