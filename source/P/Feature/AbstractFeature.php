<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P\Feature;

use P\Application;

abstract class AbstractFeature
{
    public function getConfiguration()
    {
        return array();
    }

    public function getServices()
    {
        return array();
    }

    public function getCallbacks()
    {
        return array();
    }

    public function getRoutes()
    {
        return array();
    }

    public function register(Application $application)
    {
        $serviceLocator = $application->getServiceLocator();
        
        // configuration
        $configuration = $serviceLocator->get('Configuration');
        $configuration->merge($this->getConfiguration());
        
        // services
        foreach ($this->getServices() as $name => $service) {
            $serviceLocator->set($name, $service);
        }
        
        // routes
        $routeStack = $serviceLocator->get('Router')->getRouteStack();
        foreach ($this->getRoutes() as $routeName => $route) {
            $routeStack[$routeName] = $route;
        }
        
        // application callbacks
        foreach ($this->getCallbacks() as $callback) {
            $application->on($callback[0], $callback[1], (isset($callback[2]) ? $callback[2] : 0));
        }
    }

}