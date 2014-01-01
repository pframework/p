<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P;

class ApplicationContext
{
    protected $configuration = [];
    protected $callbacks = [];
    protected $routes = [];
    protected $services = [];

    public function __construct(array $configuration = array())
    {
        foreach ($configuration as $section => $c) {
            switch ($section) {
                case 'route':
                case 'routes':
                    $this->setRoutes($c);
                    break;
                case 'service':
                case 'services':
                    $this->setServices($c);
                    break;
                case 'config':
                case 'configuration':
                    $this->setConfiguration($c);
                    break;
                case 'callback':
                case 'callbacks':
                    $this->setCallbacks($c);
                    break;
            }
        }
    }

    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function setCallbacks($callbacks)
    {
        $this->callbacks = $callbacks;
        return $this;
    }

    public function getCallbacks()
    {
        return $this->callbacks;
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
        return $this;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setServices($services)
    {
        $this->services = $services;
        return $this;
    }

    public function getServices()
    {
        return $this->services;
    }
}
