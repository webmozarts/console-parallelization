.DEFAULT_GOAL := help

OS := $(shell uname)
PHPNOGC=php -d zend.enable_gc=0
CCYELLOW=\033[0;33m
CCEND=\033[0m

.PHONY: help
help:
	@echo "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


#
# Commands
#---------------------------------------------------------------------------

.PHONY: cs
PHP_CS_FIXER=vendor/bin/php-cs-fixer
cs:	## Fixes CS
cs: $(PHP_CS_FIXER)
	$(PHPNOGC) $(PHP_CS_FIXER) fix
	LC_ALL=C sort -u .gitignore -o .gitignore


#
# Tests
#---------------------------------------------------------------------------

PHPUNIT=vendor/bin/phpunit
test:	## Runs the tests
test: $(PHPUNIT)
	$(PHPUNIT)


#
# Rules from files
#---------------------------------------------------------------------------

composer.lock: composer.json
	composer install
	touch $@

vendor: composer.lock
	composer install
	touch $@

$(PHP_CS_FIXER): vendor
	touch $@

$(PHPUNIT): vendor
	touch $@
