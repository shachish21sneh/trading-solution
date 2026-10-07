<?php

namespace App\Events;

use App\Models\MarketAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketAlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MarketAlert $alert
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("alerts.{$this->alert->symbol}"),
            new Channel("alerts.global"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MarketAlertCreated';
    }
}
