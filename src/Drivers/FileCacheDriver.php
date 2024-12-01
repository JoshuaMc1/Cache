<?php

namespace JoshuaMc1\Cache\Drivers;

use JoshuaMc1\Cache\Contracts\CacheInterface;

class FileCacheDriver implements CacheInterface
{
    private $path;
    private $ttl;
    private $prefix;

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? sprintf('%s/.cache', dirname(__DIR__, 3));
        $this->ttl = $config['ttl'] ?? 3600;
        $this->prefix = $config['prefix'] ?? 'c_';

        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0777, true) && !is_dir($this->path)) {
                throw new \RuntimeException("Failed to create or access cache directory: {$this->path}");
            }
        }

        if (!is_writable($this->path)) {
            throw new \RuntimeException("Cache directory is not writable: {$this->path}");
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
    public function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? $this->ttl;
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl
        ];

        $filePath = $this->getFilePath($key);
        file_put_contents($filePath, serialize($data));
    }

    /**
     * Get a cache entry.
     *
     * @param string $key The key of the cache entry to retrieve.
     *
     * @return mixed|null The value of the cache entry if it exists and hasn't expired, null otherwise.
     */
    public function get($key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $data = unserialize(file_get_contents($filePath));

        if (time() > $data['expires_at']) {
            unlink($filePath);
            return null;
        }

        return $data['value'];
    }

    /**
     * Check if a cache entry exists.
     *
     * @param string $key The key of the cache entry to check.
     *
     * @return bool True if the cache entry exists, false otherwise.
     */
    public function has($key): bool
    {
        return file_exists($this->getFilePath($key));
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $key The key of the cache entry to delete.
     *
     * @return void
     */
    public function delete($key)
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Deletes all cache entries.
     *
     * This method iterates over all the files in the cache directory and
     * deletes them one by one. This is necessary because the cache directory
     * may contain other files that are not cache entries.
     */
    public function clear()
    {
        $files = glob("{$this->path}/*");

        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Generates a file path for the given cache key.
     *
     * The file path is constructed by concatenating the cache path, the prefix,
     * the MD5 of the cache key, and the ".cache" extension.
     *
     * @param string $key The cache key.
     *
     * @return string The file path where the cache entry is stored.
     */
    private function getFilePath($key)
    {
        return sprintf('%s/%s%s.cache', $this->path, $this->prefix, md5($key));
    }
}
