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
 * Filter form for the import result page.
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

/**
 * Filter form for the import result page (sent via GET, so filtered views can be bookmarked).
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_results_filter_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'runid');
        $mform->setType('runid', PARAM_INT);
        $mform->addElement('hidden', 'view');
        $mform->setType('view', PARAM_ALPHA);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $tenantoptions = ['' => get_string('filterall', 'local_lehrgaengeapi')];
        foreach (tenants::all() as $tenant) {
            $tenantoptions[$tenant['abbr']] = $tenant['abbr'] . ' – ' . $tenant['name'];
        }
        $mform->addElement('select', 'tenant', get_string('filtertenant', 'local_lehrgaengeapi'), $tenantoptions);
        $mform->setType('tenant', PARAM_TEXT);

        $mform->addElement('text', 'course', get_string('filtercourse', 'local_lehrgaengeapi'), ['size' => 20]);
        $mform->setType('course', PARAM_TEXT);

        $mform->addElement('text', 'externalid', get_string('filterexternalid', 'local_lehrgaengeapi'), ['size' => 12]);
        $mform->setType('externalid', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('filter'));
    }
}
