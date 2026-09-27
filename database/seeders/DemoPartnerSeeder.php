<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

// ══════════════════════════════════════════════════════════════════
//  Massar — DemoPartnerSeeder  (LOCAL / TESTING ONLY)
//  Location: database/seeders/DemoPartnerSeeder.php
//
//  One demo partner organisation — "Al-Amal Foundation", the partner
//  used in all the mockups — with a Company Admin and a Case Worker,
//  so every screen can be tried straight after `migrate --seed`.
//
//    admin@alamal.test       → company_admin (Rana Hassan)
//    caseworker@alamal.test  → employee      (Karim Adel, Case Worker)
//
//  Password for both: DEFAULT_PASSWORD from .env (the same one the
//  super admin seeder uses). Both are pre-verified.
//
//  DatabaseSeeder only calls this outside production. It is safe to
//  re-run: accounts are matched by email and passwords are never reset.
// ══════════════════════════════════════════════════════════════════

class DemoPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Al-Amal Foundation'],
            [
                'name_ar'              => 'مؤسسة الأمل',
                'type'                 => 'ngo',
                'governorate'          => 'cai',
                'contact_email'        => 'info@alamal.test',
                'seat_limit'           => 10,
                'subscription_ends_at' => now()->addYear(),
            ]
        );

        $people = [
            ['admin@alamal.test', 'Rana Hassan', UserRole::CompanyAdmin, 'Programme Manager'],
            ['caseworker@alamal.test', 'Karim Adel', UserRole::Employee, 'Case Worker'],
        ];

        foreach ($people as [$email, $name, $role, $title]) {
            $user = User::firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;

            $user->fill([
                'name'       => $name,
                'role'       => $role->value,
                'job_title'  => $title,
                'company_id' => $company->id,
                'language'   => 'en',
                'is_active'  => true,
            ]);
            $user->email_verified_at ??= now();

            if ($isNew) {
                $user->password = config('app.default_password');
            }

            $user->save();
        }

        $this->command?->info('✅ Demo partner ready: Al-Amal Foundation (admin@alamal.test, caseworker@alamal.test).');
    }
}
