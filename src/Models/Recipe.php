<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class Recipe
{
    /**
     * @param array<int, Step> $steps
     * @param array<int, Ingredient> $ingredients
     * @param array<int, Cookware> $cookware
     * @param array<int, Comment> $comments
     */
    public function __construct(
        private readonly ?string $slug,
        private readonly Metadata $metadata,
        private readonly array $steps,
        private readonly array $ingredients,
        private readonly array $cookware,
        private readonly array $comments,
    ) {
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    /**
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<int, Ingredient>
     */
    public function getIngredients(): array
    {
        return $this->ingredients;
    }

    /**
     * @return array<int, Cookware>
     */
    public function getCookware(): array
    {
        return $this->cookware;
    }

    /**
     * @return array<int, Comment>
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * @return array<int, string>
     */
    public function getIngredientNames(): array
    {
        return array_values(
            array_map(
                static fn (Ingredient $ingredient): string => $ingredient->getName(),
                $this->ingredients
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public function getCookwareNames(): array
    {
        return array_values(
            array_map(
                static fn (Cookware $cookware): string => $cookware->getName(),
                $this->cookware
            )
        );
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->metadata->getTags();
    }
}
