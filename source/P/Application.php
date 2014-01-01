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
 * @property Router $router
 * @property Router\RouteStack $routes
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

    /**
     * @param array $configuration
     */
    public function __construct(/* configuration array or service locator instance */)
    {
        $arg1 = (func_num_args() > 0) ? func_get_arg(0) : null;

        $serviceLocator = ($arg1 instanceof ServiceLocator) ? $arg1 : new ServiceLocator;

        if (is_array($arg1)) {
            $serviceLocator->set('Configuration', new Configuration($arg1));
        }

        $this->bootstrapBaseServices($serviceLocator);

        if ($arg1 instanceof ApplicationContext) {
            $this->registerContext($arg1);
        }
    }

    protected function bootstrapBaseServices(ServiceLocator $sl)
    {
        $this->serviceLocator = $sl;

        // value object, never needs to be injected, can be created
        $this->applicationState = new ApplicationState($sl);

        // router
        $router = ($sl->has('Router')) ? $sl->get('Router') : ($sl->set('Router', new Router)->get('Router'));
        $routerSource = $router->getSource();
        $sl->set('RouterSource', $routerSource);

        // register source as
        if ($routerSource instanceof Router\HttpSource) {
            $sl->set('HTTPSource', $routerSource);
        }
        if ($routerSource instanceof Router\CliSource) {
            $sl->set('CLISource', $routerSource);
        }

        $sl->set('Application', $this);
        $sl->set('ApplicationState', $this->applicationState);
        $sl->set('ServiceLocator', $sl);
        
        // config file application configuration
        if ($sl->has('Configuration')) {
            $configuration = $sl->get('Configuration');
            if (isset($configuration['application']) && is_array($configuration['application'])) {
                foreach ($configuration['application'] as $n => $v) {
                    $m = null;
                    switch ($n) {
                        case 'routes': foreach ($v as $a => $b) $this->addRoute($a, $b); break;
                        case 'services': foreach ($v as $a => $b) $this->addService($a, $b); break;
                        case 'contexts': foreach ($v as $a => $b) $this->registerContext($b); break;
                        default: continue;
                    }
                }
            }
        } else {
            $sl->set('Configuration', new Configuration());
        }
    }

    /**
     * @return $this
     */
    public function initialize()
    {
        if ($this->applicationState->hasPreviousScope('Application.Initialize')) {
            return $this;
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

        // default error handling
        if (!isset($this->callbacks['Application.Error'])) {
            $this->registerContext(new Feature\BasicErrorHandler);
        }

        /** @var $router Router */
        $router = $this->serviceLocator->get('Router');

        $this->trigger('Application.PreRoute');

        try {
            $routeMatch = $router->route();
            $this->serviceLocator->set('RouteMatch', $routeMatch, true);
        } catch (\Exception $e) {
            return $this->trigger('Application.Error', array('type' => self::ERROR_EXCEPTION, 'exception' => $e));
        }

        $this->trigger('Application.PostRoute');

        if ($routeMatch == null) {
            /** @var $router Router */
            $router = $this->serviceLocator->get('Router');
            $routeMatch = $router->getLastRouteMatch();

            if (!$routeMatch) {
                return $this->trigger('Application.Error', array('type' => self::ERROR_UNROUTABLE));
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
            $dispatchParams = $routeMatch->getParameters();
            $routeSource = $router->getSource();
            if ($routeSource instanceof Router\HttpSource) {
                $dispatchParams['HttpUri'] = $routeSource['uri'];
                $dispatchParams['HttpMethod'] = $routeSource['method'];
            }
            $this->applicationState->pushScope('Application.Dispatch', $dispatchParams);
            $callable = $route->getDispatchable();
            $result = $this->serviceLocator->invoke($callable, $this->applicationState, null, false);
            $this->applicationState->setResult($result);
        } catch (\Exception $e) {
            $this->trigger('Application.Error', array('type' => self::ERROR_EXCEPTION, 'exception' => $e));
        } finally {
            $this->applicationState->popScope();
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
            return $this;
        }
        foreach (clone $this->callbacks[$scopeIdentifier] as $callback) {
            $result = $this->serviceLocator->invoke($callback, $this->applicationState);
            if ($result == ApplicationState::NULLIFY_RESULT) {
                $this->applicationState->setResult(null);
            } elseif (!is_null($result)) {
                $this->applicationState->setResult($result);
            }
        }
        $this->applicationState->popScope();
        return $this;
    }

    public function registerContext($context)
    {
        if (is_array($context)) {
            $context = new ApplicationContext($context);
        } elseif (!$context instanceof ApplicationContext) {
            throw new \InvalidArgumentException('Context but be an array or P\ApplicationContext object');
        }

        // configuration
        $configuration = $this->serviceLocator->get('Configuration');
        $configuration->merge($context->getConfiguration());

        // services
        foreach ($context->getServices() as $name => $args) {
            if (is_object($args)) {
                $this->serviceLocator->set($name, $args);
            } else {
                $this->serviceLocator->set($name, $args[0], (isset($args[1]) ? $args[1] : null));
            }

        }

        // routes
        $routeStack = $this->serviceLocator->get('Router')->getRouteStack();
        foreach ($context->getRoutes() as $routeName => $route) {
            $routeStack[$routeName] = $route;
        }

        // application callbacks
        foreach ($context->getCallbacks() as $callback) {
            $this->on($callback[0], $callback[1], (isset($callback[2]) ? $callback[2] : 0));
        }

        return $this;
    }

    public function addRoute($nameOrRouteSpec /*, $routeSpec */)
    {
        $funcArgs = func_get_args();
        $args = (is_array($nameOrRouteSpec)) ? array(null, $funcArgs[0]) : array($funcArgs[0], $funcArgs[1]);
        $this->serviceLocator->get('Router')->getRouteStack()->offsetSet($args[0], $args[1]);
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
    public function getServiceManager()
    {
        return $this->serviceLocator;
    }

    /**
     * @return mixed
     */
    public function handleProblem(/* any args */)
    {
        if ($problemHandler = $this->serviceLocator->get('ProblemHandler')) {
            return call_user_func_array($problemHandler, func_get_args());
        }
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