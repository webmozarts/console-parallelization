<?php

/*
 * This file is part of the Webmozarts Console Parallelization package.
 *
 * (c) Webmozarts GmbH <office@webmozarts.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\AutoReview;

use Fidry\Makefile\Test\BaseMakefileTestCase;

/**
 * @coversNothing
 *
 * @internal
 */
final class MakefileTest extends BaseMakefileTestCase
{
    protected static function getMakefilePath(): string
    {
        return __DIR__.'/../../Makefile';
    }

    protected function getExpectedHelpOutput(): string
    {
        return <<<'EOF'
            [33mUsage:[0m
              make TARGET

            [32m#
            # Commands
            #---------------------------------------------------------------------------[0m

            [33mcs:[0m 	 	   Fixes CS
            [33mphp_cs_fixer:[0m 	   Runs PHP-CS-Fixer
            [33mgitignore_sort:[0m	   Sorts the .gitignore entries
            [33mcomposer_normalize:[0m   Normalizes the composer.json
            [33mtest:[0m 	 	   Runs all the tests
            [33mphpstan:[0m 	   Runs PHPStan
            [33mphpunit:[0m	   Runs PHPUnit
            [33mphpunit_coverage_infection:[0m  Runs PHPUnit with code coverage for Infection
            [33mphpunit_coverage_html:[0m	     Runs PHPUnit with code coverage with HTML report
            [33minfection:[0m	   Runs Infection
            [33mvalidate-package:[0m  Validates the Composer package
            [33mclear:[0m 	  	   Clears various artifacts
            [33mclear_cache:[0m 	   Clears the integration test app cache
            [33mclear_coverage:[0m	   Clears the coverage reports

            EOF;
    }
}
