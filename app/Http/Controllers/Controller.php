<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — Base Controller
//  Location: app/Http/Controllers/Controller.php
//
//  Every controller extends this. Two shared helpers:
//
//  authorizePermission('cv.upload')
//      Stops the request with 403 unless the signed-in user holds
//      the permission key (config/permissions.php). Prefer the route
//      middleware `can:key` for whole routes; use this inside an
//      action when the check depends on the record.
//
//  logDeletion($model, 'Beneficiary #12 — Sara Mostafa')
//      Writes a permanent trace (who, when, snapshot) BEFORE a record
//      is deleted — call it inside the same DB::transaction() as the
//      delete, so a delete can never succeed without leaving a trace.
//      Beneficiary data is personal data; "gone with no trace" is
//      never acceptable (Scope v2 §7).
// ══════════════════════════════════════════════════════════════════

abstract class Controller
{
    protected function authorizePermission(string $key): void
    {
        abort_unless(auth()->user()?->can($key) === true, 403, __('errors.forbidden'));
    }

    protected function logDeletion(Model $model, string $summary, array $extra = []): void
    {
        \App\Support\DeletionLogger::log($model, $summary, $extra);
    }
}
