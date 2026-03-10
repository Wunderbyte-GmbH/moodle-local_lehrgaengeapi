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
 * Course creator wrapper.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\tenants;

use company;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tenant creator wrapper.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_creator {
    /**
     * Create a Moodle course in a given category.
     *
     * @param array $tenant
     * @return company|null
     */
    public function get_tenant(array $tenant): company|null {
        global $DB;
        $tenant = $DB->get_record(
            'company',
            ['name' => $tenant['name'], 'shortname' => $tenant['abbr']],
            'id'
        );
        if ($tenant) {
            return new company($tenant->id);
        }
        return null;
    }
}
