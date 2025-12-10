<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models\Tokens;

final class CookwareToken implements Token
{
    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toText(): string
    {
        return '#'.$this->name;
    }
}
