[Back to parent section](../README.md)

# Manual import and import history — Administrator guide

How to import a specific year on demand, and how to read what any past run — manual or automatic — actually did.

---

## Quick setup path

1. Open *Site administration → Plugins → Local plugins → Manual Lehrgaenge import*.
2. Pick a year and press **Start import**.
3. You are redirected to the import history; the run appears as *Running*.
4. Wait for the notification, or let the page refresh itself.
5. Open **View details** to see per-tenant and per-course results.

---

## Table of Contents

1. [When to use a manual import](#1-when-to-use-a-manual-import)
2. [Starting an import](#2-starting-an-import)
3. [What happens after you press the button](#3-what-happens-after-you-press-the-button)
4. [Import history](#4-import-history)
5. [The run detail page](#5-the-run-detail-page)
6. [Notifications](#6-notifications)

---

## 1. When to use a manual import

The nightly [scheduled task](../scheduled_tasks/README.md) fetches the Lehrgänge list **without a date filter** —
whatever the API returns by default. A manual import adds an explicit year filter:

```
lehrgangVon = <year>-01-01
lehrgangBis = <year>-12-31
```

Use it to:

- **Backfill a historical year**, so old participants get a completion record in the year's `_alt` course.
- **Re-run the current year** after fixing a template course, an API key or an IOMAD company.
- **Test a new tenant's key** without waiting for the nightly run.

Importing a past year does not create a course per past year — everything is funnelled into a single `_alt` course
of the current year. See [Course creation](../course_creation/README.md#4-past-years-and-_alt-courses).

---

## 2. Starting an import

Page: `/local/lehrgaengeapi/admin/manual_import.php`
(*Site administration → Plugins → Local plugins → Manual Lehrgaenge import*, requires `moodle/site:config`).

The form has a single field:

| Field | Values |
|-------|--------|
| **Year** | Every year from 2012 up to the current year, newest first, defaulting to the current year |

The import always covers **all configured tenants**. There is no per-tenant import.

---

## 3. What happens after you press the button

1. The page probes the plugin's sync lock. If another sync currently holds it, you get
   *"Another sync is already running. Please try again in a few minutes."* and nothing is queued.
2. Otherwise an ad-hoc task `manual_import_lehrgaenge_task` is queued with your user id and the chosen year, and you
   are redirected to the import history with a confirmation.
3. The task runs on the **next cron pass** — not immediately. On a site with cron every minute, expect up to a
   minute of delay before the run starts.
4. When the task starts, it takes the lock for real. If the nightly sync grabbed it in the meantime, the run is
   recorded as *Skipped* and you are notified.

Because the lock probe on the form and the lock acquisition in the task are separate, the probe cannot fully prevent
a race — it just gives immediate feedback in the common case.

A manual import that fails is **not retried**. The error is recorded and you are notified, deliberately: silently
re-running a one-shot import hours later would be surprising.

---

## 4. Import history

Page: `/local/lehrgaengeapi/admin/import_runs.php`
(*Site administration → Plugins → Local plugins → Import history*).

The overview lists the 100 most recent runs, newest first:

| Column | Notes |
|--------|-------|
| **Year** | The imported year, or *Automatic* for an unfiltered scheduled run |
| **Status** | *Running*, *Success*, *Error* or *Skipped* |
| **Triggered by** | The user who started a manual import, or *System (automatic)* |
| **Started** / **Ended** | Timestamps; *Ended* is `-` while the run is in progress |
| **Result** | One-line totals across all tenants, or the error / skip reason |

While any run is *Running*, the page refreshes itself every 10 seconds (every 5 seconds on a run's detail page), so
you can watch progress without reloading. The manual import writes its partial summary after each tenant, so the
counters grow as the run proceeds.

---

## 5. The run detail page

**View details** opens one run. It shows the run metadata, then:

### Per-tenant table

Tenant, created, skipped, failed and total Lehrgänge. Remember that *skipped* mostly means "the course already
existed, participants were still synced" — see
[Course creation](../course_creation/README.md#1-the-matching-order).

### Lehrgaenge with a failed participant sync

Individual Lehrgänge whose `/teilnehmer` call threw, with the external id and the error. The course itself was still
created or matched; only its participant sync was abandoned.

### Tenant-level errors

Tenants whose Lehrgänge list could not be fetched at all. Shown as a warning; the remaining tenants were still
processed. This section only ever appears for manual imports — the scheduled task aborts the whole run instead.

### Participant details per course

A collapsible, scrollable table with one row per course: the twelve user and assignment counters described in
[Participants](../participants/README.md#7-reading-the-counters). The course column links to the Moodle course and
is labelled with the external Lehrgang id.

### Raw data (JSON)

The unmodified `summary` column. Use it when a counter does not add up, or to see statuses the mapping did not
recognise.

---

## 6. Notifications

When a **manual** import finishes, the user who triggered it receives a notification through the
`local_lehrgaengeapi/importcomplete` message provider:

| Outcome | Message |
|---------|---------|
| Success | *Lehrgaenge import `<year>` completed* — results are in the import history |
| Error | *Lehrgaenge import `<year>` failed* — aborted due to an error |
| Skipped | *Lehrgaenge import `<year>` could not start* — another sync held the lock |

The notification links straight to the import history. Popup delivery is enabled by default and email delivery is
permitted; users can change this under *Preferences → Notification preferences*.

Scheduled runs do not notify the user who triggered a manual import (there is none). Instead, the users selected
in the **Notify on automatic import** setting are notified — see
[Configuration](../configuration/README.md#5-notifications-for-automatic-imports) and
[Scheduled tasks](../scheduled_tasks/README.md#6-notifications).
