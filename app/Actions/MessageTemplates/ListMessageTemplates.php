<?php

namespace App\Actions\MessageTemplates;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ListMessageTemplates
{
    /**
     * @param  EloquentCollection<int, Branch>  $branches
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(User $actor, EloquentCollection $branches): Collection
    {
        if ($actor->hasRole(RoleName::Superadmin->value)) {
            return MessageTemplate::query()
                ->with(['organization:id,name', 'branch:id,name'])
                ->orderBy('organization_id')
                ->orderBy('scope_key')
                ->orderBy('order_status')
                ->get()
                ->map(fn (MessageTemplate $template): array => $this->summary($template));
        }

        $templates = MessageTemplate::query()
            ->with('organization:id,name')
            ->where('organization_id', $actor->organization_id)
            ->get();
        $organizationTemplates = $templates
            ->whereNull('branch_id')
            ->keyBy(fn (MessageTemplate $template): string => $template->order_status->value);
        $branchTemplates = $templates
            ->whereNotNull('branch_id')
            ->keyBy(fn (MessageTemplate $template): string => $this->branchStatusKey(
                (int) $template->branch_id,
                $template->order_status,
            ));
        $rows = collect();

        foreach ($branches as $branch) {
            foreach (OrderStatus::cases() as $status) {
                $override = $branchTemplates->get($this->branchStatusKey($branch->id, $status));
                $template = $override ?? $organizationTemplates->get($status->value);

                if (! $template instanceof MessageTemplate) {
                    continue;
                }

                $rows->push($this->summary(
                    template: $template,
                    branch: $branch,
                    isInherited: ! $override instanceof MessageTemplate,
                    rowKey: $this->branchStatusKey($branch->id, $status),
                ));
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function summary(
        MessageTemplate $template,
        ?Branch $branch = null,
        bool $isInherited = false,
        ?string $rowKey = null,
    ): array {
        $effectiveBranch = $branch ?? $template->branch;

        return [
            'id' => $template->id,
            'row_key' => $rowKey ?? 'template:'.$template->id,
            'organization_id' => $template->organization_id,
            'branch_id' => $effectiveBranch?->id,
            'scope_key' => $effectiveBranch === null ? 'organization' : 'branch:'.$effectiveBranch->id,
            'order_status' => $template->order_status->value,
            'body' => $template->body,
            'is_active' => $template->is_active,
            'is_inherited' => $isInherited,
            'organization' => [
                'id' => $template->organization->id,
                'name' => $template->organization->name,
            ],
            'branch' => $effectiveBranch === null ? null : [
                'id' => $effectiveBranch->id,
                'name' => $effectiveBranch->name,
            ],
        ];
    }

    private function branchStatusKey(int $branchId, OrderStatus $status): string
    {
        return sprintf('branch:%d:%s', $branchId, $status->value);
    }
}
