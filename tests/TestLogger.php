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

use function fclose;
use function fopen;
use function fwrite;
use const PHP_EOL;
use function unlink;

final class TestLogger
{
    private const FILE_NAME = __DIR__.'/output';

    /**
     * @var resource
     */
    private $outputHandler;

    public function __destruct()
    {
        if (isset($this->outputHandler)) {
            fclose($this->outputHandler);
        }
    }

    public static function clearLogfile(): void
    {
        @unlink(self::FILE_NAME);
    }

    public function recordFirstCommand(): void
    {
        $this->write(__METHOD__);
    }

    private function write(string $message): void
    {
        if (!isset($this->outputHandler)) {
            $this->outputHandler = fopen(
                self::FILE_NAME,
                'ab',
            );
        }

        fwrite($this->outputHandler, $message.PHP_EOL);
    }
}
