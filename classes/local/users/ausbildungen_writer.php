<?php
// (Standard-GPL-Header wie in den anderen Dateien)

/**
 * Writes a participant's Ausbildungen into the user's custom profile field.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\users;

/**
 * Writes a participant's Ausbildungen into the user's custom profile field.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_writer {
    /** @var string[] Keys of an Ausbildung that are stored, in this order. */
    private const KEYS = ['bezeichnung', 'kurzBezeichnung', 'von', 'bis', 'status'];

    /** @var int|null Id of the profile field, resolved once per instance. */
    private ?int $fieldid = null;

    /** @var bool Whether $fieldid has been looked up already. */
    private bool $resolved = false;

    /**
     * Save the participant's Ausbildungen for a user.
     *
     * Always writes - an empty or missing list is stored as an empty value. Nothing is
     * written if the value is unchanged, so a user appearing in many Lehrgaenge costs
     * one query per occurrence and no write.
     *
     * @param int $userid Moodle user id.
     * @param array $participant Participant payload from the API.
     * @return bool True if the stored value was created or changed.
     */
    public function write(int $userid, array $participant): bool {
        global $DB;

        $fieldid = $this->get_fieldid();
        if ($fieldid === null) {
            return false;
        }

        $value = $this->encode($participant['ausbildungen'] ?? []);
        $row = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid], 'id, data');

        if ($row) {
            if ((string)$row->data === $value) {
                return false;
            }
            $DB->set_field('user_info_data', 'data', $value, ['id' => $row->id]);
            return true;
        }

        $DB->insert_record('user_info_data', (object)[
            'userid' => $userid,
            'fieldid' => $fieldid,
            'data' => $value,
            'dataformat' => FORMAT_PLAIN,
        ]);
        return true;
    }

    /**
     * Look up the profile field once; log once if it is missing.
     *
     * @return int|null
     */
    private function get_fieldid(): ?int {
        if (!$this->resolved) {
            $this->fieldid = ausbildungen_profile_field::get_fieldid();
            $this->resolved = true;
            if ($this->fieldid === null) {
                mtrace('local_lehrgaengeapi: profile field "' . ausbildungen_profile_field::SHORTNAME
                    . '" not found - Ausbildungen are not saved.');
            }
        }
        return $this->fieldid;
    }

    /**
     * Reduce the API list to the known keys and encode it as readable JSON.
     *
     * @param mixed $ausbildungen Value of the participant's "ausbildungen" key.
     * @return string JSON, or an empty string if there is nothing to store.
     */
    private function encode($ausbildungen): string {
        if (!is_array($ausbildungen)) {
            return '';
        }

        $list = [];
        foreach ($ausbildungen as $ausbildung) {
            if (!is_array($ausbildung)) {
                continue;
            }
            $entry = [];
            foreach (self::KEYS as $key) {
                $entry[$key] = is_scalar($ausbildung[$key] ?? null) ? (string)$ausbildung[$key] : '';
            }
            $list[] = $entry;
        }

        return $list ? json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }
}
