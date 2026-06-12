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
use block_configurable_reports\form\chain_export_form;

/**
 * Render export summary after a chain bulk export.
 *
 * @param renderer_base $output
 * @param export_result $result
 * @return void
 */
function block_configurable_reports_render_chainexport_summary($output, export_result $result): void {
    if (!empty($result->exported)) {
        echo html_writer::tag('h4', get_string('chainexportsummaryheading', 'block_configurable_reports'));
        echo html_writer::start_tag('ul');
        foreach ($result->exported as $item) {
            echo html_writer::tag('li', s($item->label) . ' — ' . s($item->filename));
        }
        echo html_writer::end_tag('ul');
    }

    if (!empty($result->skipped)) {
        echo html_writer::tag('h4', get_string('chainexportskippedheading', 'block_configurable_reports'));
        echo html_writer::start_tag('ul');
        foreach ($result->skipped as $item) {
            echo html_writer::tag('li', s($item->label) . ' — ' . s($item->reason));
        }
        echo html_writer::end_tag('ul');
    }

    if (!$result->has_exports()) {
        echo $output->notification(get_string('chainexportnoexported', 'block_configurable_reports'), 'warning');
    }
}

$id = required_param('id', PARAM_INT);
$chainid = optional_param('chainid', '', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', null, PARAM_INT);
$downloadzip = optional_param('downloadzip', 0, PARAM_BOOL);
$exportdone = optional_param('exportdone', 0, PARAM_BOOL);

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
    if (empty($SESSION->block_configurable_reports_chainexport)
        || ($SESSION->block_configurable_reports_chainexport['userid'] ?? 0) != $USER->id
        || ($SESSION->block_configurable_reports_chainexport['reportid'] ?? 0) != $id
        || empty($SESSION->block_configurable_reports_chainexport['zippath'])) {
        throw new moodle_exception('chainerror_nozip', 'block_configurable_reports');
    }

    $zippath = $SESSION->block_configurable_reports_chainexport['zippath'];
    $zipfilename = $SESSION->block_configurable_reports_chainexport['zipfilename'] ?? basename($zippath);
    unset($SESSION->block_configurable_reports_chainexport);
    send_temp_file($zippath, $zipfilename);
}

$activechains = definition::get_active_chain_elements($report);
if (empty($activechains)) {
    throw new moodle_exception('chainerror_nochains', 'block_configurable_reports');
}

$filterparams = [];
$request = array_merge($_POST, $_GET);
foreach ($request as $key => $val) {
    if (strpos($key, 'filter_') === 0) {
        $filterparams[$key] = $val;
    }
}

$pagetitle = get_string('chainexport', 'block_configurable_reports');
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/blocks/configurable_reports/chainexport.php', ['id' => $id]);
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($report->name));

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

    $runnerinstance = new runner($report, $chainelement, $context, (int) $USER->id);
    $rows = $runnerinstance->get_parent_row_descriptors();

    $formurl = new moodle_url('/blocks/configurable_reports/chainexport.php', ['id' => $id, 'chainid' => $chainid]);
    $form = new chain_export_form($formurl->out(false), [
        'reportid' => $id,
        'chainid' => $chainid,
        'courseid' => $courseid,
        'rows' => $rows,
        'formats' => $formats,
        'filterparams' => $filterparams,
    ]);

    if ($data = $form->get_data()) {
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $selectedrowkeys = chain_export_form::extract_selected_rowkeys($data);
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
        ], $filterparams)));
    }

    $defaultdata = new stdClass();
    foreach ($rows as $row) {
        $field = 'rowkey_' . $row->rowkey;
        $defaultdata->{$field} = 1;
    }
    if (!empty($formats)) {
        $defaultdata->exportformat = array_key_first($formats);
    }
    $form->set_data($defaultdata);

    $viewreportparams = array_merge(['id' => $id, 'courseid' => $courseid], $filterparams);
    $viewreporturl = new moodle_url('/blocks/configurable_reports/viewreport.php', $viewreportparams);

    echo $OUTPUT->header();
    echo $OUTPUT->heading($pagetitle);
    echo html_writer::div(
        html_writer::link($viewreporturl, get_string('chainexportviewreport', 'block_configurable_reports')),
        'mb-3'
    );

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

            $downloadurl = new moodle_url('/blocks/configurable_reports/chainexport.php', array_merge([
                'id' => $id,
                'chainid' => $chainid,
                'courseid' => $courseid,
                'downloadzip' => 1,
                'sesskey' => sesskey(),
            ], $filterparams));
            echo html_writer::div(
                $OUTPUT->single_button($downloadurl, get_string('chainexportdownloadzip', 'block_configurable_reports'), 'get'),
                'mb-3'
            );
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
    if (empty($rows)) {
        echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'), 'info');
    } else {
        $form->display();
    }
    echo $OUTPUT->continue_button($viewreporturl);
    echo $OUTPUT->footer();
    exit;
}

$viewreportparams = array_merge(['id' => $id, 'courseid' => $courseid], $filterparams);
$viewreporturl = new moodle_url('/blocks/configurable_reports/viewreport.php', $viewreportparams);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo html_writer::tag('p', get_string('chainexportchoose', 'block_configurable_reports'));
echo html_writer::div(
    html_writer::link($viewreporturl, get_string('chainexportviewreport', 'block_configurable_reports')),
    'mb-3'
);

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

echo $OUTPUT->continue_button($viewreporturl);
echo $OUTPUT->footer();
