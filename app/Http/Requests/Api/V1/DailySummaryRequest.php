<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class DailySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['date' => ['sometimes', 'date_format:Y-m-d'], 'branch_id' => ['sometimes', 'integer', 'min:1'],
            'organization_id' => ['sometimes', 'integer', 'min:1']];
    }
}
