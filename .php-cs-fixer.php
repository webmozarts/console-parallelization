<?php declare(strict_types=1);

/*
 * This file is part of the Fidry\Console package.
 *
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Fidry\PhpCsFixerConfig\FidryConfig;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->append([
        '.github/fix-symfony-versions',
        'set-composer-conflicts.php',
    ])
    ->exclude([
        'dist',
        'tests/Integration/App/var',
    ]);

$config = new FidryConfig(
// The header comment used
    <<<'EOF'
        This file is part of the Webmozarts Console Parallelization package.

        (c) Webmozarts GmbH <office@webmozarts.com>

        For the full copyright and license information, please view the LICENSE
        file that was distributed with this source code.
        EOF,
    // The min PHP version supported (best to align with your composer.json)
    81_000,
);
$config->setCacheFile(__DIR__.'/dist/.php-cs-fixer.cache');
$config->setRules(
    array_merge(
        $config->getRules(),
        [
            'mb_str_functions' => false,
        ],
    ),
);

return $config->setFinder($finder);
