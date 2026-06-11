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

require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Chain configuration helpers and validation.
 *
 * @package   block_configurable_reports
 */
class definition {

    /**
     * Get chain elements from a parent report configuration.
     *
     * @param object $report Parent report record.
     * @return array<int, array<string, mixed>>
     */
    public static function get_chain_elements(object $report): array {
        $components = cr_unserialize($report->components);
        $elements = $components['chains']['elements'] ?? [];
        return is_array($elements) ? $elements : [];
    }

    /**
     * Get active (enabled) chain elements.
     *
     * @param object $report
     * @return array<int, array<string, mixed>>
     */
    public static function get_active_chain_elements(object $report): array {
        $active = [];
        foreach (self::get_chain_elements($report) as $element) {
            $formdata = (object) ($element['formdata'] ?? new \stdClass());
            if (isset($formdata->enabled) && empty($formdata->enabled)) {
                continue;
            }
            if (!self::validate_element($report, $element)->valid) {
                continue;
            }
            $active[] = $element;
        }
        return $active;
    }

    /**
     * Whether the report has at least one active chain.
     *
     * @param object $report
     * @return bool
     */
    public static function report_has_active_chains(object $report): bool {
        return !empty(self::get_active_chain_elements($report));
    }

    /**
     * Find a chain element by its element id.
     *
     * @param object $report
     * @param string $chainid
     * @return array<string, mixed>|null
     */
    public static function get_chain_element_by_id(object $report, string $chainid): ?array {
        foreach (self::get_chain_elements($report) as $element) {
            if (($element['id'] ?? '') === $chainid) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Normalise chain form data.
     *
     * @param object $formdata
     * @return object
     */
    public static function normalise_formdata(object $formdata): object {
        $normalised = clone $formdata;

        if (isset($normalised->rowkeycolumns) && is_string($normalised->rowkeycolumns)) {
            $normalised->rowkeycolumns = array_values(array_filter(array_map('trim', explode(',', $normalised->rowkeycolumns))));
        }
        if (!isset($normalised->rowkeycolumns) || !is_array($normalised->rowkeycolumns)) {
            $normalised->rowkeycolumns = [];
        }

        if (!isset($normalised->mappings) || !is_array($normalised->mappings)) {
            $normalised->mappings = [];
        }

        $mappings = [];
        foreach ($normalised->mappings as $mapping) {
            $mapping = (object) $mapping;
            $source = trim((string) ($mapping->sourcecolumn ?? ''));
            $target = trim((string) ($mapping->targetfilter ?? ''));
            if ($source === '' || $target === '') {
                continue;
            }
            $mappings[] = (object) [
                'sourcecolumn' => $source,
                'targetfilter' => $target,
            ];
        }
        $normalised->mappings = $mappings;

        if (empty($normalised->filenamepattern)) {
            $normalised->filenamepattern = '##reportname##_##row##';
        }

        if (!isset($normalised->enabled)) {
            $normalised->enabled = 1;
        }

        return $normalised;
    }

    /**
     * Validation result object.
     *
     * @param bool $valid
     * @param string $error
     * @return \stdClass
     */
    private static function result(bool $valid, string $error = ''): \stdClass {
        $result = new \stdClass();
        $result->valid = $valid;
        $result->error = $error;
        return $result;
    }

    /**
     * Validate a single chain element (parent -> child only).
     *
     * @param object $parentreport
     * @param array<string, mixed> $element
     * @return \stdClass
     */
    public static function validate_element(object $parentreport, array $element): \stdClass {
        global $DB;

        $formdata = self::normalise_formdata((object) ($element['formdata'] ?? new \stdClass()));

        if (empty($formdata->childreportid)) {
            return self::result(false, get_string('chainerror_nochild', 'block_configurable_reports'));
        }

        if ((int) $formdata->childreportid === (int) $parentreport->id) {
            return self::result(false, get_string('chainerror_selfreference', 'block_configurable_reports'));
        }

        $child = $DB->get_record('block_configurable_reports', ['id' => (int) $formdata->childreportid]);
        if (!$child) {
            return self::result(false, get_string('chainerror_childmissing', 'block_configurable_reports'));
        }

        if (empty($formdata->mappings)) {
            return self::result(false, get_string('chainerror_nomappings', 'block_configurable_reports'));
        }

        if (empty($formdata->rowkeycolumns)) {
            return self::result(false, get_string('chainerror_norowkeys', 'block_configurable_reports'));
        }

        // Depth-2 only: child must not define its own active chains pointing back.
        if (self::child_references_parent($child, (int) $parentreport->id)) {
            return self::result(false, get_string('chainerror_cycle', 'block_configurable_reports'));
        }

        if (empty(self::get_allowed_export_formats($child))) {
            return self::result(false, get_string('chainerror_noexport', 'block_configurable_reports'));
        }

        return self::result(true);
    }

    /**
     * Whether child report chains back to the given parent id.
     *
     * @param object $childreport
     * @param int $parentid
     * @return bool
     */
    public static function child_references_parent(object $childreport, int $parentid): bool {
        foreach (self::get_chain_elements($childreport) as $element) {
            $formdata = (object) ($element['formdata'] ?? new \stdClass());
            if ((int) ($formdata->childreportid ?? 0) === $parentid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Export formats allowed on the child report.
     *
     * @param object $childreport
     * @return array<string, string> format => label
     */
    public static function get_allowed_export_formats(object $childreport): array {
        global $CFG;

        $formats = [];
        if (empty($childreport->export)) {
            return $formats;
        }

        $exportplugins = cr_get_export_plugins();
        foreach (explode(',', $childreport->export) as $format) {
            $format = trim($format);
            if ($format === '' || !isset($exportplugins[$format])) {
                continue;
            }
            if (!file_exists($CFG->dirroot . '/blocks/configurable_reports/export/' . $format . '/export.php')) {
                continue;
            }
            $formats[$format] = strtoupper($format);
        }
        return $formats;
    }

    /**
     * Build a stable row key hash from column values.
     *
     * @param array<int, string> $keyvalues
     * @return string
     */
    public static function build_row_key_hash(array $keyvalues): string {
        return sha1(json_encode(array_values($keyvalues)));
    }

    /**
     * Extract row key values from a parent table row.
     *
     * @param object $table Parent report table object with head/data.
     * @param int $rowindex
     * @param array<int, string> $rowkeycolumns
     * @return array<int, string>
     */
    public static function extract_row_key_values(object $table, int $rowindex, array $rowkeycolumns): array {
        $values = [];
        $row = $table->data[$rowindex] ?? [];
        foreach ($rowkeycolumns as $columnname) {
            $colindex = array_search($columnname, $table->head, true);
            if ($colindex === false) {
                $values[] = '';
                continue;
            }
            $cell = $row[$colindex] ?? '';
            $values[] = trim(strip_tags((string) $cell));
        }
        return $values;
    }

    /**
     * Map parent row values to child filter parameters.
     *
     * @param object $table
     * @param int $rowindex
     * @param object $formdata Normalised chain form data.
     * @return array<string, mixed>
     */
    public static function build_filter_params_for_row(object $table, int $rowindex, object $formdata): array {
        $params = [];
        $row = $table->data[$rowindex] ?? [];

        foreach ($formdata->mappings as $mapping) {
            $colindex = array_search($mapping->sourcecolumn, $table->head, true);
            $value = '';
            if ($colindex !== false) {
                $value = strip_tags((string) ($row[$colindex] ?? ''));
                $value = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            }
            $params[$mapping->targetfilter] = $value;
        }

        return $params;
    }

    /**
     * Build a safe export filename from pattern and row context.
     *
     * @param object $childreport
     * @param object $formdata
     * @param array<string, string> $placeholders
     * @param string $format
     * @return string
     */
    public static function build_filename(
        object $childreport,
        object $formdata,
        array $placeholders,
        string $format
    ): string {
        $pattern = $formdata->filenamepattern ?? '##reportname##_##row##';
        $replacements = array_merge([
            'reportname' => format_string($childreport->name),
            'row' => '1',
        ], $placeholders);

        $filename = $pattern;
        foreach ($replacements as $key => $value) {
            $filename = str_replace('##' . $key . '##', $value, $filename);
        }

        $filename = preg_replace('/##[^#]+##/', '', $filename);
        $filename = clean_filename($filename);
        if ($filename === '') {
            $filename = 'report';
        }

        $extensions = [
            'csv' => 'csv',
            'xls' => 'xls',
            'ods' => 'ods',
            'slk' => 'slk',
            'json' => 'json',
        ];
        $ext = $extensions[$format] ?? $format;

        if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $filename)) {
            $filename .= '.' . $ext;
        }

        return $filename;
    }

    /**
     * Maximum rows allowed for chain export.
     *
     * @return int
     */
    public static function get_max_export_rows(): int {
        $limit = (int) get_config('block_configurable_reports', 'chainmaxrows');
        return $limit > 0 ? $limit : 100;
    }
}
