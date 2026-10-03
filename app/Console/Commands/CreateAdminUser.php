<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nombre completo}
        {--email= : Correo electrónico}
        {--password= : Contraseña de al menos 8 caracteres}';

    protected $description = 'Crea o actualiza el usuario administrador inicial';

    public function handle(): int
    {
        $name = $this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Nombre completo') : null);
        $email = $this->option('email') ?: ($this->input->isInteractive() ? $this->ask('Correo electrónico') : null);
        $password = $this->option('password') ?: ($this->input->isInteractive() ? $this->secret('Contraseña') : null);

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => mb_strtolower((string) $email)],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->info("Administrador {$user->email} listo para iniciar sesión.");

        return self::SUCCESS;
    }
}
