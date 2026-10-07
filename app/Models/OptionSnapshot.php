<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false; // We use explicit snapshot_time

    protected $table = 'option_snapshots';

    protected $fillable = [
        'underlying_id',
        'symbol',
        'expiry_date',
        'spot_price',
        'strike_price',
        'option_type',
        'oi',
        'change_oi',
        'volume',
        'iv',
        'ltp',
        'price_change',
        'snapshot_time',
    ];

    protected $casts = [
        'spot_price' => 'float',
        'strike_price' => 'float',
        'oi' => 'integer',
        'change_oi' => 'integer',
        'volume' => 'integer',
        'iv' => 'float',
        'ltp' => 'float',
        'price_change' => 'float',
        'snapshot_time' => 'datetime',
    ];

    public function underlying(): BelongsTo
    {
        return $this->belongsTo(Underlying::class);
    }
}
