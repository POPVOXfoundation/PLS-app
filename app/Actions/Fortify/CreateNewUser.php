<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, int|string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'password' => $this->passwordRules(),
        ], [
            'country_id.required' => 'Choose the country where you will create and manage PLS inquiries.',
            'country_id.exists' => 'Choose a valid country for your account.',
        ], [
            'country_id' => 'country',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'country_id' => (int) $input['country_id'],
            'password' => $input['password'],
        ]);
    }
}
