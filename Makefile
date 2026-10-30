.DEFAULT_GOAL := help
COMPOSE = docker compose
PHP = $(COMPOSE) exec php

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-18s %s\n", $$1, $$2}'

init: ## Copy the compose override, start containers and install dependencies
	@test -f compose.override.yml || cp compose.override.dist.yml compose.override.yml
	$(COMPOSE) up -d --wait
	$(PHP) composer install

up: ## Start containers
	$(COMPOSE) up -d

down: ## Stop containers
	$(COMPOSE) down

php-shell: ## Open a shell in the php container
	$(PHP) sh

database-init: ## (Re)create the dev and test databases of the test application, run migrations and load fixtures
	$(PHP) vendor/bin/console doctrine:database:drop --force --if-exists
	$(PHP) vendor/bin/console doctrine:database:create
	$(PHP) vendor/bin/console doctrine:migrations:migrate -n
	$(PHP) vendor/bin/console sylius:fixtures:load -n
	$(COMPOSE) exec -e APP_ENV=test php vendor/bin/console doctrine:database:drop --force --if-exists
	$(COMPOSE) exec -e APP_ENV=test php vendor/bin/console doctrine:database:create
	$(COMPOSE) exec -e APP_ENV=test php vendor/bin/console doctrine:migrations:migrate -n
	$(COMPOSE) exec -e APP_ENV=test php vendor/bin/console sylius:fixtures:load -n

frontend: ## Build the admin and shop assets of the test application (needed to open it in a browser)
	$(COMPOSE) run --rm nodejs
	$(PHP) vendor/bin/console assets:install vendor/sylius/test-application/public

test: ecs phpstan phpunit ## Run every check

ecs: ## Check coding standards
	$(PHP) vendor/bin/ecs check

phpstan: ## Static analysis
	$(PHP) vendor/bin/phpstan analyse -c phpstan.neon --no-progress

phpunit: ## Unit and integration tests
	$(PHP) vendor/bin/phpunit

.PHONY: help init up down php-shell database-init frontend test ecs phpstan phpunit
