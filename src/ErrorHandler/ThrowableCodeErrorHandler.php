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

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Throwable;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Logger\Logger;
use function max;

final readonly class ThrowableCodeErrorHandler implements ErrorHandler
{
    public function __construct(
        private ErrorHandler $decoratedErrorHandler = new NullErrorHandler(),
    ) {
    }

    public function handleError(string $item, Throwable $throwable, Logger $logger): int
    {
        $exitCode = $this->decoratedErrorHandler->handleError($item, $throwable, $logger);

        // Ensures the code is at minima 1 since we do not want 0 here (as it
        // means success) and it is common for throwables to have a 0 code.
        $throwableCode = max(1, $throwable->getCode());
        Assert::natural($throwableCode);

        return $exitCode + $throwableCode;
    }
}
