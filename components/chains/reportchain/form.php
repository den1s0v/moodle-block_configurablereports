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

use block_configurable_reports\chain\filter_params;
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
        } else {
            $mform->addElement('header', 'filterbindingsheader',
                get_string('chainfilterbindingsheader', 'block_configurable_reports'));
            $mform->addHelpButton('filterbindingsheader', 'chainfilterbindingsheader', 'block_configurable_reports');
            $chooseoption = ['' => get_string('choose')];
            $bindingcolumnoptions = !empty($sourcecolumnoptions) ? ($chooseoption + $sourcecolumnoptions) : [];
            $modeoptions = filter_params::get_mode_options();
            $this->add_filter_binding_groups($mform, $filteroptions, $bindingcolumnoptions, $modeoptions);
            $mform->addElement('hidden', 'filterbindingcount', count($filteroptions));
            $mform->setType('filterbindingcount', PARAM_INT);
        }

        $pluginclass->add_filter_config_action_buttons($this, $this->_customdata);
    }

    /**
     * Add one form group per child report filter binding.
     *
     * @param MoodleQuickForm $mform
     * @param array<string, string> $filteroptions
     * @param array<string, string> $sourcecolumnoptions
     * @param array<string, string> $modeoptions
     * @return void
     */
    protected function add_filter_binding_groups($mform, array $filteroptions, array $sourcecolumnoptions,
            array $modeoptions): void {
        $i = 0;
        foreach ($filteroptions as $paramname => $filterlabel) {
            $targetfield = 'targetfilter' . $i;
            $modefield = 'mode' . $i;
            $sourcefield = 'sourcecolumn' . $i;
            $constantfield = 'constantvalue' . $i;

            $mform->addElement('hidden', $targetfield, $paramname);
            $mform->setType($targetfield, PARAM_RAW);

            $groupelements = [];
            $groupelements[] = $mform->createElement('select', $modefield, '', $modeoptions);
            if (!empty($sourcecolumnoptions)) {
                $groupelements[] = $mform->createElement('select', $sourcefield, '', $sourcecolumnoptions);
            } else {
                $groupelements[] = $mform->createElement('text', $sourcefield, '', ['size' => 30]);
            }
            $groupelements[] = $mform->createElement('text', $constantfield, '', ['size' => 30]);

            $mform->addGroup(
                $groupelements,
                'filterbindinggroup' . $i,
                $filterlabel,
                html_writer::span(' | ', 'px-2'),
                false
            );
            $mform->setType($modefield, PARAM_ALPHA);
            $mform->setType($sourcefield, PARAM_RAW);
            $mform->setType($constantfield, PARAM_RAW);
            $mform->setDefault($modefield, filter_params::MODE_EMPTY);

            $mform->hideIf($sourcefield, $modefield, 'neq', filter_params::MODE_COLUMN);
            $mform->hideIf($constantfield, $modefield, 'neq', filter_params::MODE_CONSTANT);

            $i++;
        }
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

        if (!empty($filteroptions)) {
            $bindingcount = max(0, (int) ($data['filterbindingcount'] ?? count($filteroptions)));
            for ($i = 0; $i < $bindingcount; $i++) {
                $target = $this->read_binding_field((object) $data, 'targetfilter', $i);
                $mode = $this->read_binding_field((object) $data, 'mode', $i);
                $targetlabel = $filteroptions[$target] ?? $target;
                if ($target === '' || !array_key_exists($target, $filteroptions)) {
                    $errors['filterbindinggroup' . $i] = get_string('chainerror_unknownfilter', 'block_configurable_reports', $targetlabel);
                    continue;
                }
                if (!filter_params::is_valid_mode($mode)) {
                    $errors['filterbindinggroup' . $i] = get_string('chainerror_invalidfiltermode', 'block_configurable_reports', $targetlabel);
                    continue;
                }
                if ($mode === filter_params::MODE_COLUMN) {
                    $source = $this->read_binding_field((object) $data, 'sourcecolumn', $i);
                    if ($source === '') {
                        $errors['filterbindinggroup' . $i] = get_string('chainerror_nocolumnsource', 'block_configurable_reports', $targetlabel);
                    } else if ($hascolumnmetadata && !output_columns::is_known_column($sourcereport, $source)) {
                        $errors['filterbindinggroup' . $i] = get_string('chainerror_unknowncolumn', 'block_configurable_reports', $source);
                    }
                } else if ($mode === filter_params::MODE_CONSTANT) {
                    $constant = $this->read_binding_field((object) $data, 'constantvalue', $i);
                    if ($constant === '') {
                        $errors['filterbindinggroup' . $i] = get_string('chainerror_noconstantvalue', 'block_configurable_reports', $targetlabel);
                    }
                }
            }
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
     * Read a filter binding field from form data (top-level or inside a form group).
     *
     * @param object $data
     * @param string $field
     * @param int $index
     * @return string
     */
    protected function read_binding_field(object $data, string $field, int $index): string {
        $key = $field . $index;
        if (property_exists($data, $key)) {
            return trim((string) $data->$key);
        }

        $groupkey = 'filterbindinggroup' . $index;
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
     * Expand stored filter bindings for form fields.
     *
     * @param object $data
     * @return void
     */
    public function set_data($data): void {
        $data = (object) $data;
        $childreportid = (int) ($data->childreportid ?? $this->_customdata['storedchildreportid'] ?? 0);
        $filteroptions = [];
        if ($childreportid > 0) {
            $filteroptions = $this->_customdata['pluginclass']->get_child_filter_options($childreportid);
        }

        $bindingsbytarget = [];
        $rawbindings = $data->filterbindings ?? $data->mappings ?? [];
        if (!empty($rawbindings)) {
            foreach ($rawbindings as $binding) {
                $binding = (object) $binding;
                $target = trim((string) ($binding->targetfilter ?? ''));
                if ($target === '') {
                    continue;
                }
                $bindingsbytarget[$target] = $binding;
            }
        }

        $index = 0;
        foreach ($filteroptions as $paramname => $unused) {
            $binding = $bindingsbytarget[$paramname] ?? null;
            $data->{'targetfilter' . $index} = $paramname;
            if ($binding !== null) {
                $mode = $binding->mode ?? filter_params::MODE_COLUMN;
                if (!filter_params::is_valid_mode($mode)) {
                    $mode = filter_params::MODE_COLUMN;
                }
                if (!isset($binding->mode) && isset($binding->sourcecolumn)) {
                    $mode = filter_params::MODE_COLUMN;
                }
                $data->{'mode' . $index} = $mode;
                $data->{'sourcecolumn' . $index} = $binding->sourcecolumn ?? '';
                $data->{'constantvalue' . $index} = $binding->constantvalue ?? '';
            } else {
                $data->{'mode' . $index} = filter_params::MODE_EMPTY;
                $data->{'sourcecolumn' . $index} = '';
                $data->{'constantvalue' . $index} = '';
            }
            $index++;
        }
        if ($index > 0) {
            $data->filterbindingcount = $index;
        }

        if (!empty($data->rowkeycolumns) && is_array($data->rowkeycolumns)) {
            if (!output_columns::has_metadata($this->_customdata['report'])) {
                $data->rowkeycolumns = implode(', ', $data->rowkeycolumns);
            }
        }
        parent::set_data($data);
    }

    /**
     * Prepare filter bindings for storage.
     *
     * @param object $data
     * @return object
     */
    public function prepare_filterbinding_data(object $data): object {
        if (!isset($data->enabled)) {
            $data->enabled = 0;
        }
        if (isset($data->rowkeycolumns)) {
            $data->rowkeycolumns = $this->normalise_rowkey_submission($data->rowkeycolumns);
        }
        $bindings = [];
        $bindingcount = max(0, (int) ($data->filterbindingcount ?? 0));
        for ($i = 0; $i < $bindingcount; $i++) {
            $target = $this->read_binding_field($data, 'targetfilter', $i);
            $mode = $this->read_binding_field($data, 'mode', $i);
            if ($target === '' || !filter_params::is_valid_mode($mode)) {
                continue;
            }
            $bindings[] = (object) [
                'targetfilter' => $target,
                'mode' => $mode,
                'sourcecolumn' => $this->read_binding_field($data, 'sourcecolumn', $i),
                'constantvalue' => $this->read_binding_field($data, 'constantvalue', $i),
            ];
        }
        $data->filterbindings = $bindings;
        for ($i = 0; $i < $bindingcount; $i++) {
            unset(
                $data->{'targetfilter' . $i},
                $data->{'mode' . $i},
                $data->{'sourcecolumn' . $i},
                $data->{'constantvalue' . $i},
                $data->{'filterbindinggroup' . $i}
            );
        }
        unset($data->filterbindingcount, $data->configstep, $data->continueconfig);
        return $data;
    }

    /**
     * @deprecated Use prepare_filterbinding_data().
     * @param object $data
     * @return object
     */
    public function prepare_mapping_data(object $data): object {
        return $this->prepare_filterbinding_data($data);
    }
}
