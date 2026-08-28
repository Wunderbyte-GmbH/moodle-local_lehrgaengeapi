[Back to parent section](../README.md)

# Scheduled tasks — Reference

`local_lehrgaengeapi` registers one scheduled task and queues two ad-hoc tasks. All of them run through Moodle's
cron.

---

## Quick setup path

1. Open *Site administration → Server → Scheduled tasks*.
2. Search for *Sync Lehrgaenge (external API)*.
3. Adjust the schedule if the default Sunday night slot does not suit you.
4. Run it manually once to verify the configuration.
5. Check the result in the [import history](../manual_import/README.md#4-import-history).

---

## Table of Contents

1. [`sync_lehrgaenge_task`](#1-sync_lehrgaenge_task)
2. [`manual_import_lehrgaenge_task`](#2-manual_import_lehrgaenge_task)
3. [`copy_course_content_task`](#3-copy_course_content_task)
4. [Error handling](#4-error-handling)
5. [Locking](#5-locking)
6. [Running a task by hand](#6-running-a-task-by-hand)

---

## 1. `sync_lehrgaenge_task`

| Setting | Value |
|---------|-------|
| **Class** | `\local_lehrgaengeapi\task\sync_lehrgaenge_task` |
| **Name in the UI** | Sync Lehrgaenge (external API) |
| **Type** | Scheduled |
| **Default schedule** | `0 3 * * 0` — Sundays at 03:00 |
| **Blocking** | No |
| **Fail delay** | 60 seconds |

### Purpose

The routine full sync. It loops over every tenant with an API key setting, fetches that tenant's Lehrgänge list
**without any date filter**, and runs the full course and participant sync for each entry. The result is written to
the import-run log with year `null`, which the UI displays as *Automatic*.

### Performance considerations

This is a heavy task. Runtime is dominated by the external API: one list call per tenant, plus one participants call
per Lehrgang, plus the configured `requestdelayms` before each of those. With 35 tenants and a few thousand
Lehrgänge, a run takes tens of minutes.

It is intentionally scheduled weekly, at night. If you move it to a daily or hourly schedule, watch for `429`
responses and raise `requestdelayms` — see [Configuration](../configuration/README.md#3-rate-limiting-and-timeouts).

Make sure your cron setup does not kill long-running tasks before this one finishes.

---

## 2. `manual_import_lehrgaenge_task`

| Setting | Value |
|---------|-------|
| **Class** | `\local_lehrgaengeapi\task\manual_import_lehrgaenge_task` |
| **Name in the UI** | Manual Lehrgaenge import (year) |
| **Type** | Ad-hoc, queued by the admin UI |

### Purpose

Does the same work as the scheduled task, but with an explicit `lehrgangVon` / `lehrgangBis` filter for one year,
and it remembers which user triggered it so it can notify them afterwards.

It is queued from `/local/lehrgaengeapi/admin/manual_import.php` and picked up by the next cron pass — see
[Manual import](../manual_import/README.md).

The important behavioural difference from the scheduled task is its per-tenant error isolation, described in
[Error handling](#4-error-handling).

---

## 3. `copy_course_content_task`

| Setting | Value |
|---------|-------|
| **Class** | `\local_lehrgaengeapi\task\copy_course_content_task` |
| **Type** | Ad-hoc, queued once per newly created course |

### Purpose

Copies activities and sections from the template course into a newly created course via a backup/restore in
`MODE_IMPORT` with `TARGET_EXISTING_ADDING`, running as the site admin. Before restoring it removes the new course's
auto-generated *Announcements* forum so the template's own forum is not duplicated.

This runs asynchronously so that course creation and participant enrolment are not blocked by a backup. A course
therefore appears **empty** until this task has run. See
[Course creation](../course_creation/README.md#7-copying-the-template-content).

### Performance considerations

One backup and one restore per created course. A large import that creates hundreds of courses queues hundreds of
these. They are queued with the "reschedule existing" flag, so duplicates for the same payload are not stacked.

If a restore pre-check fails, the task logs the pre-check results as JSON and leaves the course empty rather than
retrying forever.

---

## 4. Error handling

The two sync tasks handle failures very differently, which matters when you are diagnosing a bad run.

**Scheduled task — fail fast.** Any exception aborts the entire run, including the tenants that had not been
processed yet. The run is marked *Error*, the exception is logged with its response body, and it is rethrown so
Moodle applies the 60-second `faildelay` and retries later.

| Exception | Logged as |
|-----------|-----------|
| `api_rate_limited_exception` (429) | `rate limited (429). Retry-After=<n>` |
| `api_unauthorized_exception` (401) | `unauthorized (401). Check token setting.` |
| any other `api_exception` / `Throwable` | `unexpected error: <message>` |

So a single tenant with a stale API key stops the whole nightly sync. Check the import history after changing keys.

**Manual import — isolate per tenant.** Each tenant is wrapped in its own `try`/`catch`. A failing tenant is
recorded with an `error` entry in the summary and the run continues with the next one. The run is only marked
*Error* if something outside the tenant loop throws. Failures are not retried.

In **both** tasks, a failure to fetch the participants of one individual Lehrgang is caught at a lower level: the
course is still created and mapped, and the failure is listed under *Lehrgaenge with a failed participant sync* on
the run detail page.

---

## 5. Locking

Both sync tasks take the same lock before doing any work:

```php
$factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
$lock = $factory->get_lock('sync_lehrgaenge', 0);
```

The timeout is `0`, so neither task waits — they react immediately:

| Task | If the lock is held |
|------|---------------------|
| `sync_lehrgaenge_task` | Logs a message and throws, so `faildelay` retries it automatically |
| `manual_import_lehrgaenge_task` | Records the run as *Skipped*, notifies the triggering user, and gives up |

The lock is always released in a `finally` block, so a crashed run does not leave it stuck. If you ever do need to
clear a stale lock, restart the cron process — Moodle's default DB lock factory releases locks when the owning
session ends.

---

## 6. Running a task by hand

Run the scheduled sync immediately:

```bash
php admin/cli/scheduled_task.php --execute='\local_lehrgaengeapi\task\sync_lehrgaenge_task'
```

Process the queued ad-hoc tasks (manual imports and course-content copies):

```bash
php admin/cli/adhoc_task.php --execute
php admin/cli/adhoc_task.php --classname='\local_lehrgaengeapi\task\copy_course_content_task' --execute
```

All tasks write progress with `mtrace()`, prefixed `local_lehrgaengeapi:` or `copy_course_content_task:`, so the
cron log is the fastest place to watch a run. The same information, structured, ends up in the
[import history](../manual_import/README.md#4-import-history).
