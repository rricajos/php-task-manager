# PHP Task Manager

[![CI](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%206-brightgreen)](https://phpstan.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests](https://img.shields.io/badge/tests-93%20passed-brightgreen)](https://github.com/rricajos/php-task-manager/actions)

A task manager with **CLI** and **REST API** interfaces, built with PHP 8.x, SQLite, JWT authentication, and design patterns. Supports multi-user isolation, pagination, sorting, due dates, and streaming export.

## Features

- **Two interfaces**: Interactive CLI menu + REST API with JWT auth
- **Multi-user isolation**: Each user sees only their own tasks
- **Task editing**: Update title, description, priority, and due dates via PUT endpoint
- **Due dates**: Optional fecha_vencimiento with overdue detection
- **Pagination and sorting**: Configurable page size, sort field, and direction
- **Priority/status filtering**: Filter tasks by prioridad (alta/media/baja) and estado
- **Streaming export**: JSON and CSV export streamed directly (no temp files in API mode)
- **PHP 8.x**: Enums, readonly, constructor promotion, match, named arguments, union types
- **Design patterns**: Singleton, Repository, Strategy, Dependency Injection
- **Security**: JWT (HMAC-SHA256), bcrypt password hashing, prepared statements
- **Testing**: 93 PHPUnit tests (unit + integration)
- **CI/CD**: GitHub Actions with PHP 8.1/8.2/8.3 matrix, PHPStan static analysis, PHP-CS-Fixer
- **Docker**: Containerized deployment with docker-compose
- **Environment config**: .env support for JWT_SECRET, JWT_TTL, DB_PATH
- **API docs**: OpenAPI 3.0 specification included

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
  -d '{"title": "Learn PHP 8", "priority": "alta", "due_date": "2026-12-31"}'

# Update task
curl -X PUT http://localhost:8080/tasks/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title": "Master PHP 8", "priority": "media"}'

# List tasks with pagination and sorting
curl "http://localhost:8080/tasks?page=1&per_page=10&sort=prioridad&order=asc" \
  -H "Authorization: Bearer <token>"
```

### Tests

```bash
composer test
```

### Static Analysis and Code Style

```bash
composer analyse        # Run PHPStan (level 6)
composer cs-check       # Check code style (dry run)
composer cs-fix         # Auto-fix code style
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

### Pagination Parameters

All list endpoints support the following query parameters:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `page` | `1` | Page number |
| `per_page` | `20` | Results per page (max 100) |
| `sort` | `fecha_creacion` | Sort field (`fecha_creacion`, `prioridad`, `titulo`, `estado`) |
| `order` | `DESC` | Sort direction (`ASC` or `DESC`) |
| `status` | `todas` | Filter by status (`pendiente`, `completada`, `todas`) |
| `priority` | _(none)_ | Filter by priority (`alta`, `media`, `baja`) |

Full API documentation available in [openapi.yaml](openapi.yaml) -- view it in [Swagger Editor](https://editor.swagger.io/).

## Environment Variables

Configure via `.env` file or system environment:

| Variable | Default | Description |
|----------|---------|-------------|
| `JWT_SECRET` | `super_secret_key_change_me` | Secret key for signing JWT tokens (change in production) |
| `JWT_TTL` | `86400` | JWT token lifetime in seconds (default: 24 hours) |
| `DB_PATH` | `data/tasks.db` | Path to the SQLite database file |

## Architecture

```
php-task-manager/
├── app.php                  # CLI entry point
├── api.php                  # REST API entry point
├── openapi.yaml             # OpenAPI 3.0 spec
├── composer.json
├── phpunit.xml
├── phpstan.neon             # PHPStan static analysis config
├── .php-cs-fixer.php        # PHP-CS-Fixer code style config
├── .env                     # Environment variables (not committed)
├── Dockerfile               # Container image definition
├── docker-compose.yml       # Multi-service orchestration
├── data/
│   └── tasks.db             # SQLite database (auto-created)
├── src/
│   ├── ApiController.php    # REST controller (user-scoped)
│   ├── AppException.php     # Custom exception hierarchy
│   ├── AuthService.php      # Registration, login, JWT, profile
│   ├── Database.php         # Singleton PDO connection
│   ├── ExportService.php    # Strategy: JSON + CSV exporters (file + streaming)
│   ├── JsonResponse.php     # HTTP response helper (paginated support)
│   ├── Router.php           # HTTP router with path params
│   ├── Task.php             # Entity with Priority/Status enums, due dates
│   ├── TaskRepository.php   # Data access layer (user-scoped)
│   └── TaskService.php      # Business logic + validation + pagination
├── tests/
│   ├── Unit/
│   │   ├── TaskTest.php          # Entity, enums, factory tests
│   │   ├── TaskServiceTest.php   # Validation, CRUD, search tests
│   │   └── ExportServiceTest.php # JSON/CSV export tests
│   └── Integration/
│       └── TaskWorkflowTest.php  # End-to-end workflow tests
└── .github/
    └── workflows/
        └── ci.yml           # CI: tests, PHPStan, code style
```

## Design Patterns

| Pattern | Class | Purpose |
|---------|-------|---------|
| **Singleton** | `Database` | Single PDO connection instance |
| **Repository** | `TaskRepository` | Encapsulated data access with user-scoped queries |
| **Strategy** | `ExporterInterface` / `JsonExporter` / `CsvExporter` | Pluggable export formats (file + streaming) |
| **Dependency Injection** | `TaskService`, `ApiController`, `AuthService` | Constructor-injected dependencies |

## Requirements

- PHP 8.1+
- `pdo_sqlite` extension
- Composer (for dependency management)
- Docker (optional, for containerized deployment)

## Roadmap

- [ ] Task categories/tags
- [ ] Recurring tasks
- [ ] Team collaboration (shared task lists)
- [ ] WebSocket notifications for real-time updates
- [ ] Rate limiting on API endpoints
- [ ] OpenAPI schema validation middleware

## License

[MIT](LICENSE)
