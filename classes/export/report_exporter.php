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

/**
 * Reusable report export helpers.
 *
 * @package   block_configurable_reports
 */
class report_exporter {

    /**
     * Write report data to a temporary file and return its path.
     *
     * @param object $finalreport
     * @param string $format
     * @param string $filename Suggested filename without path.
     * @return string Absolute path to temp file.
     */
    public static function write_to_tempfile(object $finalreport, string $format, string $filename): string {
        global $CFG;

        $exportplugin = $CFG->dirroot . '/blocks/configurable_reports/export/' . $format . '/export.php';
        if (!file_exists($exportplugin)) {
            throw new \moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }

        require_once($exportplugin);

        $tempdir = make_temp_directory('block_configurable_reports/chainexport');
        $filepath = $tempdir . '/' . clean_filename($filename);

        if (!function_exists('export_report_to_path')) {
            throw new \moodle_exception('chainerror_exportformat', 'block_configurable_reports');
        }

        if (in_array($format, ['xls', 'ods'], true)) {
            export_report_to_path($finalreport, $filepath, clean_filename($filename));
        } else {
            export_report_to_path($finalreport, $filepath);
        }
        return $filepath;
    }

    /**
     * Send report to browser using existing export plugins.
     *
     * @param object $finalreport
     * @param string $format
     * @return void
     */
    public static function send_download(object $finalreport, string $format): void {
        global $CFG;

        $exportplugin = $CFG->dirroot . '/blocks/configurable_reports/export/' . $format . '/export.php';
        require_once($exportplugin);
        export_report($finalreport);
    }
}
