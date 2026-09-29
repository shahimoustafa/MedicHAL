<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use Closure;
use InvalidArgumentException;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $key, callable|object|string $concrete): void
    {
        $this->bindings[$key] = $concrete;
        unset($this->instances[$key]);
    }

    public function singleton(string $key, callable|object|string $concrete): void
    {
        $this->bindings[$key] = $concrete;
    }

    public function instance(string $key, object $instance): void
    {
        $this->instances[$key] = $instance;
    }

    public function get(string $key): mixed
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (!isset($this->bindings[$key])) {
            throw new InvalidArgumentException(sprintf('Service "%s" not found in container.', $key));
        }

        $concrete = $this->bindings[$key];

        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } elseif (is_object($concrete)) {
            $object = $concrete;
        } elseif (is_string($concrete) && class_exists($concrete)) {
            $object = new $concrete();
        } else {
            $object = $concrete;
        }

        $this->instances[$key] = $object;

        return $object;
    }

    public function has(string $key): bool
    {
        return isset($this->instances[$key]) || isset($this->bindings[$key]);
    }

    public function isResolved(string $key): bool
    {
        return isset($this->instances[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->instances[$key], $this->bindings[$key]);
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
    }
}
