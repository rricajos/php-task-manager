# PHP Task Manager

[![CI](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%207-brightgreen)](https://phpstan.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests](https://img.shields.io/badge/tests-340%2B%20passed-brightgreen)](https://github.com/rricajos/php-task-manager/actions)
[![codecov](https://codecov.io/gh/rricajos/php-task-manager/branch/main/graph/badge.svg)](https://codecov.io/gh/rricajos/php-task-manager)

A task manager with **CLI** and **REST API** interfaces, built with PHP 8.x, SQLite, JWT authentication, and design patterns. Supports multi-user isolation, pagination, sorting, due dates, and streaming export.

## Features

- **Two interfaces**: Interactive CLI menu + REST API with JWT auth
- **Multi-user isolation**: Each user sees only their own tasks
- **Task editing**: Update title, description, priority, and due dates via PUT endpoint
- **Due dates**: Optional due dates with overdue detection
- **Pagination and sorting**: Configurable page size, sort field, and direction
- **Priority/status filtering**: Filter tasks by priority (high/medium/low) and status
- **Streaming export**: JSON and CSV export streamed directly (no temp files in API mode)
- **PHP 8.x**: Enums, readonly, constructor promotion, match, named arguments, union types
- **Design patterns**: Singleton, Repository, Strategy, Dependency Injection
- **Rate limiting**: IP-based sliding window rate limiting (10 req/min for auth, 60 req/min for tasks)
- **CORS middleware**: Configurable Cross-Origin Resource Sharing with environment variable support
- **Security**: JWT (HMAC-SHA256), bcrypt password hashing, prepared statements
- **Recurring tasks**: Auto-creates next occurrence on completion (`daily`/`weekly`/`monthly`)
- **Bulk operations**: Complete or delete multiple tasks in one transactional API call
- **Testing**: 340+ PHPUnit tests (unit + integration)
- **CI/CD**: GitHub Actions with PHP 8.1/8.2/8.3 matrix, PHPStan static analysis, PHP-CS-Fixer
- **Docker**: Containerized deployment with docker-compose
- **Environment config**: .env support for JWT_SECRET, JWT_TTL, DB_PATH
- **API docs**: OpenAPI 3.0 specification included
- **Task tagging system** with many-to-many relationships and colored labels
- **Single-page frontend application** (vanilla JavaScript, no build step)

## Quick Start

### With Composer

```bash
git clone https://github.com/rricajos/php-task-manager.git
cd php-task-manager
composer install
```

### With Docker

```bash
# Start the API server
docker compose up -d api

# Or build and run manually
docker build -t php-task-manager .
docker run -p 8080:8080 php-task-manager
```

> The frontend is served automatically from the same server -- no separate setup needed. Open [http://localhost:8080](http://localhost:8080) in your browser to access the web interface.

### CLI

```bash
php app.php

# Or with Docker
docker compose run --rm cli
```

### REST API

```bash
php -S localhost:8080 api.php
```

> After starting the API, open [http://localhost:8080](http://localhost:8080) in your browser to access the web interface. The frontend is served automatically from the same server -- no separate setup needed.

Then register and get a token:

```bash
# Register
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "secret123"}'

# Login
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "secret123"}'

# Create task (use token from login response)
curl -X POST http://localhost:8080/tasks \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title": "Learn PHP 8", "priority": "high", "due_date": "2026-12-31"}'

# Update task
curl -X PUT http://localhost:8080/tasks/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title": "Master PHP 8", "priority": "medium"}'

# List tasks with pagination and sorting
curl "http://localhost:8080/tasks?page=1&per_page=10&sort=priority&order=asc" \
  -H "Authorization: Bearer <token>"
```

### Tests

```bash
composer test
```

### Static Analysis and Code Style

```bash
composer analyse        # Run PHPStan (level 7)
composer cs-check       # Check code style (dry run)
composer cs-fix         # Auto-fix code style
composer coverage     # Generate test coverage report
```

## API Endpoints

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `POST` | `/auth/register` | Register user | No |
| `POST` | `/auth/login` | Login (get JWT) | No |
| `GET` | `/auth/me` | Get authenticated user profile | Yes |
| `GET` | `/tasks` | List tasks (paginated, filterable, sortable) | Yes |
| `GET` | `/tasks/{id}` | Get task by ID | Yes |
| `POST` | `/tasks` | Create task | Yes |
| `PUT` | `/tasks/{id}` | Update task (title, description, priority, due_date) | Yes |
| `PATCH` | `/tasks/{id}/complete` | Mark as completed | Yes |
| `DELETE` | `/tasks/{id}` | Delete task | Yes |
| `GET` | `/tasks/search?q=keyword` | Search tasks (paginated) | Yes |
| `GET` | `/tasks/stats` | Statistics | Yes |
| `GET` | `/tasks/export?format=json\|csv` | Export tasks (streaming download) | Yes |
| `POST` | `/tasks/bulk-complete` | Complete multiple tasks | Yes |
| `POST` | `/tasks/bulk-delete` | Delete multiple tasks | Yes |
| `GET` | `/tags` | List all tags | Yes |
| `POST` | `/tags` | Create tag | Yes |
| `PATCH` | `/tags/{id}` | Update tag (name, color) | Yes |
| `DELETE` | `/tags/{id}` | Delete tag | Yes |
| `POST` | `/tasks/{id}/tags` | Sync task tags (replace all) | Yes |

### Pagination Parameters

All list endpoints support the following query parameters:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `20` | Results per page (max 100) |
| `sort` | `created_at` | Sort field (`created_at`, `priority`, `title`, `status`) |
| `order` | `DESC` | Sort direction (`ASC` or `DESC`) |
| `status` | `all` | Filter by status (`pending`, `completed`, `all`) |
| `priority` | _(none)_ | Filter by priority (`high`, `medium`, `low`) |

Full API documentation available in [openapi.yaml](openapi.yaml) -- view it in [Swagger Editor](https://editor.swagger.io/).

## Environment Variables

Configure via `.env` file or system environment:

| Variable | Default | Description |
|----------|---------|-------------|
| `JWT_SECRET` | `super_secret_key_change_me` | Secret key for signing JWT tokens (change in production) |
| `JWT_TTL` | `86400` | JWT token lifetime in seconds (default: 24 hours) |
| `DB_PATH` | `data/tasks.db` | Path to the SQLite database file |
| `CORS_ORIGIN` | `*` | Allowed CORS origin (use specific domain in production) |

## Architecture

```
php-task-manager/
├── app.php                  # CLI entry point (delegates to CliApp)
├── api.php                  # REST API entry point
├── openapi.yaml             # OpenAPI 3.0 spec
├── composer.json
├── phpunit.xml
├── phpstan.neon             # PHPStan static analysis config
├── .php-cs-fixer.php        # PHP-CS-Fixer code style config
├── .env                     # Environment variables (not committed)
├── Dockerfile               # Container image definition
├── docker-compose.yml       # Multi-service orchestration
├── public/                    # Frontend SPA
│   ├── index.html
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── data/
│   └── tasks.db             # SQLite database (auto-created)
├── src/
│   ├── ApiController.php    # REST controller (user-scoped)
│   ├── AppException.php     # Custom exception hierarchy
│   ├── AuthService.php      # Registration, login, JWT, profile
│   ├── AuthServiceInterface.php  # Auth service contract
│   ├── CliApp.php           # CLI application (Facade pattern)
│   ├── CorsMiddleware.php   # CORS middleware with configurable origin
│   ├── Database.php         # Singleton PDO connection
│   ├── ExportService.php    # Strategy: JSON + CSV exporters
│   ├── JsonResponse.php     # HTTP response helper
│   ├── Middleware.php        # HTTP middleware (auth, JSON parsing)
│   ├── RateLimiter.php      # IP-based sliding window rate limiter
│   ├── RecurrenceInterval.php  # Backed enum with nextDueDate() calculation
│   ├── Router.php           # HTTP router with path params
│   ├── TagRepository.php    # Tag data access (many-to-many task-tags)
│   ├── Task.php             # Entity with Priority/Status/RecurrenceInterval enums
│   ├── TaskRepository.php   # Data access layer (user-scoped)
│   ├── TaskService.php      # Business logic + validation
│   ├── TaskServiceInterface.php  # Task service contract
│   └── bootstrap.php        # Autoloader configuration
├── tests/
│   ├── Unit/
│   │   ├── AuthServiceTest.php      # Auth + JWT tests
│   │   ├── CorsMiddlewareTest.php  # CORS middleware tests
│   │   ├── DatabaseTest.php         # DB singleton + schema tests
│   │   ├── ExportServiceTest.php    # JSON/CSV export tests
│   │   ├── JsonResponseTest.php     # HTTP response tests
│   │   ├── MiddlewareTest.php       # Middleware tests
│   │   ├── RateLimiterTest.php     # Rate limiter tests
│   │   ├── RouterTest.php           # Routing tests
│   │   ├── TagRepositoryTest.php    # Tag repository tests
│   │   ├── RecurrenceIntervalTest.php  # Recurrence enum + date calculation tests
│   │   ├── TaskRepositoryTest.php   # Repository tests (incl. bulk operations)
│   │   ├── TaskServiceTest.php      # Service + validation tests (incl. recurrence)
│   │   └── TaskTest.php             # Entity + enum tests
│   └── Integration/
│       ├── ApiWorkflowTest.php      # API end-to-end tests
│       └── TaskWorkflowTest.php     # Task workflow tests
└── .github/
    └── workflows/
        └── ci.yml           # CI: tests, PHPStan, code style
```

## Design Patterns

| Pattern | Class | Purpose |
|---------|-------|---------|
| **Singleton** | `Database` | Single PDO connection instance |
| **Repository** | `TaskRepository` | Encapsulated data access with user-scoped queries |
| **Strategy** | `ExporterInterface` / `JsonExporter` / `CsvExporter` | Pluggable export formats |
| **Dependency Injection** | `TaskService`, `ApiController` | Constructor-injected dependencies |
| **Facade** | `CliApp` | Simplified CLI interface over complex subsystems |
| **Middleware** | `Middleware` | HTTP request processing pipeline |
| **Interface Segregation** | `TaskServiceInterface`, `AuthServiceInterface` | Focused service contracts |
| **Sliding Window** | `RateLimiter` | IP-based rate limiting with SQLite storage |
| **Many-to-Many** | `TagRepository` | Task-tags relationship with join table |

## Requirements

- PHP 8.1+
- `pdo_sqlite` extension
- Composer (for dependency management)
- Docker (optional, for containerized deployment)

## Roadmap

- [x] Recurring tasks
- [ ] Team collaboration (shared task lists)
- [ ] WebSocket notifications for real-time updates
- [ ] OpenAPI schema validation middleware

## License

[MIT](LICENSE)
