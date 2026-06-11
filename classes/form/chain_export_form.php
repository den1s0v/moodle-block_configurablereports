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

namespace block_configurable_reports\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Chain export row selection form.
 *
 * @package   block_configurable_reports
 */
class chain_export_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'id', $custom['reportid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'chainid', $custom['chainid']);
        $mform->setType('chainid', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'courseid', $custom['courseid']);
        $mform->setType('courseid', PARAM_INT);

        foreach ($custom['filterparams'] as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    $field = $name . '[' . $subkey . ']';
                    $mform->addElement('hidden', $field, $subvalue);
                    $mform->setType($field, PARAM_RAW);
                }
            } else {
                $mform->addElement('hidden', $name, $value);
                $mform->setType($name, PARAM_RAW);
            }
        }

        $mform->addElement('header', 'chainexportheader', get_string('chainexportselectrows', 'block_configurable_reports'));

        $mform->addElement('select', 'exportformat', get_string('chainexportformat', 'block_configurable_reports'),
            $custom['formats']);
        $mform->addRule('exportformat', null, 'required', null, 'client');

        $selectall = [];
        foreach ($custom['rows'] as $row) {
            $selectall[] = $mform->createElement('advcheckbox', 'rowkey_' . $row->rowkey, '',
                s($row->label), ['group' => 1], [0, 1]);
        }
        if (!empty($selectall)) {
            $mform->addGroup($selectall, 'rowkeys', get_string('chainexportrows', 'block_configurable_reports'),
                '<br />', false);
        }

        $this->add_action_buttons(false, get_string('chainexportdownload', 'block_configurable_reports'));
    }

    /**
     * Extract selected row keys from submitted data.
     *
     * @param object $data
     * @return array<int, string>
     */
    public static function extract_selected_rowkeys(object $data): array {
        $selected = [];
        foreach ((array) $data as $key => $value) {
            if (strpos($key, 'rowkey_') === 0 && !empty($value)) {
                $selected[] = substr($key, strlen('rowkey_'));
            }
        }
        return $selected;
    }
}
