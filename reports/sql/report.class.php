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
defined('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS') || define('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS', 5000);

/**
 * Class report_sql
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class report_sql extends report_base {

    /**
     * @var bool
     */
    private bool $forexport = false;

    /**
     * @var int
     */
    private int $filterexecmode = BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_NORMAL;

    /**
     * set_forexport
     *
     * @param bool $isforexport
     * @return void
     */
    public function set_forexport(bool $isforexport): void {
        $this->forexport = $isforexport;
    }

    /**
     * is_forexport
     *
     * @return bool
     */
    public function is_forexport(): bool {
        return $this->forexport;
    }

    /**
     * Init
     *
     * @return void
     */
    public function init(): void {
        $this->components = [
            'customsql',
            'filters',
            'template',
            'permissions',
            'calcs',
            'plot',
        ];
    }

    /**
     * Build executable SQL from raw query and configured filters.
     *
     * @param string $rawsql SQL from report configuration.
     * @param int $mode BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_* constant.
     * @return string
     */
    public function build_sql_from_config(string $rawsql, int $mode = BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_NORMAL): string {
        global $CFG;

        if ($mode === BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_SKIP) {
            return $rawsql;
        }

        $this->filterexecmode = $mode;
        $sql = $rawsql;

        $components = cr_unserialize($this->config->components);
        $filters = $components['filters']['elements'] ?? [];

        if ($mode !== BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_RESTRICTIVE && !empty($filters)) {
            foreach ($filters as $f) {
                require_once($CFG->dirroot . '/blocks/configurable_reports/components/filters/' . $f['pluginname'] .
                    '/plugin.class.php');
                $classname = 'plugin_' . $f['pluginname'];
                $class = new $classname($this->config);
                $formdata = (object) ($f['formdata'] ?? new stdClass());
                $sql = $class->execute($sql, $formdata);
            }
            $sql = $this->apply_emptybehavior_postprocess($sql, $filters);
        }

        $prepareoptions = [
            'restrictive' => ($mode === BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_RESTRICTIVE),
        ];
        return $this->prepare_sql($sql, $prepareoptions);
    }

    /**
     * Replace remaining filter placeholders according to per-filter empty behaviour.
     *
     * @param string $sql
     * @param array $filters
     * @return string
     */
    private function apply_emptybehavior_postprocess(string $sql, array $filters): string {
        global $CFG;

        if (!preg_match_all('/%%FILTER_[^%]+%%/i', $sql, $matches)) {
            return $sql;
        }

        require_once($CFG->dirroot . '/blocks/configurable_reports/classes/filter_sql_analyzer.php');

        foreach ($matches[0] as $placeholder) {
            $inner = trim($placeholder, '%');
            $ph = [
                'full' => $placeholder,
                'inner' => $inner,
                'name' => self::placeholder_token_name($inner),
                'payload' => self::placeholder_token_payload($inner),
            ];

            foreach ($filters as $f) {
                require_once($CFG->dirroot . '/blocks/configurable_reports/components/filters/' . $f['pluginname'] .
                    '/plugin.class.php');
                $classname = 'plugin_' . $f['pluginname'];
                $class = new $classname($this->config);
                $formdata = (object) ($f['formdata'] ?? new stdClass());

                if (!\block_configurable_reports\filter_sql_analyzer::placeholder_matches_filter_public(
                    $ph,
                    $f['pluginname'],
                    $formdata
                )) {
                    continue;
                }

                if ($class->get_emptybehavior($formdata) === 'false') {
                    $sql = str_replace($placeholder, $class->get_restrictive_replacement($placeholder), $sql);
                }
                break;
            }
        }

        return $sql;
    }

    /**
     * @param string $inner
     * @return string
     */
    private static function placeholder_token_name(string $inner): string {
        $colon = strpos($inner, ':');
        if ($colon === false) {
            return $inner;
        }
        return substr($inner, 0, $colon);
    }

    /**
     * @param string $inner
     * @return string
     */
    private static function placeholder_token_payload(string $inner): string {
        $colon = strpos($inner, ':');
        if ($colon === false) {
            return '';
        }
        return substr($inner, $colon + 1);
    }

    /**
     * prepare_sql
     *
     * @param string $sql
     * @param array $options Optional flags: restrictive (bool).
     * @return array|string|string[]
     */
    public function prepare_sql(string $sql, array $options = []) {
        global $USER, $CFG, $COURSE;

        $restrictive = !empty($options['restrictive']);

        // Enable debug mode from SQL query.
        $this->config->debug = strpos($sql, '%%DEBUG%%') !== false;

        // Pass special custom undefined variable as filter.
        // Security warning !!! can be used for sql injection.
        // Use %%FILTER_VAR%% in your sql code with caution.
        $filtervar = optional_param('filter_var', '', PARAM_RAW);
        if (!empty($filtervar)) {
            $sql = str_replace('%%FILTER_VAR%%', $filtervar, $sql);
        }

        $starttime = $restrictive ? '0' : '0';
        $endtime = $restrictive ? '0' : '2145938400';

        // See http://en.wikipedia.org/wiki/Year_2038_problem.
        $sql = str_replace([
            '%%USERID%%',
            '%%COURSEID%%',
            '%%CATEGORYID%%',
            '%%STARTTIME%%',
            '%%ENDTIME%%',
            '%%WWWROOT%%',
        ],
            [$USER->id, $COURSE->id, $COURSE->category, $starttime, $endtime, $CFG->wwwroot],
            $sql);

        if ($restrictive) {
            $sql = preg_replace('/%%FILTER_[^%]+%%/i', ' AND 1=0 ', $sql);
        }

        $sql = preg_replace('/%{2}[^%]+%{2}/i', '', $sql);

        return str_replace('?', '[[QUESTIONMARK]]', $sql);
    }

    /**
     * Replace prefix_ table placeholders with the site table prefix.
     *
     * @param string $sql
     * @return string
     */
    private function normalize_sql_prefixes(string $sql): string {
        global $CFG;

        return preg_replace('/\bprefix_(?=\w+)/i', $CFG->prefix, $sql);
    }

    /**
     * Build an EXPLAIN statement for the given SQL (db family dependent).
     *
     * @param string $sql Normalized SQL (prefixes already applied).
     * @return string EXPLAIN statement.
     * @throws \block_configurable_reports\exceptions\explain_unsupported_exception If EXPLAIN is not supported for this database family.
     */
    private function build_explain_sql(string $sql): string {
        global $remotedb;

        switch ($remotedb->get_dbfamily()) {
            case 'postgres':
            case 'mysql':
                return 'EXPLAIN ' . $sql;
            case 'sqlite':
                return 'EXPLAIN QUERY PLAN ' . $sql;
            default:
                throw new \block_configurable_reports\exceptions\explain_unsupported_exception($remotedb->get_dbfamily());
        }
    }

    /**
     * Run EXPLAIN on SQL to validate parse/plan without fetching rows.
     *
     * @param string $sql Normalized SQL (prefixes already applied).
     * @return void
     * @throws \block_configurable_reports\exceptions\explain_unsupported_exception
     * @throws dml_exception
     */
    private function explain_query_sql(string $sql): void {
        global $remotedb;

        $explainsql = $this->build_explain_sql($sql);
        $rs = $remotedb->get_recordset_sql($explainsql);
        $rs->close();
    }

    /**
     * execute_query
     *
     * @param string $sql
     * @param array|int $options Options: validation (bool), maxrows (int), prefixes_normalized (bool).
     * @return mixed
     */
    public function execute_query($sql, $options = []) {
        if (!is_array($options)) {
            $options = [];
        }
        global $remotedb, $DB, $CFG;

        $validation = !empty($options['validation']);
        $maxrows = $options['maxrows'] ?? null;

        if (empty($options['prefixes_normalized'])) {
            $sql = $this->normalize_sql_prefixes($sql);
        }

        $reportlimit = get_config('block_configurable_reports', 'reportlimit');
        if (empty($reportlimit) || $reportlimit == '0') {
            $reportlimit = BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS;
        }
        if ($maxrows !== null) {
            $reportlimit = min((int) $maxrows, (int) $reportlimit);
        }

        $starttime = microtime(true);

        if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $sql) && !empty($CFG->block_configurable_reports_enable_sql_execution)) {
            // Run special (dangerous) queries directly.
            $results = $remotedb->execute($sql);
        } else {
            $results = $remotedb->get_recordset_sql($sql, null, 0, $reportlimit);
        }

        if (!$validation && !empty($this->config->id)) {
            // Update the execution time in the DB.
            $updaterecord = $DB->get_record('block_configurable_reports', ['id' => $this->config->id]);
            if ($updaterecord) {
                $updaterecord->lastexecutiontime = round((microtime(true) - $starttime) * 1000);
                $this->config->lastexecutiontime = $updaterecord->lastexecutiontime;
                $DB->update_record('block_configurable_reports', $updaterecord);
            }
        }

        return $results;
    }

    /**
     * Validate SQL syntax by running a restrictive version of the query.
     *
     * @param string $rawsql
     * @return string|null Error message or null if valid.
     */
    public function validate_query_sql(string $rawsql): ?string {
        core_php_time_limit::raise(60);

        try {
            $sql = $this->build_sql_from_config($rawsql, BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_RESTRICTIVE);
            $sql = $this->normalize_sql_prefixes($sql);

            if (get_config('block_configurable_reports', 'validate_sql_with_explain')) {
                try {
                    $this->explain_query_sql($sql);
                    return null;
                } catch (\block_configurable_reports\exceptions\explain_unsupported_exception $e) {
                    // Unsupported DB family — fall back to execute with maxrows 1.
                } catch (dml_exception $e) {
                    // EXPLAIN failed at runtime — fall back to execute with maxrows 1.
                } catch (Throwable $e) {
                    if (defined('DEBUG_DEVELOPER') && DEBUG_DEVELOPER) {
                        throw $e;
                    }
                }
            }

            $rs = $this->execute_query($sql, [
                'validation' => true,
                'maxrows' => 1,
                'prefixes_normalized' => true,
            ]);
            if ($rs) {
                $rs->close();
            }
        } catch (dml_read_exception $e) {
            return get_string('queryfailed', 'block_configurable_reports', $e->error);
        } catch (moodle_exception $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * create_report
     *
     * @return bool
     */
    public function create_report(): bool {
        global $CFG;

        $components = cr_unserialize($this->config->components);

        $calcs = $components['calcs']['elements'] ?? [];

        $tablehead = [];
        $finalcalcs = [];
        $finaltable = [];

        $config = $components['customsql']['config'] ?? new stdClass;
        $totalrecords = 0;

        $sql = '';
        if (isset($config->querysql)) {
            $sql = $this->build_sql_from_config($config->querysql, BLOCK_CONFIGURABLE_REPORTS_FILTER_EXEC_NORMAL);

            if ($rs = $this->execute_query($sql)) {
                foreach ($rs as $row) {
                    if (empty($finaltable)) {
                        foreach ($row as $colname => $value) {
                            $tablehead[] = $colname;
                        }
                    }
                    $arrayrow = array_values((array) $row);
                    foreach ($arrayrow as $ii => $cell) {
                        if (!$this->is_forexport()) {
                            $cell = format_text($cell, FORMAT_HTML, ['trusted' => true, 'noclean' => true, 'para' => false]);
                        }
                        $arrayrow[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
                    }
                    $totalrecords++;
                    $finaltable[] = $arrayrow;
                }
                $rs->close();
            }
        }
        $this->sql = $sql;
        $this->totalrecords = $totalrecords;

        // Calcs.

        $finalcalcs = $this->get_calcs($finaltable, $tablehead);

        $table = new stdClass;
        $table->id = 'reporttable';
        $table->data = $finaltable;
        $table->head = $tablehead;

        $calcs = new html_table();
        $calcs->id = 'calcstable';
        $calcs->data = [$finalcalcs];
        $calcs->head = $tablehead;

        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->name = $this->config->name;
        $this->finalreport->table = $table;
        $this->finalreport->calcs = $calcs;

        return true;
    }

}
