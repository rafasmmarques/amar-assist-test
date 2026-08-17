<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Charge extends Model
{
    use HasFactory;

    public const PAYMENT_METHOD_BOLETO = 'boleto';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_PIX = 'pix';

    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'uuid',
        'contract_id',
        'billing_period',
        'payment_method',
        'original_amount',
        'fixed_fee_amount',
        'due_date',
        'status',
        'paid_original_amount',
        'paid_fixed_fee_amount',
        'paid_late_interest_amount',
        'paid_total_amount',
        'paid_at',
        'idempotency_key',
    ];

    protected $casts = [
        'billing_period' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'original_amount' => 'string',
        'fixed_fee_amount' => 'string',
        'paid_original_amount' => 'string',
        'paid_fixed_fee_amount' => 'string',
        'paid_late_interest_amount' => 'string',
        'paid_total_amount' => 'string',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(ChargePaymentDetail::class);
    }
}
