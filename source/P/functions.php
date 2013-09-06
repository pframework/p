<?php

namespace P;

/**
 * get_callable() is used to retrieve a PHP callable from a specification.
 * 
 * P framework allows callables to be anything that passes is_callable()
 * or anything in the form "Class->method"
 *
 * @param callable|string $specification
 * @param array|\ArrayAccess $parameters
 * @return \callable
 */
function get_callable($specification, $parameters = array()) {
    if (is_callable($specification)) {
        return $specification;
    } elseif (is_instantiable($specification)) {
        return instantiate($specification, $parameters);
    }
    throw new \InvalidArgumentException('Provided specification cannot become callable');
}

/**
 * is_callableish() - is it callable or form of Class->method?
 * @param callable|string $specification
 * @return bool
 */
function is_callish($specification) {
    if (is_callable($specification)) {
        return true;
    }
    return (bool) strpos($specification, '->');
}

function is_instantiable($specification, &$class = null, &$method = null) {
    if (strpos($specification, '->')) {
        list($class, $method) = preg_split('#->#', $specification, 2);        
    } else {
        $class = $specification;
    }
    return class_exists($class, true);
}

/**
 * instantiate() - given Class->method, instantiate this Class
 * and return a callable, if there is a contructor, parameter
 * matching will be preformed.
 *
 * @param string $instantiator
 * @param array|\ArrayAccess $parameters
 * @return \callable
 */
function instantiate($instantiator, $parameters = array()) {
    // do replacements on class and method
    if (strpos($instantiator, '{') !== false) {
        $instantiator = get_instantiator_with_replacements($instantiator, $parameters);
    }

    $c = $m = null;
    if (!is_instantiable($instantiator, $c, $m)) {
        throw new \InvalidArgumentException('Provided instantiator does not look like a valid instantiator');
    }

    $a = get_matched_arguments(array($c, '__construct'), $parameters);

    // switch to avoid Reflection in most common use cases
    switch (count($a)) {
        case 0: $o = new $c();
        case 1: $o = new $c($a[0]);
        case 2: $o = new $c($a[0], $a[1]);
        case 3: $o = new $c($a[0], $a[1], $a[2]);
        case 4: $o = new $c($a[0], $a[1], $a[2], $a[3]);
        default:
            $r = new \ReflectionClass($c);
            $o = $r->newInstanceArgs($a);
    }
    
    return ($m) ? array($o, $m) : $o;
}

/**
 * get_instantiator_with_replacements() Substitute named parameters where
 * they exist within replacement curly brackes
 * 
 * @param string $instantiator
 * @param array|\ArrayAccess $parameters
 * @return string
 */
function get_instantiator_with_replacements($instantiator, $parameters) {
    while (preg_match('#{([^}]+)}#', $instantiator, $subMatches)) {
        if (!isset($callTimeArguments[$subMatches[1]])) {
            throw new \RuntimeException('Cannot substitute ' . $subMatches[1]);
        }
        if (!is_scalar($callTimeArguments[$subMatches[1]])) {
            throw new \RuntimeException($subMatches[0] . ' replacement found but is not a scalar');
        }
        $instantiator = str_replace($subMatches[0], (string) $callTimeArguments[$subMatches[1]], $instantiator);
    }
    return $instantiator;
}

/**
 * invoke() - given a callable, call callable with parameter matching
 *
 * @param \callable $callable
 * @param array|\ArrayAccess $parameters
 * @param object Used for scope of Closure callables
 * @return \callable
 */
function invoke($callable, $parameters, $closureScope = null) {
    $a = get_matched_arguments($callable, $parameters);
    if ($closureScope && $callable instanceof \Closure
        && is_object($closureScope)
        && version_compare(PHP_VERSION, '5.4.0', '>=')) {
        $callable = $callable->bindTo($closureScope, get_class($closureScope));
    }
    switch (count($a)) {
        case 0: return $callable();
        case 1: return $callable($a[0]);
        case 2: return $callable($a[0], $a[1]);
        case 3: return $callable($a[0], $a[1], $a[2]);
        case 4: return $callable($a[0], $a[1], $a[2], $a[3]);
        default: return call_user_func_array($callable, $a);
    }
}

/**
 * get_matched_arguments() - given a callable, call callable with parameter matching
 *
 * @param \callable $callable
 * @param array|\ArrayAccess $parameters
 * @param object Used for scope of Closure callables
 * @return \callable
 */
function get_matched_arguments($callable, $parameters) {

    if (!is_array($parameters) && !$parameters instanceof \ArrayAccess) {
        throw new \InvalidArgumentException('$arguments for ' . __CLASS__ . ' must be array or ArrayAccess');
    }

    if (is_string($callable) || $callable instanceof \Closure) {
        $r = new \ReflectionFunction($callable);
        $rps = $r->getParameters();
    } elseif (is_array($callable) && count($callable) == 2) {
        $r = (is_string($callable[0])) ? new \ReflectionClass($callable[0]) : new \ReflectionObject($callable[0]);
        $method = strtolower($callable[1]);
        if ($r->hasMethod($method)) {
            $rps = $r->getMethod($method)->getParameters();
        } elseif ($r->hasMethod('__call')) {
            return array_values($parameters);
        } else {
            return array();
        }
    } else {
        throw new \Exception('Unknown callable');
    }

    $matchedArgs = array();

    foreach ($rps as $rp) {
        $paramName = $rp->getName();

        if (isset($parameters[$paramName])) {
            // call-time arguments get priority
            $matchedArgs[] = $parameters[$paramName];
        } elseif ($rp->isOptional()) {
            // use default specified by method signature
            $matchedArgs[] = $rp->getDefaultValue();
        } else {
            // otherwise, null
            $matchedArgs[] = null;
        }
    }
    return $matchedArgs;
}
