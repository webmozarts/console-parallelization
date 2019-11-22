<?php

/*
 * This file is part of the Webmozarts Console Parallelization package.
 *
 * (c) Webmozarts GmbH <office@webmozarts.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests'
    ])
;

return PhpCsFixer\Config::create()
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'header_comment' => [
            'header' => <<<'EOF'
This file is part of the Webmozarts Console Parallelization package.

(c) Webmozarts GmbH <office@webmozarts.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF
            ,
            'location' => 'after_open',
        ],
    ])
    ->setFinder($finder)
;
