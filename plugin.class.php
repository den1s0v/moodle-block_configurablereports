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
            'default' => get_string('emptybehavior_default', 'block_configurable_reports'),
        ];
        $mform->addElement('select', 'emptybehavior', get_string('emptybehavior', 'block_configurable_reports'), $options);
        $mform->setDefault('emptybehavior', 'omit');
        $mform->addHelpButton('emptybehavior', 'emptybehavior', 'block_configurable_reports');
        $mform->addElement('static', 'emptybehavior_intro', '',
            get_string('emptybehavior_intro', 'block_configurable_reports'));
        $mform->hideIf('emptybehavior_intro', 'emptybehavior', 'neq', 'default');
    }

    /**
     * Add default filter value field (shown when emptybehavior is "default").
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function add_defaultvalue_field(MoodleQuickForm $mform): void {
        $placeholder = get_string('emptyfilter_defaultvalue_placeholder', 'block_configurable_reports');
        $mform->addElement('text', 'defaultvalue', get_string('emptyfilter_defaultvalue', 'block_configurable_reports'),
            ['placeholder' => $placeholder, 'size' => 50]);
        $mform->setType('defaultvalue', PARAM_RAW);
        $mform->hideIf('defaultvalue', 'emptybehavior', 'neq', 'default');
        $mform->addHelpButton('defaultvalue', 'emptyfilter_defaultvalue', 'block_configurable_reports');
        $mform->addElement('static', 'defaultvalue_hint', '',
            get_string('emptyfilter_defaultvalue_hint', 'block_configurable_reports'));
        $mform->hideIf('defaultvalue_hint', 'emptybehavior', 'neq', 'default');
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
     * Whether to apply the configured default when the report filter is empty.
     *
     * @param object $formdata
     * @return bool
     */
    public function should_use_default_when_empty(object $formdata): bool {
        return $this->get_emptybehavior($formdata) === 'default'
            && $this->get_default_filter_value($formdata) !== null;
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
     * Get configured behaviour when filter value is empty on the report form.
     *
     * @param object $formdata
     * @return string omit|false|default
     */
    public function get_emptybehavior(object $formdata): string {
        $behavior = $formdata->emptybehavior ?? 'omit';
        if (!in_array($behavior, ['omit', 'false', 'default'], true)) {
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
