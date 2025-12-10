<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class Comment
{
    public function __construct(
        private readonly string $text,
        private readonly int $lineNumber,
    ) {}

    public function getText(): string
    {
        return $this->text;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }
}
