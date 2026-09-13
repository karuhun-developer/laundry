<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MessageTemplates\BuildOrderWhatsAppSuggestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowOrderWhatsAppSuggestionRequest;
use App\Http\Resources\WhatsAppSuggestionResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;

class OrderWhatsAppSuggestionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ShowOrderWhatsAppSuggestionRequest $request,
        Organization $organization,
        Branch $branch,
        Order $order,
        BuildOrderWhatsAppSuggestion $buildOrderWhatsAppSuggestion,
    ): WhatsAppSuggestionResource {
        return new WhatsAppSuggestionResource($buildOrderWhatsAppSuggestion->handle($order));
    }
}
