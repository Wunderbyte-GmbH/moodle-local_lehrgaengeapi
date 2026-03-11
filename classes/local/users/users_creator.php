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

            $initialid = trim((string)($p['initialId'] ?? ''));
            if ($initialid === '') {
                $skipped++;
                continue;
            }
            $map = $this->usermap->ensure($initialid);

            if (!empty($map->userid)) {
                $u = $DB->get_record('user', ['id' => (int)$map->userid, 'deleted' => 0], '*', IGNORE_MISSING);
                if ($u) {
                    $existing++;
                    continue;
                }
            }

            $email = $this->pick_email($p);
            $u = null;
            if ($email !== '') {
                $u = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MISSING);
            }

            if ($u) {
                $this->usermap->set_userid($initialid, (int)$u->id);
                $existing++;
                continue;
            }

            $firstname = trim((string)($p['vorname'] ?? ''));
            $lastname  = trim((string)($p['nachname'] ?? ''));

            if ($firstname === '') {
                $firstname = 'Teilnehmer';
            }
            if ($lastname === '') {
                $lastname = $initialid;
            }

            if ($email === '') {
                // If API ever sends empty business email: create a placeholder.
                $email = $this->placeholder_email($initialid);
            }

            $username = $this->make_unique_username($initialid);
            $email = $this->make_unique_email($email);

            $newuser = (object)[
                'auth'       => 'manual',
                'confirmed'  => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username'   => $username,
                'password'   => hash_internal_user_password(random_string(20)),
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'email'      => $email,
                'idnumber'   => $initialid,
                'city'       => (string)($p['ort'] ?? ''),
                'country'    => 'DE',
            ];

            $userid = user_create_user($newuser, false, false);

            // Persist mapping.
            $this->usermap->set_userid($initialid, (int)$userid);

            $created++;
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
        return trim((string)($p['emailBusiness'] ?? ''));
    }

    /**
     * Deterministic placeholder email.
     * @param string $initialid
     * @return string
     */
    private function placeholder_email(string $initialid): string {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $initialid));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'user';
        }
        return $slug . '@invalid.local';
    }

    /**
     * Make a safe + unique username based on initialId.
     * @param string $seed
     * @return string
     */
    private function make_unique_username(string $seed): string {
        global $DB;

        $base = strtolower($seed);
        $base = preg_replace('/[^a-z0-9._-]+/', '_', $base);
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'user';
        }
        $base = substr($base, 0, 60);

        $candidate = $base;
        $i = 0;

        while ($DB->record_exists('user', ['username' => $candidate])) {
            $i++;
            $suffix = '_' . $i;
            $candidate = substr($base, 0, 60 - strlen($suffix)) . $suffix;
        }

        return $candidate;
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
}
