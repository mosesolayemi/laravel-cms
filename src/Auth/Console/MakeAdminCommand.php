<?php

namespace PhynixMedia\Cms\Auth\Console;

use Closure;
use PhynixMedia\Cms\Auth\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

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
        /*
         * Each answer is validated by its own prompt, so a password that is
         * too short asks again instead of throwing away the three answers
         * before it. The whole set is still validated below, because the
         * options bypass the prompts entirely.
         */
        $data = [
            'firstname' => $this->option('firstname') ?: text('First name', required: true, validate: $this->rule('firstname')),
            'lastname' => $this->option('lastname') ?: text('Last name', required: true, validate: $this->rule('lastname')),
            'email' => $this->option('email') ?: text('Email address', required: true, validate: $this->rule('email')),
            'password' => $this->option('password') ?: $this->askForPassword(),
        ];

        $validator = Validator::make($data, $this->rules());

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

    /**
     * Ask for the password in the clear.
     *
     * Typed openly rather than masked: this runs once, on a machine the
     * person installing already controls, and a mistyped password they
     * cannot see costs more here than the shoulder-surfing it avoids.
     * It is hashed by the model before it reaches the database.
     */
    protected function askForPassword(): string
    {
        return text(
            label: 'Password',
            hint: 'At least 8 characters. Shown as you type so you can check it.',
            required: true,
            validate: $this->rule('password'),
        );
    }

    /**
     * A prompt validator for one field.
     *
     * Laravel Prompts re-asks whenever this returns a message, which is the
     * whole point: an invalid answer is a typo to correct, not a reason to
     * abandon the command.
     */
    protected function rule(string $field): Closure
    {
        return function (string $value) use ($field): ?string {
            $validator = Validator::make([$field => $value], [$field => $this->rules()[$field]]);

            return $validator->fails() ? $validator->errors()->first($field) : null;
        };
    }

    /**
     * @return array<string, string[]>
     */
    protected function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
