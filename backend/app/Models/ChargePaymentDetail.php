<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargePaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_id',
        'boleto_barcode',
        'pix_key',
        'pix_transaction_id',
        'card_reference',
        'card_brand',
        'card_last4',
    ];

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
