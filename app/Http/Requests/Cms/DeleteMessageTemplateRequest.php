<?php

namespace App\Http\Requests\Cms;

use App\Models\MessageTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteMessageTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $messageTemplate = $this->messageTemplate();

        return $messageTemplate !== null
            && $this->user()?->can('delete', $messageTemplate) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in([$this->messageTemplate()?->deletionConfirmation()]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Ketik kode konfirmasi persis untuk menghapus template.',
        ];
    }

    private function messageTemplate(): ?MessageTemplate
    {
        $messageTemplate = $this->route('messageTemplate');

        return $messageTemplate instanceof MessageTemplate ? $messageTemplate : null;
    }
}
