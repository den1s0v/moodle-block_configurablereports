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
require_once($CFG->dirroot . '/blocks/configurable_reports/classes/chain/filter_params.php');

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

        $normalised->filterbindings = self::normalise_filterbindings($normalised);
        unset($normalised->mappings);

        if (empty($normalised->filenamepattern)) {
            $normalised->filenamepattern = '##reportname##_##row##';
        }

        $normalised->zipfilenamepattern = trim((string) ($normalised->zipfilenamepattern ?? ''));

        if (!isset($normalised->enabled)) {
            $normalised->enabled = 1;
        }

        $normalised->chainname = trim((string) ($normalised->chainname ?? ''));

        return $normalised;
    }

    /**
     * Draft chain form values preserved across child-report reloads.
     *
     * @return \stdClass
     */
    public static function read_form_draft_from_request(): \stdClass {
        $draft = new \stdClass();

        if (optional_param('draft_chainname', null, PARAM_TEXT) !== null) {
            $draft->chainname = optional_param('draft_chainname', '', PARAM_TEXT);
        }
        if (($enabled = optional_param('draft_enabled', -1, PARAM_INT)) >= 0) {
            $draft->enabled = $enabled;
        }
        if (optional_param('draft_filenamepattern', null, PARAM_RAW) !== null) {
            $draft->filenamepattern = optional_param('draft_filenamepattern', '', PARAM_RAW);
        }

        return $draft;
    }

    /**
     * URL query parameters for chain form draft state.
     *
     * @return array<string, string|int>
     */
    public static function get_form_draft_url_params(): array {
        $params = [];
        $draft = self::read_form_draft_from_request();

        if (property_exists($draft, 'chainname') && $draft->chainname !== '') {
            $params['draft_chainname'] = $draft->chainname;
        }
        if (property_exists($draft, 'enabled')) {
            $params['draft_enabled'] = (int) $draft->enabled;
        }
        if (property_exists($draft, 'filenamepattern') && $draft->filenamepattern !== '') {
            $params['draft_filenamepattern'] = $draft->filenamepattern;
        }

        return $params;
    }

    /**
     * Merge draft request values into form prefill data.
     *
     * @param object $prefill
     * @param int $childreportid
     * @return object
     */
    public static function apply_form_draft_to_prefill(object $prefill, int $childreportid): object {
        global $DB;

        $draft = self::read_form_draft_from_request();
        if (property_exists($draft, 'chainname')) {
            $prefill->chainname = $draft->chainname;
        } else if (empty($prefill->chainname) && $childreportid > 0) {
            $child = $DB->get_record('block_configurable_reports', ['id' => $childreportid], 'name', IGNORE_MISSING);
            if ($child) {
                $prefill->chainname = $child->name;
            }
        }
        if (property_exists($draft, 'enabled')) {
            $prefill->enabled = $draft->enabled;
        }
        if (property_exists($draft, 'filenamepattern')) {
            $prefill->filenamepattern = $draft->filenamepattern;
        }

        return $prefill;
    }

    /**
     * Human-readable chain name for UI lists.
     *
     * @param array<string, mixed> $element
     * @param object|null $childreport Optional preloaded child report record.
     * @return string
     */
    public static function get_chain_display_name(array $element, ?object $childreport = null): string {
        global $DB;

        $formdata = self::normalise_formdata((object) ($element['formdata'] ?? new \stdClass()));
        if ($formdata->chainname !== '') {
            return format_string($formdata->chainname);
        }

        if ($childreport === null && !empty($formdata->childreportid)) {
            $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $formdata->childreportid],
                'id,name', IGNORE_MISSING);
        }
        if ($childreport) {
            return format_string($childreport->name);
        }

        return get_string('reportchain', 'block_configurable_reports');
    }

    /**
     * Label for chain selection lists: chain name and child report name.
     *
     * @param array<string, mixed> $element
     * @param object|null $childreport
     * @return string
     */
    public static function get_chain_list_label(array $element, ?object $childreport = null): string {
        global $DB;

        $formdata = self::normalise_formdata((object) ($element['formdata'] ?? new \stdClass()));
        $chainname = self::get_chain_display_name($element, $childreport);

        if ($childreport === null && !empty($formdata->childreportid)) {
            $childreport = $DB->get_record('block_configurable_reports', ['id' => (int) $formdata->childreportid],
                'id,name', IGNORE_MISSING);
        }

        $childname = $childreport
            ? format_string($childreport->name)
            : get_string('reportchain_summary_missing', 'block_configurable_reports');

        $a = (object) [
            'chain' => $chainname,
            'child' => $childname,
        ];
        return get_string('chainexportlistlabel', 'block_configurable_reports', $a);
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

        $childfilters = filter_params::get_child_filter_options($child);
        if (!empty($childfilters)) {
            $bindingcheck = self::validate_filterbindings($child, $formdata);
            if (!$bindingcheck->valid) {
                return $bindingcheck;
            }
        }

        if (empty($formdata->rowkeycolumns)) {
            return self::result(false, get_string('chainerror_norowkeys', 'block_configurable_reports'));
        }

        $columncheck = self::validate_source_columns($parentreport, $formdata);
        if (!$columncheck->valid) {
            return $columncheck;
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
     * Validate chain column names against cached SQL output metadata.
     *
     * @param object $parentreport
     * @param object $formdata Normalised chain form data.
     * @return \stdClass
     */
    public static function validate_source_columns(object $parentreport, object $formdata): \stdClass {
        $metadata = \block_configurable_reports\report\output_columns::get_column_names($parentreport);
        if (empty($metadata)) {
            return self::result(true);
        }

        foreach ($formdata->rowkeycolumns as $column) {
            if (!in_array($column, $metadata, true)) {
                return self::result(false, get_string('chainerror_unknowncolumn', 'block_configurable_reports', $column));
            }
        }

        foreach ($formdata->filterbindings as $binding) {
            if (($binding->mode ?? '') !== filter_params::MODE_COLUMN) {
                continue;
            }
            $source = trim((string) ($binding->sourcecolumn ?? ''));
            if ($source !== '' && !in_array($source, $metadata, true)) {
                return self::result(false, get_string('chainerror_unknowncolumn', 'block_configurable_reports', $source));
            }
        }

        return self::result(true);
    }

    /**
     * Normalise filter binding records and migrate legacy mappings.
     *
     * @param object $formdata
     * @return array<int, object>
     */
    public static function normalise_filterbindings(object $formdata): array {
        $bindings = [];

        if (!empty($formdata->filterbindings) && is_array($formdata->filterbindings)) {
            foreach ($formdata->filterbindings as $binding) {
                $binding = (object) $binding;
                $target = trim((string) ($binding->targetfilter ?? ''));
                if ($target === '') {
                    continue;
                }
                $mode = trim((string) ($binding->mode ?? filter_params::MODE_EMPTY));
                if (!filter_params::is_valid_mode($mode)) {
                    $mode = filter_params::MODE_EMPTY;
                }
                $bindings[] = (object) [
                    'targetfilter' => $target,
                    'mode' => $mode,
                    'sourcecolumn' => trim((string) ($binding->sourcecolumn ?? '')),
                    'constantvalue' => (string) ($binding->constantvalue ?? ''),
                ];
            }
            return $bindings;
        }

        if (!empty($formdata->mappings) && is_array($formdata->mappings)) {
            foreach ($formdata->mappings as $mapping) {
                $mapping = (object) $mapping;
                $source = trim((string) ($mapping->sourcecolumn ?? ''));
                $target = trim((string) ($mapping->targetfilter ?? ''));
                if ($source === '' || $target === '') {
                    continue;
                }
                $bindings[] = (object) [
                    'targetfilter' => $target,
                    'mode' => filter_params::MODE_COLUMN,
                    'sourcecolumn' => $source,
                    'constantvalue' => '',
                ];
            }
        }

        return $bindings;
    }

    /**
     * Human-readable summary line for one filter binding, or null if inactive.
     *
     * @param object $binding
     * @param array<string, string> $filterlabels
     * @return string|null
     */
    public static function format_filter_binding_summary(object $binding, array $filterlabels): ?string {
        $target = trim((string) ($binding->targetfilter ?? ''));
        if ($target === '') {
            return null;
        }
        $targetlabel = $filterlabels[$target] ?? $target;
        $mode = $binding->mode ?? filter_params::MODE_EMPTY;
        switch ($mode) {
            case filter_params::MODE_COLUMN:
                $source = trim((string) ($binding->sourcecolumn ?? ''));
                if ($source === '') {
                    return null;
                }
                return s($source) . ' → ' . s($targetlabel);
            case filter_params::MODE_CONSTANT:
                $constant = trim((string) ($binding->constantvalue ?? ''));
                if ($constant === '') {
                    return null;
                }
                return "'" . s($constant) . "' → " . s($targetlabel);
            case filter_params::MODE_EMPTY:
            default:
                return null;
        }
    }

    /**
     * Validate filter bindings against the child report filter set.
     *
     * @param object $childreport
     * @param object $formdata Normalised chain form data.
     * @return \stdClass
     */
    public static function validate_filterbindings(object $childreport, object $formdata): \stdClass {
        $expected = filter_params::get_child_filter_options($childreport);
        if (empty($expected)) {
            return self::result(true);
        }

        $labels = filter_params::get_child_filter_labels($childreport);
        $bytarget = [];
        foreach ($formdata->filterbindings as $binding) {
            $target = trim((string) ($binding->targetfilter ?? ''));
            if ($target === '') {
                continue;
            }
            $bytarget[$target] = $binding;
        }

        foreach (array_keys($expected) as $paramname) {
            if (!isset($bytarget[$paramname])) {
                $label = $labels[$paramname] ?? $paramname;
                return self::result(false, get_string('chainerror_missingfilterbinding', 'block_configurable_reports',
                    $label));
            }
            $binding = $bytarget[$paramname];
            $mode = $binding->mode ?? '';
            $label = $labels[$paramname] ?? $paramname;
            if ($mode === filter_params::MODE_COLUMN) {
                if (trim((string) ($binding->sourcecolumn ?? '')) === '') {
                    return self::result(false, get_string('chainerror_nocolumnsource', 'block_configurable_reports',
                        $label));
                }
            } else if ($mode === filter_params::MODE_CONSTANT) {
                if (trim((string) ($binding->constantvalue ?? '')) === '') {
                    return self::result(false, get_string('chainerror_noconstantvalue', 'block_configurable_reports',
                        $label));
                }
            } else if ($mode !== filter_params::MODE_EMPTY) {
                return self::result(false, get_string('chainerror_invalidfiltermode', 'block_configurable_reports',
                    $label));
            }
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
     * Map parent row values to child filter parameters via explicit bindings.
     *
     * @param object $table
     * @param int $rowindex
     * @param object $formdata Normalised chain form data.
     * @return array<string, mixed>
     */
    public static function build_child_filter_params_for_row(object $table, int $rowindex, object $formdata): array {
        $params = [];
        $row = $table->data[$rowindex] ?? [];

        foreach ($formdata->filterbindings as $binding) {
            $target = trim((string) ($binding->targetfilter ?? ''));
            if ($target === '') {
                continue;
            }
            switch ($binding->mode ?? filter_params::MODE_EMPTY) {
                case filter_params::MODE_COLUMN:
                    $colindex = array_search($binding->sourcecolumn, $table->head, true);
                    $value = '';
                    if ($colindex !== false) {
                        $value = strip_tags((string) ($row[$colindex] ?? ''));
                        $value = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    }
                    $params[$target] = $value;
                    break;
                case filter_params::MODE_CONSTANT:
                    $params[$target] = (string) ($binding->constantvalue ?? '');
                    break;
                case filter_params::MODE_EMPTY:
                default:
                    $params[$target] = '';
                    break;
            }
        }

        return $params;
    }

    /**
     * @deprecated Use build_child_filter_params_for_row().
     * @param object $table
     * @param int $rowindex
     * @param object $formdata
     * @return array<string, mixed>
     */
    public static function build_filter_params_for_row(object $table, int $rowindex, object $formdata): array {
        return self::build_child_filter_params_for_row($table, $rowindex, $formdata);
    }

    /**
     * Hash of column-binding values for a parent row (deduplication key).
     *
     * @param object $table
     * @param int $rowindex
     * @param object $formdata Normalised chain form data.
     * @return string
     */
    public static function build_column_mapping_hash(object $table, int $rowindex, object $formdata): string {
        $parts = [];
        foreach ($formdata->filterbindings as $binding) {
            if (($binding->mode ?? '') !== filter_params::MODE_COLUMN) {
                continue;
            }
            $target = trim((string) ($binding->targetfilter ?? ''));
            $params = self::build_child_filter_params_for_row($table, $rowindex, (object) [
                'filterbindings' => [$binding],
            ]);
            $parts[$target] = $params[$target] ?? '';
        }
        ksort($parts);
        return sha1(json_encode($parts));
    }

    /**
     * Group row indexes that share the same column-binding values.
     *
     * @param object $table
     * @param array<int, int> $rowindexes
     * @param object $formdata Normalised chain form data.
     * @return array<int, object> Objects with rowindex, count, rowindexes.
     */
    public static function group_row_indexes_by_column_mapping(object $table, array $rowindexes, object $formdata): array {
        $groups = [];
        foreach ($rowindexes as $rowindex) {
            $hash = self::build_column_mapping_hash($table, $rowindex, $formdata);
            if (!isset($groups[$hash])) {
                $groups[$hash] = (object) [
                    'rowindex' => $rowindex,
                    'count' => 1,
                    'rowindexes' => [$rowindex],
                ];
            } else {
                $groups[$hash]->count++;
                $groups[$hash]->rowindexes[] = $rowindex;
            }
        }
        return array_values($groups);
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
            'xls' => 'xlsx',
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
     * Build ZIP archive filename from chain configuration.
     *
     * @param object $parentreport
     * @param object $childreport
     * @param object $formdata Normalised chain form data.
     * @return string
     */
    public static function build_zip_filename(object $parentreport, object $childreport, object $formdata): string {
        $pattern = trim((string) ($formdata->zipfilenamepattern ?? ''));
        if ($pattern === '') {
            $pattern = '##sourcereport##-##targetreport##';
        }

        $replacements = [
            'sourcereport' => format_string($parentreport->name),
            'targetreport' => format_string($childreport->name),
            'chainname' => $formdata->chainname !== '' ? $formdata->chainname : format_string($childreport->name),
        ];

        $filename = $pattern;
        foreach ($replacements as $key => $value) {
            $filename = str_replace('##' . $key . '##', $value, $filename);
        }
        $filename = preg_replace('/##[^#]+##/', '', $filename);
        $filename = clean_filename($filename);
        if ($filename === '') {
            $filename = clean_filename(format_string($parentreport->name) . '-' . format_string($childreport->name));
        }
        if (!preg_match('/\.zip$/i', $filename)) {
            $filename .= '.zip';
        }
        return $filename;
    }

    /**
     * Whether a generated child report contains data rows to export.
     *
     * @param object $finalreport
     * @return bool
     */
    public static function finalreport_has_data(object $finalreport): bool {
        if (empty($finalreport->table)) {
            return false;
        }
        return !empty($finalreport->table->data);
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
