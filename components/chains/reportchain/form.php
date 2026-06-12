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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Report chain configuration form.
 *
 * @package   block_configurable_reports
 */
class reportchain_form extends moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;
        $pluginclass = $this->_customdata['pluginclass'];
        $cid = $this->_customdata['cid'] ?? '';

        $childreportid = optional_param('childreportid', 0, PARAM_INT);
        if (!$childreportid && !empty($this->_customdata['storedchildreportid'])) {
            $childreportid = (int) $this->_customdata['storedchildreportid'];
        }

        $configready = $childreportid > 0;

        $mform->addElement('header', 'crformheader', get_string('reportchain', 'block_configurable_reports'));

        $mform->addElement('text', 'chainname', get_string('chainname', 'block_configurable_reports'), ['size' => 60]);
        $mform->setType('chainname', PARAM_TEXT);
        $mform->addHelpButton('chainname', 'chainname', 'block_configurable_reports');
        $mform->addRule('chainname', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', '', get_string('chainenabled', 'block_configurable_reports'));
        $mform->addHelpButton('enabled', 'chainenabled', 'block_configurable_reports');
        $mform->setDefault('enabled', 1);

        $reports = $pluginclass->get_available_child_reports();
        $reportoptions = [0 => get_string('choose')];
        foreach ($reports as $report) {
            $reportoptions[$report->id] = format_string($report->name);
        }

        $furl = $CFG->wwwroot . '/blocks/configurable_reports/editplugin.php?id=' . $this->_customdata['report']->id .
            '&comp=chains&pname=reportchain';
        if ($cid !== '') {
            $furl .= '&cid=' . urlencode($cid);
        }
        $selectattrs = ['onchange' => 'location.href="' . $furl . '&childreportid="+document.getElementById("id_childreportid").value'];

        $mform->addElement('select', 'childreportid', get_string('chainchildreport', 'block_configurable_reports'),
            $reportoptions, $selectattrs);
        $mform->setDefault('childreportid', $childreportid);
        $mform->addRule('childreportid', null, 'required', null, 'client');

        if (!$configready) {
            $mform->addElement('static', 'selectchildhint', '', get_string('chainselectchildhint', 'block_configurable_reports'));
            $this->add_action_buttons(false);
            return;
        }

        $mform->addElement('text', 'filenamepattern', get_string('chainfilenamepattern', 'block_configurable_reports'),
            ['size' => 60]);
        $mform->setType('filenamepattern', PARAM_RAW);
        $mform->setDefault('filenamepattern', '##reportname##_##row##');
        $mform->addHelpButton('filenamepattern', 'chainfilenamepattern', 'block_configurable_reports');

        $mform->addElement('text', 'rowkeycolumns', get_string('chainrowkeycolumns', 'block_configurable_reports'),
            ['size' => 60]);
        $mform->setType('rowkeycolumns', PARAM_RAW);
        $mform->addHelpButton('rowkeycolumns', 'chainrowkeycolumns', 'block_configurable_reports');
        $mform->addRule('rowkeycolumns', null, 'required', null, 'client');

        $filteroptions = $pluginclass->get_child_filter_options($childreportid);
        if (empty($filteroptions)) {
            $mform->addElement('static', 'nofilters', '', get_string('chainnofilters', 'block_configurable_reports'));
        }

        $mappingcount = max(1, (int) ($this->_customdata['mappingcount'] ?? 1));

        $mform->addElement('static', 'mappingcolumnsheader', '',
            html_writer::div(
                get_string('chainmappingcolumnsheader', 'block_configurable_reports'),
                'chain-mapping-columns-header fw-bold mb-2'
            ));
        $this->add_mapping_groups($mform, $mappingcount, $filteroptions);

        $mform->addElement('hidden', 'mappingcount', $mappingcount);
        $mform->setType('mappingcount', PARAM_INT);

        if (!empty($this->_customdata['formbaseurl'])) {
            $addurl = new moodle_url($this->_customdata['formbaseurl'], ['mappingcount' => $mappingcount + 1]);
            $mform->addElement('static', 'addmappinglink', '',
                html_writer::link($addurl, get_string('chainaddmapping', 'block_configurable_reports')));
        }

        $submitlabel = $this->_customdata['submitlabel'] ?? get_string('add', 'block_configurable_reports');
        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * Add one form group per column-to-filter mapping.
     *
     * @param MoodleQuickForm $mform
     * @param int $mappingcount
     * @param array<string, string> $filteroptions
     * @return void
     */
    protected function add_mapping_groups($mform, int $mappingcount, array $filteroptions): void {
        for ($i = 0; $i < $mappingcount; $i++) {
            $sourcefield = 'sourcecolumn' . $i;
            $targetfield = 'targetfilter' . $i;
            $groupelements = [];
            $groupelements[] = $mform->createElement('text', $sourcefield, '', ['size' => 30]);
            if (!empty($filteroptions)) {
                $groupelements[] = $mform->createElement('select', $targetfield, '', $filteroptions);
            } else {
                $groupelements[] = $mform->createElement('text', $targetfield, '', ['size' => 30]);
            }

            $mform->addGroup(
                $groupelements,
                'mappinggroup' . $i,
                get_string('chainmappingheader', 'block_configurable_reports', $i + 1),
                html_writer::span(' → ', 'px-2'),
                false
            );
            $mform->setType($sourcefield, PARAM_RAW);
            $mform->setType($targetfield, PARAM_RAW);
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty($data['childreportid'])) {
            $errors['childreportid'] = get_string('chainerror_nochild', 'block_configurable_reports');
            return $errors;
        }

        if (empty(trim((string) ($data['chainname'] ?? '')))) {
            $errors['chainname'] = get_string('chainerror_noname', 'block_configurable_reports');
        }

        if (empty(trim((string) ($data['rowkeycolumns'] ?? '')))) {
            $errors['rowkeycolumns'] = get_string('chainerror_norowkeys', 'block_configurable_reports');
        }

        $hasmapping = false;
        $mappingcount = max(1, (int) ($data['mappingcount'] ?? 1));
        for ($i = 0; $i < $mappingcount; $i++) {
            $source = $this->get_mapping_value($data, 'sourcecolumn', $i);
            $target = $this->get_mapping_value($data, 'targetfilter', $i);
            if ($source !== '' && $target !== '') {
                $hasmapping = true;
                break;
            }
        }
        if (!$hasmapping) {
            $errors['mappinggroup0'] = get_string('chainerror_nomappings', 'block_configurable_reports');
        }

        $sourcereport = $this->_customdata['report'];
        if ((int) $data['childreportid'] === (int) $sourcereport->id) {
            $errors['childreportid'] = get_string('chainerror_selfreference', 'block_configurable_reports');
        }

        return $errors;
    }

    /**
     * Read a mapping field from submitted form data.
     *
     * @param array $data
     * @param string $field
     * @param int $index
     * @return string
     */
    protected function get_mapping_value(array $data, string $field, int $index): string {
        return $this->read_mapping_field((object) $data, $field, $index);
    }

    /**
     * Read a mapping field from form data (top-level or inside a form group).
     *
     * @param object $data
     * @param string $field
     * @param int $index
     * @return string
     */
    protected function read_mapping_field(object $data, string $field, int $index): string {
        $key = $field . $index;
        if (property_exists($data, $key)) {
            return trim((string) $data->$key);
        }

        $groupkey = 'mappinggroup' . $index;
        if (property_exists($data, $groupkey)) {
            $group = $data->$groupkey;
            if (is_array($group) && array_key_exists($key, $group)) {
                return trim((string) $group[$key]);
            }
            if (is_object($group) && property_exists($group, $key)) {
                return trim((string) $group->$key);
            }
        }

        // Legacy bracket notation from older form versions.
        if (property_exists($data, $field) && is_array($data->$field) && array_key_exists($index, $data->$field)) {
            return trim((string) $data->$field[$index]);
        }
        $flatkey = $field . '[' . $index . ']';
        if (property_exists($data, $flatkey)) {
            return trim((string) $data->$flatkey);
        }

        return '';
    }

    /**
     * Expand stored mappings for form fields.
     *
     * @param object $data
     * @return void
     */
    public function set_data($data): void {
        $data = (object) $data;
        if (!empty($data->mappings)) {
            $index = 0;
            foreach ($data->mappings as $mapping) {
                $mapping = (object) $mapping;
                $sourcefield = 'sourcecolumn' . $index;
                $targetfield = 'targetfilter' . $index;
                $data->$sourcefield = $mapping->sourcecolumn ?? '';
                $data->$targetfield = $mapping->targetfilter ?? '';
                $index++;
            }
            $data->mappingcount = $index;
        }
        if (!empty($this->_customdata['mappingcount'])) {
            $data->mappingcount = max((int) ($data->mappingcount ?? 1), (int) $this->_customdata['mappingcount']);
        }
        if (!empty($data->rowkeycolumns) && is_array($data->rowkeycolumns)) {
            $data->rowkeycolumns = implode(', ', $data->rowkeycolumns);
        }
        parent::set_data($data);
    }

    /**
     * Prepare mappings for storage.
     *
     * @param object $data
     * @return object
     */
    public function prepare_mapping_data(object $data): object {
        if (!isset($data->enabled)) {
            $data->enabled = 0;
        }
        $mappings = [];
        $mappingcount = max(1, (int) ($data->mappingcount ?? 1));
        for ($i = 0; $i < $mappingcount; $i++) {
            $source = $this->read_mapping_field($data, 'sourcecolumn', $i);
            $target = $this->read_mapping_field($data, 'targetfilter', $i);
            if ($source === '' || $target === '') {
                continue;
            }
            $mappings[] = (object) [
                'sourcecolumn' => $source,
                'targetfilter' => $target,
            ];
        }
        $data->mappings = $mappings;
        for ($i = 0; $i < $mappingcount; $i++) {
            unset($data->{'sourcecolumn' . $i}, $data->{'targetfilter' . $i}, $data->{'mappinggroup' . $i});
        }
        unset($data->mappingcount);
        return $data;
    }
}
