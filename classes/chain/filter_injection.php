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

namespace block_configurable_reports\chain;

defined('MOODLE_INTERNAL') || die();

/**
 * Scoped filter parameter injection for programmatic child report execution.
 *
 * @package   block_configurable_reports
 */
class filter_injection {

    /** @var array<int, array<string, mixed>> */
    private static array $params = [];

    /**
     * Set injected filter parameters for a report execution scope.
     *
     * @param int $reportid
     * @param array<string, mixed> $params
     * @return void
     */
    public static function set(int $reportid, array $params): void {
        self::$params[$reportid] = $params;
    }

    /**
     * Get an injected filter parameter if present.
     *
     * @param int $reportid
     * @param string $paramname
     * @return mixed|null
     */
    public static function get(int $reportid, string $paramname) {
        if (!self::has($reportid, $paramname)) {
            return null;
        }
        return self::$params[$reportid][$paramname];
    }

    /**
     * Whether a parameter was injected for this report.
     *
     * @param int $reportid
     * @param string $paramname
     * @return bool
     */
    public static function has(int $reportid, string $paramname): bool {
        return isset(self::$params[$reportid]) && array_key_exists($paramname, self::$params[$reportid]);
    }

    /**
     * Clear injected parameters for a report.
     *
     * @param int $reportid
     * @return void
     */
    public static function clear(int $reportid): void {
        unset(self::$params[$reportid]);
    }

    /**
     * Clear all injected parameters.
     *
     * @return void
     */
    public static function clear_all(): void {
        self::$params = [];
    }
}
