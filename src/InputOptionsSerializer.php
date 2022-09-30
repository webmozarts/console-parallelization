<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use Symfony\Component\Console\Input\InputInterface;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;

final class InputOptionsSerializer
{
    /**
     * @param string[] $blackListParams
     *
     * @return string[]
     */
    public function serialize(InputInterface $input, array $blackListParams): array
    {
        $options = array_diff_key(
            array_filter($input->getOptions()),
            array_fill_keys($blackListParams, ''),
        );

        $preparedOptionList = [];
        foreach ($options as $name => $value) {
            $definition = $this->getDefinition();
            $option = $definition->getOption($name);

            $optionString = '';
            if (!$option->acceptValue()) {
                $optionString .= '--'.$name;
            } elseif ($option->isArray()) {
                foreach ($value as $arrayValue) {
                    $optionString .= '--'.$name.'='.$this->quoteOptionValue($arrayValue);
                }
            } else {
                $optionString .= '--'.$name.'='.$this->quoteOptionValue($value);
            }

            $preparedOptionList[] = $optionString;
        }

        return $preparedOptionList;
    }
}
