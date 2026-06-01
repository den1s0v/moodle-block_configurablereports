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
 * EXPLAIN validation is not available for the current database family.
 *
 * @package   block_configurable_reports
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\exceptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Thrown when EXPLAIN cannot be built for the active database driver family.
 */
class explain_unsupported_exception extends \Exception {

    /**
     * @param string $dbfamily Value from {@see \moodle_database::get_dbfamily()}.
     */
    public function __construct(string $dbfamily) {
        parent::__construct('EXPLAIN not supported for database family: ' . $dbfamily);
    }
}
