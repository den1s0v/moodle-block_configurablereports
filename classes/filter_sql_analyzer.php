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
 * Analyses SQL report placeholders against configured filters.
 *
 * @package   block_configurable_reports
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Filter SQL placeholder analyser.
 */
class filter_sql_analyzer {

    /** @var string[] System placeholders substituted by prepare_sql(), not filter components. */
    public const SYSTEM_PLACEHOLDERS = [
        'USERID',
        'COURSEID',
        'CATEGORYID',
        'WWWROOT',
        'DEBUG',
        'FILTER_VAR',
        'STARTTIME',
        'ENDTIME',
    ];

    /**
     * Map FILTER token prefix to filter plugin name(s).
     *
     * @var array<string, string|array>
     */
    private const FILTER_REGISTRY = [
        'FILTER_SEARCHTEXT' => 'searchtext',
        'FILTER_COURSES' => 'courses',
        'FILTER_CATEGORIES' => 'categories',
        'FILTER_SUBCATEGORIES' => 'subcategories',
        'FILTER_COHORTS' => 'cohorts',
        'FILTER_SEMESTER' => 'semester',
        'FILTER_YEARNUMERIC' => 'yearnumeric',
        'FILTER_YEARHEBREW' => 'yearhebrew',
        'FILTER_ROLE' => 'role',
        'FILTER_COURSEUSER' => 'user',
        'FILTER_SYSTEMUSER' => 'users',
        'FILTER_COURSEENROLLEDSTUDENTS' => 'enrolledstudents',
        'FILTER_COURSEMODULEID' => 'coursemodules',
        'FILTER_COURSEMODULEFIELDS' => 'coursemodules',
        'FILTER_COURSEMODULE' => 'coursemodules',
        'FILTER_USERS' => ['fuserfield', 'fsearchuserfield'],
        'FILTER_STARTTIME' => 'startendtime',
        'FILTER_ENDTIME' => 'startendtime',
        'FILTER_COMPETENCYFRAMEWORKS' => 'competencyframeworks',
        'FILTER_COMPETENCYTEMPLATES' => 'competencytemplates',
    ];

    /**
     * Analyse SQL query against configured filters.
     *
     * @param string $querysql Raw SQL from report config.
     * @param array $filterelements filters.elements from report components.
     * @return \stdClass Analysis result with filterrows, missing, notices.
     */
    public static function analyse(string $querysql, array $filterelements): \stdClass {
        $result = (object) [
            'filterrows' => [],
            'missing' => [],
            'notices' => [],
        ];

        if ($querysql === '') {
            return $result;
        }

        preg_match_all('/%%([^%]+)%%/', $querysql, $matches);
        $tokens = $matches[1] ?? [];

        $filterplaceholders = [];
        foreach ($tokens as $inner) {
            $name = self::token_name($inner);
            if (self::is_system_placeholder($name)) {
                continue;
            }
            if (strpos($name, 'FILTER_') === 0) {
                $filterplaceholders[] = [
                    'full' => '%%' . $inner . '%%',
                    'inner' => $inner,
                    'name' => $name,
                    'payload' => self::token_payload($inner),
                ];
            }
        }

        $claimed = [];
        foreach ($filterelements as $index => $element) {
            if (empty($element['pluginname'])) {
                continue;
            }
            $formdata = (object) ($element['formdata'] ?? new \stdClass());
            $row = (object) [
                'index' => $index,
                'pluginname' => $element['pluginname'],
                'summary' => $element['summary'] ?? '',
                'status' => 'notfound',
                'usages' => [],
                'duplicate' => false,
            ];

            foreach ($filterplaceholders as $ph) {
                if (self::placeholder_matches_filter($ph, $element['pluginname'], $formdata)) {
                    $row->usages[] = self::describe_usage($ph);
                    if (isset($claimed[$ph['inner']])) {
                        $row->duplicate = true;
                    }
                    $claimed[$ph['inner']] = true;
                }
            }

            if (!empty($row->usages)) {
                $row->status = $row->duplicate ? 'duplicate' : 'used';
            }

            $result->filterrows[$index] = $row;
        }

        foreach ($filterplaceholders as $ph) {
            if (isset($claimed[$ph['inner']])) {
                continue;
            }
            $plugins = self::suggest_plugins($ph['name']);
            $result->missing[] = (object) [
                'placeholder' => $ph['full'],
                'inner' => $ph['inner'],
                'name' => $ph['name'],
                'payload' => $ph['payload'],
                'detail' => self::describe_usage($ph),
                'suggestedplugins' => $plugins,
                'prefill' => self::prefill_for_placeholder($ph),
            ];
        }

        if (self::has_bare_date_placeholders($querysql, $filterelements, $filterplaceholders)) {
            $result->notices[] = 'filtersql_startendtime_notice';
        }

        return $result;
    }

    /**
     * Human-readable operator label.
     *
     * @param string $operator
     * @return string
     */
    public static function operator_label(string $operator): string {
        $map = [
            '~' => 'operator_like',
            'in' => 'operator_in',
            '=' => 'operator_exact',
            '<' => 'operator_lt',
            '>' => 'operator_gt',
            '<=' => 'operator_lte',
            '>=' => 'operator_gte',
        ];
        $key = $map[$operator] ?? 'operator_unknown';
        return get_string($key, 'block_configurable_reports', $operator);
    }

    /**
     * Public wrapper for placeholder matching (used when building SQL).
     *
     * @param array $ph
     * @param string $pluginname
     * @param \stdClass $formdata
     * @return bool
     */
    public static function placeholder_matches_filter_public(array $ph, string $pluginname, \stdClass $formdata): bool {
        return self::placeholder_matches_filter($ph, $pluginname, $formdata);
    }

    /**
     * @param array $ph Placeholder info.
     * @return string
     */
    private static function describe_usage(array $ph): string {
        $parts = explode(':', $ph['payload']);
        $field = $parts[0] ?? $ph['payload'];
        $operator = $parts[1] ?? '';
        if ($operator !== '') {
            $oplabel = self::operator_label($operator);
            return get_string('filterusage_detail_fieldop', 'block_configurable_reports', (object) [
                'field' => $field,
                'operator' => $operator,
                'operatorlabel' => $oplabel,
            ]);
        }
        return get_string('filterusage_detail_field', 'block_configurable_reports', (object) [
            'field' => $field,
        ]);
    }

    /**
     * @param string $inner Token inner text.
     * @return string
     */
    private static function token_name(string $inner): string {
        $colon = strpos($inner, ':');
        if ($colon === false) {
            return $inner;
        }
        return substr($inner, 0, $colon);
    }

    /**
     * @param string $inner
     * @return string
     */
    private static function token_payload(string $inner): string {
        $colon = strpos($inner, ':');
        if ($colon === false) {
            return '';
        }
        return substr($inner, $colon + 1);
    }

    /**
     * @param string $name
     * @return bool
     */
    private static function is_system_placeholder(string $name): bool {
        return in_array(strtoupper($name), self::SYSTEM_PLACEHOLDERS, true);
    }

    /**
     * Case-insensitive token name comparison.
     *
     * @param string $name
     * @param string $expected
     * @return bool
     */
    private static function token_equals(string $name, string $expected): bool {
        return strcasecmp($name, $expected) === 0;
    }

    /**
     * Case-insensitive token prefix check.
     *
     * @param string $name
     * @param string $prefix
     * @return bool
     */
    private static function token_starts_with(string $name, string $prefix): bool {
        return strncasecmp($name, $prefix, strlen($prefix)) === 0;
    }

    /**
     * @param array $ph
     * @param string $pluginname
     * @param \stdClass $formdata
     * @return bool
     */
    private static function placeholder_matches_filter(array $ph, string $pluginname, \stdClass $formdata): bool {
        $name = $ph['name'];

        switch ($pluginname) {
            case 'searchtext':
                if (self::token_equals($name, 'FILTER_SEARCHTEXT')) {
                    return empty($formdata->idnumber);
                }
                if (!empty($formdata->idnumber)) {
                    return self::token_equals($name, 'FILTER_SEARCHTEXT_' . $formdata->idnumber);
                }
                return false;

            case 'fuserfield':
                if (self::token_equals($name, 'FILTER_USERS')) {
                    return true;
                }
                if (!empty($formdata->field)) {
                    return self::token_equals($name, 'FILTER_USERS_' . $formdata->field);
                }
                return false;

            case 'fsearchuserfield':
                return self::token_equals($name, 'FILTER_USERS');

            case 'startendtime':
                return self::token_equals($name, 'FILTER_STARTTIME')
                    || self::token_equals($name, 'FILTER_ENDTIME');

            case 'coursemodules':
                return self::token_equals($name, 'FILTER_COURSEMODULEID')
                    || self::token_equals($name, 'FILTER_COURSEMODULEFIELDS')
                    || self::token_equals($name, 'FILTER_COURSEMODULE');

            default:
                $registry = self::FILTER_REGISTRY;
                foreach ($registry as $prefix => $mapped) {
                    if (self::token_equals($name, $prefix) || self::token_starts_with($name, $prefix . '_')) {
                        if (is_array($mapped)) {
                            return in_array($pluginname, $mapped, true);
                        }
                        return $pluginname === $mapped;
                    }
                }
                return false;
        }
    }

    /**
     * @param string $name
     * @return string[]
     */
    private static function suggest_plugins(string $name): array {
        foreach (self::FILTER_REGISTRY as $prefix => $mapped) {
            if (self::token_equals($name, $prefix) || self::token_starts_with($name, $prefix . '_')) {
                return is_array($mapped) ? $mapped : [$mapped];
            }
        }
        if (self::token_starts_with($name, 'FILTER_SEARCHTEXT')) {
            return ['searchtext'];
        }
        if (self::token_starts_with($name, 'FILTER_USERS')) {
            return ['fuserfield', 'fsearchuserfield'];
        }
        return [];
    }

    /**
     * @param array $ph
     * @return \stdClass
     */
    private static function prefill_for_placeholder(array $ph): \stdClass {
        $prefill = (object) [];
        $inner = $ph['inner'];
        if (preg_match('/^FILTER_SEARCHTEXT_([^:]+)/i', $inner, $matches)) {
            $prefill->idnumber = $matches[1];
        } else if (self::token_equals(self::token_name($inner), 'FILTER_SEARCHTEXT')) {
            $prefill->idnumber = '';
        } else if (preg_match('/^FILTER_USERS_([^:]+)/i', $inner, $matches)) {
            $prefill->field = $matches[1];
        }
        $parts = explode(':', $ph['payload']);
        if (!empty($parts[0])) {
            $prefill->label = $parts[0];
        }
        return $prefill;
    }

    /**
     * @param string $querysql
     * @param array $filterelements
     * @param array $filterplaceholders
     * @return bool
     */
    private static function has_bare_date_placeholders(
        string $querysql,
        array $filterelements,
        array $filterplaceholders
    ): bool {
        if (strpos($querysql, '%%STARTTIME%%') === false && strpos($querysql, '%%ENDTIME%%') === false) {
            return false;
        }
        foreach ($filterelements as $element) {
            if (($element['pluginname'] ?? '') === 'startendtime') {
                return false;
            }
        }
        foreach ($filterplaceholders as $ph) {
            if (self::token_equals($ph['name'], 'FILTER_STARTTIME') || self::token_equals($ph['name'], 'FILTER_ENDTIME')) {
                return false;
            }
        }
        return true;
    }
}
