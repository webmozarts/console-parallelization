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
SRC_TESTS_FILES=$(shell find src/ tests/ -type f)
COVERAGE_DIR = dist/coverage
COVERAGE_XML = $(COVERAGE_DIR)/xml
COVERAGE_JUNIT = $(COVERAGE_DIR)/phpunit.junit.xml
COVERAGE_HTML = $(COVERAGE_DIR)/html
TARGET_MSI = 100

PHP_CS_FIXER_BIN = vendor-bin/php-cs-fixer/vendor/friendsofphp/php-cs-fixer/php-cs-fixer
PHP_CS_FIXER = $(PHPNOGC) $(PHP_CS_FIXER_BIN)

PHPSTAN_BIN = vendor/phpstan/phpstan/phpstan
PHPSTAN = $(PHPSTAN_BIN)

PHPUNIT_BIN = vendor/bin/phpunit
PHPUNIT = $(PHPUNIT_BIN)
PHPUNIT_COVERAGE_INFECTION = XDEBUG_MODE=coverage $(PHPUNIT) --exclude-group autoreview --coverage-xml=$(COVERAGE_XML) --log-junit=$(COVERAGE_JUNIT)
PHPUNIT_COVERAGE_HTML = XDEBUG_MODE=coverage $(PHPUNIT) --coverage-html=$(COVERAGE_HTML)

INFECTION_BIN = vendor/bin/infection
INFECTION = $(INFECTION_BIN) --skip-initial-tests --coverage=$(COVERAGE_DIR) --only-covered --show-mutations --min-msi=$(TARGET_MSI) --min-covered-msi=$(TARGET_MSI) --ansi --threads=max
INFECTION_WITH_INITIAL_TESTS = $(INFECTION_BIN) --only-covered --show-mutations --min-msi=$(TARGET_MSI) --min-covered-msi=$(TARGET_MSI) --ansi --threads=max

RECTOR_BIN = vendor-bin/rector/vendor/bin/rector
RECTOR = $(RECTOR_BIN)


#
# Commands
#---------------------------------------------------------------------------

.PHONY: check
check: 		## Runs all the checks
check: autoreview infection

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'

.PHONY: autoreview
autoreview: 	## Runs the Auto-Review checks
autoreview: cs validate-package phpstan rector_lint phpunit_autoreview

.PHONY: cs
cs: 	 	## Fixes CS
cs: php_cs_fixer gitignore_sort composer_normalize

.PHONY: cs_lint
cs_lint:	## Lints CS
cs_lint: php_cs_fixer_lint composer_normalize_lint

.PHONY: php_cs_fixer
php_cs_fixer: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix

.PHONY: php_cs_fixer_lint
php_cs_fixer_lint: $(PHP_CS_FIXER_BIN)
	$(PHP_CS_FIXER) fix --dry-run

.PHONY: gitignore_sort
gitignore_sort:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: composer_normalize
composer_normalize:	vendor
	composer normalize

.PHONY: composer_normalize_lint
composer_normalize_lint:	vendor
	composer normalize --dry-run

.PHONY: test
test: 	 	## Runs all the tests
test: validate-package phpstan phpunit

.PHONY: phpstan
phpstan: phpstan_src phpstan_tests

.PHONY: phpstan_src
phpstan_src: $(PHPSTAN_BIN) vendor
	$(PHPSTAN) analyze --configuration phpstan-src.neon.dist

.PHONY: phpstan_tests
phpstan_tests: $(PHPSTAN_BIN) vendor
	$(PHPSTAN) analyze --configuration phpstan-tests.neon.dist

.PHONY: phpunit
phpunit: $(PHPUNIT_BIN)
	$(PHPUNIT) --testsuite=Tests --colors=always

.PHONY: phpunit_autoreview
phpunit_autoreview: $(PHPUNIT_BIN)
	$(PHPUNIT) --testsuite=AutoReview --colors=always

.PHONY: phpunit_infection
phpunit_infection: $(PHPUNIT_BIN) vendor
	$(PHPUNIT_COVERAGE_INFECTION)

.PHONY: phpunit_html
phpunit_html:	## Runs PHPUnit with code coverage with HTML report
phpunit_html: $(PHPUNIT_BIN) vendor
	$(PHPUNIT_COVERAGE_HTML)
	@echo "You can check the report by opening the file \"$(COVERAGE_HTML)/index.html\"."

.PHONY: infection
infection: $(INFECTION_BIN) vendor
	$(INFECTION_WITH_INITIAL_TESTS) --initial-tests-php-options='-dzend_extension=xdebug.so'

.PHONY: _infection
_infection: $(INFECTION_BIN) $(COVERAGE_XML) $(COVERAGE_JUNIT) vendor
	$(INFECTION)

.PHONY: validate-package
validate-package: vendor
	composer validate --strict

.PHONY: clean
clean: 	  	## Removes various temporary artifacts
clean: clear_cache clear_coverage clear_dist
	@# Silently clean up old files
	@rm -rf .php-cs-fixer.cache \
		.php_cs.cache \
		.phpunit.result.cache

.PHONY: clear_cache
clear_cache:
	rm -rf tests/Integration/**/cache || true

.PHONY: clear_coverage
clear_coverage:
	rm -rf $(COVERAGE_DIR) || true

.PHONY: clear_dist
clear_dist:
	rm -rf dist || true
	mkdir -p dist
	touch dist/.gitkeep

.PHONY: rector
rector: $(RECTOR_BIN)
	$(RECTOR)

.PHONY: rector_lint
rector_lint: $(RECTOR_BIN) dist
	$(RECTOR) --dry-run


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install
	touch -c $@

vendor: composer.lock
	composer install
	touch -c $@

$(PHP_CS_FIXER_BIN): vendor
	composer bin php-cs-fixer install
	touch -c $@

$(PHPSTAN_BIN): vendor
	touch -c $@

$(PHPUNIT_BIN): vendor
	touch -c $@

$(COVERAGE_XML): $(PHPUNIT_BIN) $(SRC_TESTS_FILES) phpunit.xml.dist
	$(MAKE) phpunit_infection
	touch -c $@
	touch -c $(COVERAGE_JUNIT)

$(COVERAGE_JUNIT): $(PHPUNIT_BIN) $(SRC_TESTS_FILES) phpunit.xml.dist
	$(MAKE) phpunit_infection
	touch -c $@
	touch -c $(COVERAGE_XML)

$(INFECTION_BIN): vendor
	touch -c $@

$(RECTOR_BIN): vendor
	composer bin rector install
	touch -c $@
