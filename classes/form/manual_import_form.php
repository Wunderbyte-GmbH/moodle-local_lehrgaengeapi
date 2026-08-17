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
 * Form for triggering a manual year-filtered Lehrgaenge import.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for triggering a manual year-filtered Lehrgaenge import.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manual_import_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $currentyear = (int)date('Y');
        $years = [];
        for ($year = $currentyear; $year >= $currentyear - 15; $year--) {
            $years[$year] = (string)$year;
        }

        $mform->addElement('select', 'year', get_string('yearfield', 'local_lehrgaengeapi'), $years);
        $mform->setType('year', PARAM_INT);
        $mform->setDefault('year', $currentyear);
        $mform->addHelpButton('year', 'yearfield', 'local_lehrgaengeapi');

        $this->add_action_buttons(true, get_string('startimport', 'local_lehrgaengeapi'));
    }
}
