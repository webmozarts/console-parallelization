# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules


.DEFAULT_GOAL := help


# Global variables
OS := $(shell uname)
PHPNOGC=php -d zend.enable_gc=0
CCYELLOW=\033[0;33m
CCEND=\033[0m

# PHP specific variables
PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/friendsofphp/php-cs-fixer/php-cs-fixer
PHP_CS_FIXER = $(PHPNOGC) $(PHP_CS_FIXER_BIN)
PHPSTAN_BIN = vendor/phpstan/phpstan/phpstan
PHPSTAN = $(PHPSTAN_BIN)
PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)


#
# Commands
#---------------------------------------------------------------------------

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'

.PHONY: cs
cs: 	 ## Fixes CS
cs: php_cs_fixer gitignore_sort

.PHONY: php_cs_fixer
php_cs_fixer: # Runs PHP-CS-Fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix

.PHONY: gitignore_sort
gitignore_sort:	# Sorts the .gitignore entries
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: test
test: 	 ## Runs all the tests
test: clear-cache validate-package phpstan phpunit

.PHONY: phpstan
phpstan: # Runs PHPStan
phpstan: $(PHPSTAN_BIN) vendor
ifndef SKIP_PHPSTAN
	$(PHPSTAN) analyze
endif

.PHONY: phpunit
phpunit:	# Runs PHPUnit
phpunit: $(PHPUNIT_BIN)
	$(PHPUNIT)

.PHONY: validate-package
validate-package: # Validates the Composer package
validate-package: vendor
	composer validate --strict

.PHONY: clear-cache
clear-cache: # Clears the integration test app cache
clear-cache:
	rm -rf tests/Integration/**/cache || true


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install
	touch $@

vendor: composer.lock
	composer install
	touch $@

$(PHP_CS_FIXER_BIN): vendor
	composer bin php-cs-fixer install
	touch $@

$(PHPSTAN_BIN): vendor
	touch $@

$(PHPUNIT_BIN): vendor
	touch $@
