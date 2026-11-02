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
 * Import result table: one row per Moodle course of an import run, summing all its Lehrgänge.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use moodle_url;
use html_writer;

/**
 * Import result table: one row per Moodle course of an import run, summing all its Lehrgänge.
 *
 * Several Florix Lehrgänge can land in the same course (e.g. _alt courses); this view shows
 * them together. Sortable, paged and downloadable via table_sql.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_summary_table extends \table_sql {
    /** @var string[] Counter columns that are summed per course. */
    private const SUMS = [
        'total', 'enrolled', 'alreadyenrolled', 'unenrolled', 'alreadyunenrolled', 'completed', 'noop', 'assignskipped',
    ];

    /** @var int Import run shown. */
    private int $runid;

    /** @var array Active filters, passed on to the per-Lehrgang link. */
    private array $filters;

    /**
     * Constructor.
     *
     * @param int $runid Import run to show.
     * @param array $filters See course_result_filter::where().
     */
    public function __construct(int $runid, array $filters = []) {
        parent::__construct('local_lehrgaengeapi_course_summary_' . $runid);
        $this->runid = $runid;
        $this->filters = $filters;

        $columns = [
            'courseshortname' => 'colcourse',
            'lehrgaenge' => 'collehrgaenge',
            'lehrgaengelist' => 'collehrgaengelist',
            'total' => 'coltotal',
            'enrolled' => 'colenrolled',
            'alreadyenrolled' => 'colalreadyenrolled',
            'unenrolled' => 'colunenrolled',
            'alreadyunenrolled' => 'colalreadyunenrolled',
            'completed' => 'colcompleted',
            'noop' => 'colnoop',
            'assignskipped' => 'colassignmentskipped',
        ];
        $this->define_columns(array_keys($columns));
        $this->define_headers(array_map(static fn($key) => get_string($key, 'local_lehrgaengeapi'), array_values($columns)));
        $this->sortable(true, 'courseshortname', SORT_ASC);
        $this->no_sorting('lehrgaengelist');
        $this->collapsible(false);
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);
        $this->set_attribute('class', 'generaltable table-sm');

        $this->set_query($runid, $filters);
    }

    /**
     * Group the matching Lehrgang rows per course. The grouping happens in a subquery,
     * so table_sql can sort, count and page the result like a plain table.
     *
     * @param int $runid
     * @param array $filters
     * @return void
     */
    private function set_query(int $runid, array $filters): void {
        global $DB;

        [$where, $params] = course_result_filter::where($runid, $filters);

        $sums = implode(', ', array_map(static fn($c) => "SUM(r.{$c}) AS {$c}", self::SUMS));
        $label = $DB->sql_concat(
            "COALESCE(r.tenantabbr, '')",
            "': '",
            "COALESCE(r.kurzbezeichnung, '')",
            "' ('",
            'r.externalid',
            "')'"
        );
        $list = $DB->sql_group_concat($label, ', ', 'r.externalid');

        $this->set_sql(
            's.*',
            "(SELECT r.courseid, MAX(r.courseshortname) AS courseshortname,
                     COUNT(1) AS lehrgaenge, {$list} AS lehrgaengelist, {$sums}
                FROM {local_lehrgaengeapi_course_result} r
               WHERE {$where}
            GROUP BY r.courseid) s",
            '1 = 1',
            $params
        );
    }

    /**
     * Course shortname linked to the course (plain text in downloads).
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_courseshortname($row): string {
        if ((int)$row->courseid === 0) {
            return get_string('collectioncourse', 'local_lehrgaengeapi');
        }
        $shortname = (string)$row->courseshortname;
        if ($this->is_downloading()) {
            return $shortname;
        }
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $row->courseid]), s($shortname));
    }

    /**
     * Number of Lehrgänge, linked to the per-Lehrgang view of this course (plain number in downloads).
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_lehrgaenge($row): string {
        if ($this->is_downloading() || (int)$row->courseid === 0) {
            return (string)$row->lehrgaenge;
        }
        $url = new moodle_url('/local/lehrgaengeapi/admin/import_results.php', [
            'runid' => $this->runid,
            'view' => 'lehrgang',
            'courseid' => $row->courseid,
        ] + array_filter($this->filters));
        return html_writer::link($url, (int)$row->lehrgaenge);
    }
}
