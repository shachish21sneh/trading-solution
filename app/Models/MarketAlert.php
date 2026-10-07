<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketAlert extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'market_alerts';

    protected $fillable = [
        'underlying_id',
        'symbol',
        'alert_type',
        'severity',
        'strike_price',
        'option_type',
        'title',
        'message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'strike_price' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function underlying(): BelongsTo
    {
        return $this->belongsTo(Underlying::class);
    }
}
