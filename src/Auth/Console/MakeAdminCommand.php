<?php

namespace Cms\Auth\Console;

use Cms\Auth\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first administrator, since there is no public sign-up for the
 * panel by design.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'auth:admin
        {--firstname= : The administrator\'s first name}
        {--lastname= : The administrator\'s last name}
        {--email= : The administrator\'s email address}
        {--password= : The administrator\'s password}';

    protected $description = 'Create a back-office administrator';

    public function handle(): int
    {
        $data = [
            'firstname' => $this->option('firstname') ?: text('First name', required: true),
            'lastname' => $this->option('lastname') ?: text('Last name', required: true),
            'email' => $this->option('email') ?: text('Email address', required: true),
            'password' => $this->option('password') ?: password('Password', required: true),
        ];

        $validator = Validator::make($data, [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create($validator->validated());

        $this->components->info("Administrator {$admin->email} created.");

        return self::SUCCESS;
    }
}
