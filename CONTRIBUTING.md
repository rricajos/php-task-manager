# Contributing

Thank you for your interest in this project. Contributions are welcome via pull requests.

## Requirements

- PHP 8.1+
- Composer
- Docker (recommended for running tests consistently)

## Setup

```bash
git clone https://github.com/rricajos/php-task-manager.git
cd php-task-manager
composer install
cp .env.example .env   # or create .env manually (see README)
```

## Before Submitting a PR

All of the following must pass:

```bash
composer test        # 0 test failures
composer analyse     # 0 PHPStan errors (level 7)
composer cs-check    # 0 code style issues
```

Or with Docker (no local PHP needed):

```bash
docker compose run --rm api bash -c "vendor/bin/phpunit && vendor/bin/phpstan analyse --level 7 src/"
```

## Code Style

This project uses [PHP-CS-Fixer](https://cs.symfony.com/) with the PER-CS 2.0 ruleset. Run `composer cs-fix` to auto-format before committing.

## Conventions

- **PHP 8.1+** features are preferred: enums, readonly, constructor promotion, named arguments
- All public methods must have PHPDoc with `@param`, `@return`, and `@throws` where applicable
- New features require new tests. Aim to keep or improve test coverage
- Repository methods must filter by `user_id` to maintain user isolation
- Keep the `Task` entity free of API/HTTP concerns

## Reporting Issues

Open an issue at [github.com/rricajos/php-task-manager/issues](https://github.com/rricajos/php-task-manager/issues) with steps to reproduce.
