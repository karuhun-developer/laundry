<?php

namespace App\Data;

use App\Enums\OrderComplaintStatus;
use App\Http\Requests\Api\V1\StoreOrderComplaintRequest;

final readonly class OrderComplaintData
{
    public function __construct(
        public ?int $complaintId,
        public ?string $category,
        public ?string $description,
        public OrderComplaintStatus $status,
        public ?string $note,
    ) {}

    public static function fromRequest(StoreOrderComplaintRequest $request): self
    {
        return new self($request->filled('complaint_id') ? $request->integer('complaint_id') : null,
            $request->input('category'), $request->input('description'),
            OrderComplaintStatus::from($request->input('status', 'new')), $request->input('note'));
    }
}
