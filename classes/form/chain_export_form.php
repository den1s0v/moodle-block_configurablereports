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

use block_configurable_reports\export\report_matrix;

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

        if (!empty($custom['rows'])) {
            $mform->addElement('html', self::render_rows_table($custom['head'] ?? [], $custom['rows']));
        }

        $this->add_action_buttons(false, get_string('chainexportdownload', 'block_configurable_reports'));
    }

    /**
     * Render parent rows as a selectable table.
     *
     * @param array<int|string, string> $head
     * @param array<int, object> $rows
     * @return string
     */
    public static function render_rows_table(array $head, array $rows): string {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable chainexport-rowtable';
        $table->id = 'chainexport-rowtable';

        $selectall = \html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'id' => 'chainexport-selectall',
            'checked' => 'checked',
            'title' => get_string('chainexportselectall', 'block_configurable_reports'),
        ]);
        $headercells = [$selectall];
        foreach ($head as $heading) {
            $headercells[] = $heading;
        }
        $table->head = $headercells;

        foreach ($rows as $row) {
            $cells = [
                \html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'name' => 'rowkey_' . $row->rowkey,
                    'value' => '1',
                    'class' => 'chainexport-rowcb',
                    'checked' => 'checked',
                ]),
            ];
            foreach ($row->cells as $cell) {
                $cells[] = s(report_matrix::cell_to_plain_text($cell));
            }
            $table->data[] = $cells;
        }

        return \html_writer::table($table);
    }

    /**
     * Ensure at least one row is selected.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty(self::extract_selected_rowkeys((object) $data))) {
            $errors['chainexportheader'] = get_string('chainexportnorowsselected', 'block_configurable_reports');
        }
        return $errors;
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
