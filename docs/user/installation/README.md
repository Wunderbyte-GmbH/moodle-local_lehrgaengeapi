[Back to parent section](../README.md)

# Installation — Administrator guide

How to install `local_lehrgaengeapi`, and what has to exist on the site before it can do anything useful.

---

## Quick setup path

1. Make sure IOMAD is installed and one company exists per tenant.
2. Copy the plugin to `local/lehrgaengeapi`.
3. Run the Moodle upgrade to create the plugin's database tables.
4. Configure the API connection — see [Configuration](../configuration/README.md).
5. Enter at least one tenant API key — see [Tenants](../tenants/README.md).
6. Create the template courses — see [Course creation](../course_creation/README.md#3-template-courses).

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [The IOMAD dependency](#2-the-iomad-dependency)
3. [Installing the plugin](#3-installing-the-plugin)
4. [What the installation creates](#4-what-the-installation-creates)
5. [Upgrading](#5-upgrading)
6. [Uninstalling](#6-uninstalling)

---

## 1. Requirements

| Requirement | Value |
|-------------|-------|
| **Component** | `local_lehrgaengeapi` |
| **Release** | 0.2.5 (`$plugin->version = 2026081700`) |
| **Moodle** | 4.5 (405) and 5.0 (500) are declared as supported; `$plugin->requires = 2022112800` (Moodle 4.1) |
| **Maturity** | `MATURITY_STABLE` |
| **PHP** | 8.1+ (the code uses `readonly` properties, `never` return types and `str_contains()`) |
| **IOMAD** | Required at runtime — see below |
| **Outbound HTTPS** | The Moodle server must be able to reach the external ZMS API, usually with a client certificate |

The plugin has no capabilities of its own. All admin pages are protected by `moodle/site:config`, so only site
administrators can reach them.

---

## 2. The IOMAD dependency

The plugin uses IOMAD's `company` class to decide **which course category** a new course is created in and to
register the created course with the tenant:

```php
$company = $this->tenantcreator->get_tenant($tenant);   // reads the {company} table
$data->category = $tenant->get('category');             // company course category
$currentcompany->add_course($course);                   // registers the course with the company
```

This dependency is **hard but undeclared** — `version.php` contains no `$plugin->dependencies` entry, so Moodle will
happily let you install the plugin on a site without IOMAD. It will then fail at sync time, because the global
`company` class does not exist.

### What you must prepare

For every tenant you want to sync, an IOMAD company must exist whose

- `name` matches the tenant name, **and**
- `code` matches the tenant abbreviation

exactly as listed in [Tenants](../tenants/README.md). The lookup is an exact match on both columns:

```php
$DB->get_record('company', ['name' => $tenant['name'], 'code' => $tenant['abbr']], 'id');
```

If no company matches, every Lehrgang of that tenant is counted as *skipped* and nothing is created. A company with
the name `Hessische Landesfeuerwehrschule` and code `HLFS` should always exist, because it is used as the fallback
target for Lehrgänge held at an HLFS location — see [Course creation](../course_creation/README.md#5-hlfs-routing).

---

## 3. Installing the plugin

```bash
cd /path/to/moodle
git clone <repository-url> local/lehrgaengeapi
php admin/cli/upgrade.php
```

Or install the ZIP through *Site administration → Plugins → Install plugins*.

After the upgrade, the settings page is available at
*Site administration → Plugins → Local plugins → Lehrgaenge API*
([/admin/settings.php?section=local_lehrgaengeapi](/admin/settings.php?section=local_lehrgaengeapi)).

---

## 4. What the installation creates

### Database tables

| Table | Purpose |
|-------|---------|
| `local_lehrgaengeapi_coursemap` | Maps an external `Lehrgang.id` to a Moodle `course.id` |
| `local_lehrgaengeapi_usermap` | Maps an external participant id to a Moodle `user.id` |
| `local_lehrgaengeapi_import_run` | Log of every manual and automatic sync run |

Full column reference: [Database schema](../../developer-guides/DATABASE_SCHEMA.md).

### Admin pages

| Page | URL |
|------|-----|
| Settings | `/admin/settings.php?section=local_lehrgaengeapi` |
| Manual Lehrgaenge import | `/local/lehrgaengeapi/admin/manual_import.php` |
| Import history | `/local/lehrgaengeapi/admin/import_runs.php` |

Both admin pages are registered as `admin_externalpage` entries under *Local plugins*.

### Scheduled task

`\local_lehrgaengeapi\task\sync_lehrgaenge_task`, by default every Sunday at 03:00 — see
[Scheduled tasks](../scheduled_tasks/README.md).

### Custom profile field

`ausbildungen` (Textarea, category *Lehrgaenge*, locked, not visible) — holds the trainings of a participant as JSON,
see [Participants](../participants/README.md#3-ausbildungen-in-the-user-profile). It is created on installation and
on upgrade.

### Message provider

`local_lehrgaengeapi/importcomplete` — notifies the user who triggered a manual import once it finishes, and the
users selected in the *Notify on automatic import* setting when the scheduled sync finishes or fails. Popup
notifications are enabled by default, email is permitted but off by default.

---

## 5. Upgrading

Upgrades are handled by `db/upgrade.php`:

| Version | Step |
|---------|------|
| `2026021700` | Creates `local_lehrgaengeapi_coursemap` and `local_lehrgaengeapi_usermap` with their indexes |
| `2026081700` | Creates `local_lehrgaengeapi_import_run` with its `timestarted` index |
| `2026101600` | Creates the custom profile field `ausbildungen` (skipped if it already exists) |

Every step is guarded by `table_exists()` / `index_exists()` or, for the profile field, by a lookup of its shortname, so
re-running an upgrade is safe.

Run upgrades the usual way:

```bash
php admin/cli/upgrade.php
```

---

## 6. Uninstalling

Uninstall through *Site administration → Plugins → Plugins overview*. Moodle drops the three plugin tables and the
plugin's configuration.

**Nothing else is removed.** The custom profile field `ausbildungen` and the values saved in it stay as well. Courses,
users, enrolments, groups and course-completion records created by the plugin
stay exactly as they are — they are ordinary Moodle objects. If you reinstall, the mapping tables start empty, and
the sync will re-link existing courses by shortname and existing users by `idnumber` / `username` rather than
duplicating them.
