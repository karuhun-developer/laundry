<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class ShowOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user()?->can('view', $order) === true;
    }

    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }
}
