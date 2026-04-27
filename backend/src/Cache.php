<?php
/**
 * Caching Service
 *
 * Provides unified caching interface supporting multiple backends
 * Implements cache invalidation strategies and performance monitoring
 */

class Cache
{
    private static $instance = null;
    private $backend;
    private $prefix;
    private $defaultTtl;
    private $stats;

    /**
     * Supported cache backends
     */
    const BACKEND_MEMORY = 'memory';
    const BACKEND_FILE = 'file';
    const BACKEND_REDIS = 'redis';
    const BACKEND_MEMCACHED = 'memcached';

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->prefix = getenv('CACHE_PREFIX') ?: 'flight_control_';
        $this->defaultTtl = getenv('CACHE_DEFAULT_TTL') ?: 3600; // 1 hour
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ];

        $this->initializeBackend();
    }

    /**
     * Initialize cache backend
     */
    private function initializeBackend()
    {
        $backendType = getenv('CACHE_BACKEND') ?: self::BACKEND_MEMORY;

        switch ($backendType) {
            case self::BACKEND_REDIS:
                $this->backend = new RedisCacheBackend();
                break;
            case self::BACKEND_MEMCACHED:
                $this->backend = new MemcachedCacheBackend();
                break;
            case self::BACKEND_FILE:
                $this->backend = new FileCacheBackend();
                break;
            case self::BACKEND_MEMORY:
            default:
                $this->backend = new MemoryCacheBackend();
                break;
        }
    }

    /**
     * Get cache key with prefix
     */
    private function getKey($key)
    {
        return $this->prefix . $key;
    }

    /**
     * Get value from cache
     */
    public function get($key, $default = null)
    {
        $cacheKey = $this->getKey($key);
        $value = $this->backend->get($cacheKey);

        if ($value !== null) {
            $this->stats['hits']++;
            return $value;
        }

        $this->stats['misses']++;
        return $default;
    }

    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null)
    {
        $cacheKey = $this->getKey($key);
        $ttl = $ttl ?: $this->defaultTtl;

        $result = $this->backend->set($cacheKey, $value, $ttl);
        if ($result) {
            $this->stats['sets']++;
        }

        return $result;
    }

    /**
     * Delete value from cache
     */
    public function delete($key)
    {
        $cacheKey = $this->getKey($key);
        $result = $this->backend->delete($cacheKey);

        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    /**
     * Check if key exists in cache
     */
    public function has($key)
    {
        $cacheKey = $this->getKey($key);
        return $this->backend->has($cacheKey);
    }

    /**
     * Clear all cache entries
     */
    public function clear()
    {
        return $this->backend->clear();
    }

    /**
     * Get or set value with callback
     */
    public function remember($key, $ttl, $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $backendStats = $this->backend->getStats();
        return array_merge($this->stats, [
            'backend' => $backendStats,
            'hit_rate' => $this->stats['hits'] + $this->stats['misses'] > 0
                ? $this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses'])
                : 0
        ]);
    }

    /**
     * Get multiple values
     */
    public function getMultiple($keys, $default = null)
    {
        $cacheKeys = array_map([$this, 'getKey'], $keys);
        $values = $this->backend->getMultiple($cacheKeys);

        $result = [];
        foreach ($keys as $key) {
            $cacheKey = $this->getKey($key);
            if (isset($values[$cacheKey])) {
                $this->stats['hits']++;
                $result[$key] = $values[$cacheKey];
            } else {
                $this->stats['misses']++;
                $result[$key] = $default;
            }
        }

        return $result;
    }

    /**
     * Set multiple values
     */
    public function setMultiple($values, $ttl = null)
    {
        $cacheValues = [];
        foreach ($values as $key => $value) {
            $cacheValues[$this->getKey($key)] = $value;
        }

        $ttl = $ttl ?: $this->defaultTtl;
        $result = $this->backend->setMultiple($cacheValues, $ttl);

        if ($result) {
            $this->stats['sets'] += count($values);
        }

        return $result;
    }

    /**
     * Delete multiple values
     */
    public function deleteMultiple($keys)
    {
        $cacheKeys = array_map([$this, 'getKey'], $keys);
        $result = $this->backend->deleteMultiple($cacheKeys);

        if ($result) {
            $this->stats['deletes'] += count($keys);
        }

        return $result;
    }

    /**
     * Increment numeric value
     */
    public function increment($key, $value = 1)
    {
        $cacheKey = $this->getKey($key);
        return $this->backend->increment($cacheKey, $value);
    }

    /**
     * Decrement numeric value
     */
    public function decrement($key, $value = 1)
    {
        $cacheKey = $this->getKey($key);
        return $this->backend->decrement($cacheKey, $value);
    }
}

/**
 * Cache Backend Interface
 */
interface CacheBackendInterface
{
    public function get($key);
    public function set($key, $value, $ttl);
    public function delete($key);
    public function has($key);
    public function clear();
    public function getMultiple($keys);
    public function setMultiple($values, $ttl);
    public function deleteMultiple($keys);
    public function increment($key, $value);
    public function decrement($key, $value);
    public function getStats();
}

/**
 * Memory Cache Backend (for development/testing)
 */
class MemoryCacheBackend implements CacheBackendInterface
{
    private $storage = [];
    private $expirations = [];

    public function get($key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        if (isset($this->expirations[$key]) && time() > $this->expirations[$key]) {
            unset($this->storage[$key], $this->expirations[$key]);
            return null;
        }

        return $this->storage[$key];
    }

    public function set($key, $value, $ttl)
    {
        $this->storage[$key] = $value;
        if ($ttl > 0) {
            $this->expirations[$key] = time() + $ttl;
        }
        return true;
    }

    public function delete($key)
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key], $this->expirations[$key]);
            return true;
        }
        return false;
    }

    public function has($key)
    {
        return $this->get($key) !== null;
    }

    public function clear()
    {
        $this->storage = [];
        $this->expirations = [];
        return true;
    }

    public function getMultiple($keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function setMultiple($values, $ttl)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple($keys)
    {
        $deleted = 0;
        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }
        return $deleted > 0;
    }

    public function increment($key, $value)
    {
        $current = $this->get($key) ?: 0;
        $newValue = $current + $value;
        $this->set($key, $newValue, 0);
        return $newValue;
    }

    public function decrement($key, $value)
    {
        $current = $this->get($key) ?: 0;
        $newValue = $current - $value;
        $this->set($key, $newValue, 0);
        return $newValue;
    }

    public function getStats()
    {
        return [
            'type' => 'memory',
            'items' => count($this->storage),
            'expirations' => count($this->expirations)
        ];
    }
}

/**
 * File Cache Backend
 */
class FileCacheBackend implements CacheBackendInterface
{
    private $cacheDir;

    public function __construct()
    {
        $this->cacheDir = getenv('CACHE_FILE_DIR') ?: __DIR__ . '/../../cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getFilePath($key)
    {
        return $this->cacheDir . md5($key) . '.cache';
    }

    public function get($key)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));
        if (!$data || !isset($data['value'], $data['expires'])) {
            return null;
        }

        if ($data['expires'] > 0 && time() > $data['expires']) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set($key, $value, $ttl)
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    public function delete($key)
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }

    public function has($key)
    {
        return $this->get($key) !== null;
    }

    public function clear()
    {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    public function getMultiple($keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function setMultiple($values, $ttl)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple($keys)
    {
        $deleted = 0;
        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }
        return $deleted > 0;
    }

    public function increment($key, $value)
    {
        $current = $this->get($key) ?: 0;
        $newValue = $current + $value;
        $this->set($key, $newValue, 0);
        return $newValue;
    }

    public function decrement($key, $value)
    {
        $current = $this->get($key) ?: 0;
        $newValue = $current - $value;
        $this->set($key, $newValue, 0);
        return $newValue;
    }

    public function getStats()
    {
        $files = glob($this->cacheDir . '*.cache');
        return [
            'type' => 'file',
            'cache_dir' => $this->cacheDir,
            'files' => count($files)
        ];
    }
}

/**
 * Redis Cache Backend
 */
class RedisCacheBackend implements CacheBackendInterface
{
    private $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $host = getenv('REDIS_HOST') ?: 'localhost';
        $port = getenv('REDIS_PORT') ?: 6379;

        try {
            $this->redis->connect($host, $port);
            if ($password = getenv('REDIS_PASSWORD')) {
                $this->redis->auth($password);
            }
            if ($db = getenv('REDIS_DB')) {
                $this->redis->select($db);
            }
        } catch (Exception $e) {
            throw new Exception("Redis connection failed: " . $e->getMessage());
        }
    }

    public function get($key)
    {
        $value = $this->redis->get($key);
        return $value === false ? null : unserialize($value);
    }

    public function set($key, $value, $ttl)
    {
        $serialized = serialize($value);
        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        } else {
            return $this->redis->set($key, $serialized);
        }
    }

    public function delete($key)
    {
        return $this->redis->del($key) > 0;
    }

    public function has($key)
    {
        return $this->redis->exists($key);
    }

    public function clear()
    {
        return $this->redis->flushdb();
    }

    public function getMultiple($keys)
    {
        $values = $this->redis->mget($keys);
        $result = [];
        foreach ($keys as $i => $key) {
            if ($values[$i] !== false) {
                $result[$key] = unserialize($values[$i]);
            }
        }
        return $result;
    }

    public function setMultiple($values, $ttl)
    {
        $serialized = [];
        foreach ($values as $key => $value) {
            $serialized[$key] = serialize($value);
        }

        if ($ttl > 0) {
            $this->redis->multi();
            foreach ($serialized as $key => $value) {
                $this->redis->setex($key, $ttl, $value);
            }
            $result = $this->redis->exec();
            return !in_array(false, $result);
        } else {
            return $this->redis->mset($serialized);
        }
    }

    public function deleteMultiple($keys)
    {
        return $this->redis->del($keys) > 0;
    }

    public function increment($key, $value)
    {
        return $this->redis->incrby($key, $value);
    }

    public function decrement($key, $value)
    {
        return $this->redis->decrby($key, $value);
    }

    public function getStats()
    {
        $info = $this->redis->info();
        return [
            'type' => 'redis',
            'connected' => $this->redis->isConnected(),
            'db_size' => $this->redis->dbSize(),
            'info' => $info
        ];
    }
}

/**
 * Memcached Cache Backend
 */
class MemcachedCacheBackend implements CacheBackendInterface
{
    private $memcached;

    public function __construct()
    {
        $this->memcached = new Memcached();
        $servers = getenv('MEMCACHED_SERVERS') ?: 'localhost:11211';

        $serverList = [];
        foreach (explode(',', $servers) as $server) {
            list($host, $port) = explode(':', $server);
            $serverList[] = [$host, (int)$port];
        }

        $this->memcached->addServers($serverList);
    }

    public function get($key)
    {
        $value = $this->memcached->get($key);
        return $this->memcached->getResultCode() === Memcached::RES_SUCCESS
            ? unserialize($value) : null;
    }

    public function set($key, $value, $ttl)
    {
        $serialized = serialize($value);
        return $this->memcached->set($key, $serialized, $ttl);
    }

    public function delete($key)
    {
        return $this->memcached->delete($key);
    }

    public function has($key)
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() === Memcached::RES_SUCCESS;
    }

    public function clear()
    {
        return $this->memcached->flush();
    }

    public function getMultiple($keys)
    {
        $values = $this->memcached->getMulti($keys);
        $result = [];
        if ($values) {
            foreach ($values as $key => $value) {
                $result[$key] = unserialize($value);
            }
        }
        return $result;
    }

    public function setMultiple($values, $ttl)
    {
        $serialized = [];
        foreach ($values as $key => $value) {
            $serialized[$key] = serialize($value);
        }
        return $this->memcached->setMulti($serialized, $ttl);
    }

    public function deleteMultiple($keys)
    {
        return $this->memcached->deleteMulti($keys);
    }

    public function increment($key, $value)
    {
        return $this->memcached->increment($key, $value);
    }

    public function decrement($key, $value)
    {
        return $this->memcached->decrement($key, $value);
    }

    public function getStats()
    {
        $stats = $this->memcached->getStats();
        return [
            'type' => 'memcached',
            'servers' => count($stats),
            'stats' => $stats
        ];
    }
}

// Usage examples:
/*
// Basic usage
$cache = Cache::getInstance();

// Simple get/set
$cache->set('user_123', ['name' => 'John', 'role' => 'admin'], 3600);
$user = $cache->get('user_123');

// Remember pattern (cache database results)
$flights = $cache->remember('active_flights', 300, function() {
    return $flightRepo->getActiveFlights();
});

// Cache multiple values
$users = $cache->getMultiple(['user_1', 'user_2', 'user_3']);

// Cache with tags (for invalidation)
$cache->set('flights_list', $flights, 3600);
$cache->set('flights_count', $count, 3600);

// Invalidate related cache
$cache->deleteMultiple(['flights_list', 'flights_count']);

// Get cache statistics
$stats = $cache->getStats();
echo "Hit rate: " . ($stats['hit_rate'] * 100) . "%\n";
*/
?>
