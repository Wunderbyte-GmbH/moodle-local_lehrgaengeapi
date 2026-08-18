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

namespace local_lehrgaengeapi\local\users;

use local_lehrgaengeapi\local\repository\usermap_repository;
use stdClass;

/**
 * Course creator wrapper.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class users_creator {
    /** @var usermap_repository */
    private usermap_repository $usermap;

    /**
     * Constructor.
     * @return void
     */
    public function __construct() {
        $this->usermap = new usermap_repository();
    }

    /**
     * Create a Moodle users in a given category.
     * @param array $participants
     * @return array
     */
    public function create(array $participants): array {
        global $DB, $CFG;

        $created = 0;
        $existing = 0;
        $skipped = 0;
        $total = is_array($participants) ? count($participants) : 0;

        foreach ($participants as $p) {
            if (!is_array($p)) {
                $skipped++;
                continue;
            }

            $id = trim((string)($p['id'] ?? ''));
            $initialid = trim((string)($p['initialId'] ?? ''));

            if ($id === '') {
                $skipped++;
                continue;
            }
            $map = $this->usermap->ensure($id);

            if (!empty($map->userid)) {
                $u = $DB->get_record('user', ['id' => (int)$map->userid, 'deleted' => 0], '*', IGNORE_MISSING);
                if ($u) {
                    $this->check_and_fill_ids($u, $id, $initialid);
                    $existing++;
                    continue;
                }
            }

            $u = $DB->get_record('user', ['idnumber' => $id, 'deleted' => 0], '*', IGNORE_MISSING);
            if ($u) {
                $this->usermap->set_userid($id, (int)$u->id);
                $existing++;
                continue;
            }

            $u = $DB->get_record('user', [
                'mnethostid' => (int)$CFG->mnet_localhost_id,
                'username' => $id,
                'deleted' => 0,
            ], '*', IGNORE_MISSING);
            if ($u) {
                $this->usermap->set_userid($id, (int)$u->id);
                $this->check_and_fill_ids($u, $id, $initialid);
                $existing++;
                continue;
            }

            $email = $this->pick_email($p);
            $firstname = trim((string)($p['vorname'] ?? ''));
            $lastname  = trim((string)($p['nachname'] ?? ''));

            if ($firstname === '') {
                $firstname = 'Teilnehmer';
            }
            if ($lastname === '') {
                $lastname = $id;
            }

            $email = $this->make_unique_email($email);
            $username = trim((string)($p['initialId'] ?? $email));

            $newuser = (object)[
                'auth'       => 'manual',
                'confirmed'  => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username'   => $username,
                'password'   => hash_internal_user_password($username . 'Hlfs#'),
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'email'      => $email,
                'idnumber'   => $id,
                'city'       => (string)($p['ort'] ?? ''),
                'country'    => 'DE',
            ];

            try {
                $userid = user_create_user($newuser, false, false);
                // Persist mapping.
                $this->usermap->set_userid($id, (int)$userid);
                $created++;
            } catch (\dml_write_exception $e) {
                // Another process may have inserted the same username concurrently.
                $u = $DB->get_record('user', [
                    'mnethostid' => (int)$CFG->mnet_localhost_id,
                    'username' => $username,
                    'deleted' => 0,
                ], '*', IGNORE_MISSING);

                if ($u) {
                    $this->usermap->set_userid($id, (int)$u->id);
                    $existing++;
                    continue;
                }

                throw $e;
            }
        }

        return [
            'created'  => $created,
            'existing' => $existing,
            'skipped'  => $skipped,
            'total'    => $total,
        ];
    }

    /**
     * Always use business email.
     *
     * @param array $p
     * @return string
     */
    private function pick_email(array $p): string {
        if (!empty($p['emails']['emailBusiness'])) {
            return trim((string)$p['emails']['emailBusiness']);
        }
        if (!empty($p['emails']['emailPrivat'])) {
            return trim((string)$p['emails']['emailPrivat']);
        }
        return $this->placeholder_email($p['id']);
    }

    /**
     * Deterministic placeholder email.
     * @param string $id
     * @return string
     */
    private function placeholder_email(string $id): string {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $id));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'user';
        }
        return $slug . '@invalid.local';
    }

    /**
     * Ensure email uniqueness if Moodle disallows duplicates.
     * @param string $email
     * @return string
     */
    private function make_unique_email(string $email): string {
        global $DB;

        if (!empty(get_config('core', 'allowaccountssameemail'))) {
            return $email;
        }

        $candidate = $email;
        $i = 0;

        while ($DB->record_exists('user', ['email' => $candidate, 'deleted' => 0])) {
            $i++;

            if (strpos($email, '@') !== false) {
                [$local, $domain] = explode('@', $email, 2);
                $candidate = $local . '+' . $i . '@' . $domain;
            } else {
                $candidate = $email . '+' . $i;
            }

            if ($i > 50) {
                $candidate = $this->placeholder_email($email . '-' . $i);
                break;
            }
        }

        return $candidate;
    }

    /**
     * Update initial id if empty. This is used to ensure that the initial id is set for existing users.
     *
     * @param stdClass $user
     * @param string $id
     * @param string $initialid
     * @return string
     */
    private function check_and_fill_ids(stdClass $user, string $id, string $initialid): void {
        global $DB;
        if (empty($user->idnumber)) {
            $user->idnumber = $id;
            $DB->update_record('user', $user);
        }
        if ($initialid !== '' && $user->username != $initialid) {
            $user->username = $initialid;
            $user->password = hash_internal_user_password($initialid . 'Hlfs#');
            $DB->update_record('user', $user);
        }
    }
}
