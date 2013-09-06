<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Router;

class HttpRoute implements RouteInterface
{

    protected $specification = null;

    protected $parameterDefaults = array();
    protected $parameterValidators = array();
    protected $useImpliedTrailingSlash = true;

    protected $dispatchable = null;

    public function __construct($specification, $dispatchable, array $parameterDefaults = array(), array $parameterValidators = array(), $useImpliedTrailingSlash = true)
    {
        $this->dispatchable = $dispatchable;
        $this->specification = $specification;
        $this->parameterDefaults = $parameterDefaults;
        $this->parameterValidators = $parameterValidators;
        $this->useImpliedTrailingSlash = $useImpliedTrailingSlash;
    }

    public function getDispatchable()
    {
        return $this->dispatchable;
    }

    public function match(SourceInterface $source)
    {
        /* @var $source HttpSource */
        if (!$source instanceof HttpSource) {
            return false;
        }

        if (strpos($this->specification, ' /') !== false) {
            list ($specificationMethods, $specificationUri) = explode(' ', $this->specification);
        } else {
            $specificationUri = $this->specification;
        }

        $reqMethod = $source['method'];
        $reqUri = $source['uri'];

        if (isset($specificationMethods)) {
            $specificationMethods = explode(',', $specificationMethods);
            if (!in_array($reqMethod, $specificationMethods)) {
                return false;
            }
        }

        $specificationParts = $this->parseSpecification($specificationUri);

        // split into path+query vars
        list($reqUriPath, $reqUriQuery) = (strpos($reqUri, '?') !== false) ? explode('?', $reqUri, 2) : array($reqUri, null);

        $regex = '';

        foreach ($specificationParts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $regex .= preg_quote($part[1]);
                    break;
                case 'parameter':
                    $groupName = '?P<' . $part[1] . '>';
                    if ($part[2] === null) {
                        $regex .= '(' . $groupName . '[^/]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }
                    if (isset($part[3])) {
                        if (!isset($this->parameterValidators[$part[1]])) {
                            $this->parameterValidators[$part[1]] = array();
                        }
                        $this->parameterValidators[$part[1]][] = $part[3];
                    }
                    break;
                case 'optional-start':
                    $regex .= '(?:';
                    break;
                case 'optional-end':
                    $regex .= ')?';
                    break;
                case 'wildcard':
                    $regex .= '(?P<wildcard>.*)';
                    $isWildcard = true;
                    break;
            }
        }

        if ($this->useImpliedTrailingSlash && substr($reqUriPath, -1) !== '/' && !isset($isWildcard)) {
            $reqUriPath .= '/';
        }

        if ($this->useImpliedTrailingSlash && !isset($isWildcard)) {
            $regex .= '/?';
        }

        if (!preg_match_all('(^' . $regex . '$)', $reqUriPath, $matches)) {
            return false;
        }

        $parameters = $this->parameterDefaults;

        foreach ($matches as $parameterName => $parameterValue) {
            if (is_int($parameterName)) {
                continue;
            }

            if ($parameterValue[0] != '') {
                $parameters[$parameterName] = $parameterValue[0];
            }

        }

        // validate:
        foreach ($this->parameterValidators as $parameterName => $validators) {
            foreach ($validators as $validator) {
                if (is_string($validator) && $validator{0} == '#') {
                    if (!preg_match($validator, $parameters[$parameterName])) {
                        return false;
                    }
                }
            }
        }

        return $parameters;
    }

    public function assemble($parameters)
    {
        $path = '';
        $level = -1;
        $skip = array();
        $optional = array();

        if (strpos($this->specification, ' /') !== false) {
            list ($specificationMethods, $specificationUri) = explode(' ', $this->specification);
        } else {
            $specificationUri = $this->specification;
        }

        $specificationParts = $this->parseSpecification($specificationUri);

        foreach ($specificationParts as $part) {
            switch ($part[0]) {
                case 'literal':
                    if ($optional) {
                        $optional[$level] .= $part[1];
                    } else {
                        $path .= $part[1];
                    }
                    break;

                case 'parameter':
                    if (!isset($parameters[$part[1]])) {
                        if (!$optional) {
                            throw new \InvalidArgumentException(sprintf('Missing parameter "%s"', $part[1]));
                        } else {
                            $skip[$level] = true;
                        }
                    } elseif ($optional) {
                        $optional[$level] .= $parameters[$part[1]];
                    } else {
                        $path .= $parameters[$part[1]];
                    }
                    break;

                case 'optional-start':
                    $level++;
                    $skip[$level] = false;
                    $optional[$level] = '';
                    break;

                case 'optional-end':
                    $level--;
                    $optpath = array_pop($optional);
                    if (array_pop($skip) === false) {
                        if (isset($optional[$level])) {
                            $optional[$level] .= $optpath;
                        } else {
                            $path .= $optpath;
                        }
                    }
                    unset($optpath);
                    break;
            }
        }

        return $path;
    }

    protected function parseSpecification($specification)
    {
        // in-function static caching
        static $partsCache = array();

        if (isset($partsCache[$specification])) {
            return $partsCache[$specification];
        }

        $currentPos = 0;
        $length = strlen($specification);
        $level = 0;
        $parts = array();
        $token = -1;

        while ($currentPos < $length) {
            preg_match('(\G(?P<literal>[^\*:{\[\]]*)(?P<token>[\*:{\[\]]|$))', $specification, $matches, 0, $currentPos);

            $currentPos += strlen($matches[0]);

            if (!empty($matches['literal'])) {
                $parts[++$token] = array('literal', $matches['literal']);
            }

            if ($matches['token'] === ':') {
                if (!preg_match('(\G(?P<name>[^:/{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)', $specification, $matches, 0, $currentPos)) {
                    throw new \RuntimeException('Found empty parameter name');
                }
                $validator = null;
                if (substr($matches['name'], -1) === '#') {
                    list($matches['name'], $validator) = explode('#', $matches['name'], 2);
                    $validator = '#' . $validator;
                }
                $parts[++$token] = array('parameter', $matches['name'], isset($matches['delimiters']) ? $matches['delimiters'] : null, $validator);
                $currentPos += strlen($matches[0]);
            } elseif ($matches['token'] === '[') {
                $parts[++$token] = array('optional-start');
                $level++;
            } elseif ($matches['token'] === ']') {
                $parts[++$token] = array('optional-end');
                $level--;
                if ($level < 0) {
                    throw new \RuntimeException('Found closing bracket without matching opening bracket');
                }
            } elseif ($matches['token'] === '*') {
                $parts[++$token] = array('wildcard');
            } else {
                break;
            }
        }

        if ($level > 0) {
            throw new \RuntimeException('Found unbalanced brackets');
        }

        $partsCache[$specification] = $parts;

        return $partsCache[$specification];
    }

}