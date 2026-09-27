<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReportDownload (Step 14 · Reports)
//  Location: app/Models/ReportDownload.php
//
//  One line per Excel or PDF download: who, when, which question.
//  company_id is null for the Super Admin's reports across partners.
// ══════════════════════════════════════════════════════════════════

class ReportDownload extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['params' => 'array', 'created_at' => 'datetime'];
}
