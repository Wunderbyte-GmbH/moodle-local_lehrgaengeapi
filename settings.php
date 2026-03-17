<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_lehrgaengeapi
 * @category    admin
 * @copyright   2025 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\local\tenants\tenants;

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_lehrgaengeapi';

if ($hassiteconfig) {
    $settings = new admin_settingpage($componentname, get_string('pluginname', $componentname));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            $componentname . '/settings_heading',
            get_string('settingsheading', $componentname),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            $componentname . '/baseurl',
            get_string('baseurl', $componentname),
            get_string('baseurldesc', $componentname),
            '',
            PARAM_URL
        ));

        $settings->add(new admin_setting_configtext(
            $componentname . '/timeout',
            get_string('timeout', $componentname),
            get_string('timeoutdesc', $componentname),
            30,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            $componentname . '/interval_lehrgaenge',
            get_string('intervallehrgaenge', $componentname),
            get_string('intervallehrgaengedesc', $componentname),
            900,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            $componentname . '/interval_teilnehmer',
            get_string('intervalteilnehmer', $componentname),
            get_string('intervalteilnehmerdesc', $componentname),
            3600,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            $componentname . '/requestdelayms',
            get_string('requestdelayms', $componentname),
            get_string('requestdelaymsdesc', $componentname),
            1000,
            PARAM_INT
        ));

        global $DB;
        $courses = $DB->get_records('course', null, 'fullname ASC', 'id, fullname, shortname');

        $courseoptions = [0 => get_string('none')];
        foreach ($courses as $c) {
            if ((int)$c->id === SITEID) {
                continue;
            }
            $courseoptions[(int)$c->id] = format_string($c->fullname) . ' (' . $c->shortname . ')';
        }

        $settings->add(new admin_setting_configselect_autocomplete(
            $componentname . '/targetcourseid',
            'targetcourseid',
            'targetcourseiddesc',
            0,
            $courseoptions
        ));

        $settings->add(new admin_setting_heading(
            $componentname . '/mandanten_heading',
            get_string('tenantheading', $componentname),
            get_string('tenantdescription', $componentname)
        ));
        $tenants = tenants::all();

        foreach ($tenants as $mandant) {
            $abbrclean = core_text::strtolower($mandant['abbr']);
            $abbrclean = preg_replace('/[^a-z0-9_]/', '_', $abbrclean);

            $settings->add(new admin_setting_description(
                $componentname . '/mandant_' . $abbrclean . '_label',
                format_string($mandant['name']) . ' (' . s($mandant['abbr']) . ')',
                ''
            ));

            $settings->add(new admin_setting_configtext(
                $componentname . '/apikey_' . $abbrclean,
                get_string('apikey', $componentname),
                get_string('apikeydesc', $componentname),
                '',
                PARAM_TEXT
            ));
        }
    }
}
