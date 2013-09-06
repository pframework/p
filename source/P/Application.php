<?php
/**
 * P Framework
 * @link http://github.com/pframework
 * @license UNLICENSE http://unlicense.org/UNLICENSE
 * @copyright Public Domain
 * @author Ralph Schindler <ralph@ralphschindler.com>
 */

namespace P;

if (!function_exists('P\invoke')) {
    include 'functions.php'; // likely should have been loaded by composer, just sayin'
}

/**
 * @property Dispatcher $dispatcher
 * @property Router $router
 * @property ServiceLocator $serviceLocator
 * @property ServiceLocator $services
 */
class Application implements \ArrayAccess
{
    const ERROR_UNROUTABLE = '__unroutable__';
    const ERROR_UNDISPATCHABLE = '__undispatchable__';
    const ERROR_EXCEPTION = '__exception__';

    /** @var ApplicationState */
    protected $applicationState = null;

    /** @var ServiceLocator */
    protected $serviceLocator = null;

    /** @var \SplPriorityQueue[] */
    protected $callbacks = array();

    public function __construct($configuration = array())
    {
        if (is_array($configuration)) {
            $configuration = new Configuration($configuration);
        } elseif (!$configuration instanceof Configuration) {
            throw new \InvalidArgumentException('An array or Configuration object is required');
        }

        $this->bootstrapBaseServices(array('Configuration' => $configuration));
    }

    protected function bootstrapBaseServices(array $services)
    {
        $sl = new ServiceLocator();
        $this->serviceLocator = $sl;

        $this->applicationState = new ApplicationState($sl);

        if ($services) {
            foreach ($services as $n => $s) {
                $sl->set($n, $s);
            }
        }

        // router
        $router = new Router(new Router\RouteStack);
        $sl->set('Router', $router);

        $routerSource = $router->getSource();
        $sl->set('RouterSource', $routerSource);

        // register source as
        if ($routerSource instanceof Router\HttpSource) {
            $sl->set('HttpSource', $routerSource);
        }
        if ($routerSource instanceof Router\CliSource) {
            $sl->set('CliSource', $routerSource);
        }

        // base problem handler, it is replacable
        // $sl->set('ProblemHandler', array($this, 'problemHandlerCallback'), true);

        $sl->set('Application', $this);
        $sl->set('ApplicationState', $this->applicationState);
        $sl->set('ServiceLocator', $sl);
    }

    /**
     * @return void
     */
    public function initialize()
    {
        if ($this->applicationState->hasPreviousScope('Application.Initialize')) {
            return;
        }

        $this->trigger('Application.Initialize');
        return $this;
    }

    /**
     * @return mixed|null
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function run()
    {
        $this->initialize();

        /** @var $router Router */
        $router = $this->serviceLocator->get('Router');

        $this->trigger('Application.PreRoute');

        try {
            $routeMatch = $router->route();
            $this->serviceLocator->set('RouteMatch', $routeMatch, true);
        } catch (\Exception $e) {
            return $this->handleProblem(self::ERROR_UNROUTABLE, $e);
        }

        $this->trigger('Application.PostRoute');

        if ($routeMatch == null) {
            /** @var $router Router */
            $router = $this->serviceLocator->get('Router');
            $routeMatch = $router->getLastRouteMatch();

            if (!$routeMatch) {
                return $this->handleProblem(self::ERROR_UNROUTABLE);
            }

        } elseif (!$routeMatch instanceof Router\RouteMatch) {
            throw new \InvalidArgumentException('Provided RouteMatch must be of type P\Router\RouteMatch');
        }

        $route = $routeMatch->getRoute();

        if (!$route instanceof Router\RouteInterface) {
            throw new \RuntimeException('Matched route must implement MiniP\ApplicationRoute\ApplicationRouteInterface');
        }

        $this->trigger('Application.PreDispatch');

        try {
            $routeSource = $router->getSource();
            $dispatchParams = $routeMatch->getParameters();
            if ($routeSource instanceof Router\HttpSource) {
                $dispatchParams['HttpUri'] = $routeSource['uri'];
                $dispatchParams['HttpMethod'] = $routeSource['method'];
            }
            $this->applicationState->pushScope('Application.Dispatch', $dispatchParams);
            $result = invoke($route->getDispatchable(), $this->applicationState);
            $this->applicationState->setResult($result);
        } catch (\Exception $e) {
            $this->handleProblem(self::ERROR_UNDISPATCHABLE, $e);
        }

        $this->trigger('Application.PostDispatch');
    }

    public function on($scopeIdentifier, $callback, $priority = 0)
    {
        if (!isset($this->callbacks[$scopeIdentifier])) {
            $this->callbacks[$scopeIdentifier] = new \SplPriorityQueue();
        }
        $this->callbacks[$scopeIdentifier]->insert($callback, $priority);
        return $this;
    }

    public function trigger($scopeIdentifier, $parameters = array())
    {
        $this->applicationState->pushScope($scopeIdentifier, $parameters);
        if (!isset($this->callbacks[$scopeIdentifier])) {
            $this->applicationState->popScope();
            return;
        }
        foreach (clone $this->callbacks[$scopeIdentifier] as $callback) {
            $result = invoke($callback, $this->applicationState);
            if ($result == ApplicationState::NULLIFY_RESULT) {
                $this->applicationState->setResult(null);
            } elseif (!is_null($result)) {
                $this->applicationState->setResult($result);
            }
        }
        $this->applicationState->popScope();
        return $this;
    }

    public function register(Feature\AbstractFeature $feature)
    {
        $feature->register($this);
        return $this;
    }

    public function addRoute($nameOrRouteSpec /*, $routeSpec */)
    {
        $args = func_get_args();
        $arg1 = (is_array($nameOrRouteSpec)) ? null : $args[0];
        if (isset($args[1])) {
            $arg2 = $args[1];
        }
        $this->serviceLocator->get('Router')->getRouteStack()->offsetSet($arg1, $arg2);
        return $this;
    }
    
    public function addService($name, $service)
    {
        $this->serviceLocator[$name] = $service;
        return $this;
    }

    /**
     * @param string|null $routeName
     * @param string|Router\RouteInterface $routeSpecification
     * @return Application|void
     */
    public function offsetSet($routeName, $routeSpecification)
    {
        $this->router->routes[$routeName] = $routeSpecification;
        return $this;
    }

    /**
     * Get A Route
     * @param mixed $routeName
     * @return Router\RouteInterface
     */
    public function offsetGet($routeName)
    {
        return $this->routes[$routeName];
    }

    /**
     * Does Route Exist?
     * @param mixed $routeName
     * @return bool
     */
    public function offsetExists($routeName)
    {
        return isset($this->routes[$routeName]);
    }

    /**
     * Remove a Route
     * @param mixed $routeName
     */
    public function offsetUnset($routeName)
    {
        unset($this->routes[$routeName]);
    }

    /**
     * @return ServiceLocator
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * @return mixed
     */
    public function handleProblem(/* any args */)
    {
        var_dump(func_get_args());
        exit;
    }

    /**
     * @param $name
     * @return ServiceLocator|mixed
     * @throws \InvalidArgumentException
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'services':
            case 'servicelocator':
                return $this->serviceLocator;
            case 'router':
                return $this->serviceLocator->get('Router');
            case 'routes':
                return $this->serviceLocator->get('Router')->getRouteStack();
            case 'dispatcher':
                return $this->serviceLocator->get('Dispatcher');
            default:
                if ($this->serviceLocator->has($name)) {
                    return $this->serviceLocator->get($name);
                }
        }

        throw new \InvalidArgumentException(
            $name . ' is not a valid property in the application object or a valid service in the ServiceLocator'
        );
    }

}