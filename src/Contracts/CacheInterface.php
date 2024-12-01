<?php

namespace JoshuaMc1\Cache\Contracts;

interface CacheInterface
{
    public function set($key, $value, $ttl = null);

    public function has($key);

    public function get($key);

    public function delete($key);

    public function clear();
}
