<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class IngredientOccurrence
{
    public function __construct(
        private readonly int $stepIndex,
        private readonly ?float $quantity,
        private readonly ?string $unit,
        private readonly bool $optional,
        private readonly ?string $rawQuantity = null,
    ) {
    }

    public function getStepIndex(): int
    {
        return $this->stepIndex;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function getRawQuantity(): ?string
    {
        return $this->rawQuantity;
    }
}
