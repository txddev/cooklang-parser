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
        return new self(self::canonicalize($attributes));
    }

    public function getTitle(): ?string
    {
        return $this->getString('title');
    }

    public function getServings(): ?int
    {
        return self::parseServingsValue($this->attributes['servings'] ?? null);
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
        return self::parseDurationToMinutes($this->attributes['prepTime'] ?? null);
    }

    public function getCookTime(): ?int
    {
        return self::parseDurationToMinutes($this->attributes['cookTime'] ?? null);
    }

    public function getTotalTime(): ?int
    {
        return self::parseDurationToMinutes($this->attributes['totalTime'] ?? null);
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function canonicalize(array $attributes): array
    {
        $canonical = $attributes;
        $lookup = self::buildLookup($attributes);

        self::assignString($canonical, $lookup, 'source', ['source', 'source_name']);
        self::assignString($canonical, $lookup, 'author', ['author', 'source_author']);
        self::assignString($canonical, $lookup, 'source_url', ['source_url']);

        if (($servingsValue = self::firstValue($lookup, ['servings', 'serves', 'yield'])) !== null) {
            $servings = self::parseServingsValue($servingsValue);

            if ($servings !== null) {
                $canonical['servings'] = $servings;
            }
        }

        self::assignDuration($canonical, $lookup, 'totalTime', ['time_required', 'time', 'duration']);
        self::assignDuration($canonical, $lookup, 'prepTime', ['prep_time', 'time_prep']);
        self::assignDuration($canonical, $lookup, 'cookTime', ['cook_time', 'time_cook']);

        self::assignString($canonical, $lookup, 'course', ['course', 'category']);
        self::assignString($canonical, $lookup, 'locale', ['locale']);
        self::assignString($canonical, $lookup, 'difficulty', ['difficulty']);
        self::assignString($canonical, $lookup, 'cuisine', ['cuisine']);
        self::assignList($canonical, $lookup, 'diet', ['diet']);
        self::assignList($canonical, $lookup, 'tags', ['tags']);
        self::assignImage($canonical, $lookup, ['image', 'images', 'picture', 'pictures']);
        self::assignString($canonical, $lookup, 'description', ['introduction', 'description']);

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function buildLookup(array $attributes): array
    {
        $lookup = [];

        foreach ($attributes as $key => $value) {
            $lookup[self::normalizeKey((string) $key)] = $value;
        }

        $source = $attributes['source'] ?? null;

        if (is_array($source)) {
            foreach ($source as $key => $value) {
                $lookup['source_'.self::normalizeKey((string) $key)] = $value;
            }
        }

        if (isset($lookup['source']) && is_array($lookup['source'])) {
            $sourceArray = $lookup['source'];

            if (isset($sourceArray['name'])) {
                $lookup['source'] = $sourceArray['name'];
            }

            if (isset($sourceArray['url'])) {
                $lookup['source_url'] = $sourceArray['url'];
            }

            if (isset($sourceArray['author'])) {
                $lookup['source_author'] = $sourceArray['author'];
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @param  array<int, string>  $keys
     */
    private static function firstValue(array $lookup, array $keys): mixed
    {
        foreach ($keys as $key) {
            $normalized = self::normalizeKey($key);

            if (array_key_exists($normalized, $lookup)) {
                $value = $lookup[$normalized];

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function assignString(array &$canonical, array $lookup, string $target, array $candidates): void
    {
        $value = self::firstValue($lookup, $candidates);
        $string = self::toStringValue($value);

        if ($string !== null) {
            $canonical[$target] = $string;
        }
    }

    private static function assignDuration(array &$canonical, array $lookup, string $target, array $candidates): void
    {
        $value = self::firstValue($lookup, $candidates);

        if ($value === null) {
            return;
        }

        $minutes = self::parseDurationToMinutes($value);

        if ($minutes !== null) {
            $canonical[$target] = $minutes;
        }
    }

    private static function assignList(array &$canonical, array $lookup, string $target, array $candidates): void
    {
        $value = self::firstValue($lookup, $candidates);

        if ($value === null) {
            return;
        }

        $list = self::normalizeList($value);

        if ($list !== []) {
            $canonical[$target] = $list;
        }
    }

    private static function assignImage(array &$canonical, array $lookup, array $candidates): void
    {
        $value = self::firstValue($lookup, $candidates);

        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            $list = self::normalizeList($value);

            if ($list === []) {
                return;
            }

            $canonical['images'] = $list;
            $canonical['image'] = $canonical['image'] ?? $list[0];

            return;
        }

        $string = self::toStringValue($value);

        if ($string !== null) {
            $canonical['image'] = $string;
        }
    }

    private static function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));

            return array_values(array_filter($parts, static fn ($part) => $part !== ''));
        }

        if (is_array($value)) {
            return array_values(
                array_filter(
                    array_map(
                        static fn ($item) => is_scalar($item) ? trim((string) $item) : null,
                        $value
                    ),
                    static fn ($item) => $item !== null && $item !== ''
                )
            );
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? [] : [$string];
        }

        return [];
    }

    private static function toStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    private static function parseServingsValue(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function parseDurationToMinutes(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^pt(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $normalized, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            return ($hours * 60) + $minutes + (int) floor($seconds / 60);
        }

        $hours = null;
        $minutes = null;

        if (preg_match('/(\d+)\s*(?:h|hour|hours)/', $normalized, $matches)) {
            $hours = (int) $matches[1];
        }

        if (preg_match('/(\d+)\s*(?:m|min|mins|minute|minutes)/', $normalized, $matches)) {
            $minutes = (int) $matches[1];
        }

        if ($hours !== null || $minutes !== null) {
            return ($hours ?? 0) * 60 + ($minutes ?? 0);
        }

        if (preg_match('/^(\d+):(\d{2})$/', $normalized, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        if (preg_match('/(\d+)/', $normalized, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return trim($key, '_');
    }
}
