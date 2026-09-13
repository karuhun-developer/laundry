<?php

namespace App\Http\Requests\Cms;

use App\Models\City;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $city = $this->city();

        return $city !== null && $this->user()?->can('delete', $city) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in([$this->city()?->name])]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Ketik nama kota persis untuk mengonfirmasi penghapusan.'];
    }

    private function city(): ?City
    {
        $city = $this->route('city');

        return $city instanceof City ? $city : null;
    }
}
