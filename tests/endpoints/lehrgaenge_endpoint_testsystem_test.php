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
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\endpoints;

use local_lehrgaengeapi\task\sync_lehrgaenge_task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../test_helpers/curl_helper.php');

/**
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_endpoint_testsystem_test extends \advanced_testcase {
    /**
     * Ensure list() calls /lehrgaenge and returns decoded array.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::list
     */
    public function test_testserver_data(): void {
        global $CFG, $DB;
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }

        $this->resetAfterTest(true);

        require_once($CFG->dirroot . '/course/lib.php');

        $categoryid = (int)\core_course_category::get_default()->id;

        // Create course with shortname F-IV (only if it doesn't already exist).
        $existing = $DB->get_record('course', ['shortname' => 'F-IV'], '*', IGNORE_MISSING);
        if (!$existing) {
            $courserec = (object)[
                'category'  => $categoryid,
                'fullname'  => 'F-IV (Test)',
                'shortname' => 'F-IV',
                'summary'   => '',
                'format'    => 'topics',
                'visible'   => 1,
            ];
            create_course($courserec);
        }

        require_once($CFG->dirroot . '/local/iomad/lib/company.php');

        $companyrecord = (object)[
            'name'                   => 'Landkreis Bergstraße',
            'shortname'              => 'HP',
            'city'                   => 'Test',
            'country'                => 'DE',
            'maildisplay'            => 2,
            'mailformat'             => 1,
            'maildigest'             => 0,
            'autosubscribe'          => 1,
            'trackforums'            => 0,
            'htmleditor'             => 1,
            'screenreader'           => 0,
            'timezone'               => '99',
            'lang'                   => 'de',
            'theme'                  => 'iomadboost',
            'category'               => $categoryid,
            'profileid'              => 0,
            'suspended'              => 0,
            'supervisorprofileid'    => 0,
            'managernotify'          => 0,
            'parentid'               => 0,
            'ecommerce'              => 0,
            'managerdigestday'       => 0,
            'previousroletemplateid' => 0,
            'previousemailtemplateid' => 0,
            'departmentprofileid'    => 0,
        ];
        $companyid = $DB->insert_record('company', $companyrecord);
        \company::initialise_departments($companyid);

        // Base URL.
        set_config(
            'baseurl',
            'https://zms-hlfs.de/fw-hessen-schule/rest/services/moodle-services',
            'local_lehrgaengeapi'
        );

        // Client certificate + key (paths on the VM).
        set_config(
            'certificate_hp',
            '/etc/moodle-secrets/zms/client-cert.pem',
            'local_lehrgaengeapi'
        );
        set_config(
            'key_hp',
            '/etc/moodle-secrets/zms/client-key.pem',
            'local_lehrgaengeapi'
        );

        set_config(
            'apikey_hp',
            'YQtETIwanceHu1tHtgI1oEcxaGo5t3aasZttsi48Utpzz0NpyDot8ULDnMwiITdHmVOi4f4n',
            'local_lehrgaengeapi'
        );

        set_config('requestdelayms', 2000, 'local_lehrgaengeapi');

        if (!file_exists('/etc/moodle-secrets/zms/client-cert.pem') || !file_exists('/etc/moodle-secrets/zms/client-key.pem')) {
            $this->markTestSkipped('Test system certificate or key files are not available in this environment.');
        }

        $taskmanager = new sync_lehrgaenge_task();
        $taskmanager->execute();
    }
}
