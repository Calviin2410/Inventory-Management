<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function () {
    $name = trim((string) $this->ask('Administrator name'));
    $email = trim((string) $this->ask('Administrator email'));
    $password = (string) $this->secret('Password (minimum 8 characters)');
    $confirmation = (string) $this->secret('Confirm password');

    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $confirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', 'min:8'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    User::create([
        'name' => $name,
        'email' => strtolower($email),
        'password' => Hash::make($password),
        'role' => 'admin',
    ]);

    $this->info('Administrator account created successfully.');

    return 0;
})->purpose('Create an administrator account securely');
