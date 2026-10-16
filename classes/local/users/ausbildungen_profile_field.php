<?php
// (Standard-GPL-Header wie in den anderen Dateien)

/**
 * Creates and looks up the custom profile field that holds a user's Ausbildungen.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\users;

/**
 * Creates and looks up the custom profile field that holds a user's Ausbildungen.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_profile_field {
    /** @var string Shortname of the custom profile field. */
    public const SHORTNAME = 'ausbildungen';

    /** @var string Name of the category the field is created in. */
    private const CATEGORYNAME = 'Lehrgaenge';

    /**
     * Id of the profile field, null if it does not exist (e.g. an admin deleted it).
     *
     * Deliberately not cached statically: callers cache it per run themselves.
     *
     * @return int|null
     */
    public static function get_fieldid(): ?int {
        global $DB;
        $id = $DB->get_field('user_info_field', 'id', ['shortname' => self::SHORTNAME]);
        return $id === false ? null : (int)$id;
    }

    /**
     * Create the profile field if it does not exist yet. Safe to call repeatedly.
     *
     * @return int Id of the (existing or newly created) field.
     */
    public static function ensure_exists(): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $existing = self::get_fieldid();
        if ($existing !== null) {
            return $existing;
        }

        $category = $DB->get_record('user_info_category', ['name' => self::CATEGORYNAME]);
        if (!$category) {
            $sortorder = (int)$DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') + 1;
            $categoryid = $DB->insert_record('user_info_category', (object)[
                'name' => self::CATEGORYNAME,
                'sortorder' => $sortorder,
            ]);
        } else {
            $categoryid = (int)$category->id;
        }

        $sortorder = (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = :categoryid',
            ['categoryid' => $categoryid]
        ) + 1;

        return (int)$DB->insert_record('user_info_field', (object)[
            'shortname' => self::SHORTNAME,
            'name' => 'Ausbildungen',
            'datatype' => 'textarea',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => $categoryid,
            'sortorder' => $sortorder,
            'required' => 0,
            'locked' => 1,
            'visible' => PROFILE_VISIBLE_NONE,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => 0,
            'param1' => 30,
            'param2' => 10,
        ]);
    }
}
