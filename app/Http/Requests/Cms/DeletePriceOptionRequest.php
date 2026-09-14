<?php

namespace App\Http\Requests\Cms;

use App\Models\PriceOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeletePriceOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priceOption = $this->priceOption();

        return $priceOption !== null && $this->user()?->can('delete', $priceOption) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->priceOption()?->name])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik nama paket persis untuk mengonfirmasi penghapusan.'];
    }

    private function priceOption(): ?PriceOption
    {
        $priceOption = $this->route('priceOption');

        return $priceOption instanceof PriceOption ? $priceOption : null;
    }
}
