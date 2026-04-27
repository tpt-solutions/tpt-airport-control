<?php
/**
 * Service Container (Dependency Injection Container)
 *
 * Provides dependency injection and service management for the Flight Control System
 * Implements service registration, resolution, and lifecycle management
 */

class ServiceContainer
{
    private static $instance = null;
    private $services = [];
    private $shared = [];
    private $aliases = [];
    private $tags = [];

    // Service scopes
    const SCOPE_SINGLETON = 'singleton';
    const SCOPE_TRANSIENT = 'transient';
    const SCOPE_REQUEST = 'request';

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerCoreServices();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        // Initialize container
    }

    /**
     * Register core services
     */
    private function registerCoreServices()
    {
        // Register core infrastructure services
        $this->singleton('logger', function($c) {
            return Logger::getInstance();
        });

        $this->singleton('cache', function($c) {
            return Cache::getInstance();
        });

        $this->singleton('validator', function($c) {
            return Validator::getInstance();
        });

        $this->singleton('security_audit', function($c) {
            return SecurityAudit::getInstance();
        });

        // Register database connection
        $this->singleton('database', function($c) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: 5432;
            $dbname = getenv('DB_NAME') ?: 'flight_control';
            $user = getenv('DB_USER') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        });

        // Register repositories
        $this->singleton('flight_repository', function($c) {
            return new FlightRepository($c->get('database'));
        });

        $this->singleton('booking_repository', function($c) {
            return new BookingRepository($c->get('database'));
        });

        $this->singleton('passenger_repository', function($c) {
            return new PassengerRepository($c->get('database'));
        });

        $this->singleton('user_repository', function($c) {
            return new UserRepository($c->get('database'));
        });

        // Register services
        $this->singleton('flight_service', function($c) {
            return new FlightService($c->get('flight_repository'));
        });

        $this->singleton('booking_service', function($c) {
            return new BookingService($c->get('booking_repository'));
        });

        $this->singleton('passenger_service', function($c) {
            return new PassengerService($c->get('passenger_repository'));
        });

        $this->singleton('user_service', function($c) {
            return new UserService($c->get('user_repository'));
        });

        $this->singleton('runway_repository', function($c) {
            return new RunwayRepository($c->get('database'));
        });

        $this->singleton('runway_service', function($c) {
            return new RunwayService($c->get('runway_repository'));
        });

        $this->singleton('authentication_service', function($c) {
            return new AuthenticationService($c->get('user_repository'));
        });

        $this->singleton('authorization_service', function($c) {
            return new AuthorizationService();
        });

        // Register controllers
        $this->transient('flight_controller', function($c) {
            return new FlightController($c->get('database'));
        });

        $this->transient('booking_controller', function($c) {
            return new BookingController($c->get('database'));
        });

        $this->transient('passenger_controller', function($c) {
            return new PassengerController($c->get('database'));
        });

        $this->transient('user_controller', function($c) {
            return new UserController($c->get('database'));
        });
    }

    /**
     * Register a singleton service
     */
    public function singleton($name, $concrete)
    {
        $this->services[$name] = [
            'concrete' => $concrete,
            'scope' => self::SCOPE_SINGLETON,
            'shared' => false
        ];
    }

    /**
     * Register a transient service
     */
    public function transient($name, $concrete)
    {
        $this->services[$name] = [
            'concrete' => $concrete,
            'scope' => self::SCOPE_TRANSIENT,
            'shared' => false
        ];
    }

    /**
     * Register a request-scoped service
     */
    public function request($name, $concrete)
    {
        $this->services[$name] = [
            'concrete' => $concrete,
            'scope' => self::SCOPE_REQUEST,
            'shared' => false
        ];
    }

    /**
     * Register a service with custom configuration
     */
    public function bind($name, $concrete, $scope = self::SCOPE_SINGLETON)
    {
        $this->services[$name] = [
            'concrete' => $concrete,
            'scope' => $scope,
            'shared' => false
        ];
    }

    /**
     * Register an alias for a service
     */
    public function alias($alias, $service)
    {
        $this->aliases[$alias] = $service;
    }

    /**
     * Tag a service for grouping
     */
    public function tag($service, $tag)
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }
        $this->tags[$tag][] = $service;
    }

    /**
     * Get all services with a specific tag
     */
    public function tagged($tag)
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return array_map(function($service) {
            return $this->get($service);
        }, $this->tags[$tag]);
    }

    /**
     * Resolve a service from the container
     */
    public function get($name)
    {
        // Resolve aliases
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        // Check if service is registered
        if (!isset($this->services[$name])) {
            throw new Exception("Service '{$name}' is not registered in the container");
        }

        $service = $this->services[$name];

        // Return shared instance for singletons
        if ($service['scope'] === self::SCOPE_SINGLETON && $service['shared']) {
            return $this->shared[$name];
        }

        // Resolve the service
        $instance = $this->resolve($service['concrete']);

        // Store shared instance for singletons
        if ($service['scope'] === self::SCOPE_SINGLETON) {
            $this->shared[$name] = $instance;
            $this->services[$name]['shared'] = true;
        }

        return $instance;
    }

    /**
     * Check if a service is registered
     */
    public function has($name)
    {
        return isset($this->services[$name]) || isset($this->aliases[$name]);
    }

    /**
     * Resolve a service concrete
     */
    private function resolve($concrete)
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            $reflection = new ReflectionClass($concrete);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return new $concrete();
            }

            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters);

            return $reflection->newInstanceArgs($dependencies);
        }

        return $concrete;
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $parameters)
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();

            if (!$dependency) {
                // No type hint, try to get default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter '{$parameter->getName()}' without type hint or default value");
                }
                continue;
            }

            $dependencyName = $dependency->getName();

            // Check if it's a class we can resolve
            if ($this->has($dependencyName)) {
                $dependencies[] = $this->get($dependencyName);
            } elseif (class_exists($dependencyName)) {
                $dependencies[] = $this->resolve($dependencyName);
            } else {
                // Try to resolve by parameter name
                $paramName = $parameter->getName();
                if ($this->has($paramName)) {
                    $dependencies[] = $this->get($paramName);
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve dependency '{$dependencyName}' for parameter '{$paramName}'");
                }
            }
        }

        return $dependencies;
    }

    /**
     * Make an instance with automatic dependency injection
     */
    public function make($class, array $parameters = [])
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $constructorParams = $constructor->getParameters();
        $dependencies = [];

        foreach ($constructorParams as $param) {
            $paramName = $param->getName();

            // Check if parameter was explicitly provided
            if (isset($parameters[$paramName])) {
                $dependencies[] = $parameters[$paramName];
                continue;
            }

            // Try to resolve from container
            try {
                $resolved = $this->resolveDependencies([$param]);
                $dependencies[] = $resolved[0];
            } catch (Exception $e) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Call a method with automatic dependency injection
     */
    public function call($callable, array $parameters = [])
    {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new ReflectionFunction($callable);
        }

        $methodParams = $reflection->getParameters();
        $dependencies = [];

        foreach ($methodParams as $param) {
            $paramName = $param->getName();

            // Check if parameter was explicitly provided
            if (isset($parameters[$paramName])) {
                $dependencies[] = $parameters[$paramName];
                continue;
            }

            // Try to resolve from container
            try {
                $resolved = $this->resolveDependencies([$param]);
                $dependencies[] = $resolved[0];
            } catch (Exception $e) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $callable(...$dependencies);
    }

    /**
     * Register a factory for creating instances
     */
    public function factory($name, $factory)
    {
        $this->services[$name] = [
            'concrete' => $factory,
            'scope' => self::SCOPE_TRANSIENT,
            'shared' => false
        ];
    }

    /**
     * Extend an existing service
     */
    public function extend($name, $extender)
    {
        if (!isset($this->services[$name])) {
            throw new Exception("Service '{$name}' is not registered");
        }

        $originalConcrete = $this->services[$name]['concrete'];

        $this->services[$name]['concrete'] = function($c) use ($originalConcrete, $extender) {
            $instance = $this->resolve($originalConcrete);
            return $extender($instance, $c);
        };
    }

    /**
     * Clear all shared instances (for testing)
     */
    public function clear()
    {
        $this->shared = [];
        foreach ($this->services as $name => $service) {
            $this->services[$name]['shared'] = false;
        }
    }

    /**
     * Get all registered services
     */
    public function getServices()
    {
        return array_keys($this->services);
    }

    /**
     * Get service information
     */
    public function getServiceInfo($name)
    {
        if (!isset($this->services[$name])) {
            return null;
        }

        return [
            'scope' => $this->services[$name]['scope'],
            'shared' => $this->services[$name]['shared'],
            'aliases' => array_keys($this->aliases, $name)
        ];
    }
}

// Global helper functions for easier access
if (!function_exists('app')) {
    /**
     * Get service from container
     */
    function app($service = null) {
        $container = ServiceContainer::getInstance();

        if ($service === null) {
            return $container;
        }

        return $container->get($service);
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve a class with dependency injection
     */
    function resolve($class, $parameters = []) {
        return ServiceContainer::getInstance()->make($class, $parameters);
    }
}

// Usage examples:
/*
// Get services from container
$logger = app('logger');
$cache = app('cache');
$flightService = app('flight_service');

// Make instances with DI
$controller = app()->make('FlightController');
$userService = resolve('UserService');

// Call methods with DI
$result = app()->call([$flightService, 'getActiveFlights']);

// Register custom services
app()->singleton('my_service', function($c) {
    return new MyService($c->get('database'));
});

// Tag services for batch operations
app()->tag('flight_service', 'business_services');
app()->tag('booking_service', 'business_services');
$businessServices = app()->tagged('business_services');
*/
?>
