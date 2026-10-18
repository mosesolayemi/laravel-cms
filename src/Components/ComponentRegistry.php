<?php

namespace PhynixMedia\Cms\Components;

use InvalidArgumentException;

/**
 * Every component type available to editors, keyed by type key.
 *
 * Filled at boot from the package's own generic types plus whatever the host
 * lists in `cms.components.types`. A host type registered under the same key
 * as a package type replaces it, which is how a site restyles, say, the
 * generic rich text component without forking the package.
 */
class ComponentRegistry
{
    /**
     * The builder item type for a reference to a library component. It is
     * not a registered type: the renderer resolves it to the component's own.
     */
    public const LIBRARY = 'library';

    /**
     * The builder item type of "Copy from the library" before a component is
     * chosen; choosing replaces it with a copy, so it only persists unfinished.
     */
    public const LIBRARY_COPY = 'library-copy';

    /**
     * The key in a builder item's data marking it hidden from the site: kept
     * on the page with its content, shown again by clearing it.
     */
    public const HIDDEN = '_hidden';

    /**
     * @var array<string, ComponentType>
     */
    protected array $types = [];

    /**
     * @param  ComponentType|class-string<ComponentType>  ...$types
     */
    public function register(ComponentType|string ...$types): static
    {
        foreach ($types as $type) {
            $type = is_string($type) ? $this->resolve($type) : $type;

            if (in_array($type->key(), [self::LIBRARY, self::LIBRARY_COPY], true)) {
                throw new InvalidArgumentException('"' . $type->key() . '" is reserved for the component library.');
            }

            $this->types[$type->key()] = $type;
        }

        return $this;
    }

    public function get(?string $key): ?ComponentType
    {
        return $key === null ? null : ($this->types[$key] ?? null);
    }

    public function has(?string $key): bool
    {
        return $key !== null && isset($this->types[$key]);
    }

    /**
     * @return array<string, ComponentType>
     */
    public function all(): array
    {
        return array_filter($this->types, fn (ComponentType $type): bool => $type->isAvailable());
    }

    /**
     * @return array<string, ComponentType>
     */
    public function forArea(?string $area): array
    {
        if ($area === null) {
            return $this->all();
        }

        return array_filter($this->all(), fn (ComponentType $type): bool => $type->allowedIn($area));
    }

    /**
     * Select options, grouped the way the picker groups them.
     *
     * @return array<string, array<string, string>>
     */
    public function options(?string $area = null): array
    {
        $options = [];

        foreach ($this->forArea($area) as $key => $type) {
            $options[$type->group()][$key] = $type->label();
        }

        ksort($options);

        return $options;
    }

    /**
     * @param  class-string<ComponentType>  $class
     */
    protected function resolve(string $class): ComponentType
    {
        $type = app($class);

        if (! $type instanceof ComponentType) {
            throw new InvalidArgumentException("{$class} must extend " . ComponentType::class . '.');
        }

        return $type;
    }
}
