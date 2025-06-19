COMPOSE=docker-compose
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

up:
	@${COMPOSE} up -d

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

encore_dev:
	@yarn encore dev

encore_prod:
	@ yarn encore production

phpunit-dox:
	@${PHP} bin/phpunit --testdox

phpunit:
	@${PHP} bin/phpunit

phpunit-clean:
	@${PHP} bin/phpunit --no-output

phpunit-brief:
	@${PHP} bin/phpunit --testdox-html=/dev/null

git-reset:
	@git reset --hard
	@git clean -fd

# В файл local.mk можно добавлять дополнительные make-команды,
# которые требуются лично вам, но не нужны на проекте в целом
-include local.mk
