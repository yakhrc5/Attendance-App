<?php

namespace App\Actions\Fortify;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /*use PasswordValidationRules;*/

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $request = app(RegisterRequest::class);

        Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
        )->validate();

        // 一般ユーザー登録画面から作成されるユーザーは role=user で固定する
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => 'user',
            'password' => Hash::make($input['password']),
        ]);
    }
}
