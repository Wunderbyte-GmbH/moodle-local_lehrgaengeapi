[Back to documentation index](../README.md)

# API client — Developer guide

The HTTP layer: `api_client`, the authenticator strategy, the `lehrgaenge_endpoint` facade and the typed exception
hierarchy.

---

## Quick orientation

1. Build an endpoint with `factory::lehrgaenge_endpoint($apikey)`.
2. Call one of the four endpoint methods.
3. Catch `api_exception` (or one of its subclasses) for anything that is not a 2xx.

---

## Table of Contents

1. [Building a client](#1-building-a-client)
2. [`lehrgaenge_endpoint`](#2-lehrgaenge_endpoint)
3. [`api_client`](#3-api_client)
4. [Authentication](#4-authentication)
5. [`api_response`](#5-api_response)
6. [Exceptions](#6-exceptions)
7. [Payload shapes](#7-payload-shapes)
8. [Adding an endpoint](#8-adding-an-endpoint)

---

## 1. Building a client

The supported way:

```php
use local_lehrgaengeapi\factory;

$endpoint = factory::lehrgaenge_endpoint($apikey);
$lehrgaenge = $endpoint->list($tenant);
```

`factory::lehrgaenge_endpoint()` reads `baseurl` and `timeout` from plugin config and wires
`token_authenticator` → `api_client` → `lehrgaenge_endpoint`.

Building it by hand is only worth doing in tests, where you want to inject a `\curl` double:

```php
use local_lehrgaengeapi\api\api_client;
use local_lehrgaengeapi\api\auth\token_authenticator;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint;

$client = new api_client('https://zms.example.org/rest/services/moodle-services',
    new token_authenticator('secret'), 30, $fakecurl);
$endpoint = new lehrgaenge_endpoint($client);
```

---

## 2. `lehrgaenge_endpoint`

A typed facade over four API paths. It implements `lehrgaenge_endpoint_interface`, which is what the sync services
depend on — always type-hint the interface so tests can substitute a fake.

| Method | HTTP | Used by the sync |
|--------|------|------------------|
| `list($tenant, $searchcriteria = null): array` | `GET /lehrgaenge` | Yes |
| `get_by_id($tenant, string $id): array` | `GET /lehrgaenge/{id}` | No |
| `participants($tenant, string $id): array` | `GET /lehrgaenge/{id}/teilnehmer` | Yes |
| `participant_extern($tenant, string $id, string $teilnehmerid): array` | `GET /lehrgaenge/{id}/teilnehmer-extern/{teilnehmerId}` | No |

Path segments are `rawurlencode()`d. Every method returns the decoded JSON array and throws on a non-2xx status.

### Search criteria

`list()` accepts three shapes:

```php
$endpoint->list($tenant);                                              // no filter
$endpoint->list($tenant, ['lehrgangVon' => '2025-01-01',               // each key its own query param
                          'lehrgangBis' => '2025-12-31']);
$endpoint->list($tenant, '{"…":"…"}');                                 // literal searchCriteria=<string>
```

The array form is what the real API wants. Despite the `searchCriteria` naming in the OpenAPI document, the filter
fields are **top-level query parameters** — wrapping them in a `searchCriteria` object returns HTTP 400. The string
form is retained for API paths that genuinely expect the wrapped shape.

### The `$tenant` parameter

Every method takes `$tenant` as its first argument and **never uses it**. It is threaded through `api_client::get()`
unused as well. Pass the tenant array you already have; do not build one for the sake of it.

---

## 3. `api_client`

```php
public function __construct(
    string $baseurl,
    authenticator_interface $authenticator,
    int $timeoutseconds = 30,
    ?\curl $curl = null
);

public function get(string $path, array $tenant, array $query = []): api_response;
```

`get()` is the only verb implemented. It:

1. Builds the URL: base URL with trailing slash stripped, plus the path, plus a query string built with an
   **explicit `'&'` separator** (see below).
2. Reads `certificationpath`, `certificate_general` and `key_general` from config and, if the certificate *and* key
   are both non-empty, sets `CURLOPT_SSLCERT` / `CURLOPT_SSLKEY` to `certificationpath . filename` —
   concatenated with no separator, so the path must end with a slash.
3. Sets `Accept: application/json` plus whatever the authenticator returns.
4. Performs the request, reads `http_code` from `curl->get_info()`.
5. Returns an `api_response` on 2xx, throws otherwise.

### The `arg_separator.output` trap

```php
$url .= '?' . http_build_query($filtered, '', '&');
```

Moodle's bootstrap sets the `arg_separator.output` ini setting to `&amp;` so that `moodle_url` renders HTML-safe
links. `http_build_query()` inherits that setting when no separator is passed, which turns a real outgoing request
into `?lehrgangVon=…&amp;lehrgangBis=…` — the API then sees a parameter literally named `amp;lehrgangBis` and
answers 400. Always pass the separator explicitly.

`null` values in the query array are filtered out before the string is built.

### Response header parsing

`curl->getResponse()` returns raw headers, and may contain several blocks when redirects occurred. The client splits
on blank lines, takes the **last** non-empty block, and parses `Name: value` lines into an associative array. On
some Moodle versions the value can come back as an array on a blocked call; that case is normalised to an empty
string.

---

## 4. Authentication

```php
interface authenticator_interface {
    public function get_headers(): array;   // ['Header-Name' => 'value']
}
```

The only implementation is `token_authenticator`:

```php
new token_authenticator(string $token, string $headername = 'X-MoodleAuthToken');
```

It returns `['X-MoodleAuthToken' => $token]`, or an empty array when the token is an empty string — which is why a
tenant with an empty API key produces a 401 rather than a crash, in the rare case the sync's own empty-key guard is
bypassed.

To support a different scheme (Bearer tokens, HMAC signing), implement the interface and pass it to `api_client`.
Nothing else needs to change.

---

## 5. `api_response`

An immutable value object with `readonly` properties:

```php
public readonly int $status;
public readonly string $body;
public readonly array $headers;

public function json_array(): array;          // json_decode with JSON_THROW_ON_ERROR
public function get_header(string $name): ?string;   // case-insensitive
```

`json_array()` throws `\JsonException` on malformed JSON and returns `[]` when the payload decodes to a scalar.

---

## 6. Exceptions

All API failures extend `api_exception`, which extends `\moodle_exception` with the string key
`apirequestfailed` / `local_lehrgaengeapi`.

| Status | Exception |
|--------|-----------|
| 400 | `api_bad_request_exception` |
| 401 | `api_unauthorized_exception` |
| 403 | `api_forbidden_exception` |
| 404 | `api_not_found_exception` |
| 429 | `api_rate_limited_exception` |
| ≥ 500 | `api_server_error_exception` |
| 0 | `api_exception` with the cURL error appended to the message |
| anything else non-2xx | `api_exception` |

Every one carries the context you need for a log line:

```php
$e->get_http_status();       // ?int
$e->get_response_body();     // ?string  — raw body, worth logging
$e->get_response_headers();  // array
$e->get_previous_exception();// ?Throwable
```

`api_rate_limited_exception` adds:

```php
$e->get_retry_after_seconds();  // ?int, from the Retry-After header, null when absent or non-positive
```

The tasks log `get_response_body()` alongside the message, which is what surfaces the API's own error text in the
import history's *Raw data (JSON)* panel.

---

## 7. Payload shapes

Representative fixtures live in `tests/dummy_data/`. The fields the plugin actually reads:

### Lehrgang (`GET /lehrgaenge`)

```json
{
  "id": "FD-EXT-X-2025",
  "bezeichnung": "Atemschutzgeräteträgerlehrgang",
  "kurzbezeichnung": "AGT",
  "ort": "FTZ Bröckehausen",
  "startTag": "2025-03-10",
  "endTag": "2025-03-14"
}
```

| Field | Used for |
|-------|----------|
| `id` | Course `idnumber`, mapping key |
| `kurzbezeichnung` | Course type segment of the shortname, after the mapping table |
| `endTag` | Two-digit year segment of the shortname (`substr($endTag, 2, 2)`) |
| `ort` | HLFS routing (prefix match) |
| `startTag` | Group name |
| `bezeichnung` | Read by the unreachable F-III e-learning branch only |

Everything else in the payload (`typ`, `startZeit`, `maxAnzahlTeilnehmer`, `lehrgangstage`, …) is ignored.

### Teilnehmer (`GET /lehrgaenge/{id}/teilnehmer`)

| Field | Used for |
|-------|----------|
| `id` | User `idnumber`, `usermap` key |
| `initialId` | Username (and password seed) |
| `vorname` / `nachname` | Names, with fallbacks |
| `emails.emailBusiness` → `emails.emailPrivat` | Email, in that order |
| `ort` | User city |
| `status` | Status → action mapping |

> **Fixture inconsistency.** `users_creator` reads the email from a nested `emails` object
> (`$p['emails']['emailBusiness']`), and the service-level tests build their payloads that way. The JSON fixtures in
> `tests/dummy_data/` instead carry `emailBusiness` / `emailPrivat` at the top level. If you are writing a new test
> against those fixtures, note that no email will be picked up and users get placeholder addresses. Confirm the real
> shape against the live API before "fixing" either side.

---

## 8. Adding an endpoint

1. Add the method to `lehrgaenge_endpoint_interface` **and** `lehrgaenge_endpoint`.
2. `rawurlencode()` every path segment that comes from data.
3. Return `$response->json_array()`; let exceptions propagate.
4. Update the fakes in `tests/` — they implement the interface, so a new method breaks them at parse time.

```php
public function stundenplan($tenant, string $id): array {
    $response = $this->client->get('/lehrgaenge/' . rawurlencode($id) . '/stundenplan', $tenant);
    return $response->json_array();
}
```

If you need a verb other than GET, add it to `api_client` alongside `get()` and reuse `build_url()`,
`parse_response_headers()` and `throw_for_status()`.
