PHPUNIT=php ./vendor/bin/phpunit
BEHAT=./vendor/bin/behat

all: help

help:
	@echo "Usage:"
	@echo "    make test             # run the tests"
	@echo "    make composer-install # install locally composer"

composer-install: composer.phar
	php composer.phar --no-ansi --no-interaction -dev install

composer.phar:
	curl -s -z composer.phar -o composer.phar http://getcomposer.org/composer.phar

test:
	${BEHAT}

.PHONY: test all help
