<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models\Tokens;

interface Token
{
    public function toText(): string;
}
