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
 * Filter form for the linking result page.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_lehrgaengeapi\local\tenants\tenants;
use local_lehrgaengeapi\table\link_result_table;

/**
 * Filter form for the linking result page (sent via GET, so filtered views can be bookmarked).
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_results_filter_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $all = get_string('filterall', 'local_lehrgaengeapi');

        $mform->addElement('hidden', 'runid');
        $mform->setType('runid', PARAM_INT);

        $outcomes = ['' => $all] + link_result_table::outcome_options();
        $mform->addElement('select', 'outcome', get_string('filteroutcome', 'local_lehrgaengeapi'), $outcomes);
        $mform->setType('outcome', PARAM_ALPHANUMEXT);

        $tenantoptions = [
            '' => $all,
            link_result_table::TENANT_NONE => get_string('filtertenantnone', 'local_lehrgaengeapi'),
        ];
        foreach (tenants::all() as $tenant) {
            $tenantoptions[$tenant['abbr']] = $tenant['abbr'] . ' – ' . $tenant['name'];
        }
        $tenantoptions['HLFS'] = $tenantoptions['HLFS'] ?? 'HLFS';
        $mform->addElement('select', 'tenant', get_string('filtertenant', 'local_lehrgaengeapi'), $tenantoptions);
        $mform->setType('tenant', PARAM_TEXT);

        $mform->addElement('text', 'userid', get_string('filteruserid', 'local_lehrgaengeapi'), ['size' => 8]);
        $mform->setType('userid', PARAM_INT);

        $this->add_action_buttons(false, get_string('filter'));
    }
}
