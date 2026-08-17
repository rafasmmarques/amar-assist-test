<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request): bool => $request->user() !== null
            && Gate::forUser($request->user())->allows('viewHorizon'));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => true);
    }
}
