<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CvDocument;
use App\Models\JobSeeker;
use App\Services\Pool\TalentPool;
use App\Services\Cv\CvStorage;
use Illuminate\Console\Command;

// ══════════════════════════════════════════════════════════════════
//  Massar — pool:prune (Step 10, runs every night — routes/console.php)
//  Location: app/Console/Commands/PrunePool.php
//
//  Personal data is not kept longer than needed (Scope v2 §7):
//    · a CV uploaded on the public site whose registration (or "Replace
//      my CV") was never finished: deleted after 1 day
//    · an account whose email was never confirmed: deleted, with its
//      profile and CV, after 7 days (the email can then register again)
// ══════════════════════════════════════════════════════════════════

class PrunePool extends Command
{
    protected $signature = 'pool:prune';

    protected $description = 'Delete unfinished public registrations and CVs (Talent Pool)';

    public function handle(CvStorage $storage, TalentPool $pool): int
    {
        $poolId = Company::poolId();
        $cvs = 0;
        CvDocument::query()->withoutGlobalScopes()->where('company_id', $poolId)->whereNull('beneficiary_id')
            ->where('created_at', '<', now()->subDay())->orderBy('id')
            ->each(function (CvDocument $d) use ($storage, &$cvs) {
                $storage->delete($d->stored_path);
                $d->delete();
                $cvs++;
            });

        $accounts = 0;
        JobSeeker::query()->whereNull('email_verified_at')->where('created_at', '<', now()->subDays(7))->orderBy('id')
            ->each(function (JobSeeker $s) use ($pool, &$accounts) {
                $pool->delete($s);
                $accounts++;
            });

        $this->info("Deleted {$cvs} unfinished CV(s) and {$accounts} unconfirmed account(s).");

        return self::SUCCESS;
    }
}
