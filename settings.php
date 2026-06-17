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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/dbhost', get_string('dbhost', 'block_configurable_reports'),
            get_string('dbhostinfo', 'block_configurable_reports'), '', PARAM_URL, 30
        )
    );
    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/dbname', get_string('dbname', 'block_configurable_reports'),
            get_string('dbnameinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new admin_setting_configpasswordunmask(
            'block_configurable_reports/dbuser', get_string('dbuser', 'block_configurable_reports'),
            get_string('dbuserinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );
    $settings->add(
        new admin_setting_configpasswordunmask(
            'block_configurable_reports/dbpass', get_string('dbpass', 'block_configurable_reports'),
            get_string('dbpassinfo', 'block_configurable_reports'), '', PARAM_RAW, 30
        )
    );

    $settings->add(
        new admin_setting_configtime(
            'block_configurable_reports/cron_hour',
            'cron_minute',
            get_string('executeat', 'block_configurable_reports'),
            get_string('executeatinfo', 'block_configurable_reports'),
            ['h' => 0, 'm' => 0]
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/sqlsecurity', get_string('sqlsecurity', 'block_configurable_reports'),
            get_string('sqlsecurityinfo', 'block_configurable_reports'), 1
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/crrepository',
            get_string('crrepository', 'block_configurable_reports'),
            get_string('crrepositoryinfo', 'block_configurable_reports'),
            'jleyva/moodle-configurable_reports_repository',
            PARAM_URL,
            40
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/sharedsqlrepository',
            get_string('sharedsqlrepository', 'block_configurable_reports'),
            get_string('sharedsqlrepositoryinfo', 'block_configurable_reports'),
            'jleyva/moodle-custom_sql_report_queries',
            PARAM_URL,
            40
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/sqlsyntaxhighlight', get_string('sqlsyntaxhighlight', 'block_configurable_reports'),
            get_string('sqlsyntaxhighlightinfo', 'block_configurable_reports'), 1
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/show_sql_outputcolumns_diagnostic',
            get_string('show_sql_outputcolumns_diagnostic', 'block_configurable_reports'),
            get_string('show_sql_outputcolumns_diagnostic_help', 'block_configurable_reports'),
            1
        )
    );

    $reporttableoptions = ['html' => 'Simple', 'jquery' => 'jQuery', 'datatables' => 'DataTables JS'];
    $settings->add(
        new admin_setting_configselect(
            'block_configurable_reports/reporttableui', get_string('reporttableui', 'block_configurable_reports'),
            get_string('reporttableuiinfo', 'block_configurable_reports'), 'datatables', $reporttableoptions
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/requirefiltersubmit',
            get_string('requirefiltersubmit', 'block_configurable_reports'),
            get_string('requirefiltersubmit_help', 'block_configurable_reports'),
            0
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/validate_sql_with_explain',
            get_string('validate_sql_with_explain', 'block_configurable_reports'),
            get_string('validate_sql_with_explain_help', 'block_configurable_reports'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/reportlimit', get_string('reportlimit', 'block_configurable_reports'),
            get_string('reportlimitinfo', 'block_configurable_reports'), '5000', PARAM_INT, 6
        )
    );

    $settings->add(new admin_setting_configtext('block_configurable_reports/allowedsqlusers', get_string('allowedsqlusers', 'block_configurable_reports'),
        get_string('allowedsqlusersinfo', 'block_configurable_reports'), '', PARAM_TEXT));
    // csv delimiters used in get_delimiter() of moodle lib/csvlib.class.php
    $csvdelimiteroptions= array('cfg'=>'cfg','colon'=>'colon','comma'=>'comma','semicolon'=>'semicolon','tab'=>'tab');
    $settings->add(new admin_setting_configselect('block_configurable_reports/csvdelimiter', get_string('csvdelimiter', 'block_configurable_reports'), 
        get_string('csvdelimiterinfo', 'block_configurable_reports'), 'cfg', $csvdelimiteroptions));

    $settings->add(
        new admin_setting_configcheckbox(
            'block_configurable_reports/jsonunicode',
            get_string('jsonunicode', 'block_configurable_reports'),
            get_string('jsonunicodeinfo', 'block_configurable_reports'),
            1
        )
    );
    

    $settings->add(new admin_setting_heading(
        'block_configurable_reports/chainsettings',
        get_string('chainsettingsheading', 'block_configurable_reports'),
        get_string('chainsettingsheading_desc', 'block_configurable_reports')
    ));

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/chainmaxrows',
            get_string('chainmaxrows', 'block_configurable_reports'),
            get_string('chainmaxrowsinfo', 'block_configurable_reports'),
            '100',
            PARAM_INT,
            6
        )
    );

    $chainexportmodeoptions = [
        (string) BLOCK_CONFIGURABLE_REPORTS_CHAINEXPORT_SYNC => get_string('chainexportmode_sync', 'block_configurable_reports'),
        (string) BLOCK_CONFIGURABLE_REPORTS_CHAINEXPORT_ASYNC => get_string('chainexportmode_async', 'block_configurable_reports'),
    ];
    $settings->add(
        new admin_setting_configselect(
            'block_configurable_reports/chainexportmode_default',
            get_string('chainexportmode_default', 'block_configurable_reports'),
            get_string('chainexportmode_default_help', 'block_configurable_reports'),
            (string) BLOCK_CONFIGURABLE_REPORTS_CHAINEXPORT_ASYNC,
            $chainexportmodeoptions
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/chainexportdelaythresholdms',
            get_string('chainexportdelaythresholdms', 'block_configurable_reports'),
            get_string('chainexportdelaythresholdms_help', 'block_configurable_reports'),
            '5000',
            PARAM_INT,
            8
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/chainexportdelaypercent',
            get_string('chainexportdelaypercent', 'block_configurable_reports'),
            get_string('chainexportdelaypercent_help', 'block_configurable_reports'),
            '15',
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/chainexportjobttl',
            get_string('chainexportjobttl', 'block_configurable_reports'),
            get_string('chainexportjobttl_help', 'block_configurable_reports'),
            '48',
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'block_configurable_reports/chainexportstalerunminutes',
            get_string('chainexportstalerunminutes', 'block_configurable_reports'),
            get_string('chainexportstalerunminutes_help', 'block_configurable_reports'),
            '120',
            PARAM_INT,
            6
        )
    );


}
