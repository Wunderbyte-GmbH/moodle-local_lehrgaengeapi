[Back to parent section](../README.md)

# Participants — Administrator guide

How a *Teilnehmer* from the external system becomes a Moodle user, and what their status does to their enrolment,
their group and their course completion.

---

## Quick setup path

1. Make sure the target course has a **manual enrolment** instance — without it nothing is enrolled.
2. Run an import and open the run's detail page in the [import history](../manual_import/README.md#4-import-history).
3. Expand *Participant details per course* to see per-course counters.
4. Match unexpected counters against the [status table](#4-status-to-action-mapping) below.

---

## Table of Contents

1. [The participant sync in order](#1-the-participant-sync-in-order)
2. [User matching and creation](#2-user-matching-and-creation)
3. [Ausbildungen in the user profile](#3-ausbildungen-in-the-user-profile)
4. [Status-to-action mapping](#4-status-to-action-mapping)
5. [Enrolment and unenrolment](#5-enrolment-and-unenrolment)
6. [Groups](#6-groups)
7. [Course completion](#7-course-completion)
8. [Reading the counters](#8-reading-the-counters)

---

## 1. The participant sync in order

For each Lehrgang whose course exists (newly created or pre-existing):

1. Sleep for `requestdelayms` milliseconds.
2. `GET /lehrgaenge/{id}/teilnehmer`.
3. If the list is empty, return zero counters — nothing else happens.
4. Ensure a Moodle user exists for every participant and save their Ausbildungen (`users_creator`).
5. Enrol / unenrol / complete each participant according to their status (`participant_course_assigner`).

A failure in step 2 for one Lehrgang does **not** abort the run. The error is recorded per Lehrgang under
*Lehrgaenge with a failed participant sync* on the run detail page, and the sync moves on.

---

## 2. User matching and creation

The participant's `id` from the API is the key. The plugin looks for an existing Moodle user in this order:

1. The **mapping table** `local_lehrgaengeapi_usermap` — a previously linked `user.id`.
2. A user whose **`idnumber`** equals the participant id.
3. A user whose **`username`** equals the participant id, on the local mnet host.

Only if all three miss is a new user created:

| Moodle field | Value |
|--------------|-------|
| `username` | The participant's `initialId`, falling back to their email address |
| `password` | Derived from the username plus a fixed suffix, hashed |
| `idnumber` | The participant's `id` |
| `firstname` | `vorname`, or `Teilnehmer` when empty |
| `lastname` | `nachname`, or the participant id when empty |
| `email` | `emails.emailBusiness`, else `emails.emailPrivat`, else a placeholder `<id>@invalid.local` |
| `city` | `ort` |
| `country` | `DE` |
| `auth` | `manual` |
| `confirmed` | `1` |

Notes worth knowing:

- **Business email wins.** The private address is only used when no business address is present.
- **Email collisions** are resolved by appending `+1`, `+2`, … to the local part, unless the site allows accounts
  with the same email address. After 50 attempts the plugin falls back to a placeholder address.
- **Participants without a usable email** get `<slug>@invalid.local`. These addresses do not bounce because nothing
  is delivered to them, but they will show up in any "users with invalid email" report.
- **Existing users are repaired, not replaced.** If a matched user has an empty `idnumber` it is filled in, and if
  their `username` differs from the participant's `initialId` the username *and* password are rewritten to match.
  Be aware of that last one: re-running an import can change the username of an existing account.
- New users are created with `user_create_user($newuser, false, false)` — no password-update event and no user-created
  event are triggered, so welcome emails are not sent.
- A concurrent insert of the same username is caught and resolved to the existing user rather than failing the run.

---

## 3. Ausbildungen in the user profile

The participant payload contains an `ausbildungen` list: the trainings the person has taken, each with
`bezeichnung`, `kurzBezeichnung`, `von`, `bis` and `status`. The plugin stores this list for every participant in the
custom profile field **Ausbildungen** (shortname `ausbildungen`, category *Lehrgaenge*).

| Aspect | Behaviour |
|--------|-----------|
| **Format** | JSON list in API order, reduced to the five keys above. Umlauts and slashes stay readable. |
| **When** | On **every** sync, for new users and for existing users alike (matched by mapping, `idnumber` or `username`). |
| **Unchanged list** | Nothing is written. A user who appears in many Lehrgaenge costs a lookup, not a write. |
| **Changed list** | The stored value is replaced, so new trainings arrive with the next sync. |
| **Empty or missing list** | Still saved, as an empty value. A previously stored list is **cleared**. |
| **Field missing** | Nothing is saved and the import carries on. The cron log says `profile field "ausbildungen" not found`. |

The field is created when the plugin is installed or upgraded. It is **locked** and **not visible** to users; only
the import writes to it. If an admin deletes the field, create a *Textarea* profile field with the shortname
`ausbildungen` again and the next sync fills it.

> An empty `ausbildungen` list from the API overwrites existing data. If the API ever returns incomplete lists for
> a participant, the profile field follows it.

---

## 4. Status-to-action mapping

The API sends statuses like `S034_EINBERUFUNG_VERSCHICKT_KREIS` or `S052_NICHT_BESTANDEN_KREIS`. The plugin strips the
leading code (`S034_`) and matches the rest on substrings:

| Status contains | Action | Effect |
|-----------------|--------|--------|
| `ABGELEHNT`, `NICHT_`, `ZURUECKGESTELLT`, `STORNIERT`, `ABGEBROCHEN`, `TERMIN_ABGESAGT`, `FEHLT_UNENTSCHULDIGT` or `UEBERREGIONALER_LEHRGANG` | unassign | Unenrol from the course |
| `TEILGENOMMEN` or `BESTANDEN` | assign + complete | Enrol **and** mark the course complete |
| `EINBERUFUNG` (*Einberufung verschickt*) or `ANGEMELDET_EIGENE` | assign | Enrol into the course (and add to a group) |
| anything else | no-op | Nothing at all |

Matching is case-insensitive on the trimmed, uppercased value, and the checks are in the order shown, so
`NICHT_BESTANDEN` unenrols instead of completing.

> **Not every "angemeldet"-like status enrols.** `S018_ANGEMELDET_KREIS`, `S033_EINBERUFEN_KREIS` and
> `FEHLT_ENTSCHULDIGT` statuses are **no-ops**. Only *Einberufung verschickt* and *Angemeldet eigene Gemeinde* enrol.

To change these rules, see [Status handlers](../../developer-guides/STATUS_HANDLERS_API.md).

---

## 5. Enrolment and unenrolment

Enrolment uses the course's **manual enrolment instance** and the role configured on it (`enrol.roleid`, normally
*Student*).

The whole course is skipped — every participant counted under *Assignment skipped* — when:

- the `manual` enrolment plugin is disabled site-wide, or
- the course has no `manual` enrolment instance.

A participant is individually skipped when they have no id, no mapping row, no linked `user.id`, or their Moodle
user has been deleted.

Unenrolment removes the user's manual enrolment only. A user enrolled by another method (cohort sync, self
enrolment) keeps that enrolment; the counter still reports *Already unenrolled* because no manual enrolment row was
found.

Unenrolling deletes the user's grades and activity data in that course, exactly as an unenrolment through the UI
does. Statuses like `STORNIERT` therefore discard work — that is intended, but worth knowing before importing a
historical year that contains cancellations.

---

## 6. Groups

Participants who are enrolled (newly or already) are also placed into a course group, unless group assignment is
disabled for that course.

The group name is derived from the Lehrgang's **start date** (`startTag`):

```
G<nn> (<startTag>)      e.g.  G01 (2025-03-10)
```

The logic is:

1. If the course already has a group whose name ends with ` (<startTag>)`, reuse it.
2. Otherwise, scan the existing `G<nn> (…)` groups, take the highest counter, and create the next one.

So each run of a course type within a year gets its own numbered group, and re-running an import puts people back
into the group that already exists rather than creating duplicates.

**Group assignment is skipped entirely for `_alt` courses** (see
[Course creation](../course_creation/README.md#4-past-years-and-_alt-courses)), because one `_alt` course absorbs
Lehrgänge from many different years.

A course with no `startTag` in its payload gets no group assignment at all.

---

## 7. Course completion

The `TEILGENOMMEN` and `BESTANDEN` statuses mark the **course** complete for that user via
`completion_completion::mark_complete()`.

- Users already marked complete are left alone and still counted under *Completed*.
- This writes a course-completion record directly. It does not evaluate the course's completion criteria, and it
  does not complete individual activities.
- Completion is applied in addition to enrolment: a `TEILGENOMMEN` or `BESTANDEN` participant is enrolled first, then completed.

This is the mechanism behind the `_alt` courses: a historical import enrols people into the current year's `_alt`
course purely so their completion record exists.

---

## 8. Reading the counters

Each course produces two counter sets on the run detail page.

**Users**

| Counter | Meaning |
|---------|---------|
| New users | A Moodle account was created |
| Existing users | An account was matched by mapping, `idnumber` or `username` |
| Users skipped | The payload entry had no usable id |
| Trainings updated | The user's *Ausbildungen* profile field was created or changed (see [section 3](#3-ausbildungen-in-the-user-profile)) |

**Assignments**

| Counter | Meaning |
|---------|---------|
| Enrolled | Manual enrolment created now |
| Already enrolled | The user was enrolled before this run |
| Unenrolled | Manual enrolment removed now |
| Already unenrolled | Nothing to remove |
| No action | The status matched no rule |
| Completed | Course completion written or already present |
| Assignment skipped | No manual enrolment instance, or the participant could not be resolved to a user |

A healthy nightly run on a stable dataset shows almost everything under *Existing users* and *Already enrolled*.
A large *No action* count usually means the API is sending statuses the mapping does not cover — check the raw JSON
at the bottom of the run detail page.
