<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P;

/**
 * P\ServiceLocator
 */
class ServiceLocator implements \ArrayAccess, \Countable
{
    /** @var mixed[] */
    protected $services = [];

    /** @var string[] */
    protected $types = [];

    /** @var bool[] */
    protected $instantiated = [];

    /** @var bool[] */
    protected $modifiables = [];

    /**
     * @var [type][method][args]
     */
    protected $arguments = [];

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return (isset($this->services[$name]));
    }

    /**
     * @param $typeHint
     * @return bool
     */
    public function hasType($typeHint)
    {
        return (isset($this->types[strtolower($typeHint)]));
    }

    /**
     * @param $name
     * @param $service
     * @param string $type (Null if actual service)
     * @param bool $modifiable
     * @return ServiceLocator
     * @throws \InvalidArgumentException
     */
    public function set($name, $service, $type = null, $modifiable = false)
    {
        if (!is_string($name) || $name == '') {
            throw new \InvalidArgumentException('$name must be a string in ServiceLocator::set()');
        }
        if (isset($this->modifiables[$name]) && $this->modifiables[$name] === false) {
            throw new \InvalidArgumentException(
                'This service ' . $name . ' is already set and can not modifiable.'
            );
        }
        // set the service
        $this->services[$name] = $service;

        // if no type provided, instance is actual service
        if ($type === null) {
            if (is_array($service)) { throw new \Exception('foo'); }
            $this->types[strtolower(get_class($service))] = $name;
            $this->instantiated[$name] = true;
        } else {
            $this->types[strtolower($type)] = $name;
        }

        $this->modifiables[$name] = $modifiable;
        return $this;
    }

    /**
     * @param $name
     * @return mixed
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function get($name)
    {
        static $depth = 0;
        static $allNames = array();
        if (!isset($this->services[$name])) {
            throw new \Exception('Service by name ' . $name . ' was not located in this ServiceLocator');
        }
        if ($depth > 99) {
            throw new \RuntimeException(
                'Recursion detected when trying to resolve these services: ' . implode(', ', $allNames)
            );
        }
        // instantiate service in this block
        if (!isset($this->instantiated[$name])) {
            $depth++;
            $allNames[] = $name;
            /** @var $factory \Closure */
            $factory = $this->services[$name];

            if (!is_callable($factory)) {
                $factory = $this->instantiate($factory);
            }

            $this->services[$name] = $service = $this->invoke($factory, $this, $this);
            if (is_object($service)) {
                $this->types[strtolower(get_class($service))] = $name;
            }
            $this->instantiated[$name] = true; // allow closure wrapped in a closure
            $depth--;
        }
        return $this->services[$name];
    }

    public function validate(array $nameExpectedTypeMap)
    {
        foreach ($nameExpectedTypeMap as $name => $expectedType) {
            $service = $this->get($name);
            switch ($expectedType) {
                case 'is_callable':
                    if (!is_callable($service)) {
                        throw new \UnexpectedValueException($name . ' was found, but was not is_callable()');
                    }
                    break;
                default:
                    if (!$service instanceof $expectedType) {
                        throw new \UnexpectedValueException($name . ' was found, but was not of type ' . $expectedType);
                    }
            }
        }
        return true;
    }

    /**
     * @param $name
     * @return ServiceLocator
     * @throws \InvalidArgumentException
     */
    public function remove($name)
    {
        if (!isset($this->services[$name])) {
            throw new \InvalidArgumentException($name . ' is not a registered service.');
        }
        if (isset($this->modifiables[$name]) && $this->modifiables[$name] === false) {
            throw new \InvalidArgumentException(
                'This service ' . $name . ' is marked as unmodifiable and therefore cannot be removed.'
            );
        }
        unset($this->services[$name], $this->modifiables[$name]);
        return $this;
    }

    /**
     * @param mixed $name
     * @param mixed $service
     * @return ServiceLocator|void
     */
    public function offsetSet($name, $service)
    {
        return $this->set($name, $service);
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * @param mixed $name
     * @return bool
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * @param mixed $name
     * @return ServiceLocator|void
     */
    public function offsetUnset($name)
    {
        return $this->remove($name);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->services);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    public function instantiate($instantiator, $parameters = array())
    {
        if (strpos($instantiator, '{') !== false) {
            while (preg_match('#{([^}]+)}#', $instantiator, $subMatches)) {
                if (!isset($parameters[$subMatches[1]])) {
                    throw new \RuntimeException('Cannot substitute ' . $subMatches[1]);
                }
                if (!is_scalar($parameters[$subMatches[1]])) {
                    throw new \RuntimeException($subMatches[0] . ' replacement found but is not a scalar');
                }
                $instantiator = str_replace($subMatches[0], (string) $parameters[$subMatches[1]], $instantiator);
            }
        }

        list($c, $m) = (strpos($instantiator, '->') !== false) ? preg_split('#->#', $instantiator, 2) : [$instantiator, null];

        if (!class_exists($c, true)) {
            throw new \InvalidArgumentException('Class in instantiator cannot be located: ' . $c);
        }

        if ($c == null) {
            throw new \InvalidArgumentException('Provided instantiator does not look like a valid instantiator');
        }

        $a = $this->matchArguments(array($c, '__construct'), $parameters);

        // switch to avoid Reflection in most common use cases
        switch (count($a)) {
            case 0: $o = new $c(); break;
            case 1: $o = new $c($a[0]); break;
            case 2: $o = new $c($a[0], $a[1]); break;
            case 3: $o = new $c($a[0], $a[1], $a[2]); break;
            case 4: $o = new $c($a[0], $a[1], $a[2], $a[3]); break;
            default:
                $r = new \ReflectionClass($c);
                $o = $r->newInstanceArgs($a);
        }

        return ($m) ? array($o, $m) : $o;
    }

    /**
     * @param $callable
     * @param array $parameters
     * @param null|array|\ArrayAccess $scope
     * @param bool $allParametersRequired
     * @return mixed
     */
    public function invoke($callable, $parameters = array(), $scope = null)
    {
        if (is_string($callable) && strpos($callable, '->') !== false) {
            $callable = $this->instantiate($callable, $parameters);
        }

        $c = ($callable instanceof \Closure && is_object($scope)) ? $callable->bindTo($scope, get_class($scope)) : $callable;
        $a = $this->matchArguments($c, $parameters);
        if (!is_callable($c)) {
            throw new \RuntimeException('The constructed callable is actually not callable');
        }
        switch (count($a)) {
            case 0: return $c();
            case 1: return $c($a[0]);
            case 2: return $c($a[0], $a[1]);
            case 3: return $c($a[0], $a[1], $a[2]);
            case 4: return $c($a[0], $a[1], $a[2], $a[3]);
            default: return call_user_func_array($c, $a);
        }
    }

    public function matchArguments($callable, $parameters)
    {
        if (!is_array($parameters) && !$parameters instanceof \ArrayAccess) {
            throw new \InvalidArgumentException('$arguments for ' . __CLASS__ . ' must be array or ArrayAccess');
        }

        if (is_string($callable) || $callable instanceof \Closure) {
            if (is_string($callable) && strpos($callable, '::') !== false) {
                $callable = explode('::', $callable);
                $r = new \ReflectionMethod($callable[0], $callable[1]);
            } else {
                $r = new \ReflectionFunction($callable);
            }
            $rps = $r->getParameters();
        } elseif (is_array($callable) && count($callable) == 2) {
            $r = (is_string($callable[0])) ? new \ReflectionClass($callable[0]) : new \ReflectionObject($callable[0]);
            $method = strtolower($callable[1]);
            if ($r->hasMethod($method)) {
                $rps = $r->getMethod($method)->getParameters();
            } elseif ($r->hasMethod('__call')) {
                return array($method, $parameters);
            } else {
                return array();
            }
        } elseif (is_object($callable) && is_callable($callable)) {
            $r = new \ReflectionMethod($callable, '__invoke');
            $rps = $r->getParameters();
        } else {
            throw new \Exception('Unknown callable ' . ((is_object($callable)) ? get_class($callable) : $callable));
        }

        $matchedArgs = array();

        foreach ($rps as $rp) {
            // check if it has a type hint, using its reflection class
            $thrc = $rp->getClass();
            if ($thrc) {
                $type = strtolower($thrc->getName());
            }

            if (isset($type) && isset($this->types[$type])) {
                $matchedArgs[] = $this->get($this->types[$type]);
                unset($type);
                continue;
            }

            // get param name
            $paramName = $rp->getName();

            if (isset($parameters[$paramName])) {
                // call-time arguments get priority
                $matchedArgs[] = $parameters[$paramName];
            } elseif ($rp->isOptional()) {
                // use default specified by method signature
                $matchedArgs[] = $rp->getDefaultValue();
            } else {
                $subject = preg_replace('#\s+#', ' ', (string) $r);
                throw new \RuntimeException('Could not find a match for ' . $rp . ' of ' . $subject);
            }
        }
        return $matchedArgs;
    }

}
