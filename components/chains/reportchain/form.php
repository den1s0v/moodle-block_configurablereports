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

use block_configurable_reports\report\output_columns;

/**
 * Report chain configuration form.
 *
 * @package   block_configurable_reports
 */
class reportchain_form extends moodleform {

    /**
     * Moodle resets submit button text to «Add» after set_data; re-apply our caption.
     *
     * @return void
     */
    public function definition_after_data(): void {
        parent::definition_after_data();
        $childreportid = optional_param('childreportid', 0, PARAM_INT);
        if (!$childreportid && !empty($this->_customdata['storedchildreportid'])) {
            $childreportid = (int) $this->_customdata['storedchildreportid'];
        }
        if ($childreportid > 0) {
            $this->_customdata['pluginclass']->filter_config_definition_after_data($this, $this->_form, $this->_customdata);
        }
    }

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $pluginclass = $this->_customdata['pluginclass'];
        $cid = $this->_customdata['cid'] ?? '';
        $sourcereport = $this->_customdata['report'];

        $childreportid = optional_param('childreportid', 0, PARAM_INT);
        if (!$childreportid && !empty($this->_customdata['storedchildreportid'])) {
            $childreportid = (int) $this->_customdata['storedchildreportid'];
        }

        $configready = $childreportid > 0;
        $sourcecolumnoptions = output_columns::get_select_options($sourcereport);
        $hascolumnmetadata = !empty($sourcecolumnoptions);

        $mform->addElement('header', 'crformheader', get_string('reportchain', 'block_configurable_reports'));

        $reports = $pluginclass->get_available_child_reports();
        $reportoptions = [0 => get_string('choose')];
        foreach ($reports as $report) {
            $reportoptions[$report->id] = format_string($report->name);
        }

        $mform->addElement('select', 'childreportid', get_string('chainchildreport', 'block_configurable_reports'),
            $reportoptions);
        $mform->setDefault('childreportid', $childreportid);
        $mform->addRule('childreportid', null, 'required', null, 'client');

        if (!$configready) {
            $mform->addElement('static', 'selectchildhint', '', get_string('chainselectchildhint', 'block_configurable_reports'));
            $mform->addElement('hidden', 'configstep', 1);
            $mform->setType('configstep', PARAM_INT);
            $buttonarray = [];
            $buttonarray[] = $mform->createElement('submit', 'continueconfig',
                get_string('chaincontinueconfig', 'block_configurable_reports'));
            $buttonarray[] = $mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
            return;
        }

        $mform->addElement('text', 'chainname', get_string('chainname', 'block_configurable_reports'), ['size' => 60]);
        $mform->setType('chainname', PARAM_TEXT);
        $mform->addHelpButton('chainname', 'chainname', 'block_configurable_reports');
        $mform->addRule('chainname', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', '', get_string('chainenabled', 'block_configurable_reports'));
        $mform->addHelpButton('enabled', 'chainenabled', 'block_configurable_reports');
        $mform->setDefault('enabled', 1);

        $mform->addElement('text', 'filenamepattern', get_string('chainfilenamepattern', 'block_configurable_reports'),
            ['size' => 60]);
        $mform->setType('filenamepattern', PARAM_RAW);
        $mform->setDefault('filenamepattern', '##reportname##_##row##');
        $mform->addHelpButton('filenamepattern', 'chainfilenamepattern', 'block_configurable_reports');

        if ($hascolumnmetadata) {
            $rowkeysize = min(10, max(3, count($sourcecolumnoptions)));
            $mform->addElement('select', 'rowkeycolumns', get_string('chainrowkeycolumns', 'block_configurable_reports'),
                $sourcecolumnoptions, ['multiple' => 'multiple', 'size' => $rowkeysize]);
            $mform->setType('rowkeycolumns', PARAM_RAW);
            $mform->addHelpButton('rowkeycolumns', 'chainrowkeycolumns', 'block_configurable_reports');
            $mform->addRule('rowkeycolumns', null, 'required', null, 'client');
        } else {
            if (($sourcereport->type ?? '') === 'sql') {
                $mform->addElement('static', 'nocolumnsmetadata', '',
                    get_string('chainresavesqlforcolumns', 'block_configurable_reports'));
            } else {
                $mform->addElement('static', 'nocolumnsmetadata', '',
                    get_string('chainnocolumnsmetadata', 'block_configurable_reports'));
            }
            $mform->addElement('text', 'rowkeycolumns', get_string('chainrowkeycolumns', 'block_configurable_reports'),
                ['size' => 60]);
            $mform->setType('rowkeycolumns', PARAM_RAW);
            $mform->addHelpButton('rowkeycolumns', 'chainrowkeycolumns', 'block_configurable_reports');
            $mform->addRule('rowkeycolumns', null, 'required', null, 'client');
        }

        $filteroptions = $pluginclass->get_child_filter_options($childreportid);
        if (empty($filteroptions)) {
            $mform->addElement('static', 'nofilters', '', get_string('chainnofilters', 'block_configurable_reports'));
        }

        $mappingcount = max(1, (int) ($this->_customdata['mappingcount'] ?? 1));
        $chooseoption = ['' => get_string('choose')];
        $mappingcolumnoptions = !empty($sourcecolumnoptions) ? ($chooseoption + $sourcecolumnoptions) : [];
        $mappingfilteroptions = !empty($filteroptions) ? ($chooseoption + $filteroptions) : [];

        $mform->addElement('static', 'mappingcolumnsheader', '',
            html_writer::div(
                get_string('chainmappingcolumnsheader', 'block_configurable_reports'),
                'chain-mapping-columns-header fw-bold mb-2'
            ));
        $this->add_mapping_groups($mform, $mappingcount, $mappingfilteroptions, $mappingcolumnoptions);

        $mform->addElement('hidden', 'mappingcount', $mappingcount);
        $mform->setType('mappingcount', PARAM_INT);

        if (!empty($this->_customdata['formbaseurl'])) {
            $addurl = $this->append_chain_url_params(new moodle_url($this->_customdata['formbaseurl'], [
                'mappingcount' => $mappingcount + 1,
                'childreportid' => $childreportid,
            ]));
            $mform->addElement('static', 'addmappinglink', '',
                html_writer::link('#', get_string('chainaddmapping', 'block_configurable_reports'), [
                    'onclick' => $this->build_chain_navigation_onclick($addurl->out(false)),
                ]));
        }

        $pluginclass->add_filter_config_action_buttons($this, $this->_customdata);
    }

    /**
     * Append current draft field values to a URL variable in JavaScript.
     *
     * @param string $urlvar
     * @return string
     */
    protected function build_draft_append_js(string $urlvar = 'u'): string {
        $script = "var n=document.getElementById('id_chainname');if(n&&n.value){" . $urlvar . "+='&draft_chainname='+encodeURIComponent(n.value)}";
        $script .= ";var e=document.getElementById('id_enabled');if(e){" . $urlvar . "+='&draft_enabled='+(e.checked?1:0)}";
        $script .= ";var f=document.getElementById('id_filenamepattern');if(f&&f.value){" . $urlvar . "+='&draft_filenamepattern='+encodeURIComponent(f.value)}";
        return $script;
    }

    /**
     * onclick handler for chain form links that must keep unsaved draft values.
     *
     * @param string $baseurl
     * @return string
     */
    protected function build_chain_navigation_onclick(string $baseurl): string {
        return "var u='" . $baseurl . "';" . $this->build_draft_append_js('u') . ";location.href=u;return false;";
    }

    /**
     * Append child report id and draft query params to a form navigation URL.
     *
     * @param moodle_url $url
     * @return moodle_url
     */
    protected function append_chain_url_params(moodle_url $url): moodle_url {
        foreach ($this->_customdata['chaindraftparams'] ?? [] as $name => $value) {
            $url->param($name, $value);
        }
        return $url;
    }

    /**
     * Add one form group per column-to-filter mapping.
     *
     * @param MoodleQuickForm $mform
     * @param int $mappingcount
     * @param array<string, string> $filteroptions
     * @param array<string, string> $sourcecolumnoptions
     * @return void
     */
    protected function add_mapping_groups($mform, int $mappingcount, array $filteroptions, array $sourcecolumnoptions = []): void {
        for ($i = 0; $i < $mappingcount; $i++) {
            $sourcefield = 'sourcecolumn' . $i;
            $targetfield = 'targetfilter' . $i;
            $groupelements = [];
            if (!empty($sourcecolumnoptions)) {
                $groupelements[] = $mform->createElement('select', $sourcefield, '', $sourcecolumnoptions);
            } else {
                $groupelements[] = $mform->createElement('text', $sourcefield, '', ['size' => 30]);
            }
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

            if ($mappingcount > 1 && !empty($this->_customdata['formbaseurl'])) {
                $removeurl = $this->append_chain_url_params(new moodle_url($this->_customdata['formbaseurl'], [
                    'removemapping' => $i,
                    'mappingcount' => $mappingcount,
                    'childreportid' => (int) ($this->_customdata['storedchildreportid'] ?? optional_param('childreportid', 0, PARAM_INT)),
                    'sesskey' => sesskey(),
                ]));
                $mform->addElement('static', 'removemapping' . $i, '',
                    html_writer::link('#', get_string('chainremovemapping', 'block_configurable_reports'), [
                        'class' => 'chain-remove-mapping small text-muted',
                        'onclick' => $this->build_chain_navigation_onclick($removeurl->out(false)),
                    ]));
            }
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

        if ((int) $data['childreportid'] === (int) $this->_customdata['report']->id) {
            $errors['childreportid'] = get_string('chainerror_selfreference', 'block_configurable_reports');
            return $errors;
        }

        if (!empty($data['configstep'])) {
            return $errors;
        }

        if (empty(trim((string) ($data['chainname'] ?? '')))) {
            $errors['chainname'] = get_string('chainerror_noname', 'block_configurable_reports');
        }

        $sourcereport = $this->_customdata['report'];
        $hascolumnmetadata = output_columns::has_metadata($sourcereport);
        $pluginclass = $this->_customdata['pluginclass'];
        $filteroptions = $pluginclass->get_child_filter_options((int) $data['childreportid']);

        if ($hascolumnmetadata) {
            $rowkeys = $this->normalise_rowkey_submission($data['rowkeycolumns'] ?? null);
            if (empty($rowkeys)) {
                $errors['rowkeycolumns'] = get_string('chainerror_norowkeys', 'block_configurable_reports');
            } else {
                foreach ($rowkeys as $column) {
                    if (!output_columns::is_known_column($sourcereport, $column)) {
                        $errors['rowkeycolumns'] = get_string('chainerror_unknowncolumn', 'block_configurable_reports', $column);
                        break;
                    }
                }
            }
        } else if (empty(trim((string) ($data['rowkeycolumns'] ?? '')))) {
            $errors['rowkeycolumns'] = get_string('chainerror_norowkeys', 'block_configurable_reports');
        }

        $hasmapping = false;
        $mappingcount = max(1, (int) ($data['mappingcount'] ?? 1));
        for ($i = 0; $i < $mappingcount; $i++) {
            $source = $this->read_mapping_field((object) $data, 'sourcecolumn', $i);
            $target = $this->read_mapping_field((object) $data, 'targetfilter', $i);
            if ($source !== '' && $target !== '') {
                $hasmapping = true;
                if ($hascolumnmetadata && !output_columns::is_known_column($sourcereport, $source)) {
                    $errors['mappinggroup' . $i] = get_string('chainerror_unknowncolumn', 'block_configurable_reports', $source);
                }
                if (!empty($filteroptions) && !array_key_exists($target, $filteroptions)) {
                    $errors['mappinggroup' . $i] = get_string('chainerror_unknownfilter', 'block_configurable_reports', $target);
                }
            }
        }
        if (!$hasmapping) {
            $errors['mappinggroup0'] = get_string('chainerror_nomappings', 'block_configurable_reports');
        }

        return $errors;
    }

    /**
     * Normalise row key columns from form submission (multiselect or text).
     *
     * @param mixed $rowkeys
     * @return array<int, string>
     */
    protected function normalise_rowkey_submission($rowkeys): array {
        if (is_array($rowkeys)) {
            return array_values(array_filter(array_map(function($value) {
                return trim((string) $value);
            }, $rowkeys)));
        }
        if (is_string($rowkeys) && trim($rowkeys) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $rowkeys))));
        }
        return [];
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
            if (!output_columns::has_metadata($this->_customdata['report'])) {
                $data->rowkeycolumns = implode(', ', $data->rowkeycolumns);
            }
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
        if (isset($data->rowkeycolumns)) {
            $data->rowkeycolumns = $this->normalise_rowkey_submission($data->rowkeycolumns);
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
        unset($data->mappingcount, $data->configstep, $data->continueconfig);
        return $data;
    }
}
