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

/**
 * Result of a chain bulk export run.
 *
 * @package   block_configurable_reports
 */
class export_result {

    /** @var string|null Path to ZIP archive when at least one file was exported. */
    public ?string $zippath = null;

    /** @var string Suggested ZIP download filename. */
    public string $zipfilename = 'chainexport.zip';

    /** @var array<int, object> Exported items with label and filename. */
    public array $exported = [];

    /** @var array<int, object> Skipped items with label and reason. */
    public array $skipped = [];

    /**
     * Whether any file was exported.
     *
     * @return bool
     */
    public function has_exports(): bool {
        return !empty($this->exported) && $this->zippath !== null;
    }
}
