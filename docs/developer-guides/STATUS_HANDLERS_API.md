[Back to documentation index](../README.md)

# Status handlers — Developer guide

How a raw participant status from the external API becomes an enrol / unenrol / complete decision, and how to change
or extend the mapping.

---

## Quick orientation

1. `participant_status_handler_resolver::resolve()` turns a status string into a handler.
2. The handler returns an immutable `participant_status_action`.
3. `participant_course_assigner` executes the action.

To change *which* statuses do what, edit the resolver. To add a *new kind of outcome*, add a factory method to
`participant_status_action` and a branch in the assigner.

---

## Table of Contents

1. [The pipeline](#1-the-pipeline)
2. [Status parsing](#2-status-parsing)
3. [The mapping rules](#3-the-mapping-rules)
4. [`participant_status_action`](#4-participant_status_action)
5. [The handlers](#5-the-handlers)
6. [Changing which statuses map where](#6-changing-which-statuses-map-where)
7. [Adding a new outcome](#7-adding-a-new-outcome)
8. [Testing](#8-testing)

---

## 1. The pipeline

```
"S033_EINBERUFEN_KREIS"
        │
        ▼  participant_status_handler_resolver::resolve()
   strip first and last underscore segment  ->  "EINBERUFEN"
        │
        ▼  substring match
   angemeldet_participant_status_handler
        │
        ▼  ::process()
   participant_status_action::assign_only()      // shouldassign = 1, shouldcomplete = 0
        │
        ▼  participant_course_assigner::subscribe_participant()
   enrol_user() + ensure_participant_group_membership()
```

The resolver is constructed with its four handlers injected, in `participant_course_assigner::__construct()`:

```php
new participant_status_handler_resolver(
    new angemeldet_participant_status_handler(),
    new bestanden_participant_status_handler(),
    new noop_participant_status_handler(),
    new abgemeldet_participant_status_handler()
);
```

---

## 2. Status parsing

```php
private function get_main_status_identifer(string $rawstatus): string {
    $parts = explode('_', $rawstatus);
    array_shift($parts);   // drop the leading code, e.g. "S033"
    array_pop($parts);     // drop the trailing scope, e.g. "KREIS"
    return implode('_', $parts);
}
```

The input is `strtoupper(trim($rawstatus))` first, so matching is case-insensitive.

| Raw status | Middle segment |
|------------|----------------|
| `S033_EINBERUFEN_KREIS` | `EINBERUFEN` |
| `S018_ANGEMELDET_KREIS` | `ANGEMELDET` |
| `S084_BESTANDEN_LAND` | `BESTANDEN` |
| `S090_NICHT_TEILGENOMMEN_LAND` | `NICHT_TEILGENOMMEN` |
| `TEILGENOMMEN` | *(empty — both segments are dropped)* |
| `''` | *(empty)* |

The last two rows matter: a status with fewer than three segments loses everything and falls through to the no-op
handler. Do not feed the resolver a bare keyword and expect it to match.

---

## 3. The mapping rules

Checked in this order, with `strpos() !== false` substring matching:

| # | Middle segment contains | Handler | Action |
|---|-------------------------|---------|--------|
| 1 | `EINBERUFUNG` or `EINBERUFEN` | `angemeldet_…` | assign |
| 2 | `TEILGENOMMEN` | `bestanden_…` | assign + complete |
| 3 | `FEHLT`, `STORNIERT`, `ABGEBROCHEN` or `ZURUECKGESTELLT` | `abgemeldet_…` | unassign |
| — | anything else | `noop_…` | nothing |

Consequences that regularly surprise people:

- **`ANGEMELDET` does not enrol.** Rule 1 matches only the *Einberufung* family, despite the handler's name.
- **`BESTANDEN` does not complete.** Rule 2 keys on `TEILGENOMMEN`, despite the handler's name.
- Because rule 2 is a substring match, **`NICHT_TEILGENOMMEN` also assigns and completes** — the negation is not
  detected. If that is wrong for your data, rule 2 needs a negative guard.
- Rule ordering matters: a status containing both `EINBERUFEN` and `STORNIERT` assigns.

The handler class names describe the *outcome family they were named after*, not the statuses that reach them.
Judge behaviour by the resolver, not by the class name.

---

## 4. `participant_status_action`

An immutable decision object holding two integers:

```php
new participant_status_action(int $shouldassign, int $shouldcomplete);
```

| Factory | `shouldassign` | `shouldcomplete` | Meaning |
|---------|----------------|------------------|---------|
| `noop()` | 0 | 0 | Do nothing |
| `assign_only()` | 1 | 0 | Enrol |
| `unassign_only()` | -1 | 0 | Unenrol |
| `assign_and_complete()` | 1 | 1 | Enrol and mark complete |

Predicates:

```php
$action->should_assign();          // shouldassign > 0
$action->should_unassign();        // shouldassign < 0
$action->should_complete();        // shouldcomplete > 0
$action->should_not_do_anything(); // both === 0
```

`should_not_do_anything()` is checked **first** in the assigner and returns early, so an action that is
`noop` on assignment but wants completion is not expressible — encode it as `assign_and_complete()` or add a new
predicate.

---

## 5. The handlers

Each implements `participant_status_handler_interface` and does nothing but return an action:

```php
interface participant_status_handler_interface {
    public function process(): participant_status_action;
}
```

| Class | Returns |
|-------|---------|
| `angemeldet_participant_status_handler` | `assign_only()` |
| `bestanden_participant_status_handler` | `assign_and_complete()` |
| `abgemeldet_participant_status_handler` | `unassign_only()` |
| `noop_participant_status_handler` | `noop()` |

They are stateless and take no constructor arguments. `process()` receives no context — the participant payload is
not passed in — so a handler cannot make a decision that depends on the person, only on the status class the
resolver already determined.

---

## 6. Changing which statuses map where

This is the common case, and it is a one-file change in
`participant_status_handler_resolver::resolve()`:

```php
// Treat ANGEMELDET as an enrolment as well.
if (
    strpos($status, 'EINBERUFUNG') !== false ||
    strpos($status, 'EINBERUFEN') !== false ||
    strpos($status, 'ANGEMELDET') !== false
) {
    return $this->angemeldethandler;
}

// Do not let NICHT_TEILGENOMMEN complete the course.
if (strpos($status, 'TEILGENOMMEN') !== false && strpos($status, 'NICHT') === false) {
    return $this->bestandenhandler;
}
```

Before changing anything, check what the API actually sends: run an import and read *Raw data (JSON)* on the run
detail page, or query the statuses directly. A high *No action* count in the
[import history](../user/manual_import/README.md#4-import-history) is the signal that statuses are going unmatched.

Changing a status from no-op to unassign is **destructive** — unenrolling deletes the user's course data. Test on a
copy first.

---

## 7. Adding a new outcome

Say you want *suspend* instead of *unenrol* for cancellations.

**1. Extend the action.** Add a third dimension and a factory:

```php
public static function assign_suspended(): self {
    return new self(1, 0, 1);   // plus a $shouldsuspend property and a should_suspend() predicate
}
```

**2. Add a handler:**

```php
final class storniert_participant_status_handler implements participant_status_handler_interface {
    public function process(): participant_status_action {
        return participant_status_action::assign_suspended();
    }
}
```

**3. Inject it** into the resolver's constructor and return it from the matching rule. The resolver takes its
handlers as constructor arguments, so also update the construction site in
`participant_course_assigner::__construct()`.

**4. Execute it** in `participant_course_assigner::subscribe_participant()`:

```php
if ($action->should_suspend()) {
    $this->plugin->update_user_enrol($this->manualinstance, $userid, ENROL_USER_SUSPENDED);
    $report['suspended']++;
}
```

**5. Add the counter** to the `$report` array initialised in `assign()`, to the report structure documented in
[Sync services](SYNC_SERVICE_API.md#2-the-summary-structure), and to the fixed column list in
`admin/import_runs.php` — the history page renders a hard-coded set of columns and will not pick up a new key on its
own.

The resolver's constructor signature is positional, so adding a handler is a breaking change for anyone
constructing it directly. There are only two such places: the assigner and the resolver's own test.

---

## 8. Testing

Two test files cover this area directly:

| File | Covers |
|------|--------|
| `tests/lehrgang_status/participant_status_handler_resolver_test.php` | Status string → handler |
| `tests/lehrgang_status/participant_status_action_test.php` | The action factories and predicates |

Both are pure unit tests with no database, so they are fast. Add a case for every status you add a rule for,
including the near-miss (`NICHT_TEILGENOMMEN` against `TEILGENOMMEN`).

End-to-end behaviour — that an `EINBERUFEN` participant really ends up enrolled — is covered by the service tests in
`tests/services/`, which drive the whole sync with a fake endpoint. See [Testing](TESTING.md).
