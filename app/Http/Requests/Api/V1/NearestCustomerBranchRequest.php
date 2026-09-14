<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RoleName;

class NearestCustomerBranchRequest extends NearestBranchRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Customer->value) === true;
    }
}
