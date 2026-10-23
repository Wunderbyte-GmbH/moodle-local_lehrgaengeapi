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
 * Tests for participant_status_handler_resolver.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\lehrgang_status;

use local_lehrgaengeapi\local\lehrgang_status\abgemeldet_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\angemeldet_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\bestanden_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\noop_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver;

/**
 * Tests for participant_status_handler_resolver.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_status_handler_resolver_test extends \advanced_testcase {
    /**
     * Status groups.
     *
     * @var array
     */
    private array $states = [
        'einschreiben' => [
            'handler' => angemeldet_participant_status_handler::class,
            'states' => [
                'S034_EINBERUFUNG_VERSCHICKT_KREIS',
                'S066_EINBERUFUNG_VERSCHICKT_LAND',
                'S107_EINBERUFUNG_VERSCHICKT_WERKFEUERWEHRVERBAND',
                'S122_EINBERUFUNG_VERSCHICKT_GEMEINDE',
                'S129_ANGEMELDET_EIGENE_GEMEINDE',
            ],
        ],

        'abschliessen' => [
            'handler' => bestanden_participant_status_handler::class,
            'states' => [
                'S050_TEILGENOMMEN_KREIS',
                'S051_BESTANDEN_KREIS',
                'S082_TEILGENOMMEN_LAND',
                'S084_BESTANDEN_LAND',
                'S113_TEILGENOMMEN_WERKFEUERWEHRVERBAND',
                'S114_BESTANDEN_WERKFEUERWEHRVERBAND',
                'S126_BESTANDEN_GEMEINDE',
                'S128_TEILGENOMMEN_GEMEINDE',
            ],
        ],

        'austragen' => [
            'handler' => abgemeldet_participant_status_handler::class,
            'states' => [
                'S014_ABGELEHNT_GEMEINDE',
                'S022_ABGELEHNT_KREIS',
                'S040_FEHLT_UNENTSCHULDIGT_KREIS',
                'S042_ABGEBROCHEN_KREIS',
                'S052_NICHT_BESTANDEN_KREIS',
                'S058_ABGELEHNT_LAND',
                'S068_FEHLT_UNENTSCHULDIGT_LAND',
                'S070_NICHT_TEILGENOMMEN_LAND',
                'S074_ABGEBROCHEN_LAND',
                'S078_NICHT_BESTANDEN_LAND',
                'S087_ZURUECKGESTELLT_GEMEINDE',
                'S088_UEBERREGIONALER_LEHRGANG_ZURUECKGESTELLT_EIGENER_KREIS',
                'S089_ZURUECKGESTELLT_KREIS',
                'S090_ZURUECKGESTELLT_LAND',
                'S094_UEBERREGIONALER_LEHRGANG_ANGEMELDET_EIGENER_KREIS',
                'S099_TERMIN_ABGESAGT',
                'S100_STORNIERT_KREIS',
                'S101_STORNIERT_LAND',
                'S103_ABGELEHNT_WERKFEUERWEHRVERBAND',
                'S104_ZURUECKGESTELLT_WERKFEUERWEHRVERBAND',
                'S105_STORNIERT_WERKFEUERWEHRVERBAND',
                'S109_FEHLT_UNENTSCHULDIGT_WERKFEUERWEHRVERBAND',
                'S111_ABGEBROCHEN_WERKFEUERWEHRVERBAND',
                'S112_NICHT_BESTANDEN_WERKFEUERWEHRVERBAND',
                'S121_STORNIERT_GEMEINDE',
                'S124_FEHLT_UNENTSCHULDIGT_GEMEINDE',
                'S125_ABGEBROCHEN_GEMEINDE',
                'S127_NICHT_BESTANDEN_GEMEINDE',
                'S130_ZURUECKGESTELLT_EIGENE_GEMEINDE',
            ],
        ],

        'noop' => [
            'handler' => noop_participant_status_handler::class,
            'states' => [
                'S000_ERFASST_ORTSFEUERWEHR',
                'S010_ANGEMELDET_GEMEINDE',
                'S018_ANGEMELDET_KREIS',
                'S033_EINBERUFEN_KREIS',
                'S039_FEHLT_ENTSCHULDIGT_KREIS',
                'S054_ANGEMELDET_LAND',
                'S065_EINBERUFEN_LAND',
                'S067_FEHLT_ENTSCHULDIGT_LAND',
                'S102_ANGEMELDET_WERKFEUERWEHRVERBAND',
                'S106_EINBERUFEN_WERKFEUERWEHRVERBAND',
                'S108_FEHLT_ENTSCHULDIGT_WERKFEUERWEHRVERBAND',
                'S120_EINBERUFEN_GEMEINDE',
                'S123_FEHLT_ENTSCHULDIGT_GEMEINDE',
                'S140_VERSCHOBEN',
            ],
        ],
    ];

    /**
     * Ensure all statuses resolve to the correct handler.
     *
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver
     *
     * @return void
     */
    public function test_resolve_returns_correct_handler(): void {
        $resolver = new participant_status_handler_resolver(
            new angemeldet_participant_status_handler(),
            new bestanden_participant_status_handler(),
            new noop_participant_status_handler(),
            new abgemeldet_participant_status_handler()
        );

        foreach ($this->states as $groupname => $config) {
            $expectedhandler = $config['handler'];
            foreach ($config['states'] as $state) {
                $handler = $resolver->resolve($state);
                $this->assertInstanceOf(
                    $expectedhandler,
                    $handler,
                    "Failed asserting state '{$state}' in group '{$groupname}'"
                );
            }
        }
    }

    /**
     * Unknown or malformed status falls back to noop handler.
     *
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver::resolve
     */
    public function test_resolve_returns_noop_for_unknown_status(): void {
        $resolver = new participant_status_handler_resolver(
            new angemeldet_participant_status_handler(),
            new bestanden_participant_status_handler(),
            new noop_participant_status_handler(),
            new abgemeldet_participant_status_handler()
        );

        $handler = $resolver->resolve('S999_UNBEKANNT_STATUS');
        $this->assertInstanceOf(noop_participant_status_handler::class, $handler);

        $handler = $resolver->resolve(null);
        $this->assertInstanceOf(noop_participant_status_handler::class, $handler);
    }

    /**
     * Resolver normalizes lower-case/whitespace wrapped input.
     *
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver::resolve
     */
    public function test_resolve_normalizes_input_before_matching(): void {
        $resolver = new participant_status_handler_resolver(
            new angemeldet_participant_status_handler(),
            new bestanden_participant_status_handler(),
            new noop_participant_status_handler(),
            new abgemeldet_participant_status_handler()
        );

        $handler = $resolver->resolve('  s033_einberufen_kreis  ');

        $this->assertInstanceOf(angemeldet_participant_status_handler::class, $handler);
    }
}
