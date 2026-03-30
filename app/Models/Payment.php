<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'md5',
        'qr_code',
        'amount',
        'currency',
        'bill_number',
        'mobile_number',
        'store_label',
        'terminal_label',
        'merchant_name',
        'status',
        'bakong_response',
        'transaction_id',
        'expires_at',
        'paid_at',
        'telegram_sent',
        'check_attempts',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bakong_response' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'telegram_sent' => 'boolean',
    ];

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsSuccess(?array $bakongResponse = null, ?string $transactionId = null): void
    {
        $this->update([
            'status' => 'SUCCESS',
            'paid_at' => now(),
            'bakong_response' => $bakongResponse,
            'transaction_id' => $transactionId,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'EXPIRED']);
    }

    public function incrementCheckAttempts(): void
    {
        $this->increment('check_attempts');
        $this->update(['last_checked_at' => now()]);
    }

    public function markTelegramSent(): void
    {
        $this->update(['telegram_sent' => true]);
    }
}