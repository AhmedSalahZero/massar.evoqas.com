<?php

namespace App\Enums;

// ══════════════════════════════════════════════════════════════════
//  Massar — UserRole
//  Location: app/Enums/UserRole.php
//
//  The three account types. Stored as a plain string in users.role
//  (see the users migration for why it is not a DB enum).
//
//    SuperAdmin   → Massar platform team (no company)
//    CompanyAdmin → a partner organisation's administrator
//    Employee     → a partner's staff member (case worker, officer …)
//
//  What each role may DO is not decided here — that is the job of
//  config/permissions.php and App\Support\Permissions.
// ══════════════════════════════════════════════════════════════════

enum UserRole: string
{
    case SuperAdmin   = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case Employee     = 'employee';

    public function label(?string $locale = null): string
    {
        $ar = ($locale ?? app()->getLocale()) === 'ar';

        return match ($this) {
            UserRole::SuperAdmin   => $ar ? 'المدير العام' : 'Super Admin',
            UserRole::CompanyAdmin => $ar ? 'مدير المؤسسة' : 'Company Admin',
            UserRole::Employee     => $ar ? 'موظف' : 'Employee',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
