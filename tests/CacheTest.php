<?php

use PHPUnit\Framework\TestCase;
use JoshuaMc1\Cache\Cache;
use JoshuaMc1\Cache\Drivers\FileCacheDriver;
use JoshuaMc1\Cache\Drivers\DatabaseCacheDriver;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Cache::class)]
#[CoversClass(FileCacheDriver::class)]
#[CoversClass(DatabaseCacheDriver::class)]
class CacheTest extends TestCase
{
    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = Cache::getInstance();
        $instance2 = Cache::getInstance();

        $this->assertInstanceOf(Cache::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_set_and_get(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        Cache::set($key, $value);

        $this->assertSame($value, Cache::get($key));
    }

    public function test_delete(): void
    {
        $key = 'test_key';

        Cache::set($key, 'test_value');

        $this->assertTrue(Cache::has($key));

        Cache::delete($key);

        $this->assertFalse(Cache::has($key));
    }
}
