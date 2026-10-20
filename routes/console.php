<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
Artisan::command('app:create-superadmin', function () {
    if (User::where('role', 'superadmin')->exists()) { $this->error('Superadmin sudah tersedia.'); return 1; }
    $name = text('Nama superadmin', required: true);
    $email = text('Email superadmin', required: true);
    $secret = password('Kata sandi (minimal 12 karakter)', required: true);
    $confirmation = password('Ulangi kata sandi', required: true);
    $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $secret, 'password_confirmation' => $confirmation], [
        'name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12|confirmed',
    ]);
    if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return 1; }
    User::create(['name' => $name, 'email' => $email, 'password' => $secret, 'role' => 'superadmin', 'is_active' => true]);
    $this->info('Superadmin berhasil dibuat.');
})->purpose('Membuat superadmin pertama tanpa kredensial bawaan');
