[Back to documentation index](../README.md)

# Sync services — Developer guide

`lehrgaenge_sync_service`, `participants_sync_service` and the report structure they produce.

---

## Quick orientation

1. `lehrgaenge_sync_service::sync()` is the per-tenant entry point — everything else hangs off it.
2. Its return value is the per-tenant summary that ends up JSON-encoded in the import-run log.
3. Type-hint `lehrgaenge_endpoint_interface`, not the concrete endpoint, so tests can inject a fake.

---

## Table of Contents

1. [`lehrgaenge_sync_service`](#1-lehrgaenge_sync_service)
2. [The summary structure](#2-the-summary-structure)
3. [`participants_sync_service`](#3-participants_sync_service)
4. [`course_creator`](#4-course_creator)
5. [`users_creator`](#5-users_creator)
6. [`participant_course_assigner`](#6-participant_course_assigner)
7. [Repositories](#7-repositories)
8. [Changing the sync](#8-changing-the-sync)

---

## 1. `lehrgaenge_sync_service`

```php
public function __construct(
    lehrgaenge_endpoint_interface $endpoint,
    coursemap_repository $coursemap,
    course_creator $coursecreator,
    participants_sync_service $participantssync,
    tenant_creator $tenantcreator
);

public function sync(array $tenant, array|string|null $searchcriteria = null): array;
```

`$tenant` is one entry from `tenants::get_all_with_keys()`:

```php
['name' => 'Landkreis Fulda', 'abbr' => 'FD', 'configkey' => 'apikey_fd', 'apikey' => '…']
```

`$searchcriteria` is passed straight to `lehrgaenge_endpoint::list()`. The manual import passes
`['lehrgangVon' => 'YYYY-01-01', 'lehrgangBis' => 'YYYY-12-31']`; the scheduled task passes `null`.

### Control flow per Lehrgang

```php
$company = $this->tenantcreator->get_tenant($tenant);          // once, before the loop
$hlfscompany = $this->get_main_company();                      // once, before the loop

foreach ($items as $item) {
    // 1. HLFS override on the venue prefix.
    $currentcompany = (strpos($item['ort'] ?? '', 'HLFS') === 0 && $hlfscompany) ? $hlfscompany : $company;

    // 2. Guards: payload must be an array, company must exist, id must be non-empty -> skipped.

    // 3. Shortname: <company code>-<mapped kurzbezeichnung>-<substr(endTag, 2, 2)>
    $identifications = $this->set_course_identifications($item, $currentcompany);
    $shortname = implode('-', $identifications);

    // 4. Existing course by shortname, then by <code>-<type>-<current year>_alt for past years.

    // 5a. Found     -> set_courseid(), sync participants, $skipped++
    // 5b. Not found -> course_creator::create(), add_course(), sync participants, set_courseid(), $created++
}
```

Two guards deserve attention when you change this:

- `$currentcompany` is resolved **before** the `is_array($item)` check, so `$item['ort']` is read from a value that
  has not been validated yet. Keep the payload well-formed.
- A `Throwable` from the participant sync is caught per Lehrgang and appended to `failed`. Course creation errors
  are **not** caught here — they propagate up to the task.

### Naming helpers

| Method | Purpose |
|--------|---------|
| `set_course_identifications()` | Returns `['tenant' => company code, 'coursename' => mapped type, 'year' => two digits]` |
| `resolve_coursename_identifier()` | Applies the `$coursematching` table (and contains the unreachable F-III branch) |
| `resolve_alt_shortname_for_past_course()` | `<code>-<type>-<current yy>_alt` when the Lehrgang year is in the past |
| `build_assignment_course_payload()` | Adds `_skipgroupassignment` to the payload for `_alt` courses |
| `get_main_company()` | Looks up the `Hessische Landesfeuerwehrschule` / `HLFS` company |

`$coursematching` maps API `kurzbezeichnung` values to shortname segments; unmapped values pass through unchanged.
See [Course creation](../user/course_creation/README.md#8-course-type-mapping).

The `_skipgroupassignment` flag is an in-memory marker on the course payload array, not a database field. It is the
only channel by which the sync service tells the assigner to skip groups.

---

## 2. The summary structure

`sync()` returns, and the tasks collect per tenant:

```php
[
  'created' => 3,          // courses created this run
  'skipped' => 41,         // Lehrgänge whose course already existed, or that were rejected by a guard
  'total'   => 44,         // entries in the API response
  'failed'  => [           // participant-sync failures, one entry per Lehrgang
      ['id' => 'FD-EXT-X-2025', 'error' => 'External API request failed: HTTP 500 for …'],
  ],
  'userreport' => [        // keyed by Moodle course id
      42 => [
          'florixid'    => 'FD-EXT-X-2025',
          'users'       => ['created' => 2, 'existing' => 9, 'skipped' => 0, 'total' => 11],
          'assignments' => ['skipped' => 0, 'noop' => 1, 'enrolled' => 2, 'unenrolled' => 0,
                            'alreadyenrolled' => 8, 'alreadyunenrolled' => 0, 'completed' => 0,
                            'total' => 11],
      ],
  ],
]
```

The tasks wrap this in a per-tenant map (`['FD' => [...], 'HP' => [...]]`) and JSON-encode it into
`local_lehrgaengeapi_import_run.summary`. The manual import adds an `'error' => '…'` key to a tenant's entry when
that tenant failed wholesale.

Note that `userreport` is keyed by **course id**, so several Lehrgänge sharing one `_alt` course overwrite each
other's entry — the last one wins. `skipped` conflates "already existed" with "rejected by a guard"; if you need to
tell them apart, that is a change to `sync()`.

`admin/import_runs.php` is the only consumer of this structure. Any change here must be mirrored there.

---

## 3. `participants_sync_service`

```php
public function __construct(
    lehrgaenge_endpoint_interface $endpoint,
    users_creator $usercreator,
    participant_course_assigner $assigner
);

public function sync_for_course(
    string $externalid, int $courseid, array $course, array $tenant
): array;
```

Thin by design:

1. `usleep(requestdelayms * 1000)` — the only rate-limit throttle in the plugin.
2. `GET /lehrgaenge/{externalid}/teilnehmer`.
3. Empty or non-array response → return zeroed counters immediately.
4. `users_creator::create($participants)`.
5. `participant_course_assigner::assign($participants, $courseid, $course)`.

`$course` is the Lehrgang payload, possibly carrying `_skipgroupassignment`.

---

## 4. `course_creator`

```php
public function create(company $tenant, array $item, array $identifications): ?\stdClass;
```

Returns `null` — and the Lehrgang is counted as skipped — when no template can be resolved, or when a course with
the target shortname already exists.

Template resolution order:

1. `get_previous_course()` — `<code>-<type>-<year - 1>`.
2. `get_local_template_course()` — a course whose shortname is exactly the course type, excluding the site course.

Then it copies a fixed field list from the template (`summary`, `format`, `numsections`, `groupmode`,
`enablecompletion`, …), overrides `category` / `fullname` / `shortname` / `idnumber` / `visible`, sets `startdate`
and `enddate` to 1 January and 31 December of the Lehrgang year, calls `create_course()`, and queues
`copy_course_content_task`.

For an `_alt` target the fullname gets the localised *Completed* suffix appended
(`get_string('completion', 'local_lehrgaengeapi')`).

> `resolve_target_shortname()` here and `resolve_alt_shortname_for_past_course()` in the sync service implement the
> same `_alt` rule independently. Change both together or the sync will look for a name the creator does not
> produce.

---

## 5. `users_creator`

```php
public function create(array $participants): array;   // ['created','existing','skipped','total']
```

Per participant, keyed on `$p['id']`:

1. `usermap_repository::ensure($id)`, then the mapped `user.id` if it resolves to a live user.
2. A user with `idnumber = $id`.
3. A user with `username = $id` on the local mnet host.
4. Otherwise create one.

Details that bite:

- `check_and_fill_ids()` **rewrites the username and password** of a matched user whose username differs from the
  participant's `initialId`. It also backfills an empty `idnumber`.
- The password is `hash_internal_user_password($username . 'Hlfs#')` — deterministic, and derived from data the
  external system controls. Treat these accounts as provisioned, not as credentials.
- `make_unique_email()` appends `+1`, `+2`, … to the local part while `allowaccountssameemail` is off, falling back
  to a placeholder after 50 attempts.
- `user_create_user($newuser, false, false)` suppresses both the password-update and the user-created event, so no
  welcome email is sent.
- A `dml_write_exception` on insert is treated as a concurrent creation of the same username and resolved to the
  existing user; anything else is rethrown.

---

## 6. `participant_course_assigner`

```php
public function assign(array $participants, int $courseid, array $course): array;
```

Constructed with no arguments — it builds its own `usermap_repository` and status resolver, and looks up the
`manual` enrol plugin. It returns early, counting every participant as `skipped`, when the manual plugin is
unavailable or the course has no manual enrolment instance.

Per participant:

1. Resolve the Moodle user via `usermap` (skip if unmapped or deleted).
2. `participant_status_handler_resolver::resolve($participant['status'])` → handler → `participant_status_action`.
3. `should_not_do_anything()` → `noop++`.
4. `should_assign()` → enrol via the manual instance with `enrol.roleid`, then ensure group membership unless
   `_skipgroupassignment` is set.
5. `should_unassign()` → unenrol if a `user_enrolments` row exists, else `alreadyunenrolled++`.
6. `should_complete()` → `completion_completion::mark_complete(time())`, verified by re-reading
   `course_completions.timecompleted`.

Group naming (`G01 (2025-03-10)`) is derived from the payload's `startTag`; an existing group whose name ends with
` (<startTag>)` is reused, otherwise the next free `G<nn>` counter is taken.

The class caches `courseid`, context, manual instance and course payload in properties, so **one instance is bound
to one course at a time**. It is nonetheless constructed once per tenant and reused across courses — `assign()`
resets all four every call. Keep that invariant if you add state.

---

## 7. Repositories

`coursemap_repository` and `usermap_repository` share a shape:

```php
get_by_externalid(string $externalid): ?stdClass;
ensure(string $externalid): stdClass;              // insert if missing
set_courseid|set_userid(string $externalid, int $id): stdClass;
```

`set_*()` is idempotent — it returns early when the id already matches, so `timemodified` only moves on a real
change.

`import_run_repository` is documented in [Database schema](DATABASE_SCHEMA.md#3-local_lehrgaengeapi_import_run) and
exposes `start()`, `mark_success()`, `mark_error()`, `mark_skipped()`, `update_progress()`, `get()`, `get_recent()`
and the static `decode_summary()`.

---

## 8. Changing the sync

| Goal | Where |
|------|-------|
| Different shortname convention | `set_course_identifications()` **and** `resolve_target_shortname()` |
| Different template resolution | `course_creator::get_previous_course()` / `get_local_template_course()` |
| Propagate updates to existing courses | New branch in `sync()` where `$existing` is found — currently a no-op by design |
| More inherited course fields | The `$fields` array in `build_course_data_from_template()` |
| Different user matching | `users_creator::create()` |
| Different enrolment method | `participant_course_assigner` — replace the `manual` lookups |
| New counters | The report arrays **and** `admin/import_runs.php`, which renders fixed columns |

Whatever you change, add a service-level test with a fake endpoint rather than a unit test of a private helper —
see [Testing](TESTING.md).
