<?php

declare(strict_types=1);

namespace Txd\CooklangParser\Models;

final class Cookware
{
    /**
     * @var array<int, int>
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
     * @return array<int, int>
     */
    public function getOccurrences(): array
    {
        return $this->occurrences;
    }

    public function addOccurrence(int $stepIndex): void
    {
        $this->occurrences[] = $stepIndex;
    }
}
