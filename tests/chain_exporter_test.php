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

use block_configurable_reports\chain\exporter;

/**
 * Tests for chain ZIP exporter.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\chain\exporter
 */
class chain_exporter_test extends \advanced_testcase {

    /**
     * Spreadsheet entries should use store compression.
     */
    public function test_compression_method_for_spreadsheets(): void {
        $this->assertSame(\ZipArchive::CM_STORE, exporter::compression_method_for_entry('grades.xlsx'));
        $this->assertSame(\ZipArchive::CM_STORE, exporter::compression_method_for_entry('report.xls'));
        $this->assertSame(\ZipArchive::CM_STORE, exporter::compression_method_for_entry('data.ods'));
    }

    /**
     * Text exports should use deflate compression.
     */
    public function test_compression_method_for_text_exports(): void {
        $this->assertSame(\ZipArchive::CM_DEFLATE, exporter::compression_method_for_entry('grades.csv'));
        $this->assertSame(\ZipArchive::CM_DEFLATE, exporter::compression_method_for_entry('report.json'));
        $this->assertSame(\ZipArchive::CM_DEFLATE, exporter::compression_method_for_entry('legacy.slk'));
    }
}
