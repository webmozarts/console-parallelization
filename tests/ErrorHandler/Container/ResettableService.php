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

namespace Webmozarts\Console\Parallelization\ErrorHandler\Container;

use Symfony\Contracts\Service\ResetInterface;

final class ResettableService implements ResetInterface
{
    public bool $reset = false;

    public function reset(): void
    {
        $this->reset = true;
    }
}
