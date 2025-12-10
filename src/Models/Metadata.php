<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class Metadata
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function getTitle(): ?string
    {
        return $this->getString('title');
    }

    public function getServings(): ?int
    {
        return $this->getInt('servings');
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        $value = $this->attributes['tags'] ?? [];

        if (is_string($value)) {
            return array_values(
                array_filter(array_map('trim', explode(',', $value)))
            );
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn ($tag) => is_scalar($tag) ? (string) $tag : null,
                    $value
                )
            )
        );
    }

    public function getSource(): ?string
    {
        return $this->getString('source');
    }

    public function getPrepTime(): ?int
    {
        return $this->getInt('prepTime');
    }

    public function getCookTime(): ?int
    {
        return $this->getInt('cookTime');
    }

    public function getTotalTime(): ?int
    {
        return $this->getInt('totalTime');
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private function getString(string $key): ?string
    {
        $value = $this->attributes[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function getInt(string $key): ?int
    {
        $value = $this->attributes[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
