<?php

namespace WPOptiKit\Core;

use RuntimeException;

final class Container
{
    /**
     * @var array<string, mixed>
     */
    private array $entries = array();

    /**
     * @var array<string, callable(self): mixed>
     */
    private array $factories = array();

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->entries)) {
            return $this->entries[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('Container entry "%s" is not registered.', $id));
        }

        $this->entries[$id] = $this->factories[$id]($this);

        return $this->entries[$id];
    }
}
