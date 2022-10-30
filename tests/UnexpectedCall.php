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

namespace Webmozarts\Console\Parallelization;

use DomainException;
use function sprintf;

final class UnexpectedCall extends DomainException
{
    public static function forMethod($method): self
    {
        return new self(
            sprintf(
                'Did not expect "%s" to be called.',
                $method,
            ),
        );
    }
}
