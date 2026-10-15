<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('demo:setup')]
#[Description('Creates (or reuses) a demo underwriter and prints a fresh API token for the dashboard.')]
class DemoSetup extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to create a demo user in production.');

            return self::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => 'underwriter@example.test'],
            ['name' => 'Demo Underwriter', 'password' => Str::random(40)],
        );

        $user->tokens()->where('name', 'demo')->delete();

        $this->line($user->createToken('demo')->plainTextToken);

        return self::SUCCESS;
    }
}
