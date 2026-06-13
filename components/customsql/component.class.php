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

/**
 * Class component_customsql
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class component_customsql extends component_base {

    /**
     * Init
     *
     * @return void
     */
    public function init(): void {
        global $PAGE;

        $this->plugins = false;
        $this->ordering = false;
        $this->form = true;
        $this->help = true;

        if (get_config('block_configurable_reports', 'sqlsyntaxhighlight')) {
            $PAGE->requires->js_call_amd('block_configurable_reports/main', 'cmirror');
        }
    }

    /**
     * Fresh diagnostic HTML built during the current save request.
     *
     * @var string|null
     */
    private ?string $outputcolumnsdiagnostichtml = null;

    /**
     * form_process_data
     *
     * @param moodleform $cform
     * @return void
     */
    public function form_process_data(moodleform $cform): void {
        global $DB, $CFG;
        if ($this->form) {
            $data = $cform->get_data();
            $rawsql = trim((string) ($data->querysql ?? ''));
            require_once($CFG->dirroot . '/blocks/configurable_reports/reports/sql/report.class.php');
            $reportclass = new report_sql($this->config->id);
            $extraction = $reportclass->extract_output_columns_result($rawsql);
            $data->querysql = $rawsql;
            $data->outputcolumns = $extraction->columns;
            $data->outputcolumns_detected = !empty($extraction->detected) ? 1 : 0;
            $data->outputcolumns_reason = $extraction->reason;
            $data->outputcolumns_source = $extraction->columns_source ?? 'none';
            $data->outputcolumns_updated = time();
            $this->outputcolumnsdiagnostichtml = \block_configurable_reports\report\output_columns::format_diagnostic(
                $extraction->columns,
                !empty($extraction->detected),
                (string) ($extraction->reason ?? ''),
                (int) $data->outputcolumns_updated
            );
            // Function cr_serialize() will add slashes.
            $components = cr_unserialize($this->config->components);
            $components['customsql']['config'] = $data;
            $this->config->components = cr_serialize($components);
            $DB->update_record('block_configurable_reports', $this->config);
        }
    }

    /**
     * Form set data
     *
     * @param moodleform $cform
     * @return void
     */
    public function form_set_data(moodleform $cform): void {
        if ($this->form) {
            $components = cr_unserialize($this->config->components);
            $sqlconfig = $components['customsql']['config'] ?? new stdclass;
            $cform->set_data($sqlconfig);
        }
    }

    /**
     * HTML diagnostic for cached SQL output columns.
     *
     * @return string
     */
    public function get_output_columns_diagnostic_html(): string {
        if ($this->outputcolumnsdiagnostichtml !== null) {
            return $this->outputcolumnsdiagnostichtml;
        }

        $components = cr_unserialize($this->config->components ?? '');
        $sqlconfig = $components['customsql']['config'] ?? new \stdClass();

        $columns = [];
        foreach ($sqlconfig->outputcolumns ?? [] as $column) {
            $name = trim((string) $column);
            if ($name !== '') {
                $columns[] = $name;
            }
        }

        $detected = !empty($sqlconfig->outputcolumns_detected) || !empty($columns);

        return \block_configurable_reports\report\output_columns::format_diagnostic(
            $columns,
            $detected,
            (string) ($sqlconfig->outputcolumns_reason ?? ''),
            (int) ($sqlconfig->outputcolumns_updated ?? 0)
        );
    }

    /**
     * Print the output columns diagnostic panel on the SQL settings page.
     *
     * @return void
     */
    public function print_output_columns_diagnostic(): void {
        global $OUTPUT;

        if (!\block_configurable_reports\report\output_columns::is_diagnostic_visible()) {
            return;
        }

        echo '<p>';
        echo $OUTPUT->heading(get_string('sqloutputcolumns_heading', 'block_configurable_reports'), 5);
        echo $OUTPUT->box(
            $this->get_output_columns_diagnostic_html(),
            'generalbox boxwidthnormal boxaligncenter sql-outputcolumns-diagnostic'
        );
    }

}
