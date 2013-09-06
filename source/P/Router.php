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
 * @property $routes Router\RouteStack
 */
class Router
{
    const ASSEMBLE_USE_LAST_ROUTE_MATCH = 'useLastRouteMatch';

    /**
     * @var Router\RouteStack
     */
    protected $routeStack = null;
    protected $source = null;
    protected $routeMatchPrototype = null;
    protected $lastRouteMatch = null;

    /**
     * @param array|Router\RouteStack $routes
     * @param $source
     * @param Router\RouteMatch $routeMatchPrototype
     */
    public function __construct($routes = array(), Router\SourceInterface $source = null, Router\RouteMatch $routeMatchPrototype = null)
    {
        if ($routes instanceof Router\RouteStack) {
            $this->routeStack = $routes;
        } else {
            $this->routeStack = new Router\RouteStack($routes);
        }

        $this->setSource(($source) ?: (php_sapi_name() == 'cli') ? new Router\CliSource : new Router\HttpSource);
        $this->routeMatchPrototype = ($routeMatchPrototype) ?: new Router\RouteMatch;
    }

    public function getRouteStack()
    {
        return $this->routeStack;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setSource(Router\SourceInterface $source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @return Router\RouteMatch|null
     */
    public function getLastRouteMatch()
    {
        return $this->lastRouteMatch;
    }

    public function setLastRouteMatch($lastRouteMatch)
    {
        $this->lastRouteMatch = $lastRouteMatch;
        return $this;
    }

    public function route()
    {
        /** @var $route Router\RouteInterface */
        foreach ($this->routeStack as $name => $route) {
            $parameters = $route->match($this->source);
            if ($parameters !== false) {
                $routeMatch = clone $this->routeMatchPrototype;
                $routeMatch->setName($name);
                $routeMatch->setRoute($route);
                $routeMatch->setParameters($parameters);
                $this->setLastRouteMatch($routeMatch);
                return $routeMatch;
            }
        }

        return false;
    }

    public function match($routeName, Router\SourceInterface $source)
    {
        /** @var $route Router\RouteInterface */
        $route = $this->routeStack[$routeName];
        return $route->match($source);
    }

    public function assembleMatch($parameters = array())
    {
        return $this->assemble(self::ASSEMBLE_USE_LAST_ROUTE_MATCH, $parameters);
    }

    public function assemble($routeName, array $parameters = array())
    {
        if ($routeName == self::ASSEMBLE_USE_LAST_ROUTE_MATCH) {
            $routeName = $this->getLastRouteMatch()->getName();
        }

        /** @var $route Router\RouteInterface */
        $route = $this->routeStack[$routeName];
        return $route->assemble($parameters);
    }

    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'routematch':
            case 'lastroutematch':
                return $this->lastRouteMatch;
            case 'routes':
                return $this->routeStack;
            case 'routestack':
                return $this->routeStack;
        }
        throw new \InvalidArgumentException(
            $name . ' is not a valid magic property.'
        );
    }

}
