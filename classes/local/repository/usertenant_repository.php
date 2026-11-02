<?php
// (Standard-GPL-Header)

/**
 * Remembers through which tenants a user was delivered by the API.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Remembers through which tenants a user was delivered by the API.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usertenant_repository {
    /** @var string DB table name */
    private const TABLE = 'local_lehrgaengeapi_usertenant';

    /**
     * Remember a tenant for a user (no-op if already known).
     *
     * @param int $userid Moodle user id.
     * @param string $tenantabbr Tenant abbreviation, e.g. FD.
     * @return bool True if the tenant was newly remembered.
     */
    public function remember(int $userid, string $tenantabbr): bool {
        global $DB;

        $tenantabbr = trim($tenantabbr);
        if ($userid <= 0 || $tenantabbr === '') {
            return false;
        }
        if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'tenantabbr' => $tenantabbr])) {
            return false;
        }

        $now = time();
        try {
            $DB->insert_record(self::TABLE, (object)[
                'userid' => $userid,
                'tenantabbr' => $tenantabbr,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // A concurrent run remembered the same pair in the meantime - the unique index won.
            return false;
        }
        return true;
    }

    /**
     * All remembered tenants of a user, in the order they were first remembered.
     *
     * @param int $userid Moodle user id.
     * @return string[] Tenant abbreviations.
     */
    public function get_tenants(int $userid): array {
        global $DB;
        return array_values($DB->get_fieldset_select(
            self::TABLE,
            'tenantabbr',
            'userid = :userid ORDER BY timecreated ASC, id ASC',
            ['userid' => $userid]
        ));
    }
}
