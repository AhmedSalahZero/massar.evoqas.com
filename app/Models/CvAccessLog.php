<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvAccessLog (who opened or downloaded an original CV)
//  Location: app/Models/CvAccessLog.php
//  Scope v2 §7 Personal Data Protection — written by
//  App\CvFileController on every download / view. Never edited.
// ══════════════════════════════════════════════════════════════════

class CvAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
