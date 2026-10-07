<?php

namespace App\Repositories\Contracts;

use App\Models\Underlying;
use Illuminate\Support\Collection;

interface UnderlyingRepositoryInterface
{
    public function getActiveUnderlyings(): Collection;
    public function findBySymbol(string $symbol): ?Underlying;
    public function updateSpotPrice(string $symbol, float $spotPrice): bool;
}
