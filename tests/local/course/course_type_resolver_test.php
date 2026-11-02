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
 * Tests for course_type_resolver.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

/**
 * Tests for course_type_resolver.
 *
 * The mapping must stay exactly as the import used it, because import and Ausbildungen
 * linking have to arrive at the same course shortnames.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_type_resolver_test extends \basic_testcase {
    /**
     * Mapped kurzbezeichnungen.
     *
     * @return array
     */
    public static function mapping_provider(): array {
        return [
            'F-I' => ['F-I', 'F-I'],
            'F-II' => ['F-II', 'F-II'],
            'F-Atr' => ['F-Atr', 'F-ATR'],
            'F-Ma' => ['F-Ma', 'F-MA'],
            'Sprechfunk' => ['F/K-Sprechfunk', 'F/K-SPF'],
            'F-TH-VU' => ['F-TH-VU', 'F-TH-VU'],
            'mobBSA' => ['F/B-mobBSA-Hessen', 'F-BSA'],
            'BSA-Sem' => ['F/B-BSA-Sem.', 'F/B-BSA-Sem.'],
            'F-III' => ['F-III', 'F-III-elearn'],
            'unmapped stays unchanged' => ['AGT', 'AGT'],
            'unmapped with umlaut' => ['WB-BF/FF-Ma-Flurförder', 'WB-BF/FF-Ma-Flurförder'],
            'empty' => ['', ''],
        ];
    }

    /**
     * Every kurzbezeichnung resolves to the same course type as in the import.
     *
     * @dataProvider mapping_provider
     * @covers \local_lehrgaengeapi\local\course\course_type_resolver::resolve
     * @param string $kurzbezeichnung
     * @param string $expected
     */
    public function test_resolve_maps_like_the_import(string $kurzbezeichnung, string $expected): void {
        $this->assertSame($expected, (new course_type_resolver())->resolve($kurzbezeichnung, 'Irgendein Lehrgang'));
    }

    /**
     * F-III goes to F-III-elearn regardless of the bezeichnung (documented current behaviour).
     *
     * @covers \local_lehrgaengeapi\local\course\course_type_resolver::resolve
     */
    public function test_f_iii_ignores_bezeichnung(): void {
        $resolver = new course_type_resolver();
        $this->assertSame('F-III-elearn', $resolver->resolve('F-III', 'Gruppenführer (E-Learning)'));
        $this->assertSame('F-III-elearn', $resolver->resolve('F-III', 'Gruppenführer Präsenz'));
        $this->assertSame('F-III-elearn', $resolver->resolve('F-III'));
    }
}
