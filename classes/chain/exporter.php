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
     * @param string $filename Archive entry filename.
     * @return string Temp file path.
     */
    public function export_child_report_to_tempfile(object $finalreport, string $format, string $filename): string {
        return report_exporter::write_to_tempfile($finalreport, $format, $filename);
    }

    /**
     * Create a ZIP archive from export files.
     *
     * @param array<int, array{name: string, path: string}> $files
     * @param string $zipfilename
     * @return string Path to zip file.
     */
    public function create_zip_archive(array $files, string $zipfilename): string {
        $tempdir = temp_file_cleanup::get_temp_directory();
        $zippath = $tempdir . '/' . uniqid('zip_', true) . '_' . clean_filename($zipfilename);

        $zip = new \zip_archive();
        if ($zip->open($zippath, \file_archive::CREATE) !== true) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }

        try {
            $usednames = [];
            foreach ($files as $file) {
                $entryname = $file['name'];
                if (isset($usednames[$entryname])) {
                    $usednames[$entryname]++;
                    $dot = strrpos($entryname, '.');
                    if ($dot !== false) {
                        $entryname = substr($entryname, 0, $dot) . '_' . $usednames[$entryname] . substr($entryname, $dot);
                    } else {
                        $entryname .= '_' . $usednames[$entryname];
                    }
                } else {
                    $usednames[$entryname] = 1;
                }

                if (!$zip->add_file_from_pathname($entryname, $file['path'])) {
                    throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
                }
            }
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            temp_file_cleanup::delete_file_if_exists($zippath);
            throw $e;
        } finally {
            temp_file_cleanup::cleanup_temp_files(array_column($files, 'path'));
        }

        return $zippath;
    }
}
