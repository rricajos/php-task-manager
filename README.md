# PHP Task Manager

[![CI](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/rricajos/php-task-manager/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests](https://img.shields.io/badge/tests-93%20passed-brightgreen)](https://github.com/rricajos/php-task-manager/actions)

A task manager with **CLI** and **REST API** interfaces, built with PHP 8.x, SQLite, JWT authentication, and design patterns. No external dependencies beyond PHPUnit for testing.

## Features

- **Two interfaces**: Interactive CLI menu + REST API with JWT auth
- **PHP 8.x**: Enums, readonly, constructor promotion, match, named arguments, union types
- **Design patterns**: Singleton, Repository, Strategy, Dependency Injection
- **Security**: JWT (HMAC-SHA256), bcrypt password hashing, prepared statements
- **Testing**: 93 PHPUnit tests (unit + integration)
- **CI/CD**: GitHub Actions with PHP 8.1/8.2/8.3 matrix
- **API docs**: OpenAPI 3.0 specification included

## Quick Start

```bash
git clone https://github.com/rricajos/php-task-manager.git
cd php-task-manager
composer install
```

### CLI

```bash
php app.php
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
  -d '{"title": "Learn PHP 8", "priority": "alta"}'
```

### Tests

```bash
composer test
```

## API Endpoints

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `POST` | `/auth/register` | Register user | No |
| `POST` | `/auth/login` | Login (get JWT) | No |
| `GET` | `/tasks` | List tasks (`?status=pendiente\|completada`) | Yes |
| `GET` | `/tasks/{id}` | Get task by ID | Yes |
| `POST` | `/tasks` | Create task | Yes |
| `PATCH` | `/tasks/{id}/complete` | Mark as completed | Yes |
| `DELETE` | `/tasks/{id}` | Delete task | Yes |
| `GET` | `/tasks/search?q=keyword` | Search tasks | Yes |
| `GET` | `/tasks/stats` | Statistics | Yes |
| `GET` | `/tasks/export?format=json\|csv` | Export tasks | Yes |

Full API documentation available in [openapi.yaml](openapi.yaml) — view it in [Swagger Editor](https://editor.swagger.io/).

## Architecture

```
php-task-manager/
├── app.php                  # CLI entry point
├── api.php                  # REST API entry point
├── openapi.yaml             # OpenAPI 3.0 spec
├── composer.json
├── phpunit.xml
├── data/
│   └── tasks.db             # SQLite database (auto-created)
├── src/
│   ├── ApiController.php    # REST controller
│   ├── AppException.php     # Custom exception hierarchy
│   ├── AuthService.php      # Registration, login, JWT
│   ├── Database.php         # Singleton PDO connection
│   ├── ExportService.php    # Strategy: JSON + CSV exporters
│   ├── JsonResponse.php     # HTTP response helper
│   ├── Router.php           # HTTP router with path params
│   ├── Task.php             # Entity with Priority/Status enums
│   ├── TaskRepository.php   # Data access layer
│   └── TaskService.php      # Business logic + validation
└── tests/
    ├── Unit/
    │   ├── TaskTest.php          # 25 tests: entity, enums, factory
    │   ├── TaskServiceTest.php   # 29 tests: validation, CRUD, search
    │   └── ExportServiceTest.php # 22 tests: JSON/CSV export
    └── Integration/
        └── TaskWorkflowTest.php  # 17 tests: end-to-end workflows
```

## Design Patterns

| Pattern | Class | Purpose |
|---------|-------|---------|
| **Singleton** | `Database` | Single PDO connection instance |
| **Repository** | `TaskRepository` | Encapsulated data access |
| **Strategy** | `ExporterInterface` / `JsonExporter` / `CsvExporter` | Pluggable export formats |
| **Dependency Injection** | `TaskService`, `ApiController` | Constructor-injected dependencies |

## Requirements

- PHP 8.1+
- `pdo_sqlite` extension

## License

[MIT](LICENSE)
