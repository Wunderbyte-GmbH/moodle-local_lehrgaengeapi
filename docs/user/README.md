[Back to documentation index](../README.md)

# local_lehrgaengeapi — Administrator documentation

Index of the administrator documentation chapters. For developer topics see the [developer guides](../developer-guides/ARCHITECTURE.md).

| I want to… | Go to… |
|------------|--------|
| Install the plugin and understand its dependencies | [Installation](installation/README.md) |
| Configure the API connection | [Configuration](configuration/README.md) |
| Add an API key for a district / city / school | [Tenants](tenants/README.md) |
| Understand which Moodle course a Lehrgang ends up in | [Course creation](course_creation/README.md) |
| Understand when a participant is enrolled or marked complete | [Participants](participants/README.md) |
| Import a specific year on demand | [Manual import](manual_import/README.md) |
| Understand the nightly background sync | [Scheduled tasks](scheduled_tasks/README.md) |
| Fix an import that fails or does nothing | [Troubleshooting](troubleshooting/README.md) |

---

## The sync in one paragraph

Once per run, the plugin loops over every tenant that has an API key. For each tenant it fetches the list of
*Lehrgänge* from the external API, works out a Moodle course shortname for each one, and creates that course from a
template if it does not exist yet. It then fetches the *Teilnehmer* of that Lehrgang, creates a Moodle user for each
person who does not have one yet, and enrols, unenrols or marks them complete depending on their status. Everything
that happened is written to an import-run log you can read in the admin UI.
