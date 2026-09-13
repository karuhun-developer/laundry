<?php

namespace App\Http\Controllers\Cms;

use App\Actions\MessageTemplates\CreateMessageTemplate;
use App\Actions\MessageTemplates\DeleteMessageTemplate;
use App\Actions\MessageTemplates\UpdateMessageTemplate;
use App\Data\MessageTemplateData;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteMessageTemplateRequest;
use App\Http\Requests\Cms\StoreMessageTemplateRequest;
use App\Http\Requests\Cms\UpdateMessageTemplateRequest;
use App\Models\Branch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Rules\SupportedMessageTemplateVariables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        /** @var User $actor */
        $actor = request()->user();
        $isSuperadmin = $actor->hasRole(RoleName::Superadmin->value);

        return Inertia::render('cms/MessageTemplates', [
            'messageTemplates' => MessageTemplate::query()
                ->with(['organization:id,name', 'branch:id,name'])
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->orderBy('organization_id')
                ->orderBy('scope_key')
                ->orderBy('order_status')
                ->get(),
            'branches' => Branch::query()
                ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->orderBy('name')
                ->get(['id', 'organization_id', 'name']),
            'organizations' => $isSuperadmin
                ? Organization::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'orderStatuses' => array_map(
                static fn (OrderStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                OrderStatus::cases(),
            ),
            'supportedVariables' => array_map(
                static fn (string $variable): string => '{'.$variable.'}',
                SupportedMessageTemplateVariables::VARIABLES,
            ),
        ]);
    }

    public function store(
        StoreMessageTemplateRequest $request,
        CreateMessageTemplate $createMessageTemplate,
    ): RedirectResponse {
        $createMessageTemplate->handle(MessageTemplateData::fromRequest($request));

        return back()->with('success', 'Template WhatsApp berhasil dibuat.');
    }

    public function update(
        UpdateMessageTemplateRequest $request,
        MessageTemplate $messageTemplate,
        UpdateMessageTemplate $updateMessageTemplate,
    ): RedirectResponse {
        $updateMessageTemplate->handle($messageTemplate, MessageTemplateData::fromRequest($request));

        return back()->with('success', 'Template WhatsApp berhasil diperbarui.');
    }

    public function destroy(
        DeleteMessageTemplateRequest $request,
        MessageTemplate $messageTemplate,
        DeleteMessageTemplate $deleteMessageTemplate,
    ): RedirectResponse {
        $deleteMessageTemplate->handle($messageTemplate);

        return back()->with('success', 'Template WhatsApp berhasil dihapus.');
    }
}
