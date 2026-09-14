<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Enums\RoleName;
use App\Models\User;
use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    public function __construct(
        private readonly WhatsAppPhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $normalizedPhone = $this->phoneNormalizer->normalize($input['whatsapp_phone'] ?? null);
        $input['whatsapp_phone'] = $normalizedPhone ?? ($input['whatsapp_phone'] ?? '');

        Validator::make($input, [
            'name' => $this->nameRules(),
            'whatsapp_phone' => [
                'required',
                'string',
                'max:24',
                new ValidWhatsAppPhone($this->phoneNormalizer),
                Rule::unique(User::class, 'whatsapp_phone'),
            ],
            'pin' => ['required', 'digits:6', 'confirmed'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => null,
            'whatsapp_phone' => $normalizedPhone,
            'password' => null,
            'session_version' => 1,
        ]);
        $user->setCustomerPin($input['pin']);
        $user->save();

        Role::findOrCreate(RoleName::Customer->value);
        $user->assignRole(RoleName::Customer->value);

        return $user;
    }
}
