<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OptionChainUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $symbol,
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("option-chain.{$this->symbol}"),
            new Channel('option-chain.global'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OptionChainUpdated';
    }
}
