<?php
/**
 * Layered cache: Redis (shared across workers/containers) -> APCu (per-host
 * shared memory) -> per-request static array. Every layer is optional, so the
 * app keeps working on a plain XAMPP install with none of them enabled.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!function_exists('ait_redis')) {
    function ait_redis(): ?Redis
    {
        static $redis = false;
        if ($redis !== false) {
            return $redis;
        }
        $redis = null;
        $host = (string) ait_env('REDIS_HOST', '');
        if ($host === '' || !class_exists('Redis')) {
            return null;
        }
        try {
            $client = new Redis();
            if (!$client->connect($host, (int) ait_env('REDIS_PORT', 6379), 1.5)) {
                return null;
            }
            $password = (string) ait_env('REDIS_PASSWORD', '');
            if ($password !== '') {
                $client->auth($password);
            }
            $client->setOption(Redis::OPT_PREFIX, 'ait:');
            $redis = $client;
        } catch (Throwable $e) {
            error_log('Redis unavailable: ' . $e->getMessage());
        }
        return $redis;
    }
}

if (!function_exists('ait_cache_backend')) {
    function ait_cache_backend(): string
    {
        if (ait_redis() !== null) {
            return 'redis';
        }
        return (function_exists('apcu_enabled') && apcu_enabled()) ? 'apcu' : 'memory';
    }
}

if (!function_exists('ait_cache_get')) {
    function ait_cache_get(string $key, mixed &$found = null): mixed
    {
        $found = false;
        $memory = &$GLOBALS['ait_cache_memory'];
        if (is_array($memory) && array_key_exists($key, $memory)) {
            $found = true;
            return $memory[$key];
        }
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $value = apcu_fetch('ait:' . $key, $hit);
            if ($hit) {
                $found = true;
                return $memory[$key] = $value;
            }
        }
        $redis = ait_redis();
        if ($redis !== null) {
            $raw = $redis->get($key);
            if (is_string($raw)) {
                $found = true;
                $value = unserialize($raw, ['allowed_classes' => false]);
                if (function_exists('apcu_enabled') && apcu_enabled()) {
                    apcu_store('ait:' . $key, $value, 30);
                }
                return $memory[$key] = $value;
            }
        }
        return null;
    }
}

if (!function_exists('ait_cache_set')) {
    function ait_cache_set(string $key, mixed $value, int $ttl): void
    {
        $GLOBALS['ait_cache_memory'][$key] = $value;
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            // APCu is the hot layer: keep it short so Redis stays the source of truth.
            apcu_store('ait:' . $key, $value, min($ttl, 30));
        }
        $redis = ait_redis();
        $redis?->setex($key, $ttl, serialize($value));
    }
}

if (!function_exists('ait_cache_forget')) {
    function ait_cache_forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($GLOBALS['ait_cache_memory'][$key]);
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                apcu_delete('ait:' . $key);
            }
            ait_redis()?->del($key);
        }
    }
}

if (!function_exists('ait_cache_remember')) {
    /**
     * Returns the cached value or computes it once. A short Redis lock stops a
     * cold key from stampeding the database when many requests miss together.
     */
    function ait_cache_remember(string $key, int $ttl, callable $producer): mixed
    {
        $value = ait_cache_get($key, $found);
        if ($found) {
            return $value;
        }
        $redis = ait_redis();
        $lockKey = 'lock:' . $key;
        $haveLock = $redis === null || (bool) $redis->set($lockKey, '1', ['nx', 'ex' => 10]);
        if (!$haveLock) {
            // Someone else is building it; wait briefly for their result.
            for ($i = 0; $i < 20; $i++) {
                usleep(50000);
                $value = ait_cache_get($key, $found);
                if ($found) {
                    return $value;
                }
            }
        }
        try {
            $value = $producer();
            ait_cache_set($key, $value, $ttl);
            return $value;
        } finally {
            if ($haveLock && $redis !== null) {
                $redis->del($lockKey);
            }
        }
    }
}
