<?php

namespace App\Data;

use App\Enums\OrderStatus;
use App\Http\Requests\Cms\StoreMessageTemplateRequest;
use App\Http\Requests\Cms\UpdateMessageTemplateRequest;

final readonly class MessageTemplateData
{
    public function __construct(
        public int $organizationId,
        public ?int $branchId,
        public string $scopeKey,
        public OrderStatus $orderStatus,
        public string $body,
        public bool $isActive,
    ) {}

    public static function fromRequest(
        StoreMessageTemplateRequest|UpdateMessageTemplateRequest $request,
    ): self {
        return new self(
            organizationId: $request->organizationId(),
            branchId: $request->branchId(),
            scopeKey: $request->scopeKey(),
            orderStatus: OrderStatus::from($request->string('order_status')->toString()),
            body: $request->string('body')->toString(),
            isActive: $request->boolean('is_active', true),
        );
    }
}
