<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
class CreateAdmin extends Command
{
    protected $signature = 'meeting:admin {--email=} {--name=Admin}';
    protected $description = 'Buat atau perbarui administrator dengan password interaktif';
    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email admin');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Email tidak valid.'); return self::FAILURE; }
        $password = $this->secret('Password admin (min. 12 karakter)');
        if (strlen((string) $password) < 12) { $this->error('Password minimal 12 karakter.'); return self::FAILURE; }
        User::updateOrCreate(['email'=>$email], [
            'name'=>$this->option('name'), 'password'=>$password,
            'is_admin'=>true, 'can_view_history'=>true, 'can_manage_settings'=>true, 'is_active'=>true,
        ]);
        $this->info('Administrator berhasil dibuat/diperbarui: '.$email);
        return self::SUCCESS;
    }
}
