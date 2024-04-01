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
use PHPUnit\Framework\Attributes\CoversNothing;
use function file_get_contents;

/**
 * @internal
 */
#[CoversNothing]
final class MakefileTest extends BaseMakefileTestCase
{
    private const EXPECTED_OUTPUT_FILE_PATH = __DIR__.'/makefile_help_output';

    protected static function getMakefilePath(): string
    {
        return __DIR__.'/../../Makefile';
    }

    protected function getExpectedHelpOutput(): string
    {
        return file_get_contents(self::EXPECTED_OUTPUT_FILE_PATH);
    }
}
