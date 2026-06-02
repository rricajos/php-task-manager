# Changelog

All notable changes to this project are documented in this file.

## [1.4.0] — 2026-06-02

### Added
- **Recurring tasks** — `RecurrenceInterval` backed enum (`none`, `daily`, `weekly`, `monthly`); completing a recurring task auto-creates the next occurrence with the calculated `due_date`; `recurrence` field available in all task CRUD endpoints and responses
- **Bulk operations** — `POST /tasks/bulk-complete` and `POST /tasks/bulk-delete`; transactional SQLite execution; partial-success reporting (`affected` + `skipped` fields); user-isolated (cross-user IDs silently ignored)
- Frontend SPA: recurrence `<select>` in task form, recurrence badge (&#8635;) on task cards, bulk-select checkboxes on each card, floating bulk action bar with "Complete selected" / "Delete selected" / "Clear"
- OpenAPI 3.0 spec: `recurrence` field added to `Task` schema and create/update request bodies; `POST /tasks/bulk-complete` and `POST /tasks/bulk-delete` endpoints documented
- ~50 new tests covering `RecurrenceInterval`, recurring task auto-creation, `bulkComplete`/`bulkDelete` (repository + service layers), user isolation, partial success, and input validation

### Changed
- `Task` entity gains `recurrence` property (defaults to `RecurrenceInterval::None`)
- `TaskRepository::save()` and `update()` now persist the `recurrence` column
- `TaskService::completeTask()` triggers next-occurrence creation for recurring tasks

## [1.3.0] — 2026-05-28

### Added
- **Tags/Categories** — Full many-to-many tagging system: `tags` and `task_tags` tables, `TagRepository` with user-scoped CRUD, batch tag loading (no N+1), cascade deletes
- 5 new API endpoints: `GET /tags`, `POST /tags`, `PATCH /tags/{id}`, `DELETE /tags/{id}`, `POST /tasks/{id}/tags`
- `tag_ids` support in `POST /tasks` and `PATCH /tasks/{id}` request bodies
- Tags included in all task responses (`tags` array with id, name, color)
- Tag management UI in the SPA: colored tag pills on task cards, checkboxes in task form, management modal
- **Frontend SPA** — Vanilla JavaScript single-page application served from the same PHP server (`public/`): auth views, task list with filters/search/pagination, stats, export, tag management; no build step required
- OpenAPI 3.0 spec updated with tag endpoints, `Tag`, `TagSummary`, `TagResponse`, `TagListResponse`, `SyncTaskTagsResponse` schemas
- 26 new `TagRepositoryTest` tests + 3 `DatabaseTest` schema tests

### Changed
- All task endpoints now include a `tags` array in their responses
- `ApiController` enriches task responses with tags via single batch query

## [1.2.0] — 2026-05-27

### Added
- **Rate limiting** — IP-based sliding window rate limiter (`RateLimiter`): 10 req/min on `/auth/*`, 60 req/min on task endpoints; `X-RateLimit-*` response headers
- **CORS middleware** — `CorsMiddleware` with `CORS_ORIGIN` environment variable support and preflight (`OPTIONS`) handling
- PHPUnit coverage configuration (`phpunit.xml`), Codecov badge in README
- `RateLimiterTest` (20 tests) and `CorsMiddlewareTest` (18 tests)

## [1.1.0] — 2026-05-26

### Added
- **Task export** — Strategy pattern: `ExporterInterface`, `JsonExporter`, `CsvExporter`; streaming export via `GET /tasks/export?format=json|csv` with `Content-Disposition: attachment`; UTF-8 BOM on CSV for Excel compatibility
- **Task statistics** — `GET /tasks/stats` endpoint: totals, breakdown by status/priority, completion percentage
- **Search** — `GET /tasks/search?q=keyword` with full-text search across title and description, paginated
- **Due dates** — Optional `due_date` field (YYYY-MM-DD) with overdue detection; filterable
- **JWT profile endpoint** — `GET /auth/me` returns authenticated user profile
- **OpenAPI 3.0 specification** — `openapi.yaml` with full schema, security, pagination and rate limit documentation
- **GitHub Actions CI** — Matrix pipeline on PHP 8.1/8.2/8.3: PHPUnit, PHPStan level 7, PHP-CS-Fixer
- **Docker** — `Dockerfile` + `docker-compose.yml` with `api` and `cli` services; SQLite data volume
- **Makefile** — Convenience targets: `test`, `analyse`, `cs-check`, `cs-fix`, `coverage`, `docker-build`, `docker-test`
- `.env` support — `JWT_SECRET`, `JWT_TTL`, `DB_PATH`, `CORS_ORIGIN`
- PHPStan raised to **level 7**; PHP-CS-Fixer with PER-CS 2.0 ruleset

### Changed
- `TaskRepository` expanded with search, stats, export, due date filtering
- All list endpoints support pagination (`page`, `per_page`, `sort`, `order`, `status`, `priority`)

## [1.0.0] — 2026-05-25

### Added
- **Core task management** — SQLite-backed CRUD: create, list, complete, delete tasks with `Priority` and `Status` backed enums
- **Multi-user isolation** — All queries scoped by `user_id` extracted from JWT token
- **JWT authentication** — HMAC-SHA256 signed tokens; `POST /auth/register`, `POST /auth/login`; bcrypt password hashing
- **REST API** — `Router` with path parameters; `ApiController`; `JsonResponse` helper; `Middleware` for auth and JSON body parsing
- **CLI interface** — `CliApp` interactive menu (Facade pattern) for local task management
- **Repository pattern** — `TaskRepository` with `TaskService` business logic layer; `TaskServiceInterface` and `AuthServiceInterface` contracts
- **Database singleton** — `Database` with WAL mode, foreign keys, `CASCADE` deletes
- **Testing** — PHPUnit with unit tests for all layers: entities, repository, service, controller, router, auth
- **PHP 8.1+ features** — `readonly`, constructor promotion, backed enums, `match`, named arguments, union types, `strict_types`
