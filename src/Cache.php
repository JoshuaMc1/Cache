<?php

namespace JoshuaMc1\Cache;

use JoshuaMc1\Cache\Drivers\DatabaseCacheDriver;
use JoshuaMc1\Cache\Drivers\FileCacheDriver;

class Cache
{
    private static $instance;
    private $config;
    private $driverInstance;

    private function __construct()
    {

        $this->config = require __DIR__ . '/config/cache.php';

        $this->initializeDriver();
    }

    /**
     * Returns the singleton instance of the Cache class.
     *
     * If the instance does not exist, it initializes a new one.
     *
     * @return self The singleton instance of the Cache class.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Handles static method calls and redirects them to the driver instance.
     *
     * If the method does not exist in the driver instance, it throws a
     * `BadMethodCallException`.
     *
     * @param string $name The name of the method to call.
     * @param array $arguments The arguments to pass to the method.
     *
     * @throws \BadMethodCallException If the method does not exist in the
     *                                  driver instance.
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = self::getInstance();

        if (!method_exists($instance->driverInstance, $name)) {
            throw new \BadMethodCallException("Método '{$name}' not found.");
        }

        return call_user_func_array([$instance->driverInstance, $name], $arguments);
    }

    /**
     * Initializes the driver instance according to the configuration.
     *
     * @throws \Exception If the driver is not found or not supported.
     */
    private function initializeDriver()
    {
        $driver = $this->config['driver'];
        $drivers = $this->config['drivers'];

        if (!isset($drivers[$driver])) {
            throw new \Exception("Driver '{$driver}' not found.");
        }

        $driverConfig = $drivers[$driver];

        switch ($driver) {
            case 'file':
                $this->driverInstance = new FileCacheDriver($driverConfig);
                break;

            case 'mysql':
                $this->driverInstance = new DatabaseCacheDriver($driverConfig);
                break;

            case 'sqlite':
                $this->driverInstance = new DatabaseCacheDriver($driverConfig);
                break;

            default:
                throw new \Exception("Driver '{$driver}' not supported.");
        }
    }

    /**
     * Set a cache entry.
     *
     * @param string $key The key of the cache entry to set.
     * @param mixed $value The value of the cache entry to set.
     * @param int $ttl The time to live of the cache entry in seconds. Default is 3600 (1 hour).
     *
     * @return void
     */
    public static function set($key, $value, $ttl = null)
    {
        return self::getInstance()->driverInstance->set($key, $value, $ttl);
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key The key of the cache entry to check.
     *
     * @return bool True if the cache entry exists, false otherwise.
     */
    public static function has($key)
    {
        return self::getInstance()->driverInstance->has($key);
    }

    /**
     * Retrieve a cache entry.
     *
     * @param string $key The key of the cache entry to retrieve.
     *
     * @return mixed|null The value of the cache entry if it exists and hasn't expired, null otherwise.
     */
    public static function get($key)
    {
        return self::getInstance()->driverInstance->get($key);
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $key The key of the cache entry to delete.
     *
     * @return void
     */
    public static function delete($key)
    {
        return self::getInstance()->driverInstance->delete($key);
    }

    /**
     * Deletes all cache entries.
     *
     * @return void
     */
    public static function clear()
    {
        return self::getInstance()->driverInstance->clear();
    }
}
