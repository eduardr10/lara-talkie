<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:32', Rule::unique(User::class, 'name')],
            'pin' => ['required', 'digits:6'],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'pin' => bcrypt($input['pin']),
        ]);
    }
}
