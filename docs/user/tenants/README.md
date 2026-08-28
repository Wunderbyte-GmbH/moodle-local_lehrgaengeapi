[Back to parent section](../README.md)

# Tenants (Mandanten) — Administrator guide

The plugin is multi-tenant. Every Hessian district, city and school is a *Mandant* with its own API key on the
external system and its own IOMAD company in Moodle.

---

## Quick setup path

1. Create an IOMAD company whose **name** and **code** match the tenant exactly (table below).
2. Enter the tenant's API key on the settings page, under *Tenant settings*.
3. Create the template courses in that company's course category — see [Course creation](../course_creation/README.md#3-template-courses).
4. Run a [manual import](../manual_import/README.md) and check the tenant's row in the import history.

---

## Table of Contents

1. [How a tenant is defined](#1-how-a-tenant-is-defined)
2. [The tenant list](#2-the-tenant-list)
3. [Linking a tenant to an IOMAD company](#3-linking-a-tenant-to-an-iomad-company)
4. [Which tenants a run processes](#4-which-tenants-a-run-processes)
5. [The HLFS special case](#5-the-hlfs-special-case)
6. [Adding or renaming a tenant](#6-adding-or-renaming-a-tenant)

---

## 1. How a tenant is defined

Tenants are **hard-coded** in `classes/local/tenants/tenants.php` as a list of name/abbreviation pairs. They are not
stored in the database and cannot be edited through the UI.

Each tenant contributes:

- one **API key setting**, named `apikey_<normalised abbreviation>`, rendered on the settings page;
- one expected **IOMAD company**, matched on `name` + `code`.

The abbreviation is normalised for the config name by lowercasing it and replacing every character outside
`[a-z0-9_]` with an underscore. This is a **byte-wise** replacement, so a two-byte umlaut becomes *two*
underscores — `RÜD` becomes `r__d`, not `r_d`. The rendered field label always shows the real abbreviation, so you
normally never see this; it matters only when you set values from the CLI.

---

## 2. The tenant list

35 tenants are defined: 21 districts (*Landkreise*), 12 cities with their own fire service, and 2 state
institutions.

| Tenant name | Abbreviation | API key config name |
|-------------|--------------|---------------------|
| Landkreis Bergstraße | `HP` | `apikey_hp` |
| Landkreis Darmstadt-Dieburg | `DA` | `apikey_da` |
| Landkreis Groß-Gerau | `GG` | `apikey_gg` |
| Hochtaunuskreis | `HG` | `apikey_hg` |
| Main-Kinzig-Kreis | `MKK` | `apikey_mkk` |
| Main-Taunus-Kreis | `MTK` | `apikey_mtk` |
| Odenwaldkreis | `ERB` | `apikey_erb` |
| Landkreis Offenbach | `OF` | `apikey_of` |
| Rheingau-Taunus-Kreis | `RÜD` | `apikey_r__d` |
| Wetteraukreis | `FB` | `apikey_fb` |
| Landkreis Gießen | `GI` | `apikey_gi` |
| Landkreis Limburg-Weilburg | `LM` | `apikey_lm` |
| Landkreis Marburg-Biedenkopf | `MR` | `apikey_mr` |
| Lahn-Dill-Kreis | `LDK` | `apikey_ldk` |
| Vogelsbergkreis | `VB` | `apikey_vb` |
| Landkreis Fulda | `FD` | `apikey_fd` |
| Landkreis Hersfeld-Rotenburg | `HEF` | `apikey_hef` |
| Landkreis Kassel | `KSL` | `apikey_ksl` |
| Schwalm-Eder-Kreis | `HR` | `apikey_hr` |
| Landkreis Waldeck-Frankenberg | `KB` | `apikey_kb` |
| Werra-Meißner-Kreis | `ESW` | `apikey_esw` |
| Darmstadt | `DAS` | `apikey_das` |
| Frankfurt am Main | `F` | `apikey_f` |
| Kassel | `KS` | `apikey_ks` |
| Offenbach am Main | `OFS` | `apikey_ofs` |
| Wiesbaden | `WI` | `apikey_wi` |
| Bad Homburg | `BHS` | `apikey_bhs` |
| Fulda | `FDS` | `apikey_fds` |
| Hanau | `HU` | `apikey_hu` |
| Gießen | `GIS` | `apikey_gis` |
| Marburg | `MRS` | `apikey_mrs` |
| Rüsselsheim | `RÜS` | `apikey_r__s` |
| Wetzlar | `WES` | `apikey_wes` |
| Hessische Landesfeuerwehrschule | `HLFS` | `apikey_hlfs` |
| Hessische Kinder- und Jugendfeuerwehr | `HKJF` | `apikey_hkjf` |

Note the deliberate collisions between a district and its independent city — `Landkreis Fulda (FD)` and
`Fulda (FDS)`, `Landkreis Gießen (GI)` and `Gießen (GIS)`, `Landkreis Kassel (KSL)` and `Kassel (KS)`. These are
separate tenants with separate API keys and separate companies.

---

## 3. Linking a tenant to an IOMAD company

The lookup is an exact two-column match:

```php
$DB->get_record('company', ['name' => $tenant['name'], 'code' => $tenant['abbr']], 'id');
```

So the company's **name** must be byte-identical to the tenant name in the table above — including the `ß` in
*Bergstraße*, the `ß` in *Groß-Gerau* and the `ß` in *Werra-Meißner-Kreis* — and its **code** must be the
abbreviation.

The company controls two things:

| Company field | Used for |
|---------------|----------|
| `category` | The course category every course for this tenant is created in |
| `code` | The first segment of the generated course shortname, e.g. `FD-AGT-25` |

Newly created courses are also registered with the company via `company::add_course()`.

If the company is missing or the name does not match exactly, **every Lehrgang of that tenant is skipped**. The
import history will show the tenant with `created = 0` and `skipped = total`.

---

## 4. Which tenants a run processes

Both the scheduled task and the manual import loop over `tenants::get_all_with_keys()`, which returns every tenant
whose API key setting **exists** in `config_plugins` — including one that was saved as an empty string.

The sync then short-circuits on an empty key:

```php
if (empty($tenant['apikey'])) {
    return ['created' => 0, 'skipped' => 0, 'total' => 0, 'failed' => [], 'userreport' => []];
}
```

The practical effect: a tenant with no key still appears in the import-history summary, but with all counters at
zero and no API call made. That is the expected state for the 30-odd tenants you are not entitled to read.

A tenant whose key is *wrong* behaves very differently — the API answers `401`, and in the **scheduled** task that
aborts the entire run for all remaining tenants. The manual import catches per-tenant failures and carries on. See
[Scheduled tasks](../scheduled_tasks/README.md#4-error-handling) for the difference.

---

## 5. The HLFS special case

Independently of which tenant's API key produced a Lehrgang, the plugin re-routes it to the
**Hessische Landesfeuerwehrschule** company when the Lehrgang's location starts with `HLFS`:

```php
if (!empty($item['ort']) && strpos($item['ort'], 'HLFS') === 0 && $hlfscompany) {
    $currentcompany = $hlfscompany;
}
```

The course is then created in the HLFS category with an `HLFS-…` shortname. Details in
[Course creation](../course_creation/README.md#5-hlfs-routing).

Because of this, the HLFS company should exist even on a site that does not sync HLFS's own API key. If it does not,
the plugin falls back to the Lehrgang's own tenant company.

---

## 6. Adding or renaming a tenant

The tenant list is code, so both operations need a code change in
`classes/local/tenants/tenants.php::all()` followed by a plugin release:

```php
['name' => 'Neuer Landkreis', 'abbr' => 'NLK'],
```

After deploying, the new API key field appears on the settings page automatically.

**Renaming is not free.** The tenant name and abbreviation are the join key to the IOMAD company, and the
abbreviation is baked into every course shortname already created (`FD-AGT-25`). Changing an abbreviation means the
existing courses no longer match the naming convention, so the next run will create fresh courses alongside them.
Rename the IOMAD company and the existing course shortnames in the same maintenance window, or keep the old
abbreviation.
