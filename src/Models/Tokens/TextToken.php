<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models\Tokens;

final class TextToken implements Token
{
    public function __construct(private readonly string $text)
    {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function toText(): string
    {
        return $this->text;
    }
}
