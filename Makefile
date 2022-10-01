# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules


.DEFAULT_GOAL := all


# Global variables
OS := $(shell uname)
PHPNOGC=php -d zend.enable_gc=0
CCYELLOW=\033[0;33m
CCEND=\033[0m

# PHP specific variables
COVERAGE_DIR = dist/coverage-xml
TARGET_MSI = 50

PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/friendsofphp/php-cs-fixer/php-cs-fixer
PHP_CS_FIXER = $(PHPNOGC) $(PHP_CS_FIXER_BIN)
PHPSTAN_BIN = vendor/phpstan/phpstan/phpstan
PHPSTAN = $(PHPSTAN_BIN)
PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)
PHPUNIT_COVERAGE_INFECTION = XDEBUG_MODE=coverage $(PHPUNIT) --coverage-xml=$(COVERAGE_DIR)/coverage-xml --log-junit=$(COVERAGE_DIR)/phpunit.junit.xml
PHPUNIT_COVERAGE_HTML = XDEBUG_MODE=coverage $(PHPUNIT) --coverage-html=$(COVERAGE_DIR)/coverage-html
INFECTION_BIN = vendor/bin/infection
INFECTION = $(INFECTION_BIN) --skip-initial-tests --coverage=$(COVERAGE_DIR) --only-covered --show-mutations --min-msi=$(TARGET_MSI) --min-covered-msi=$(TARGET_MSI) --ansi --threads=max


#
# Commands
#---------------------------------------------------------------------------

.PHONY: all
all: cs test

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'

.PHONY: cs
cs: 	 	  ## Fixes CS
cs: php_cs_fixer gitignore_sort

.PHONY: php_cs_fixer
php_cs_fixer: 	  ## Runs PHP-CS-Fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix

.PHONY: gitignore_sort
gitignore_sort:	  ## Sorts the .gitignore entries
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: test
test: 	 	  ## Runs all the tests
test: clear-cache validate-package phpstan phpunit

.PHONY: phpstan
phpstan: 	  ## Runs PHPStan
phpstan: $(PHPSTAN_BIN) vendor
ifndef SKIP_PHPSTAN
	$(PHPSTAN) analyze
endif

.PHONY: phpunit
phpunit:	  ## Runs PHPUnit
phpunit: $(PHPUNIT_BIN)
	$(PHPUNIT)

.PHONY: phpunit_coverage_infection
phpunit_coverage_infection: ## Runs PHPUnit with code coverage for Infection
phpunit_coverage_infection: $(PHPUNIT_BIN) vendor
	$(PHPUNIT_COVERAGE_INFECTION)

.PHONY: phpunit_coverage_html
phpunit_coverage_html:	    ## Runs PHPUnit with code coverage with HTML report
phpunit_coverage_html: $(PHPUNIT_BIN) vendor
	$(PHPUNIT_COVERAGE_HTML)

.PHONY: infection
infection:	  ## Runs Infection
infection: $(INFECTION_BIN) $(COVERAGE_DIR) vendor
	if [ -d $(COVERAGE_DIR)/coverage-xml ]; then $(INFECTION); fi

.PHONY: validate-package
validate-package: ## Validates the Composer package
validate-package: vendor
	composer validate --strict

.PHONY: clear
clear: 	  	  ## Clears various artifacts
clear: clear-cache clear-coverage

.PHONY: clear-cache
clear-cache: 	  ## Clears the integration test app cache
clear-cache:
	rm -rf tests/Integration/**/cache || true

.PHONY: clear-coverage
clear-coverage:	  ## Clears the coverage reports
clear-coverage:
	rm -rf dist/phpunit* || true
	rm -rf dist/coverage* || true


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

$(COVERAGE_DIR): $(PHPUNIT_BIN) src tests phpunit.xml.dist
	$(MAKE) phpunit_coverage_infection
	$(TOUCH) "$@"

$(INFECTION_BIN): vendor
	touch $@
