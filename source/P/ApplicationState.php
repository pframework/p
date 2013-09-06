<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P;

class ApplicationState implements \ArrayAccess
{
    const NULLIFY_RESULT = '__nullify_result__';

    protected $serviceLocator;
    protected $previousScopes = array();
    protected $scopes = array();
    protected $scopeParameters = array();
    protected $scopeResults = null;

    /**
     * @param array|\ArrayAccess $args1
     * @param array|\ArrayAccess $args2
     * @throws \InvalidArgumentException
     */
    public function __construct(ServiceLocator $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function pushScope($scope, $parameters = array())
    {
        array_unshift($this->scopes, $scope);
        array_unshift($this->scopeParameters, $parameters);
        return $this;
    }

    public function popScope()
    {
        // remove off current scope
        array_shift($this->scopeParameters);
        $scope = array_shift($this->scopes);

        // mark as previous
        array_unshift($this->previousScopes, $scope);
        return $scope;
    }

    public function getScope()
    {
        return (isset($this->scopes[0])) ? $this->scopes[0] : null;
    }

    public function getScopeParameters()
    {
        return $this->scopeParameters[0];
    }

    public function getScopes()
    {
        return $this->scopes;
    }

    public function hasPreviousScope($name)
    {
        return in_array($name, $this->previousScopes);
    }

    public function getPreviousScopes()
    {
        return $this->previousScopes;
    }

    public function setResult($result)
    {
        $this->scopeResults[$this->getScope()] = $result;
    }

    public function getResult($scope = null)
    {
        if ($scope == null) {
            $scope = $this->getScope();
        }
        if (isset($this->scopeResults[$scope])) {
            return $this->scopeResults[$scope];            
        }
        return null;
    }

    public function offsetExists($offset)
    {
        foreach ($this->scopeParameters as $args) {
            if (isset($args[$offset])) {
                return true;
            }
        }
        if (isset($this->serviceLocator[$offset])) {
            return true;
        }
        return false;
    }

    public function offsetGet($offset)
    {
        foreach ($this->scopeParameters as $args) {
            if (isset($args[$offset])) {
                return $args[$offset];
            }
        }
        if (isset($this->serviceLocator[$offset])) {
            return $this->serviceLocator[$offset];
        }
        return false;
    }

    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException(__METHOD__ . ' is not supported by ' . __CLASS__);
    }

    public function offsetUnset($offset)
    {
        throw new \RuntimeException(__METHOD__ . ' is not supported by ' . __CLASS__);
    }

}
