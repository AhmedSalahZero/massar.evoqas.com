<?php

use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\BackboneController as AdminBackboneController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RuleRequestController as AdminRuleRequestController;
use App\Http\Controllers\App\BeneficiaryController;
use App\Http\Controllers\App\CvBankController;
use App\Http\Controllers\App\CvFileController;
use App\Http\Controllers\App\CvUploadController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\EligibilityController;
use App\Http\Controllers\App\OpportunityController;
use App\Http\Controllers\App\LearnedRuleController;
use App\Http\Controllers\App\MatchController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\OccupationController;
use App\Http\Controllers\App\PreferenceController;
use App\Http\Controllers\App\ReviewQueueController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\EmployerSearchController;
use App\Http\Controllers\App\IntakeController;
use App\Http\Controllers\App\PoolController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\JoinController;
use App\Http\Controllers\Public\MyProfileController;
use App\Http\Controllers\Public\SeekerAuthController;
use Illuminate\Support\Facades\Route;

// ══════════════════════════════════════════════════════════════════
//  Massar — Web Routes
//  Location: routes/web.php
//
//  Structure:
//    /                 → sends each person to the right place by role
//    /admin/*          → Massar platform (super_admin)     name: admin.*
//    /app/*            → partner workspace (company staff) name: app.*
//    /preferences/*    → theme / language / occupation standard —
//                        shared by both areas              name: preferences.*
//    routes/auth.php   → login, forgot/reset password, email code,
//                        confirm password, logout
//
//  Every signed-in group runs: auth → auth.session (sign out other
//  devices after a password change) → verified (email code, when
//  switched on) → admin|member → no-duplicate (double-submit guard).
//  Individual screens add `can:<permission.key>` from
//  config/permissions.php.
//
//  Modules that are in the scope but not built yet point to
//  ComingSoonController, which renders one shared "planned" page —
//  so the whole navigation works from day one and each module
//  replaces its line here when it is built.
// ══════════════════════════════════════════════════════════════════

// ── Root → role-based redirect ──────────────────────────────────
Route::get('/', HomeController::class)->name('home');

// ══════════════════════════════════════════════════════════════════
//  Public site for job seekers (Step 10). Separate from the staff
//  sign-in (/login): job seekers have their own accounts ('seeker'
//  guard) and never see the partner workspace.
// ══════════════════════════════════════════════════════════════════
Route::name('seeker.')->group(function () {
    // The occupation search used by the registration questions.
    Route::get('/occupation-search', [BeneficiaryController::class, 'pick'])->middleware('throttle:60,1')->name('occupations');
    Route::get('/employer-search', EmployerSearchController::class)->middleware('throttle:60,1')->name('employers');

    Route::middleware('guest:seeker')->group(function () {
        Route::get('/join', [JoinController::class, 'index'])->name('join');
        Route::post('/join/cv', [JoinController::class, 'readCv'])->middleware('throttle:10,1')->name('join.cv');
        Route::post('/join', [JoinController::class, 'store'])->middleware('throttle:10,1')->name('join.store');

        Route::get('/sign-in', [SeekerAuthController::class, 'create'])->name('login');
        Route::post('/sign-in', [SeekerAuthController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/sign-in/forgot', [SeekerAuthController::class, 'forgot'])->name('password.request');
        Route::post('/sign-in/forgot', [SeekerAuthController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/sign-in/reset/{token}', [SeekerAuthController::class, 'resetShow'])->name('password.reset');
        Route::post('/sign-in/reset', [SeekerAuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.store');
    });

    Route::middleware('seeker.auth')->group(function () {
        Route::post('/sign-out', [SeekerAuthController::class, 'destroy'])->name('logout');
        Route::get('/join/verify', [SeekerAuthController::class, 'verifyShow'])->name('verify');
        Route::post('/join/verify', [SeekerAuthController::class, 'verifyStore'])->middleware('throttle:10,1')->name('verify.store');
        Route::post('/join/verify/resend', [SeekerAuthController::class, 'verifyResend'])->middleware('throttle:6,1')->name('verify.resend');

        Route::middleware('seeker.verified')->group(function () {
            Route::get('/me', [MyProfileController::class, 'show'])->name('profile');
            Route::get('/me/edit', [MyProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/me', [MyProfileController::class, 'update'])->name('profile.update');
            Route::patch('/me/visibility', [MyProfileController::class, 'visibility'])->name('profile.visibility');
            Route::delete('/me', [MyProfileController::class, 'destroy'])->middleware('throttle:10,1')->name('profile.destroy');
            Route::get('/me/cv', [MyProfileController::class, 'download'])->name('cv.download');
            Route::post('/me/cv', [MyProfileController::class, 'upload'])->middleware('throttle:10,1')->name('cv.upload');
            Route::get('/me/cv/{uuid}', [MyProfileController::class, 'changes'])->whereUuid('uuid')->name('cv.changes');
            Route::post('/me/cv/{uuid}', [MyProfileController::class, 'apply'])->whereUuid('uuid')->name('cv.apply');
        });
    });
});

// ══════════════════════════════════════════════════════════════════
//  PREFERENCES (any signed-in user) — no page reload, idempotent
// ══════════════════════════════════════════════════════════════════
Route::middleware(['auth'])
    ->prefix('preferences')
    ->name('preferences.')
    ->group(function () {
        // Written as [Controller, 'method'] on purpose: a bare 'locale'
        // clashes with PHP's built-in Locale class and breaks every
        // artisan command ("Invalid route action: [locale]").
        Route::patch('/theme', [PreferenceController::class, 'theme'])->name('theme');
        Route::patch('/locale', [PreferenceController::class, 'locale'])->name('locale');
        Route::patch('/standard', [PreferenceController::class, 'standard'])->name('standard');
    });

// ══════════════════════════════════════════════════════════════════
//  ADMIN — Massar platform (super_admin) — /admin — admin.*
// ══════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'auth.session', 'verified', 'admin', 'no-duplicate'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // ── Partner organisations (Scope v2 §4 Company Onboarding) ──
        Route::middleware('can:platform.companies')->group(function () {
            Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
            Route::post('/companies', [AdminCompanyController::class, 'store'])->name('companies.store');
            Route::patch('/companies/{company}', [AdminCompanyController::class, 'update'])->name('companies.update');
            Route::patch('/companies/{company}/toggle-active', [AdminCompanyController::class, 'toggleActive'])->name('companies.toggle-active');
            Route::delete('/companies/{company}', [AdminCompanyController::class, 'destroy'])->name('companies.destroy');
        });

        Route::get('/activity', [AdminActivityController::class, 'index'])
            ->middleware('can:platform.activity')->name('activity.index');

        // ── Occupation backbone (Scope v2 §1) — browser + edition switch
        //    The data itself is loaded by `php artisan backbone:import`
        //    and `php artisan market:import`.
        Route::middleware('can:platform.backbone')->group(function () {
            Route::get('/backbone', [AdminBackboneController::class, 'index'])->name('backbone.index');
            Route::get('/backbone/units/{code}', [AdminBackboneController::class, 'unit'])
                ->where('code', '[0-9]{4}')->name('backbone.unit');
            Route::get('/backbone/esco/{escoOccupation}', [AdminBackboneController::class, 'esco'])
                ->whereNumber('escoOccupation')->name('backbone.esco');
            Route::get('/backbone/skills/{escoSkill}', [AdminBackboneController::class, 'skill'])
                ->whereNumber('escoSkill')->name('backbone.skill');
            // The one change allowed here: choose which labour market
            // edition is shown everywhere (after reviewing it).
            // Step 10.5: sub-sectors typed as "Other".
            Route::get('/sectors', [\App\Http\Controllers\Admin\SectorProposalController::class, 'index'])->name('sectors.index');
            Route::post('/sectors/proposals/{proposal}/add', [\App\Http\Controllers\Admin\SectorProposalController::class, 'add'])->whereNumber('proposal')->name('sectors.add');
            Route::post('/sectors/proposals/{proposal}/merge', [\App\Http\Controllers\Admin\SectorProposalController::class, 'merge'])->whereNumber('proposal')->name('sectors.merge');
            Route::post('/sectors/proposals/{proposal}/decline', [\App\Http\Controllers\Admin\SectorProposalController::class, 'decline'])->whereNumber('proposal')->name('sectors.decline');
            Route::patch('/backbone/editions/{edition}/use', [AdminBackboneController::class, 'useEdition'])
                ->whereNumber('edition')->name('backbone.editions.use');
        });

        // ── Rule promotion (Scope v2 §3 Learned Rules, layer 2) ─────
        Route::middleware('can:platform.rules')->group(function () {
            Route::get('/rule-requests', [AdminRuleRequestController::class, 'index'])->name('rule-requests.index');
            Route::post('/rule-requests/{rule}/promote', [AdminRuleRequestController::class, 'promote'])->whereNumber('rule')->name('rule-requests.promote');
            Route::post('/rule-requests/{rule}/decline', [AdminRuleRequestController::class, 'decline'])->whereNumber('rule')->name('rule-requests.decline');
            Route::delete('/massar-rules/{rule}', [AdminRuleRequestController::class, 'destroy'])->whereNumber('rule')->name('massar-rules.destroy');
        });

        // ── Reports across all partners (Step 14): counts only, "fewer than 5" ──
        Route::get('/reports', [ReportController::class, 'adminIndex'])->middleware('can:reports.view')->name('reports.index');
        Route::get('/reports/occupations', [ReportController::class, 'occupations'])->middleware('can:reports.view')->name('reports.occupations');
        Route::get('/reports/export', [ReportController::class, 'adminExport'])->middleware(['can:reports.view', 'can:reports.export'])->name('reports.export');

        // ── Own profile ─────────────────────────────────────────────
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

// ══════════════════════════════════════════════════════════════════
//  APP — partner workspace (company_admin + employee) — /app — app.*
// ══════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'auth.session', 'verified', 'member', 'no-duplicate'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Beneficiaries (Scope v2 §3 Module B · Registration & Profiles)
        //    {number} is the partner's own running number, looked up in
        //    the signed-in person's workspace only (see the controller).
        Route::middleware('can:beneficiaries.view')->group(function () {
            Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
            Route::get('/occupation-picker', [BeneficiaryController::class, 'pick'])->name('occupation-picker');
            Route::get('/employer-search', EmployerSearchController::class)->name('employer-search');
        });
        Route::middleware('can:beneficiaries.create')->group(function () {
            Route::get('/beneficiaries/create', [BeneficiaryController::class, 'create'])->name('beneficiaries.create');
            Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
        });
        Route::get('/beneficiaries/{number}', [BeneficiaryController::class, 'show'])
            ->whereNumber('number')->middleware('can:beneficiaries.view')->name('beneficiaries.show');
        Route::middleware('can:beneficiaries.edit')->group(function () {
            Route::get('/beneficiaries/{number}/edit', [BeneficiaryController::class, 'edit'])->whereNumber('number')->name('beneficiaries.edit');
            Route::patch('/beneficiaries/{number}', [BeneficiaryController::class, 'update'])->whereNumber('number')->name('beneficiaries.update');
        });

        // ── Searchable CV Bank (Scope v2 §3) ────────────────────────
        Route::middleware('can:beneficiaries.view')->group(function () {
            Route::get('/cv-bank', [CvBankController::class, 'index'])->name('cv-bank.index');
            Route::get('/cv-bank/occupations', [CvBankController::class, 'occupations'])->name('cv-bank.occupations');
        });

        // ── Module B — planned ──────────────────────────────────────
        // Public Talent Pool (Step 10): job seekers who registered themselves and chose to be seen.
        Route::middleware('can:pool.view')->group(function () {
            Route::get('/pool', [PoolController::class, 'index'])->name('pool.index');
            Route::get('/pool/{uuid}', [PoolController::class, 'show'])->whereUuid('uuid')->name('pool.show');
            Route::get('/pool/{uuid}/cv', [PoolController::class, 'cv'])->whereUuid('uuid')->middleware('can:cv.download')->name('pool.cv');
            Route::post('/pool/{uuid}/add', [PoolController::class, 'add'])->whereUuid('uuid')
                ->middleware(['can:pool.add', 'can:beneficiaries.create'])->name('pool.add');
        });
        // Guided Intake (Scope v2 §3): two doors into the same profile — with a CV, or questions only.
        Route::middleware('can:beneficiaries.create')->group(function () {
            Route::get('/intake', [IntakeController::class, 'index'])->name('intake.index');
            Route::post('/intake', [IntakeController::class, 'store'])->name('intake.store');
            Route::post('/intake/cv', [IntakeController::class, 'readCv'])->middleware('can:cv.upload')->name('intake.cv');
        });
        // Eligibility (Step 11's engine). Since Step 12 it lives INSIDE each Job and Training
        // (docs/SCOPE_JOBS_AND_TRAINING.md): check one person, the case worker's decision, notes.
        Route::middleware('can:eligibility.check')->group(function () {
            Route::post('/beneficiaries/{number}/eligibility', [EligibilityController::class, 'check'])->whereNumber('number')
                ->middleware('can:beneficiaries.view')->name('beneficiaries.eligibility');
            Route::post('/eligibility/runs/{run}/step', [EligibilityController::class, 'step'])->whereNumber('run')->name('eligibility.runs.step');
        });
        Route::middleware('can:eligibility.decide')->group(function () {
            Route::post('/eligibility/{id}/decide', [EligibilityController::class, 'decide'])->whereNumber('id')->name('eligibility.decide');
            Route::patch('/eligibility/{id}/notes', [EligibilityController::class, 'notes'])->whereNumber('id')->name('eligibility.notes');
        });

        // ── CV Bank: upload, read, review (Scope v2 §3) ─────────────
        Route::middleware('can:cv.upload')->group(function () {
            Route::get('/cv-upload', [CvUploadController::class, 'index'])->name('cv-upload.index');
            Route::post('/cv-upload/batches', [CvUploadController::class, 'batch'])->name('cv-upload.batch');
            Route::post('/cv-upload/batches/{batch}/files', [CvUploadController::class, 'file'])->whereNumber('batch')->name('cv-upload.file');
        });
        Route::middleware('can:cv.review')->group(function () {
            Route::get('/review-queue', [ReviewQueueController::class, 'index'])->name('review-queue.index');
            Route::get('/review-queue/{uuid}', [ReviewQueueController::class, 'show'])->whereUuid('uuid')->name('review-queue.show');
            Route::post('/review-queue/{uuid}/approve', [ReviewQueueController::class, 'approve'])->whereUuid('uuid')->name('review-queue.approve');
            Route::post('/review-queue/{uuid}/attach', [ReviewQueueController::class, 'attach'])->whereUuid('uuid')->name('review-queue.attach');
            Route::post('/review-queue/{uuid}/reject', [ReviewQueueController::class, 'reject'])->whereUuid('uuid')->name('review-queue.reject');
            // "Update from this CV": find the person, compare, make the ticked changes.
            Route::get('/review-queue/profiles', [ReviewQueueController::class, 'profiles'])->name('review-queue.profiles');
            Route::get('/review-queue/{uuid}/update/{number}', [ReviewQueueController::class, 'compare'])->whereUuid('uuid')->whereNumber('number')->name('review-queue.compare');
            Route::post('/review-queue/{uuid}/update/{number}', [ReviewQueueController::class, 'update'])->whereUuid('uuid')->whereNumber('number')->name('review-queue.update');
            Route::post('/review-queue/{uuid}/reread', [ReviewQueueController::class, 'reread'])->whereUuid('uuid')->name('review-queue.reread');
            // Teach a Learned Rule from the CV on screen.
            Route::post('/review-queue/{uuid}/teach', [ReviewQueueController::class, 'teach'])->whereUuid('uuid')->middleware('can:rules.manage')->name('review-queue.teach');
        });
        // Original CV files — sensitive; every download / opening is logged.
        Route::get('/cv-files/{uuid}', CvFileController::class)->whereUuid('uuid')->middleware('can:cv.download')->name('cv-files.show');

        // ── Opportunities ───────────────────────────────────────────
        // Jobs & Training (Step 12 · docs/SCOPE_JOBS_AND_TRAINING.md): the same screens for both
        // kinds, each with its eligibility inside. /app/jobs/… and /app/training/…
        foreach (\App\Models\Opportunity::SECTION as $kind => $section) {
            Route::prefix($section)->name("$section.")->group(function () use ($kind) {
                Route::middleware('can:opportunities.manage')->group(function () use ($kind) {
                    Route::get('/create', [OpportunityController::class, 'create'])->defaults('kind', $kind)->name('create');
                    Route::post('/', [OpportunityController::class, 'store'])->defaults('kind', $kind)->name('store');
                    Route::get('/{opportunity}/edit', [OpportunityController::class, 'edit'])->whereNumber('opportunity')->defaults('kind', $kind)->name('edit');
                    Route::patch('/{opportunity}', [OpportunityController::class, 'update'])->whereNumber('opportunity')->defaults('kind', $kind)->name('update');
                    Route::post('/{opportunity}/copy', [OpportunityController::class, 'copy'])->whereNumber('opportunity')->defaults('kind', $kind)->name('copy');
                    Route::post('/{opportunity}/close', [OpportunityController::class, 'close'])->whereNumber('opportunity')->defaults('kind', $kind)->name('close');
                    Route::post('/{opportunity}/reopen', [OpportunityController::class, 'reopen'])->whereNumber('opportunity')->defaults('kind', $kind)->name('reopen');
                    Route::delete('/{opportunity}', [OpportunityController::class, 'destroy'])->whereNumber('opportunity')->defaults('kind', $kind)->name('destroy');
                });
                Route::post('/{opportunity}/runs', [EligibilityController::class, 'start'])->whereNumber('opportunity')->defaults('kind', $kind)
                    ->middleware('can:eligibility.check')->name('runs.start');
                Route::middleware('can:opportunities.view')->group(function () use ($kind) {
                    Route::get('/', [OpportunityController::class, 'index'])->defaults('kind', $kind)->name('index');
                    Route::get('/{opportunity}', [OpportunityController::class, 'show'])->whereNumber('opportunity')->defaults('kind', $kind)->name('show');
                });
            });
        }
        // Matches (Step 13 · docs/SCOPE_MATCHES.md): refer Eligible people to a job or
        // training and follow them to Hired / Completed. /app/matches/…
        Route::middleware('can:matches.view')->group(function () {
            Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
            Route::get('/matches/{id}/timeline', [MatchController::class, 'timeline'])->whereNumber('id')->name('matches.timeline');
        });
        Route::middleware('can:matches.manage')->group(function () {
            Route::post('/matches', [MatchController::class, 'store'])->name('matches.store');
            Route::post('/matches/{id}/move', [MatchController::class, 'move'])->whereNumber('id')->name('matches.move');
            Route::post('/matches/{id}/back', [MatchController::class, 'back'])->whereNumber('id')->name('matches.back');
            Route::post('/matches/{id}/stop', [MatchController::class, 'stop'])->whereNumber('id')->name('matches.stop');
            Route::post('/matches/{id}/restart', [MatchController::class, 'restart'])->whereNumber('id')->name('matches.restart');
        });

        // ── Insights ────────────────────────────────────────────────
        // Occupations (Scope v2 §1 + §4 Shared Backbone Access) — the
        // same backbone and labour market data as the Super Admin sees,
        // read-only, with flagged figures removed.
        Route::middleware('can:occupations.view')->group(function () {
            Route::get('/occupations', [OccupationController::class, 'index'])->name('occupations.index');
            Route::get('/occupations/units/{code}', [OccupationController::class, 'unit'])
                ->where('code', '[0-9]{4}')->name('occupations.unit');
            Route::get('/occupations/esco/{escoOccupation}', [OccupationController::class, 'esco'])
                ->whereNumber('escoOccupation')->name('occupations.esco');
            Route::get('/occupations/skills/{escoSkill}', [OccupationController::class, 'skill'])
                ->whereNumber('escoSkill')->name('occupations.skill');
        });
        // Reports (Step 14 · docs/SCOPE_REPORTS.md): combined filters, counts or averages,
        // any date range, Excel / PDF, saved for the team.
        Route::middleware('can:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/occupations', [ReportController::class, 'occupations'])->name('reports.occupations');
            Route::post('/reports/saved', [ReportController::class, 'save'])->name('reports.save');
            Route::patch('/reports/saved/{id}', [ReportController::class, 'rename'])->whereNumber('id')->name('reports.rename');
            Route::delete('/reports/saved/{id}', [ReportController::class, 'forget'])->whereNumber('id')->name('reports.forget');
        });
        Route::get('/reports/export', [ReportController::class, 'export'])->middleware(['can:reports.view', 'can:reports.export'])->name('reports.export');

        // ── Settings ────────────────────────────────────────────────
        // Learned Rules (Scope v2 §3, layer 1): this workspace's rules, and Massar's (read-only).
        Route::get('/rules', [LearnedRuleController::class, 'index'])->middleware('can:rules.view')->name('rules.index');
        Route::middleware('can:rules.manage')->group(function () {
            Route::post('/rules', [LearnedRuleController::class, 'store'])->name('rules.store');
            Route::patch('/rules/{rule}', [LearnedRuleController::class, 'update'])->whereNumber('rule')->name('rules.update');
            Route::delete('/rules/{rule}', [LearnedRuleController::class, 'destroy'])->whereNumber('rule')->name('rules.destroy');
        });
        Route::middleware('can:rules.propose')->group(function () {
            Route::post('/rules/{rule}/propose', [LearnedRuleController::class, 'propose'])->whereNumber('rule')->name('rules.propose');
            Route::post('/rules/{rule}/withdraw', [LearnedRuleController::class, 'withdraw'])->whereNumber('rule')->name('rules.withdraw');
        });

        Route::middleware('can:team.manage')->group(function () {
            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::patch('/team/{user}', [TeamController::class, 'update'])->name('team.update');
            Route::patch('/team/{user}/toggle-active', [TeamController::class, 'toggleActive'])->name('team.toggle-active');
        });

        // ── Own profile ─────────────────────────────────────────────
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

// ── Auth Routes ────────────────────────────────────────────────
require __DIR__.'/auth.php';