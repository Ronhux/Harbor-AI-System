<?php

namespace App\Rules;

use App\Models\User;
use App\Support\PhilippineMobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a contact number that normalizes to another account's canonical
 * login number.
 *
 * This is the friendly pre-check for the database UNIQUE constraint on
 * `users.login_number`, so a profile update reports a clear field error
 * instead of surfacing an unhandled constraint violation. Values that are not
 * valid Philippine mobile numbers (blank, landline, free text) are left alone:
 * they do not claim a canonical login number, so they cannot collide.
 */
class LoginNumberNotTaken implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $canonical = PhilippineMobile::normalize($value);

        if ($canonical === null || !User::hasLoginNumberColumn()) {
            return;
        }

        $taken = User::query()
            ->where('login_number', $canonical)
            ->when($this->ignoreUserId !== null, fn ($query) => $query->where('user_id', '!=', $this->ignoreUserId))
            ->exists();

        if ($taken) {
            $fail('That mobile number is already used by another HarborAI account.');
        }
    }
}