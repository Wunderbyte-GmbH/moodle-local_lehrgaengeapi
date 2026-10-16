[Back to parent section](../README.md)

# Troubleshooting — Administrator guide

Symptom → cause → fix, plus the known quirks that are easy to mistake for bugs in your configuration.

---

## Quick setup path

1. Open the [import history](../manual_import/README.md#4-import-history) and find the failing run.
2. Read the *Result* column, then **View details**.
3. Expand *Raw data (JSON)* — it holds the exact API error and the per-course counters.
4. Cross-check the symptom in the tables below.
5. Re-run a manual import for one year to verify the fix.

---

## Table of Contents

1. [Connection and authentication](#1-connection-and-authentication)
2. [Nothing is imported](#2-nothing-is-imported)
3. [Courses are wrong or missing](#3-courses-are-wrong-or-missing)
4. [Participants are wrong or missing](#4-participants-are-wrong-or-missing)
5. [Runs never start or never finish](#5-runs-never-start-or-never-finish)
6. [Known quirks](#6-known-quirks)
7. [Where to look](#7-where-to-look)

---

## 1. Connection and authentication

| Symptom | Cause | Fix |
|---------|-------|-----|
| `External API request failed: HTTP 0 for …` | No HTTP response at all: empty base URL, DNS failure, firewall, or timeout | The message appends the cURL error when there is one. Check `baseurl`, outbound firewall rules and `timeout`. |
| `HTTP 401` | Wrong or missing API key for that tenant, or mTLS not applied | Verify `apikey_<tenant>`. Remember mTLS is only applied when **both** `certificate_general` and `key_general` are set. |
| `HTTP 403` | The key is valid but not entitled to that tenant's data | Confirm with the API operator which tenants the key covers. |
| `HTTP 400` | The API rejected the query parameters | Usually a malformed year filter. See the raw JSON for the API's own message. |
| `HTTP 429` | Rate limited | Increase `requestdelayms`. The `Retry-After` value, when sent, is written to the cron log. |
| `HTTP 5xx` | The external system is down | Retry later; the scheduled task retries automatically via `faildelay`. |
| mTLS fails although the files exist | `certificationpath` and the filename are concatenated without a separator | Make the path end with a slash: `/etc/moodle/certs/`, not `/etc/moodle/certs`. |

Moodle's own outbound-request restrictions apply. If your site sets `curlsecurityblockedhosts` or requires a proxy,
the API host must be allowed there too.

---

## 2. Nothing is imported

Work down this list — the first two causes account for most cases.

| Check | How |
|-------|-----|
| **Does the tenant have an API key?** | A tenant with an empty key is silently skipped with all counters at zero. Look at the per-tenant table in the run details. |
| **Does the IOMAD company exist?** | Name **and** code must match the tenant exactly, umlauts and `ß` included. Without it, every Lehrgang is counted as *skipped*. See [Tenants](../tenants/README.md#3-linking-a-tenant-to-an-iomad-company). |
| **Do the template courses exist?** | No template → no course is created, and the Lehrgang is skipped. See [Course creation](../course_creation/README.md#3-template-courses). |
| **Is IOMAD installed at all?** | The plugin has an undeclared hard dependency on IOMAD's `company` class and will fatal without it. |
| **Did the run actually run?** | Ad-hoc tasks wait for the next cron pass. Check *Site administration → Server → Tasks → Ad-hoc tasks*. |

A large *skipped* count on a healthy site is normal — it means the courses already existed and only their
participants were synced.

---

## 3. Courses are wrong or missing

| Symptom | Explanation |
|---------|-------------|
| A course was created but is **empty** | Content is copied by an asynchronous ad-hoc task. Wait for the next cron pass, then check the cron log for `copy_course_content_task`. |
| The Lehrgang title changed in the external system, Moodle did not follow | By design. The plugin never updates an existing course — it only creates missing ones. |
| The course fullname is just `AGT`, not the Lehrgang name | By design. The fullname is the course type; `bezeichnung` is not used. |
| A course landed in the wrong category | The category comes from the IOMAD company, and Lehrgänge whose `ort` starts with `HLFS` are re-routed to the HLFS company. See [HLFS routing](../course_creation/README.md#5-hlfs-routing). |
| Importing 2019 created a `…-26_alt` course | By design. Past years are funnelled into one `_alt` course of the current year. |
| The year in the shortname looks off by one | The year comes from `endTag`, the Lehrgang **end** date. A course running December→January lands in the later year. |
| No F-III courses are ever created | Known quirk — see [below](#6-known-quirks). |

---

## 4. Participants are wrong or missing

| Symptom | Explanation |
|---------|-------------|
| Everything counted under *Assignment skipped* | The course has no manual enrolment instance, or the `manual` plugin is disabled site-wide. |
| Large *No action* count | The statuses in the payload match none of the mapping rules. `ANGEMELDET` and `BESTANDEN` are genuinely no-ops — only *Einberufung* enrols and only `TEILGENOMMEN` completes. See [Participants](../participants/README.md#3-status-to-action-mapping). |
| Users appear with `@invalid.local` addresses | The payload had neither a business nor a private email. |
| Duplicate-looking accounts | Matching is by mapping row, then `idnumber`, then `username`. A person whose external id changed gets a second account. |
| An existing user's **username changed** after an import | Intended repair behaviour: when a matched user's username differs from the participant's `initialId`, username and password are rewritten. |
| Unenrolment deleted someone's grades | Expected — unenrolling removes course data, as it does in the UI. Statuses like `STORNIERT` unenrol. |
| No groups in an `_alt` course | By design — group assignment is disabled there. |
| No groups anywhere | The payload has no `startTag`, so no group name can be derived. |
| Course marked complete without any activity being complete | `TEILGENOMMEN` writes a course-completion record directly, bypassing completion criteria. |

---

## 5. Runs never start or never finish

| Symptom | Cause | Fix |
|---------|-------|-----|
| *"Another sync is already running"* on the form | The nightly sync holds the lock | Wait for it to finish, or run it manually to completion first. |
| A run is recorded as *Skipped* | The lock was taken between queueing and execution | Simply start the import again. |
| A run sits at *Running* forever | The cron process died mid-run | The lock is released when the DB session ends. Check the cron log for a fatal error, then re-run. |
| The nightly sync stops after one tenant | The scheduled task aborts the whole run on any API error | Fix the failing tenant's key. A manual import isolates tenants and will get through the rest. See [Scheduled tasks](../scheduled_tasks/README.md#4-error-handling). |
| Runs take hours | Expected at scale: one participants call plus `requestdelayms` per Lehrgang | Lower `requestdelayms` if the API tolerates it, or import year by year. |

---

## 6. Known quirks

These are quirks in the plugin itself, not in your configuration.

**F-III is always mapped to `F-III-elearn`.**
The course-type mapping rewrites `F-III` before the code that inspects the `bezeichnung` for *e-learning* can run,
so that branch is unreachable and classroom and e-learning F-III Lehrgänge are treated identically. Unless a
template course with shortname `F-III-elearn` exists, all F-III Lehrgänge are skipped. The plugin's own test asserts
this behaviour.

**Four settings do nothing.**
*Sync interval: Lehrgänge list*, *Sync interval: Teilnehmer*, *Target course ID* and the unregistered *API token*
are not read by any code path that runs during a sync. Sync frequency is the scheduled task's schedule, and
templates are resolved by shortname. See
[Configuration](../configuration/README.md#6-settings-that-currently-have-no-effect).

**The `requestdelayms` help text says 500 ms; the real default is 1000 ms.**
Only the help text is stale.

**Certificate settings sit under the "Tenant settings" heading but are global.**
One certificate and key are used for every tenant. Only the API key is per tenant.

**The `_alt` year comparison is two-digit.**
It compares two-digit years numerically and will misbehave in 2100.

**The IOMAD dependency is undeclared.**
`version.php` has no `$plugin->dependencies`, so Moodle allows installation on a site without IOMAD; the failure
appears only at sync time.

---

## 7. Where to look

| Source | What it gives you |
|--------|-------------------|
| [Import history](../manual_import/README.md#4-import-history) | Per-tenant and per-course counters for every run |
| *Raw data (JSON)* on a run detail page | The unprocessed summary, including the API's own error text |
| Cron log | `mtrace()` output prefixed `local_lehrgaengeapi:` and `copy_course_content_task:` |
| *Site administration → Server → Tasks → Ad-hoc tasks* | Whether a queued import or content copy is still pending |
| `local_lehrgaengeapi_coursemap` / `_usermap` | Which external id is linked to which Moodle course or user |
| Moodle debugging (`DEVELOPER`, display on) | Full stack traces from a manually executed task |

For a manual, reproducible test run:

```bash
php admin/cli/scheduled_task.php --execute='\local_lehrgaengeapi\task\sync_lehrgaenge_task'
```
