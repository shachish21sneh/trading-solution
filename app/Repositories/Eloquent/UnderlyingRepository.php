<?php

namespace App\Repositories\Eloquent;

use App\Models\Underlying;
use App\Repositories\Contracts\UnderlyingRepositoryInterface;
use Illuminate\Support\Collection;

class UnderlyingRepository implements UnderlyingRepositoryInterface
{
    public function getActiveUnderlyings(): Collection
    {
        return Underlying::where('is_active', true)->get();
    }

    public function findBySymbol(string $symbol): ?Underlying
    {
        return Underlying::where('symbol', strtoupper($symbol))->first();
    }

    public function updateSpotPrice(string $symbol, float $spotPrice): bool
    {
        return Underlying::where('symbol', strtoupper($symbol))->update(['spot_price' => $spotPrice]) > 0;
    }
}
