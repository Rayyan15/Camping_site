<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccessSynchronizer;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

class CreateOwner extends Command
{
    protected $signature = 'app:create-owner {email : Login email} {name : Display name}';

    protected $description = 'Create the owner account. The password is prompted, or read from OWNER_PASSWORD.';

    public function handle(AccessSynchronizer $access, UserAccountService $accounts): int
    {
        $minLength = (int) config('access.min_password_length');
        $secret = config('access.owner_bootstrap_password')
            ?: password('Password (minimal '.$minLength.' karakter)', required: true);

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $secret],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:'.$minLength],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $access->sync();
        $accounts->create($validator->validated(), User::ROLE_OWNER);

        $this->components->info('Akun owner dibuat. Aktifkan autentikasi dua langkah saat login pertama.');

        return self::SUCCESS;
    }
}
