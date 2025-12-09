<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

use Txd\CooklangParser\Models\Tokens\Token;

final class Step
{
    /**
     * @param array<int, Token> $tokens
     */
    public function __construct(
        private readonly int $index,
        private readonly array $tokens,
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return array<int, Token>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getText(): string
    {
        return trim(
            implode('', array_map(
                static fn (Token $token): string => $token->toText(),
                $this->tokens
            ))
        );
    }
}
