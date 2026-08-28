[Back to documentation index](../README.md)

# Database schema — Developer guide

The three tables `local_lehrgaengeapi` owns, column by column, plus the Moodle tables it reads and writes.

---

## Quick orientation

1. Two mapping tables link external ids to Moodle ids.
2. One log table records every sync run.
3. Everything else the plugin touches is core Moodle: `course`, `user`, `enrol`, `user_enrolments`, `groups`,
   `groups_members`, `course_completions`, and IOMAD's `company`.

Schema is defined in `db/install.xml` and evolved in `db/upgrade.php`. Never edit `install.xml` by hand — use the
XMLDB editor.

---

## Table of Contents

1. [`local_lehrgaengeapi_coursemap`](#1-local_lehrgaengeapi_coursemap)
2. [`local_lehrgaengeapi_usermap`](#2-local_lehrgaengeapi_usermap)
3. [`local_lehrgaengeapi_import_run`](#3-local_lehrgaengeapi_import_run)
4. [The summary column](#4-the-summary-column)
5. [Core tables the plugin touches](#5-core-tables-the-plugin-touches)
6. [Upgrade steps](#6-upgrade-steps)
7. [Useful queries](#7-useful-queries)

---

## 1. `local_lehrgaengeapi_coursemap`

Maps an external `Lehrgang.id` to a Moodle course.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | int(10), sequence | no | Primary key |
| `externalid` | char(64) | no | The external `Lehrgang.id` |
| `courseid` | int(10) | yes | Moodle `course.id`, null until linked |
| `timecreated` | int(10) | no | |
| `timemodified` | int(10) | no | Only moves when `courseid` actually changes |

| Index | Type | Columns |
|-------|------|---------|
| `externalid_uix` | unique | `externalid` |
| `courseid_ix` | non-unique | `courseid` |

Accessed through `coursemap_repository`. Note that `courseid` is **not** unique: several Lehrgänge from past years
legitimately map to the same `_alt` course.

This table is an audit trail rather than the matching key — course matching is done by shortname. Truncating it does
not break a re-import; the rows are simply recreated.

---

## 2. `local_lehrgaengeapi_usermap`

Maps an external participant id to a Moodle user.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | int(10), sequence | no | Primary key |
| `externalinitialid` | char(64) | no | Despite the name, this holds the participant's **`id`**, not their `initialId` |
| `userid` | int(10) | yes | Moodle `user.id`, null until linked |
| `timecreated` | int(10) | no | |
| `timemodified` | int(10) | no | Only moves when `userid` actually changes |

| Index | Type | Columns |
|-------|------|---------|
| `externalinitialid_uix` | unique | `externalinitialid` |
| `userid_ix` | non-unique | `userid` |

Accessed through `usermap_repository`, whose method names (`get_by_externalid`, `set_userid`) all address this
column. The column name is a leftover from an earlier design; the value written is `$participant['id']`.

Unlike `coursemap`, this table **is** consulted first when matching a participant to a Moodle user, so truncating it
falls back to matching by `idnumber` and `username`.

---

## 3. `local_lehrgaengeapi_import_run`

One row per sync run, manual or automatic.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | int(10), sequence | no | Primary key |
| `year` | int(10) | yes | Filtered year; **null** for an automatic run with no year filter |
| `status` | char(20) | no | `running`, `success`, `error`, `skipped`; default `running` |
| `triggeredby` | int(10) | yes | `user.id` for a manual import, null for cron |
| `timestarted` | int(10) | no | Default `0` |
| `timeended` | int(10) | yes | Null while running |
| `summary` | text | yes | JSON — see below |

| Index | Type | Columns |
|-------|------|---------|
| `timestarted_ix` | non-unique | `timestarted` |

Status constants live on the repository: `import_run_repository::STATUS_RUNNING`, `STATUS_SUCCESS`, `STATUS_ERROR`,
`STATUS_SKIPPED`.

The table has no retention policy — rows accumulate. `get_recent()` caps the overview at 100 rows, but nothing
prunes. On a busy site, add your own housekeeping.

A run left as `running` indicates a crashed cron process; nothing reconciles those automatically.

---

## 4. The summary column

`summary` is `json_encode(..., JSON_UNESCAPED_UNICODE)` of different shapes depending on status:

**Success or running** — keyed by tenant abbreviation:

```json
{
  "FD": {
    "created": 3,
    "skipped": 41,
    "total": 44,
    "failed": [{"id": "FD-EXT-X-2025", "error": "External API request failed: HTTP 500 for …"}],
    "userreport": {
      "42": {
        "florixid": "FD-EXT-X-2025",
        "users": {"created": 2, "existing": 9, "skipped": 0, "total": 11},
        "assignments": {"skipped": 0, "noop": 1, "enrolled": 2, "unenrolled": 0,
                        "alreadyenrolled": 8, "alreadyunenrolled": 0, "completed": 0, "total": 11}
      }
    }
  },
  "HP": {"created": 0, "skipped": 0, "total": 0, "failed": [], "userreport": []}
}
```

A manual import adds `"error": "…"` to a tenant entry that failed wholesale.

**Error:** `{"error": "…"}` — **Skipped:** `{"reason": "…"}`

Decode it with the static helper rather than by hand:

```php
$decoded = import_run_repository::decode_summary($run);   // always an array, [] when empty or malformed
```

`update_progress()` rewrites this column mid-run without touching `status` or `timeended`, which is what lets the
history page show partial results while a manual import is still going.

---

## 5. Core tables the plugin touches

| Table | Read | Written |
|-------|------|---------|
| `course` | By `shortname` (matching), by `id` | Via `create_course()` |
| `company` (IOMAD) | By `name` + `code` | Via `company::add_course()` |
| `user` | By `idnumber`, `username`, `email`, `id` | Via `user_create_user()`; `update_record()` in `check_and_fill_ids()` |
| `enrol` | The `manual` instance of the course | No |
| `user_enrolments` | To detect an existing manual enrolment | Via `enrol_plugin::enrol_user()` / `unenrol_user()` |
| `groups` | By course and name pattern | Via `groups_create_group()` |
| `groups_members` | — | Via `groups_add_member()` |
| `course_completions` | To verify completion | Via `completion_completion::mark_complete()` |
| `config_plugins` | All settings, including `apikey_*` | Through the settings UI only |
| `course_modules`, `modules`, `forum` | To find the auto-created announcements forum | Via `course_delete_module()` |

Only the two `update_record()` calls in `users_creator::check_and_fill_ids()` write directly to a core table without
going through a core API. Everything else uses Moodle's own functions.

---

## 6. Upgrade steps

| Version | Step |
|---------|------|
| `2026021700` | Creates `local_lehrgaengeapi_coursemap` and `local_lehrgaengeapi_usermap` with their unique and non-unique indexes |
| `2026081700` | Creates `local_lehrgaengeapi_import_run` and its `timestarted` index |

Every step is guarded by `table_exists()` / `index_exists()`, so re-running is safe. Note that the `install.xml`
header still declares `VERSION="2026021700"` while the current plugin version is `2026081700` — cosmetic, but worth
bumping next time the schema changes.

---

## 7. Useful queries

Recent runs and their outcome:

```sql
SELECT id, year, status, FROM_UNIXTIME(timestarted) AS started,
       FROM_UNIXTIME(timeended) AS ended, triggeredby
  FROM mdl_local_lehrgaengeapi_import_run
 ORDER BY timestarted DESC
 LIMIT 20;
```

Runs stuck in `running` (crashed cron):

```sql
SELECT id, year, FROM_UNIXTIME(timestarted)
  FROM mdl_local_lehrgaengeapi_import_run
 WHERE status = 'running' AND timestarted < UNIX_TIMESTAMP() - 86400;
```

Which Lehrgänge share one Moodle course (the `_alt` case):

```sql
SELECT courseid, COUNT(*) AS lehrgaenge, GROUP_CONCAT(externalid)
  FROM mdl_local_lehrgaengeapi_coursemap
 WHERE courseid IS NOT NULL
 GROUP BY courseid
HAVING COUNT(*) > 1;
```

Mapping rows that never resolved to a Moodle object:

```sql
SELECT COUNT(*) FROM mdl_local_lehrgaengeapi_coursemap WHERE courseid IS NULL;
SELECT COUNT(*) FROM mdl_local_lehrgaengeapi_usermap   WHERE userid   IS NULL;
```

Users provisioned by the plugin with a placeholder email:

```sql
SELECT u.id, u.username, u.idnumber, u.email
  FROM mdl_user u
  JOIN mdl_local_lehrgaengeapi_usermap m ON m.userid = u.id
 WHERE u.email LIKE '%@invalid.local' AND u.deleted = 0;
```

Adjust the `mdl_` prefix to your installation.
