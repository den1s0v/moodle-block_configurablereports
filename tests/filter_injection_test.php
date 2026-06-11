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

use block_configurable_reports\chain\filter_injection;

/**
 * Tests for filter injection scope.
 *
 * @package   block_configurable_reports
 * @covers    \block_configurable_reports\chain\filter_injection
 */
class filter_injection_test extends \advanced_testcase {

    /**
     * Injected values should be readable and clearable per report.
     */
    public function test_injection_scope_per_report(): void {
        filter_injection::clear_all();
        filter_injection::set(10, ['filter_courses' => '42']);
        $this->assertTrue(filter_injection::has(10, 'filter_courses'));
        $this->assertSame('42', filter_injection::get(10, 'filter_courses'));
        $this->assertFalse(filter_injection::has(11, 'filter_courses'));
        filter_injection::clear(10);
        $this->assertFalse(filter_injection::has(10, 'filter_courses'));
    }
}
