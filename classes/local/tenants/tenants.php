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

namespace local_lehrgaengeapi\local\tenants;

use core_text;

/**
 * Class tenants
 *
 * @package    local_lehrgaengeapi
 * @copyright  2026 Jacob Viertel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenants {
    /**
     * Returns a tenant abbreviation in config-safe format.
     *
     * @param string $abbr The abbreviation of the tenant.
     * @return string
     */
    protected static function normalise_abbr(string $abbr): string {
        $abbrclean = core_text::strtolower($abbr);
        return preg_replace('/[^a-z0-9_]/', '_', $abbrclean);
    }

    /**
     * Returns a list of all tenants.
     * @return array An array of tenants, each tenant is an associative array with 'name' and 'abbr' keys.
     */
    public static function all(): array {
        return [
            ['name' => 'Landkreis Bergstraße', 'abbr' => 'HP'],
            ['name' => 'Landkreis Darmstadt-Dieburg', 'abbr' => 'DA'],
            ['name' => 'Landkreis Groß-Gerau', 'abbr' => 'GG'],
            ['name' => 'Hochtaunuskreis', 'abbr' => 'HG'],
            ['name' => 'Main-Kinzig-Kreis', 'abbr' => 'MKK'],
            ['name' => 'Main-Taunus-Kreis', 'abbr' => 'MTK'],
            ['name' => 'Odenwaldkreis', 'abbr' => 'ERB'],
            ['name' => 'Landkreis Offenbach', 'abbr' => 'OF'],
            ['name' => 'Rheingau-Taunus-Kreis', 'abbr' => 'RÜD'],
            ['name' => 'Wetteraukreis', 'abbr' => 'FB'],
            ['name' => 'Landkreis Gießen', 'abbr' => 'GI'],
            ['name' => 'Landkreis Limburg-Weilburg', 'abbr' => 'LM'],
            ['name' => 'Landkreis Marburg-Biedenkopf', 'abbr' => 'MR'],
            ['name' => 'Lahn-Dill-Kreis', 'abbr' => 'LDK'],
            ['name' => 'Vogelsbergkreis', 'abbr' => 'VB'],
            ['name' => 'Landkreis Fulda', 'abbr' => 'FD'],
            ['name' => 'Landkreis Hersfeld-Rotenburg', 'abbr' => 'HEF'],
            ['name' => 'Landkreis Kassel', 'abbr' => 'KSL'],
            ['name' => 'Schwalm-Eder-Kreis', 'abbr' => 'HR'],
            ['name' => 'Landkreis Waldeck-Frankenberg', 'abbr' => 'KB'],
            ['name' => 'Werra-Meißner-Kreis', 'abbr' => 'ESW'],
            ['name' => 'Darmstadt', 'abbr' => 'DAS'],
            ['name' => 'Frankfurt am Main', 'abbr' => 'F'],
            ['name' => 'Kassel', 'abbr' => 'KS'],
            ['name' => 'Offenbach am Main', 'abbr' => 'OFS'],
            ['name' => 'Wiesbaden', 'abbr' => 'WI'],
            ['name' => 'Bad Homburg', 'abbr' => 'BHS'],
            ['name' => 'Fulda', 'abbr' => 'FDS'],
            ['name' => 'Hanau', 'abbr' => 'HU'],
            ['name' => 'Gießen', 'abbr' => 'GIS'],
            ['name' => 'Marburg', 'abbr' => 'MRS'],
            ['name' => 'Rüsselsheim', 'abbr' => 'RÜS'],
            ['name' => 'Wetzlar', 'abbr' => 'WES'],
            ['name' => 'Hessische Landesfeuerwehrschule', 'abbr' => 'HLFS'],
            ['name' => 'Hessische Kinder- und Jugendfeuerwehr', 'abbr' => 'HKJF'],
        ];
    }

    /**
     * Returns a tenant by its abbreviation.
     * @param string $abbr The abbreviation of the tenant.
     * @return array|null The tenant with 'name' and 'abbr' keys, or null if not found.
     */
    public static function get_config_key(string $abbr): string {
        return 'apikey_' . self::normalise_abbr($abbr);
    }

    /**
     * Returns the config key for the client certificate path of a tenant.
     *
     * @param string $abbr The abbreviation of the tenant.
     * @return string
     */
    public static function get_certificate_config_key(string $abbr): string {
        return 'certificate_' . self::normalise_abbr($abbr);
    }

    /**
     * Returns the config key for the client key path of a tenant.
     *
     * @param string $abbr The abbreviation of the tenant.
     * @return string
     */
    public static function get_key_config_key(string $abbr): string {
        return 'key_' . self::normalise_abbr($abbr);
    }

    /**
     * Returns all tenants with their corresponding API keys from config.
     * @return array An array of tenants, each tenant is an associative array with 'name', 'abbr', 'configkey' and 'apikey' keys.
     */
    public static function get_all_with_keys(): array {
        $config = get_config('local_lehrgaengeapi');
        $result = [];

        foreach (self::all() as $mandant) {
            $configkey = self::get_config_key($mandant['abbr']);
            if (isset($config->{$configkey})) {
                $certificateconfigkey = self::get_certificate_config_key($mandant['abbr']);
                $keyconfigkey = self::get_key_config_key($mandant['abbr']);
                $result[] = [
                    'name' => $mandant['name'],
                    'abbr' => $mandant['abbr'],
                    'certificate' => $config->{$certificateconfigkey} ?? null,
                    'key' => $config->{$keyconfigkey} ?? null,
                    'configkey' => $configkey,
                    'apikey' => $config->{$configkey},
                ];
            }
        }

        return $result;
    }
}
