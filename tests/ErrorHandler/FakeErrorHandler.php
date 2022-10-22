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

use DomainException;
use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class FakeErrorHandler implements ErrorHandler
{
    public function handleError(string $item, Throwable $throwable, Logger $logger): int
    {
        throw new DomainException('Unexpected call.');
    }
}
