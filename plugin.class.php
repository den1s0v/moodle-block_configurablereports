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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO namespace.

/**
 * Class plugin_base
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class plugin_base {

    /**
     * @var string
     */
    public string $fullname = '';

    /**
     * @var string
     */
    public $type = '';

    /**
     * @var false|mixed|stdClass|null
     */
    public $report = null;

    /**
     * @var bool
     */
    public bool $form = false;

    /**
     * @var array
     */
    public $cache = [];

    /**
     * @var bool
     */
    public bool $unique = false;

    /**
     * @var array
     */
    public array $reporttypes = [];

    /**
     * @var true
     */
    public bool $ordering;

    /**
     * __construct
     *
     * @param int|object $report
     */
    public function __construct($report) {
        global $DB;

        if (is_numeric($report)) {
            $this->report = $DB->get_record('block_configurable_reports', ['id' => $report]);
        } else {
            $this->report = $report;
        }
        $this->init();
    }

    /**
     * Summary
     *
     * @param object $data
     * @return string
     */
    public function summary(object $data): string {
        return '';
    }

    /**
     * Init
     *
     * @return void
     */
    public function init(): void {
        throw new coding_exception('init method not implemented');
    }

    /**
     * colformat
     *
     * @param object|null $data
     * @return string[]
     */
    public function colformat(?object $data): array {
        $align = $data->align ?? '';
        $size = $data->size ?? '';
        $wrap = $data->wrap ?? '';

        return [$align, $size, $wrap];
    }

    /**
     * print_filter
     *
     * @param MoodleQuickForm $mform
     * @param bool|object $formdata
     * @return mixed
     */
    public function print_filter(MoodleQuickForm $mform, $formdata = false): void {
        throw new coding_exception('print_filter method not implemented');
    }

    /**
     * Add empty-filter behaviour field to filter configuration forms.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function add_emptybehavior_field(MoodleQuickForm $mform): void {
        $options = [
            'omit' => get_string('emptybehavior_omit', 'block_configurable_reports'),
            'false' => get_string('emptybehavior_false', 'block_configurable_reports'),
        ];
        $mform->addElement('select', 'emptybehavior', get_string('emptybehavior', 'block_configurable_reports'), $options);
        $mform->setDefault('emptybehavior', 'omit');
        $mform->addHelpButton('emptybehavior', 'emptybehavior', 'block_configurable_reports');
    }

    /**
     * Submit button caption for filter configuration form (add vs edit).
     *
     * @param array|object|null $customdata
     * @return string
     */
    protected function get_filter_config_submit_caption($customdata = null): string {
        if (is_object($customdata)) {
            $customdata = (array) $customdata;
        }
        if (is_array($customdata) && !empty($customdata['submitlabel'])) {
            return $customdata['submitlabel'];
        }
        if (optional_param('cid', '', PARAM_RAW) !== '') {
            return get_string('filterconfig_save', 'block_configurable_reports');
        }
        return get_string('add', 'block_configurable_reports');
    }

    /**
     * Add submit/cancel buttons for filter plugin configuration form.
     *
     * @param moodleform $form
     * @param array|object|null $customdata moodleform custom data (expects submitlabel from editplugin.php)
     * @return void
     */
    public function add_filter_config_action_buttons(moodleform $form, $customdata = null): void {
        $form->add_action_buttons(true, $this->get_filter_config_submit_caption($customdata));
    }

    /**
     * Re-apply submit button caption (value only; do not setLabel — avoids extra text above buttons).
     *
     * @param MoodleQuickForm $mform
     * @param string $caption
     * @return void
     */
    public function apply_filter_config_submit_button_value(MoodleQuickForm $mform, string $caption): void {
        $apply = function ($el) use ($caption) {
            if (!is_object($el) || $el->getName() !== 'submitbutton') {
                return;
            }
            if (method_exists($el, 'setValue')) {
                $el->setValue($caption);
            }
            if (method_exists($el, 'updateAttributes')) {
                $el->updateAttributes(['value' => $caption]);
            }
        };
        if ($mform->elementExists('submitbutton')) {
            $apply($mform->getElement('submitbutton'));
        }
        foreach (['buttonar', 'buttonbar'] as $gname) {
            if (!$mform->elementExists($gname)) {
                continue;
            }
            $group = $mform->getElement($gname);
            $children = method_exists($group, 'getElements') ? $group->getElements()
                : (isset($group->_elements) ? $group->_elements : []);
            foreach ($children as $child) {
                $apply($child);
            }
        }
    }

    /**
     * Re-apply submit caption after parent::definition_after_data (Moodle may reset value to «Add»).
     *
     * @param moodleform $form
     * @param MoodleQuickForm $mform
     * @param array|object|null $customdata
     * @return void
     */
    public function filter_config_definition_after_data(moodleform $form, MoodleQuickForm $mform, $customdata = null): void {
        $this->apply_filter_config_submit_button_value($mform, $this->get_filter_config_submit_caption($customdata));
    }

    public function add_usefilterdefault_field(MoodleQuickForm $mform): void {
        $mform->addElement('advcheckbox', 'usefilterdefault', '', get_string('filterdefault_enable', 'block_configurable_reports'));
        $mform->addHelpButton('usefilterdefault', 'filterdefault_enable', 'block_configurable_reports');
    }

    /**
     * Add default filter value field (shown when usefilterdefault is checked).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function add_defaultvalue_field(MoodleQuickForm $mform): void {
        $placeholder = get_string('emptyfilter_defaultvalue_placeholder', 'block_configurable_reports');
        $mform->addElement('text', 'defaultvalue', get_string('emptyfilter_defaultvalue', 'block_configurable_reports'),
            ['placeholder' => $placeholder, 'size' => 50]);
        $mform->setType('defaultvalue', PARAM_RAW);
        $mform->hideIf('defaultvalue', 'usefilterdefault', 'notchecked');
        $mform->addHelpButton('defaultvalue', 'emptyfilter_defaultvalue', 'block_configurable_reports');
        $mform->addElement('static', 'defaultvalue_hint', '',
            get_string('emptyfilter_defaultvalue_hint', 'block_configurable_reports'));
        $mform->hideIf('defaultvalue_hint', 'usefilterdefault', 'notchecked');
    }

    /**
     * Configured default value for empty report filter, or null if unset.
     *
     * @param object $formdata
     * @return string|null
     */
    public function get_default_filter_value(object $formdata): ?string {
        if (!isset($formdata->defaultvalue)) {
            return null;
        }
        $value = trim((string) $formdata->defaultvalue);
        return $value === '' ? null : $value;
    }

    /**
     * Whether the filter default template is enabled in configuration.
     *
     * @param object $formdata
     * @return bool
     */
    public function is_filterdefault_enabled(object $formdata): bool {
        if (!empty($formdata->usefilterdefault)) {
            return true;
        }
        return ($formdata->emptybehavior ?? '') === 'default';
    }

    /**
     * Whether a default may be applied for this filter parameter on the report page.
     *
     * @param object $formdata
     * @return bool
     */
    public function should_use_default_when_empty(object $formdata): bool {
        return $this->is_filterdefault_enabled($formdata)
            && $this->get_default_filter_value($formdata) !== null;
    }

    /**
     * Whether a report filter parameter was present in the current HTTP request.
     *
     * @param string $paramname
     * @return bool
     */
    public function is_filter_param_in_request(string $paramname): bool {
        if (function_exists('param_exists')) {
            return param_exists($paramname);
        }
        return array_key_exists($paramname, $_POST) || array_key_exists($paramname, $_GET);
    }

    /**
     * Whether the configured default should be used for this request parameter.
     *
     * @param object $formdata
     * @param string $paramname
     * @return bool
     */
    public function should_apply_default_for_param(object $formdata, string $paramname): bool {
        return $this->should_use_default_when_empty($formdata)
            && !$this->is_filter_param_in_request($paramname);
    }

    /**
     * Resolve a text report filter parameter, applying the template default when appropriate.
     *
     * @param string $paramname
     * @param object $formdata
     * @param string|int $paramtype PARAM_* constant
     * @return string
     */
    public function resolve_text_filter_param(string $paramname, object $formdata, $paramtype = PARAM_RAW): string {
        if ($this->should_apply_default_for_param($formdata, $paramname)) {
            return $this->get_default_filter_value($formdata) ?? '';
        }
        return (string) optional_param($paramname, '', $paramtype);
    }

    /**
     * Resolve an encoded (base64) select filter parameter, applying the template default when appropriate.
     *
     * @param string $paramname
     * @param object $formdata
     * @param string|int $paramtype PARAM_* constant
     * @return string
     */
    public function resolve_encoded_filter_param(string $paramname, object $formdata, $paramtype = PARAM_RAW): string {
        if ($this->should_apply_default_for_param($formdata, $paramname)) {
            return $this->get_default_filter_value_encoded($formdata) ?? '';
        }
        return (string) optional_param($paramname, '', $paramtype);
    }

    /**
     * Default value encoded for select-based report filters (base64 option keys).
     *
     * @param object $formdata
     * @return string|null
     */
    public function get_default_filter_value_encoded(object $formdata): ?string {
        $value = $this->get_default_filter_value($formdata);
        if ($value === null) {
            return null;
        }
        return base64_encode($value);
    }

    /**
     * Normalize stored filter config for the admin edit form (legacy emptybehavior=default).
     *
     * @param object $formdata
     * @return object
     */
    public function prepare_filter_config_formdata(object $formdata): object {
        if (($formdata->emptybehavior ?? '') === 'default') {
            $formdata->usefilterdefault = 1;
            $formdata->emptybehavior = 'omit';
        }
        return $formdata;
    }

    /**
     * Get configured behaviour when filter value is empty on the report form.
     *
     * @param object $formdata
     * @return string omit|false
     */
    public function get_emptybehavior(object $formdata): string {
        $behavior = $formdata->emptybehavior ?? 'omit';
        if ($behavior === 'default') {
            return 'omit';
        }
        if (!in_array($behavior, ['omit', 'false'], true)) {
            return 'omit';
        }
        return $behavior;
    }

    /**
     * SQL replacement when filter is empty and behaviour is "false".
     *
     * @param string $fullplaceholder Full %%...%% token.
     * @return string
     */
    public function get_restrictive_replacement(string $fullplaceholder): string {
        if (preg_match('/%%FILTER_COURSEMODULE:/i', $fullplaceholder)) {
            return ' AND 1=0 ';
        }
        if (preg_match('/%%FILTER_COURSEMODULEFIELDS:/i', $fullplaceholder)) {
            return ' 1=0 ';
        }
        return ' AND 1=0 ';
    }

}
