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

namespace block_configurable_reports\export;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/odslib.class.php');

/**
 * ODS workbook that can be saved to a filesystem path.
 *
 * MoodleODSWorkbook::close() always streams to the browser.
 *
 * @package   block_configurable_reports
 */
class ods_file_workbook extends \MoodleODSWorkbook {

    /**
     * Save workbook to a file without sending HTTP headers.
     *
     * @param string $filepath Absolute path to the output file.
     * @return void
     */
    public function save_to_path(string $filepath): void {
        $writer = new \MoodleODSWriter($this->worksheets);
        $contents = $writer->get_file_content();
        if (file_put_contents($filepath, $contents) === false) {
            throw new \moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }
    }
}
