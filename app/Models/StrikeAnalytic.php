<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrikeAnalytic extends Model
{
    use HasFactory;

    protected $table = 'strike_analytics';

    protected $fillable = [
        'underlying_id',
        'symbol',
        'strike_price',
        'option_type',
        'peak_oi',
        'peak_time',
        'lowest_point',
        'bottom_time',
        'current_trend',
        'trend_started_at',
        'started_increasing_at',
        'started_decreasing_at',
        'recovered_at',
        'current_oi',
        'prev_oi',
        'highest_drop_pct',
    ];

    protected $casts = [
        'strike_price' => 'float',
        'peak_oi' => 'integer',
        'lowest_point' => 'integer',
        'current_oi' => 'integer',
        'prev_oi' => 'integer',
        'highest_drop_pct' => 'float',
        'peak_time' => 'datetime',
        'bottom_time' => 'datetime',
        'trend_started_at' => 'datetime',
        'started_increasing_at' => 'datetime',
        'started_decreasing_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function underlying(): BelongsTo
    {
        return $this->belongsTo(Underlying::class);
    }

    /**
     * Difference from Peak
     */
    public function getDiffFromPeakAttribute(): int
    {
        return max(0, $this->peak_oi - $this->current_oi);
    }

    /**
     * Percentage Drop from Peak
     */
    public function getDropPercentageAttribute(): float
    {
        if ($this->peak_oi <= 0) {
            return 0.0;
        }

        return round((($this->peak_oi - $this->current_oi) / $this->peak_oi) * 100, 2);
    }
}
