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
 * Synchronous cleanup of chain export temp files.
 *
 * @package   block_configurable_reports
 */
class temp_file_cleanup {

    /** @var string Relative temp directory name under $CFG->tempdir. */
    public const TEMPDIR = 'block_configurable_reports/chainexport';

    /**
     * Absolute path to the chain export temp directory.
     *
     * @return string
     */
    public static function get_temp_directory(): string {
        return make_temp_directory(self::TEMPDIR);
    }

    /**
     * Minimum age before a temp file may be deleted (session timeout, at least one hour).
     *
     * @return int Seconds.
     */
    public static function get_max_age_seconds(): int {
        global $CFG;
        $sessiontimeout = !empty($CFG->sessiontimeout) ? (int) $CFG->sessiontimeout : (8 * HOURSECS);
        return max($sessiontimeout, HOURSECS);
    }

    /**
     * Delete files in the chain export temp directory older than the retention period.
     *
     * Recent files are kept so parallel exports from other users are not affected.
     *
     * @param array<int, string> $excludepaths Absolute paths that must not be deleted.
     * @return void
     */
    public static function cleanup_stale_files(array $excludepaths = []): void {
        $dir = self::get_temp_directory();
        if (!is_dir($dir)) {
            return;
        }

        $excluded = [];
        foreach ($excludepaths as $path) {
            if ($path && is_file($path)) {
                $realpath = realpath($path);
                if ($realpath !== false) {
                    $excluded[$realpath] = true;
                }
            }
        }

        $cutoff = time() - self::get_max_age_seconds();
        $iterator = new \DirectoryIterator($dir);
        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isFile()) {
                continue;
            }
            $path = $item->getRealPath();
            if ($path === false || isset($excluded[$path])) {
                continue;
            }
            if ($item->getMTime() < $cutoff) {
                @unlink($path);
            }
        }
    }

    /**
     * Delete a single temp file when it is no longer needed.
     *
     * @param string|null $path
     * @return void
     */
    public static function delete_file_if_exists(?string $path): void {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Delete multiple temp files.
     *
     * @param array<int, string> $paths
     * @return void
     */
    public static function cleanup_temp_files(array $paths): void {
        foreach ($paths as $path) {
            self::delete_file_if_exists($path);
        }
    }
}
