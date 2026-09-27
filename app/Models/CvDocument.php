<?php

namespace App\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvDocument (one uploaded CV file)
//  Location: app/Models/CvDocument.php
//  Table and statuses: see database/migrations/…_create_cv_tables.php
//
//  Written only by App\Services\Cv\CvIntake (upload) and
//  App\ReviewQueueController (review). The original file is read and
//  written only through App\Services\Cv\CvStorage (encrypted).
//  Every screen looks documents up with inWorkspace($companyId).
// ══════════════════════════════════════════════════════════════════

class CvDocument extends Model
{
    use BelongsToCompany;

    public const ADDED = 'added';
    public const REVIEW = 'review';
    public const DUPLICATE = 'duplicate';
    public const UNREADABLE = 'unreadable';
    public const APPROVED = 'approved';
    public const ATTACHED = 'attached';
    public const REJECTED = 'rejected';

    /** Waiting for a person (the review queue). */
    public const OPEN = [self::REVIEW, self::DUPLICATE, self::UNREADABLE];

    /** Linked to a profile. */
    public const ON_PROFILE = [self::ADDED, self::APPROVED, self::ATTACHED];

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['text', 'stored_path', 'sha256'];

    protected $casts = [
        'reading'     => 'array',
        'duplicates'  => 'array',
        'size'        => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A CV added to (or taken from) a profile: that profile's search index is refreshed.
        static::saved(function (CvDocument $d) {
            if (! $d->wasChanged(['beneficiary_id', 'status', 'text']) && ! $d->wasRecentlyCreated) {
                return;
            }
            foreach (array_unique(array_filter([$d->beneficiary_id, $d->getOriginal('beneficiary_id')])) as $id) {
                if ($b = Beneficiary::query()->withoutGlobalScopes()->find($id)) {
                    app(\App\Services\Cv\CvBankIndex::class)->refresh($b);
                }
            }
        });
    }

    public function scopeInWorkspace(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** The name the reading engine found, for lists. */
    public function foundName(?string $locale = null): ?string
    {
        $form = $this->reading['form'] ?? [];
        $ar = $form['name_ar'] ?? null;
        $en = $form['name_en'] ?? null;

        return ($locale === 'ar' ? ($ar ?: $en) : ($en ?: $ar)) ?: null;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CvBatch::class, 'batch_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CvAccessLog::class)->orderByDesc('id');
    }
}
