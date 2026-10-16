<?php
// (Standard-GPL-Header)

/**
 * Post-install hook of local_lehrgaengeapi.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create the custom profile field for the Ausbildungen.
 *
 * @return bool
 */
function xmldb_local_lehrgaengeapi_install(): bool {
    \local_lehrgaengeapi\local\users\ausbildungen_profile_field::ensure_exists();
    return true;
}
