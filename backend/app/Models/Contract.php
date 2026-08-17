<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    public const PERSON_TYPE_PF = 'PF';

    public const PERSON_TYPE_PJ = 'PJ';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'client_id',
        'person_type',
        'billing_cycle_day',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
