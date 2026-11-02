<?php
// (Standard-GPL-Header)

/**
 * Maps an API kurzbezeichnung to the course type used in course shortnames.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

/**
 * Maps an API kurzbezeichnung to the course type used in course shortnames.
 *
 * Shared by the import and the Ausbildungen linking, so both arrive at the same shortnames.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_type_resolver {
    /** @var array<string,string> API kurzbezeichnung => course type. */
    private const MAPPING = [
        'F-I' => 'F-I',
        'F-II' => 'F-II',
        'F-Atr' => 'F-ATR',
        'F-Ma' => 'F-MA',
        'F/K-Sprechfunk' => 'F/K-SPF',
        'F-TH-VU' => 'F-TH-VU',
        'F/B-mobBSA-Hessen' => 'F-BSA',
        'F/B-BSA-Sem.' => 'F/B-BSA-Sem.',
        'F-III' => 'F-III-elearn',
    ];

    /**
     * Resolve the course type.
     *
     * Special case F-III: the e-learning check on the bezeichnung is kept as it was in the
     * import. Since F-III is mapped first, it is currently never reached (documented quirk).
     *
     * @param string $kurzbezeichnung As delivered by the API.
     * @param string $bezeichnung Long title, only relevant for F-III.
     * @return string Course type; unmapped values are returned unchanged.
     */
    public function resolve(string $kurzbezeichnung, string $bezeichnung = ''): string {
        if (isset(self::MAPPING[$kurzbezeichnung])) {
            $kurzbezeichnung = self::MAPPING[$kurzbezeichnung];
        }
        if ($kurzbezeichnung !== 'F-III') {
            return $kurzbezeichnung;
        }

        $bezeichnung = mb_strtolower(trim($bezeichnung));
        if ($bezeichnung !== '' && mb_strpos($bezeichnung, 'e-learning') !== false) {
            return self::MAPPING['F-III'];
        }

        return $kurzbezeichnung;
    }
}
