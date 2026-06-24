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

require_once('../../config.php');

require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/component.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/plugin.class.php');

use block_configurable_reports\chain\definition;
use block_configurable_reports\chain\export_job;

$id = required_param('id', PARAM_INT);
$comp = required_param('comp', PARAM_ALPHA);
$courseid = optional_param('courseid', null, PARAM_INT);

if (!$report = $DB->get_record('block_configurable_reports', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists');
}

// Ignore report's courseid, If we are running this report on a specific courseid
// (For permission checks).
if (empty($courseid)) {
    $courseid = $report->courseid;
}

if (!$course = $DB->get_record("course", ['id' => $courseid])) {
    throw new moodle_exception("No such course id");
}

// Force user login in course (SITE or Course).
if ($course->id == SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

$PAGE->set_url('/blocks/configurable_reports/editreport.php', ['id' => $id, 'comp' => $comp]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$PAGE->requires->js('/blocks/configurable_reports/js/configurable_reports.js');

$hasreportscap = has_capability('block/configurable_reports:managereports', $context);
if (!$hasreportscap && !has_capability('block/configurable_reports:manageownreports', $context)) {
    throw new moodle_exception('badpermissions');
}

if (!$hasreportscap && $report->ownerid != $USER->id) {
    throw new moodle_exception('badpermissions');
}

if ($report->type === 'sql' && !block_configurable_reports_can_managesqlreports($context)) {
    throw new moodle_exception('nosqlpermissions');
}

require_once($CFG->dirroot . '/blocks/configurable_reports/reports/' . $report->type . '/report.class.php');

$reportclassname = 'report_' . $report->type;
$reportclass = new $reportclassname($report->id);

if (!in_array($comp, $reportclass->components)) {
    throw new moodle_exception('badcomponent');
}

$elements = cr_unserialize($report->components);
$elements = $elements[$comp]['elements'] ?? [];

require_once($CFG->dirroot . '/blocks/configurable_reports/components/' . $comp . '/component.class.php');
$componentclassname = 'component_' . $comp;
$compclass = new $componentclassname($report->id);

if ($compclass->form) {
    require_once($CFG->dirroot . '/blocks/configurable_reports/components/' . $comp . '/form.php');
    $classname = $comp . '_form';
    $editform = new $classname(
        'editcomp.php?id=' . $id . '&comp=' . $comp,
        compact('compclass', 'comp', 'id', 'report', 'reportclass', 'elements')
    );

    if ($editform->is_cancelled()) {
        redirect($CFG->wwwroot . '/blocks/configurable_reports/editcomp.php?id=' . $id . '&amp;comp=' . $comp);
    } else if ($data = $editform->get_data()) {
        $compclass->form_process_data($editform);
        $PAGE->set_cacheable(false);
        $report = $DB->get_record('block_configurable_reports', ['id' => $id], '*', MUST_EXIST);
    }

    $compclass->form_set_data($editform);
}

if ($compclass->plugins) {
    $currentplugins = [];
    if ($elements) {
        foreach ($elements as $e) {
            $currentplugins[] = $e['pluginname'];
        }
    }
    $plugins = get_list_of_plugins('blocks/configurable_reports/components/' . $comp);
    $optionsplugins = [];
    foreach ($plugins as $p) {
        require_once($CFG->dirroot . '/blocks/configurable_reports/components/' . $comp . '/' . $p . '/plugin.class.php');
        $pluginclassname = 'plugin_' . $p;
        $pluginclass = new $pluginclassname($report);
        if (in_array($report->type, $pluginclass->reporttypes)) {
            if ($pluginclass->unique && in_array($p, $currentplugins)) {
                continue;
            }
            $optionsplugins[$p] = get_string($p, 'block_configurable_reports');
        }
    }
    asort($optionsplugins);
}

$managereporturl = new moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $courseid]);
$PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'), $managereporturl);

$title = format_string($report->name);
$PAGE->navbar->add($title);

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_cacheable(true);

echo $OUTPUT->header();

$currenttab = $comp;
require('tabs.php');

if ($comp === 'chains') {
    $viewreporturl = new moodle_url('/blocks/configurable_reports/viewreport.php', [
        'id' => $id,
        'courseid' => $courseid,
    ]);
    $helpurl = new moodle_url('/blocks/configurable_reports/chainhelp.php', [
        'id' => $id,
        'courseid' => $courseid,
    ]);
    $chainshelp = get_string('chains_usage_help', 'block_configurable_reports', (object) [
        'viewreportlink' => html_writer::link($viewreporturl, get_string('viewreport', 'block_configurable_reports')),
        'exportlink' => get_string('chainexportlink', 'block_configurable_reports'),
        'helplink' => html_writer::link($helpurl, get_string('chainhelp_link', 'block_configurable_reports')),
    ]);
    echo $OUTPUT->box($chainshelp, 'generalbox boxwidthnormal boxaligncenter chains-usage-help mb-3');
}

$filteranalysis = null;
if ($comp === 'filters' && $report->type === 'sql') {
    require_once($CFG->dirroot . '/blocks/configurable_reports/classes/filter_sql_analyzer.php');
    $allcomponents = cr_unserialize($report->components);
    $querysql = $allcomponents['customsql']['config']->querysql ?? '';
    $filteranalysis = \block_configurable_reports\filter_sql_analyzer::analyse($querysql, $elements);
}

if ($elements) {
    $table = new stdclass;
    $table->head = [get_string('idnumber'), get_string('name'), get_string('summary')];
    if ($filteranalysis !== null) {
        $table->head[] = get_string('filterusage_column', 'block_configurable_reports');
    }
    $table->head[] = get_string('edit');
    $i = 0;

    foreach ($elements as $idx => $e) {

        if (empty($e)) {
            continue;
        }

        require_once($CFG->dirroot . '/blocks/configurable_reports/components/' . $comp . '/' . $e['pluginname'] .
            '/plugin.class.php');
        $pluginclassname = 'plugin_' . $e['pluginname'];
        $pluginclass = new $pluginclassname($report);

        $editcell = '';

        if ($comp === 'chains') {
            $chainform = \block_configurable_reports\chain\definition::normalise_formdata((object) ($e['formdata'] ?? new stdClass()));
            $enabled = !empty($chainform->enabled);
            $toggleurl = new moodle_url('/blocks/configurable_reports/editplugin.php', [
                'id' => $id,
                'comp' => $comp,
                'pname' => $e['pluginname'],
                'cid' => $e['id'],
                'toggleenabled' => 1,
                'sesskey' => sesskey(),
            ]);
            $toggleicon = $enabled ? 't/hide' : 't/show';
            $togglelabel = $enabled
                ? get_string('chaindisable', 'block_configurable_reports')
                : get_string('chainenable', 'block_configurable_reports');
            $editcell .= html_writer::link($toggleurl, $OUTPUT->pix_icon($toggleicon, $togglelabel), [
                'title' => $togglelabel,
                'class' => 'action-icon chain-toggle-enabled',
            ]);
        }

        if ($pluginclass->form) {
            $editcell .= '<a href="editplugin.php?id=' . $id . '&comp=' . $comp . '&pname=' . $e['pluginname'] . '&cid=' .
                $e['id'] . '">' .
                $OUTPUT->pix_icon('t/edit', get_string('edit')) .
                '</a>';
        }

        $editcell .= '<a href="editplugin.php?id=' . $id . '&comp=' . $comp . '&pname=' . $e['pluginname'] .
            '&cid=' . $e['id'] . '&delete=1&amp;sesskey=' . sesskey() . '">' .
            $OUTPUT->pix_icon('t/delete', get_string('delete')) .
            '</a>';

        if ($compclass->ordering && $i != 0 && count($elements) > 1) {
            $editcell .= '<a href="editplugin.php?id=' . $id . '&comp=' . $comp . '&pname=' . $e['pluginname'] . '&cid=' .
                $e['id'] .
                '&moveup=1&amp;sesskey=' . sesskey() . '">' .
                $OUTPUT->pix_icon('t/up', get_string('moveup')) .
                '</a>';
        }
        if ($compclass->ordering && $i != count($elements) - 1) {
            $editcell .= '<a href="editplugin.php?id=' . $id . '&comp=' . $comp . '&pname=' . $e['pluginname'] . '&cid=' .
                $e['id'] .
                '&movedown=1&amp;sesskey=' . sesskey() . '">' .
                $OUTPUT->pix_icon('t/down', get_string('movedown')) .
                '</a>';
        }

        $namecell = $e['pluginfullname'];
        $summarycell = $e['summary'];
        if ($comp === 'chains') {
            if (!isset($chainform)) {
                $chainform = \block_configurable_reports\chain\definition::normalise_formdata(
                    (object) ($e['formdata'] ?? new stdClass())
                );
            }
            $chainchild = null;
            if (!empty($chainform->childreportid)) {
                $chainchild = $DB->get_record('block_configurable_reports', ['id' => (int) $chainform->childreportid],
                    'id,name', IGNORE_MISSING);
            }
            $namecell = \block_configurable_reports\chain\definition::get_chain_display_name($e, $chainchild ?: null);
            $summarycell = $pluginclass->summary($chainform);
        }

        $rowdata = ['c' . ($i + 1), $namecell, $summarycell];
        if ($filteranalysis !== null) {
            $usagecell = \block_configurable_reports\filter_sql_analyzer::format_notfound_html();
            if (isset($filteranalysis->filterrows[$idx])) {
                $userow = $filteranalysis->filterrows[$idx];
                if ($userow->status === 'used') {
                    $usagecell = \block_configurable_reports\filter_sql_analyzer::format_usages_html($userow->usages);
                } else if ($userow->status === 'duplicate') {
                    $usagecell = get_string('filterusage_duplicate', 'block_configurable_reports') . '<br />' .
                        \block_configurable_reports\filter_sql_analyzer::format_usages_html($userow->usages);
                }
            }
            $rowdata[] = $usagecell;
        }
        $rowdata[] = $editcell;
        $table->data[] = $rowdata;
        $i++;
    }
    cr_print_table($table);

    if ($comp === 'chains') {
        $userjobs = export_job::get_user_jobs_for_report((int) $USER->id, (int) $id);
        if ($userjobs) {
            echo $OUTPUT->heading(get_string('chainexportjobsheading', 'block_configurable_reports'), 4);
            $clearallurl = new moodle_url('/blocks/configurable_reports/chainexport.php', [
                'id' => $id,
                'courseid' => $courseid,
                'deletealljobs' => 1,
                'sesskey' => sesskey(),
            ]);
            echo html_writer::div(
                html_writer::link(
                    $clearallurl,
                    get_string('chainexportclearall', 'block_configurable_reports'),
                    ['class' => 'btn btn-secondary mb-2', 'onclick' => "return confirm('" .
                        s(get_string('chainexportclearallconfirm', 'block_configurable_reports')) . "');"]
                ),
                'chainexport-clearall mb-2'
            );
            $jobtable = new html_table();
            $jobtable->attributes['class'] = 'generaltable chainexport-jobs';
            $jobtable->head = [
                get_string('chainexportjobchain', 'block_configurable_reports'),
                get_string('chainexportjobstatus', 'block_configurable_reports'),
                get_string('chainexportjobprogress', 'block_configurable_reports'),
                get_string('chainexportjobcreated', 'block_configurable_reports'),
                get_string('chainexportjobfinished', 'block_configurable_reports'),
                get_string('edit'),
            ];
            foreach ($userjobs as $job) {
                $chainelement = definition::get_chain_element_by_id($report, $job->chainid);
                $chainlabel = $job->chainid;
                if ($chainelement) {
                    $chainform = definition::normalise_formdata((object) ($chainelement['formdata'] ?? new stdClass()));
                    $chainchild = null;
                    if (!empty($chainform->childreportid)) {
                        $chainchild = $DB->get_record('block_configurable_reports', ['id' => (int) $chainform->childreportid],
                            'id,name', IGNORE_MISSING);
                    }
                    $chainlabel = definition::get_chain_display_name($chainelement, $chainchild ?: null);
                }
                $monitorurl = new moodle_url('/blocks/configurable_reports/chainexport.php', [
                    'id' => $id,
                    'chainid' => $job->chainid,
                    'courseid' => $courseid,
                    'jobid' => (int) $job->id,
                ]);
                $actions = html_writer::link($monitorurl, get_string('chainexportjobmonitor', 'block_configurable_reports'));
                $downloadable = in_array($job->status, [export_job::STATUS_COMPLETED, export_job::STATUS_PARTIAL], true)
                    && empty($job->zipdownloaded)
                    && (int) $job->timeexpires > time()
                    && export_job::resolve_zip_path($job) !== null;
                if ($downloadable) {
                    $downloadurl = new moodle_url('/blocks/configurable_reports/chainexport.php', [
                        'id' => $id,
                        'chainid' => $job->chainid,
                        'courseid' => $courseid,
                        'jobid' => (int) $job->id,
                        'downloadzip' => 1,
                        'sesskey' => sesskey(),
                    ]);
                    $actions .= ' ' . html_writer::link($downloadurl, get_string('chainexportdownloadzip', 'block_configurable_reports'));
                }
                if (export_job::is_deletable($job)) {
                    $deleteurl = new moodle_url('/blocks/configurable_reports/chainexport.php', [
                        'id' => $id,
                        'chainid' => $job->chainid,
                        'courseid' => $courseid,
                        'jobid' => (int) $job->id,
                        'deletejob' => 1,
                        'sesskey' => sesskey(),
                    ]);
                    $actions .= ' ' . html_writer::link(
                        $deleteurl,
                        get_string('chainexportdelete', 'block_configurable_reports'),
                        ['onclick' => "return confirm('" . s(get_string('chainexportdeleteconfirm', 'block_configurable_reports')) . "');"]
                    );
                } else if (export_job::is_dismissable($job)) {
                    $dismissurl = new moodle_url('/blocks/configurable_reports/chainexport.php', [
                        'id' => $id,
                        'chainid' => $job->chainid,
                        'courseid' => $courseid,
                        'jobid' => (int) $job->id,
                        'dismissjob' => 1,
                        'sesskey' => sesskey(),
                    ]);
                    $actions .= ' ' . html_writer::link($dismissurl, get_string('chainexportdismiss', 'block_configurable_reports'));
                }
                $jobtable->data[] = [
                    s($chainlabel),
                    get_string('chainexportstatus_' . $job->status, 'block_configurable_reports'),
                    (int) $job->progressdone . ' / ' . (int) $job->progresstotal,
                    userdate((int) $job->timecreated),
                    !empty($job->timefinished) ? userdate((int) $job->timefinished) : '-',
                    $actions,
                ];
            }
            echo html_writer::table($jobtable);
        }
    }
} else if ($compclass->plugins) {
    echo $OUTPUT->heading(get_string('no' . $comp . 'yet', 'block_configurable_reports'));
}

if ($filteranalysis !== null) {
    if (!empty($filteranalysis->notices)) {
        foreach ($filteranalysis->notices as $noticekey) {
            echo $OUTPUT->notification(get_string($noticekey, 'block_configurable_reports'), 'info');
        }
    }
    if (!empty($filteranalysis->missing)) {
        echo $OUTPUT->heading(get_string('filtersql_missing_heading', 'block_configurable_reports'), 4);
        $missingtable = new html_table();
        $missingtable->head = [
            get_string('filtersql_placeholder', 'block_configurable_reports'),
            get_string('filtersql_detail', 'block_configurable_reports'),
            get_string('edit'),
        ];
        foreach ($filteranalysis->missing as $missing) {
            $addlinks = [];
            foreach ($missing->suggestedplugins as $splugin) {
                $params = [
                    'id' => $id,
                    'comp' => $comp,
                    'pname' => $splugin,
                ];
                if (!empty($missing->prefill->idnumber)) {
                    $params['prefill_idnumber'] = $missing->prefill->idnumber;
                }
                if (!empty($missing->prefill->field)) {
                    $params['prefill_field'] = $missing->prefill->field;
                }
                if (!empty($missing->prefill->label)) {
                    $params['prefill_label'] = $missing->prefill->label;
                }
                $url = new moodle_url('/blocks/configurable_reports/editplugin.php', $params);
                $addlinks[] = html_writer::link($url, get_string('filtersql_addfilter', 'block_configurable_reports') .
                    ' (' . get_string($splugin, 'block_configurable_reports') . ')');
            }
            $missingtable->data[] = [
                s($missing->placeholder),
                $missing->detail,
                implode('<br />', $addlinks),
            ];
        }
        echo html_writer::table($missingtable);
    }
}

if ($compclass->plugins) {
    echo '<div class="boxaligncenter">';
    echo '<p class="centerpara">';
    print_string('add');
    echo ': &nbsp;';

    $attributes = ['id' => 'menuplugin'];

    echo html_writer::select($optionsplugins, 'plugin', '', ['' => get_string('choose')], $attributes);
    $OUTPUT->add_action_handler(
        new component_action('change', 'menuplugin', ['url' => "editplugin.php?id=" . $id . "&comp=" . $comp . "&pname="]),
        'menuplugin'
    );
    echo '</p>';
    echo '</div>';
}

if ($compclass->form) {
    $editform->display();
    if ($comp === 'customsql' && $compclass instanceof component_customsql) {
        $compclass->print_output_columns_diagnostic();
    }
}

if ($compclass->help) {
    echo '<div class="boxaligncenter">';
    echo '<p class="centerpara">';
    echo $OUTPUT->help_icon(
        'comp_' . $comp,
        'block_configurable_reports',
        get_string('comp_' . $comp, 'block_configurable_reports')
    );
    echo '</p>';
    echo '</div>';
}

echo $OUTPUT->footer();
