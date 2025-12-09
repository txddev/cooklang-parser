<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models\Tokens;

final class TimerToken implements Token
{
    public function __construct(
        private readonly ?string $name,
        private readonly ?float $duration,
        private readonly ?string $unit,
        private readonly ?string $rawDuration = null,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getRawDuration(): ?string
    {
        return $this->rawDuration;
    }

    public function toText(): string
    {
        if ($this->rawDuration !== null) {
            $name = $this->name ?? '';

            return '~' . $name . '{' . $this->rawDuration . '}';
        }

        $suffix = '';

        if ($this->duration !== null) {
            $suffix = $this->duration . ($this->unit ?? '');
        }

        return '~' . ($this->name ?? $suffix);
    }
}
