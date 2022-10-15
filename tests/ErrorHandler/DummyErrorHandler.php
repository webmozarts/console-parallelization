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
use Webmozarts\Console\Parallelization\Logger\Logger;
use function func_get_args;

final class DummyErrorHandler implements ErrorHandler
{
    /**
     * @var list<array{string, Throwable, Logger}>
     */
    public array $calls = [];

    public function handleError(string $item, Throwable $throwable, Logger $logger): void
    {
        $this->calls[] = func_get_args();
    }
}
