[Back to parent section](../README.md)

# Configuration — Administrator guide

Every setting the plugin registers, what it actually controls, and which settings are currently inert.

All settings live at *Site administration → Plugins → Local plugins → Lehrgaenge API*
([/admin/settings.php?section=local_lehrgaengeapi](/admin/settings.php?section=local_lehrgaengeapi))
and are stored in Moodle's `config_plugins` table under the plugin name `local_lehrgaengeapi`.

---

## Quick setup path

1. Set **Base URL** to the external API root.
2. Set **Certificate path**, **Client certificate filename** and **Client key filename** if the API requires mTLS.
3. Leave **Request timeout** and **Request delay** at their defaults for the first run.
4. Enter the API key for each tenant further down the page — see [Tenants](../tenants/README.md).
5. Save, then trigger a [manual import](../manual_import/README.md) for the current year to verify.

---

## Table of Contents

1. [External API settings](#1-external-api-settings)
2. [Client certificates (mTLS)](#2-client-certificates-mtls)
3. [Rate limiting and timeouts](#3-rate-limiting-and-timeouts)
4. [Tenant settings](#4-tenant-settings)
5. [Settings that currently have no effect](#5-settings-that-currently-have-no-effect)
6. [Setting the values from the CLI](#6-setting-the-values-from-the-cli)

---

## 1. External API settings

| Setting | Config name | Type | Default | Effect |
|---------|-------------|------|---------|--------|
| **Base URL** | `baseurl` | `PARAM_URL` | *(empty)* | Root of the external API, e.g. `https://zms.example.org/rest/services/moodle-services`. All endpoint paths are appended to it. A trailing `/` is stripped automatically. |
| **Request timeout (seconds)** | `timeout` | `PARAM_INT` | `30` | `CURLOPT_TIMEOUT` for every API call. |
| **Request delay between participant calls (ms)** | `requestdelayms` | `PARAM_INT` | `1000` | Milliseconds slept before **each** `/teilnehmer` request. |
| **Certificate path** | `certificationpath` | directory | *(empty)* | Absolute path to the directory holding the client certificate and key. Moodle validates that the directory exists. |
| **Client certificate filename** | `certificate_general` | `PARAM_RAW` | *(empty)* | Filename inside the certificate path. |
| **Client key filename** | `key_general` | `PARAM_RAW` | *(empty)* | Filename inside the certificate path. |

An empty **Base URL** is not validated at save time. The failure shows up as an `HTTP 0` error on the first run —
see [Troubleshooting](../troubleshooting/README.md).

---

## 2. Client certificates (mTLS)

The external API is normally protected by mutual TLS. The plugin builds the cURL options like this:

```php
$options['CURLOPT_SSLCERT'] = $certificationpath . $clientcert;
$options['CURLOPT_SSLKEY']  = $certificationpath . $clientkey;
```

Two consequences worth knowing:

- The two values are **concatenated without a separator**. Your certificate path must therefore end with a slash,
  e.g. `/etc/moodle/certs/` — not `/etc/moodle/certs`. Moodle's directory picker accepts both, so this is easy to
  get wrong.
- The certificate is applied **only if both** the certificate filename and the key filename are non-empty. Setting
  just one of them silently disables mTLS, and the API will answer `401`.

Despite sitting under the *Tenant settings* heading, `certificate_general` and `key_general` are **global**: the same
certificate and key are used for every tenant. Only the API key is per tenant.

The web server user must be able to read both files. Keep the key outside the web root and outside `moodledata`.

---

## 3. Rate limiting and timeouts

The external API rate-limits aggressively when a full year is imported across 35 tenants. Two settings control the
pace:

| Symptom | Setting to change |
|---------|-------------------|
| Runs abort with `HTTP 429` | Increase `requestdelayms` (e.g. `1000` → `2000`) |
| Single calls abort with `HTTP 0` and a cURL timeout message | Increase `timeout` |

`requestdelayms` is applied once per Lehrgang, immediately before its participants are fetched:

```php
$delayms = (int)get_config('local_lehrgaengeapi', 'requestdelayms');
if ($delayms > 0) {
    usleep($delayms * 1000);
}
```

Budget accordingly: at the default of 1000 ms, an import of 2000 Lehrgänge spends over half an hour sleeping alone.

> The help text for this setting says *"Default: 500 ms"*. The actual default registered in `settings.php` is
> **1000 ms**. The setting works; only the help text is stale.

A `429` response is raised as `api_rate_limited_exception`, which carries the `Retry-After` header if the API sends
one. The scheduled task logs it and lets Moodle's `faildelay` retry the run; a manual import records the error and
notifies the user instead of retrying.

---

## 4. Tenant settings

Below the *Tenant settings* heading, the settings page renders one **API key** field per tenant, labelled with the
tenant name and abbreviation. The config name is derived from the abbreviation:

```
apikey_<lowercased abbreviation, non [a-z0-9_] replaced by _>
```

For example `Main-Kinzig-Kreis (MKK)` → `apikey_mkk`, and `Rheingau-Taunus-Kreis (RÜD)` → `apikey_r_d`.

The full list of tenants and their config names is in [Tenants](../tenants/README.md).

---

## 5. Settings that currently have no effect

Four settings are registered on the page but are not read by any code path that runs during a sync. They are
documented here so nobody spends time tuning them:

| Setting | Config name | Status |
|---------|-------------|--------|
| **Sync interval: Lehrgänge list (seconds)** | `interval_lehrgaenge` | Read only by `moodle_settings_repository`, which is never instantiated. The sync frequency is controlled by the [scheduled task](../scheduled_tasks/README.md) schedule, not by this value. |
| **Sync interval: Teilnehmer (seconds)** | `interval_teilnehmer` | Same as above. Participants are synced inline with their Lehrgang, on the same schedule. |
| **Target course ID** | `targetcourseid` | Registered and translated, but never read outside a test. Template courses are resolved **by shortname**, not by this setting — see [Course creation](../course_creation/README.md#3-template-courses). |
| **API token** | `token` | `moodle_settings_repository::get_token()` reads it, but the setting is not registered on the settings page and the repository is unused. Authentication uses the per-tenant API keys. |

Changing any of these has no effect on what the plugin does.

---

## 6. Setting the values from the CLI

Useful for scripted deployments:

```bash
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=baseurl \
    --set=https://zms.example.org/rest/services/moodle-services
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=certificationpath --set=/etc/moodle/certs/
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=certificate_general --set=client.pem
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=key_general --set=client.key
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=apikey_fd --set=<secret>
```

To read a value back:

```bash
php admin/cli/cfg.php --component=local_lehrgaengeapi --name=baseurl
```

API keys are stored in plain text in `config_plugins`, like all Moodle plugin settings. Treat database dumps
accordingly.
