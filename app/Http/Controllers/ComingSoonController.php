<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — ComingSoonController
//  Location: app/Http/Controllers/ComingSoonController.php
//
//  One shared page for every module that is in the Scope of Work but
//  not built yet. The route passes a `module` key
//  (->defaults('module', 'cv_upload')) and the page shows that
//  module's title, what it will do (from Scope v2) and its status.
//  The texts live in resources/js/lang/translations.js under
//  soon.<module>.*.
//
//  When a module is built, its route in routes/web.php is pointed at
//  the real controller and this page is simply no longer used for it.
// ══════════════════════════════════════════════════════════════════

class ComingSoonController extends Controller
{
    public const MODULES = [
        'pool', 'intake', 'cv_upload', 'review_queue',
        'occupations', 'rules',
        'rule_requests',
    ];

    public function __invoke(Request $request, string $module): Response
    {
        abort_unless(in_array($module, self::MODULES, true), 404);

        return Inertia::render('ComingSoon', [
            'module' => $module,
        ]);
    }
}
