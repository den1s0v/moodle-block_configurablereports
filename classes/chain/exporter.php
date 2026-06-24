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

    /** @var \ZipArchive|null */
    private ?\ZipArchive $zip = null;

    /** @var string */
    private string $zippath = '';

    /** @var array<string, int> */
    private array $usednames = [];

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
     * Open a ZIP archive for incremental writes.
     *
     * @param string $zipfilename Download filename (not used in the on-disk path).
     * @param int|null $jobid When set, use a short canonical path for background jobs.
     * @param bool $resume When true, append to an existing job ZIP if valid.
     * @return string Path to the zip file.
     */
    public function open_zip_archive(string $zipfilename, ?int $jobid = null, bool $resume = false): string {
        if ($this->zip !== null) {
            throw new \coding_exception('ZIP archive already open');
        }

        $tempdir = temp_file_cleanup::get_temp_directory();
        if ($jobid !== null) {
            $this->zippath = export_job::job_zip_path($jobid);
            $this->usednames = [];
            if ($resume && export_job::is_valid_zip_file($this->zippath)) {
                $existing = new \ZipArchive();
                if ($existing->open($this->zippath) === true) {
                    for ($i = 0; $i < $existing->numFiles; $i++) {
                        $name = $existing->getNameIndex($i);
                        if ($name !== false && $name !== '') {
                            $this->usednames[$name] = 1;
                        }
                    }
                    $existing->close();
                }
            } else if (is_file($this->zippath)) {
                @unlink($this->zippath);
            }
        } else {
            $this->zippath = $tempdir . '/' . uniqid('zip_', true) . '.zip';
            $this->usednames = [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($this->zippath, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }
        $this->zip = $zip;
        return $this->zippath;
    }

    /**
     * Add a file to the open ZIP archive.
     *
     * @param string $entryname
     * @param string $filepath
     * @return void
     */
    public function add_file_to_zip(string $entryname, string $filepath): void {
        if ($this->zip === null) {
            throw new \coding_exception('ZIP archive is not open');
        }

        $entryname = $this->unique_entry_name($entryname);
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }
        $contents = file_get_contents($filepath);
        if ($contents === false) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }
        if (!$this->zip->addFromString($entryname, $contents)) {
            throw new \moodle_exception('chainerror_zip', 'block_configurable_reports');
        }

        $index = $this->zip->numFiles - 1;
        $method = self::compression_method_for_entry($entryname);
        if (method_exists($this->zip, 'setCompressionIndex')) {
            $this->zip->setCompressionIndex($index, $method);
        } else if ($method === \ZipArchive::CM_STORE && method_exists($this->zip, 'setCompressionName')) {
            $this->zip->setCompressionName($entryname, \ZipArchive::CM_STORE);
        }
    }

    /**
     * Close the open ZIP archive.
     *
     * @return string Path to the zip file.
     */
    public function close_zip_archive(): string {
        if ($this->zip === null) {
            throw new \coding_exception('ZIP archive is not open');
        }
        $this->zip->close();
        $this->zip = null;
        return $this->zippath;
    }

    /**
     * Whether a ZIP archive is currently open.
     *
     * @return bool
     */
    public function is_zip_open(): bool {
        return $this->zip !== null;
    }

    /**
     * Create a ZIP archive from export files (one-shot).
     *
     * @param array<int, array{name: string, path: string}> $files
     * @param string $zipfilename
     * @return string Path to zip file.
     */
    public function create_zip_archive(array $files, string $zipfilename): string {
        $this->open_zip_archive($zipfilename);
        try {
            foreach ($files as $file) {
                $this->add_file_to_zip($file['name'], $file['path']);
            }
            return $this->close_zip_archive();
        } catch (\Throwable $e) {
            if ($this->zip !== null) {
                $this->zip->close();
                $this->zip = null;
            }
            temp_file_cleanup::delete_file_if_exists($this->zippath);
            throw $e;
        } finally {
            temp_file_cleanup::cleanup_temp_files(array_column($files, 'path'));
        }
    }

    /**
     * Compression method for a zip entry based on file extension.
     *
     * @param string $entryname
     * @return int ZipArchive::CM_* constant.
     */
    public static function compression_method_for_entry(string $entryname): int {
        if (preg_match('/\.(xlsx?|ods)$/i', $entryname)) {
            return \ZipArchive::CM_STORE;
        }
        return \ZipArchive::CM_DEFLATE;
    }

    /**
     * Ensure unique entry names inside the archive.
     *
     * @param string $entryname
     * @return string
     */
    private function unique_entry_name(string $entryname): string {
        if (!isset($this->usednames[$entryname])) {
            $this->usednames[$entryname] = 1;
            return $entryname;
        }
        $this->usednames[$entryname]++;
        $dot = strrpos($entryname, '.');
        if ($dot !== false) {
            return substr($entryname, 0, $dot) . '_' . $this->usednames[$entryname] . substr($entryname, $dot);
        }
        return $entryname . '_' . $this->usednames[$entryname];
    }
}
