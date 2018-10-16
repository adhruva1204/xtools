<?php
/**
 * This file contains only the HelperBase class.
 */

declare(strict_types = 1);

namespace AppBundle\Helper;

use DateInterval;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * All helper classes inherit from this.
 */
abstract class HelperBase
{

    /** @var ContainerInterface The DI container. */
    protected $container;

    /**
     * Get a cache key.
     * @param string $key
     * @return string The key with a representation of the class name prefixed.
     */
    private function getCacheKey(string $key): string
    {
        return str_replace('\\', '', static::class).'.'.$key;
    }

    /**
     * Find out whether the given key exists in the cache.
     * @param string $key The cache key.
     * @return boolean
     */
    protected function cacheHas(string $key): bool
    {
        /** @var \Symfony\Component\Cache\Adapter\AdapterInterface $cache */
        $cache = $this->container->get('cache.app');
        return $cache->getItem($this->getCacheKey($key))->isHit();
    }

    /**
     * Get a value from the cache. With this it is not possible to tell the difference between a
     * cached value of 'null' and there being no cached value; if that situation is likely, you
     * should use the cache service directly.
     * @param string $key The cache key.
     * @return mixed|null Whatever's in the cache, or null if the key isn't present.
     */
    protected function cacheGet(string $key)
    {
        /** @var \Symfony\Component\Cache\Adapter\AdapterInterface $cache */
        $cache = $this->container->get('cache.app');
        $item = $cache->getItem($this->getCacheKey($key));
        if ($item->isHit()) {
            return $item->get();
        }
        return null;
    }

    /**
     * Save a value to the cache.
     * @param string $key The cache key.
     * @param string $value The value to cache.
     * @param string $expiresAfter A DateInterval interval specification.
     */
    protected function cacheSave(string $key, string $value, string $expiresAfter): void
    {
        /** @var \Symfony\Component\Cache\Adapter\AdapterInterface $cache */
        $cache = $this->container->get('cache.app');
        $item = $cache->getItem($this->getCacheKey($key));
        $item->set($value);
        $item->expiresAfter(new DateInterval($expiresAfter));
        $cache->save($item);
    }
}
