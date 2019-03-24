<?php

namespace P;

/**
 * Reflection-based object factory
 *
 * @package InQuest
 */
class AutoFactory
{
    /**
     * Attempts to create a new class based on constructor typehints and parameter names
     *
     * @param ServiceLocator $serviceLocator
     * @param string         $requestedClass
     *
     * @return object
     * @throws \Exception
     */
    static public function create(ServiceLocator $serviceLocator, $requestedClass, array $options = null)
    {
        if (class_exists($requestedClass)) {
            $reflection = new \ReflectionClass($requestedClass);

            $arguments = [];
            $constructor = $reflection->getConstructor();
            if ($constructor) {
                $constructorParams = $reflection->getConstructor()->getParameters();

                if (count($constructorParams) == 0) {
                    return new $requestedClass;
                } else {

                    foreach ($constructorParams as $param) {
                        $class = $param->getClass();

                        $argument = null;
                        if ($class) {
                            $argument = $serviceLocator->get($class->getName());

                            // The Service Locator itself is not in the SL, so checking for it explicitly
                            if ($class->getName() == ServiceLocator::class) {
                                $argument = $serviceLocator;
                            }
                        }

                        if ($argument == null && $serviceLocator->has($param->getName())) {
                            $argument = $serviceLocator->get($param->getName());
                        }

                        if (!$param->isOptional() && $argument == null) {
                            throw new \RuntimeException('Missing required argument "' . $param->getName() . '" for class "' . $requestedClass);
                        }

                        if ($param->isOptional() && $argument == null) {
                            $arguments[$param->getName()] = $param->getDefaultValue();
                        } else {
                            $arguments[$param->getName()] = $argument;
                        }
                    }
                }
            }

            $instance = $reflection->newInstanceArgs($arguments);
            return $instance;
        }

        throw new \RuntimeException('Cannot find class ' . $requestedClass);
    }
}

