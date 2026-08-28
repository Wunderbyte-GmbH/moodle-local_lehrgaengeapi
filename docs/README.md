# local_lehrgaengeapi — Documentation

Welcome to the documentation for the **local_lehrgaengeapi** Moodle plugin by [Wunderbyte GmbH](https://www.wunderbyte.at).

The plugin connects Moodle to an external **ZMS / Lehrgangsverwaltung REST API** and keeps Moodle in sync with it:
it creates courses for *Lehrgänge*, creates Moodle users for *Teilnehmer*, and enrols, unenrols or marks them
complete according to their status in the external system. It is multi-tenant: every Hessian district, city and
school (*Mandant*) has its own API key and its own IOMAD company.

This `docs/` directory is the central reference for administrators and developers who work with the plugin.

---

## Quick-start guide

| I want to… | Go to… |
|------------|--------|
| Install the plugin and understand its dependencies | [Installation](user/installation/README.md) |
| Configure the API connection (URL, timeout, certificates) | [Configuration](user/configuration/README.md) |
| Add an API key for a district / city / school | [Tenants](user/tenants/README.md) |
| Understand which Moodle course a Lehrgang ends up in | [Course creation](user/course_creation/README.md) |
| Understand when a participant is enrolled, unenrolled or marked complete | [Participants](user/participants/README.md) |
| Import a specific year on demand | [Manual import](user/manual_import/README.md) |
| See what a past import actually did | [Manual import → Import history](user/manual_import/README.md#4-import-history) |
| Understand the nightly background sync | [Scheduled tasks](user/scheduled_tasks/README.md) |
| Fix an import that fails or does nothing | [Troubleshooting](user/troubleshooting/README.md) |
| Understand how the code is put together | [Architecture](developer-guides/ARCHITECTURE.md) |
| Call the external API from your own code | [API client](developer-guides/API_CLIENT_API.md) |
| Change how courses and participants are synced | [Sync services](developer-guides/SYNC_SERVICE_API.md) |
| Add or change a participant status mapping | [Status handlers](developer-guides/STATUS_HANDLERS_API.md) |
| Look up a database table or column | [Database schema](developer-guides/DATABASE_SCHEMA.md) |
| Run the test suite | [Testing](developer-guides/TESTING.md) |

Important distinctions when reading (or explaining) these docs:

- **Course creation** decides *which* Moodle course a Lehrgang maps to (naming convention, template copy, `_alt` courses).
  Questions about "why was no course created" belong there.
- **Participants** decides *what happens to a person* inside that course (enrol / unenrol / complete / group).
  Questions about "why is this user not enrolled" belong there.
- **Tenants** is about API keys and IOMAD companies. A tenant without an API key is silently skipped by every run —
  this is the single most common cause of "nothing was imported".
- The plugin **never updates an existing course**. It creates courses that do not exist yet and then syncs
  participants into them. Changing a Lehrgang title in the external system will not rename the Moodle course.

---

## First admin workflow (click-by-click)

1. Install [IOMAD](https://www.iomad.org/) and create one company per tenant — see [Installation](user/installation/README.md).
2. Set the API base URL and the client certificate under
   *Site administration → Plugins → Local plugins → Lehrgaenge API*: [/admin/settings.php?section=local_lehrgaengeapi](/admin/settings.php?section=local_lehrgaengeapi).
3. Enter the API key for each tenant you are entitled to read: [Tenants](user/tenants/README.md).
4. Create the **template courses** the plugin copies from — see [Course creation](user/course_creation/README.md#3-template-courses).
5. Trigger a manual import for the current year: [/local/lehrgaengeapi/admin/manual_import.php](/local/lehrgaengeapi/admin/manual_import.php).
6. Check the result in the import history: [/local/lehrgaengeapi/admin/import_runs.php](/local/lehrgaengeapi/admin/import_runs.php).
7. Leave the nightly [scheduled task](user/scheduled_tasks/README.md) to keep everything up to date.

---

## Documentation sections

### Administrator guides

| Directory | Description |
|-----------|-------------|
| [`installation/`](user/installation/README.md) | Requirements, the hard IOMAD dependency, installation and upgrade |
| [`configuration/`](user/configuration/README.md) | Every setting in `settings.php`, what it does and what it does *not* do |
| [`tenants/`](user/tenants/README.md) | The 35 tenants, their abbreviations, API key settings and IOMAD company mapping |
| [`course_creation/`](user/course_creation/README.md) | Shortname convention, template courses, `_alt` courses for past years, HLFS routing |
| [`participants/`](user/participants/README.md) | User creation, status → action mapping, enrolment, groups and course completion |
| [`manual_import/`](user/manual_import/README.md) | The manual year import page, the notification, and the import history reports |
| [`scheduled_tasks/`](user/scheduled_tasks/README.md) | The scheduled and ad-hoc tasks: purpose, schedule, locking and tuning |
| [`troubleshooting/`](user/troubleshooting/README.md) | Symptom → cause → fix, including known quirks and dead settings |

### Developer guides

| File | Description |
|------|-------------|
| [`ARCHITECTURE.md`](developer-guides/ARCHITECTURE.md) | Layers, data flow, class map and extension points |
| [`API_CLIENT_API.md`](developer-guides/API_CLIENT_API.md) | `api_client`, authenticators, endpoints, typed exceptions |
| [`SYNC_SERVICE_API.md`](developer-guides/SYNC_SERVICE_API.md) | `lehrgaenge_sync_service`, `participants_sync_service` and the report format |
| [`STATUS_HANDLERS_API.md`](developer-guides/STATUS_HANDLERS_API.md) | How raw statuses are resolved to actions, and how to add one |
| [`DATABASE_SCHEMA.md`](developer-guides/DATABASE_SCHEMA.md) | The three plugin tables, column by column |
| [`TESTING.md`](developer-guides/TESTING.md) | PHPUnit suite, fixtures, fake endpoints and the live test-system test |

---

## Contributing to documentation

- All documentation is written in Markdown.
- Each topic has its own subdirectory under `docs/user/`, developer topics live in `docs/developer-guides/`.
- Screenshots go in a `pix/` subdirectory inside the relevant topic directory.
- Language strings are maintained in English **and** German (`lang/en/`, `lang/de/`) — keep both in sync.

---

## Related resources

- [Wunderbyte website](https://www.wunderbyte.at)
- [IOMAD](https://www.iomad.org/) — provides the `company` multi-tenancy layer this plugin builds on
