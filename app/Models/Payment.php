<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'donation_id',
        'payment_method_id',
        'transaction_reference',
        'payment_status',
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
        'check_attempts',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bakong_response' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
}
