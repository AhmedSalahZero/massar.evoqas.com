<?php

namespace App\Enums;

// ══════════════════════════════════════════════════════════════════
//  Massar — LoginActivityType
//  Location: app/Enums/LoginActivityType.php
//
//  The two kinds of row in user_login_activities: an explicit sign-in
//  (Login) and the first page of a new day (DailyAccess). See
//  UserLoginFrequencyService.
// ══════════════════════════════════════════════════════════════════

enum LoginActivityType: string
{
    case Login       = 'login';
    case DailyAccess = 'daily_access';
}
