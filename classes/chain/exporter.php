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

use block_configurable_reports\export\report_exporter;

/**
 * Chain ZIP exporter.
 *
 * @package   block_configurable_reports
 */
class exporter {

    /**
     * Export a child final report to a temp file.
     *
     * @param object $finalreport
     * @param string $format
     * @param string $filename
     * @return string
     */
    public function export_child_report_to_tempfile(object $finalreport, string $format, string $filename): string {
        return report_exporter::write_to_tempfile($finalreport, $format, $filename);
    }

    /**
     * Create a ZIP archive from export files.
     *
     * @param array<int, string> $files
     * @param string $zipfilename
     * @return string Path to zip file.
     */
    public function create_zip_archive(array $files, string $zipfilename): string {
        $tempdir = make_temp_directory('block_configurable_reports/chainexport');
        $zippath = $tempdir . '/' . $zipfilename;

        $zip = new \zip_archive();
        if ($zip->open($zippath, \file_archive::CREATE) !== true) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }

        foreach ($files as $filepath) {
            if (!$zip->add_file_from_pathname(basename($filepath), $filepath)) {
                $zip->close();
                throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
            }
        }
        $zip->close();

        foreach ($files as $filepath) {
            @unlink($filepath);
        }

        return $zippath;
    }
}
