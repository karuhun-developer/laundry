<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Services\CreateService;
use App\Data\ServiceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreServiceRequest;
use Illuminate\Http\RedirectResponse;

class ServiceController extends Controller
{
    public function store(StoreServiceRequest $request, CreateService $createService): RedirectResponse
    {
        $createService->handle(ServiceData::fromRequest($request));

        return back()->with('success', 'Layanan berhasil dibuat.');
    }
}
