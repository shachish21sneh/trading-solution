<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Underlying extends Model
{
    use HasFactory;

    protected $table = 'underlyings';

    protected $fillable = [
        'symbol',
        'name',
        'spot_price',
        'strike_step',
        'lot_size',
        'available_expiries',
        'selected_expiry',
        'is_active',
    ];

    protected $casts = [
        'spot_price' => 'float',
        'strike_step' => 'float',
        'lot_size' => 'integer',
        'available_expiries' => 'array',
        'is_active' => 'boolean',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(OptionSnapshot::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(StrikeAnalytic::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MarketAlert::class);
    }

    /**
     * Compute ATM strike based on spot price and strike step
     */
    public function getAtmStrikeAttribute(): float
    {
        $step = $this->strike_step ?: 50;
        return round($this->spot_price / $step) * $step;
    }

    /**
     * Generate the 11 strikes ladder: ATM-5 to ATM+5
     */
    public function getVisibleStrikes(int $range = 5): array
    {
        $atm = $this->getAtmStrikeAttribute();
        $step = $this->strike_step ?: 50;
        $strikes = [];

        for ($i = -$range; $i <= $range; $i++) {
            $strikes[] = round($atm + ($i * $step), 2);
        }

        return $strikes;
    }
}
