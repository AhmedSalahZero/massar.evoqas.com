<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — Employer (a known company, Step 10.5)
//  Location: app/Models/Employer.php
//  Table: see database/migrations/…_create_sectors_and_employers_tables.php
//
//  company_id NULL  = the Massar list (from employers.xlsx), for everyone
//  company_id set   = learned by one partner workspace, only for it
//  Found through App\Services\Employers\EmployerBook (never queried
//  without visibleTo() from a screen).
// ══════════════════════════════════════════════════════════════════

class Employer extends Model
{
    protected $guarded = ['id'];

    public const OWNERSHIPS = ['state', 'private', 'foreign', 'partial'];

    /** The Massar list plus this workspace's own (never another partner's). */
    public function scopeVisibleTo(Builder $q, ?int $companyId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)));
    }

    public function displayName(?string $locale = null): string
    {
        $ar = ($locale ?? app()->getLocale()) === 'ar';

        return (string) ($ar ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar));
    }
}
