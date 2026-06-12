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
 * Chain export UI and download endpoint.
 *
 * @package   block_configurable_reports
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');

use block_configurable_reports\chain\definition;
use block_configurable_reports\chain\export_result;
use block_configurable_reports\chain\runner;
use block_configurable_reports\chain\temp_file_cleanup;
use block_configurable_reports\form\chain_export_form;

/**
 * Remove a ZIP file referenced in the current user's chain export session.
 *
 * @return void
 */
function block_configurable_reports_chainexport_discard_session_zip(): void {
    global $SESSION;
    if (!empty($SESSION->block_configurable_reports_chainexport['zippath'])) {
        temp_file_cleanup::delete_file_if_exists($SESSION->block_configurable_reports_chainexport['zippath']);
    }
}

/**
 * Render export summary after a chain bulk export.
 *
 * @param renderer_base $output
 * @param export_result $result
 * @return void
 */
function block_configurable_reports_render_chainexport_summary($output, export_result $result): void {
    if (!empty($result->exported)) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable chainexport-summary';
        $table->head = [
            get_string('chainexportsummaryrow', 'block_configurable_reports'),
            get_string('chainexportsummaryfile', 'block_configurable_reports'),
        ];
        foreach ($result->exported as $item) {
            $table->data[] = [s($item->label), s($item->filename)];
        }
        echo html_writer::tag('h4', get_string('chainexportsummaryheading', 'block_configurable_reports'));
        echo html_writer::table($table);
    }

    if (!empty($result->skipped)) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable chainexport-summary';
        $table->head = [
            get_string('chainexportsummaryrow', 'block_configurable_reports'),
            get_string('chainexportsummaryreason', 'block_configurable_reports'),
        ];
        foreach ($result->skipped as $item) {
            $table->data[] = [s($item->label), s($item->reason)];
        }
        echo html_writer::tag('h4', get_string('chainexportskippedheading', 'block_configurable_reports'));
        echo html_writer::table($table);
    }

    if (!$result->has_exports()) {
        echo $output->notification(get_string('chainexportnoexported', 'block_configurable_reports'), 'warning');
    }
}

/**
 * Render a back link with a left arrow at the top of the page.
 *
 * @param renderer_base $output
 * @param moodle_url $url
 * @return void
 */
function block_configurable_reports_chainexport_render_back_link($output, moodle_url $url): void {
    echo html_writer::div(
        html_writer::link($url, $output->larrow() . ' ' . get_string('back'), ['class' => 'chainexport-backlink']),
        'mb-3'
    );
}

/**
 * Print standard report management tabs when the user can manage the report.
 *
 * @param object $report
 * @param report_base $reportclass
 * @param context $context
 * @return void
 */
function block_configurable_reports_chainexport_print_tabs(object $report, report_base $reportclass, context $context): void {
    global $USER;

    $hasmanageallcap = has_capability('block/configurable_reports:managereports', $context);
    $hasmanageowncap = has_capability('block/configurable_reports:manageownreports', $context);
    if ($hasmanageallcap || ($hasmanageowncap && $report->ownerid == $USER->id)) {
        $currenttab = 'viewreport';
        include($GLOBALS['CFG']->dirroot . '/blocks/configurable_reports/tabs.php');
    }
}

$id = required_param('id', PARAM_INT);
$chainid = optional_param('chainid', '', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', null, PARAM_INT);
$downloadzip = optional_param('downloadzip', 0, PARAM_BOOL);
$exportdone = optional_param('exportdone', 0, PARAM_BOOL);
$exportformat = optional_param('exportformat', '', PARAM_ALPHA);

$filterparams = [];
$request = array_merge($_POST, $_GET);
foreach ($request as $key => $val) {
    if (strpos($key, 'filter_') === 0) {
        $filterparams[$key] = $val;
    }
}

if (!$report = $DB->get_record('block_configurable_reports', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_configurable_reports');
}

if ($courseid && $report->global) {
    $report->courseid = $courseid;
} else {
    $courseid = $report->courseid;
}

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception('error');
}

if ((int) $course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

require_once($CFG->dirroot . '/blocks/configurable_reports/reports/' . $report->type . '/report.class.php');
$reportclassname = 'report_' . $report->type;
$reportclass = new $reportclassname($report);

if (!$reportclass->check_permissions($USER->id, $context)) {
    throw new moodle_exception('badpermissions', 'block_configurable_reports');
}

if ($downloadzip) {
    require_sesskey();
    $redirecturl = new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
        'id' => $id,
        'chainid' => $chainid,
        'courseid' => $courseid,
        'exportdone' => 1,
        'exportformat' => $exportformat,
    ], $filterparams));

    $sessionexport = $SESSION->block_configurable_reports_chainexport ?? null;
    $validsession = !empty($sessionexport)
        && ($sessionexport['userid'] ?? 0) == $USER->id
        && ($sessionexport['reportid'] ?? 0) == $id
        && ($sessionexport['chainid'] ?? '') === $chainid;

    if (!$validsession || empty($sessionexport['zippath']) || !is_file($sessionexport['zippath'])) {
        if ($validsession) {
            $SESSION->block_configurable_reports_chainexport['zippath'] = null;
            $SESSION->block_configurable_reports_chainexport['zipdownloaded'] = true;
        }
        redirect($redirecturl, get_string('chainerror_nozip', 'block_configurable_reports'),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    $zippath = $sessionexport['zippath'];
    $zipfilename = $sessionexport['zipfilename'] ?? basename($zippath);
    $SESSION->block_configurable_reports_chainexport['zippath'] = null;
    $SESSION->block_configurable_reports_chainexport['zipdownloaded'] = true;
    send_temp_file($zippath, $zipfilename);
}

$activechains = definition::get_active_chain_elements($report);
if (empty($activechains)) {
    throw new moodle_exception('chainerror_nochains', 'block_configurable_reports');
}

$singlechainmode = (count($activechains) === 1);
if ($chainid === '' && $singlechainmode) {
    redirect(new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
        'id' => $id,
        'chainid' => $activechains[0]['id'],
        'courseid' => $courseid,
        'exportformat' => $exportformat,
    ], $filterparams)));
}

$reportname = format_string($report->name);
$hasmanageallcap = has_capability('block/configurable_reports:managereports', $context);
$hasmanageowncap = has_capability('block/configurable_reports:manageownreports', $context);
$canmanage = ($hasmanageallcap || ($hasmanageowncap && $report->ownerid == $USER->id));

$pagetitle = get_string('chainexport', 'block_configurable_reports');
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/blocks/configurable_reports/chainexport.php', ['id' => $id]);
$PAGE->set_title($reportname);
$PAGE->set_heading($reportname);
$PAGE->set_cacheable(true);

if ($canmanage) {
    $managereporturl = new moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $courseid]);
    $PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'), $managereporturl);
    $PAGE->navbar->add($reportname);
} else {
    $PAGE->navbar->add(get_string('viewreport', 'block_configurable_reports'));
    $PAGE->navbar->add($reportname);
}

$viewreportparams = array_merge(['id' => $id, 'courseid' => $courseid], $filterparams);
$viewreporturl = new moodle_url('/blocks/configurable_reports/viewreport.php', $viewreportparams);
$chainlisturl = new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
    'id' => $id,
    'courseid' => $courseid,
], $filterparams));
$chainexportbackurl = $singlechainmode ? $viewreporturl : $chainlisturl;

if ($chainid) {
    $chainelement = definition::get_chain_element_by_id($report, $chainid);
    if (!$chainelement) {
        throw new moodle_exception('chainerror_invalid', 'block_configurable_reports');
    }

    $validation = definition::validate_element($report, $chainelement);
    if (!$validation->valid) {
        throw new moodle_exception('chainerror_invalid', 'block_configurable_reports', '', $validation->error);
    }

    global $DB;
    $childreport = $DB->get_record('block_configurable_reports', [
        'id' => (int) definition::normalise_formdata((object) $chainelement['formdata'])->childreportid,
    ], '*', MUST_EXIST);
    $formats = definition::get_allowed_export_formats($childreport);
    $chaincontextlabel = definition::get_chain_list_label($chainelement, $childreport);
    $pageheading = get_string('chainexportheadingcontext', 'block_configurable_reports', (object) [
        'source' => $reportname,
        'chain' => $chaincontextlabel,
    ]);

    $runnerinstance = new runner($report, $chainelement, $context, (int) $USER->id);
    $rows = $runnerinstance->get_parent_row_descriptors();
    $head = $runnerinstance->get_parent_table_head();

    $selectedrowkeys = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedrowkeys = chain_export_form::extract_selected_rowkeys_from_submission();
    }

    $formparams = array_merge([
        'id' => $id,
        'chainid' => $chainid,
        'courseid' => $courseid,
    ], $filterparams);
    if ($exportformat !== '' && isset($formats[$exportformat])) {
        $formparams['exportformat'] = $exportformat;
    }
    $formurl = new moodle_url('/blocks/configurable_reports/chainexport.php', $formparams);
    $form = new chain_export_form($formurl->out(false), [
        'reportid' => $id,
        'chainid' => $chainid,
        'courseid' => $courseid,
        'rows' => $rows,
        'head' => $head,
        'formats' => $formats,
        'filterparams' => $filterparams,
        'selectedrowkeys' => $selectedrowkeys,
    ]);

    if ($form->is_cancelled()) {
        redirect($chainexportbackurl);
    }

    if ($form->is_submitted() && $form->is_validated()) {
        $data = $form->get_data();
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        block_configurable_reports_chainexport_discard_session_zip();

        $selectedrowkeys = chain_export_form::extract_selected_rowkeys_from_submission();
        $exportresult = $runnerinstance->export_selected_rows($selectedrowkeys, $data->exportformat);

        if ($exportresult->has_exports()) {
            $SESSION->block_configurable_reports_chainexport = [
                'userid' => (int) $USER->id,
                'reportid' => (int) $id,
                'chainid' => $chainid,
                'zippath' => $exportresult->zippath,
                'zipfilename' => $exportresult->zipfilename,
                'exported' => $exportresult->exported,
                'skipped' => $exportresult->skipped,
                'zipdownloaded' => false,
            ];
        } else {
            unset($SESSION->block_configurable_reports_chainexport);
            $SESSION->block_configurable_reports_chainexport_summary = [
                'userid' => (int) $USER->id,
                'reportid' => (int) $id,
                'chainid' => $chainid,
                'exported' => [],
                'skipped' => $exportresult->skipped,
            ];
        }

        redirect(new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
            'id' => $id,
            'chainid' => $chainid,
            'courseid' => $courseid,
            'exportdone' => 1,
            'exportformat' => $data->exportformat,
        ], $filterparams)));
    }

    $defaultdata = new stdClass();
    if ($form->is_submitted()) {
        $submitted = $form->get_submitted_data();
        if (!empty($submitted->exportformat)) {
            $defaultdata->exportformat = $submitted->exportformat;
        }
    } else if ($exportformat !== '' && isset($formats[$exportformat])) {
        $defaultdata->exportformat = $exportformat;
    } else if (!empty($formats)) {
        $defaultdata->exportformat = array_key_first($formats);
    }
    $form->set_data($defaultdata);

    $norowsmessage = json_encode(get_string('chainexportnorowsselected', 'block_configurable_reports'));
    $PAGE->requires->js_amd_inline(<<<EOT
require(['jquery'], function($) {
    var selectall = $('#chainexport-selectall');
    var rowboxes = $('.chainexport-rowcb');
    var norowsmessage = {$norowsmessage};

    function syncSelectAll() {
        var allchecked = rowboxes.length > 0 && rowboxes.filter(':checked').length === rowboxes.length;
        selectall.prop('checked', allchecked);
    }

    selectall.on('change', function() {
        rowboxes.prop('checked', selectall.prop('checked'));
    });
    rowboxes.on('change', syncSelectAll);
    syncSelectAll();

    $('#mform1').on('submit', function(e) {
        if (rowboxes.length > 0 && rowboxes.filter(':checked').length === 0) {
            e.preventDefault();
            window.alert(norowsmessage);
        }
    });

    $('#id_exportformat').on('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('exportformat', $(this).val());
        window.history.replaceState({}, '', url);
    });
});
EOT
    );

    echo $OUTPUT->header();
    block_configurable_reports_chainexport_print_tabs($report, $reportclass, $context);
    block_configurable_reports_chainexport_render_back_link($OUTPUT, $chainexportbackurl);
    echo $OUTPUT->heading($pageheading);

    if ($exportdone) {
        $summaryresult = new export_result();
        if (!empty($SESSION->block_configurable_reports_chainexport)
            && ($SESSION->block_configurable_reports_chainexport['userid'] ?? 0) == $USER->id
            && ($SESSION->block_configurable_reports_chainexport['reportid'] ?? 0) == $id
            && ($SESSION->block_configurable_reports_chainexport['chainid'] ?? '') === $chainid) {
            $summaryresult->exported = $SESSION->block_configurable_reports_chainexport['exported'] ?? [];
            $summaryresult->skipped = $SESSION->block_configurable_reports_chainexport['skipped'] ?? [];
            $summaryresult->zippath = $SESSION->block_configurable_reports_chainexport['zippath'] ?? null;
            $summaryresult->zipfilename = $SESSION->block_configurable_reports_chainexport['zipfilename'] ?? 'chainexport.zip';
            $zipdownloaded = !empty($SESSION->block_configurable_reports_chainexport['zipdownloaded']);

            $showdownload = !empty($summaryresult->zippath) && is_file($summaryresult->zippath);
            if ($showdownload) {
                $downloadurl = new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
                    'id' => $id,
                    'chainid' => $chainid,
                    'courseid' => $courseid,
                    'downloadzip' => 1,
                    'exportformat' => $exportformat,
                    'sesskey' => sesskey(),
                ], $filterparams));
                $exportedcount = count($summaryresult->exported);
                echo html_writer::div(
                    html_writer::tag('p', get_string('chainexportdownloadready', 'block_configurable_reports', $exportedcount),
                        ['class' => 'chainexport-downloadready mb-2']) .
                    html_writer::link(
                        $downloadurl,
                        get_string('chainexportdownloadzip', 'block_configurable_reports'),
                        ['class' => 'btn btn-primary']
                    ),
                    'mb-3 chainexport-downloadzip'
                );
            } else if ($zipdownloaded || $summaryresult->has_exports()) {
                echo $OUTPUT->notification(get_string('chainexportzipalreadydownloaded', 'block_configurable_reports'), 'info');
            }
        } else if (!empty($SESSION->block_configurable_reports_chainexport_summary)
            && ($SESSION->block_configurable_reports_chainexport_summary['userid'] ?? 0) == $USER->id
            && ($SESSION->block_configurable_reports_chainexport_summary['reportid'] ?? 0) == $id
            && ($SESSION->block_configurable_reports_chainexport_summary['chainid'] ?? '') === $chainid) {
            $summaryresult->skipped = $SESSION->block_configurable_reports_chainexport_summary['skipped'] ?? [];
            unset($SESSION->block_configurable_reports_chainexport_summary);
        }

        block_configurable_reports_render_chainexport_summary($OUTPUT, $summaryresult);
        echo html_writer::empty_tag('hr');
    }

    echo html_writer::tag('p', get_string('chainexportintro', 'block_configurable_reports'));
    if ($form->is_submitted() && !$form->is_validated()) {
        echo $OUTPUT->notification(get_string('chainexportvalidationfailed', 'block_configurable_reports'), 'notifyerror');
    }
    if (empty($rows)) {
        echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'), 'info');
    } else {
        $form->display();
    }
    echo $OUTPUT->single_button($chainexportbackurl, get_string('back'), 'get');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
block_configurable_reports_chainexport_print_tabs($report, $reportclass, $context);
block_configurable_reports_chainexport_render_back_link($OUTPUT, $viewreporturl);
echo $OUTPUT->heading($pagetitle);
echo html_writer::tag('p', get_string('chainexportchoose', 'block_configurable_reports'));

echo html_writer::start_tag('ul', ['class' => 'chainexportlist']);
foreach ($activechains as $chainelement) {
    $formdata = definition::normalise_formdata((object) ($chainelement['formdata'] ?? new stdClass()));
    $child = $DB->get_record('block_configurable_reports', ['id' => (int) $formdata->childreportid], 'id,name', IGNORE_MISSING);
    $label = definition::get_chain_list_label($chainelement, $child ?: null);
    $url = new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
        'id' => $id,
        'chainid' => $chainelement['id'],
        'courseid' => $courseid,
    ], $filterparams));
    echo html_writer::tag('li', html_writer::link($url, $label));
}
echo html_writer::end_tag('ul');

echo $OUTPUT->single_button($viewreporturl, get_string('back'), 'get');
echo $OUTPUT->footer();
