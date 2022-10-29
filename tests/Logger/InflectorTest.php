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

namespace Webmozarts\Console\Parallelization\Logger;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class InflectorTest extends TestCase
{
    /**
     * @dataProvider singularProvider
     */
    public function test_it_can_pluralize_words(
        string $singular,
        int $count,
        ?string $expected
    ): void {
        $actual = Inflector::pluralize($singular, $count);

        self::assertSame($expected, $actual);
    }

    public static function singularProvider(): iterable
    {
        $singular = 'batch';
        $plural = 'batches';

        yield 'singular; 0 count' => [
            $singular,
            0,
            $singular,
        ];

        yield 'singular; 1 count' => [
            $singular,
            1,
            $singular,
        ];

        yield 'singular; 2 count' => [
            $singular,
            2,
            $plural,
        ];

        yield 'singular; several count' => [
            $singular,
            99,
            $plural,
        ];
    }

    public function test_it_cannot_pluralize_an_unknown_word(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the singular word to be a known word from the inflector dictionary. Got "unknown".');

        Inflector::pluralize('unknown', null);
    }
}
