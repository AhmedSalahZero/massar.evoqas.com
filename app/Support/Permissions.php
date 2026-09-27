<?php

namespace App\Support;

use App\Models\User;

// ══════════════════════════════════════════════════════════════════
//  Massar — Permissions
//  Location: app/Support/Permissions.php
//
//  Reads config/permissions.php and answers one question:
//  "does this user hold this permission key?"
//
//  Registered as a Gate::before() hook in AppServiceProvider, so
//  every normal Laravel check works with our keys:
//      $user->can('cv.upload')
//      Gate::authorize('team.manage')
//      Route::middleware('can:platform.companies')
//      @can('reports.export') in Blade
//
//  Rules:
//    · Unknown keys are ignored here (return null), so Laravel's own
//      policies and gates still work for anything that is not ours.
//    · A user's own users.permissions (JSON array) wins when set.
//      NULL → role defaults. This is what lets the Permission Builder
//      arrive at the end of the build without touching any screen.
//    · Inactive users hold nothing.
//
//  The resolved list is memoised per user per request, so checking
//  many keys on one page costs one config read, not many.
// ══════════════════════════════════════════════════════════════════

final class Permissions
{
    /** @var array<int, array<int, string>> */
    private static array $memo = [];

    /**
     * Every registered key, flat: ['beneficiaries.view', …].
     */
    public static function all(): array
    {
        $keys = [];

        foreach (config('permissions.modules', []) as $module) {
            $keys = array_merge($keys, array_keys($module['keys'] ?? []));
        }

        return $keys;
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * The keys this user holds.
     */
    public static function for(User $user): array
    {
        if (isset(self::$memo[$user->id])) {
            return self::$memo[$user->id];
        }

        if (! $user->is_active) {
            return self::$memo[$user->id] = [];
        }

        $granted = is_array($user->permissions)
            ? $user->permissions
            : self::expand(config("permissions.defaults.{$user->role}", []));

        return self::$memo[$user->id] = array_values(array_intersect(self::all(), $granted));
    }

    /**
     * Gate::before hook. Returns true/false for our keys, null for
     * anything else (so other gates and policies still run).
     */
    public static function check(User $user, string $ability): ?bool
    {
        if (! self::exists($ability)) {
            return null;
        }

        return in_array($ability, self::for($user), true);
    }

    /**
     * Turn "beneficiaries.*" into every key of that module, and
     * module names like "insights.*" into that module's keys.
     */
    public static function expand(array $patterns): array
    {
        $all = self::all();
        $out = [];

        foreach ($patterns as $pattern) {
            if (! str_ends_with($pattern, '.*')) {
                $out[] = $pattern;
                continue;
            }

            $prefix = substr($pattern, 0, -2);
            $moduleKeys = array_keys(config("permissions.modules.{$prefix}.keys", []));

            $out = array_merge(
                $out,
                $moduleKeys,
                array_filter($all, fn ($k) => str_starts_with($k, $prefix.'.'))
            );
        }

        return array_values(array_unique($out));
    }

    /**
     * Clear the per-request memo — call after changing a user's role
     * or permissions within the same request (and in tests).
     */
    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$memo = [];

            return;
        }

        unset(self::$memo[$userId]);
    }
}
