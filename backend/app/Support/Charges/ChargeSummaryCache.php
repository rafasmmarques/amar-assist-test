<?php

namespace App\Support\Charges;

use App\Models\Charge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ChargeSummaryCache
{
    public const TTL_SECONDS = 60;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function remember(int $userId, array $filters, Builder $query): array
    {
        return Cache::remember($this->key($userId, $filters), self::TTL_SECONDS, function () use ($query): array {
            $charges = (clone $query)->get(['id', 'status', 'payment_method']);

            return [
                'total' => $charges->count(),
                'by_status' => [
                    Charge::STATUS_OPEN => $charges->where('status', Charge::STATUS_OPEN)->count(),
                    Charge::STATUS_PAID => $charges->where('status', Charge::STATUS_PAID)->count(),
                ],
                'by_payment_method' => [
                    Charge::PAYMENT_METHOD_BOLETO => $charges->where('payment_method', Charge::PAYMENT_METHOD_BOLETO)->count(),
                    Charge::PAYMENT_METHOD_PIX => $charges->where('payment_method', Charge::PAYMENT_METHOD_PIX)->count(),
                    Charge::PAYMENT_METHOD_CARD => $charges->where('payment_method', Charge::PAYMENT_METHOD_CARD)->count(),
                ],
            ];
        });
    }

    public function invalidate(): void
    {
        Cache::forever($this->versionKey(), $this->version() + 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function key(int $userId, array $filters): string
    {
        ksort($filters);

        return sprintf(
            'charges:summary:user:%d:filters:%s:v:%d',
            $userId,
            sha1(json_encode($filters, JSON_THROW_ON_ERROR)),
            $this->version()
        );
    }

    private function version(): int
    {
        return (int) Cache::get($this->versionKey(), 1);
    }

    private function versionKey(): string
    {
        return 'charges:summary:version';
    }
}
