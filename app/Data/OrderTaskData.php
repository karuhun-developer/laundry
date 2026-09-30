<?php

namespace App\Data;

use App\Enums\OrderTaskStatus;
use App\Http\Requests\Api\V1\StoreOrderTaskRequest;

final readonly class OrderTaskData
{
    public function __construct(
        public ?int $taskId,
        public string $kind,
        public OrderTaskStatus $status,
        public ?string $operatorName,
        public ?string $scheduledAt,
        public ?string $address,
        public ?string $recipientName,
        public ?string $note,
    ) {}

    public static function fromRequest(StoreOrderTaskRequest $request): self
    {
        return new self($request->filled('task_id') ? $request->integer('task_id') : null,
            $request->string('kind')->toString(), OrderTaskStatus::from($request->string('status')->toString()),
            $request->input('operator_name'), $request->input('scheduled_at'), $request->input('address'),
            $request->input('recipient_name'), $request->input('note'));
    }
}
