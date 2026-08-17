<?php

namespace App\Http\Controllers;

use App\Actions\Charges\GenerateCharge;
use App\Actions\Charges\PayCharge;
use App\Exceptions\ChargeGenerationConflict;
use App\Http\Requests\Charges\GenerateChargeRequest;
use App\Http\Requests\Charges\IndexChargeRequest;
use App\Http\Requests\Charges\PayChargeRequest;
use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChargeController extends Controller
{
    public function index(IndexChargeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Charge::class);

        $filters = $request->validated();
        $referenceDate = now('America/Sao_Paulo')->toDateString();
        $charges = Charge::query()
            ->with('contract.client')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['payment_method']), fn ($query) => $query->where('payment_method', $filters['payment_method']))
            ->when(isset($filters['contract']), fn ($query) => $query->where('contract_id', $filters['contract']))
            ->when(isset($filters['client']), fn ($query) => $query->whereHas('contract', fn ($contractQuery) => $contractQuery->where('client_id', $filters['client'])))
            ->when(isset($filters['due_from']), fn ($query) => $query->whereDate('due_date', '>=', $filters['due_from']))
            ->when(isset($filters['due_to']), fn ($query) => $query->whereDate('due_date', '<=', $filters['due_to']))
            ->orderByRaw(
                'CASE
                    WHEN status = ? AND due_date < ? THEN 0
                    WHEN status = ? THEN 1
                    ELSE 2
                END',
                [Charge::STATUS_OPEN, $referenceDate, Charge::STATUS_OPEN]
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => ChargeResource::collection($charges->getCollection())->resolve(),
            'meta' => [
                'current_page' => $charges->currentPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
                'last_page' => $charges->lastPage(),
            ],
            'links' => [
                'first' => $charges->url(1),
                'last' => $charges->url($charges->lastPage()),
                'prev' => $charges->previousPageUrl(),
                'next' => $charges->nextPageUrl(),
            ],
        ]);
    }

    public function show(Charge $charge): ChargeResource
    {
        $this->authorize('view', $charge);

        return ChargeResource::make($charge->load('paymentDetail'));
    }

    public function generate(GenerateChargeRequest $request): JsonResponse
    {
        $this->authorize('create', Charge::class);

        try {
            $result = app(GenerateCharge::class)->execute($request->validated());
        } catch (ChargeGenerationConflict $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        return ChargeResource::make($result['charge'])->additional([
            'message' => $result['created'] ? 'Cobranca gerada com sucesso.' : 'Cobranca ja existente retornada.',
        ])->response()->setStatusCode($result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function pay(PayChargeRequest $request, Charge $charge): JsonResponse
    {
        $this->authorize('pay', $charge);

        $paidCharge = app(PayCharge::class)->execute($charge, $request->header('Idempotency-Key'));

        return ChargeResource::make($paidCharge)->additional([
            'message' => 'Cobranca paga com sucesso.',
        ])->response();
    }
}
