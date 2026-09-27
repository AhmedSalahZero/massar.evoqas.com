<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — Sector (business sectors and sub-sectors, Step 10.5)
//  Location: app/Models/Sector.php
//  A sector (IND · TRD · SRV) has no parent; a sub-sector (I01, S01 …)
//  has parent = its sector. Loaded by `php artisan employers:import`.
// ══════════════════════════════════════════════════════════════════

class Sector extends Model
{
    protected $guarded = ['id'];

    /** The fixed list for the screens: [{code, name_en, name_ar, subs: [{code, name_en, name_ar}]}]. */
    public static function tree(): array
    {
        $all = static::query()->orderBy('sort')->get(['code', 'parent', 'name_en', 'name_ar']);

        return $all->whereNull('parent')->values()->map(fn (Sector $s) => [
            'code' => $s->code, 'name_en' => $s->name_en, 'name_ar' => $s->name_ar,
            'subs' => $all->where('parent', $s->code)->values()->map(fn (Sector $x) => ['code' => $x->code, 'name_en' => $x->name_en, 'name_ar' => $x->name_ar])->all(),
        ])->all();
    }
}
