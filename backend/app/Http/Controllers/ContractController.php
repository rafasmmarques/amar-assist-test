<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CreateContract;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    public function index(Client $client): JsonResource
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = $client->contracts()->orderBy('id')->get();

        return ContractResource::collection($contracts);
    }

    public function store(StoreContractRequest $request, Client $client): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $data = $request->validated();
        $data['status'] ??= Contract::STATUS_ACTIVE;

        $contract = app(CreateContract::class)->execute($client, $data);

        return ContractResource::make($contract)->additional([
            'message' => 'Contrato criado com sucesso.',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Contract $contract): ContractResource
    {
        $this->authorize('view', $contract);

        return ContractResource::make($contract);
    }
}
