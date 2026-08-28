[Back to documentation index](../README.md)

# Architecture — Developer guide

How `local_lehrgaengeapi` is put together: the layers, the flow of one sync run, and where to hook in.

---

## Quick orientation

1. Read [The layers](#1-the-layers) to place any class you are looking at.
2. Read [One sync run, step by step](#2-one-sync-run-step-by-step) to follow the control flow.
3. Jump to the API reference for the layer you need to change:
   [API client](API_CLIENT_API.md) · [Sync services](SYNC_SERVICE_API.md) ·
   [Status handlers](STATUS_HANDLERS_API.md) · [Database schema](DATABASE_SCHEMA.md).

---

## Table of Contents

1. [The layers](#1-the-layers)
2. [One sync run, step by step](#2-one-sync-run-step-by-step)
3. [Class map](#3-class-map)
4. [Wiring and the factory](#4-wiring-and-the-factory)
5. [Design decisions worth knowing](#5-design-decisions-worth-knowing)
6. [Extension points](#6-extension-points)
7. [Dead code](#7-dead-code)

---

## 1. The layers

```
Entry points     sync_lehrgaenge_task      manual_import_lehrgaenge_task      admin/*.php
                            \                        /                            |
                             \                      /                             |
Orchestration                 lehrgaenge_sync_service                    import_run_repository
                              /            |            \
Domain services   course_creator   participants_sync_service   tenant_creator
                                     /               \
                            users_creator    participant_course_assigner
                                                       |
                                          participant_status_handler_resolver
                                                       |
                                              participant_status_action

API layer         lehrgaenge_endpoint → api_client → \curl → external ZMS API
                                          |
                              token_authenticator, api_response, api_exception hierarchy

Persistence       coursemap_repository, usermap_repository, import_run_repository
```

Each layer only knows the one below it. The API layer knows nothing about Moodle courses; the domain services know
nothing about HTTP.

---

## 2. One sync run, step by step

Both entry-point tasks follow the same shape:

1. **Take the lock.** `\core\lock\lock_config::get_lock_factory('local_lehrgaengeapi')`, resource `sync_lehrgaenge`,
   timeout `0`.
2. **Open a run row.** `import_run_repository::start($year, $triggeredby)` — status `running`.
3. **Loop over tenants.** `tenants::get_all_with_keys()` returns every tenant whose `apikey_*` setting exists.
4. **Build a service per tenant.** `factory::lehrgaenge_sync_service($apikey)` wires a whole object graph bound to
   that tenant's key.
5. **Sync.** `lehrgaenge_sync_service::sync($tenant, $searchcriteria)`:
   - `GET /lehrgaenge` (with `lehrgangVon` / `lehrgangBis` for a manual import);
   - resolve the target IOMAD company, with the HLFS override;
   - build the shortname, look for an existing course, then the `_alt` variant;
   - create the course from a template when nothing matched, and queue the content copy;
   - record the mapping and sync the participants.
6. **Sync participants** — `participants_sync_service::sync_for_course()`:
   - sleep `requestdelayms`, `GET /lehrgaenge/{id}/teilnehmer`;
   - `users_creator::create()` ensures Moodle users exist;
   - `participant_course_assigner::assign()` enrols, unenrols, groups and completes.
7. **Close the run row.** `mark_success()` / `mark_error()` / `mark_skipped()`, JSON-encoding the summary.
8. **Release the lock** in a `finally` block.

The manual import additionally calls `update_progress()` after each tenant so the admin UI can show partial results
while the run is still going, and notifies the triggering user at the end.

---

## 3. Class map

### Entry points

| Class / file | Role |
|--------------|------|
| `task\sync_lehrgaenge_task` | Weekly scheduled full sync, no date filter, fails fast |
| `task\manual_import_lehrgaenge_task` | Ad-hoc year-filtered import, isolates per-tenant errors, notifies the user |
| `task\copy_course_content_task` | Ad-hoc backup/restore of a template course into a new course |
| `admin/manual_import.php` | Form page that queues the manual import |
| `admin/import_runs.php` | Import history overview and run detail rendering |
| `form\manual_import_form` | The year select |

### Orchestration and domain

| Class | Role |
|-------|------|
| `local\services\lehrgaenge_sync_service` | Per-tenant orchestrator: list → company → shortname → course → participants |
| `local\services\participants_sync_service` | Fetches participants and delegates to user creation and assignment |
| `local\services\participant_course_assigner` | Enrolment, unenrolment, groups, course completion |
| `local\course\course_creator` | Template resolution, `_alt` naming, `create_course()`, queues the content copy |
| `local\users\users_creator` | User matching and creation, email uniqueness |
| `local\tenants\tenants` | The hard-coded tenant list and config-key normalisation |
| `local\tenants\tenant_creator` | Resolves an IOMAD `company` from a tenant array |

### Status handling

| Class | Role |
|-------|------|
| `local\lehrgang_status\participant_status_handler_resolver` | Raw status string → handler |
| `local\lehrgang_status\*_participant_status_handler` | One handler per outcome |
| `local\lehrgang_status\participant_status_action` | Immutable decision object (assign / unassign / complete / noop) |

### API layer

| Class | Role |
|-------|------|
| `api\api_client` | GET requests, mTLS, header parsing, status → exception mapping |
| `api\api_response` | Immutable status / body / headers with JSON decoding |
| `api\auth\token_authenticator` | Adds the `X-MoodleAuthToken` header |
| `api\endpoints\lehrgaenge_endpoint` | Typed facade over the four API paths |
| `api\exceptions\*` | `api_exception` plus one subclass per HTTP failure class |

### Persistence

| Class | Table |
|-------|-------|
| `local\repository\coursemap_repository` | `local_lehrgaengeapi_coursemap` |
| `local\repository\usermap_repository` | `local_lehrgaengeapi_usermap` |
| `local\repository\import_run_repository` | `local_lehrgaengeapi_import_run` |

---

## 4. Wiring and the factory

There is no dependency-injection container. `local_lehrgaengeapi\factory` is a static service locator with two
methods:

```php
factory::lehrgaenge_endpoint(string $token): lehrgaenge_endpoint;
factory::lehrgaenge_sync_service(string $token): lehrgaenge_sync_service;
```

`lehrgaenge_sync_service()` reads `baseurl` and `timeout` from plugin config, builds the client with a
`token_authenticator` for the given key, and constructs the whole domain graph around it.

Because the token is baked in at construction time, a **new service instance is built for every tenant** in the
loop. Do not cache one across tenants.

The constructors of the domain classes take their collaborators as parameters, which is what makes the sync services
testable — the test suite passes a fake `lehrgaenge_endpoint_interface` instead of going through the factory. See
[Testing](TESTING.md).

---

## 5. Design decisions worth knowing

**Create-only, never update.** The sync creates missing courses and users and adjusts enrolments. It never updates
an existing course record. Renames and date changes in the external system are not propagated.

**Shortname is the real identity.** The mapping tables exist and are maintained, but course matching is driven by
the generated shortname, not by `coursemap`. `coursemap` is written after the fact and is effectively an audit
trail. User matching, by contrast, does consult `usermap` first.

**Failure isolation is deliberate and asymmetric.** A participants call that fails takes down only that Lehrgang, in
both tasks. A list call that fails takes down the whole scheduled run, but only that tenant in a manual import. See
[Scheduled tasks](../user/scheduled_tasks/README.md#4-error-handling).

**Backup/restore is asynchronous.** Copying template content inside the sync would make an import unbearably slow
and would hold the lock far longer, so it is queued as an ad-hoc task per created course.

**`http_build_query()` gets an explicit separator.** Moodle's bootstrap sets `arg_separator.output` to `&amp;`, so
building a query string without passing `'&'` explicitly silently produces `amp;lehrgangBis` parameters. The client
passes the separator; keep it that way if you touch `api_client::build_url()`.

**The lock timeout is zero on purpose.** Neither task queues behind the other; the scheduled task throws so
`faildelay` retries it, and the manual import records itself as *skipped*.

---

## 6. Extension points

| To change… | Touch… |
|------------|--------|
| Which statuses enrol, unenrol or complete | `participant_status_handler_resolver::resolve()` — see [Status handlers](STATUS_HANDLERS_API.md) |
| A new outcome (e.g. suspend instead of unenrol) | Add a handler plus a factory method on `participant_status_action`, then handle it in `participant_course_assigner::subscribe_participant()` |
| The shortname convention | `lehrgaenge_sync_service::set_course_identifications()` and `course_creator::resolve_target_shortname()` — both must agree |
| Course-type normalisation | The `$coursematching` array in `lehrgaenge_sync_service` |
| Which fields a new course inherits | `course_creator::build_course_data_from_template()` |
| How users are matched or created | `users_creator::create()` |
| Group naming | `participant_course_assigner::resolve_group_name_from_participant()` |
| A new API endpoint | Add a method to `lehrgaenge_endpoint` and `lehrgaenge_endpoint_interface` |
| A different auth scheme | Implement `authenticator_interface` and pass it to `api_client` |
| The tenant list | `tenants::all()` — see [Tenants](../user/tenants/README.md#6-adding-or-renaming-a-tenant) |

There are no subplugins, no hooks and no events. Every extension point is a code change in this plugin.

---

## 7. Dead code

Worth knowing before you go looking for callers:

- `lehrgaenge_sync_service::sync_one()`, `create_course_from_lehrgang()`, `desired_course_fields()` and
  `get_default_categoryid()` are private and never called. They implement an earlier, template-less creation path.
- `config\moodle_settings_repository` and `config\settings_repository_interface` are never instantiated. Config is
  read with `get_config()` directly in `factory`, `api_client` and `participants_sync_service`.
- `lehrgaenge_endpoint::get_by_id()` and `participant_extern()` are implemented and tested but not used by the sync.
- Every `api_client` method takes an `array $tenant` parameter that it never reads.
- `participant_course_assigner::get_groupcounter()` has a branch that returns a group *name* from a method declared
  `: int`. It is unreachable in practice, because `resolve_group_name_from_participant()` already returns early when
  a group for that start tag exists — but it would be a `TypeError` if it were ever reached.
