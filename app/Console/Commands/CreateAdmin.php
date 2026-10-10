<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin')]
#[Description('Create an administrator account, or promote an existing user')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = text('Email', required: true, validate: ['email' => 'email']);

        if ($user = User::query()->where('email', $email)->first()) {
            if ($user->is_admin) {
                $this->components->info("{$email} is already an administrator.");

                return self::SUCCESS;
            }

            if (! confirm("{$email} already has an account. Make it an administrator?")) {
                return self::FAILURE;
            }
        } else {
            $user = new User([
                'email' => $email,
                'name' => text('Name', required: true),
                'password' => password('Password', required: true, validate: ['password' => 'min:8']),
            ]);
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->components->info("{$email} is now an administrator. Log in at ".route('login'));

        return self::SUCCESS;
    }
}
