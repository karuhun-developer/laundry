<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $email
 * @property string|null $whatsapp_phone
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $pin_hash
 * @property Carbon|null $pin_expires_at
 * @property bool $must_change_pin
 * @property int $session_version
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'email', 'whatsapp_phone', 'password', 'pin_hash', 'pin_expires_at', 'must_change_pin', 'session_version'])]
#[Hidden(['password', 'pin_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    /** @return BelongsToMany<Organization, $this> */
    public function customerOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'customer_organization', 'customer_id')
            ->withTimestamps();
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function setCustomerPin(string $pin, ?CarbonInterface $expiresAt = null, bool $mustChange = false): void
    {
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_expires_at' => $expiresAt,
            'must_change_pin' => $mustChange,
        ]);
    }

    public function customerPinMatches(string $pin): bool
    {
        if ($this->pin_hash === null || ! Hash::check($pin, $this->pin_hash)) {
            return false;
        }

        return $this->pin_expires_at === null || $this->pin_expires_at->isFuture();
    }

    /** @return HasMany<Order, $this> */
    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /** @return HasMany<Order, $this> */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_id');
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function customerOnly(Builder $query): Builder
    {
        return $query
            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery
                ->where('name', RoleName::Customer->value))
            ->whereDoesntHave('roles', fn (Builder $roleQuery): Builder => $roleQuery
                ->where('name', '!=', RoleName::Customer->value));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_expires_at' => 'datetime',
            'must_change_pin' => 'boolean',
            'session_version' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
