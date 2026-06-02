.PHONY: test analyse cs-check cs-fix coverage start-api start-cli install docker-build docker-test

install:
	composer install

test:
	vendor/bin/phpunit --testdox

coverage:
	vendor/bin/phpunit --coverage-clover coverage.xml --coverage-text --coverage-html coverage-report

analyse:
	vendor/bin/phpstan analyse --level 7 --memory-limit=512M src/

cs-check:
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	vendor/bin/php-cs-fixer fix

start-api:
	php -S localhost:8080 api.php

start-cli:
	php app.php

docker-build:
	docker build -t php-task-manager .

docker-test:
	docker build -q -t php-task-manager-test . && \
	docker run --rm php-task-manager-test bash -c " \
		vendor/bin/phpunit --testdox && \
		vendor/bin/phpstan analyse --level 7 --memory-limit=512M src/"
