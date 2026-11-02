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
 * Report table: one row per user and Ausbildung of a linking run.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use local_lehrgaengeapi\local\services\ausbildungen_linker;
use moodle_url;
use html_writer;

/**
 * Report table: one row per user and Ausbildung of a linking run.
 *
 * Sortable, paged and downloadable (CSV, Excel, ...) via table_sql.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_result_table extends \table_sql {
    /** @var string Filter value for "rows without tenant". */
    public const TENANT_NONE = '-';

    /**
     * Constructor.
     *
     * @param int $runid Linking run to show.
     * @param array $filters Optional 'outcome', 'tenant' (abbr or TENANT_NONE) and 'userid'.
     */
    public function __construct(int $runid, array $filters = []) {
        parent::__construct('local_lehrgaengeapi_link_results_' . $runid);

        $columns = [
            'userid', 'fullname', 'tenantabbr', 'kurzbezeichnung', 'bezeichnung',
            'von', 'bis', 'apistatus', 'outcome', 'course', 'message',
        ];
        $headers = [
            get_string('coluserid', 'local_lehrgaengeapi'),
            get_string('coluser', 'local_lehrgaengeapi'),
            get_string('coltenant', 'local_lehrgaengeapi'),
            get_string('colkurzbezeichnung', 'local_lehrgaengeapi'),
            get_string('colbezeichnung', 'local_lehrgaengeapi'),
            get_string('colvon', 'local_lehrgaengeapi'),
            get_string('colbis', 'local_lehrgaengeapi'),
            get_string('colapistatus', 'local_lehrgaengeapi'),
            get_string('coloutcome', 'local_lehrgaengeapi'),
            get_string('colcourse', 'local_lehrgaengeapi'),
            get_string('colmessage', 'local_lehrgaengeapi'),
        ];
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->sortable(true, 'userid', SORT_ASC);
        $this->no_sorting('message');
        $this->collapsible(false);
        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);
        $this->set_attribute('class', 'generaltable table-sm');

        $this->set_query($runid, $filters);
    }

    /**
     * Build the SQL for the run and the active filters.
     *
     * @param int $runid
     * @param array $filters
     * @return void
     */
    private function set_query(int $runid, array $filters): void {
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        $where = ['r.runid = :runid'];
        $params = ['runid' => $runid];

        $outcome = (string)($filters['outcome'] ?? '');
        if ($outcome !== '') {
            $where[] = 'r.outcome = :outcome';
            $params['outcome'] = $outcome;
        }

        $tenant = (string)($filters['tenant'] ?? '');
        if ($tenant === self::TENANT_NONE) {
            $where[] = 'r.tenantabbr IS NULL';
        } else if ($tenant !== '') {
            $where[] = 'r.tenantabbr = :tenant';
            $params['tenant'] = $tenant;
        }

        $userid = (int)($filters['userid'] ?? 0);
        if ($userid > 0) {
            $where[] = 'r.userid = :userid';
            $params['userid'] = $userid;
        }

        $this->set_sql(
            "r.*, {$namefields}, c.shortname AS courseshortname",
            '{local_lehrgaengeapi_link_result} r
             JOIN {user} u ON u.id = r.userid
        LEFT JOIN {course} c ON c.id = r.courseid',
            implode(' AND ', $where),
            $params
        );
    }

    /**
     * All outcomes with their labels, in the order of the linking flow.
     *
     * @return array<string,string>
     */
    public static function outcome_options(): array {
        $outcomes = [
            ausbildungen_linker::OUTCOME_LINKED_OWN,
            ausbildungen_linker::OUTCOME_LINKED_HLFS,
            ausbildungen_linker::OUTCOME_ALREADY,
            ausbildungen_linker::OUTCOME_NOTFOUND,
            ausbildungen_linker::OUTCOME_STATUSOPEN,
            ausbildungen_linker::OUTCOME_NOTCOMPLETED,
            ausbildungen_linker::OUTCOME_NOTYPE,
            ausbildungen_linker::OUTCOME_ERROR,
        ];
        $options = [];
        foreach ($outcomes as $outcome) {
            $options[$outcome] = get_string('linkoutcome_' . $outcome, 'local_lehrgaengeapi');
        }
        return $options;
    }

    /**
     * User name, linked to the profile (plain text in downloads).
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_fullname($row): string {
        $name = fullname($row);
        if ($this->is_downloading()) {
            return $name;
        }
        return html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->userid]), s($name));
    }

    /**
     * Tenant, or "without tenant".
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_tenantabbr($row): string {
        if ($row->tenantabbr === null || $row->tenantabbr === '') {
            return get_string('filtertenantnone', 'local_lehrgaengeapi');
        }
        return s($row->tenantabbr);
    }

    /**
     * Readable outcome.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_outcome($row): string {
        $options = self::outcome_options();
        return $options[$row->outcome] ?? s($row->outcome);
    }

    /**
     * Target course shortname, linked to the course (plain text in downloads).
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_course($row): string {
        if (empty($row->courseid)) {
            return '';
        }
        $shortname = (string)($row->courseshortname ?? $row->courseid);
        if ($this->is_downloading()) {
            return $shortname;
        }
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $row->courseid]), s($shortname));
    }
}
