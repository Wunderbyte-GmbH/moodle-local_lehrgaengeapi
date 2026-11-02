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
 * Import result table: one row per Lehrgang of an import run.
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
 * Import result table: one row per Lehrgang of an import run.
 *
 * Sortable, paged and downloadable (CSV, Excel, ...) via table_sql.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_result_table extends \table_sql {
    /**
     * Constructor.
     *
     * @param int $runid Import run to show.
     * @param array $filters See course_result_filter::where().
     */
    public function __construct(int $runid, array $filters = []) {
        parent::__construct('local_lehrgaengeapi_course_results_' . $runid);

        $columns = [
            'courseshortname' => 'colcourse',
            'tenantabbr' => 'coltenant',
            'externalid' => 'colexternalid',
            'kurzbezeichnung' => 'colkurzbezeichnung',
            'total' => 'coltotal',
            'userscreated' => 'colusercreated',
            'usersexisting' => 'coluserexisting',
            'usersskipped' => 'coluserskipped',
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
        $this->collapsible(false);
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);
        $this->set_attribute('class', 'generaltable table-sm');

        [$where, $params] = course_result_filter::where($runid, $filters);
        $this->set_sql('r.*', '{local_lehrgaengeapi_course_result} r', $where, $params);
    }

    /**
     * Course shortname as stored at import time, linked to the course (plain text in downloads).
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
}
