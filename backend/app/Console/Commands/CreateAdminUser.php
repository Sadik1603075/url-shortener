<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('app:create-admin-user')]
#[Description('Create an administrator user')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $this->components->info('Create Admin User');

        $name = $this->ask('Name');

        if (blank($name)) {
            $this->components->error('Name is required.');

            return self::FAILURE;
        }

        $email = $this->ask('Email');

        $emailValidator = Validator::make(
            ['email' => $email],
            [
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email'),
                ],
            ]
        );

        if ($emailValidator->fails()) {
            $this->components->error(
                $emailValidator->errors()->first('email')
            );

            return self::FAILURE;
        }

        $password = $this->secret('Password');

        if (blank($password)) {
            $this->components->error('Password is required.');

            return self::FAILURE;
        }

        $passwordConfirmation = $this->secret(
            'Confirm password'
        );

        if ($password !== $passwordConfirmation) {
            $this->components->error(
                'Passwords do not match.'
            );

            return self::FAILURE;
        }

        $role = $this->choice(
            'Select administrator role',
            [
                'admin',
                'super_admin',
            ],
            0
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $this->newLine();

        $this->components->success(
            "Administrator [{$user->email}] created successfully."
        );

        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
            ]
        );

        return self::SUCCESS;
    }
}
