<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Router;

class CliRoute implements RouteInterface
{
    const PART_WORD = 'word';
    const PART_PARAMETER = 'parameter';
    const PART_OPTION = 'option';

    protected $specificationParts = array();
    protected $parameterDefaults = array();
    protected $parameterValidators = array();

    public function __construct($specification, array $parameterDefaults = array(), array $parameterValidators = array())
    {
        $this->parseSpecification($specification);
        $this->parameterDefaults = $parameterDefaults;
        $this->parameterValidators = $parameterValidators;
    }

    public function getDispatchable()
    {
        return $this->dispatchable;
    }

    public function match($source)
    {
        if (!$source instanceof CliSource) {
            return false;
        }

        if (count($this->specificationParts) == 0) {
            return array();
        }

        $argvParts = $source->getArguments();
        $curArgvPart = array_shift($argvParts);

        $parameters = array();

        foreach ($this->specificationParts as $i => $specPart) {

            switch ($specPart[0]) {

                case self::PART_WORD:
                    if ($specPart[1] != $curArgvPart) {
                        return false;
                    } else {
                        $curArgvPart = array_shift($argvParts);
                        continue;
                    }
                    break;

                case self::PART_PARAMETER:

                    $parameterName = ltrim(rtrim($specPart[1], '?'), ':');
                    $parameters[$parameterName] = null;

                    if ($curArgvPart == '' && substr($specPart[1], -1) != '?') {
                        return false;
                    }

                    $parameters[$parameterName] = $curArgvPart;
                    $curArgvPart = array_shift($argvParts);
                    break;

                case self::PART_OPTION:
                    $optionName = substr($specPart[1], 1);
                    $parameters[$optionName] = array();
                    while ($curArgvPart{0} == '-') {
                        $parameters[$optionName][] = $curArgvPart;
                        $curArgvPart = array_shift($argvParts);
                    }
                    break;

            }

        }

        return $parameters;
    }

    public function assemble($parameters)
    {
        // @todo
        return '';
    }

    protected function parseSpecification($specification)
    {
        if ($specification == '') {
            return;
        }
        $parts = explode(' ', $specification);
        foreach ($parts as $i => $v) {
            $type = self::PART_WORD;
            if ($v{0} == ':') {
                $type = self::PART_PARAMETER;
            } elseif ($v{0} == '-') {
                $type = self::PART_OPTION;
            }
            $parts[$i] = array($type, $v);
        }
        $this->specificationParts = $parts;
    }
}
