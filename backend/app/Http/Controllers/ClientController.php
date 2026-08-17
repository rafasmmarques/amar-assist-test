<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clients\IndexClientRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(IndexClientRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $filters = $request->validated();
        $clients = Client::query()
            ->when(isset($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['document']), fn ($query) => $query->where('document', 'like', '%'.$filters['document'].'%'))
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => ClientResource::collection($clients->getCollection())->resolve(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'last_page' => $clients->lastPage(),
            ],
            'links' => [
                'first' => $clients->url(1),
                'last' => $clients->url($clients->lastPage()),
                'prev' => $clients->previousPageUrl(),
                'next' => $clients->nextPageUrl(),
            ],
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validated();
        $data['status'] = Client::STATUS_ACTIVE;

        $client = Client::create($data);

        return ClientResource::make($client)->additional([
            'message' => 'Cliente criado com sucesso.',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return ClientResource::make($client);
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return ClientResource::make($client->refresh())->additional([
            'message' => 'Cliente atualizado com sucesso.',
        ]);
    }

    public function activate(Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client->update(['status' => Client::STATUS_ACTIVE]);

        return ClientResource::make($client->refresh())->additional([
            'message' => 'Cliente ativado com sucesso.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function deactivate(Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client = DB::transaction(function () use ($client): Client {
            $lockedClient = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedClient->contracts()->exists()) {
                throw ValidationException::withMessages([
                    'client' => ['Cliente com contrato associado nao pode ser desativado.'],
                ]);
            }

            $lockedClient->update(['status' => Client::STATUS_INACTIVE]);

            return $lockedClient->refresh();
        });

        return ClientResource::make($client)->additional([
            'message' => 'Cliente desativado com sucesso.',
        ]);
    }
}
