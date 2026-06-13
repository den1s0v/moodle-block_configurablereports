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

require_once($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/locallib.php');

/**
 * Child report filter parameter names for chain bindings.
 *
 * @package   block_configurable_reports
 */
class filter_params {

    /** @var string Binding mode: value from source report column. */
    public const MODE_COLUMN = 'column';

    /** @var string Binding mode: explicit empty filter submission. */
    public const MODE_EMPTY = 'empty';

    /** @var string Binding mode: fixed constant for all rows. */
    public const MODE_CONSTANT = 'constant';

    /**
     * Guess request parameter name for a filter plugin.
     *
     * @param string $pluginname
     * @param object $formdata
     * @return string
     */
    public static function guess_param_name(string $pluginname, object $formdata): string {
        switch ($pluginname) {
            case 'searchtext':
                if (!empty($formdata->idnumber)) {
                    return 'filter_searchtext_' . $formdata->idnumber;
                }
                return 'filter_searchtext';
            case 'fcoursefield':
                if (!empty($formdata->field)) {
                    return 'filter_fcoursefield_' . $formdata->field;
                }
                return 'filter_fcoursefield';
            case 'fuserfield':
            case 'fsearchuserfield':
                if (!empty($formdata->field)) {
                    return 'filter_fuserfield_' . $formdata->field;
                }
                return 'filter_fuserfield';
            case 'coursecategories':
                return 'filter_coursecategories';
            case 'startendtime':
                return 'filter_starttime';
            default:
                return 'filter_' . $pluginname;
        }
    }

    /**
     * Human-readable filter label as shown on the report view form.
     *
     * @param string $pluginname
     * @param object $formdata Filter plugin configuration.
     * @param string $paramname Request parameter name for this filter.
     * @return string
     */
    public static function get_filter_view_label(string $pluginname, object $formdata, string $paramname): string {
        switch ($pluginname) {
            case 'searchtext':
                if (!empty($formdata->label)) {
                    return (string) $formdata->label;
                }
                return get_string('filter', 'block_configurable_reports');
            case 'fcoursefield':
                if (!empty($formdata->field)) {
                    return self::resolve_core_field_label($formdata->field);
                }
                break;
            case 'fuserfield':
            case 'fsearchuserfield':
                return self::resolve_user_field_label($formdata);
            case 'courses':
                return get_string('course');
            case 'coursecategories':
            case 'categories':
            case 'subcategories':
                return get_string('category');
            case 'users':
                return get_string('users');
            case 'user':
                return get_string('user');
            case 'startendtime':
                if ($paramname === 'filter_endtime') {
                    return get_string('endtime', 'block_configurable_reports');
                }
                return get_string('starttime', 'block_configurable_reports');
            case 'semester':
                return get_string('filtersemester', 'block_configurable_reports');
            case 'role':
                return get_string('filterrole', 'block_configurable_reports');
            case 'yearnumeric':
                return get_string('filteryearnumeric', 'block_configurable_reports');
            case 'yearhebrew':
                return get_string('filteryearhebrew', 'block_configurable_reports');
            case 'coursemodules':
                return get_string('filtercoursemodules', 'block_configurable_reports');
            case 'enrolledstudents':
                return get_string('student', 'block_configurable_reports');
        }

        $label = get_string($pluginname, 'block_configurable_reports', null, true);
        if ($label !== '[[' . $pluginname . ']]') {
            return $label;
        }

        return $paramname;
    }

    /**
     * Resolve a standard DB field name to a display label.
     *
     * @param string $field
     * @return string
     */
    private static function resolve_core_field_label(string $field): string {
        $label = get_string($field, '', null, true);
        if ($label !== '[[' . $field . ']]') {
            return $label;
        }
        return $field;
    }

    /**
     * Resolve user field filter label (core or custom profile field).
     *
     * @param object $formdata
     * @return string
     */
    private static function resolve_user_field_label(object $formdata): string {
        if (empty($formdata->field)) {
            return get_string('filterfuserfield', 'block_configurable_reports');
        }

        if (strpos($formdata->field, 'profile_') === 0) {
            global $DB;
            $shortname = str_replace('profile_', '', $formdata->field);
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname], 'name', IGNORE_MISSING);
            if ($field) {
                return format_string($field->name);
            }
        }

        return self::resolve_core_field_label($formdata->field);
    }

    /**
     * Filter options for a child report: param name => label.
     *
     * @param object $childreport Child report record.
     * @return array<string, string>
     */
    public static function get_child_filter_options(object $childreport): array {
        $components = cr_unserialize($childreport->components ?? '');
        $filters = $components['filters']['elements'] ?? [];
        $options = [];

        foreach ($filters as $filter) {
            $pluginname = $filter['pluginname'] ?? '';
            if ($pluginname === '') {
                continue;
            }
            $formdata = (object) ($filter['formdata'] ?? new \stdClass());
            $paramname = self::guess_param_name($pluginname, $formdata);
            if ($paramname === '') {
                continue;
            }
            $options[$paramname] = self::get_filter_view_label($pluginname, $formdata, $paramname);
        }

        return $options;
    }

    /**
     * View labels keyed by filter parameter name for a child report.
     *
     * @param object $childreport Child report record.
     * @return array<string, string>
     */
    public static function get_child_filter_labels(object $childreport): array {
        return self::get_child_filter_options($childreport);
    }

    /**
     * Ordered list of valid binding modes.
     *
     * @return array<string, string>
     */
    public static function get_mode_options(): array {
        return [
            self::MODE_COLUMN => get_string('chainfiltermode_column', 'block_configurable_reports'),
            self::MODE_EMPTY => get_string('chainfiltermode_empty', 'block_configurable_reports'),
            self::MODE_CONSTANT => get_string('chainfiltermode_constant', 'block_configurable_reports'),
        ];
    }

    /**
     * Whether a binding mode string is valid.
     *
     * @param string $mode
     * @return bool
     */
    public static function is_valid_mode(string $mode): bool {
        return in_array($mode, [self::MODE_COLUMN, self::MODE_EMPTY, self::MODE_CONSTANT], true);
    }
}
