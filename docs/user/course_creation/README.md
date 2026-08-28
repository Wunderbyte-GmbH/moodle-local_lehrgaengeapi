[Back to parent section](../README.md)

# Course creation — Administrator guide

How a *Lehrgang* from the external system becomes a Moodle course: the shortname convention, the template copy, and
the special cases for past years and HLFS venues.

---

## Quick setup path

1. Work out the shortname the plugin will generate for your Lehrgang (section 2).
2. Make sure a **template course** exists whose shortname is the course-type identifier (section 3).
3. Run a [manual import](../manual_import/README.md) for the relevant year.
4. Check the created course, then wait for the ad-hoc task to copy the template content into it.

---

## Table of Contents

1. [The matching order](#1-the-matching-order)
2. [The shortname convention](#2-the-shortname-convention)
3. [Template courses](#3-template-courses)
4. [Past years and `_alt` courses](#4-past-years-and-_alt-courses)
5. [HLFS routing](#5-hlfs-routing)
6. [What the created course looks like](#6-what-the-created-course-looks-like)
7. [Copying the template content](#7-copying-the-template-content)
8. [Course type mapping](#8-course-type-mapping)

---

## 1. The matching order

For every Lehrgang in the API response, the plugin does this, in order:

1. Pick the target **company** — the tenant's company, or the HLFS company if the venue starts with `HLFS`
   (see [section 5](#5-hlfs-routing)).
2. Build the **shortname** from company code, course type and year (see [section 2](#2-the-shortname-convention)).
3. Look for an existing course with that shortname.
4. If not found and the Lehrgang's year is in the past, look for the `<currentyear>_alt` variant
   (see [section 4](#4-past-years-and-_alt-courses)).
5. **If a course was found**: record the mapping, sync its participants, count the Lehrgang as *skipped*.
6. **If not**: create the course from a template, register it with the company, sync its participants,
   count it as *created*.

Two things follow from this that surprise people:

- *skipped* does **not** mean "nothing happened". A skipped Lehrgang is one whose course already existed;
  its participants are still fully synced. On a healthy site running nightly, almost everything is *skipped*.
- The plugin **never updates an existing course**. Title, dates, category and visibility are set once, at creation.

---

## 2. The shortname convention

```
<company code>-<course type>-<two-digit year>
```

| Segment | Source |
|---------|--------|
| Company code | IOMAD `company.code` of the target company, e.g. `FD` |
| Course type | `kurzbezeichnung` from the API, after the [mapping table](#8-course-type-mapping) |
| Two-digit year | `substr($item['endTag'], 2, 2)` — characters 3–4 of the Lehrgang end date |

The year therefore comes from the **end date** (`endTag`), which the API delivers as `YYYY-MM-DD`. A Lehrgang ending
`2025-03-14` yields `25`.

Examples:

| Company | `kurzbezeichnung` | `endTag` | Shortname |
|---------|-------------------|----------|-----------|
| `FD` | `AGT` | `2025-03-14` | `FD-AGT-25` |
| `FD` | `F-Atr` | `2025-07-31` | `FD-F-ATR-25` |
| `HLFS` | `F-II` | `2026-05-02` | `HLFS-F-II-26` |

The course **fullname** is set to the course type alone (`AGT`, `F-II`), not to the Lehrgang's `bezeichnung`.

---

## 3. Template courses

A course is only ever created **from a template**. If no template can be found, the Lehrgang is skipped and nothing
is created — this is by far the most common reason for an import that "does nothing".

Templates are resolved by shortname, in this order:

1. **Last year's course for the same tenant and type** — `<code>-<type>-<year-1>`, e.g. `FD-AGT-24` when creating
   `FD-AGT-25`. This carries a tenant's own customisations forward from year to year.
2. **The global master course** — a course whose shortname is exactly the course type, e.g. `AGT`. It must not be
   the site front page.

If neither exists, `course_creator::create()` returns `null` and the Lehrgang is counted as skipped.

So before the first import of a new course type, create one course per type with the bare type as its shortname
(`AGT`, `F-I`, `F-II`, `F/K-SPF`, …) and put the desired activities and sections in it.

> The **Target course ID** setting on the settings page looks like it configures this, but it is not read by any
> code path. Template resolution is purely by shortname. See
> [Configuration](../configuration/README.md#5-settings-that-currently-have-no-effect).

---

## 4. Past years and `_alt` courses

Importing a historical year would otherwise create a course per year per type, which is not wanted: old Lehrgänge
exist only so their participants get a completion record. Instead, everything from a past year is funnelled into a
single **`_alt` course of the current year**:

```
<company code>-<course type>-<current two-digit year>_alt
```

For example, importing 2019 in 2026 targets `FD-AGT-26_alt`. All 2019, 2020, … Lehrgänge of type `AGT` for tenant
`FD` share that one course.

Two effects:

| Aspect | Behaviour in an `_alt` course |
|--------|-------------------------------|
| Fullname | The course type plus the localised word for *Completed* (`AGT Abgeschlossen` / `AGT Completed`) |
| Group assignment | **Disabled.** Participants are enrolled but not put into date groups, because the groups of many different years would collide |

Participants are still enrolled and marked complete exactly as in a normal course — see
[Participants](../participants/README.md).

A Lehrgang counts as "past" when its two-digit year is numerically lower than the current two-digit year. Note that
this comparison is on two digits, so it will misbehave in the year 2100; it is fine for the plugin's lifetime.

---

## 5. HLFS routing

Lehrgänge held at the state fire service school are centralised, regardless of which district registered them:

```php
if (!empty($item['ort']) && strpos($item['ort'], 'HLFS') === 0 && $hlfscompany) {
    $currentcompany = $hlfscompany;
}
```

The check is a **prefix** match on the `ort` field, so `HLFS Kassel` and `HLFS-Aussenstelle` both route to HLFS,
while `Feuerwehrschule HLFS` does not.

The consequence is that the course is created in the HLFS company's category with an `HLFS-…` shortname, and the
HLFS template chain (`HLFS-F-II-24`, then `F-II`) is used. If no company with name
`Hessische Landesfeuerwehrschule` and code `HLFS` exists, the routing is skipped and the Lehrgang stays with its own
tenant.

---

## 6. What the created course looks like

Fields taken from the **template** course:

`summary`, `summaryformat`, `format`, `numsections`, `startdate`, `enddate`, `lang`, `newsitems`, `showgrades`,
`groupmode`, `groupmodeforce`, `enablecompletion`, `completionnotify`, `maxbytes`, `showreports`, `visibleold`

Fields set by the plugin, overriding the template:

| Field | Value |
|-------|-------|
| `category` | The target company's course category |
| `fullname` | The course type (plus *Completed* for `_alt` courses) |
| `shortname` | The generated shortname |
| `idnumber` | The external `Lehrgang.id` |
| `visible` | `1` |
| `startdate` | 1 January of the Lehrgang year, 00:00 |
| `enddate` | 31 December of the Lehrgang year, 23:59:59 |

Note that `idnumber` holds the external id of **whichever Lehrgang created the course first**. For an `_alt` course
that absorbs many Lehrgänge, the other external ids are recorded only in `local_lehrgaengeapi_coursemap`.

As a final guard, creation is aborted if a course with the target shortname appeared in the meantime.

---

## 7. Copying the template content

Activities and sections are **not** copied synchronously. After the course record is created, the plugin queues an
ad-hoc task:

```php
\local_lehrgaengeapi\task\copy_course_content_task
```

which runs on the next cron pass and performs a backup/restore of the template into the new course
(`MODE_IMPORT`, `TARGET_EXISTING_ADDING`, no user data). Before restoring, it deletes the auto-created
*Announcements* forum from the new course so the template's own forum does not end up duplicated.

This means a freshly created course looks **empty for a few minutes** — participants are enrolled immediately, the
content arrives with the next cron run. On a site with a busy ad-hoc queue it can take longer.

If the restore pre-check fails, the task logs the pre-check results as JSON to the cron output and leaves the course
empty. Search the cron log for `copy_course_content_task`.

---

## 8. Course type mapping

`kurzbezeichnung` values from the API are normalised before being used in the shortname:

| API value | Used in shortname |
|-----------|-------------------|
| `F-I` | `F-I` |
| `F-II` | `F-II` |
| `F-Atr` | `F-ATR` |
| `F-Ma` | `F-MA` |
| `F/K-Sprechfunk` | `F/K-SPF` |
| `F-TH-VU` | `F-TH-VU` |
| `F/B-mobBSA-Hessen` | `F-BSA` |
| `F/B-BSA-Sem.` | `F/B-BSA-Sem.` |
| `F-III` | `F-III-elearn` |

Any value not in this table is used unchanged, so `AGT` stays `AGT`.

> **Known quirk — F-III.** The mapping table sends *every* `F-III` Lehrgang to `F-III-elearn`. The code below the
> table that inspects the `bezeichnung` for the word *e-learning*, intended to separate classroom from e-learning
> courses, can never be reached, because `F-III` has already been rewritten by then. In practice this means: unless
> a template course with the shortname `F-III-elearn` exists, all F-III Lehrgänge are skipped. The plugin's own test
> for this case (`tests/services/lehrgaenge_sync_service_f_iii_test.php`) asserts exactly that skipping behaviour.
> If you want classroom F-III courses to be created, create an `F-III-elearn` template, or fix the mapping.
