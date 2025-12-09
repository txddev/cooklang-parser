<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class Ingredient
{
    /**
     * @var array<int, IngredientOccurrence>
     */
    private array $occurrences = [];

    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, IngredientOccurrence>
     */
    public function getOccurrences(): array
    {
        return $this->occurrences;
    }

    public function addOccurrence(IngredientOccurrence $occurrence): void
    {
        $this->occurrences[] = $occurrence;
    }
}
