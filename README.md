# Lehrgaenge API

```mermaid
flowchart TD
  Cron["Moodle Cron / Scheduled Task Runner"] --> Task["sync_lehrgaenge_task (scheduled_task)"]
  Task --> Lock["\\core\\lock (prevents overlap)"]
  Task --> Factory["factory: build services from config"]

  Factory --> Config["get_config('local_lehrgaengeapi')"]
  Factory --> Auth["token_authenticator (X-MoodleAuthToken)"]
  Factory --> Client["api_client (HTTP via Moodle \\curl)"]
  Factory --> Endpoint["lehrgaenge_endpoint (Facade)"]
  Factory --> Service["lehrgaenge_sync_service"]

  %% External call chain
  Service --> Endpoint
  Endpoint --> Client
  Client --> Curl["\\curl (lib/filelib.php)"]
  Curl --> External(["External ZMS API"])

  %% DB mapping/persistence
  Service --> RepoCourse["coursemap_repository"]
  Service --> RepoUser["usermap_repository"]
  RepoCourse --> DB[("Moodle DB (mapping tables)")]
  RepoUser --> DB

  %% Moodle updates
  Service --> Category["Default Course Category<br/>(moodlecourse.defaultcategory)"]
  Service --> MoodleCourses(["Moodle Course API (create/update)"])
  Service --> MoodleUsers(["Moodle User API (create/update)"])
  Category --> MoodleCourses

  %% Responses & errors
  Client -->|2xx| Response["api_response (status/body/headers)"]
  Client -->|non-2xx| Ex["api_exception (+ typed subclasses)"]
  Ex --> Task
  Response --> Endpoint
  Endpoint --> Service
  Service --> Task