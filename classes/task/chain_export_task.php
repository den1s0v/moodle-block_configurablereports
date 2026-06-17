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

namespace block_configurable_reports\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task for chain bulk export jobs.
 *
 * @package   block_configurable_reports
 */
class chain_export_task extends \core\task\adhoc_task {

    /**
     * Execute the export job.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $jobid = (int) ($data->jobid ?? 0);
        if ($jobid <= 0) {
            return;
        }
        \block_configurable_reports\chain\export_job::process_job($jobid);
    }
}
