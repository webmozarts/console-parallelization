<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use DomainException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class RawOptionsInput extends Input
{
    private function __construct(InputDefinition $definition = null)
    {
        parent::__construct($definition);
    }

    public static function getRawOptions(InputInterface $input): array
    {
        return $input->options;
    }

    protected function parse()
    {
        throw new DomainException('Not implemented.');
    }

    public function getFirstArgument()
    {
        throw new DomainException('Not implemented.');
    }

    public function hasParameterOption($values, bool $onlyParams = false)
    {
        throw new DomainException('Not implemented.');
    }

    public function getParameterOption($values, $default = false, bool $onlyParams = false)
    {
        throw new DomainException('Not implemented.');
    }
}
