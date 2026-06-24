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
 * Chain export user documentation.
 *
 * @package   block_configurable_reports
 */

require_once('../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);

require_login();

$context = context_system::instance();
if ($id) {
    $report = $DB->get_record('block_configurable_reports', ['id' => $id], '*', IGNORE_MISSING);
    if ($report) {
        if ($courseid && $report->global) {
            $report->courseid = $courseid;
        } else {
            $courseid = $report->courseid;
        }
        if ((int) $courseid === SITEID) {
            $context = context_system::instance();
        } else {
            require_login($courseid);
            $context = context_course::instance((int) $courseid);
        }
    }
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/blocks/configurable_reports/chainhelp.php', ['id' => $id, 'courseid' => $courseid]);
$PAGE->set_title(get_string('chainhelp_title', 'block_configurable_reports'));
$PAGE->set_heading(get_string('chainhelp_title', 'block_configurable_reports'));

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('chainhelp_intro', 'block_configurable_reports'));

$steps = [
    get_string('chainhelp_step1', 'block_configurable_reports'),
    get_string('chainhelp_step2', 'block_configurable_reports'),
    get_string('chainhelp_step3', 'block_configurable_reports'),
    get_string('chainhelp_step4', 'block_configurable_reports'),
    get_string('chainhelp_step5', 'block_configurable_reports'),
];

echo html_writer::start_tag('ol', ['class' => 'chainhelp-steps']);
foreach ($steps as $step) {
    echo html_writer::tag('li', $step);
}
echo html_writer::end_tag('ol');

echo html_writer::tag('p', get_string('chainhelp_note', 'block_configurable_reports'));

if ($id) {
    $backurl = new moodle_url('/blocks/configurable_reports/editcomp.php', [
        'id' => $id,
        'comp' => 'chains',
        'courseid' => $courseid,
    ]);
    echo $OUTPUT->single_button($backurl, get_string('back'), 'get');
}

echo $OUTPUT->footer();
