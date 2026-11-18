<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name= : Full name} {--email= : Login email} {--password= : Password (omit to be prompted securely)}')]
#[Description('Create an administrator account (use this to bootstrap the first admin)')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Full name', required: true),
            'email' => $this->option('email') ?: text('Email address', required: true),
            'password' => $this->option('password') ?: password('Password (min. 8 characters)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        $this->components->info("Administrator {$user->email} created. You can now sign in.");

        return self::SUCCESS;
    }
}
