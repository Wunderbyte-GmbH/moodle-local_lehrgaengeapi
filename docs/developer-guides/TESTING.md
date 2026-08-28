[Back to documentation index](../README.md)

# Testing — Developer guide

The PHPUnit suite: what it covers, how the fakes work, and how to add a test that exercises the sync end to end.

---

## Quick orientation

1. Initialise PHPUnit once, then run the plugin's tests by path.
2. Service-level tests drive the whole sync with a **fake endpoint** — prefer them over unit-testing private helpers.
3. One test talks to a real external system and skips itself when IOMAD is missing. Do not run it in CI.

---

## Table of Contents

1. [Running the tests](#1-running-the-tests)
2. [What the suite covers](#2-what-the-suite-covers)
3. [The fake endpoint pattern](#3-the-fake-endpoint-pattern)
4. [The fake curl helper](#4-the-fake-curl-helper)
5. [Fixtures](#5-fixtures)
6. [Test prerequisites](#6-test-prerequisites)
7. [Writing a new test](#7-writing-a-new-test)
8. [Code style and CI](#8-code-style-and-ci)

---

## 1. Running the tests

Initialise the PHPUnit environment (once, and after any schema change):

```bash
php admin/tool/phpunit/cli/init.php
```

Run everything in the plugin:

```bash
vendor/bin/phpunit local/lehrgaengeapi/tests
```

Run one file or one method:

```bash
vendor/bin/phpunit local/lehrgaengeapi/tests/services/lehrgaenge_sync_service_test.php
vendor/bin/phpunit --filter test_sync_creates_course local/lehrgaengeapi/tests/services/lehrgaenge_sync_service_test.php
```

After adding or removing the plugin, rebuild the config:

```bash
php admin/tool/phpunit/cli/util.php --buildconfig
```

> In this Moodle checkout, tests are normally executed through the **moodle-docker** stack rather than a local
> `vendor/bin/phpunit`. Use whichever runner your environment provides — the paths and filters above are the same
> either way.

---

## 2. What the suite covers

| Directory | Files | Covers |
|-----------|-------|--------|
| `tests/auth/` | `token_authenticator_test.php` | Header construction, empty-token behaviour |
| `tests/client/` | `api_client_test.php` | URL building, query separator, headers, status → exception mapping |
| `tests/response/` | `api_response_test.php` | JSON decoding, case-insensitive header lookup |
| `tests/endpoints/` | `lehrgaenge_endpoint_test.php` | The four endpoint methods against a fake curl |
| `tests/endpoints/` | `lehrgaenge_endpoint_testsystem_test.php` | **Live** call against the external test system |
| `tests/course/` | `course_creator_date_test.php` | `startdate` / `enddate` derivation |
| `tests/users/` | `users_creator_test.php` | User matching, creation, email uniqueness |
| `tests/lehrgang_status/` | `participant_status_handler_resolver_test.php`, `participant_status_action_test.php` | Status parsing and the action object |
| `tests/services/` | `lehrgaenge_sync_service_test.php`, `…_alt_test.php`, `…_f_iii_test.php`, `participant_course_assigner_test.php` | The sync end to end, `_alt` courses, the F-III mapping, enrolment |
| `tests/synchronization/` | `lehrgaenge_synchronization_test.php`, `…_template_test.php` | Task-level runs including template resolution |
| `tests/repositories/` | `coursemap_…`, `usermap_…`, `import_run_repository_test.php` | Repository CRUD and idempotency |
| `tests/task/` | `sync_lehrgaenge_task_test.php`, `manual_import_lehrgaenge_task_test.php`, `copy_course_content_task_test.php` | Locking, run logging, notification, backup/restore |

The bias of the suite is **service level**: most behaviour is asserted by running `sync()` against a fake endpoint
and then querying the database for the resulting courses, users and enrolments.

---

## 3. The fake endpoint pattern

The sync services depend on `lehrgaenge_endpoint_interface`, never on the concrete endpoint, so a test can substitute
an anonymous class and skip HTTP entirely:

```php
private function fake_endpoint(array $items, array $participantsbyid = []): lehrgaenge_endpoint_interface {
    return new class ($items, $participantsbyid) implements lehrgaenge_endpoint_interface {
        public function __construct(private array $items, private array $participantsbyid) {}

        public function list($tenant, $searchcriteria = null): array {
            return $this->items;
        }
        public function participants($tenant, string $id): array {
            return $this->participantsbyid[$id] ?? [];
        }
        public function get_by_id($tenant, string $id): array { return []; }
        public function participant_extern($tenant, string $id, string $teilnehmerid): array { return []; }
    };
}
```

Then wire the real services around it:

```php
$service = new lehrgaenge_sync_service(
    $this->fake_endpoint($items, $participants),
    new coursemap_repository(),
    new course_creator(),
    new participants_sync_service(
        $this->fake_endpoint($items, $participants),
        new users_creator(),
        new participant_course_assigner()
    ),
    new tenant_creator()
);

$summary = $service->sync($tenant);
```

Because the interface is implemented in full each time, adding a method to
`lehrgaenge_endpoint_interface` breaks every fake at parse time — update them in the same commit.

---

## 4. The fake curl helper

`tests/test_helpers/curl_helper.php` builds a `\curl` subclass with a canned response, so `api_client` can be tested
without a network:

```php
require_once(__DIR__ . '/../test_helpers/curl_helper.php');

$curl = (new curl_helper())->make_fake_curl($body, $httpcode, $rawheaders);
$client = new api_client('https://example.org/api', new token_authenticator('t'), 30, $curl);
```

The double records what the client did, which is what makes the header and URL assertions possible:

| Property | Holds |
|----------|-------|
| `$curl->setheaders` | Every header `api_client` set, in order |
| `$curl->lasturl` | The full URL of the last request |

This is how the `arg_separator.output` regression is guarded — assert that `lasturl` contains `&` and not `&amp;`.

---

## 5. Fixtures

JSON fixtures live in `tests/dummy_data/`, named `<status>_<endpoint>.json`:

| File | Purpose |
|------|---------|
| `200_lehrgaenge.json` | Three Lehrgänge across two years |
| `200_lehrgaenge_id.json` | A single Lehrgang |
| `200_lehrgaenge_id_teilnehmer.json` | Participants with a spread of statuses |
| `200_lehrgaenge_id_teilnehmer_extern_id.json` | One external participant |
| `400_lehrgaenge.json`, `404_lehrgaenge_id.json`, `403_…`, `401_…` | Error bodies for the exception mapping tests |

> **Fixture caveat.** The participant fixtures carry `emailBusiness` / `emailPrivat` at the **top level**, while
> `users_creator` reads them from a nested `emails` object. The service tests therefore build their participant
> payloads inline, in the nested shape, rather than loading these files. If you drive `users_creator` from the
> fixtures, expect placeholder `@invalid.local` addresses. Verify the real shape against the live API before
> changing either side.

---

## 6. Test prerequisites

Most service tests need three things in place before `sync()` will do anything:

**1. An IOMAD company** matching the tenant on `name` and `code`, with a `category`:

```php
$category = $this->getDataGenerator()->create_category(['name' => 'Test company category']);
$DB->insert_record('company', [
    'name' => 'Landkreis Fulda', 'shortname' => 'FD', 'code' => 'FD',
    'city' => 'Fulda', 'country' => 'DE', 'category' => $category->id,
]);
```

**2. A template course** whose shortname is the course type, otherwise nothing is created:

```php
$this->getDataGenerator()->create_course([
    'category' => $category->id, 'shortname' => 'AGT', 'fullname' => 'AGT master',
    'format' => 'topics', 'numsections' => 5,
]);
```

**3. `$this->resetAfterTest()`** — every test writes courses, users and enrolments.

Tests that need IOMAD's `company` class guard for it and skip when it is absent:

```php
if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
    $this->markTestSkipped('IOMAD not available');
}
```

`lehrgaenge_endpoint_testsystem_test.php` goes further and makes **real HTTP calls** to the external test system
using the configured certificate and key. It is useful for verifying a deployment, useless in CI, and slow — it
raises the timeout to 120 seconds. Skip it unless you are deliberately testing connectivity.

---

## 7. Writing a new test

A checklist that matches the conventions already in the suite:

1. Put the file under the directory matching the layer you are testing, named `<subject>_test.php`.
2. Namespace it `local_lehrgaengeapi\<subdirectory>` and extend `\advanced_testcase`
   (or `\basic_testcase` for the pure status/action tests, which need no database).
3. Call `$this->resetAfterTest()` first in any test that touches the database.
4. Add a `@covers` annotation for the class or method under test.
5. Prefer driving `sync()` with a fake endpoint over calling private helpers through reflection.
6. Assert against the **database**, not only the returned summary — the counters and the actual state have drifted
   apart before:

```php
$course = $DB->get_record('course', ['shortname' => 'FD-AGT-25'], '*', MUST_EXIST);
$userid = (int)$DB->get_field('user', 'id', ['idnumber' => 'p-agt-300'], MUST_EXIST);
$this->assertTrue(is_enrolled(\context_course::instance((int)$course->id), $userid));
```

7. Set plugin config explicitly rather than relying on defaults:

```php
set_config('apikey_fd', 'testkey', 'local_lehrgaengeapi');
set_config('requestdelayms', 0, 'local_lehrgaengeapi');   // do not sleep in tests
```

Setting `requestdelayms` to `0` matters: at the default of 1000 ms, a test with 20 Lehrgänge sleeps for 20 seconds.

---

## 8. Code style and CI

Run the Moodle coding standard over the plugin:

```bash
phpcs --standard=Moodle local/lehrgaengeapi
```

CI runs through `.github/workflows/moodle-plugin-ci.yml`, which calls Wunderbyte's shared
`catalyst-moodle-workflows` pipeline on every push and pull request. It is configured strictly:

- `codechecker_max_warnings: 0` — warnings fail the build;
- `enable_phpmd: true` — mess detection is on;
- PHPUnit, PHPDoc checker, phplint, phpcpd and Mustache checks all enabled;
- releases are disabled here (`disable_release: true`) in favour of the tag-based release workflow.

`.github/workflows/erpnext.yml` is unrelated to testing — it creates an ERPNext task when an issue is labelled.

Before pushing, the practical minimum is: `phpcs --standard=Moodle local/lehrgaengeapi` clean, and the plugin's
PHPUnit directory green.
