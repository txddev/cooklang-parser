<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models\Tokens;

final class IngredientToken implements Token
{
    public function __construct(
        private readonly string $name,
        private readonly ?float $quantity,
        private readonly ?string $unit,
        private readonly bool $optional = false,
        private readonly ?string $rawQuantity = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
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

    public function toText(): string
    {
        $suffix = '';

        if ($this->rawQuantity !== null) {
            $suffix = '{'.$this->rawQuantity.'}';
        }

        return '@'.$this->name.($this->optional ? '?' : '').$suffix;
    }
}
