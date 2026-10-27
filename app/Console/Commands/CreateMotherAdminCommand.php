<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateMotherAdminCommand extends Command
{
    protected $signature = 'admin:create-mother {--name=} {--email=} {--password=} {--force : Overwrite an existing mother admin without confirmation}';

    protected $description = 'Create the mother (super) admin account, replacing the existing one if present';

    public function handle(): int
    {
        $existing = Admin::where('is_mother', true)->first();

        if ($existing && ! $this->option('force') && ! confirm(
            label: "A mother admin already exists ({$existing->email}). Replace it with the new details?",
            default: false,
        )) {
            $this->info('Aborted. No changes were made.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?? text(
            label: 'Admin name',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'Admin email',
            required: true,
        );

        $password = $this->option('password') ?? password(
            label: 'Admin password',
            required: true,
            hint: 'Minimum 8 characters.',
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:admins,email,'.($existing?->id ?? 'NULL')],
                'password' => ['required', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ];

        // is_mother is deliberately not mass-assignable (see Admin::$fillable)
        // — this command is the one legitimate place it's ever set, via
        // forceFill(), so no request path can self-promote an admin.
        if ($existing) {
            $existing->fill($attributes)->forceFill(['is_mother' => true])->save();
            $this->info("Mother admin account replaced. New email: {$email}.");
        } else {
            $admin = Admin::make($attributes);
            $admin->forceFill(['is_mother' => true])->save();
            $this->info("Mother admin account created for {$email}.");
        }

        return self::SUCCESS;
    }
}
