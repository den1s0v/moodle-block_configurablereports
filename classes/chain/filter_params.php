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
                return 'filter_fcoursefield';
            case 'fuserfield':
                return 'filter_fuserfield';
            case 'fsearchuserfield':
                return 'filter_fsearchuserfield';
            case 'coursecategories':
                return 'filter_coursecategories';
            case 'startendtime':
                return 'filter_starttime';
            default:
                return 'filter_' . $pluginname;
        }
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
            $label = get_string($pluginname, 'block_configurable_reports', null, true);
            if ($label === '[[' . $pluginname . ']]') {
                $label = $pluginname;
            }
            $options[$paramname] = $paramname . ' (' . $label . ')';
        }

        return $options;
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
