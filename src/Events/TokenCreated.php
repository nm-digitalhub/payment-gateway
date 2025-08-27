<?php

namespace NMDigitalHub\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\PaymentToken;

class TokenCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PaymentToken $token,
        public readonly User $user,
        public readonly string $provider,
        public readonly array $metadata = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get token details for logging
     */
    public function getTokenDetails(): array
    {
        return [
            'token_id' => $this->token->id,
            'user_id' => $this->user->id,
            'provider' => $this->provider,
            'masked_card' => $this->token->last_four ? '****' . $this->token->last_four : null,
            'expires_at' => $this->token->expires_at?->format('Y-m-d'),
            'is_default' => $this->token->is_default,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if this is the user's first token
     */
    public function isFirstToken(): bool
    {
        return $this->user->paymentTokens()->count() === 1;
    }
}