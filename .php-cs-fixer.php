<?php declare(strict_types=1);

/*
 * This file is part of the Fidry\Console package.
 *
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PHPUnit75Migration:risky' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'array_syntax' => false,
        'backtick_to_shell_exec' => true,
        'blank_line_between_import_groups' => false,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'combine_nested_dirname' => true,
        'compact_nullable_typehint' => true,
        'declare_strict_types' => true,
        'dir_constant' => true,
        'echo_tag_syntax' => [
            'format' => 'short',
        ],
        'ereg_to_preg' => true,
        'fopen_flag_order' => true,
        'fopen_flags' => true,
        'fully_qualified_strict_types' => true,
        'general_phpdoc_annotation_remove' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'header_comment' => [
            'header' => <<<EOF
                This file is part of the Fidry\Console package.

                (c) Théo FIDRY <theo.fidry@gmail.com>

                For the full copyright and license information, please view the LICENSE
                file that was distributed with this source code.
                EOF,
            'location' => 'after_open',
        ],
        'is_null' => true,
        'list_syntax' => [
            'syntax' => 'short',
        ],
        'logical_operators' => true,
        'mb_str_functions' => true,
        'no_superfluous_phpdoc_tags' => [
            'remove_inheritdoc' => true,
            // Required for Psalm
            'allow_mixed' => true,
        ],
        'modernize_types_casting' => true,
        'multiline_comment_opening_closing' => true,
        'no_alternative_syntax' => true,
        'no_binary_string' => true,
        'no_homoglyph_names' => true,
        'no_php4_constructor' => true,
        'no_superfluous_elseif' => true,
        'no_unset_cast' => true,
        'no_unset_on_property' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_order_by_value' => true,
        'phpdoc_separation' => false,
        'phpdoc_to_comment' => false,
        // Exclude "meta" which renames "Resource" to "resource"
        'phpdoc_types' => ['groups' => ['simple', 'alias']],
        'php_unit_construct' => true,
        'php_unit_method_casing' => [
            'case' => 'snake_case',
        ],
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'self'
        ],
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_order' => true,
        'phpdoc_types_order' => false,
        'phpdoc_var_annotation_correct_order' => true,
        'pow_to_exponentiation' => true,
        'protected_to_private' => true,
        'self_static_accessor' => true,
        'single_line_throw' => false,
        'single_trait_insert_per_statement' => false,
// TODO: enable once we are on 8.1 min
//        'trailing_comma_in_multiline' => [
//            'after_heredoc' => true,
//            'elements' => ['arrays', 'arguments', 'parameters'],
//        ],
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__)
            ->exclude([
                'dist',
                'tests/Integration/var/cache/',
                'tests/Integration/var/logs/',
            ]),
    );
