<?php

namespace App\Console\Commands;

use App\Actions\Auth\CreateAdmin;
use App\Enums\AdminRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('store:create-admin {--name=} {--phone=} {--role=super_admin}')]
#[Description('إنشاء حساب مدير جديد للوحة الإدارة')]
class CreateAdminCommand extends Command
{
    public function handle(CreateAdmin $createAdmin): int
    {
        $name = $this->option('name') ?: $this->ask('اسم المدير');
        $phone = PhoneNumber::normalize($this->option('phone') ?: $this->ask('رقم الجوال (05XXXXXXXX)'));
        $password = $this->secret('كلمة المرور (8 أحرف على الأقل)');

        $validator = Validator::make(
            ['name' => $name, 'phone' => $phone, 'password' => $password, 'role' => $this->option('role')],
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'regex:'.PhoneNumber::PATTERN, Rule::unique(User::class, 'phone')],
                'password' => ['required', Password::min(8)->letters()->numbers()],
                'role' => ['required', Rule::enum(AdminRole::class)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $createAdmin->handle($name, $phone, $password, AdminRole::from($this->option('role')));

        $this->info("تم إنشاء المدير {$user->name} ({$user->phone}).");

        return self::SUCCESS;
    }
}
