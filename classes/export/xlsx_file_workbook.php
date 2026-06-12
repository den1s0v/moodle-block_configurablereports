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
require_once($CFG->dirroot . '/lib/excellib.class.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Excel workbook that can be saved to a filesystem path.
 *
 * MoodleExcelWorkbook::close() always streams to the browser.
 *
 * @package   block_configurable_reports
 */
class xlsx_file_workbook extends \MoodleExcelWorkbook {

    /**
     * Save workbook to a file without sending HTTP headers.
     *
     * @param string $filepath Absolute path to the output file.
     * @return void
     */
    public function save_to_path(string $filepath): void {
        foreach ($this->objspreadsheet->getAllSheets() as $sheet) {
            $sheet->setSelectedCells('A1');
        }
        $this->objspreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($this->objspreadsheet, $this->type);
        $writer->save($filepath);
    }
}
