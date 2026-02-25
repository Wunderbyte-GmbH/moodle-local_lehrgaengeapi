```mermaid
flowchart LR
  %% Scheduler
  subgraph Scheduler [Scheduler]
    Cron["Moodle Cron / Scheduled Task Runner"]
    Task["sync_lehrgaenge_task"]
  end
  Cron --> Task
  Task --> Lock["core\\lock<br/>(prevents concurrent runs)"]
  Task --> Factory["Factory"]

  %% Local services grouped vertically for clarity
  subgraph Services [Local Services]
    direction TB
    Factory --> SyncService["lehrgaenge_sync_service<br/>(orchestrator)"]
    SyncService --> CourseFlow["Course Sync"]
    SyncService --> ParticipantsSyncSvc["Participants Sync"]
  end

  %% External API cluster (keeps external calls on the right)
  # Lehrgaenge API

  ```mermaid
  flowchart LR
    %% Scheduler
    subgraph Scheduler [Scheduler]
      Cron["Moodle Cron / Scheduled Task Runner"]
      Task["sync_lehrgaenge_task"]
    end
    Cron --> Task
    Task --> Lock["core\\lock<br/>(prevents concurrent runs)"]
    Task --> Factory["Factory"]

    %% Local services grouped vertically for clarity
    subgraph Services [Local Services]
      direction TB
      Factory --> SyncService["lehrgaenge_sync_service<br/>(orchestrator)"]
      SyncService --> CourseFlow["Course Sync"]
      SyncService --> ParticipantsSyncSvc["Participants Sync"]
    end

    %% External API cluster (keeps external calls on the right)
    subgraph API [External API]
      direction TB
      Auth["token_authenticator"]
      Client["api_client<br/>(HTTP Client)"]
      Curl["curl<br/>(lib/filelib.php)"]
      External["External ZMS API"]
      Auth --> Client
      Client --> Curl --> External
    end

    %% Moodle platform cluster (bottom-right)
    subgraph Moodle [Moodle Platform]
      direction TB
      MoodleDB["Moodle DB<br/>(mapping tables)"]
      MoodleCourseAPI["Moodle Course API<br/>(create/update)"]
      MoodleUserAPI["Moodle User API<br/>(create/update)"]
    end

    %% Connections between services and API / Moodle
    SyncService --> Endpoint["lehrgaenge_endpoint<br/>(API Facade)"]
    Endpoint --> Client

    CourseFlow --> CourseCreator["course_creator"]
    CourseFlow --> RepoCourse["coursemap_repository"]
    RepoCourse --> MoodleDB
    CourseCreator --> MoodleCourseAPI

    ParticipantsSyncSvc --> UsersCreator["users_creator"]
    ParticipantsSyncSvc --> ParticipantAssigner["participant_course_assigner"]
    ParticipantsSyncSvc --> RepoUser["usermap_repository"]
    RepoUser --> MoodleDB
    UsersCreator --> MoodleUserAPI
    ParticipantAssigner --> MoodleCourseAPI

    %% Participant status handlers grouped to the right of participants flow
    subgraph Status [Participant Status Handling]
      direction LR
      Resolver["participant_status_handler_resolver"]
      Handlers["Handlers:<br/>angemeldet, bestanden, noop"]
      Action["participant_status_action"]
      Resolver --> Handlers --> Action --> MoodleUserAPI
    end
    ParticipantsSyncSvc --> Resolver

    %% Response & error handling (kept close to API cluster)
    Client -->|2xx| Response["api_response<br/>(body/status/headers)"]
    Client -->|Error| ApiEx["api_exception<br/>(401/403/404/429/500)"]
    Response --> Endpoint
    ApiEx --> Task

    %% Cosmetic: keep main orchestration visible
    Task --> SyncService
  ```